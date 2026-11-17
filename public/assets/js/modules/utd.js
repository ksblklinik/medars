(() => {
    'use strict';

    const root = document.getElementById('utdApp');
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

    async function loadAll() {
        const [reqRes, stokRes, pnRes, kmRes] = await Promise.all([
            fetch('/api/v1/utd/permintaan', { credentials: 'same-origin' }).then((r) => r.json()),
            fetch('/api/v1/utd/stok', { credentials: 'same-origin' }).then((r) => r.json()),
            fetch('/api/v1/utd/pendonor', { credentials: 'same-origin' }).then((r) => r.json()),
            fetch('/api/v1/utd/komponen', { credentials: 'same-origin' }).then((r) => r.json()),
        ]);

        document.getElementById('utdReqBody').innerHTML = (reqRes.data || []).map((r) => `<tr>
            <td>${escapeHtml(r.tanggal)}</td>
            <td><code>${escapeHtml(r.no_rawat)}</code></td>
            <td>${escapeHtml(r.golongan_darah)}</td>
            <td>${escapeHtml(r.no_kantong || '-')}</td>
            <td>${escapeHtml(r.hasil_crossmatch)}</td>
            <td><span class="badge ${r.status === 'Diserahkan' ? 'bg-success' : 'bg-warning text-dark'}">${escapeHtml(r.status)}</span></td>
            <td class="text-end"><button type="button" class="btn btn-xs btn-outline-success js-utd-serah" data-id="${Number(r.id)}" data-kantong="${escapeHtml(r.no_kantong)}">Cocok &amp; Serahkan</button></td>
        </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">Belum ada permintaan.</td></tr>';

        document.getElementById('utdStokBody').innerHTML = (stokRes.data || []).map((r) => `<tr>
            <td><code>${escapeHtml(r.no_kantong)}</code></td>
            <td>${escapeHtml(r.nama_komponen || r.kode_komponen)}</td>
            <td>${escapeHtml(r.golongan_darah)}${escapeHtml(r.resus)}</td>
            <td>${escapeHtml(r.tanggal_kadaluarsa)}</td>
            <td><span class="badge ${r.status === 'Ada' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(r.status)}</span></td>
        </tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted">Stok darah kosong.</td></tr>';

        document.getElementById('utdPendonorBody').innerHTML = (pnRes.data || []).map((r) => `<tr>
            <td><code>${escapeHtml(r.no_pendonor)}</code></td>
            <td>${escapeHtml(r.nama)}</td>
            <td>${escapeHtml(r.golongan_darah)}${escapeHtml(r.resus)}</td>
            <td>${escapeHtml(r.no_telp)}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center text-muted">Belum ada pendonor.</td></tr>';

        document.getElementById('utdKompBody').innerHTML = (kmRes.data || []).map((r) => `<tr>
            <td><code>${escapeHtml(r.kode)}</code></td>
            <td>${escapeHtml(r.nama)}</td>
            <td>${Number(r.lama)} hari</td>
            <td class="text-end">Rp ${Number(r.total).toLocaleString('id-ID')}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center text-muted">Belum ada komponen.</td></tr>';
    }

    async function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        });
    }

    document.getElementById('utdReqForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/utd/permintaan-save', {
            no_rawat: document.getElementById('utdReqRawat').value.trim(),
            golongan_darah: document.getElementById('utdReqGol').value,
            no_kantong: document.getElementById('utdReqKantong').value.trim(),
        });
        document.getElementById('utdReqForm').reset();
        await loadAll();
    });

    document.getElementById('utdReqBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-utd-serah');
        if (!btn) return;
        const kantong = btn.dataset.kantong || prompt('Masukkan No. Kantong Darah:') || '';
        await postJson('/api/v1/utd/crossmatch', {
            id: Number(btn.dataset.id),
            no_kantong: kantong,
            hasil_crossmatch: 'Cocok',
            status: 'Diserahkan',
        });
        await loadAll();
    });

    document.getElementById('utdStokForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/utd/stok-save', {
            no_kantong: document.getElementById('utdStKantong').value.trim(),
            kode_komponen: document.getElementById('utdStKomp').value.trim(),
            golongan_darah: document.getElementById('utdStGol').value,
            tanggal_aftap: document.getElementById('utdStAftap').value,
            tanggal_kadaluarsa: document.getElementById('utdStExp').value,
        });
        document.getElementById('utdStokForm').reset();
        await loadAll();
    });

    document.getElementById('utdPendonorForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/utd/pendonor-save', {
            no_pendonor: document.getElementById('utdPnNo').value.trim(),
            nama: document.getElementById('utdPnNama').value.trim(),
            golongan_darah: document.getElementById('utdPnGol').value,
            no_telp: document.getElementById('utdPnTelp').value.trim(),
        });
        document.getElementById('utdPendonorForm').reset();
        await loadAll();
    });

    document.getElementById('utdKompForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/utd/komponen-save', {
            kode: document.getElementById('utdKmKode').value.trim(),
            nama: document.getElementById('utdKmNama').value.trim(),
            total: Number(document.getElementById('utdKmTotal').value || 0),
        });
        document.getElementById('utdKompForm').reset();
        await loadAll();
    });

    loadAll();
})();
