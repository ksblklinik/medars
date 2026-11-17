(function (window, document) {
    'use strict';

    const InvApp = {
        init: function () {
            document.getElementById('btnInvSearch')?.addEventListener('click', () => this.load());
            document.getElementById('formInventaris')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.save();
            });
            this.load();
        },

        escapeHtml: function (str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        },

        apiFetch: function (url, options) {
            return (window.MedarsApp && typeof window.MedarsApp.apiFetch === 'function')
                ? window.MedarsApp.apiFetch(url, options)
                : fetch(url, options);
        },

        showAlert: function (type, msg) {
            const box = document.getElementById('invAlert');
            if (box) box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} py-2 small">${this.escapeHtml(msg)}</div>`;
        },

        load: function () {
            const q = document.getElementById('invSearchInput')?.value.trim() || '';
            this.apiFetch('/api/v1/inventaris/list?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('tbodyInventaris');
                    const rows = res.data || [];
                    if (!rows.length) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Belum ada data aset.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = '';
                    rows.forEach((r) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kode_aset)}</td><td>${this.escapeHtml(r.nama_aset)}</td><td>${this.escapeHtml(r.kategori)}</td><td>${this.escapeHtml(r.lokasi)}</td><td class="text-end">Rp ${this.escapeHtml(Number(r.nilai_perolehan || 0).toLocaleString('id-ID'))}</td><td>${this.escapeHtml(r.kondisi)}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                        tr.querySelector('.btn-del')?.addEventListener('click', () => this.del(r.kode_aset));
                        tbody.appendChild(tr);
                    });
                });
        },

        save: function () {
            const val = (id) => document.getElementById(id)?.value || '';
            this.apiFetch('/api/v1/inventaris/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    kode_aset: val('invKode'),
                    nama_aset: val('invNama'),
                    kategori: val('invKategori'),
                    lokasi: val('invLokasi'),
                    tgl_perolehan: val('invTgl'),
                    nilai_perolehan: val('invNilai'),
                    kondisi: val('invKondisi'),
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') this.load();
            });
        },

        del: function (kode) {
            this.apiFetch('/api/v1/inventaris/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ kode_aset: kode }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') this.load();
            });
        },
    };

    document.addEventListener('DOMContentLoaded', () => InvApp.init());
})(window, document);
