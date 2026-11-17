(() => {
    'use strict';

    const root = document.getElementById('ruangTindakanApp');
    if (!root) return;

    const csrfToken = root.dataset.csrf || '';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function loadList() {
        const from = encodeURIComponent(document.getElementById('rtFrom').value);
        const to = encodeURIComponent(document.getElementById('rtTo').value);
        const q = encodeURIComponent(document.getElementById('rtSearch').value.trim());
        const res = await fetch(`/api/v1/ruang-tindakan/list?from=${from}&to=${to}&q=${q}`, { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];

        document.getElementById('rtTableBody').innerHTML = rows.map((r) => `<tr>
            <td>${escapeHtml(r.tgl_tindakan)}</td>
            <td><code>${escapeHtml(r.no_rawat)}</code></td>
            <td>${escapeHtml(r.kd_jenis_prw)}</td>
            <td>${escapeHtml(r.diagnosis)}</td>
            <td>${escapeHtml(r.nm_petugas)}</td>
            <td><span class="badge ${r.status === 'Selesai' ? 'bg-success' : 'bg-warning text-dark'}">${escapeHtml(r.status)}</span></td>
            <td class="text-end text-nowrap">
                <a href="/ruang-tindakan/cetak?id=${Number(r.id)}" target="_blank" class="btn btn-xs btn-outline-secondary me-1">Cetak</a>
                <button type="button" class="btn btn-xs btn-outline-danger js-rt-del" data-id="${Number(r.id)}">Hapus</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada data tindakan.</td></tr>';
    }

    document.getElementById('rtForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await fetch('/api/v1/ruang-tindakan/save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                no_rawat: document.getElementById('rtRawat').value.trim(),
                kd_jenis_prw: document.getElementById('rtKode').value.trim(),
                tgl_tindakan: document.getElementById('rtTgl').value,
                status: document.getElementById('rtStatus').value,
                diagnosis: document.getElementById('rtDiagnosis').value.trim(),
                kd_dokter: document.getElementById('rtDokter').value.trim(),
                kd_paramedis1: document.getElementById('rtParamedis').value.trim(),
                laporan_operasi: document.getElementById('rtLaporan').value.trim(),
                catatan: document.getElementById('rtCatatan').value.trim(),
            }),
        });
        const json = await res.json();
        if (json.status !== 'success') return alert(json.message || 'Gagal menyimpan tindakan.');
        document.getElementById('rtForm').reset();
        await loadList();
    });

    document.getElementById('rtTableBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-rt-del');
        if (!btn || !confirm('Hapus data tindakan ini?')) return;
        await fetch('/api/v1/ruang-tindakan/delete', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ id: Number(btn.dataset.id) }),
        });
        await loadList();
    });

    document.getElementById('rtFilterBtn').addEventListener('click', loadList);
    loadList();
})();
