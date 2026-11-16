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

    async function loadOutbox() {
        const tbody = document.getElementById('waOutboxBody');
        if (!tbody) return;
        const data = await apiFetch('/api/v1/wagateway/outbox');
        if (!data.ok || !data.outbox.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Belum ada riwayat pengiriman.</td></tr>';
            return;
        }
        tbody.innerHTML = data.outbox.map((o) => `<tr>
            <td class="ps-2"><span class="badge ${o.session_name === 'system' ? 'bg-primary' : 'bg-info text-dark'}">${escapeHtml(o.session_name)}</span></td>
            <td>${escapeHtml(o.no_hp)}</td>
            <td class="small text-truncate" style="max-width: 200px;">${escapeHtml(o.pesan)}</td>
            <td><span class="badge ${o.status === 'Sent' ? 'bg-success' : 'bg-danger'}">${escapeHtml(o.status)}</span></td>
        </tr>`).join('');
    }

    document.getElementById('btnRefreshOutbox')?.addEventListener('click', loadOutbox);

    document.getElementById('formSendWa')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('waSendFeedback');
        const data = await apiFetch('/api/v1/wagateway/send', {
            method: 'POST',
            body: JSON.stringify({
                session: document.getElementById('waSession').value,
                phone: document.getElementById('waPhone').value.trim(),
                message: document.getElementById('waMessage').value.trim(),
            }),
        });
        if (fb) {
            fb.innerHTML = data.ok
                ? '<div class="alert alert-success py-2 small">Pesan terkirim ke WhatsApp.</div>'
                : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        }
        loadOutbox();
    });

    document.getElementById('formAddWhitelist')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fb = document.getElementById('wlFeedback');
        const data = await apiFetch('/api/v1/wagateway/whitelist', {
            method: 'POST',
            body: JSON.stringify({
                phone: document.getElementById('wlPhone').value.trim(),
                nama: document.getElementById('wlName').value.trim(),
            }),
        });
        if (fb) {
            fb.innerHTML = data.ok
                ? '<div class="alert alert-success py-2 small">Nomor berhasil didaftarkan ke Whitelist.</div>'
                : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        }
    });

    loadOutbox();
})();
