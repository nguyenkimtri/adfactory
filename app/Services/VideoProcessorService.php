<?php

namespace App\Services;

use App\Models\VideoJob;
use App\Events\VideoJobUpdated;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VideoProcessorService
{
    protected $job;
    protected $tempDir;
    protected $ytdlpPath;

    public function __construct(VideoJob $job)
    {
        $this->job = $job;
        // Chuẩn hóa đường dẫn cho cả Windows và Linux
        $this->tempDir = storage_path("app/temp/{$job->id}");
        $ytdlpName = (PHP_OS_FAMILY === 'Windows') ? 'yt-dlp.exe' : 'yt-dlp';
        $this->ytdlpPath = file_exists(base_path($ytdlpName)) ? base_path($ytdlpName) : $ytdlpName;
        
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function updateProgress($percentage, $message)
    {
        $this->job->update(['progress' => $percentage, 'status_message' => $message]);
        broadcast(new VideoJobUpdated($this->job));
    }

    public function process()
    {
        try {
            $this->updateProgress(5, 'Đang chuẩn bị tài nguyên...');
            $this->job->update(['status' => 'processing']);
            broadcast(new VideoJobUpdated($this->job));

            $paths = $this->downloadResources();
            
            if ($this->job->settings['auto_subtitle'] ?? false) {
                $this->updateProgress(30, 'AI đang tạo phụ đề...');
                $paths['subtitle'] = $this->transcribeAudio($paths['audio']);
            }

            $audioDuration = $this->getDuration($paths['audio']);
            $videoPath = $this->prepareVideo($paths['videos'], $audioDuration);
            
            $projectName = $this->job->project_name ?? 'video_' . $this->job->id;
            $safeProjectName = Str::slug($projectName, '_');
            $outputPath = storage_path("app/public/exports/{$safeProjectName}.mp4");
            if (!file_exists(dirname($outputPath))) mkdir(dirname($outputPath), 0777, true);

            $this->updateProgress(70, 'Đang Render Video Final...');
            $this->render($videoPath, $paths['audio'], $paths['bg_music'] ?? null, $paths['logo'] ?? null, $paths['subtitle'] ?? null, $outputPath, $audioDuration);
            
            $this->job->update([
                'status' => 'completed',
                'progress' => 100,
                'output_path' => asset("storage/exports/{$safeProjectName}.mp4"),
            ]);
            broadcast(new VideoJobUpdated($this->job));
            $this->callWebhook();

        } catch (\Exception $e) {
            Log::error("Render Error Job {$this->job->id}: " . $e->getMessage());
            $this->job->update([
                'status' => 'failed', 
                'error_message' => "Render failed: " . Str::limit($e->getMessage(), 200)
            ]);
            broadcast(new VideoJobUpdated($this->job));
        } finally {
            $this->cleanup();
        }
    }

    protected function downloadResources() {
        $paths = ['audio' => $this->download($this->job->audio_url, 'main_audio')];
        if ($this->job->bg_music_url) $paths['bg_music'] = $this->download($this->job->bg_music_url, 'bg_music');
        if ($this->job->logo_url) $paths['logo'] = $this->download($this->job->logo_url, 'logo');
        $paths['videos'] = [];
        foreach ($this->job->video_sources ?? [] as $i => $url) {
            $paths['videos'][] = $this->download($url, "v_{$i}");
        }
        return $paths;
    }

    protected function download($url, $filename) {
        $path = "{$this->tempDir}/" . Str::slug($filename, '_');
        $cmd = "\"{$this->ytdlpPath}\" -o \"{$path}.%(ext)s\" \"{$url}\" --no-playlist 2>&1";
        shell_exec($cmd);
        $files = glob("{$path}.*");
        if (empty($files)) throw new \Exception("Không thể tải: {$url}");
        return $files[0];
    }

    protected function getDuration($path) {
        return (float) shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 \"{$path}\"");
    }

    protected function prepareVideo($videoPaths, $targetDuration) {
        $res = (($this->job->settings['format'] ?? '9:16') === '9:16') ? '1080:1920' : '1920:1080';
        $normDir = "{$this->tempDir}/norm"; 
        if (!file_exists($normDir)) mkdir($normDir, 0777, true);
        
        $normPaths = [];
        foreach ($videoPaths as $i => $path) {
            $out = "{$normDir}/{$i}.mp4";
            shell_exec("ffmpeg -y -i \"{$path}\" -vf \"scale={$res}:force_original_aspect_ratio=increase,crop={$res},setpts=PTS-STARTPTS\" -r 30 -c:v libx264 -preset superfast -an \"{$out}\" 2>&1");
            if (file_exists($out)) $normPaths[] = $out;
        }

        if (empty($normPaths)) throw new \Exception("Không có video nguồn nào hợp lệ.");

        $listFile = "{$this->tempDir}/list.txt"; 
        $content = "";
        foreach ($normPaths as $p) $content .= "file '" . realpath($p) . "'\n";
        file_put_contents($listFile, $content);
        
        $concatPath = "{$this->tempDir}/concat.mp4";
        shell_exec("ffmpeg -y -f concat -safe 0 -i \"{$listFile}\" -c copy \"{$concatPath}\" 2>&1");
        return $concatPath;
    }

    protected function transcribeAudio($audioPath) {
        $scriptPath = base_path('app/Services/whisper_service.py');
        $assPath = "{$this->tempDir}/s.ass";
        $py = (PHP_OS_FAMILY === 'Windows') ? 'python' : 'python3';
        shell_exec("{$py} \"{$scriptPath}\" \"{$audioPath}\" \"{$assPath}\" 2>&1");
        return file_exists($assPath) ? $assPath : null;
    }

    protected function render($videoPath, $audioPath, $bgMusicPath, $logoPath, $subtitlePath, $outputPath, $duration) {
        $res = (($this->job->settings['format'] ?? '9:16') === '9:16') ? '1080:1920' : '1920:1080';
        $inputs = ["-stream_loop -1 -i \"{$videoPath}\"", "-i \"{$audioPath}\""];
        
        if ($logoPath) $inputs[] = "-ignore_loop 0 -loop 1 -i \"{$logoPath}\"";
        if ($bgMusicPath) $inputs[] = "-i \"{$bgMusicPath}\"";
        
        $vFilters = ["[0:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res}[vbase]"];
        $lastV = "vbase";

        if ($subtitlePath) {
            // Sửa lỗi thoát chuỗi đường dẫn phụ đề cho Linux
            $safeAssPath = str_replace([':', '\\'], ['\\:', '/'], realpath($subtitlePath));
            if (PHP_OS_FAMILY !== 'Windows') {
                $safeAssPath = str_replace(':', '\:', realpath($subtitlePath));
            }
            $vFilters[] = "[{$lastV}]ass='{$safeAssPath}'[vsub]";
            $lastV = "vsub";
        }

        if ($logoPath) { 
            $opacity = ($this->job->settings['logo_opacity'] ?? 80) / 100;
            $size = $this->job->settings['logo_size'] ?? 200;
            $speed = $this->job->settings['logo_speed'] ?? 5;
            
            $vFilters[] = "[2:v]scale={$size}:-1,format=rgba,colorchannelmixer=aa={$opacity}[logo]";
            // Bouncing Logic chuyên nghiệp
            $vFilters[] = "[{$lastV}][logo]overlay=x='if(lte(mod(t,10),5), (W-w)*mod(t,5)/5, (W-w)*(1-mod(t,5)/5))':y='if(lte(mod(t,6),3), (H-h)*mod(t,3)/3, (H-h)*(1-mod(t,3)/3))':shortest=1[vlogo]"; 
            $lastV = "vlogo"; 
        }

        $volMain = ($this->job->settings['volume_audio'] ?? 100) / 100;
        $aFilters = ["[1:a]volume={$volMain}[amain]"]; 
        $lastA = "amain";

        if ($bgMusicPath) { 
            $volMusic = ($this->job->settings['volume_music'] ?? 20) / 100;
            $aFilters[] = "[3:a]volume={$volMusic}[abg]"; 
            $aFilters[] = "[{$lastA}][abg]amix=inputs=2:duration=first[amixout]"; 
            $lastA = "amixout"; 
        }
        
        $filterStr = implode(';', array_merge($vFilters, $aFilters));
        $cmd = "ffmpeg -y " . implode(' ', $inputs) . " -filter_complex \"{$filterStr}\" -map \"[{$lastV}]\" -map \"[{$lastA}]\" -t {$duration} -c:v libx264 -preset ultrafast -pix_fmt yuv420p -c:a aac \"{$outputPath}\" 2>&1";
        
        $output = shell_exec($cmd);
        if (!file_exists($outputPath)) {
            throw new \Exception("FFmpeg failed to create output file. Log: " . substr($output, -500));
        }
    }

    protected function cleanup() {
        try {
            if (file_exists($this->tempDir)) {
                shell_exec((PHP_OS_FAMILY === 'Windows' ? "rmdir /s /q " : "rm -rf ") . escapeshellarg($this->tempDir));
            }
        } catch (\Exception $e) {}
    }

    protected function callWebhook() {
        if ($url = $this->job->webhook_url) {
            Http::post($url, ['job_id' => $this->job->id, 'status' => 'completed', 'video_url' => $this->job->output_path]);
        }
    }
}
