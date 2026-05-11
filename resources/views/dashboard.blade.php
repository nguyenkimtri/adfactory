<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Factory Studio - Tự động hóa sản xuất video</title>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00f2fe;
            --secondary: #4facfe;
            --bg: #0b0e14;
            --card-bg: #161b22;
            --border: rgba(255, 255, 255, 0.08);
            --text: #e6edf3;
            --text-muted: #8b949e;
            --success: #238636;
            --danger: #da3633;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Outfit', sans-serif; }
        body { background-color: var(--bg); color: var(--text); overflow: hidden; height: 100vh; display: flex; flex-direction: column; }

        header { padding: 15px 30px; background: rgba(22, 27, 34, 0.8); backdrop-filter: blur(10px); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; z-index: 100; }
        .logo { display: flex; align-items: center; gap: 12px; font-size: 1.5rem; font-weight: 700; background: linear-gradient(90deg, var(--primary), var(--secondary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav-actions { display: flex; gap: 12px; }
        .btn-nav { padding: 8px 16px; border-radius: 12px; border: 1px solid var(--border); background: rgba(255,255,255,0.05); color: var(--text); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; text-decoration: none; transition: all 0.2s; }
        .btn-nav:hover { background: rgba(255,255,255,0.1); border-color: var(--primary); }

        main { display: flex; flex: 1; overflow: hidden; padding: 20px; gap: 20px; }
        .sidebar { width: 380px; display: flex; flex-direction: column; gap: 20px; overflow-y: auto; padding-right: 10px; }
        .content { flex: 1; background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border); display: flex; flex-direction: column; padding: 25px; overflow-y: auto; }

        .card { background: var(--card-bg); border-radius: 24px; border: 1px solid var(--border); padding: 20px; }
        .card h3 { font-size: 1rem; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; }
        textarea, input, select { width: 100%; background: rgba(0,0,0,0.2); border: 1px solid var(--border); border-radius: 12px; padding: 12px; color: var(--text); font-size: 0.9rem; transition: all 0.2s; }
        textarea:focus, input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px rgba(0, 242, 254, 0.1); }
        textarea { height: 80px; resize: none; }

        .btn-primary { width: 100%; padding: 15px; border-radius: 16px; border: none; background: linear-gradient(90deg, var(--secondary), var(--primary)); color: white; font-weight: 700; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: transform 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-primary:active { transform: translateY(0); }

        .job-item { background: rgba(255,255,255,0.02); border-radius: 20px; padding: 18px; margin-bottom: 15px; border: 1px solid var(--border); transition: all 0.3s; }
        .job-item:hover { border-color: rgba(0, 242, 254, 0.3); background: rgba(255,255,255,0.04); }
        .job-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .status-badge { padding: 4px 12px; border-radius: 8px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
        .status-pending { background: rgba(255, 171, 0, 0.1); color: #ffab00; }
        .status-processing { background: rgba(0, 242, 254, 0.1); color: var(--primary); }
        .status-completed { background: rgba(35, 134, 54, 0.1); color: #3fb950; }
        .status-failed { background: rgba(218, 54, 51, 0.1); color: #f85149; }

        .progress-bar { height: 10px; background: rgba(255,255,255,0.1); border-radius: 5px; overflow: hidden; margin-top: 10px; }
        .progress-fill { height: 100%; background: var(--primary); width: 0%; transition: width 0.4s ease; display: flex; align-items: center; justify-content: center; font-size: 0.6rem; color: #fff; }
        .status-msg { font-size: 0.75rem; color: var(--primary); display: flex; align-items: center; gap: 6px; }

        .btn-action { padding: 6px 12px; border-radius: 10px; border: 1px solid var(--border); background: transparent; color: var(--text); cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; gap: 6px; }
        .btn-action:hover { background: rgba(255,255,255,0.05); }
        .btn-play { color: var(--primary); border-color: rgba(0, 242, 254, 0.2); }
        .btn-copy { color: #a855f7; }
        .btn-cancel { color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }

        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); backdrop-filter: blur(15px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
        .modal-content { background: var(--card-bg); width: 90%; max-width: 600px; padding: 20px; border-radius: 24px; position: relative; border: 1px solid var(--border); display: flex; flex-direction: column; align-items: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        video { width: 100%; border-radius: 16px; margin-bottom: 15px; background: #000; }

        .pulse { width: 8px; height: 8px; background: var(--primary); border-radius: 50%; box-shadow: 0 0 0 rgba(0, 242, 254, 0.4); animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(0, 242, 254, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(0, 242, 254, 0); } 100% { box-shadow: 0 0 0 0 rgba(0, 242, 254, 0); } }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        .pagination-container nav { display: flex; gap: 5px; }
        .pagination-container .relative { background: var(--card-bg); border: 1px solid var(--border); color: var(--text); padding: 8px 14px; border-radius: 10px; text-decoration: none; font-size: 0.8rem; }
        .pagination-container .bg-white { background: var(--primary) !important; color: #000 !important; border-color: var(--primary); }
        .pagination-container .text-gray-500 { color: var(--text-muted); }
        .pagination-container span, .pagination-container a { display: flex; align-items: center; justify-content: center; min-width: 38px; height: 38px; }
    </style>
</head>
<body>
    <header>
        <div class="logo"><i data-lucide="video"></i> Video Factory Studio</div>
        <div class="nav-actions">
            <div id="realtime-status" class="btn-nav" style="border-color: #ffab00; color: #ffab00;">
                <i data-lucide="zap"></i> Realtime: <span id="status-text">Connecting...</span>
            </div>
            <a href="#" class="btn-nav"><i data-lucide="book-open"></i> Hướng dẫn</a>
            <a href="#" class="btn-nav"><i data-lucide="code-2"></i> API (n8n)</a>
            <a href="https://vdfs.phung.vn/video_factory_template.csv" class="btn-nav"><i data-lucide="download"></i> Tải file mẫu</a>
        </div>
    </header>

    <main>
        <div class="sidebar">
            <div class="card">
                <h3><i data-lucide="layers"></i> Tài nguyên</h3>
                <form id="generate-form">
                    @csrf
                    <div class="form-group">
                        <label>Audio chính (Mỗi link 1 dòng)</label>
                        <textarea name="audio_url" placeholder="Dán link audio MP3..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Video nguồn (Mỗi link 1 dòng)</label>
                        <textarea name="video_sources" placeholder="Link YouTube, TikTok, MP4..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Nhạc nền (Mỗi link 1 dòng)</label>
                        <textarea name="bg_music_url" placeholder="Link nhạc nền..."></textarea>
                    </div>
                    <div class="form-group" style="display:flex; justify-content:space-between; align-items:center;">
                        <label>Tự động tạo phụ đề AI</label>
                        <input type="checkbox" name="subtitles" checked style="width:auto;">
                    </div>
                    <div class="form-group">
                        <label>Logo</label>
                        <input type="text" name="logo_url" placeholder="Link ảnh logo PNG..." value="https://phung.vn/wp-content/uploads/2025/03/cropped-PHUNG-VN-FAV-192x192.png">
                    </div>
                </form>
            </div>

            <div class="card">
                <h3><i data-lucide="sliders"></i> Tinh chỉnh</h3>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Định dạng</label>
                        <select id="format">
                            <option value="9:16">Dọc (9:16)</option>
                            <option value="16:9">Ngang (16:9)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Độ mờ Logo</label>
                        <input type="range" id="logo_opacity" min="0" max="100" value="80">
                    </div>
                    <div class="form-group">
                        <label>Kích thước Logo</label>
                        <input type="range" id="logo_size" min="50" max="500" value="200">
                    </div>
                    <div class="form-group">
                        <label>Tốc độ Logo</label>
                        <input type="range" id="logo_speed" min="1" max="20" value="5">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:10px;">
                    <div class="form-group">
                        <label>Âm lượng Audio chính</label>
                        <input type="range" id="volume_audio" min="0" max="200" value="100">
                    </div>
                    <div class="form-group">
                        <label>Video gốc</label>
                        <input type="range" id="volume_video" min="0" max="100" value="0">
                    </div>
                    <div class="form-group">
                        <label>Nhạc nền</label>
                        <input type="range" id="volume_music" min="0" max="100" value="20">
                    </div>
                </div>
                <button type="button" id="submit-btn" class="btn-primary">
                    <i data-lucide="zap"></i> BẮT ĐẦU RENDER
                </button>
            </div>
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
            <div class="pagination-container" style="margin-top:20px; display:flex; justify-content:center; gap:10px;">
                {{ $jobs->links() }}
            </div>
            @endif
        </div>
    </main>

    <div id="video-modal" class="modal">
        <div class="modal-content">
            <button onclick="closeModal()" style="position:absolute; top:15px; right:15px; background:none; border:none; color:var(--text-muted); cursor:pointer;"><i data-lucide="x" size="24"></i></button>
            <video id="player" controls></video>
            <div style="display:flex; gap:10px; width:100%;">
                <a id="download-link" href="#" download class="btn-primary" style="flex:1;">Tải về máy</a>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        // GỬI LỆNH RENDER
        document.getElementById('submit-btn').addEventListener('click', async () => {
            const btn = document.getElementById('submit-btn');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="spin"></i> Đang khởi tạo...';
            lucide.createIcons();

            const formData = new FormData(document.getElementById('generate-form'));
            formData.append('settings[format]', document.getElementById('format').value);
            formData.append('settings[logo_opacity]', document.getElementById('logo_opacity').value);
            formData.append('settings[logo_size]', document.getElementById('logo_size').value);
            formData.append('settings[logo_speed]', document.getElementById('logo_speed').value);
            formData.append('settings[volume_audio]', document.getElementById('volume_audio').value);
            formData.append('settings[volume_video]', document.getElementById('volume_video').value);
            formData.append('settings[volume_music]', document.getElementById('volume_music').value);
            formData.append('settings[subtitles]', document.querySelector('input[name="subtitles"]').checked ? "1" : "0");

            try {
                const res = await axios.post('/generate', formData);
                if (res.data.success) {
                    location.reload();
                }
            } catch (err) {
                alert('Lỗi: ' + (err.response?.data?.message || err.message));
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
                lucide.createIcons();
            }
        });

        // XÓA JOB
        async function deleteJob(id) {
            if (!confirm('Bạn có chắc muốn xóa bản ghi này?')) return;
            try {
                await axios.delete(`/api/jobs/${id}`);
                document.getElementById(`job-${id}`).remove();
            } catch (err) { alert('Lỗi xóa: ' + err.message); }
        }

        function playVideo(url) {
            const modal = document.getElementById('video-modal');
            const player = document.getElementById('player');
            const download = document.getElementById('download-link');
            player.src = url;
            download.href = url;
            modal.style.display = 'flex';
            player.play();
        }

        function closeModal() {
            const modal = document.getElementById('video-modal');
            const player = document.getElementById('player');
            player.pause();
            modal.style.display = 'none';
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => alert('Đã copy link!'));
        }

        // CƠ CHẾ POLLING SIÊU CẤP
        setInterval(() => {
            fetch('/status?t=' + Date.now())
                .then(res => res.json())
                .then(data => {
                    if (data && data.data) {
                        data.data.forEach(job => {
                            updateJobUI(job);
                        });
                        document.getElementById('status-text').innerText = "Connected";
                        document.getElementById('realtime-status').style.borderColor = "#238636";
                        document.getElementById('realtime-status').style.color = "#3fb950";
                    }
                })
                .catch(err => {
                    console.error("Polling error:", err);
                    document.getElementById('status-text').innerText = "Error";
                    document.getElementById('realtime-status').style.borderColor = "#da3633";
                    document.getElementById('realtime-status').style.color = "#f85149";
                });
        }, 2500);

        function updateJobUI(job) {
            const el = document.getElementById(`job-${job.id}`);
            if (!el) return;
            
            const badge = el.querySelector('.status-badge');
            if (badge) {
                const newText = `${job.status} ${job.status === 'processing' ? job.progress + '%' : ''}`;
                if (badge.innerText !== newText) {
                    badge.className = `status-badge status-${job.status}`;
                    badge.innerText = newText;
                }
            }

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
                    if (msg) {
                        let statusText = job.status_message;
                        if (!statusText || statusText === "" || statusText === "null") {
                            if (job.progress < 30) statusText = "Đang tải tài nguyên...";
                            else if (job.progress < 70) statusText = "Đang xử lý hình ảnh...";
                            else statusText = "Đang render video...";
                        }
                        msg.innerHTML = `<span class="pulse"></span> ${statusText} (${job.progress}%)`;
                    }
                }
                el.dataset.finalized = "";
            } else {
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
    </script>
</body>
</html>
