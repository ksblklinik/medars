(() => {
    'use strict';

    const root = document.getElementById('veronisaApp');
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

    async function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        });
    }

    async function loadClaims() {
        const from = encodeURIComponent(document.getElementById('vrFrom').value);
        const to = encodeURIComponent(document.getElementById('vrTo').value);
        const q = encodeURIComponent(document.getElementById('vrSearch').value.trim());
        const res = await fetch(`/api/v1/veronisa/claims?from=${from}&to=${to}&q=${q}`, { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];

        document.getElementById('vrClaimsBody').innerHTML = rows.map((r) => `<tr>
            <td>${escapeHtml(r.tanggal)}</td>
            <td><code>${escapeHtml(r.no_rawat)}</code></td>
            <td>${escapeHtml(r.nosep || '-')}</td>
            <td><span class="badge ${r.status === 'Layak' || r.status === 'Disetujui' ? 'bg-success' : 'bg-warning text-dark'}">${escapeHtml(r.status)}</span></td>
            <td>${escapeHtml(r.catatan)}</td>
            <td class="text-end text-nowrap">
                <button type="button" class="btn btn-xs btn-outline-success me-1 js-vr-status" data-id="${Number(r.id)}" data-st="Layak">Layak</button>
                <a href="/veronisa/pdf?id=${Number(r.id)}" target="_blank" class="btn btn-xs btn-outline-secondary me-1">PDF</a>
                <button type="button" class="btn btn-xs btn-outline-danger js-vr-del" data-id="${Number(r.id)}">&times;</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada pengajuan Veronisa.</td></tr>';
    }

    async function loadMapping() {
        const res = await fetch('/api/v1/veronisa/mapping', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('vrMapBody').innerHTML = rows.map((r) => `<tr>
            <td><code>${escapeHtml(r.kode_brng)}</code></td>
            <td><code>${escapeHtml(r.kode_dpho)}</code></td>
            <td>${escapeHtml(r.nama_dpho)}</td>
            <td class="text-end">Rp ${Number(r.harga_dpho).toLocaleString('id-ID')}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada mapping obat DPHO.</td></tr>';
    }

    document.getElementById('vrClaimForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/veronisa/claims-save', {
            no_rawat: document.getElementById('vrRawat').value.trim(),
            no_rkm_medis: document.getElementById('vrRm').value.trim(),
            nosep: document.getElementById('vrSep').value.trim(),
            catatan: document.getElementById('vrCatatan').value.trim(),
        });
        document.getElementById('vrClaimForm').reset();
        await loadClaims();
    });

    document.getElementById('vrClaimsBody').addEventListener('click', async (e) => {
        const stBtn = e.target.closest('.js-vr-status');
        if (stBtn) {
            await postJson('/api/v1/veronisa/claims-status', { id: Number(stBtn.dataset.id), status: stBtn.dataset.st, catatan: 'Terverifikasi Layak' });
            await loadClaims();
            return;
        }
        const delBtn = e.target.closest('.js-vr-del');
        if (delBtn && confirm('Hapus pengajuan ini?')) {
            await postJson('/api/v1/veronisa/claims-delete', { id: Number(delBtn.dataset.id) });
            await loadClaims();
        }
    });

    document.getElementById('vrMapForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/veronisa/mapping-save', {
            kode_brng: document.getElementById('vrMapBrng').value.trim(),
            kode_dpho: document.getElementById('vrMapDpho').value.trim(),
            nama_dpho: document.getElementById('vrMapNama').value.trim(),
            harga_dpho: Number(document.getElementById('vrMapHarga').value || 0),
        });
        document.getElementById('vrMapForm').reset();
        await loadMapping();
    });

    document.getElementById('vrFilterBtn').addEventListener('click', loadClaims);
    loadClaims();
    loadMapping();
})();
