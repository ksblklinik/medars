(() => {
    'use strict';

    const root = document.getElementById('operasiApp');
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

    async function loadPaket() {
        const res = await fetch('/api/v1/operasi/paket', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];

        document.getElementById('opPaketBody').innerHTML = rows.map((r) => `<tr>
            <td><code>${escapeHtml(r.kode_paket)}</code></td>
            <td class="fw-semibold">${escapeHtml(r.nm_perawatan)}</td>
            <td>${escapeHtml(r.kategori)}</td>
            <td class="text-end">Rp ${Number(r.tarif_total).toLocaleString('id-ID')}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center text-muted">Belum ada paket operasi.</td></tr>';

        const opts = rows.map((r) => `<option value="${escapeHtml(r.kode_paket)}">${escapeHtml(r.nm_perawatan)} (Rp ${Number(r.tarif_total).toLocaleString('id-ID')})</option>`).join('');
        document.getElementById('opBkPaket').innerHTML = opts;
        document.getElementById('opLpPaket').innerHTML = '<option value="">-- Tanpa Paket --</option>' + opts;
    }

    async function loadBookings() {
        const res = await fetch('/api/v1/operasi/bookings', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('opBookingBody').innerHTML = rows.map((r) => `<tr>
            <td>${escapeHtml(r.tanggal)}</td>
            <td>${escapeHtml(r.jam_mulai)} - ${escapeHtml(r.jam_selesai)}</td>
            <td><code>${escapeHtml(r.no_rawat)}</code></td>
            <td>${escapeHtml(r.nm_perawatan || r.kode_paket)}</td>
            <td>${escapeHtml(r.kd_ruang_ok)}</td>
            <td><span class="badge ${r.status === 'Selesai' ? 'bg-success' : 'bg-warning text-dark'}">${escapeHtml(r.status)}</span></td>
            <td class="text-end">
                <button type="button" class="btn btn-xs btn-outline-success js-op-done" data-id="${Number(r.id)}">Selesai</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">Belum ada jadwal operasi.</td></tr>';
    }

    async function loadLaporan() {
        const res = await fetch('/api/v1/operasi/laporan', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('opLaporanBody').innerHTML = rows.map((r) => `<tr>
            <td>${escapeHtml(r.tanggal)}</td>
            <td><code>${escapeHtml(r.no_rawat)}</code></td>
            <td>${escapeHtml(r.diagnosa_preop)} &rarr; ${escapeHtml(r.diagnosa_postop)}</td>
            <td>${escapeHtml(r.operator1)}</td>
            <td>${escapeHtml(r.laporan_operasi)}</td>
        </tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted">Belum ada laporan operasi.</td></tr>';
    }

    document.getElementById('opPaketForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await fetch('/api/v1/operasi/paket-save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                kode_paket: document.getElementById('opPkKode').value.trim(),
                nm_perawatan: document.getElementById('opPkNama').value.trim(),
                kategori: document.getElementById('opPkKat').value.trim(),
                tarif_total: Number(document.getElementById('opPkTarif').value || 0),
            }),
        });
        document.getElementById('opPaketForm').reset();
        await loadPaket();
    });

    document.getElementById('opBookingForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await fetch('/api/v1/operasi/booking-save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                no_rawat: document.getElementById('opBkRawat').value.trim(),
                kode_paket: document.getElementById('opBkPaket').value,
                tanggal: document.getElementById('opBkTgl').value,
                jam_mulai: document.getElementById('opBkMulai').value,
                jam_selesai: document.getElementById('opBkSelesai').value,
                kd_dokter: document.getElementById('opBkDokter').value.trim(),
                kd_ruang_ok: document.getElementById('opBkRuang').value.trim(),
            }),
        });
        await loadBookings();
    });

    document.getElementById('opBookingBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-op-done');
        if (!btn) return;
        await fetch('/api/v1/operasi/booking-status', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ id: Number(btn.dataset.id), status: 'Selesai' }),
        });
        await loadBookings();
    });

    document.getElementById('opLaporanForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await fetch('/api/v1/operasi/laporan-save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                no_rawat: document.getElementById('opLpRawat').value.trim(),
                kode_paket: document.getElementById('opLpPaket').value,
                diagnosa_preop: document.getElementById('opLpPre').value.trim(),
                diagnosa_postop: document.getElementById('opLpPost').value.trim(),
                operator1: document.getElementById('opLpOp1').value.trim(),
                asisten_operator1: document.getElementById('opLpAs1').value.trim(),
                dokter_anestesi: document.getElementById('opLpAnestesi').value.trim(),
                laporan_operasi: document.getElementById('opLpIsi').value.trim(),
            }),
        });
        document.getElementById('opLaporanForm').reset();
        await loadLaporan();
    });

    loadPaket();
    loadBookings();
    loadLaporan();
})();
