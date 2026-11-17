(() => {
    'use strict';

    const root = document.getElementById('smartAgentApp');
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

    async function loadVault() {
        const res = await fetch('/api/v1/smartagent/knowledge', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('saVaultBody').innerHTML = rows.map((r) => `<tr>
            <td class="fw-semibold">${escapeHtml(r.title)}</td>
            <td><span class="badge bg-info text-dark">${escapeHtml(r.category)}</span></td>
            <td><code>${escapeHtml(r.icd10_code || '-')}</code></td>
            <td>${escapeHtml(r.content)}</td>
            <td class="text-end"><button type="button" class="btn btn-xs btn-outline-danger js-sa-del" data-id="${Number(r.id)}">&times;</button></td>
        </tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted py-3">Vault masih kosong.</td></tr>';
    }

    async function loadProfiles() {
        const res = await fetch('/api/v1/smartagent/profiles', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('saProfilesBody').innerHTML = rows.map((r) => `<tr>
            <td class="fw-semibold">${escapeHtml(r.name)}</td>
            <td>${escapeHtml(r.provider)}</td>
            <td><code>${escapeHtml(r.model_name)}</code></td>
            <td>${escapeHtml(r.endpoint_url)}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada profil AI.</td></tr>';
    }

    document.getElementById('saRecommendForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await postJson('/api/v1/smartagent/recommend', { query: document.getElementById('saQueryInput').value.trim() });
        const json = await res.json();
        const d = json.data || {};
        const vaultHtml = (d.knowledge_matches || []).map((k) => `<li class="list-group-item"><strong>${escapeHtml(k.title)}</strong> (<code>${escapeHtml(k.icd10_code)}</code>): ${escapeHtml(k.content)}</li>`).join('');
        const icdHtml = (d.icd10_candidates || []).map((c) => `<span class="badge bg-success me-1">${escapeHtml(c.kd_penyakit || c.code)} - ${escapeHtml(c.nm_penyakit || c.display || '')}</span>`).join('');
        document.getElementById('saRecommendResult').innerHTML = `
            <div class="alert alert-light border mb-2"><strong>Ringkasan:</strong> ${escapeHtml(d.summary)}</div>
            <div class="mb-2">${icdHtml || '<span class="text-muted">Tidak ada kandidat ICD-10 tambahan.</span>'}</div>
            <ul class="list-group small">${vaultHtml}</ul>
        `;
    });

    document.getElementById('saVaultForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/smartagent/knowledge-save', {
            title: document.getElementById('saTitle').value.trim(),
            category: document.getElementById('saCat').value,
            icd10_code: document.getElementById('saIcd').value.trim(),
            keywords: document.getElementById('saKw').value.trim(),
            content: document.getElementById('saContent').value.trim(),
        });
        document.getElementById('saVaultForm').reset();
        await loadVault();
    });

    document.getElementById('saVaultBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-sa-del');
        if (!btn) return;
        await postJson('/api/v1/smartagent/knowledge-delete', { id: Number(btn.dataset.id) });
        await loadVault();
    });

    document.getElementById('saProfileForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/smartagent/profiles-save', {
            name: document.getElementById('saPrName').value.trim(),
            provider: document.getElementById('saPrProv').value,
            endpoint_url: document.getElementById('saPrUrl').value.trim(),
            model_name: document.getElementById('saPrModel').value.trim(),
        });
        document.getElementById('saProfileForm').reset();
        await loadProfiles();
    });

    loadVault();
    loadProfiles();
})();
