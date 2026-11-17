(() => {
    'use strict';

    const root = document.getElementById('chatApp');
    if (!root) return;

    const csrfToken = root.dataset.csrf || '';
    let activeConvId = 0;

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

    async function loadConversations() {
        const res = await fetch('/api/v1/chat/conversations', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('chatConvList').innerHTML = rows.map((c) => `
            <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center js-open-conv ${Number(c.id) === activeConvId ? 'active' : ''}"
                data-id="${Number(c.id)}" data-title="${escapeHtml(c.title)}">
                <span>${escapeHtml(c.title)}</span>
                <span class="badge bg-secondary rounded-pill">${Number(c.total_messages || 0)}</span>
            </button>
        `).join('') || '<div class="p-3 text-muted">Belum ada percakapan.</div>';
    }

    async function loadMessages(cid, title) {
        activeConvId = cid;
        if (title) document.getElementById('chatActiveTitle').textContent = title;
        const res = await fetch('/api/v1/chat/messages?conversation_id=' + Number(cid), { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        const box = document.getElementById('chatMessagesBox');
        box.innerHTML = rows.map((m) => `
            <div class="bg-white border rounded p-2 mb-2 shadow-xs">
                <div class="d-flex justify-content-between small">
                    <strong class="text-teal">${escapeHtml(m.sender_name || m.sender_username)}</strong>
                    <span class="text-muted">${escapeHtml(m.created_at)}</span>
                </div>
                <div class="small mt-1">${escapeHtml(m.message)}</div>
            </div>
        `).join('') || '<div class="text-center text-muted small py-5">Belum ada pesan di kanal ini.</div>';
        box.scrollTop = box.scrollHeight;
    }

    async function loadQuickResponses() {
        const res = await fetch('/api/v1/chat/quick-responses', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        document.getElementById('chatQrList').innerHTML = rows.map((q) => `
            <button type="button" class="btn btn-xs btn-outline-secondary js-use-qr" data-content="${escapeHtml(q.content)}">
                ${escapeHtml(q.shortcut)}
            </button>
        `).join('');
    }

    document.getElementById('chatConvForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await postJson('/api/v1/chat/conversations-create', {
            title: document.getElementById('chatConvTitle').value.trim(),
            type: 'group',
        });
        const json = await res.json();
        document.getElementById('chatConvForm').reset();
        await loadConversations();
        if (json.data && json.data.id) {
            await loadMessages(json.data.id, json.data.title);
        }
    });

    document.getElementById('chatConvList').addEventListener('click', (e) => {
        const btn = e.target.closest('.js-open-conv');
        if (btn) loadMessages(Number(btn.dataset.id), btn.dataset.title);
    });

    document.getElementById('chatSendForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!activeConvId) return alert('Pilih percakapan terlebih dahulu.');
        await postJson('/api/v1/chat/send', {
            conversation_id: activeConvId,
            message: document.getElementById('chatMsgInput').value.trim(),
        });
        document.getElementById('chatMsgInput').value = '';
        await Promise.all([loadMessages(activeConvId), loadConversations()]);
    });

    document.getElementById('chatQrForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await postJson('/api/v1/chat/quick-responses-save', {
            shortcut: document.getElementById('chatQrShortcut').value.trim(),
            content: document.getElementById('chatQrContent').value.trim(),
        });
        document.getElementById('chatQrForm').reset();
        await loadQuickResponses();
    });

    document.getElementById('chatQrList').addEventListener('click', (e) => {
        const btn = e.target.closest('.js-use-qr');
        if (btn) {
            document.getElementById('chatMsgInput').value = btn.dataset.content || '';
            document.getElementById('chatMsgInput').focus();
        }
    });

    loadConversations();
    loadQuickResponses();
})();
