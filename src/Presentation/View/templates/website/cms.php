<div class="row g-3" id="websiteCmsPage">
    <div class="col-md-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal">Editor Halaman Website Publik</h6>
                <a href="/website" target="_blank" class="btn btn-xs btn-outline-primary">Lihat Website</a>
            </div>
            <div class="card-body">
                <form id="formWebPage">
                    <div class="mb-2"><label class="form-label small">Slug URL</label><input type="text" id="webSlug" class="form-control form-control-sm" placeholder="layanan-unggulan" required></div>
                    <div class="mb-2"><label class="form-label small">Judul Halaman *</label><input type="text" id="webTitle" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Konten</label><textarea id="webContent" class="form-control form-control-sm" rows="4" required></textarea></div>
                    <button type="submit" class="btn btn-sm btn-medical-teal">Simpan &amp; Publikasikan</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2"><h6 class="m-0 fw-bold text-medical-teal">Daftar Halaman CMS</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered mb-0" style="font-size:12px;">
                    <thead><tr><th>Slug</th><th>Judul</th><th>Status</th><th>Update</th></tr></thead>
                    <tbody id="webPagesBody"><tr><td colspan="4" class="text-center text-muted">Memuat halaman...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const esc = (v) => String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const api = window.MedarsApp && window.MedarsApp.apiFetch ? window.MedarsApp.apiFetch : null;
    if (!api) return;

    async function loadPages() {
        const res = await api('/api/v1/website/pages');
        const rows = res.data || [];
        webPagesBody.innerHTML = rows.length ? rows.map((r) => '<tr><td>' + esc(r.slug) + '</td><td>' + esc(r.title) + '</td><td>' + (Number(r.is_published) === 1 ? 'Publik' : 'Draft') + '</td><td>' + esc(r.updated_at) + '</td></tr>').join('') : '<tr><td colspan="4" class="text-center text-muted">Belum ada halaman.</td></tr>';
    }
    formWebPage?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await api('/api/v1/website/pages-save', { method: 'POST', body: JSON.stringify({ slug: webSlug.value, title: webTitle.value, content_html: webContent.value, is_published: 1 }) });
        if (res.status === 'success') { webTitle.value = ''; webContent.value = ''; loadPages(); }
    });
    loadPages();
});
</script>
