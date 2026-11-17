(function (window, document) {
    'use strict';

    const GudangApp = {
        init: function () {
            this.bindEvents();
            this.loadBarang();
            this.loadKartuStok();
        },

        escapeHtml: function (str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        },

        apiFetch: function (url, options) {
            if (window.MedarsApp && typeof window.MedarsApp.apiFetch === 'function') {
                return window.MedarsApp.apiFetch(url, options);
            }
            return fetch(url, options);
        },

        showAlert: function (type, msg) {
            const box = document.getElementById('gudangAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.getElementById('btnGudangSearch')?.addEventListener('click', () => this.loadBarang());
            document.getElementById('formSaveBarang')?.addEventListener('submit', (e) => {
                e.preventDefault();
                const val = (id) => document.getElementById(id)?.value || '';
                this.post('/api/v1/gudang/barang/save', {
                    kode_brng: val('brgKode'),
                    nama_brng: val('brgNama'),
                    kode_sat: val('brgSatuan'),
                    h_beli: val('brgHBeli'),
                    harga_tunai: val('brgHTunai'),
                    harga_kapitasi: val('brgHKap'),
                    jualbebas: val('brgJBebas'),
                });
            });
            document.getElementById('formReceiveStock')?.addEventListener('submit', (e) => {
                e.preventDefault();
                const val = (id) => document.getElementById(id)?.value || '';
                this.post('/api/v1/gudang/receive', {
                    kode_brng: val('recKode'),
                    kd_bangsal: val('recBangsal'),
                    qty: val('recQty'),
                    no_batch: val('recBatch'),
                    no_faktur: val('recFaktur'),
                    keterangan: val('recKet'),
                });
            });
            document.getElementById('formMutateStock')?.addEventListener('submit', (e) => {
                e.preventDefault();
                const val = (id) => document.getElementById(id)?.value || '';
                this.post('/api/v1/gudang/mutate', {
                    kode_brng: val('mutKode'),
                    from_bangsal: val('mutFrom'),
                    to_bangsal: val('mutTo'),
                    qty: val('mutQty'),
                    keterangan: val('mutKet'),
                });
            });
        },

        post: function (url, payload) {
            this.apiFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') {
                    this.loadBarang();
                    this.loadKartuStok();
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses permintaan gudang.'));
        },

        loadBarang: function () {
            const q = document.getElementById('gudangSearchInput')?.value.trim() || '';
            this.apiFetch('/api/v1/gudang/barang?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('tbodyMasterBarang');
                    const rows = res.data || [];
                    if (!tbody) return;
                    if (!rows.length) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada data obat.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = rows.map(b => `
                        <tr>
                            <td class="font-monospace">${this.escapeHtml(b.kode_brng)}</td>
                            <td>${this.escapeHtml(b.nama_brng)}</td>
                            <td>${this.escapeHtml(b.kode_sat)}</td>
                            <td class="text-end">Rp ${this.escapeHtml(Number(b.harga_tunai || 0).toLocaleString('id-ID'))}</td>
                            <td class="text-end fw-bold">${this.escapeHtml(b.total_stok)}</td>
                        </tr>
                    `).join('');
                })
                .catch(() => {});
        },

        loadKartuStok: function () {
            this.apiFetch('/api/v1/gudang/kartu-stok')
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('tbodyKartuStok');
                    const rows = res.data || [];
                    if (!tbody) return;
                    if (!rows.length) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">Belum ada mutasi stok.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = rows.map(k => `
                        <tr>
                            <td>${this.escapeHtml(k.tanggal)} ${this.escapeHtml(k.jam)}</td>
                            <td>${this.escapeHtml(k.nama_brng || k.kode_brng)}</td>
                            <td><span class="badge bg-secondary">${this.escapeHtml(k.kd_bangsal)}</span></td>
                            <td>${this.escapeHtml(k.posisi)}</td>
                            <td class="text-end">${this.escapeHtml(k.stok_awal)}</td>
                            <td class="text-end text-success">${this.escapeHtml(k.masuk)}</td>
                            <td class="text-end text-danger">${this.escapeHtml(k.keluar)}</td>
                            <td class="text-end fw-bold">${this.escapeHtml(k.stok_akhir)}</td>
                        </tr>
                    `).join('');
                })
                .catch(() => {});
        },
    };

    document.addEventListener('DOMContentLoaded', () => GudangApp.init());
})(window, document);
