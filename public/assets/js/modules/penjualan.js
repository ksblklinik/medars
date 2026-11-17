(() => {
    'use strict';

    const root = document.getElementById('penjualanApp');
    if (!root) return;

    const csrfToken = root.dataset.csrf || '';
    let activeOrderId = 0;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function loadBarang() {
        const res = await fetch('/api/v1/penjualan/barang', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];

        const tbody = document.getElementById('pjBarangBody');
        const sel = document.getElementById('pjItemSelect');
        tbody.innerHTML = rows.map((r) => `<tr>
            <td class="fw-semibold">${escapeHtml(r.nama_barang)}</td>
            <td>${escapeHtml(r.kode_satuan)}</td>
            <td class="text-end">${Number(r.stok)}</td>
            <td class="text-end">Rp ${Number(r.harga).toLocaleString('id-ID')}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="text-center text-muted">Belum ada barang.</td></tr>';

        sel.innerHTML = rows.map((r) => `<option value="${Number(r.id)}">${escapeHtml(r.nama_barang)} (Stok: ${Number(r.stok)} | Rp ${Number(r.harga).toLocaleString('id-ID')})</option>`).join('');
    }

    async function loadOrders() {
        const res = await fetch('/api/v1/penjualan/orders', { credentials: 'same-origin' });
        const json = await res.json();
        const rows = Array.isArray(json.data) ? json.data : [];
        const tbody = document.getElementById('pjOrdersBody');
        tbody.innerHTML = rows.map((r) => `<tr>
            <td>#${Number(r.id)}</td>
            <td>${escapeHtml(r.nama_pembeli)}</td>
            <td>Rp ${Number(r.subtotal || 0).toLocaleString('id-ID')}</td>
            <td><span class="badge ${r.status === 'Lunas' ? 'bg-success' : 'bg-warning text-dark'}">${escapeHtml(r.status)}</span></td>
            <td class="text-end"><button type="button" class="btn btn-xs btn-outline-primary js-open-order" data-id="${Number(r.id)}">Buka</button></td>
        </tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted">Belum ada order hari ini.</td></tr>';
    }

    async function loadOrderDetail(id) {
        activeOrderId = id;
        const res = await fetch('/api/v1/penjualan/order-detail?id=' + Number(id), { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.data) return;
        const h = json.data.header;
        const items = json.data.items || [];

        document.getElementById('pjActiveOrderTitle').textContent = `Order #${Number(h.id)} - ${h.nama_pembeli} (${h.status})`;
        const printLink = document.getElementById('pjPrintLink');
        printLink.href = '/penjualan/faktur?id=' + Number(h.id);
        printLink.classList.remove('d-none');

        const cartBody = document.getElementById('pjCartBody');
        cartBody.innerHTML = items.map((it) => `<tr>
            <td>${escapeHtml(it.nama_barang)}</td>
            <td class="text-end">${Number(it.jumlah)}</td>
            <td class="text-end">Rp ${Number(it.harga).toLocaleString('id-ID')}</td>
            <td class="text-end">Rp ${Number(it.harga_total).toLocaleString('id-ID')}</td>
            <td class="text-end"><button type="button" class="btn btn-xs btn-outline-danger js-del-item" data-id="${Number(it.id)}">&times;</button></td>
        </tr>`).join('') || '<tr><td colspan="5" class="text-center text-muted">Belum ada item.</td></tr>';
    }

    async function loadReport() {
        const from = encodeURIComponent(document.getElementById('pjRepFrom').value);
        const to = encodeURIComponent(document.getElementById('pjRepTo').value);
        const res = await fetch(`/api/v1/penjualan/report?from=${from}&to=${to}`, { credentials: 'same-origin' });
        const json = await res.json();
        const d = json.data || {};
        document.getElementById('pjRepCount').textContent = String(d.total_transaksi || 0);
        document.getElementById('pjRepOmzet').textContent = 'Rp ' + Number(d.total_omzet || 0).toLocaleString('id-ID');
        const top = Array.isArray(d.top_items) ? d.top_items : [];
        document.getElementById('pjRepTopBody').innerHTML = top.map((it) => `<tr>
            <td>${escapeHtml(it.nama_barang)}</td>
            <td class="text-end">${Number(it.qty_terjual)}</td>
            <td class="text-end">Rp ${Number(it.omzet).toLocaleString('id-ID')}</td>
        </tr>`).join('') || '<tr><td colspan="3" class="text-center text-muted">Belum ada penjualan.</td></tr>';
    }

    document.getElementById('pjCreateOrderForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const res = await fetch('/api/v1/penjualan/order-create', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                nama_pembeli: document.getElementById('pjNamaPembeli').value.trim() || 'Umum',
                nomor_telepon: document.getElementById('pjTelp').value.trim(),
            }),
        });
        const json = await res.json();
        if (json.data && json.data.id) {
            await loadOrders();
            await loadOrderDetail(json.data.id);
        }
    });

    document.getElementById('pjAddItemForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!activeOrderId) return alert('Buat atau pilih order terlebih dahulu.');
        const res = await fetch('/api/v1/penjualan/item-add', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                id_penjualan: activeOrderId,
                id_barang: Number(document.getElementById('pjItemSelect').value),
                jumlah: Number(document.getElementById('pjItemQty').value || 1),
            }),
        });
        const json = await res.json();
        if (json.status !== 'success') return alert(json.message || 'Gagal menambah item.');
        await Promise.all([loadBarang(), loadOrders(), loadOrderDetail(activeOrderId)]);
    });

    document.getElementById('pjCartBody').addEventListener('click', async (e) => {
        const btn = e.target.closest('.js-del-item');
        if (!btn) return;
        await fetch('/api/v1/penjualan/item-remove', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ detail_id: Number(btn.dataset.id) }),
        });
        await Promise.all([loadBarang(), loadOrders(), loadOrderDetail(activeOrderId)]);
    });

    document.getElementById('pjOrdersBody').addEventListener('click', (e) => {
        const btn = e.target.closest('.js-open-order');
        if (btn) loadOrderDetail(Number(btn.dataset.id));
    });

    document.getElementById('pjSettleForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!activeOrderId) return alert('Pilih order terlebih dahulu.');
        await fetch('/api/v1/penjualan/settle', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                id_penjualan: activeOrderId,
                potongan: Number(document.getElementById('pjPotongan').value || 0),
                cara_bayar: document.getElementById('pjCaraBayar').value,
                jumlah_bayar: Number(document.getElementById('pjJumlahBayar').value || 0),
            }),
        });
        await Promise.all([loadOrders(), loadOrderDetail(activeOrderId), loadReport()]);
    });

    document.getElementById('pjBarangForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await fetch('/api/v1/penjualan/barang-save', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({
                id: Number(document.getElementById('pjbId').value || 0),
                nama_barang: document.getElementById('pjbNama').value.trim(),
                kode_satuan: document.getElementById('pjbSatuan').value.trim(),
                stok: Number(document.getElementById('pjbStok').value || 0),
                harga: Number(document.getElementById('pjbHarga').value || 0),
            }),
        });
        document.getElementById('pjBarangForm').reset();
        await loadBarang();
    });

    document.getElementById('pjRepBtn').addEventListener('click', loadReport);

    loadBarang();
    loadOrders();
    loadReport();
})();
