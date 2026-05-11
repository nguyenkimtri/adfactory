<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Factory Studio</title>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00f2fe; --secondary: #4facfe; --bg: #0b0e14; --card-bg: #161b22; --border: rgba(255, 255, 255, 0.08); --text: #e6edf3; --text-muted: #8b949e; --success: #238636; --danger: #da3633; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background-color: var(--bg); color: var(--text); overflow: hidden; height: 100vh; display: flex; flex-direction: column; }
        header { padding: 12px 25px; background: rgba(22, 27, 34, 0.95); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 1.3rem; font-weight: 700; background: linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-actions { display: flex; gap: 10px; }
        .btn-nav { padding: 6px 12px; border-radius: 10px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: var(--text); cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 0.85rem; text-decoration: none; }
        main { display: flex; flex: 1; overflow: hidden; padding: 15px; gap: 15px; }
        .sidebar { width: 360px; display: flex; flex-direction: column; gap: 15px; overflow-y: auto; z-index: 10; }
        .content { flex: 1; background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); display: flex; flex-direction: column; padding: 20px; overflow-y: auto; }
        .card { background: var(--card-bg); border-radius: 20px; border: 1px solid var(--border); padding: 18px; }
        .card h3 { font-size: 0.9rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: var(--text-muted); text-transform: uppercase; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.75rem; color: var(--text-muted); }
        .val-display { color: var(--primary); font-weight: 600; }
        textarea, input, select { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 10px; padding: 10px; color: var(--text); font-size: 0.85rem; }
        textarea { height: 60px; resize: none; }
        input[type="range"] { height: 4px; -webkit-appearance: none; background: rgba(255,255,255,0.1); border-radius: 2px; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; height: 16px; width: 16px; border-radius: 50%; background: var(--primary); }
        .btn-primary { width: 100%; padding: 12px; border-radius: 12px; border: none; background: linear-gradient(90deg, var(--secondary), var(--primary)); color: #000; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .job-item { background: rgba(255,255,255,0.02); border-radius: 15px; padding: 15px; margin-bottom: 12px; border: 1px solid var(--border); }
        .status-badge { padding: 3px 10px; border-radius: 6px; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; }
        .status-completed { background: rgba(35, 134, 54, 0.2); color: #3fb950; }
        .status-processing { background: rgba(0, 242, 254, 0.2); color: var(--primary); }
        .progress-bar { height: 8px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; margin-top: 8px; }
        .progress-fill { height: 100%; background: var(--primary); transition: width 0.5s; }
        .status-msg { font-size: 0.7rem; color: var(--primary); margin-top: 6px; display: flex; align-items: center; gap: 5px; }
        .btn-action { padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: var(--text); cursor: pointer; font-size: 0.75rem; display: flex; align-items: center; gap: 5px; }
        
        /* PHÂN TRANG FIX TRIỆT ĐỂ */
        .pagination-container { margin-top: 20px; }
        .pagination-container nav div:first-child { display: none !important; } /* ẨN DÒNG "SHOWING X TO Y" */
        .pagination-container nav div:last-child { display: flex !important; justify-content: center !important; width: 100% !important; }
        .pagination-container span, .pagination-container a { 
            padding: 8px 14px !important; margin: 0 3px !important; border-radius: 8px !important; background: rgba(255,255,255,0.05) !important; 
            border: 1px solid var(--border) !important; color: var(--text) !important; text-decoration: none !important; font-size: 0.85rem !important;
        }
        .pagination-container .active, .pagination-container [aria-current="page"] span { background: var(--primary) !important; color: #000 !important; font-weight: 700 !important; }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: var(--card-bg); width: 95%; max-width: 500px; padding: 20px; border-radius: 20px; position: relative; border: 1px solid var(--border); }
        video { width: 100%; border-radius: 12px; }
        .pulse { width: 6px; height: 6px; background: var(--primary); border-radius: 50%; animation: pulse-anim 1.5s infinite; }
        @keyframes pulse-anim { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
        .spin { animation: spin-anim 1s linear infinite; }
        @keyframes spin-anim { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <header>
        <div class="logo"><i data-lucide="video"></i> Video Factory Studio</div>
        <div class="nav-actions">
            <div id="realtime-status" class="btn-nav" style="border-color: #ffab00; color: #ffab00;">
                <i data-lucide="zap"></i> <span id="status-text">Connecting...</span>
            </div>
            <a href="#" class="btn-nav"><i data-lucide="book-open"></i> Hướng dẫn</a>
            <a href="#" class="btn-nav"><i data-lucide="code-2"></i> API</a>
            <a href="https://vdfs.phung.vn/video_factory_template.csv" class="btn-nav"><i data-lucide="download"></i> Mẫu CSV</a>
        </div>
    </header>

    <main>
        <div class="sidebar">
            <div class="card">
                <h3><i data-lucide="layers"></i> Tài nguyên</h3>
                <form id="generate-form">
                    @csrf
                    <div class="form-group"><label>Audio chính (Mỗi link 1 dòng)</label><textarea name="audio_url" placeholder="Dán link audio..."></textarea></div>
                    <div class="form-group"><label>Video nguồn (Mỗi link 1 dòng)</label><textarea name="video_sources" placeholder="Link YouTube, TikTok..."></textarea></div>
                    <div class="form-group"><label>Nhạc nền (Mỗi link 1 dòng)</label><textarea name="bg_music_url" placeholder="Link nhạc nền..."></textarea></div>
                    <div class="form-group" style="display:flex; justify-content:space-between;"><label>Tự động tạo phụ đề AI</label><input type="checkbox" name="subtitles" checked style="width:auto; scale:1.3;"></div>
                    <div class="form-group"><label>Logo URL</label><input type="text" name="logo_url" value="https://phung.vn/wp-content/uploads/2025/03/cropped-PHUNG-VN-FAV-192x192.png"></div>
                </form>
            </div>
            <div class="card">
                <h3><i data-lucide="sliders"></i> Tinh chỉnh</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group"><label>Định dạng</label><select id="format"><option value="9:16">Dọc (9:16)</option><option value="16:9">Ngang (16:9)</option></select></div>
                    <div class="form-group"><label>Độ mờ <span class="val-display" id="val-logo-opacity">80%</span></label><input type="range" id="logo_opacity" min="0" max="100" value="80" oninput="updateVal('logo-opacity', this.value+'%')"></div>
                    <div class="form-group"><label>Size <span class="val-display" id="val-logo-size">200px</span></label><input type="range" id="logo_size" min="50" max="500" value="200" oninput="updateVal('logo-size', this.value+'px')"></div>
                    <div class="form-group"><label>Tốc độ <span class="val-display" id="val-logo-speed">5x</span></label><input type="range" id="logo_speed" min="1" max="20" value="5" oninput="updateVal('logo-speed', this.value+'x')"></div>
                    <div class="form-group"><label>Vol Audio <span class="val-display" id="val-vol-audio">100%</span></label><input type="range" id="volume_audio" min="0" max="200" value="100" oninput="updateVal('vol-audio', this.value+'%')"></div>
                    <div class="form-group"><label>Vol Video <span class="val-display" id="val-vol-video">0%</span></label><input type="range" id="volume_video" min="0" max="100" value="0" oninput="updateVal('vol-video', this.value+'%')"></div>
                </div>
                <button type="button" id="submit-btn" class="btn-primary"><i data-lucide="zap"></i> BẮT ĐẦU RENDER</button>
            </div>
        </div>

        <div class="content">
            <h3><i data-lucide="history"></i> Lịch sử sản xuất</h3>
            <div id="job-list-container">
                @if(isset($jobs))
                    @foreach($jobs as $job)
                        <div class="job-item" id="job-{{ $job->id }}">
                            <div class="job-header">
                                <div><div style="font-weight:700;font-size:0.9rem;">vd-factory-{{ $job->id }}</div><div style="color:var(--text-muted);font-size:0.65rem;">{{ $job->created_at->format('H:i:s d/m') }}</div></div>
                                <span class="status-badge status-{{ $job->status }}">{{ $job->status }} {{ $job->status === 'processing' ? $job->progress.'%' : '' }}</span>
                            </div>
                            <div class="job-body" style="margin-top:10px;">
                                @if($job->status === 'processing' || $job->status === 'pending')
                                    <div class="progress-bar"><div class="progress-fill" style="width: {{ $job->progress }}%"></div></div>
                                    <div class="status-msg"><span class="pulse"></span> {{ $job->status_message ?: 'Đang chờ...' }} ({{ $job->progress }}%)</div>
                                @elseif($job->status === 'completed')
                                    <div style="display:flex;gap:6px;"><button onclick="playVideo('{{ $job->output_path }}')" class="btn-action"><i data-lucide="play" size="12"></i> Xem</button><a href="{{ $job->output_path }}" download class="btn-action"><i data-lucide="download" size="12"></i> Tải về</a><button onclick="deleteJob('{{ $job->id }}')" class="btn-action"><i data-lucide="trash-2" size="12"></i> Xóa</button></div>
                                @elseif($job->status === 'failed')
                                    <div style="color:var(--danger);font-size:0.7rem;background:rgba(218,54,51,0.05);padding:8px;border-radius:8px;border:1px solid rgba(218,54,51,0.1);max-height:100px;overflow:hidden;">{{ $job->error_message }}</div>
                                    <button onclick="deleteJob('{{ $job->id }}')" class="btn-action" style="margin-top:8px;"><i data-lucide="trash-2" size="12"></i> Xóa</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
            <div class="pagination-container">{{ $jobs->links() }}</div>
        </div>
    </main>

    <div id="video-modal" class="modal"><div class="modal-content"><button onclick="closeModal()" style="position:absolute;top:15px;right:15px;background:none;border:none;color:#fff;cursor:pointer;"><i data-lucide="x" size="20"></i></button><video id="player" controls></video></div></div>

    <script>
        lucide.createIcons();
        function updateVal(id, val) { document.getElementById('val-'+id).innerText = val; }

        document.getElementById('submit-btn').addEventListener('click', async () => {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true; btn.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Chờ...'; lucide.createIcons();
            const formData = new FormData(document.getElementById('generate-form'));
            const fields = ['format','logo_opacity','logo_size','logo_speed','volume_audio','volume_video'];
            fields.forEach(f => formData.append(`settings[${f}]`, document.getElementById(f).value));
            formData.append('settings[volume_music]', "20");
            formData.append('settings[subtitles]', document.querySelector('input[name="subtitles"]').checked ? "1" : "0");
            try { const res = await axios.post('/generate', formData); if (res.data.success) location.reload(); } 
            catch (err) { alert('Lỗi: ' + (err.response?.data?.message || err.message)); } 
            finally { btn.disabled = false; btn.innerHTML = '<i data-lucide="zap"></i> BẮT ĐẦU RENDER'; lucide.createIcons(); }
        });

        async function deleteJob(id) { if(confirm('Xóa?')) { await axios.delete(`/api/jobs/${id}`); document.getElementById(`job-${id}`).remove(); } }
        function playVideo(url) { const m = document.getElementById('video-modal'); document.getElementById('player').src = url; m.style.display = 'flex'; document.getElementById('player').play(); }
        function closeModal() { document.getElementById('video-modal').style.display = 'none'; document.getElementById('player').pause(); }

        // POLLING HIỆU QUẢ
        async function checkStatus() {
            try {
                const res = await fetch('/status?t=' + Date.now());
                const data = await res.json();
                if (data && data.data) {
                    data.data.forEach(job => {
                        const el = document.getElementById(`job-${job.id}`);
                        if (el) {
                            const badge = el.querySelector('.status-badge');
                            if(badge) { badge.className = `status-badge status-${job.status}`; badge.innerText = `${job.status} ${job.status === 'processing' ? job.progress+'%' : ''}`; }
                            const body = el.querySelector('.job-body');
                            if (job.status === 'processing' || job.status === 'pending') {
                                const f = body.querySelector('.progress-fill'); if(f) f.style.width = job.progress+'%';
                                const m = body.querySelector('.status-msg'); 
                                if(m) {
                                    let txt = job.status_message || "Đang xử lý...";
                                    if(!job.status_message) { if(job.progress<30) txt="Đang tải..."; else if(job.progress<70) txt="Đang render..."; else txt="Hoàn tất..."; }
                                    m.innerHTML = `<span class="pulse"></span> ${txt} (${job.progress}%)`;
                                }
                            } else if (el.dataset.done !== job.status) { location.reload(); }
                        }
                    });
                    document.getElementById('status-text').innerText = "Connected";
                    document.getElementById('realtime-status').style.color = "#3fb950";
                }
            } catch (e) { document.getElementById('status-text').innerText = "Connecting..."; document.getElementById('realtime-status').style.color = "#ffab00"; }
            setTimeout(checkStatus, 3000);
        }
        checkStatus();
    </script>
</body>
</html>
