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
        $this->tempDir = storage_path("app/temp/{$job->id}");
        $ytdlpName = (PHP_OS_FAMILY === 'Windows') ? 'yt-dlp.exe' : 'yt-dlp';
        $this->ytdlpPath = file_exists(base_path($ytdlpName)) ? base_path($ytdlpName) : $ytdlpName;
        if (!file_exists($this->tempDir)) mkdir($this->tempDir, 0777, true);
    }

    protected function updateProgress($percentage, $message)
    {
        $this->job->update(['progress' => $percentage, 'status_message' => $message]);
        // Bắn tín hiệu WebSocket Realtime
        broadcast(new VideoJobUpdated($this->job));
    }

    public function process()
    {
        try {
            $this->updateProgress(5, 'Đã nhận yêu cầu...');
            $this->job->update(['status' => 'processing']);
            broadcast(new VideoJobUpdated($this->job));

            $paths = $this->downloadResources();
            if ($this->job->settings['auto_subtitle'] ?? false) {
                $this->updateProgress(30, 'Đang tạo phụ đề AI...');
                $paths['subtitle'] = $this->transcribeAudio($paths['audio']);
            }

            $audioDuration = $this->getDuration($paths['audio']);
            $videoPath = $this->prepareVideo($paths['videos'], $audioDuration);
            
            $projectName = $this->job->project_name ?? 'video_' . $this->job->id;
            $safeProjectName = Str::slug($projectName, '_');
            $outputPath = storage_path("app/public/exports/{$safeProjectName}.mp4");
            if (!file_exists(dirname($outputPath))) mkdir(dirname($outputPath), 0777, true);

            $this->updateProgress(70, 'Đang Render Final Video...');
            $this->render($videoPath, $paths['audio'], $paths['bg_music'] ?? null, $paths['logo'] ?? null, $paths['subtitle'] ?? null, $outputPath, $audioDuration);
            
            $this->job->update([
                'status' => 'completed',
                'progress' => 100,
                'output_path' => asset("storage/exports/{$safeProjectName}.mp4"),
            ]);
            broadcast(new VideoJobUpdated($this->job));
            $this->callWebhook();

        } catch (\Exception $e) {
            Log::error("WS ERROR: " . $e->getMessage());
            $this->job->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
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
        foreach ($this->job->video_sources ?? [] as $i => $url) $paths['videos'][] = $this->download($url, "v_{$i}");
        return $paths;
    }

    protected function download($url, $filename) {
        $path = "{$this->tempDir}/" . Str::slug($filename, '_');
        shell_exec("\"{$this->ytdlpPath}\" -o \"{$path}.%(ext)s\" \"{$url}\" --no-playlist 2>&1");
        $files = glob("{$path}.*");
        if (empty($files)) throw new \Exception("Download fail: {$url}");
        return $files[0];
    }

    protected function getDuration($path) {
        return (float) shell_exec("ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 \"{$path}\"");
    }

    protected function prepareVideo($videoPaths, $targetDuration) {
        $res = ($this->job->settings['format'] ?? '9:16' === '9:16') ? '1080:1920' : '1920:1080';
        $normDir = "{$this->tempDir}/norm"; if (!file_exists($normDir)) mkdir($normDir, 0777, true);
        $normPaths = [];
        foreach ($videoPaths as $i => $path) {
            $out = "{$normDir}/{$i}.mp4";
            shell_exec("ffmpeg -y -i \"{$path}\" -vf \"scale={$res}:force_original_aspect_ratio=increase,crop={$res},setpts=PTS-STARTPTS\" -r 30 -c:v libx264 -preset superfast -an \"{$out}\" 2>&1");
            if (file_exists($out)) $normPaths[] = $out;
        }
        $listFile = "{$this->tempDir}/list.txt"; $content = "";
        foreach ($normPaths as $p) $content .= "file '" . realpath($p) . "'\n";
        file_put_contents($listFile, $content);
        $concatPath = "{$this->tempDir}/concat.mp4";
        shell_exec("ffmpeg -y -f concat -safe 0 -i \"{$listFile}\" -c copy \"{$concatPath}\" 2>&1");
        return $concatPath;
    }

    protected function transcribeAudio($audioPath) {
        $scriptPath = base_path('app/Services/whisper_service.py'); $assPath = "{$this->tempDir}/s.ass";
        $py = (PHP_OS_FAMILY === 'Windows') ? 'python' : 'python3';
        shell_exec("{$py} \"{$scriptPath}\" \"{$audioPath}\" \"{$assPath}\" 2>&1");
        return file_exists($assPath) ? $assPath : null;
    }

    protected function render($videoPath, $audioPath, $bgMusicPath, $logoPath, $subtitlePath, $outputPath, $duration) {
        $res = ($this->job->settings['format'] ?? '9:16' === '9:16') ? '1080:1920' : '1920:1080';
        $inputs = ["-stream_loop -1 -i \"{$videoPath}\"", "-i \"{$audioPath}\""];
        if ($logoPath) $inputs[] = "-ignore_loop 0 -loop 1 -i \"{$logoPath}\"";
        if ($bgMusicPath) $inputs[] = "-i \"{$bgMusicPath}\"";
        
        $vFilters = ["[0:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res}[vbase]"];
        $lastV = "vbase";
        if ($subtitlePath) { $vFilters[] = "[{$lastV}]ass='".str_replace([':', '\\'], ['\\:', '/'], $subtitlePath)."'[vsub]"; $lastV = "vsub"; }
        if ($logoPath) { 
            $vFilters[] = "[2:v]scale=200:-1,format=rgba,colorchannelmixer=aa=0.8[logo]";
            $vFilters[] = "[{$lastV}][logo]overlay=x='if(lte(mod(t,10),5), (W-w)*mod(t,5)/5, (W-w)*(1-mod(t,5)/5))':y='if(lte(mod(t,6),3), (H-h)*mod(t,3)/3, (H-h)*(1-mod(t,3)/3))':shortest=1[vlogo]"; 
            $lastV = "vlogo"; 
        }
        $aFilters = ["[1:a]volume=1[amain]"]; $lastA = "amain";
        if ($bgMusicPath) { $aFilters[] = "[3:a]volume=0.2[abg]"; $aFilters[] = "[{$lastA}][abg]amix=inputs=2:duration=first[amixout]"; $lastA = "amixout"; }
        
        $filterStr = implode(';', array_merge($vFilters, $aFilters));
        shell_exec("ffmpeg -y " . implode(' ', $inputs) . " -filter_complex \"{$filterStr}\" -map \"[{$lastV}]\" -map \"[{$lastA}]\" -t {$duration} -c:v libx264 -preset ultrafast -pix_fmt yuv420p -c:a aac \"{$outputPath}\" 2>&1");
    }

    protected function cleanup() { try { if (file_exists($this->tempDir)) { shell_exec((PHP_OS_FAMILY === 'Windows' ? "rmdir /s /q " : "rm -rf ") . escapeshellarg($this->tempDir)); } } catch (\Exception $e) {} }
    protected function callWebhook() { if ($url = $this->job->webhook_url) Http::post($url, ['job_id' => $this->job->id, 'status' => 'completed', 'video_url' => $this->job->output_path]); }
}
