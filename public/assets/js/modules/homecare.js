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

    async function loadSupervisorVisits() {
        const tbody = document.getElementById('supervisorVisitsBody');
        if (!tbody) return;
        const data = await apiFetch('/api/v1/homecare/supervisor/visits');
        if (!data.ok || !data.visits.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Belum ada data asuhan aktif.</td></tr>';
            return;
        }
        tbody.innerHTML = data.visits.map((v) => `<tr>
            <td class="ps-2 fw-semibold">${escapeHtml(v.no_rawat)}</td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(v.pseudonym_pasien)}</span></td>
            <td class="small">${escapeHtml(v.preceptor_nip || '-')} / ${escapeHtml(v.mahasiswa_nim || '-')}</td>
            <td><span class="badge bg-secondary">${escapeHtml(v.status_layanan)}</span></td>
        </tr>`).join('');
    }

    document.getElementById('btnRefreshSupervisor')?.addEventListener('click', loadSupervisorVisits);

    document.getElementById('formNewHomeCare')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('hcCreateFeedback');
        const data = await apiFetch('/api/v1/homecare/kunjungan', {
            method: 'POST',
            body: JSON.stringify({
                no_rawat: document.getElementById('hcNoRawat').value.trim(),
                tgl_kunjungan: document.getElementById('hcDate').value,
                jam_kunjungan: document.getElementById('hcTime').value,
                kd_dokter_dpjp: document.getElementById('hcDpjp').value.trim(),
                diagnosis: document.getElementById('hcDiag').value.trim(),
            }),
        });
        if (fb) {
            fb.innerHTML = data.ok
                ? '<div class="alert alert-success py-2 small">Jadwal home care tersimpan.</div>'
                : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        }
        loadSupervisorVisits();
    });

    document.getElementById('formAssignPreceptor')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('asFeedback');
        const data = await apiFetch('/api/v1/homecare/preceptor/assign', {
            method: 'POST',
            body: JSON.stringify({
                no_rawat: document.getElementById('asNoRawat').value.trim(),
                preceptor_nip: document.getElementById('asPreceptorNip').value.trim(),
                institusi: document.getElementById('asInstitusi').value.trim(),
                mahasiswa_nim: document.getElementById('asNim').value.trim(),
                supervisor_id: document.getElementById('asSupervisor').value.trim(),
            }),
        });
        if (fb) {
            fb.innerHTML = data.ok
                ? '<div class="alert alert-success py-2 small">Preceptor ber-SK berhasil ditugaskan.</div>'
                : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        }
        loadSupervisorVisits();
    });

    loadSupervisorVisits();
})();
