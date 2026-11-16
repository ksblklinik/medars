(() => {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function escapeHtml(val) {
        return String(val ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function apiFetch(url, options = {}) {
        const headers = Object.assign(
            { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            options.headers || {}
        );
        const res = await fetch(url, Object.assign({}, options, { headers }));
        return res.json();
    }

    document.getElementById('btnScanOrphans')?.addEventListener('click', async () => {
        const badge = document.getElementById('orphanBadge');
        const tbody = document.getElementById('orphanTableBody');
        badge.className = 'alert alert-info py-2 small mb-2';
        badge.textContent = 'Memindai tabel transaksi...';

        const data = await apiFetch('/api/v1/audit/integrity/orphans');
        if (!data.ok) return;

        const audit = data.audit;
        if (audit.healthy) {
            badge.className = 'alert alert-success py-2 small mb-2';
            badge.textContent = 'Integritas Terverifikasi: 0 orphan record ditemukan di seluruh tabel.';
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-success py-2 small fw-bold">Semua relasi bersih (100% Valid)</td></tr>';
        } else {
            badge.className = 'alert alert-warning py-2 small mb-2';
            badge.textContent = `Peringatan: Ditemukan ${audit.total_orphans} baris orphan tanpa induk.`;
            tbody.innerHTML = audit.findings.map((f) => `<tr>
                <td class="ps-2 fw-semibold"><code>${escapeHtml(f.table)}</code></td>
                <td><code>${escapeHtml(f.column)}</code></td>
                <td class="text-danger fw-bold">${escapeHtml(f.orphan_count)}</td>
            </tr>`).join('');
        }
    });

    document.getElementById('formAuditBilling')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const nr = document.getElementById('ablNoRawat').value.trim();
        const out = document.getElementById('billingAuditResult');
        const data = await apiFetch(`/api/v1/audit/integrity/billing-balance?no_rawat=${encodeURIComponent(nr)}`);
        if (data.ok && out) {
            const b = data.balance;
            const statusBadge = b.balanced
                ? '<span class="badge bg-success">Seimbang (Valid)</span>'
                : '<span class="badge bg-danger">Discrepancy (Selisih)</span>';
            out.innerHTML = `${statusBadge} Total: <strong>Rp ${Number(b.total_amount).toLocaleString('id-ID')}</strong> (Tunai: Rp ${Number(b.tunai_amount).toLocaleString('id-ID')}, BPJS: Rp ${Number(b.bpjs_amount).toLocaleString('id-ID')})`;
        }
    });

    document.getElementById('formAuditStock')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const kb = document.getElementById('astKodeBrng').value.trim();
        const bg = document.getElementById('astKdBangsal').value.trim();
        const out = document.getElementById('stockAuditResult');
        const data = await apiFetch(`/api/v1/audit/integrity/stock-balance?kode_brng=${encodeURIComponent(kb)}&kd_bangsal=${encodeURIComponent(bg)}`);
        if (data.ok && out) {
            const s = data.stock;
            const statusBadge = s.balanced
                ? '<span class="badge bg-success">Stok Konsisten</span>'
                : '<span class="badge bg-danger">Selisih Stok Fisik vs Mutasi</span>';
            out.innerHTML = `${statusBadge} Stok Gudang: <strong>${escapeHtml(s.current_stock)}</strong> | Rekap Mutasi: <strong>${escapeHtml(s.calculated_stock)}</strong>`;
        }
    });

    document.getElementById('formRotateLogs')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const days = document.getElementById('rotDays').value;
        const out = document.getElementById('rotateLogsResult');
        const data = await apiFetch('/api/v1/audit/logs/rotate', {
            method: 'POST',
            body: JSON.stringify({ retention_days: Number(days) }),
        });
        if (data.ok && out) {
            out.innerHTML = `<div class="alert alert-success py-1 px-2 small mb-0">Berhasil memangkas ${escapeHtml(data.pruned_count)} baris log (> ${escapeHtml(data.retention_days)} hari).</div>`;
        }
    });
})();
