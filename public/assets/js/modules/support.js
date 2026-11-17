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

    function getPeriodQuery() {
        const from = document.getElementById('supportFilterFrom')?.value || '';
        const to = document.getElementById('supportFilterTo')?.value || '';
        const params = new URLSearchParams();
        if (from) params.set('from', from);
        if (to) params.set('to', to);
        const qs = params.toString();
        return qs ? '?' + qs : '';
    }

    async function loadLabOrders() {
        const tbody = document.getElementById('labOrdersBody');
        if (!tbody) return;
        const data = await apiFetch('/api/v1/penunjang/lab/orders' + getPeriodQuery());
        if (!data.ok || !data.orders.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Belum ada order lab hari ini.</td></tr>';
            return;
        }
        tbody.innerHTML = data.orders.map((o) => {
            const lockBadge = o.validation_unlocked
                ? '<span class="badge bg-success">Terbuka</span>'
                : '<span class="badge bg-warning text-dark">Terkunci Kasir</span>';
            return `<tr class="cursor-pointer lab-order-row" data-noorder="${escapeHtml(o.noorder)}" data-unlocked="${o.validation_unlocked ? '1' : '0'}">
                <td class="ps-2 fw-semibold">${escapeHtml(o.noorder)}</td>
                <td>${escapeHtml(o.nm_pasien || o.no_rawat)}</td>
                <td><span class="badge bg-secondary">${escapeHtml(o.jenis_klaim)}</span></td>
                <td>${lockBadge}</td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('.lab-order-row').forEach((row) => {
            row.addEventListener('click', () => {
                document.getElementById('labNoOrder').value = row.dataset.noorder;
                const badgeEl = document.getElementById('labLockBadge');
                badgeEl.textContent = row.dataset.unlocked === '1'
                    ? 'Terbuka (Siap Validasi Hasil)'
                    : 'Terkunci — Menunggu Pelunasan / Persetujuan Kasir';
            });
        });
    }

    async function loadRadOrders() {
        const tbody = document.getElementById('radOrdersBody');
        if (!tbody) return;
        const data = await apiFetch('/api/v1/penunjang/radiologi/orders' + getPeriodQuery());
        if (!data.ok || !data.orders.length) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3 small">Belum ada order radiologi hari ini.</td></tr>';
            return;
        }
        tbody.innerHTML = data.orders.map((o) => `
            <tr class="cursor-pointer rad-order-row" data-noorder="${escapeHtml(o.noorder)}">
                <td class="ps-2 fw-semibold">${escapeHtml(o.noorder)}</td>
                <td>${escapeHtml(o.nm_pasien || o.no_rawat)}</td>
                <td><span class="badge bg-secondary">${escapeHtml(o.jenis_klaim)}</span></td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.rad-order-row').forEach((row) => {
            row.addEventListener('click', () => {
                document.getElementById('radNoOrder').value = row.dataset.noorder;
            });
        });
    }

    async function loadOperationalReports() {
        const from = document.getElementById('repFrom')?.value || '';
        const to = document.getElementById('repTo')?.value || '';
        const data = await apiFetch(`/api/v1/laporan/operasional?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
        if (!data.ok) return;

        const visitsBody = document.getElementById('repVisitsBody');
        visitsBody.innerHTML = data.visits_by_poli.length
            ? data.visits_by_poli.map((v) => `<tr><td class="ps-2">${escapeHtml(v.nm_poli)}</td><td class="text-end pe-2 fw-bold">${escapeHtml(v.total_kunjungan)}</td></tr>`).join('')
            : '<tr><td class="text-center text-muted p-2 small">Tidak ada data</td></tr>';

        const icdBody = document.getElementById('repIcdBody');
        icdBody.innerHTML = data.top_diseases.length
            ? data.top_diseases.map((d) => `<tr><td class="ps-2"><code>${escapeHtml(d.kd_penyakit)}</code> ${escapeHtml(d.nm_indonesia)}</td><td class="text-end pe-2 fw-bold">${escapeHtml(d.total)}</td></tr>`).join('')
            : '<tr><td class="text-center text-muted p-2 small">Tidak ada data</td></tr>';

        const revBody = document.getElementById('repRevenueBody');
        revBody.innerHTML = data.revenue_by_claim.length
            ? data.revenue_by_claim.map((r) => `<tr><td class="ps-2">${escapeHtml(r.claim_type)} (${escapeHtml(r.total_items)})</td><td class="text-end pe-2 fw-bold">Rp ${Number(r.total_amount).toLocaleString('id-ID')}</td></tr>`).join('')
            : '<tr><td class="text-center text-muted p-2 small">Tidak ada data</td></tr>';

        const recBody = document.getElementById('repReconcileBody');
        if (recBody) {
            const recs = Array.isArray(data.reconciliation) ? data.reconciliation : [];
            recBody.innerHTML = recs.length
                ? recs.map((o) => `<tr><td class="font-monospace">${escapeHtml(o.no_rawat)}</td><td>${escapeHtml(o.nm_pasien)}</td><td>${escapeHtml(o.item_name)}</td><td><span class="badge bg-secondary">${escapeHtml(o.status)}</span></td><td class="text-end">Rp ${Number(o.total_amount).toLocaleString('id-ID')}</td></tr>`).join('')
                : '<tr><td colspan="5" class="text-center text-muted p-2 small">Tidak ada order pending/cancelled/void.</td></tr>';
        }
    }

    document.getElementById('btnExportLaporanCsv')?.addEventListener('click', () => {
        const from = document.getElementById('repFrom')?.value || '';
        const to = document.getElementById('repTo')?.value || '';
        window.open(`/api/v1/laporan/export-csv?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, '_blank');
    });

    document.getElementById('btnRefreshLab')?.addEventListener('click', loadLabOrders);
    document.getElementById('btnRefreshRad')?.addEventListener('click', loadRadOrders);
    document.getElementById('btnFilterSupport')?.addEventListener('click', () => {
        loadLabOrders();
        loadRadOrders();
    });
    ['supportFilterFrom', 'supportFilterTo'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', () => {
            loadLabOrders();
            loadRadOrders();
        });
    });
    document.getElementById('btnPrintLabResult')?.addEventListener('click', () => {
        const noOrder = document.getElementById('labNoOrder')?.value.trim() || '';
        if (!noOrder) return;
        window.open('/penunjang/lab/cetak?noorder=' + encodeURIComponent(noOrder), '_blank');
    });
    document.getElementById('btnPrintRadResult')?.addEventListener('click', () => {
        const noOrder = document.getElementById('radNoOrder')?.value.trim() || '';
        if (!noOrder) return;
        window.open('/penunjang/radiologi/cetak?noorder=' + encodeURIComponent(noOrder), '_blank');
    });
    document.getElementById('btnDeleteRadOrder')?.addEventListener('click', async () => {
        const noOrder = document.getElementById('radNoOrder')?.value.trim() || '';
        if (!noOrder) return;
        const data = await apiFetch('/api/v1/penunjang/radiologi/orders/delete', {
            method: 'POST',
            body: JSON.stringify({ noorder: noOrder }),
        });
        const fb = document.getElementById('radResultFeedback');
        fb.innerHTML = data.ok
            ? `<div class="alert alert-info py-2 small">Order radiologi ${escapeHtml(noOrder)} dihapus.</div>`
            : `<div class="alert alert-danger py-2 small">${escapeHtml(data.message || data.error)}</div>`;
        loadRadOrders();
    });
    document.getElementById('btnLoadReports')?.addEventListener('click', loadOperationalReports);

    document.getElementById('formLabResult')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('labResultFeedback');
        const payload = {
            no_order: document.getElementById('labNoOrder').value.trim(),
            results: [{
                kd_jenis_prw: document.getElementById('labKdPrw').value.trim(),
                pemeriksaan: document.getElementById('labParam').value.trim(),
                nilai: document.getElementById('labNilai').value.trim(),
                nilai_rujukan: document.getElementById('labRujukan').value.trim(),
            }],
        };
        const data = await apiFetch('/api/v1/penunjang/lab/results', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        fb.innerHTML = data.ok
            ? `<div class="alert alert-success py-2 small">Hasil tersimpan (${escapeHtml(data.results[0].keterangan)}).</div>`
            : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        loadLabOrders();
    });

    document.getElementById('btnDeleteLabOrder')?.addEventListener('click', async () => {
        const noOrder = document.getElementById('labNoOrder').value.trim();
        if (!noOrder) return;
        const data = await apiFetch('/api/v1/penunjang/lab/delete', {
            method: 'POST',
            body: JSON.stringify({ no_order: noOrder }),
        });
        const fb = document.getElementById('labResultFeedback');
        fb.innerHTML = data.ok
            ? `<div class="alert alert-info py-2 small">Order ${escapeHtml(noOrder)} dihapus.</div>`
            : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        loadLabOrders();
    });

    document.getElementById('formRadResult')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('radResultFeedback');
        const data = await apiFetch('/api/v1/penunjang/radiologi/results', {
            method: 'POST',
            body: JSON.stringify({
                no_order: document.getElementById('radNoOrder').value.trim(),
                hasil: document.getElementById('radExpertise').value.trim(),
            }),
        });
        fb.innerHTML = data.ok
            ? '<div class="alert alert-success py-2 small">Expertise radiologi tersimpan.</div>'
            : `<div class="alert alert-danger py-2 small">${data.error}</div>`;
        loadRadOrders();
    });

    loadLabOrders();
    loadRadOrders();
    loadOperationalReports();
})();
