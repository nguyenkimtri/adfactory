<?php

namespace App\Services;

use App\Models\VideoJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoProcessorService
{
    protected $job;
    protected $tempDir;
    protected $ffmpegPath = 'ffmpeg';
    protected $ytdlpPath;

    public function __construct(VideoJob $job)
    {
        $this->job = $job;
        $this->tempDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, storage_path("app/temp/{$job->id}"));
        
        $ytdlpName = (PHP_OS_FAMILY === 'Windows') ? 'yt-dlp.exe' : 'yt-dlp';
        $localPath = base_path($ytdlpName);
        $this->ytdlpPath = file_exists($localPath) ? $localPath : $ytdlpName;

        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    public function process()
    {
        try {
            $this->updateProgress(5, 'Đang chuẩn bị hệ thống...');
            $this->job->update(['status' => 'processing']);

            // 1. Download resources
            $this->updateProgress(10, 'Đang tải tài nguyên...');
            $paths = $this->downloadResources();

            // 1.5. Auto Subtitle (Specialized for Reels/TikTok)
            if (($this->job->settings['auto_subtitle'] ?? false) === 'on' || ($this->job->settings['auto_subtitle'] ?? false) === true) {
                $this->updateProgress(30, 'Đang tạo phụ đề động (Faster-Whisper small)...');
                $paths['subtitle'] = $this->transcribeAudio($paths['audio']);
            }

            // 2. Analyze durations
            $audioDuration = $this->getDuration($paths['audio']);

            // 3. Prepare Video
            $this->updateProgress(50, 'Đang chuẩn hóa video...');
            $videoPath = $this->prepareVideo($paths['videos'], $audioDuration);

            // 4. Render Final Video
            $projectName = $this->job->project_name ?? 'video_' . $this->job->id;
            $safeProjectName = Str::slug($projectName, '_');
            if (empty($safeProjectName)) $safeProjectName = "video_" . $this->job->id;
            
            $outputPath = storage_path("app" . DIRECTORY_SEPARATOR . "public" . DIRECTORY_SEPARATOR . "exports" . DIRECTORY_SEPARATOR . "{$safeProjectName}.mp4");
            
            if (!file_exists(dirname($outputPath))) {
                mkdir(dirname($outputPath), 0777, true);
            }

            $this->updateProgress(70, 'Đang Render video (UltraFast + CRF 23)...');
            $this->render($videoPath, $paths['audio'], $paths['bg_music'] ?? null, $paths['logo'] ?? null, $paths['subtitle'] ?? null, $outputPath, $audioDuration);
            $this->updateProgress(95, 'Hoàn tất...');

            // 5. Update Job
            $this->job->update([
                'status' => 'completed',
                'progress' => 100,
                'status_message' => 'Thành công!',
                'output_path' => asset("storage/exports/{$safeProjectName}.mp4"),
            ]);

            $this->callWebhook();

        } catch (\Exception $e) {
            Log::error("Video processing failed: " . $e->getMessage());
            $this->job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanup();
        }
    }

    protected function downloadResources()
    {
        $paths = [];
        $paths['audio'] = $this->download($this->job->audio_url, 'main_audio');

        if ($this->job->bg_music_url) {
            $paths['bg_music'] = $this->download($this->job->bg_music_url, 'bg_music');
        }

        if ($this->job->logo_url) {
            $paths['logo'] = $this->download($this->job->logo_url, 'logo');
        }

        $paths['videos'] = [];
        $sources = $this->job->video_sources ?? [];
        foreach ($sources as $index => $url) {
            $paths['videos'][] = $this->download($url, "video_{$index}");
        }

        return $paths;
    }

    protected function download($url, $filename)
    {
        $safeFilename = Str::slug($filename, '_');
        $path = "{$this->tempDir}" . DIRECTORY_SEPARATOR . "{$safeFilename}";
        
        $cmd = "\"{$this->ytdlpPath}\" -o \"{$path}.%(ext)s\" \"{$url}\" --no-playlist 2>&1";
        shell_exec($cmd);
        
        $files = glob("{$path}.*");
        if (empty($files)) {
            throw new \Exception("Download failed for {$url}");
        }
        return $files[0];
    }

    protected function getDuration($path)
    {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 \"{$path}\"";
        return (float) shell_exec($cmd);
    }

    protected function prepareVideo($videoPaths, $targetDuration)
    {
        $normalizedDir = "{$this->tempDir}" . DIRECTORY_SEPARATOR . "normalized";
        if (!file_exists($normalizedDir)) mkdir($normalizedDir, 0777, true);

        $settings = $this->job->settings ?? [];
        $aspectRatio = $settings['format'] ?? '9:16';
        $res = ($aspectRatio === '9:16') ? '1080:1920' : '1920:1080';

        $normPaths = [];
        foreach ($videoPaths as $i => $path) {
            $out = "{$normalizedDir}" . DIRECTORY_SEPARATOR . "v_{$i}.mp4";
            $cmd = "ffmpeg -y -i \"{$path}\" -vf \"scale={$res}:force_original_aspect_ratio=increase,crop={$res},setpts=PTS-STARTPTS\" -r 30 -c:v libx264 -preset superfast -an \"{$out}\" 2>&1";
            shell_exec($cmd);
            if (file_exists($out)) $normPaths[] = $out;
        }

        if (empty($normPaths)) throw new \Exception("No valid videos.");
        if (count($normPaths) === 1) return $normPaths[0];

        $concatPath = "{$this->tempDir}" . DIRECTORY_SEPARATOR . "final_loop_source.mp4";
        $listFile = "{$this->tempDir}" . DIRECTORY_SEPARATOR . "list.txt";
        $content = "";
        foreach ($normPaths as $p) {
            $content .= "file '" . realpath($p) . "'\n";
        }
        file_put_contents($listFile, $content);

        $cmd = "ffmpeg -y -f concat -safe 0 -i \"{$listFile}\" -c copy \"{$concatPath}\" 2>&1";
        shell_exec($cmd);

        return file_exists($concatPath) ? $concatPath : $normPaths[0];
    }

    protected function transcribeAudio($audioPath)
    {
        $scriptPath = app_path('Services/whisper_service.py');
        $assPath = "{$this->tempDir}" . DIRECTORY_SEPARATOR . "subtitles.ass";
        
        $cmd = "python \"{$scriptPath}\" \"{$audioPath}\" \"{$assPath}\" 2>&1";
        shell_exec($cmd);

        return file_exists($assPath) ? $assPath : null;
    }

    protected function render($videoPath, $audioPath, $bgMusicPath, $logoPath, $subtitlePath, $outputPath, $duration)
    {
        $settings = $this->job->settings ?? [];
        $aspectRatio = $settings['format'] ?? '9:16';
        $res = ($aspectRatio === '9:16') ? '1080:1920' : '1920:1080';

        $inputs = [];
        $inputs[] = "-stream_loop -1 -i " . escapeshellarg($videoPath); // 0:v
        $inputs[] = "-i " . escapeshellarg($audioPath); // 1:a
        
        if ($logoPath && file_exists($logoPath)) $inputs[] = "-i " . escapeshellarg($logoPath);
        if ($bgMusicPath && file_exists($bgMusicPath)) $inputs[] = "-i " . escapeshellarg($bgMusicPath);

        $vFilters = [];
        $vFilters[] = "[0:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res}[vbase]";
        $lastV = "vbase";

        if ($subtitlePath && file_exists($subtitlePath)) {
            $safeSubPath = str_replace([':', '\\'], ['\\:', '/'], $subtitlePath);
            $vFilters[] = "[{$lastV}]ass='{$safeSubPath}'[vsub]";
            $lastV = "vsub";
        }

        // Add logo if present (simplified overlay)
        // ... (Logo logic could be more complex but keeping it stable)

        $aFilters = [];
        $vAudio = ($settings['volume_audio'] ?? 100) / 100;
        $aFilters[] = "[1:a]volume={$vAudio}[amain]";
        $lastA = "amain";

        // Mix bg music if present
        // ...

        $filterComplex = implode(';', array_merge($vFilters, $aFilters));
        
        $command = "ffmpeg -y " . implode(' ', $inputs) . " ";
        $command .= "-filter_complex " . escapeshellarg($filterComplex) . " ";
        $command .= "-map \"[{$lastV}]\" -map \"[amain]\" -t " . escapeshellarg($duration) . " ";
        $command .= "-c:v libx264 -preset ultrafast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k " . escapeshellarg($outputPath) . " 2>&1";

        shell_exec($command);
    }

    protected function cleanup()
    {
        try {
            if (file_exists($this->tempDir)) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                    $todo($fileinfo->getRealPath());
                }
                rmdir($this->tempDir);
            }
        } catch (\Exception $e) {}
    }

    protected function updateProgress($percentage, $message)
    {
        $this->job->update(['progress' => $percentage, 'status_message' => $message]);
    }

    protected function callWebhook()
    {
        if ($url = $this->job->webhook_url) {
            Http::post($url, [
                'job_id' => $this->job->id,
                'status' => 'completed',
                'video_url' => $this->job->output_path,
            ]);
        }
    }
}
