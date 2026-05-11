<?php

namespace App\Services;

use App\Models\VideoJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoProcessorService
{
    protected $job;
    protected $tempDir;

    public function __construct(VideoJob $job)
    {
        $this->job = $job;
        $this->tempDir = storage_path("app/temp/{$job->id}");
        if (!file_exists($this->tempDir)) mkdir($this->tempDir, 0777, true);
    }

    public function process()
    {
        try {
            $this->job->update(['status' => 'processing', 'progress' => 5, 'status_message' => 'Bắt đầu xử lý...']);
            $this->updateProgress(10, 'Đang tải tài nguyên...');
            // Đảm bảo lấy đúng tên cột từ Database
            $videoPaths = $this->downloadResources($this->job->video_sources ?? [], 'v');
            $audioPaths = $this->downloadResources($this->job->audio_url ?? [], 'a');
            
            $bgMusicPath = null;
            if (!empty($this->job->bg_music_url)) {
                $bgPaths = $this->downloadResources($this->job->bg_music_url, 'bg');
                $bgMusicPath = $this->concatAudio($bgPaths, 'bg_final.mp3');
            }

            $logoPath = null;
            if ($this->job->logo_url) {
                $logoPath = $this->downloadFile($this->job->logo_url, 'logo.png');
            }

            $this->updateProgress(30, 'Đang chuẩn bị dữ liệu...');
            $audioPath = $this->concatAudio($audioPaths, 'main_audio.mp3');
            $audioDuration = $this->getDuration($audioPath);
            $videoPath = $this->prepareVideo($videoPaths, $audioDuration);

            $subtitlePath = null;
            if ($this->job->settings['subtitles'] ?? true) {
                $this->updateProgress(40, 'AI đang tạo phụ đề...');
                $subtitlePath = $this->transcribeAudio($audioPath);
            }

            $this->updateProgress(70, 'Đang Render Video Final...');
            $fileName = 'vd-factory-' . $this->job->id . date('dmY') . '.mp4';
            $outputPath = public_path("exports/{$fileName}");
            if (!file_exists(public_path('exports'))) mkdir(public_path('exports'), 0777, true);

            $this->render($videoPath, $audioPath, $bgMusicPath, $logoPath, $subtitlePath, $outputPath, $audioDuration);

            $this->job->update([
                'status' => 'completed',
                'progress' => 100,
                'status_message' => 'Hoàn thành!',
                'output_path' => asset("exports/{$fileName}"),
                'completed_at' => now(),
            ]);

            $this->callWebhook();
        } catch (\Exception $e) {
            Log::error("Job {$this->job->id} failed: " . $e->getMessage());
            $this->job->update([
                'status' => 'failed',
                'status_message' => 'Lỗi: ' . $e->getMessage(),
                'error_message' => $e->getMessage(),
            ]);
        } finally {
            $this->cleanup();
        }
    }

    protected function updateProgress($percent, $message)
    {
        $this->job->update(['progress' => $percent, 'status_message' => $message]);
    }

    protected function downloadResources($urls, $prefix)
    {
        $paths = [];
        if (!is_array($urls)) return $paths;
        foreach ($urls as $i => $url) {
            $paths[] = $this->downloadFile($url, "{$prefix}_{$i}");
        }
        return $paths;
    }

    protected function downloadFile($url, $name)
    {
        $path = "{$this->tempDir}/{$name}";
        $cmd = "yt-dlp -f \"best\" -o \"{$path}.%(ext)s\" " . escapeshellarg($url) . " 2>&1";
        shell_exec($cmd);
        
        $files = glob("{$path}.*");
        if (empty($files)) throw new \Exception("Không thể tải file: {$url}");
        return $files[0];
    }

    protected function getDuration($path)
    {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path);
        return (float) shell_exec($cmd);
    }

    protected function concatAudio($paths, $outName)
    {
        $outputPath = "{$this->tempDir}/{$outName}";
        if (count($paths) === 1) {
            // Ép về Stereo ngay từ file gốc
            shell_exec("ffmpeg -y -i " . escapeshellarg($paths[0]) . " -ac 2 -ar 44100 -acodec libmp3lame " . escapeshellarg($outputPath));
            return $outputPath;
        }

        $inputs = "";
        $filter = "";
        foreach ($paths as $i => $p) {
            $inputs .= "-i " . escapeshellarg($p) . " ";
            $filter .= "[{$i}:a]aresample=44100,pan=stereo[a{$i}];";
        }
        $count = count($paths);
        for($i=0;$i<$count;$i++) $filter .= "[a{$i}]";
        $filter .= "concat=n={$count}:v=0:a=1[outa]";

        $cmd = "ffmpeg -y {$inputs} -filter_complex \"{$filter}\" -map \"[outa]\" -ac 2 -ar 44100 " . escapeshellarg($outputPath) . " 2>&1";
        shell_exec($cmd);
        return $outputPath;
    }

    protected function prepareVideo($paths, $duration)
    {
        $outputPath = "{$this->tempDir}/concat.mp4";
        if (count($paths) === 1) {
            shell_exec("ffmpeg -y -i " . escapeshellarg($paths[0]) . " -c:v libx264 -preset ultrafast " . escapeshellarg($outputPath));
            return $outputPath;
        }

        $listPath = "{$this->tempDir}/list.txt";
        $content = "";
        foreach ($paths as $p) $content .= "file '" . str_replace("'", "'\\''", $p) . "'\n";
        file_put_contents($listPath, $content);

        $cmd = "ffmpeg -y -f concat -safe 0 -i \"{$listPath}\" -c:v libx264 -preset ultrafast \"{$outputPath}\" 2>&1";
        shell_exec($cmd);
        return $outputPath;
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
        
        $inputs = [
            "-stream_loop -1 -i " . escapeshellarg($videoPath), // Index 0
            "-i " . escapeshellarg($audioPath) // Index 1
        ];
        
        $logoIndex = null;
        if ($logoPath) {
            $inputs[] = "-loop 1 -i " . escapeshellarg($logoPath);
            $logoIndex = count($inputs) - 1;
        }

        $bgMusicIndex = null;
        if ($bgMusicPath) {
            $inputs[] = "-stream_loop -1 -i " . escapeshellarg($bgMusicPath);
            $bgMusicIndex = count($inputs) - 1;
        }
        
        $vFilters = ["[0:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res}[vbase]"];
        $lastV = "vbase";

        if ($subtitlePath) {
            $realPath = realpath($subtitlePath);
            $safeAssPath = str_replace([':', '\\', "'"], ["\\:", '/', "'\\''"], $realPath);
            $vFilters[] = "[{$lastV}]ass='{$safeAssPath}'[vsub]";
            $lastV = "vsub";
        }

        if ($logoIndex !== null) { 
            $opacity = ($this->job->settings['logo_opacity'] ?? 80) / 100;
            $size = $this->job->settings['logo_size'] ?? 200;
            $speed = ($this->job->settings['logo_speed'] ?? 5);
            $durX = 15 / $speed; $durY = 11 / $speed;
            
            $vFilters[] = "[{$logoIndex}:v]scale={$size}:-1,format=rgba,colorchannelmixer=aa={$opacity}[logo]";
            $vFilters[] = "[{$lastV}][logo]overlay=x='if(lte(mod(t,{$durX}*2),{$durX}), (W-w)*mod(t,{$durX})/{$durX}, (W-w)*(1-mod(t,{$durX})/{$durX}))':y='if(lte(mod(t,{$durY}*2),{$durY}), (H-h)*mod(t,{$durY})/{$durY}, (H-h)*(1-mod(t,{$durY})/{$durY}))'[vlogo]"; 
            $lastV = "vlogo"; 
        }

        $volMain = ($this->job->settings['volume_audio'] ?? 100) / 100;
        $volVideo = ($this->job->settings['volume_video'] ?? 0) / 100;
        $volMusic = ($this->job->settings['volume_music'] ?? 20) / 100;

        // --- BƯỚC MỚI: TRỘN ÂM THANH RIÊNG BIỆT ĐỂ ĐẢM BẢO LUÔN CÓ TIẾNG ---
        $mixedAudioPath = "{$this->tempDir}/final_mixed.mp3";
        $aInputs = ["-i " . escapeshellarg($audioPath)];
        $aFilters = ["[0:a]aresample=44100,pan=stereo,volume={$volMain}[amain]"];
        $aMixing = ["[amain]"];
        $aCount = 1;

        if ($volVideo > 0) {
            $aInputs[] = "-i " . escapeshellarg($videoPath);
            $aFilters[] = "[{$aCount}:a]aresample=44100,pan=stereo,volume={$volVideo}[avideo]";
            $aMixing[] = "[avideo]";
            $aCount++;
        }

        if ($bgMusicIndex !== null) {
            $aInputs[] = "-i " . escapeshellarg($bgMusicPath);
            $aFilters[] = "[{$aCount}:a]aresample=44100,pan=stereo,volume={$volMusic}[abg]";
            $aMixing[] = "[abg]";
            $aCount++;
        }

        $aFilterStr = implode(';', $aFilters);
        if ($aCount > 1) {
            $aFilterStr .= ";" . implode('', $aMixing) . "amix=inputs={$aCount}:duration=first:dropout_transition=0[outa]";
        } else {
            // Fix: Không dùng 'copy' trong filter complex, dùng anull hoặc map trực tiếp
            $aFilterStr .= ";[amain]anull[outa]";
        }

        $aCmd = "ffmpeg -y " . implode(' ', $aInputs) . " -filter_complex " . escapeshellarg($aFilterStr) . " -map \"[outa]\" -ac 2 -ar 44100 " . escapeshellarg($mixedAudioPath) . " 2>&1";
        exec($aCmd, $aOutput, $aRet);
        if ($aRet !== 0) {
            throw new \Exception("Lỗi trộn âm thanh (Audio Mixing failed): " . implode("\n", $aOutput));
        }

        // --- BƯỚC CUỐI: GHÉP VIDEO VỚI ÂM THANH ĐÃ TRỘN ---
        $vFilterStr = implode(';', $vFilters);
        $cmd = "ffmpeg -hide_banner -y -stream_loop -1 -i " . escapeshellarg($videoPath) . " -i " . escapeshellarg($mixedAudioPath) . 
               " -filter_complex " . escapeshellarg($vFilterStr) . 
               " -map \"[{$lastV}]\" -map 1:a -t " . escapeshellarg($duration) . 
               " -c:v libx264 -preset ultrafast -pix_fmt yuv420p -c:a aac -b:a 192k -shortest " . escapeshellarg($outputPath) . " 2>&1";
        
        Log::info("Job {$this->job->id} Executing: " . $cmd);
        exec($cmd, $outputArray, $returnCode);
        $fullLog = implode("\n", $outputArray);

        @file_put_contents(public_path('debug_render.txt'), "CMD: {$cmd}\n\nLOG:\n{$fullLog}");

        if (!file_exists($outputPath) || $returnCode !== 0) {
            Log::error("Job {$this->job->id} FFmpeg Failed. Full Log: " . $fullLog);
            throw new \Exception("FFmpeg failed (Code {$returnCode}). Log: " . ($fullLog ?: "No output from FFmpeg"));
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
