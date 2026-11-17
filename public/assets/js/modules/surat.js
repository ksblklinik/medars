(function (window, document) {
    'use strict';

    const SuratApp = {
        _activeType: 'sakit',

        init: function () {
            this.bindEvents();
            this.loadList('sakit');
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
            const box = document.getElementById('suratAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.querySelectorAll('#suratTabs button[data-type]').forEach((btn) => {
                btn.addEventListener('shown.bs.tab', () => {
                    this._activeType = btn.getAttribute('data-type') || 'sakit';
                    this.loadList(this._activeType);
                });
            });

            document.getElementById('btnSuratSearch')?.addEventListener('click', () => this.loadList(this._activeType));
            document.getElementById('suratSearchInput')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.loadList(this._activeType);
                }
            });

            document.getElementById('formSuratSakit')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createSurat({
                    type: 'sakit',
                    no_rawat: document.getElementById('sakitNoRawat').value,
                    tanggal_awal: document.getElementById('sakitAwal').value,
                    tanggal_akhir: document.getElementById('sakitAkhir').value,
                });
            });

            document.getElementById('formSuratHamil')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createSurat({
                    type: 'hamil',
                    no_rawat: document.getElementById('hamilNoRawat').value,
                    tanggal_periksa: document.getElementById('hamilTgl').value,
                    hasil_periksa: document.getElementById('hamilHasil').value,
                });
            });

            document.getElementById('formSuratNarkoba')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createSurat({
                    type: 'narkoba',
                    no_rawat: document.getElementById('nrkNoRawat').value,
                    tanggal: document.getElementById('nrkTgl').value,
                    kategori: document.getElementById('nrkKategori').value,
                    keperluan: document.getElementById('nrkKeperluan').value,
                });
            });
        },

        loadList: function (type) {
            const q = document.getElementById('suratSearchInput')?.value.trim() || '';
            this.apiFetch(`/api/v1/surat/list?type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(res => {
                    const rows = (res && res.data) ? res.data : [];
                    this.renderTable(type, rows);
                })
                .catch(() => this.showAlert('danger', 'Gagal memuat daftar surat medis.'));
        },

        createSurat: function (payload) {
            this.apiFetch('/api/v1/surat/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Proses selesai.');
                if (res.status === 'success') this.loadList(payload.type);
            })
            .catch(() => this.showAlert('danger', 'Gagal menerbitkan surat medis.'));
        },

        renderTable: function (type, rows) {
            const map = { sakit: 'tbodySuratSakit', hamil: 'tbodySuratHamil', narkoba: 'tbodySuratNarkoba' };
            const tbody = document.getElementById(map[type]);
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada surat diterbitkan.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((r) => {
                const tr = document.createElement('tr');
                let cols = '';
                if (type === 'sakit') {
                    cols = `<td class="font-monospace">${this.escapeHtml(r.no_surat)}</td><td>${this.escapeHtml(r.no_rawat)}</td><td>${this.escapeHtml(r.nm_pasien || '-')}</td><td>${this.escapeHtml(r.tanggalawal)} s/d ${this.escapeHtml(r.tanggalakhir)}</td><td>${this.escapeHtml(r.lamasakit)}</td>`;
                } else if (type === 'hamil') {
                    cols = `<td class="font-monospace">${this.escapeHtml(r.no_surat)}</td><td>${this.escapeHtml(r.no_rawat)}</td><td>${this.escapeHtml(r.nm_pasien || '-')}</td><td>${this.escapeHtml(r.tanggalperiksa)}</td><td>${this.escapeHtml(r.hasilperiksa)}</td>`;
                } else {
                    cols = `<td class="font-monospace">${this.escapeHtml(r.no_surat)}</td><td>${this.escapeHtml(r.no_rawat)}</td><td>${this.escapeHtml(r.nm_pasien || '-')}</td><td>${this.escapeHtml(r.tanggal)}</td><td>${this.escapeHtml(r.keperluan)}</td>`;
                }
                tr.innerHTML = `${cols}<td class="text-center"><button type="button" class="btn btn-outline-teal btn-sm py-0 px-2 btn-print">Cetak</button></td>`;
                tr.querySelector('.btn-print')?.addEventListener('click', () => {
                    window.open(`/surat/cetak?type=${encodeURIComponent(type)}&no_surat=${encodeURIComponent(r.no_surat)}`, '_blank');
                });
                tbody.appendChild(tr);
            });
        },
    };

    document.addEventListener('DOMContentLoaded', () => SuratApp.init());
})(window, document);
