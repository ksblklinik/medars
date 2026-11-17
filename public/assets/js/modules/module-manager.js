(() => {
    'use strict';

    const root = document.getElementById('moduleManagerApp');
    if (!root) return;

    const csrfToken = root.dataset.csrf || '';
    const tbody = document.getElementById('modulesTableBody');
    const searchInput = document.getElementById('modSearchInput');
    const searchBtn = document.getElementById('modSearchBtn');
    const filterGroup = document.getElementById('modFilterGroup');
    let currentFilter = 'all';

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function loadModules() {
        const q = encodeURIComponent(searchInput.value.trim());
        const res = await fetch('/api/v1/modules?filter=' + encodeURIComponent(currentFilter) + '&q=' + q, {
            credentials: 'same-origin',
        });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];

        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada modul yang cocok.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row) => {
            const statusBadge = row.is_active
                ? '<span class="badge bg-success">Aktif</span>'
                : '<span class="badge bg-secondary">Nonaktif</span>';
            const coreBadge = row.is_core
                ? ' <span class="badge bg-info text-dark">Inti</span>'
                : '';
            const actionBtn = row.is_core
                ? '<span class="text-muted small">Terkunci</span>'
                : `<button type="button" class="btn btn-xs ${row.is_active ? 'btn-outline-danger' : 'btn-outline-success'} js-toggle-module"
                        data-dir="${escapeHtml(row.dir)}" data-next="${row.is_active ? '0' : '1'}">
                        ${row.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                   </button>`;

            return `<tr>
                <td>
                    <input type="number" min="0" class="form-control form-control-sm js-seq-input"
                        style="width:70px;" value="${Number(row.sequence)}" data-dir="${escapeHtml(row.dir)}">
                </td>
                <td class="fw-semibold">${escapeHtml(row.name)}${coreBadge}</td>
                <td><code>${escapeHtml(row.dir)}</code></td>
                <td>${escapeHtml(row.category)}</td>
                <td class="text-muted">${escapeHtml(row.description)}</td>
                <td>${statusBadge}</td>
                <td class="text-end">${actionBtn}</td>
            </tr>`;
        }).join('');
    }

    filterGroup.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-filter]');
        if (!btn) return;
        currentFilter = btn.dataset.filter || 'all';
        filterGroup.querySelectorAll('button').forEach((b) => b.classList.remove('active'));
        btn.classList.add('active');
        loadModules();
    });

    tbody.addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-toggle-module');
        if (!btn) return;
        const res = await fetch('/api/v1/modules/toggle', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({
                dir: btn.dataset.dir,
                is_active: btn.dataset.next === '1',
            }),
        });
        const json = await res.json();
        if (json.status !== 'success') {
            alert(json.message || 'Gagal memperbarui status modul.');
            return;
        }
        await loadModules();
    });

    tbody.addEventListener('change', async (e) => {
        const input = e.target.closest('.js-seq-input');
        if (!input) return;
        await fetch('/api/v1/modules/sequence', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify({
                dir: input.dataset.dir,
                sequence: Number(input.value || 0),
            }),
        });
        await loadModules();
    });

    searchBtn.addEventListener('click', loadModules);
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadModules();
        }
    });

    loadModules();
})();
