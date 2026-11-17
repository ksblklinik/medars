<!-- Toolbar Paritas ksmlite (Cari Outbox WA, Filter Sesi/Status) -->
<div class="ksmlite-toolbar d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="input-group input-group-sm toolbar-search">
            <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
            <input type="search" class="form-control" placeholder="Cari No. HP / Isi Pesan..." data-ksmlite-search="waOutboxBody">
        </div>
        <select class="form-select form-select-sm toolbar-filter" data-ksmlite-status-filter="waOutboxBody">
            <option value="">Semua Status</option>
            <option value="sent">Sent</option>
            <option value="failed">Failed</option>
        </select>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-dark border"><i class="fa fa-shield-halved me-1 text-medical-teal"></i>10-Lapis Anti-Banned Meta</span>
    </div>
</div>

<div class="row g-3" id="waWorkspace">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-paper-plane me-1"></i> Kirim WhatsApp (Anti-Banned Meta)</h6>
            </div>
            <div class="card-body">
                <form id="formSendWa">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Sesi Pengirim</label>
                        <select class="form-select form-select-sm" id="waSession">
                            <option value="system">Sesi 1: System (Notifikasi 1-Arah)</option>
                            <option value="cs">Sesi 2: CS (Interaktif 2-Arah)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nomor Tujuan (628...)</label>
                        <input type="text" class="form-control form-control-sm" id="waPhone" placeholder="081234567890" required>
                        <div class="form-text small">Staf non-admin hanya diizinkan mengirim ke nomor Whitelist.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Pesan</label>
                        <textarea class="form-control form-control-sm" id="waMessage" rows="4" placeholder="Halo Bpk/Ibu..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm btn-medical-teal" id="btnSendWa">
                        <i class="fa fa-paper-plane me-1"></i> Kirim Pesan
                    </button>
                </form>
                <div id="waSendFeedback" class="mt-2"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-shield-halved me-1"></i> Tambah Kontak Whitelist</h6>
            </div>
            <div class="card-body">
                <form id="formAddWhitelist" class="row g-2">
                    <div class="col-md-6">
                        <input type="text" class="form-control form-control-sm" id="wlPhone" placeholder="08..." required>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control form-control-sm" id="wlName" placeholder="Nama Kontak" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Daftarkan Whitelist</button>
                    </div>
                </form>
                <div id="wlFeedback" class="mt-2"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-list me-1"></i> Log Pengiriman Terkini (Outbox)</h6>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnRefreshOutbox"><i class="fa fa-rotate"></i></button>
            </div>
            <div class="card-body p-0" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-2">Sesi</th>
                            <th>Tujuan</th>
                            <th>Pesan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="waOutboxBody">
                        <tr><td colspan="4" class="text-center text-muted py-3 small">Memuat outbox...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-2" id="waCrmPanel">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-comments me-1"></i> Live Chat 2-Arah (Sesi CS) &amp; AI Draft CS</h6>
                <button type="button" class="btn btn-xs btn-outline-primary" id="btnAiDraftCs">AI Draft CS</button>
            </div>
            <div class="card-body">
                <div class="input-group input-group-sm mb-2">
                    <input type="text" id="waCsPhone" class="form-control" placeholder="No. WA Pasien (628...)">
                    <button type="button" id="btnLoadCsChat" class="btn btn-outline-secondary">Muat Chat</button>
                </div>
                <div id="waCsChatBox" class="border rounded p-2 mb-2 bg-light" style="height:180px;overflow-y:auto;font-size:12px;">
                    <div class="text-muted text-center">Belum ada percakapan sesi CS.</div>
                </div>
                <form id="formWaCsSend" class="d-flex gap-2">
                    <input type="text" id="waCsMessage" class="form-control form-control-sm" placeholder="Ketik balasan CS atau klik AI Draft CS..." required>
                    <button type="submit" class="btn btn-sm btn-medical-teal">Kirim</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-bullhorn me-1"></i> Template Broadcast Terjadwal</h6>
            </div>
            <div class="card-body">
                <form id="formWaBroadcast" class="row g-2 mb-2">
                    <div class="col-md-6"><input type="text" id="waBcTitle" class="form-control form-control-sm" placeholder="Judul Broadcast" required></div>
                    <div class="col-md-6"><input type="datetime-local" id="waBcSchedule" class="form-control form-control-sm"></div>
                    <div class="col-md-12"><textarea id="waBcTemplate" class="form-control form-control-sm" rows="2" placeholder="Isi template (spintax / {nama})..." required></textarea></div>
                    <div class="col-md-12"><button type="submit" class="btn btn-sm btn-success">Jadwalkan Broadcast</button></div>
                </form>
                <div style="max-height:140px;overflow-y:auto;">
                    <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                        <thead><tr><th>Judul</th><th>Jadwal</th><th>Status</th></tr></thead>
                        <tbody id="waBcBody"><tr><td colspan="3" class="text-center text-muted">Belum ada template terjadwal.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/wagateway.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const api = window.MedarsApp && window.MedarsApp.apiFetch ? window.MedarsApp.apiFetch : null;
    if (!api) return;

    let lastIncoming = '';
    async function loadCsChat() {
        const phone = document.getElementById('waCsPhone').value.trim();
        const res = await api('/api/v1/wagateway/cs-chat?phone=' + encodeURIComponent(phone));
        const rows = res.data || [];
        const box = document.getElementById('waCsChatBox');
        lastIncoming = '';
        rows.forEach((r) => { if (r.direction === 'in') lastIncoming = r.message; });
        box.innerHTML = rows.length ? rows.map((r) => '<div class="mb-1 ' + (r.direction === 'out' ? 'text-end' : '') + '"><span class="badge ' + (r.direction === 'out' ? 'bg-success' : 'bg-secondary') + '">' + esc(r.sender_name) + '</span> ' + esc(r.message) + '</div>').join('') : '<div class="text-muted text-center">Belum ada percakapan sesi CS.</div>';
    }
    async function loadBroadcasts() {
        const res = await api('/api/v1/wagateway/broadcasts');
        const rows = res.data || [];
        document.getElementById('waBcBody').innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.title) + '</td><td>' + esc(r.schedule_at) + '</td><td>' + esc(r.status) + '</td></tr>').join('') : '<tr><td colspan="3" class="text-center text-muted">Belum ada template terjadwal.</td></tr>';
    }
    document.getElementById('btnLoadCsChat')?.addEventListener('click', loadCsChat);
    document.getElementById('btnAiDraftCs')?.addEventListener('click', async () => {
        const res = await api('/api/v1/wagateway/ai-draft', { method: 'POST', body: JSON.stringify({ incoming_message: lastIncoming || document.getElementById('waCsMessage').value }) });
        if (res.status === 'success' && res.data) {
            document.getElementById('waCsMessage').value = res.data.draft || '';
        }
    });
    document.getElementById('formWaCsSend')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/wagateway/cs-chat-send', { method: 'POST', body: JSON.stringify({ phone: document.getElementById('waCsPhone').value, message: document.getElementById('waCsMessage').value, direction: 'out' }) });
        if (res.status === 'success') { document.getElementById('waCsMessage').value = ''; loadCsChat(); }
    });
    document.getElementById('formWaBroadcast')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/wagateway/broadcasts-save', { method: 'POST', body: JSON.stringify({ title: waBcTitle.value, schedule_at: waBcSchedule.value, message_template: waBcTemplate.value }) });
        if (res.status === 'success') { waBcTitle.value = ''; waBcTemplate.value = ''; loadBroadcasts(); }
    });
    loadCsChat();
    loadBroadcasts();
});
</script>
