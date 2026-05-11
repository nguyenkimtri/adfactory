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

    protected function updateProgress($percentage, $message)
    {
        $this->job->update(['progress' => $percentage, 'status_message' => $message]);
    }

    public function process()
    {
        try {
            $this->updateProgress(5, 'Hệ thống đang khởi động...');
            $this->job->update(['status' => 'processing']);

            // 1. Download
            $this->updateProgress(10, 'Đang tải tài nguyên...');
            $paths = $this->downloadResources();

            // 1.5. Subtitles
            if (($this->job->settings['auto_subtitle'] ?? false) === 'on' || ($this->job->settings['auto_subtitle'] ?? false) === true) {
                $this->updateProgress(30, 'Đang phân tích giọng nói (AI Whisper)...');
                $paths['subtitle'] = $this->transcribeAudio($paths['audio']);
                if (!$paths['subtitle']) Log::warning("Subtitle generation failed or returned null.");
            }

            // 2. Duration
            $audioDuration = $this->getDuration($paths['audio']);

            // 3. Prepare Video
            $this->updateProgress(50, 'Đang xử lý khung hình video...');
            $videoPath = $this->prepareVideo($paths['videos'], $audioDuration);

            // 4. Render
            $projectName = $this->job->project_name ?? 'video_' . $this->job->id;
            $safeProjectName = Str::slug($projectName, '_');
            if (empty($safeProjectName)) $safeProjectName = "video_" . $this->job->id;
            
            $outputPath = storage_path("app/public/exports/{$safeProjectName}.mp4");
            if (!file_exists(dirname($outputPath))) mkdir(dirname($outputPath), 0777, true);

            $this->updateProgress(70, 'Đang Render (UltraFast Mode)...');
            $this->render($videoPath, $paths['audio'], $paths['bg_music'] ?? null, $paths['logo'] ?? null, $paths['subtitle'] ?? null, $outputPath, $audioDuration);
            
            $this->updateProgress(100, 'Hoàn thành!');
            $this->job->update([
                'status' => 'completed',
                'output_path' => asset("storage/exports/{$safeProjectName}.mp4"),
            ]);

            $this->callWebhook();

        } catch (\Exception $e) {
            Log::error("CRITICAL ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        } finally {
            $this->cleanup();
        }
    }

    protected function downloadResources()
    {
        $paths = [];
        $paths['audio'] = $this->download($this->job->audio_url, 'main_audio');
        if ($this->job->bg_music_url) $paths['bg_music'] = $this->download($this->job->bg_music_url, 'bg_music');
        if ($this->job->logo_url) $paths['logo'] = $this->download($this->job->logo_url, 'logo');

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
        if (empty($files)) throw new \Exception("Download failed for URL: {$url}");
        return $files[0];
    }

    protected function getDuration($path)
    {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 \"{$path}\"";
        return (float) shell_exec($cmd);
    }

    protected function prepareVideo($videoPaths, $targetDuration)
    {
        $normalizedDir = "{$this->tempDir}/normalized";
        if (!file_exists($normalizedDir)) mkdir($normalizedDir, 0777, true);

        $format = $this->job->settings['format'] ?? '9:16';
        $res = ($format === '9:16') ? '1080:1920' : '1920:1080';

        $normPaths = [];
        foreach ($videoPaths as $i => $path) {
            $out = "{$normalizedDir}/v_{$i}.mp4";
            shell_exec("ffmpeg -y -i \"{$path}\" -vf \"scale={$res}:force_original_aspect_ratio=increase,crop={$res},setpts=PTS-STARTPTS\" -r 30 -c:v libx264 -preset superfast -an \"{$out}\" 2>&1");
            if (file_exists($out)) $normPaths[] = $out;
        }

        if (empty($normPaths)) throw new \Exception("No valid video sources to process.");
        if (count($normPaths) === 1) return $normPaths[0];

        $listFile = "{$this->tempDir}/list.txt";
        $content = "";
        foreach ($normPaths as $p) $content .= "file '" . realpath($p) . "'\n";
        file_put_contents($listFile, $content);

        $concatPath = "{$this->tempDir}/final_loop_source.mp4";
        shell_exec("ffmpeg -y -f concat -safe 0 -i \"{$listFile}\" -c copy \"{$concatPath}\" 2>&1");

        return file_exists($concatPath) ? $concatPath : $normPaths[0];
    }

    protected function transcribeAudio($audioPath)
    {
        $scriptPath = app_path('Services/whisper_service.py');
        $assPath = "{$this->tempDir}/subtitles.ass";
        // Dùng python trực tiếp, giả định đã có trong PATH
        $cmd = "python \"{$scriptPath}\" \"{$audioPath}\" \"{$assPath}\" 2>&1";
        Log::info("Running Whisper: $cmd");
        $output = shell_exec($cmd);
        Log::info("Whisper Result: $output");
        return file_exists($assPath) ? $assPath : null;
    }

    protected function render($videoPath, $audioPath, $bgMusicPath, $logoPath, $subtitlePath, $outputPath, $duration)
    {
        $format = $this->job->settings['format'] ?? '9:16';
        $res = ($format === '9:16') ? '1080:1920' : '1920:1080';

        $inputs = [];
        $inputs[] = "-stream_loop -1 -i " . escapeshellarg($videoPath); // 0:v
        $inputs[] = "-i " . escapeshellarg($audioPath); // 1:a
        
        $logoIdx = -1;
        if ($logoPath && file_exists($logoPath)) {
            $inputs[] = "-i " . escapeshellarg($logoPath);
            $logoIdx = count($inputs) - 1;
        }

        $bgIdx = -1;
        if ($bgMusicPath && file_exists($bgMusicPath)) {
            $inputs[] = "-i " . escapeshellarg($bgMusicPath);
            $bgIdx = count($inputs) - 1;
        }

        $vFilters = [];
        $vFilters[] = "[0:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res}[vbase]";
        $lastV = "vbase";

        if ($subtitlePath && file_exists($subtitlePath)) {
            $safeSubPath = str_replace([':', '\\'], ['\\:', '/'], $subtitlePath);
            $vFilters[] = "[{$lastV}]ass='{$safeSubPath}'[vsub]";
            $lastV = "vsub";
        }

        if ($logoIdx !== -1) {
            $vFilters[] = "[{$logoIdx}:v]scale=200:-1,format=rgba,colorchannelmixer=aa=0.8[logo]";
            // Công thức Bounce
            $vFilters[] = "[{$lastV}][logo]overlay=x='if(lte(mod(t,10),5), (W-w)*mod(t,5)/5, (W-w)*(1-mod(t,5)/5))':y='if(lte(mod(t,6),3), (H-h)*mod(t,3)/3, (H-h)*(1-mod(t,3)/3))'[vlogo]";
            $lastV = "vlogo";
        }

        $aFilters = [];
        $volA = ($this->job->settings['volume_audio'] ?? 100) / 100;
        $aFilters[] = "[1:a]volume={$volA}[amain]";
        $lastA = "amain";

        if ($bgIdx !== -1) {
            $volM = ($this->job->settings['volume_music'] ?? 20) / 100;
            $aFilters[] = "[{$bgIdx}:a]volume={$volM}[abg]";
            $aFilters[] = "[{$lastA}][abg]amix=inputs=2:duration=first[amixout]";
            $lastA = "amixout";
        }

        $filterStr = implode(';', array_merge($vFilters, $aFilters));
        
        $command = "ffmpeg -y " . implode(' ', $inputs) . " ";
        $command .= "-filter_complex " . escapeshellarg($filterStr) . " ";
        $command .= "-map \"[{$lastV}]\" -map \"[{$lastA}]\" -t " . escapeshellarg($duration) . " ";
        $command .= "-c:v libx264 -preset ultrafast -crf 23 -pix_fmt yuv420p -c:a aac -b:a 192k " . escapeshellarg($outputPath) . " 2>&1";

        Log::info("Final FFmpeg Command: $command");
        $output = shell_exec($command);
        
        if (!file_exists($outputPath) || filesize($outputPath) < 1000) {
            throw new \Exception("FFmpeg failed. Error: " . substr($output, -500));
        }
    }

    protected function cleanup()
    {
        try {
            if (file_exists($this->tempDir)) {
                $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($files as $fileinfo) { ($fileinfo->isDir() ? 'rmdir' : 'unlink')($fileinfo->getRealPath()); }
                rmdir($this->tempDir);
            }
        } catch (\Exception $e) { Log::warning("Cleanup error: " . $e->getMessage()); }
    }

    protected function callWebhook()
    {
        if ($url = $this->job->webhook_url) {
            Http::post($url, ['job_id' => $this->job->id, 'status' => 'completed', 'video_url' => $this->job->output_path]);
        }
    }
}
