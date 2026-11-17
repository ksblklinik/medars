(() => {
    'use strict';

    const root = document.getElementById('apiKeysApp');
    if (!root) return;

    const csrfToken = root.dataset.csrf || '';
    const tbody = document.getElementById('apiKeysTableBody');
    const searchInput = document.getElementById('akSearchInput');
    const searchBtn = document.getElementById('akSearchBtn');
    const createForm = document.getElementById('apiKeyCreateForm');
    const oneTimeAlert = document.getElementById('apiKeyOneTimeAlert');
    const rawValueEl = document.getElementById('apiKeyRawValue');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function loadKeys() {
        const q = encodeURIComponent(searchInput.value.trim());
        const res = await fetch('/api/v1/api-keys?q=' + q, { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada API Key terdaftar.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row) => {
            const badge = row.is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>';
            const toggleLabel = row.is_active ? 'Nonaktifkan' : 'Aktifkan';
            const nextState = row.is_active ? '0' : '1';
            return `<tr>
                <td class="fw-semibold">${escapeHtml(row.label)}</td>
                <td>${escapeHtml(row.username || '-')}</td>
                <td><code>${escapeHtml(row.key_prefix)}</code></td>
                <td>${escapeHtml(row.methods)}</td>
                <td>${escapeHtml(row.ip_range)}</td>
                <td>${escapeHtml(row.exp_time || 'Tidak ada')}</td>
                <td>${badge}</td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-xs btn-outline-primary me-1 js-toggle-key"
                        data-id="${Number(row.id)}" data-next="${nextState}">${escapeHtml(toggleLabel)}</button>
                    <button type="button" class="btn btn-xs btn-outline-danger js-delete-key"
                        data-id="${Number(row.id)}">Hapus</button>
                </td>
            </tr>`;
        }).join('');
    }

    createForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const expRaw = document.getElementById('akExpTime').value.trim();
        const payload = {
            label: document.getElementById('akLabel').value.trim(),
            username: document.getElementById('akUsername').value.trim(),
            methods: document.getElementById('akMethods').value.trim() || 'GET',
            ip_range: document.getElementById('akIpRange').value.trim() || '*',
            exp_time: expRaw ? expRaw.replace('T', ' ') + ':00' : '',
        };
        const res = await fetch('/api/v1/api-keys/create', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (json.status === 'success' && json.data && json.data.raw_key) {
            rawValueEl.textContent = json.data.raw_key;
            oneTimeAlert.classList.remove('d-none');
            createForm.reset();
            document.getElementById('akMethods').value = 'GET,POST';
            document.getElementById('akIpRange').value = '*';
            await loadKeys();
        } else {
            alert(json.message || 'Gagal membuat API Key.');
        }
    });

    tbody.addEventListener('click', async (e) => {
        const toggleBtn = e.target.closest('.js-toggle-key');
        if (toggleBtn) {
            await fetch('/api/v1/api-keys/toggle', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({
                    id: Number(toggleBtn.dataset.id),
                    is_active: toggleBtn.dataset.next === '1',
                }),
            });
            await loadKeys();
            return;
        }

        const delBtn = e.target.closest('.js-delete-key');
        if (delBtn) {
            if (!confirm('Hapus API Key ini secara permanen?')) return;
            await fetch('/api/v1/api-keys/delete', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                body: JSON.stringify({ id: Number(delBtn.dataset.id) }),
            });
            await loadKeys();
        }
    });

    searchBtn.addEventListener('click', loadKeys);
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadKeys();
        }
    });

    loadKeys();
})();
