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

            document.getElementById('formSuratSehat')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createSurat({
                    type: 'sehat',
                    no_rawat: document.getElementById('sehatNoRawat').value,
                    tanggalsurat: document.getElementById('sehatTgl').value,
                    berat: document.getElementById('sehatBb').value,
                    tinggi: document.getElementById('sehatTb').value,
                    tensi: document.getElementById('sehatTd').value,
                    suhu: document.getElementById('sehatSuhu').value,
                    butawarna: document.getElementById('sehatBw').value,
                    kesimpulan: document.getElementById('sehatKesimpulan').value,
                    keperluan: document.getElementById('sehatKeperluan').value,
                });
            });

            document.getElementById('formSuratCutiHamil')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createSurat({
                    type: 'cuti_hamil',
                    no_rawat: document.getElementById('cutiNoRawat').value,
                    keterangan_hamil: document.getElementById('cutiKet').value,
                    terhitung_mulai: document.getElementById('cutiMulai').value,
                    perkiraan_lahir: document.getElementById('cutiHpl').value,
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

            document.getElementById('formRujukanInternal')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.apiFetch('/api/v1/rujukan-internal', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        no_rawat: document.getElementById('rujNoRawat').value,
                        kd_poli: document.getElementById('rujPoli').value,
                        kd_dokter: document.getElementById('rujDokter').value,
                        catatan: document.getElementById('rujCatatan').value,
                    }),
                })
                .then(res => res.json())
                .then(res => {
                    this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                    if (res.status === 'success') this.loadList('rujukan');
                })
                .catch(() => this.showAlert('danger', 'Gagal menyimpan rujukan internal.'));
            });
        },

        loadList: function (type) {
            const q = document.getElementById('suratSearchInput')?.value.trim() || '';
            if (type === 'rujukan') {
                this.apiFetch(`/api/v1/rujukan-internal?q=${encodeURIComponent(q)}`)
                    .then(res => res.json())
                    .then(res => {
                        const tbody = document.getElementById('tbodyRujukanInternal');
                        const rows = (res && res.data) ? res.data : [];
                        if (!tbody) return;
                        tbody.innerHTML = rows.length
                            ? rows.map(r => `<tr><td class="font-monospace">${this.escapeHtml(r.no_rawat)}</td><td>${this.escapeHtml(r.nm_pasien || '-')}</td><td>${this.escapeHtml(r.nm_poli || r.kd_poli)}</td><td>${this.escapeHtml(r.nm_dokter || r.kd_dokter)}</td><td>${this.escapeHtml(r.catatan)}</td></tr>`).join('')
                            : '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada rujukan internal.</td></tr>';
                    })
                    .catch(() => {});
                return;
            }
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
            const map = { sakit: 'tbodySuratSakit', hamil: 'tbodySuratHamil', narkoba: 'tbodySuratNarkoba', sehat: 'tbodySuratSehat', cuti_hamil: 'tbodySuratCutiHamil' };
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
                } else if (type === 'sehat') {
                    cols = `<td class="font-monospace">${this.escapeHtml(r.no_surat)}</td><td>${this.escapeHtml(r.no_rawat)}</td><td>${this.escapeHtml(r.nm_pasien || '-')}</td><td>${this.escapeHtml(r.tanggalsurat)}</td><td>${this.escapeHtml(r.keperluan)} (${this.escapeHtml(r.kesimpulan)})</td>`;
                } else if (type === 'cuti_hamil') {
                    cols = `<td class="font-monospace">${this.escapeHtml(r.no_surat)}</td><td>${this.escapeHtml(r.no_rawat)}</td><td>${this.escapeHtml(r.nm_pasien || '-')}</td><td>${this.escapeHtml(r.terhitung_mulai)}</td><td>${this.escapeHtml(r.perkiraan_lahir)} (${this.escapeHtml(r.keterangan_hamil)})</td>`;
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
