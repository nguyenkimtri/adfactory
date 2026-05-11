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
            
            // ĐẶT TÊN THEO CÚ PHÁP: vd-factory-idngaythangnam
            $fileName = "vd-factory-{$this->job->id}" . now()->format('dmY');
            $outputPath = storage_path("app/public/exports/{$fileName}.mp4");
            if (!file_exists(dirname($outputPath))) mkdir(dirname($outputPath), 0777, true);

            $this->updateProgress(70, 'Đang Render Video Final...');
            $this->render($videoPath, $paths['audio'], $paths['bg_music'] ?? null, $paths['logo'] ?? null, $paths['subtitle'] ?? null, $outputPath, $audioDuration);
            
            $this->job->update([
                'status' => 'completed',
                'progress' => 100,
                'output_path' => asset("storage/exports/{$fileName}.mp4"),
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
        // Xử lý nhiều Audio chính (Ghép nối theo thứ tự)
        $audioUrls = is_array($this->job->audio_url) ? $this->job->audio_url : [$this->job->audio_url];
        $audioPaths = [];
        foreach ($audioUrls as $i => $url) {
            if ($url) $audioPaths[] = $this->download($url, "main_audio_{$i}");
        }
        if (empty($audioPaths)) throw new \Exception("Thiếu link Audio chính.");
        $mainAudio = $this->concatAudio($audioPaths, "main_audio_full.mp3");

        $paths = ['audio' => $mainAudio];

        // Xử lý nhiều Nhạc nền (Nếu có)
        if ($this->job->bg_music_url) {
            $bgUrls = is_array($this->job->bg_music_url) ? $this->job->bg_music_url : [$this->job->bg_music_url];
            $bgPaths = [];
            foreach ($bgUrls as $i => $url) {
                if ($url) $bgPaths[] = $this->download($url, "bg_music_{$i}");
            }
            if (!empty($bgPaths)) {
                $paths['bg_music'] = $this->concatAudio($bgPaths, "bg_music_full.mp3");
            }
        }

        if ($this->job->logo_url) $paths['logo'] = $this->download($this->job->logo_url, 'logo');
        
        $paths['videos'] = [];
        foreach ($this->job->video_sources ?? [] as $i => $url) {
            if ($url) $paths['videos'][] = $this->download($url, "v_{$i}");
        }
        return $paths;
    }

    protected function concatAudio($paths, $outName) {
        if (count($paths) === 1) return $paths[0];
        $outPath = "{$this->tempDir}/{$outName}";
        $inputs = "";
        foreach ($paths as $p) $inputs .= "-i \"{$p}\" ";
        $filter = "";
        for ($i=0; $i<count($paths); $i++) {
            $filter .= "[{$i}:a]aresample=44100,pan=stereo[a{$i}];";
        }
        for ($i=0; $i<count($paths); $i++) {
            $filter .= "[a{$i}]";
        }
        $filter .= "concat=n=" . count($paths) . ":v=0:a=1[aout]";
        shell_exec("ffmpeg -y {$inputs} -filter_complex \"{$filter}\" -map \"[aout]\" -c:a libmp3lame -q:a 2 \"{$outPath}\" 2>&1");
        return $outPath;
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
        
        $volVideo = ($this->job->settings['volume_video'] ?? 0) / 100;
        $audioFlag = ($volVideo > 0) ? "" : "-an"; // Nếu có âm lượng video gốc thì không dùng -an

        $normPaths = [];
        foreach ($videoPaths as $i => $path) {
            $out = "{$normDir}/{$i}.mp4";
            shell_exec("ffmpeg -y -i \"{$path}\" -vf \"scale={$res}:force_original_aspect_ratio=increase,crop={$res},setpts=PTS-STARTPTS\" -r 30 -c:v libx264 -preset superfast {$audioFlag} \"{$out}\" 2>&1");
            if (file_exists($out)) $normPaths[] = $out;
        }

        if (empty($normPaths)) throw new \Exception("Không có video nguồn nào hợp lệ.");

        $listFile = "{$this->tempDir}/list.txt"; 
        $content = "";
        foreach ($normPaths as $p) $content .= "file '" . realpath($p) . "'\n";
        file_put_contents($listFile, $content);
        
        $concatPath = "{$this->tempDir}/concat.mp4";
        $concatAudioFlag = ($volVideo > 0) ? "" : "-an";
        shell_exec("ffmpeg -y -f concat -safe 0 -i \"{$listFile}\" -c:v copy {$concatAudioFlag} \"{$concatPath}\" 2>&1");
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
        
        $inputs = [
            "-stream_loop -1 -i " . escapeshellarg($videoPath),
            "-i " . escapeshellarg($audioPath)
        ];
        
        if ($logoPath) $inputs[] = "-ignore_loop 0 -loop 1 -i " . escapeshellarg($logoPath);
        if ($bgMusicPath) $inputs[] = "-stream_loop -1 -i " . escapeshellarg($bgMusicPath);
        
        $vFilters = ["[0:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res}[vbase]"];
        $lastV = "vbase";

        if ($subtitlePath) {
            $realPath = realpath($subtitlePath);
            // Cách thoát chuỗi tối giản cho ass filter
            $safeAssPath = str_replace([':', '\\', "'"], ["\\:", '/', "'\\''"], $realPath);
            $vFilters[] = "[{$lastV}]ass='{$safeAssPath}'[vsub]";
            $lastV = "vsub";
        }

        if ($logoPath) { 
            $opacity = ($this->job->settings['logo_opacity'] ?? 80) / 100;
            $size = $this->job->settings['logo_size'] ?? 200;
            $speed = ($this->job->settings['logo_speed'] ?? 5);
            $durX = 15 / $speed; $durY = 11 / $speed;
            
            $vFilters[] = "[2:v]scale={$size}:-1,format=rgba,colorchannelmixer=aa={$opacity}[logo]";
            $vFilters[] = "[{$lastV}][logo]overlay=x='if(lte(mod(t,{$durX}*2),{$durX}), (W-w)*mod(t,{$durX})/{$durX}, (W-w)*(1-mod(t,{$durX})/{$durX}))':y='if(lte(mod(t,{$durY}*2),{$durY}), (H-h)*mod(t,{$durY})/{$durY}, (H-h)*(1-mod(t,{$durY})/{$durY}))':shortest=1[vlogo]"; 
            $lastV = "vlogo"; 
        }

        $volMain = ($this->job->settings['volume_audio'] ?? 100) / 100;
        $volVideo = ($this->job->settings['volume_video'] ?? 0) / 100;
        $volMusic = ($this->job->settings['volume_music'] ?? 20) / 100;

        $aFilters = ["[1:a]aresample=44100,pan=stereo,volume={$volMain}[amain]"];
        $mixing = ["[amain]"];
        $audioInputs = 1;

        if ($volVideo > 0) {
            $aFilters[] = "[0:a]aresample=44100,pan=stereo,volume={$volVideo}[avideo]";
            $mixing[] = "[avideo]";
            $audioInputs++;
        }

        if ($bgMusicPath) { 
            $aFilters[] = "[3:a]aresample=44100,pan=stereo,volume={$volMusic}[abg]"; 
            $mixing[] = "[abg]";
            $audioInputs++;
        }
        
        if ($audioInputs > 1) {
            $aFilters[] = implode('', $mixing) . "amix=inputs={$audioInputs}:duration=first[amixout]";
            $lastA = "amixout";
        } else {
            $lastA = "amain";
        }
        
        $filterStr = implode(';', array_merge($vFilters, $aFilters));
        
        $cmd = "ffmpeg -y " . implode(' ', $inputs) . " -filter_complex " . escapeshellarg($filterStr) . " -map [{$lastV}] -map [{$lastA}] -t {$duration} -c:v libx264 -preset ultrafast -pix_fmt yuv420p -c:a aac -b:a 192k " . escapeshellarg($outputPath) . " 2>&1";
        
        Log::info("Job {$this->job->id} Executing: " . $cmd);
        exec($cmd, $outputArray, $returnCode);
        $fullLog = implode("\n", $outputArray);

        if (!file_exists($outputPath) || $returnCode !== 0) {
            Log::error("Job {$this->job->id} FFmpeg Failed. Full Log: " . $fullLog);
            // Lấy 3000 ký tự để không bỏ lỡ lỗi ở giữa
            throw new \Exception("FFmpeg failed (Code {$returnCode}). Log: " . substr($fullLog, 0, 3000));
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
