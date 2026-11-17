(() => {
    'use strict';

    const root = document.getElementById('orthancApp');
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

    async function loadStudies() {
        const q = encodeURIComponent(document.getElementById('orSearch').value.trim());
        const res = await fetch('/api/v1/orthanc/studies?q=' + q, { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('orBody').innerHTML = rows.map((r) => `<tr>
            <td>${escapeHtml(r.tgl_periksa)}</td>
            <td><code>${escapeHtml(r.no_rawat)}</code></td>
            <td><span class="badge bg-info text-dark">${escapeHtml(r.modality)}</span></td>
            <td>${escapeHtml(r.study_description)}</td>
            <td><code>${escapeHtml(r.study_uid)}</code></td>
            <td>${escapeHtml(r.hasil_baca || '-')}</td>
            <td class="text-end text-nowrap">
                ${r.viewer_url ? `<a href="${escapeHtml(r.viewer_url)}" target="_blank" class="btn btn-xs btn-outline-primary me-1">Viewer</a>` : ''}
                <button type="button" class="btn btn-xs btn-outline-success me-1 js-or-baca" data-id="${Number(r.id)}">Isi Baca</button>
                <button type="button" class="btn btn-xs btn-outline-danger js-or-del" data-id="${Number(r.id)}">&times;</button>
            </td>
        </tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada study PACS tertaut.</td></tr>';
    }

    document.getElementById('orStudyForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await fetch('/api/v1/orthanc/studies-save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                no_rawat: document.getElementById('orRawat').value.trim(),
                study_uid: document.getElementById('orUid').value.trim(),
                modality: document.getElementById('orMod').value,
                study_description: document.getElementById('orDesc').value.trim(),
                viewer_url: document.getElementById('orViewer').value.trim(),
                hasil_baca: document.getElementById('orHasil').value.trim(),
            }),
        });
        document.getElementById('orStudyForm').reset();
        await loadStudies();
    });

    document.getElementById('orBody').addEventListener('click', async (e) => {
        const bacaBtn = e.target.closest('.js-or-baca');
        if (bacaBtn) {
            const hasil = prompt('Masukkan hasil interpretasi radiologi:');
            if (!hasil) return;
            await fetch('/api/v1/orthanc/studies-interpretasi', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: Number(bacaBtn.dataset.id), hasil_baca: hasil }),
            });
            await loadStudies();
            return;
        }
        const delBtn = e.target.closest('.js-or-del');
        if (delBtn && confirm('Hapus study ini?')) {
            await fetch('/api/v1/orthanc/studies-delete', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: Number(delBtn.dataset.id) }),
            });
            await loadStudies();
        }
    });

    document.getElementById('orSearchBtn').addEventListener('click', loadStudies);
    loadStudies();
})();
