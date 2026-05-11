<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Factory Studio - Premium Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- WebSocket Libraries -->
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>
    <style>
        :root {
            --bg: #020617;
            --card: rgba(30, 41, 59, 0.8);
            --primary: #22d3ee;
            --primary-glow: rgba(34, 211, 238, 0.3);
            --secondary: #94a3b8;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.1);
        }

        * { box-sizing: border-box; transition: all 0.2s ease; }
        body {
            background: radial-gradient(circle at top right, #1e1b4b, #020617);
            color: var(--text-main); font-family: 'Outfit', sans-serif; margin: 0; padding: 0; height: 100vh; display: flex; flex-direction: column; overflow: hidden;
        }

        .top-bar { display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); flex-shrink: 0; }
        .top-bar h1 { font-size: 1.5rem; margin: 0; background: linear-gradient(to right, #22d3ee, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 700; }
        .top-btns { display: flex; gap: 12px; align-items: center; }
        
        #ws-status { font-size: 0.7rem; padding: 6px 12px; border-radius: 99px; display: flex; align-items: center; gap: 6px; font-weight: 600; }
        .ws-connected { color: var(--success); background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); }
        .ws-connecting { color: var(--accent); background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); }
        .ws-disconnected { color: var(--danger); background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); }

        .btn-top { padding: 8px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: #fff; display: flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-top:hover { background: rgba(255,255,255,0.1); border-color: var(--primary); }

        .main-container { display: grid; grid-template-columns: 520px 1fr; gap: 0; flex: 1; overflow: hidden; }
        .sidebar { padding: 20px; overflow-y: auto; border-right: 1px solid var(--border); background: rgba(15, 23, 42, 0.4); }
        .content { padding: 20px; overflow-y: auto; display: flex; flex-direction: column; }
        .card { background: var(--card); border: 1px solid var(--border); padding: 20px; border-radius: 20px; margin-bottom: 20px; }
        h3 { display: flex; align-items: center; gap: 10px; margin-top: 0; font-size: 1.1rem; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        input, textarea, select { width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: rgba(0,0,0,0.3); color: #fff; font-size: 0.95rem; }
        
        .slider-box { background: rgba(0,0,0,0.2); padding: 10px; border-radius: 10px; border: 1px solid var(--border); }
        .slider-label { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px; }
        input[type="range"] { -webkit-appearance: none; height: 3px; background: #334155; width: 100%; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; background: var(--primary); border-radius: 50%; cursor: pointer; }

        .switch-container { display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.05); padding: 12px 16px; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 15px; cursor: pointer; }
        .switch-container:hover { background: rgba(255,255,255,0.08); }
        .switch-text { font-size: 0.9rem; font-weight: 600; color: #fff; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider-toggle { position: absolute; cursor: pointer; inset: 0; background-color: #334155; transition: .4s; border-radius: 34px; }
        .slider-toggle:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider-toggle { background-color: var(--primary); }
        input:checked + .slider-toggle:before { transform: translateX(20px); }

        .job-item { background: rgba(255,255,255,0.03); border: 1px solid var(--border); padding: 15px; border-radius: 16px; margin-bottom: 15px; position: relative; }
        .job-header { display: flex; justify-content: space-between; align-items: center; }
        .status-badge { padding: 4px 10px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-completed { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .status-processing { background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: #f87171; }

        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: #fff; display: flex; align-items: center; gap: 5px; cursor: pointer; text-decoration: none; }
        .btn-play { color: var(--success); }
        .btn-share { color: #a855f7; }

        .progress-bar { height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.4s ease; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: var(--bg); border: 1px solid var(--border); border-radius: 24px; padding: 30px; max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; }
        .video-close-btn { position: absolute; top: 15px; right: 15px; z-index: 2010; background: rgba(0,0,0,0.5); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; }
        .toast { position: fixed; bottom: 30px; right: 30px; background: var(--success); color: white; padding: 12px 24px; border-radius: 12px; display: none; z-index: 3000; }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>🎬 Video Factory Studio</h1>
    <div class="top-btns">
        <div id="ws-status" class="ws-connecting">● Realtime: Connecting...</div>
        <a href="/sample_n8n.json" download class="btn-top" style="color:var(--success)"><i data-lucide="download-cloud"></i> Tải mẫu n8n</a>
        <button class="btn-top" onclick="openModal('api-modal')"><i data-lucide="code"></i> API Docs</button>
        <button class="btn-top" onclick="openModal('guide-modal')"><i data-lucide="help-circle"></i> Hướng dẫn</button>
    </div>
</div>

<div id="toast" class="toast">Đã sao chép link!</div>

<div class="main-container">
    <div class="sidebar">
        <form action="{{ route('generate') }}" method="POST" id="main-form">
            @csrf
            <div class="card">
                <h3><i data-lucide="link"></i> Tài nguyên</h3>
                <div class="form-group"><label>Tên dự án</label><input type="text" name="project_name" placeholder="Ví dụ: Video Review Film 01"></div>
                <div class="form-group"><label>Audio Chính</label><input type="text" name="audio_url" placeholder="Dán link audio MP3..." required></div>
                <div class="form-group"><label>Video Nguồn (Mỗi link 1 dòng)</label><textarea name="raw_video_sources" rows="3" placeholder="Link YouTube, TikTok, MP4..." required></textarea></div>
                
                <label class="switch-container">
                    <span class="switch-text">Tự động tạo phụ đề AI chuyên nghiệp</span>
                    <span class="switch">
                        <input type="checkbox" name="settings[auto_subtitle]" checked>
                        <span class="slider-toggle"></span>
                    </span>
                </label>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group"><label>Nhạc nền</label><input type="text" name="bg_music_url" placeholder="Link nhạc nền..."></div>
                    <div class="form-group"><label>Logo</label><input type="text" name="logo_url" placeholder="Link ảnh logo PNG..."></div>
                </div>
            </div>

            <div class="card">
                <h3><i data-lucide="settings"></i> Tinh chỉnh</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Định dạng</label><select name="settings[format]"><option value="9:16">Dọc (9:16)</option><option value="16:9">Ngang (16:9)</option></select></div>
                    <div class="form-group"><label>Độ mờ Logo</label><div class="slider-box"><div class="slider-label"><span>Mức</span><span id="v-logo-op">80%</span></div><input type="range" name="settings[logo_opacity]" min="0" max="100" value="80" oninput="document.getElementById('v-logo-op').innerText = this.value + '%'"></div></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Kích thước Logo</label><div class="slider-box"><div class="slider-label"><span>Size</span><span id="v-logo-size">200px</span></div><input type="range" name="settings[logo_size]" min="50" max="500" value="200" oninput="document.getElementById('v-logo-size').innerText = this.value + 'px'"></div></div>
                    <div class="form-group"><label>Tốc độ Logo</label><div class="slider-box"><div class="slider-label"><span>Speed</span><span id="v-logo-speed">1x</span></div><input type="range" name="settings[logo_speed]" min="1" max="10" value="5" oninput="document.getElementById('v-logo-speed').innerText = (this.value/5).toFixed(1) + 'x'"></div></div>
                </div>
                <div class="form-group"><label>Âm lượng Audio</label><div class="slider-box"><div class="slider-label"><span>Mức độ</span><span id="v-vol-audio">100%</span></div><input type="range" name="settings[volume_audio]" min="0" max="200" value="100" oninput="document.getElementById('v-vol-audio').innerText = this.value + '%'"></div></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group"><label>Nhạc nền</label><div class="slider-box"><div class="slider-label"><span>Mức</span><span id="v-vol-music">20%</span></div><input type="range" name="settings[volume_music]" min="0" max="100" value="20" oninput="document.getElementById('v-vol-music').innerText = this.value + '%'"></div></div>
                    <div class="form-group"><label>Video gốc</label><div class="slider-box"><div class="slider-label"><span>Mức</span><span id="v-vol-video">0%</span></div><input type="range" name="settings[volume_video]" min="0" max="100" value="0" oninput="document.getElementById('v-vol-video').innerText = this.value + '%'"></div></div>
                </div>
                <button type="submit" class="btn-render"><i data-lucide="zap"></i> BẮT ĐẦU RENDER</button>
            </div>
        </form>
    </div>

    <div class="content">
        <h3><i data-lucide="history"></i> Lịch sử sản xuất</h3>
        <div id="job-list-container">
            @foreach($jobs as $job)
                <div class="job-item" id="job-{{ $job->id }}">
                    <div class="job-header">
                        <div><div class="job-title" style="font-weight:700;">{{ $job->project_name ?? 'Video Job #'.$job->id }}</div><div style="color:var(--text-muted);font-size:0.8rem;">{{ $job->created_at->diffForHumans() }}</div></div>
                        <span class="status-badge status-{{ $job->status }}">{{ $job->status }} {{ $job->status === 'processing' ? $job->progress.'%' : '' }}</span>
                    </div>
                    <div class="job-body" style="margin-top:12px;">
                        @if($job->status === 'processing' || $job->status === 'pending')
                            <div class="progress-bar"><div class="progress-fill" style="width: {{ $job->progress }}%"></div></div>
                            <div style="font-size:0.75rem;color:var(--primary);margin-top:5px;">{{ $job->status_message }}</div>
                            <button onclick="deleteJob('{{ $job->id }}', 'Hủy video này?')" class="btn-action" style="margin-top:10px;color:var(--danger)"><i data-lucide="x-circle" size="14"></i> Hủy</button>
                        @elseif($job->status === 'completed')
                            <div style="display:flex;gap:8px;">
                                <button onclick="playVideo('{{ $job->output_path }}')" class="btn-action btn-play"><i data-lucide="play" size="14"></i> Xem</button>
                                <button onclick="shareLink('{{ $job->output_path }}')" class="btn-action btn-share"><i data-lucide="share-2" size="14"></i> Copy Link</button>
                                <a href="{{ $job->output_path }}" download class="btn-action"><i data-lucide="download" size="14"></i> Tải về</a>
                                <button onclick="deleteJob('{{ $job->id }}')" class="btn-action"><i data-lucide="trash-2" size="14"></i> Xóa</button>
                            </div>
                        @elseif($job->status === 'failed')
                            <div style="color:var(--danger);font-size:0.8rem;background:rgba(239, 68, 68, 0.05);padding:10px;border-radius:10px;margin-bottom:10px;border:1px solid rgba(239, 68, 68, 0.1)">Lỗi: {{ $job->error_message }}</div>
                            <button onclick="deleteJob('{{ $job->id }}')" class="btn-action"><i data-lucide="trash-2" size="14"></i> Xóa</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div id="api-modal" class="modal"><div class="modal-content"><div class="video-close-btn" onclick="closeModal('api-modal')"><i data-lucide="x"></i></div><h3>API Docs</h3><pre><code>POST {{ url('/api/video/generate') }}</code></pre></div></div>
<div id="guide-modal" class="modal"><div class="modal-content"><div class="video-close-btn" onclick="closeModal('guide-modal')"><i data-lucide="x"></i></div><h3>Hướng dẫn</h3><p>WebSocket kết nối qua cổng 2096 (Cloudflare SSL).</p></div></div>
<div id="video-modal" class="modal"><div class="modal-content"><div class="video-close-btn" onclick="closeVideoModal()"><i data-lucide="x"></i></div><video id="main-player" controls autoplay style="width:100%;border-radius:12px;"></video></div></div>

<script>
    lucide.createIcons();

    // SỬA LỖI: Dùng cổng 2096 - Cổng HTTPS tin cậy nhất của Cloudflare
    const echo = new Echo({
        broadcaster: 'pusher',
        key: '{{ config('reverb.apps.apps.0.key') }}',
        wsHost: window.location.hostname,
        wsPort: 2096,
        wssPort: 2096,
        forceTLS: true,
        cluster: 'mt1',
        disableStats: true,
        enabledTransports: ['ws', 'wss', 'xhr_streaming', 'xhr_polling'],
    });

    const wsStatus = document.getElementById('ws-status');
    if (echo.connector && echo.connector.pusher) {
        echo.connector.pusher.connection.bind('connected', () => {
            wsStatus.className = 'ws-connected'; wsStatus.innerText = '● Realtime: Connected';
        });
        echo.connector.pusher.connection.bind('disconnected', () => {
            wsStatus.className = 'ws-disconnected'; wsStatus.innerText = '● Realtime: Disconnected';
        });
    }

    echo.channel('jobs').listen('.job.updated', (e) => {
        const job = e.job; const el = document.getElementById(`job-${job.id}`);
        if (!el) { location.reload(); return; }
        el.querySelector('.status-badge').className = `status-badge status-${job.status}`;
        el.querySelector('.status-badge').innerText = `${job.status} ${job.status === 'processing' ? job.progress + '%' : ''}`;
        const body = el.querySelector('.job-body');
        if (job.status === 'processing' || job.status === 'pending') {
            body.innerHTML = `<div class="progress-bar"><div class="progress-fill" style="width: ${job.progress}%"></div></div><div style="font-size:0.75rem;color:var(--primary);margin-top:5px;">${job.status_message || ''}</div><button onclick="deleteJob('${job.id}', 'Hủy video này?')" class="btn-action" style="margin-top:10px;color:var(--danger)"><i data-lucide="x-circle" size="14"></i> Hủy</button>`;
        } else if (job.status === 'completed') {
            body.innerHTML = `<div style="display:flex;gap:8px;"><button onclick="playVideo('${job.output_path}')" class="btn-action btn-play"><i data-lucide="play" size="14"></i> Xem</button><button onclick="shareLink('${job.output_path}')" class="btn-action btn-share"><i data-lucide="share-2" size="14"></i> Copy Link</button><a href="${job.output_path}" download class="btn-action"><i data-lucide="download" size="14"></i> Tải về</a><button onclick="deleteJob('${job.id}')" class="btn-action"><i data-lucide="trash-2" size="14"></i> Xóa</button></div>`;
        } else if (job.status === 'failed') {
            body.innerHTML = `<div style="color:var(--danger);font-size:0.8rem;background:rgba(239, 68, 68, 0.05);padding:10px;border-radius:10px;margin-bottom:10px;border:1px solid rgba(239, 68, 68, 0.1)">Lỗi: ${job.error_message}</div><button onclick="deleteJob('${job.id}')" class="btn-action"><i data-lucide="trash-2" size="14"></i> Xóa</button>`;
        }
        lucide.createIcons();
    });

    function playVideo(url) {
        const p = document.getElementById('main-player'); p.src = url; document.getElementById('video-modal').style.display = 'flex'; p.play();
    }
    function closeVideoModal() {
        document.getElementById('video-modal').style.display = 'none'; document.getElementById('main-player').pause();
    }
    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function shareLink(url) {
        navigator.clipboard.writeText(url).then(() => {
            const t = document.getElementById('toast'); t.style.display = 'block'; setTimeout(() => t.style.display = 'none', 2000);
        });
    }
    function deleteJob(id, msg = 'Xóa video này?') {
        if(confirm(msg)) {
            fetch(`/api/jobs/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }).then(() => location.reload());
        }
    }
</script>
</body>
</html>
