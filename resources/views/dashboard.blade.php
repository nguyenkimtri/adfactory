<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Factory Studio - Premium Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
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
        
        .top-btns { display: flex; gap: 10px; align-items: center; }
        .btn-top { padding: 8px 14px; border-radius: 10px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: #fff; cursor: pointer; display: flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-top:hover { background: var(--primary); color: #000; border-color: var(--primary); }

        #ws-status { font-size: 0.7rem; padding: 6px 12px; border-radius: 99px; display: flex; align-items: center; gap: 6px; font-weight: 600; }
        .ws-connected { color: var(--success); background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); }
        .ws-connecting { color: var(--accent); background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); }

        .main-container { display: grid; grid-template-columns: 520px 1fr; gap: 0; flex: 1; overflow: hidden; }
        .sidebar { padding: 20px; overflow-y: auto; border-right: 1px solid var(--border); background: rgba(15, 23, 42, 0.4); }
        .content { padding: 20px; overflow-y: auto; display: flex; flex-direction: column; }
        .card { background: var(--card); border: 1px solid var(--border); padding: 20px; border-radius: 20px; margin-bottom: 20px; }
        h3 { display: flex; align-items: center; gap: 10px; margin-top: 0; font-size: 1.1rem; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 6px; color: var(--text-muted); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        input, textarea, select { width: 100%; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: rgba(0,0,0,0.3); color: #fff; font-size: 0.95rem; }
        textarea { resize: vertical; min-height: 80px; }
        
        .modal-content { background: var(--card-bg, #0f172a); width: 90%; max-width: 500px; padding: 20px; border-radius: 20px; position: relative; border: 1px solid var(--border-color, var(--border)); display: flex; flex-direction: column; }
        .slider-label { display: flex; justify-content: space-between; font-size: 0.8rem; margin-bottom: 6px; }
        input[type="range"] { -webkit-appearance: none; height: 3px; background: #334155; width: 100%; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; background: var(--primary); border-radius: 50%; cursor: pointer; }

        .switch-container { display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.05); padding: 12px 16px; border-radius: 16px; border: 1px solid var(--border); margin-bottom: 15px; cursor: pointer; }
        .switch-text { font-size: 0.9rem; font-weight: 600; }
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider-toggle { position: absolute; cursor: pointer; inset: 0; background-color: #334155; transition: .4s; border-radius: 34px; }
        .slider-toggle:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider-toggle { background-color: var(--primary); }
        input:checked + .slider-toggle:before { transform: translateX(20px); }

        .job-item { background: rgba(255,255,255,0.03); border: 1px solid var(--border); padding: 15px; border-radius: 16px; margin-bottom: 15px; position: relative; animation: slideIn 0.4s ease-out; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        
        .status-badge { padding: 4px 10px; border-radius: 99px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-completed { background: rgba(16, 185, 129, 0.1); color: #34d399; }
        .status-pending { background: rgba(245, 158, 11, 0.1); color: var(--accent); }
        .status-processing { background: rgba(59, 130, 246, 0.1); color: #60a5fa; }
        .status-failed { background: rgba(239, 68, 68, 0.1); color: #f87171; }

        .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: #fff; display: flex; align-items: center; gap: 5px; cursor: pointer; text-decoration: none; }
        .btn-play { color: var(--success); }
        .btn-copy { color: #a855f7; }
        .btn-cancel { color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }
        
        .progress-bar { height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.4s ease; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #fff; }
        .status-msg { font-size: 0.75rem; color: var(--primary); display: flex; align-items: center; gap: 6px; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); backdrop-filter: blur(15px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: var(--card-bg); width: 90%; max-width: 600px; padding: 20px; border-radius: 24px; position: relative; border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .modal-close { position: absolute; top: 15px; right: 15px; color: var(--text-muted); cursor: pointer; z-index: 10; }
        .api-block { background: #000; padding: 15px; border-radius: 12px; font-family: monospace; font-size: 0.85rem; margin: 10px 0; border: 1px solid var(--border); overflow-x: auto; color: #34d399; }

        .pagination-container { margin-top: auto; padding: 20px 0; display: flex; justify-content: center; gap: 10px; }
        .pagination-btn { padding: 8px 16px; border-radius: 10px; background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: #fff; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .pagination-btn:hover:not(.disabled) { background: var(--primary); border-color: var(--primary); color: #000; }
        .pagination-btn.active { background: var(--primary); color: #000; border-color: var(--primary); }
        .pagination-btn.disabled { opacity: 0.3; cursor: not-allowed; }

        .btn-render { width: 100%; padding: 14px; border-radius: 16px; border: none; background: linear-gradient(to right, var(--primary), #818cf8); color: #fff; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 1rem; box-shadow: 0 10px 15px -3px var(--primary-glow); }
        .spin { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="top-bar">
    <h1>🎬 Video Factory Studio</h1>
    <div class="top-btns">
        <div id="ws-status" class="ws-connecting">● Realtime</div>
        <button class="btn-top" onclick="openModal('guide-modal')"><i data-lucide="book-open"></i> Hướng dẫn</button>
        <button class="btn-top" onclick="openModal('api-modal')"><i data-lucide="code"></i> API (n8n)</button>
        <a href="/video_factory_template.csv" class="btn-top" download><i data-lucide="download"></i> Tải file mẫu</a>
    </div>
</div>

<div class="main-container">
    <div class="sidebar">
        <form id="render-form">
            @csrf
            <div class="card">
                <h3><i data-lucide="link"></i> Tài nguyên</h3>
                <div class="form-group"><label>Audio Chính (Mỗi link 1 dòng)</label><textarea name="audio_url" placeholder="Dán link audio MP3..." required></textarea></div>
                <div class="form-group"><label>Video Nguồn (Mỗi link 1 dòng)</label><textarea name="video_sources" rows="4" placeholder="Link YouTube, TikTok, MP4..." required></textarea></div>
                <div class="form-group"><label>Nhạc nền (Mỗi link 1 dòng)</label><textarea name="bg_music_url" placeholder="Link nhạc nền..."></textarea></div>
                
                <label class="switch-container">
                    <span class="switch-text">Tự động tạo phụ đề AI</span>
                    <span class="switch">
                        <input type="checkbox" name="settings[auto_subtitle]" checked>
                        <span class="slider-toggle"></span>
                    </span>
                </label>
                <div class="form-group"><label>Logo</label><input type="text" name="logo_url" placeholder="Link ảnh logo PNG..."></div>
            </div>

            <div class="card">
                <h3><i data-lucide="settings"></i> Tinh chỉnh</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                    <div class="form-group"><label>Định dạng</label><select name="settings[format]"><option value="9:16">Dọc (9:16)</option><option value="16:9">Ngang (16:9)</option></select></div>
                    <div class="form-group"><label>Độ mờ Logo</label><div class="slider-box"><div class="slider-label"><span>Mức</span><span id="v-logo-op">80%</span></div><input type="range" name="settings[logo_opacity]" min="0" max="100" value="80" oninput="document.getElementById('v-logo-op').innerText = this.value + '%'"></div></div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                    <div class="form-group"><label>Kích thước Logo</label><div class="slider-box"><div class="slider-label"><span>Size</span><span id="v-logo-size">200px</span></div><input type="range" name="settings[logo_size]" min="50" max="500" value="200" oninput="document.getElementById('v-logo-size').innerText = this.value + 'px'"></div></div>
                    <div class="form-group"><label>Tốc độ Logo</label><div class="slider-box"><div class="slider-label"><span>Speed</span><span id="v-logo-speed">1x</span></div><input type="range" name="settings[logo_speed]" min="1" max="10" value="5" oninput="document.getElementById('v-logo-speed').innerText = (this.value/5).toFixed(1) + 'x'"></div></div>
                </div>
                <div class="form-group"><label>Âm lượng Audio chính</label><div class="slider-box"><div class="slider-label"><span>Mức</span><span id="v-vol-audio">100%</span></div><input type="range" name="settings[volume_audio]" min="0" max="200" value="100" oninput="document.getElementById('v-vol-audio').innerText = this.value + '%'"></div></div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom:15px;">
                    <div class="form-group"><label>Nhạc nền</label><div class="slider-box"><div class="slider-label"><span>Mức</span><span id="v-vol-music">20%</span></div><input type="range" name="settings[volume_music]" min="0" max="100" value="20" oninput="document.getElementById('v-vol-music').innerText = this.value + '%'"></div></div>
                    <div class="form-group"><label>Video gốc</label><div class="slider-box"><div class="slider-label"><span>Mức</span><span id="v-vol-video">0%</span></div><input type="range" name="settings[volume_video]" min="0" max="100" value="0" oninput="document.getElementById('v-vol-video').innerText = this.value + '%'"></div></div>
                </div>
                <button type="submit" id="btn-submit" class="btn-render"><i data-lucide="zap"></i> BẮT ĐẦU RENDER</button>
            </div>
        </form>
    </div>

    <div class="content">
        <h3><i data-lucide="history"></i> Lịch sử sản xuất</h3>
        <div id="job-list-container">
            @if(isset($jobs) && count($jobs) > 0)
                @foreach($jobs as $job)
                    <div class="job-item" id="job-{{ $job->id }}">
                        <div class="job-header">
                            <div>
                                <div class="job-title" style="font-weight:700;">vd-factory-{{ $job->id }}{{ $job->created_at->format('dmY') }}</div>
                                <div style="color:var(--text-muted);font-size:0.75rem;margin-top:2px;">
                                    <i data-lucide="calendar" size="12"></i> Tạo: {{ $job->created_at->format('H:i:s d/m') }}
                                    @if($job->status === 'completed')
                                        | <i data-lucide="check-circle" size="12" style="color:var(--success)"></i> Xong: {{ $job->updated_at->format('H:i:s d/m') }}
                                    @endif
                                </div>
                            </div>
                            <span class="status-badge status-{{ $job->status }}">{{ $job->status }} {{ $job->status === 'processing' ? $job->progress.'%' : '' }}</span>
                        </div>
                        <div class="job-body" style="margin-top:12px;">
                            @if($job->status === 'processing' || $job->status === 'pending')
                                <div class="progress-bar"><div class="progress-fill" style="width: {{ $job->progress }}%"></div></div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                                    <div class="status-msg" style="font-size:0.75rem;color:var(--primary);">{{ $job->status_message ?: 'Đang xếp hàng...' }}</div>
                                    <button onclick="deleteJob('{{ $job->id }}')" class="btn-action btn-cancel"><i data-lucide="trash-2" size="12"></i> Hủy</button>
                                </div>
                            @elseif($job->status === 'completed')
                                <div style="display:flex;gap:8px;">
                                    <button onclick="playVideo('{{ $job->output_path }}')" class="btn-action btn-play"><i data-lucide="play" size="14"></i> Xem</button>
                                    <button onclick="copyToClipboard('{{ $job->output_path }}')" class="btn-action btn-copy"><i data-lucide="copy" size="14"></i> Copy Link</button>
                                    <a href="{{ $job->output_path }}" download class="btn-action"><i data-lucide="download" size="14"></i> Tải về</a>
                                    <button onclick="deleteJob('{{ $job->id }}')" class="btn-action"><i data-lucide="trash-2" size="14"></i> Xóa</button>
                                </div>
                            @elseif($job->status === 'failed')
                                <div style="color:var(--danger);font-size:0.8rem;background:rgba(239, 68, 68, 0.05);padding:12px;border-radius:10px;border:1px solid rgba(239, 68, 68, 0.1)">
                                    <div style="font-weight:700;margin-bottom:6px;"><i data-lucide="alert-circle" size="14"></i> CHI TIẾT LỖI RENDER:</div>
                                    <div style="max-height:200px;overflow-y:auto;white-space:pre-wrap;font-family:monospace;background:#000;color:#0f0;padding:10px;border-radius:6px;line-height:1.4;">{{ $job->error_message }}</div>
                                </div>
                                <button onclick="deleteJob('{{ $job->id }}')" class="btn-action" style="margin-top:10px;"><i data-lucide="trash-2" size="14"></i> Xóa</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                <div style="text-align:center; padding:50px; color:var(--text-muted);">Chưa có lịch sử sản xuất.</div>
            @endif
        </div>

        @if(isset($jobs) && method_exists($jobs, 'links'))
        <div class="pagination-container">
            @if($jobs->onFirstPage())
                <span class="pagination-btn disabled">Trang trước</span>
            @else
                <a href="{{ $jobs->previousPageUrl() }}" class="pagination-btn">Trang trước</a>
            @endif
            <span class="pagination-btn active">{{ $jobs->currentPage() }}</span>
            @if($jobs->hasMorePages())
                <a href="{{ $jobs->nextPageUrl() }}" class="pagination-btn">Trang sau</a>
            @else
                <span class="pagination-btn disabled">Trang sau</span>
            @endif
        </div>
        @endif
    </div>
</div>

<div id="guide-modal" class="modal">
    <div class="modal-content">
        <div class="modal-close" onclick="closeModal('guide-modal')"><i data-lucide="x"></i></div>
        <h2>📖 Hướng dẫn sử dụng</h2>
        <div style="line-height: 1.6; color: var(--secondary);">
            <p><strong>Bước 1:</strong> Chuẩn bị Audio (Giọng đọc) và dán link vào ô <em>Audio Chính</em>.</p>
            <p><strong>Bước 2:</strong> Chọn danh sách Video minh họa (mỗi link 1 dòng).</p>
            <p><strong>Bước 3:</strong> Cấu hình Logo, Âm lượng và Tốc độ di chuyển.</p>
            <p><strong>Bước 4:</strong> Bấm <em>BẮT ĐẦU RENDER</em>.</p>
        </div>
    </div>
</div>

<div id="api-modal" class="modal">
    <div class="modal-content">
        <div class="modal-close" onclick="closeModal('api-modal')"><i data-lucide="x"></i></div>
        <h2>🔌 Tài liệu API</h2>
        <div class="api-block">POST https://vdfs.phung.vn/generate</div>
        <p>Gửi mảng (Array) cho các trường audio_url và video_sources.</p>
    </div>
</div>

<div id="video-modal" class="modal"><div class="modal-content"><div class="video-close-btn" onclick="closeModal('video-modal')"><i data-lucide="x"></i></div><video id="main-player" controls autoplay style="max-height: 80vh; max-width: 100%; border-radius:12px;"></video></div></div>

<script>
    lucide.createIcons();
    const echo = new Echo({
        broadcaster: 'pusher',
        key: '{{ config('reverb.apps.apps.0.key') }}',
        wsHost: 'wss.phung.vn', wsPort: 443, wssPort: 443, forceTLS: true, cluster: 'mt1', disableStats: true,
        enabledTransports: ['ws', 'wss', 'xhr_streaming', 'xhr_polling'],
    });

    const wsStatus = document.getElementById('ws-status');
    setInterval(() => {
        if (echo.connector && echo.connector.pusher) {
            const state = echo.connector.pusher.connection.state;
            wsStatus.className = 'ws-' + state;
            wsStatus.innerText = '● Realtime: ' + state.charAt(0).toUpperCase() + state.slice(1);
        }
    }, 1000);

    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; if(id === 'video-modal') document.getElementById('main-player').pause(); }
    function copyToClipboard(text) { navigator.clipboard.writeText(text).then(() => alert('Đã copy link video!')); }

    function createJobElement(job) {
        const div = document.createElement('div');
        div.className = 'job-item'; div.id = `job-${job.id}`;
        const now = new Date();
        const ddmmyyyy = `${now.getDate().toString().padStart(2,'0')}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getFullYear()}`;
        div.innerHTML = `<div class="job-header"><div><div class="job-title" style="font-weight:700;">vd-factory-${job.id}${ddmmyyyy}</div><div style="color:var(--text-muted);font-size:0.75rem;margin-top:2px;"><i data-lucide="clock" size="12"></i> Vừa xong</div></div><span class="status-badge status-${job.status}">${job.status}</span></div><div class="job-body" style="margin-top:12px;"><div class="progress-bar"><div class="progress-fill" style="width: 0%"></div><div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;"><div style="font-size:0.75rem;color:var(--primary);">Đang xếp hàng...</div><button onclick="deleteJob('${job.id}')" class="btn-action btn-cancel"><i data-lucide="trash-2" size="12"></i> Hủy</button></div></div>`;
        return div;
    }

    document.getElementById('render-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-submit');
        const originalText = btn.innerHTML;
        try {
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader" class="spin"></i> ĐANG GỬI...';
            const formData = new FormData(this);
            const response = await fetch('/generate', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) {
                const errData = await response.json();
                console.error('Validation Error:', errData);
                alert('Lỗi nhập liệu (422): ' + JSON.stringify(errData.errors));
                return;
            }
            const result = await response.json();
            if (result.job_id && !document.getElementById(`job-${result.job_id}`)) {
                const container = document.getElementById('job-list-container');
                container.prepend(createJobElement({id: result.job_id, status: 'pending'}));
                lucide.createIcons();
                if (container.children.length > 5) container.lastElementChild.remove();
            }
        } catch (error) { alert('Lỗi: ' + error.message); } 
        finally { btn.disabled = false; btn.innerHTML = originalText; lucide.createIcons(); }
    });

    // CƠ CHẾ POLLING SIÊU CẤP
    setInterval(() => {
        fetch('/status?t=' + Date.now())
            .then(res => res.json())
            .then(data => {
                if (data && data.data) {
                    data.data.forEach(job => {
                        updateJobUI(job);
                    });
                }
            })
            .catch(err => console.error("Polling error:", err));
    }, 2500); // 2.5 giây một lần cho nhanh

    function updateJobUI(job) {
        const el = document.getElementById(`job-${job.id}`);
        if (!el) return;
        
        // 1. Cập nhật Badge Trạng thái
        const badge = el.querySelector('.status-badge');
        if (badge) {
            const newText = `${job.status} ${job.status === 'processing' ? job.progress + '%' : ''}`;
            if (badge.innerText !== newText) {
                badge.className = `status-badge status-${job.status}`;
                badge.innerText = newText;
            }
        }

        // 2. Cập nhật Body dựa trên trạng thái
        const body = el.querySelector('.job-body');
        if (!body) return;

        if (job.status === 'processing' || job.status === 'pending') {
            if (!body.querySelector('.progress-bar')) {
                body.innerHTML = `
                    <div class="progress-bar"><div class="progress-fill" style="width: ${job.progress}%"></div></div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                        <div class="status-msg"><span class="pulse"></span> ${job.status_message || 'Đang xử lý...'} (${job.progress}%)</div>
                        <button onclick="deleteJob('${job.id}')" class="btn-action btn-cancel"><i data-lucide="trash-2" size="12"></i> Hủy</button>
                    </div>
                `;
                lucide.createIcons();
            } else {
                const fill = body.querySelector('.progress-fill');
                if (fill) fill.style.width = job.progress + '%';
                const msg = body.querySelector('.status-msg');
                if (msg) msg.innerHTML = `<span class="pulse"></span> ${job.status_message || 'Đang xử lý...'} (${job.progress}%)`;
            }
            el.dataset.finalized = ""; // Reset trạng thái cuối
        } else {
            // Trạng thái cuối (Completed / Failed)
            if (el.dataset.finalized !== job.status) {
                if (job.status === 'completed') {
                    body.innerHTML = `<div style="display:flex;gap:8px;"><button onclick="playVideo('${job.output_path}')" class="btn-action btn-play"><i data-lucide="play" size="14"></i> Xem</button><button onclick="copyToClipboard('${job.output_path}')" class="btn-action btn-copy"><i data-lucide="copy" size="14"></i> Copy Link</button><a href="${job.output_path}" download class="btn-action"><i data-lucide="download" size="14"></i> Tải về</a><button onclick="deleteJob('${job.id}')" class="btn-action"><i data-lucide="trash-2" size="14"></i> Xóa</button></div>`;
                } else if (job.status === 'failed') {
                    body.innerHTML = `
                        <div style="color:var(--danger);font-size:0.8rem;background:rgba(239, 68, 68, 0.05);padding:12px;border-radius:10px;border:1px solid rgba(239, 68, 68, 0.1)">
                            <div style="font-weight:700;margin-bottom:6px;"><i data-lucide="alert-circle" size="14"></i> CHI TIẾT LỖI RENDER:</div>
                            <div style="max-height:200px;overflow-y:auto;white-space:pre-wrap;font-family:monospace;background:#000;color:#0f0;padding:10px;border-radius:6px;line-height:1.4;">${job.error_message || 'Không có chi tiết lỗi.'}</div>
                        </div>
                        <button onclick="deleteJob('${job.id}')" class="btn-action" style="margin-top:10px;"><i data-lucide="trash-2" size="14"></i> Xóa</button>
                    `;
                }
                el.dataset.finalized = job.status;
                lucide.createIcons();
            }
        }
    }

    if (typeof echo !== 'undefined') {
        echo.channel('jobs').listen('.job.updated', (e) => {
            updateJobUI(e.job);
        });
    }

    function playVideo(url) { document.getElementById('main-player').src = url; openModal('video-modal'); }
    function deleteJob(id) { if(confirm('Hủy/Xóa video này?')) fetch(`/api/jobs/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } }).then(() => location.reload()); }
</script>
</body>
</html>
