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

            $this->updateProgress(30, 'Đang nối âm thanh chính...');
            $audioPath = $this->concatAudio($audioPaths, 'main_audio.mp3');
            
            if (!file_exists($audioPath)) throw new \Exception("Không tìm thấy tệp âm thanh chính sau khi xử lý.");
            
            $this->updateProgress(35, 'Đang chuẩn bị phông nền video...');
            $audioDuration = $this->getDuration($audioPath);
            $videoPath = $this->prepareVideo($videoPaths, $audioDuration);

            $subtitlePath = null;
            if ($this->job->settings['subtitles'] ?? true) {
                $this->updateProgress(40, 'AI đang nghe và tạo phụ đề...');
                $subtitlePath = $this->transcribeAudio($audioPath);
            }

            $this->updateProgress(60, 'Đang trộn các lớp âm thanh...');
            // Bước này nằm trong hàm render() nên mình sẽ gọi gián tiếp hoặc cập nhật trong đó
            
            $this->updateProgress(70, 'Đang Render Video Final (Vui lòng đợi)...');
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
        
        // Giải mã và làm sạch link
        if (strpos($url, 'douyin.com') !== false || strpos($url, 'tiktok.com') !== false) {
            $url = $this->expandUrl($url);
            if (strpos($url, '?') !== false) {
                $url = explode('?', $url)[0];
            }
            Log::info("URL expanded: " . $url);
        }

        $ytDlp = 'yt-dlp';
        if (PHP_OS_FAMILY === 'Windows') {
            $localExe = base_path('yt-dlp.exe');
            $ytDlp = file_exists($localExe) ? '"' . $localExe . '"' : 'yt-dlp.exe';
        }

        $cookieFilePath = "{$this->tempDir}/cookies.txt";
        $this->generateNetscapeCookies($cookieFilePath);
        $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36";
        
        // CHIÊU CUỐI: Tự trích xuất link video từ mã nguồn (Bypass Fresh Cookies)
        $directUrl = $this->extractDirectUrlManual($url, $cookieFilePath, $userAgent);
        $finalUrl = $directUrl ?: $url;

        $options = [
            "--no-playlist",
            "--no-check-certificates",
            "--no-warnings",
            "--ignore-errors",
            "--user-agent " . escapeshellarg($userAgent),
            "--add-header \"Referer: https://www.douyin.com/\"",
        ];

        if (file_exists($cookieFilePath) && filesize($cookieFilePath) > 10) {
            $options[] = "--cookies " . escapeshellarg($cookieFilePath);
        }
        $flags = implode(' ', $options);

        $errorLogPath = public_path('yt_dlp_error.txt');
        $cmd = "{$ytDlp} {$flags} -f \"bestvideo+bestaudio/best\" --merge-output-format mp4 -o \"{$path}.%(ext)s\" " . escapeshellarg($finalUrl) . " 2>&1";
        exec($cmd, $output, $result);
        
        $files = glob("{$path}.*");
        
        if (empty($files)) {
            $fullLog = implode("\n", $output);
            file_put_contents($errorLogPath, "URL: {$finalUrl}\n\nLOG:\n" . $fullLog);
            Log::error("yt-dlp download failed. Full log saved to public/yt_dlp_error.txt");
            throw new \Exception("Lỗi tải file: Douyin chặn quá gắt. Hãy thử lấy lại Cookie mới. Chi tiết: " . url('yt_dlp_error.txt'));
        }
        
        @unlink($cookieFilePath);
        return $files[0];
    }

    protected function extractDirectUrlManual($url, $cookiePath, $userAgent)
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiePath);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $html = curl_exec($ch);
            curl_close($ch);

            if (empty($html)) return null;

            // Cách 1: Tìm trong RENDER_DATA (Phổ biến nhất)
            if (preg_match('/<script id="RENDER_DATA" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
                $decoded = urldecode($matches[1]);
                $jsonData = json_decode($decoded, true);
                if ($jsonData) {
                    $playUrl = $this->searchRecursive($jsonData, 'play_addr');
                    if ($playUrl && is_array($playUrl) && isset($playUrl['url_list'][0])) {
                        return $playUrl['url_list'][0];
                    }
                }
            }

            // Cách 2: Tìm diện rộng bằng Regex
            if (preg_match('/"(https:\/\/v[0-9]+-dy-?[a-z]*\.adwm\.com\/[^"]+)"/i', $html, $matches)) {
                return str_replace('\\u0026', '&', $matches[1]);
            }
            
            if (preg_match('/"(https:\/\/aweme\.snssdk\.com\/aweme\/v1\/play\/?[^"]+)"/i', $html, $matches)) {
                return str_replace('\\u0026', '&', $matches[1]);
            }

            return null;
        } catch (\Exception $e) { return null; }
    }

    protected function searchRecursive($array, $key) {
        if (!is_array($array)) return null;
        if (array_key_exists($key, $array)) return $array[$key];
        foreach ($array as $value) {
            if (is_array($value)) {
                $result = $this->searchRecursive($value, $key);
                if ($result) return $result;
            }
        }
        return null;
    }

    protected function generateNetscapeCookies($filePath)
    {
        $rawCookie = null;
        $cookieFile = storage_path('app/douyin_cookie.json');

        if (file_exists($cookieFile)) {
            $rawCookie = file_get_contents($cookieFile);
            Log::info("Using cookie from file: {$cookieFile}");
        } else {
            $rawCookie = env('DOUYIN_COOKIE');
            if ($rawCookie) Log::info("Using cookie from .env");
        }

        if (!$rawCookie) return;

        $content = "# Netscape HTTP Cookie File\n";
        $jsonData = json_decode($rawCookie, true);
        
        if (is_array($jsonData)) {
            foreach ($jsonData as $cookie) {
                $domain = $cookie['domain'] ?? '.douyin.com';
                $flag = strpos($domain, '.') === 0 ? 'TRUE' : 'FALSE';
                $path = $cookie['path'] ?? '/';
                $secure = ($cookie['secure'] ?? false) ? 'TRUE' : 'FALSE';
                $expiry = isset($cookie['expirationDate']) ? (int)$cookie['expirationDate'] : (time() + 86400 * 30);
                $name = $cookie['name'] ?? '';
                $value = $cookie['value'] ?? '';
                
                if ($name !== null && $value !== null) {
                    $content .= "{$domain}\t{$flag}\t{$path}\t{$secure}\t{$expiry}\t{$name}\t{$value}\n";
                }
            }
        } else {
            // Hỗ trợ chuỗi cookie thô nếu không phải JSON
            $cookies = explode(';', $rawCookie);
            foreach ($cookies as $cookie) {
                $parts = explode('=', trim($cookie), 2);
                if (count($parts) == 2) {
                    $domain = ".douyin.com";
                    $content .= "{$domain}\tTRUE\t/\tFALSE\t" . (time() + 86400 * 30) . "\t{$parts[0]}\t{$parts[1]}\n";
                }
            }
        }
        
        file_put_contents($filePath, $content);
    }

    protected function downloadFromLovetik($url, $path)
    {
        try {
            $userAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36";
            $ch = curl_init("https://lovetik.com/api/ajax/search");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['query' => $url]));
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            if ($data && isset($data['links'][0]['a'])) {
                $videoUrl = $data['links'][0]['a'];
                
                // ÁP DỤNG ĐỀ XUẤT: Thay playwm -> play
                if (strpos($videoUrl, 'playwm') !== false) {
                    $videoUrl = str_replace('playwm', 'play', $videoUrl);
                }

                $ch = curl_init($videoUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                $videoData = curl_exec($ch);
                curl_close($ch);

                if ($videoData && strlen($videoData) > 1000) {
                    file_put_contents($path . ".mp4", $videoData);
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) { return false; }
    }

    protected function downloadFromTikWM($url, $path)
    {
        try {
            $apiUrl = "https://www.tikwm.com/api/?url=" . urlencode($url);
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            if ($data && isset($data['data']['play'])) {
                $videoUrl = $data['data']['play'];
                if (strpos($videoUrl, 'http') !== 0) $videoUrl = "https://www.tikwm.com" . $videoUrl;
                $videoData = @file_get_contents($videoUrl);
                if ($videoData && strlen($videoData) > 1000) {
                    file_put_contents($path . ".mp4", $videoData);
                    return true;
                }
            }
            return false;
        } catch (\Exception $e) { return false; }
    }

    protected function expandUrl($url)
    {
        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36");
            curl_exec($ch);
            $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            return $effectiveUrl ?: $url;
        } catch (\Exception $e) {
            return $url;
        }
    }

    protected function getDuration($path)
    {
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($path);
        return (float) shell_exec($cmd);
    }

    protected function concatAudio($paths, $outName)
    {
        $outputPath = "{$this->tempDir}/{$outName}";
        if (count($paths) === 0) return null;

        if (count($paths) === 1) {
            shell_exec("ffmpeg -y -i " . escapeshellarg($paths[0]) . " -vn -ac 2 -ar 44100 -acodec libmp3lame " . escapeshellarg($outputPath));
            return $outputPath;
        }

        $inputs = "";
        $filter = "";
        foreach ($paths as $i => $p) {
            $inputs .= "-i " . escapeshellarg($p) . " ";
            // Đã loại bỏ pan=stereo, chỉ dùng aresample để đồng bộ tần số
            $filter .= "[{$i}:a]aresample=44100[a{$i}];";
        }
        $count = count($paths);
        for($i=0;$i<$count;$i++) $filter .= "[a{$i}]";
        $filter .= "concat=n={$count}:v=0:a=1[outa]";

        $cmd = "ffmpeg -y {$inputs} -filter_complex \"{$filter}\" -map \"[outa]\" -acodec libmp3lame -ac 2 -ar 44100 " . escapeshellarg($outputPath) . " 2>&1";
        shell_exec($cmd);
        return $outputPath;
    }

    protected function prepareVideo($paths, $duration)
    {
        $outputPath = "{$this->tempDir}/concat.mp4";
        if (count($paths) === 0) throw new \Exception("Không có video nguồn nào.");

        // Remux lại từng video nguồn để đảm bảo PTS chuẩn (tránh lỗi No such file or directory của concat)
        $cleanPaths = [];
        foreach ($paths as $i => $p) {
            $cleanPath = "{$this->tempDir}/v_clean_{$i}.mp4";
            shell_exec("ffmpeg -y -i " . escapeshellarg($p) . " -c copy -fflags +genpts " . escapeshellarg($cleanPath) . " 2>&1");
            $cleanPaths[] = file_exists($cleanPath) ? $cleanPath : $p;
        }

        $res = (($this->job->settings['format'] ?? '9:16') === '9:16') ? '1080:1920' : '1920:1080';
        $inputs = "";
        $filter = "";
        foreach ($cleanPaths as $i => $p) {
            $inputs .= "-i " . escapeshellarg($p) . " ";
            $filter .= "[{$i}:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res},fps=30,setpts=PTS-STARTPTS[v{$i}];";
        }
        $count = count($cleanPaths);
        for($i=0;$i<$count;$i++) $filter .= "[v{$i}]";
        $filter .= "concat=n={$count}:v=1:a=0[outv]";

        $cmd = "ffmpeg -y {$inputs} -filter_complex \"{$filter}\" -map \"[outv]\" -c:v libx264 -preset ultrafast -threads 0 -r 30 -vsync 2 \"{$outputPath}\" 2>&1";
        $log = shell_exec($cmd);
        
        if (!file_exists($outputPath)) {
            Log::error("FFmpeg concat failed. Log: " . $log);
            throw new \Exception("Không thể tạo video phông nền (concat.mp4). Lỗi FFmpeg: " . substr($log, -200));
        }
        
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
        
        $volMain = ($this->job->settings['volume_audio'] ?? 100) / 100;
        $volVideo = ($this->job->settings['volume_video'] ?? 0) / 100;
        $volMusic = ($this->job->settings['volume_music'] ?? 20) / 100;

        // --- BƯỚC 1: TRỘN ÂM THANH RIÊNG BIỆT ---
        $mixedAudioPath = "{$this->tempDir}/final_mixed.mp3";
        $aInputs = ["-i " . escapeshellarg($audioPath)];
        $aCount = 1;

        // Chỉ dùng filter_complex nếu có nhiều hơn 1 nguồn âm thanh
        if ($volVideo > 0 || $bgMusicPath) {
            $aFilters = ["[0:a]volume={$volMain}[amain]"];
            $aMixing = ["[amain]"];
            
            if ($volVideo > 0) {
                $aInputs[] = "-i " . escapeshellarg($videoPath);
                $aFilters[] = "[{$aCount}:a]volume={$volVideo}[avideo]";
                $aMixing[] = "[avideo]";
                $aCount++;
            }
            if ($bgMusicPath) {
                $aInputs[] = "-stream_loop -1 -i " . escapeshellarg($bgMusicPath);
                $aFilters[] = "[{$aCount}:a]volume={$volMusic}[abg]";
                $aMixing[] = "[abg]";
                $aCount++;
            }
            
            $aFilterStr = implode(';', $aFilters) . ";" . implode('', $aMixing) . "amix=inputs={$aCount}:duration=first:dropout_transition=0[outa]";
            $aCmd = "ffmpeg -y " . implode(' ', $aInputs) . " -filter_complex " . escapeshellarg($aFilterStr) . " -map \"[outa]\" -acodec libmp3lame -b:a 192k -ac 2 -ar 44100 " . escapeshellarg($mixedAudioPath) . " 2>&1";
        } else {
            // Nếu chỉ có 1 nguồn, map trực tiếp cho chắc chắn có tiếng
            $aCmd = "ffmpeg -y -i " . escapeshellarg($audioPath) . " -acodec libmp3lame -b:a 192k -ac 2 -ar 44100 " . escapeshellarg($mixedAudioPath) . " 2>&1";
        }

        exec($aCmd, $aOutput, $aRet);
        if ($aRet !== 0) throw new \Exception("Lỗi trộn âm thanh: " . implode("\n", $aOutput));

        // --- BƯỚC 2: GHÉP VIDEO ---
        $vInputs = [
            "-stream_loop -1 -i " . escapeshellarg($videoPath),
            "-i " . escapeshellarg($mixedAudioPath)
        ];
        
        $logoIdx = null;
        if ($logoPath) {
            $vInputs[] = "-loop 1 -i " . escapeshellarg($logoPath);
            $logoIdx = count($vInputs) - 1;
        }

        $vFilters = ["[0:v]scale={$res}:force_original_aspect_ratio=increase,crop={$res}[vbase]"];
        $lastV = "vbase";
        if ($subtitlePath) {
            $realPath = realpath($subtitlePath);
            $safeAssPath = str_replace([':', '\\', "'"], ["\\:", '/', "'\\''"], $realPath);
            $vFilters[] = "[{$lastV}]ass='{$safeAssPath}'[vsub]";
            $lastV = "vsub";
        }

        if ($logoIdx !== null) {
            $size = $this->job->settings['logo_size'] ?? 200;
            $opacity = ($this->job->settings['logo_opacity'] ?? 80) / 100;
            $speed = ($this->job->settings['logo_speed'] ?? 5);
            $durX = 15 / $speed; $durY = 11 / $speed;
            $vFilters[] = "[{$logoIdx}:v]scale={$size}:-1,format=rgba,colorchannelmixer=aa={$opacity}[logo]";
            $vFilters[] = "[{$lastV}][logo]overlay=x='if(lte(mod(t,{$durX}*2),{$durX}), (W-w)*mod(t,{$durX})/{$durX}, (W-w)*(1-mod(t,{$durX})/{$durX}))':y='if(lte(mod(t,{$durY}*2),{$durY}), (H-h)*mod(t,{$durY})/{$durY}, (H-h)*(1-mod(t,{$durY})/{$durY}))'[vlogo]";
            $lastV = "vlogo";
        }

        $vFilterStr = implode(';', $vFilters);
        $cmd = "ffmpeg -hide_banner -y -stream_loop -1 -i " . escapeshellarg($videoPath) . " -i " . escapeshellarg($mixedAudioPath);
        if ($logoPath) {
            $cmd .= " -loop 1 -i " . escapeshellarg($logoPath);
        }
        $cmd .= " -filter_complex " . escapeshellarg($vFilterStr) . 
               " -map \"[{$lastV}]\" -map 1:a -t " . escapeshellarg($duration) . 
               " -c:v libx264 -preset ultrafast -threads 0 -pix_fmt yuv420p -c:a aac -b:a 192k -movflags +faststart " . escapeshellarg($outputPath) . " >> " . public_path('debug_render.txt') . " 2>&1";
        
        Log::info("Job {$this->job->id} Executing Final: " . $cmd);
        
        // Dùng exec và chuyển hướng toàn bộ output ra file để tránh Deadlock
        exec($cmd);
        
        if (!file_exists($outputPath)) {
            $log = @file_get_contents(public_path('debug_render.txt'));
            throw new \Exception("FFmpeg không tạo được video đầu ra. Nhật ký lỗi: " . substr($log, -500));
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
