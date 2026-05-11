<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Factory - WebSocket Realtime</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Thư viện WebSocket -->
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
        .top-btns { display: flex; gap: 12px; }
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
        input[type="range"] { -webkit-appearance: none; height: 3px; background: #334155; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; background: var(--primary); border-radius: 50%; cursor: pointer; }

        .btn-render { background: linear-gradient(135deg, #06b6d4, #3b82f6); color: #fff; border: none; padding: 16px; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 10px 20px -5px rgba(6, 182, 212, 0.4); }

        .job-item { background: rgba(255,255,255,0.03); border: 1px solid var(--border); padding: 15px; border-radius: 16px; margin-bottom: 15px; transition: transform 0.3s; }
        .job-item.updated { animation: highlight 1s ease; }
        @keyframes highlight { 0% { background: rgba(34, 211, 238, 0.2); } 100% { background: rgba(255,255,255,0.03); } }

        .job-header { display: flex; justify-content: space-between; align-items: center; }
        .job-title { font-weight: 600; font-size: 1rem; }
        .status-badge { padding: 4px 10px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-completed { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .status-processing { background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: #f87171; }

        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: #fff; display: flex; align-items: center; gap: 5px; cursor: pointer; text-decoration: none; }
        .btn-play { color: var(--success); }
        .btn-share { color: #a855f7; }

        .progress-bar { height: 6px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.5s ease; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); backdrop-filter: blur(10px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: var(--bg); border: 1px solid var(--border); border-radius: 24px; padding: 30px; max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; position: relative; }
        
        .video-close-btn { position: absolute; top: 15px; right: 15px; z-index: 2010; background: rgba(0,0,0,0.5); border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; }
        .toast { position: fixed; bottom: 30px; right: 30px; background: var(--success); color: white; padding: 12px 24px; border-radius: 12px; display: none; z-index: 3000; }
        .checkbox-group { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); padding: 12px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 15px; }

        #ws-status { font-size: 0.65rem; padding: 4px 8px; border-radius: 6px; display: flex; align-items: center; gap: 5px; }
        .ws-connected { color: var(--success); background: rgba(16, 185, 129, 0.1); }
        .ws-disconnected { color: var(--danger); background: rgba(239, 68, 68, 0.1); }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>🎬 Video Factory</h1>
    <div class="top-btns">
        <div id="ws-status" class="ws-disconnected"><span style="width:6px;height:6px;background:currentColor;border-radius:50%"></span> WebSocket: Connecting...</div>
        <a href="/sample_n8n.json" download class="btn-top"><i data-lucide="download-cloud"></i> Tải mẫu n8n</a>
        <button class="btn-top" onclick="openModal('api-modal')"><i data-lucide="code"></i> API Docs</button>
    </div>
</div>

<div id="toast" class="toast">Đã sao chép link!</div>

<div class="main-container">
    <div class="sidebar">
        <form action="{{ route('generate') }}" method="POST" id="main-form">
            @csrf
            <div class="card">
                <h3><i data-lucide="link"></i> Tài nguyên</h3>
                <div class="form-group"><label>Audio Chính</label><input type="text" name="audio_url" required></div>
                <div class="form-group"><label>Video Nguồn</label><textarea name="raw_video_sources" rows="2" required></textarea></div>
                <div class="checkbox-group">
                    <input type="checkbox" name="settings[auto_subtitle]" id="auto_subtitle" checked>
                    <label for="auto_subtitle">Tự động tạo phụ đề AI</label>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div class="form-group"><label>Nhạc nền</label><input type="text" name="bg_music_url"></div>
                    <div class="form-group"><label>Logo</label><input type="text" name="logo_url"></div>
                </div>
            </div>
            <div class="card">
                <h3><i data-lucide="settings"></i> Tinh chỉnh</h3>
                <div class="form-group">
                    <label>Âm lượng Audio</label>
                    <div class="slider-box"><input type="range" name="settings[volume_audio]" min="0" max="200" value="100"></div>
                </div>
                <button type="submit" class="btn-render"><i data-lucide="zap"></i> RENDER NGAY</button>
            </div>
        </form>
    </div>

    <div class="content">
        <h3><i data-lucide="history"></i> Lịch sử sản xuất</h3>
        <div id="job-list-container">
            @foreach($jobs as $job)
                <div class="job-item" id="job-{{ $job->id }}">
                    <div class="job-header">
                        <div><div class="job-title">{{ $job->project_name ?? 'Video #'.$job->id }}</div><div class="job-time" style="font-size:0.8rem;color:var(--text-muted)">{{ $job->created_at->diffForHumans() }}</div></div>
                        <span class="status-badge status-{{ $job->status }}">{{ $job->status }}</span>
                    </div>
                    <div class="job-body">
                        @if($job->status === 'processing' || $job->status === 'pending')
                            <div class="progress-bar"><div class="progress-fill" style="width: {{ $job->progress }}%"></div></div>
                            <div class="status-msg" style="font-size:0.75rem;margin-top:5px;color:var(--primary)">{{ $job->status_message }}</div>
                        @elseif($job->status === 'completed')
                            <div style="display:flex;gap:8px;margin-top:10px;">
                                <button onclick="playVideo('{{ $job->output_path }}')" class="btn-action btn-play">Xem</button>
                                <a href="{{ $job->output_path }}" download class="btn-action">Tải về</a>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div id="video-modal" class="modal">
    <div class="modal-content">
        <div class="video-close-btn" onclick="closeVideoModal()"><i data-lucide="x"></i></div>
        <video id="main-player" controls autoplay style="width:100%"></video>
    </div>
</div>

<script>
    lucide.createIcons();

    // Cấu hình Laravel Echo kết nối Reverb
    const echo = new Echo({
        broadcaster: 'reverb',
        key: '{{ config('reverb.apps.apps.0.key') }}', // Lấy Key từ Config Laravel
        wsHost: '{{ config('reverb.apps.apps.0.options.host') }}',
        wsPort: {{ config('reverb.apps.apps.0.options.port', 8080) }},
        forceTLS: {{ config('reverb.apps.apps.0.options.scheme', 'https') === 'https' ? 'true' : 'false' }},
        enabledTransports: ['ws', 'wss'],
    });

    const statusEl = document.getElementById('ws-status');
    echo.connector.pusher.connection.bind('connected', () => {
        statusEl.className = 'ws-connected';
        statusEl.innerHTML = '<span style="width:6px;height:6px;background:currentColor;border-radius:50%"></span> WebSocket: Connected';
    });

    echo.channel('jobs')
        .listen('.job.updated', (e) => {
            console.log('Job Update Received:', e.job);
            const job = e.job;
            const jobEl = document.getElementById(`job-${job.id}`);
            
            if (jobEl) {
                jobEl.classList.add('updated');
                setTimeout(() => jobEl.classList.remove('updated'), 1000);
                
                // Cập nhật Badge Trạng thái
                const badge = jobEl.querySelector('.status-badge');
                badge.className = `status-badge status-${job.status}`;
                badge.innerText = `${job.status} ${job.status === 'processing' ? job.progress + '%' : ''}`;

                // Cập nhật nội dung (Progress bar, nút bấm)
                const body = jobEl.querySelector('.job-body');
                if (job.status === 'processing' || job.status === 'pending') {
                    body.innerHTML = `
                        <div class="progress-bar"><div class="progress-fill" style="width: ${job.progress}%"></div></div>
                        <div class="status-msg" style="font-size:0.75rem;margin-top:5px;color:var(--primary)">${job.status_message || ''}</div>
                    `;
                } else if (job.status === 'completed') {
                    body.innerHTML = `
                        <div style="display:flex;gap:8px;margin-top:10px;">
                            <button onclick="playVideo('${job.output_path}')" class="btn-action btn-play">Xem</button>
                            <a href="${job.output_path}" download class="btn-action">Tải về</a>
                        </div>
                    `;
                } else if (job.status === 'failed') {
                    body.innerHTML = `<div style="color:var(--danger);font-size:0.8rem;margin-top:5px;">Lỗi: ${job.error_message || 'Không rõ'}</div>`;
                }
            } else {
                // Nếu là Job mới hoàn toàn (vừa submit), reload hoặc thêm vào đầu danh sách
                location.reload(); 
            }
        });

    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }
    function playVideo(url) {
        const video = document.getElementById('main-player');
        video.src = url;
        document.getElementById('video-modal').style.display = 'flex';
        video.play();
    }
    function closeVideoModal() {
        document.getElementById('video-modal').style.display = 'none';
        document.getElementById('main-player').pause();
    }

    document.getElementById('main-form').onsubmit = function(e) {
        const textarea = this.querySelector('textarea[name="raw_video_sources"]');
        const lines = textarea.value.split('\n').filter(l => l.trim() !== '');
        lines.forEach(line => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'video_sources[]'; input.value = line.trim();
            this.appendChild(input);
        });
    };
</script>
</body>
</html>
