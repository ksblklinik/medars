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

    async function loadBridgingStatus() {
        const data = await apiFetch('/api/v1/bridging/status');
        const badge = document.getElementById('bpjsCircuitBadge');
        if (badge && data.bpjs_circuit) {
            const open = Boolean(data.bpjs_circuit.circuit_open);
            badge.className = open ? 'alert alert-warning py-2 small mb-2' : 'alert alert-success py-2 small mb-2';
            badge.textContent = data.bpjs_circuit.metaData?.message || (open ? 'Circuit Breaker Open' : 'BPJS Terhubung');
        }

        const tbody = document.getElementById('bridgingQueueBody');
        if (tbody) {
            const rows = data.queue || [];
            tbody.innerHTML = rows.length
                ? rows.map((q) => `<tr>
                    <td>${escapeHtml(q.provider)}</td>
                    <td>${escapeHtml(q.no_rawat)}</td>
                    <td>${escapeHtml(q.resource_type)}</td>
                    <td><span class="badge ${q.status === 'Synced' ? 'bg-success' : 'bg-secondary'}">${escapeHtml(q.status)}</span></td>
                </tr>`).join('')
                : '<tr><td colspan="4" class="text-center text-muted py-2 small">Antrean kosong.</td></tr>';
        }
    }

    document.getElementById('btnRefreshBridging')?.addEventListener('click', loadBridgingStatus);

    document.getElementById('btnProcessQueue')?.addEventListener('click', async () => {
        await apiFetch('/api/v1/bridging/satusehat/process', { method: 'POST', body: '{}' });
        loadBridgingStatus();
    });

    document.getElementById('formLeanEstimate')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const shift = document.getElementById('leanShift').value;
        const kdPoli = document.getElementById('leanKdPoli').value.trim();
        const noReg = document.getElementById('leanNoReg').value;
        const dur = document.getElementById('leanDuration').value;
        const data = await apiFetch(`/api/v1/bridging/lean/estimate?shift=${encodeURIComponent(shift)}&kd_poli=${encodeURIComponent(kdPoli)}&no_reg=${encodeURIComponent(noReg)}&duration_minutes=${encodeURIComponent(dur)}`);
        const out = document.getElementById('leanEstimateOutput');
        if (data.ok && out) {
            const est = data.estimate;
            out.innerHTML = `Mulai Dokter: <strong>${escapeHtml(est.doctor_start_time)}</strong> | Estimasi Dilayani: <strong>${escapeHtml(est.estimated_service_time)}</strong> | Saran Tiba: <strong>${escapeHtml(est.suggested_arrival_time)}</strong>`;
        }
    });

    document.getElementById('formVedikaVerify')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('vedikaFeedback');
        const data = await apiFetch('/api/v1/bridging/vedika/verify', {
            method: 'POST',
            body: JSON.stringify({
                no_rawat: document.getElementById('vedikaNoRawat').value.trim(),
                no_sep: document.getElementById('vedikaNoSep').value.trim(),
            }),
        });
        if (fb) {
            fb.innerHTML = data.ok
                ? `<div class="alert alert-success py-2 small">Status: ${escapeHtml(data.verification.status)} — ${escapeHtml(data.verification.catatan)}</div>`
                : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        }
    });

    loadBridgingStatus();
})();
