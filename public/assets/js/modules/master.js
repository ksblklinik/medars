(function (window, document) {
    'use strict';

    const MasterApp = {
        _activeEntity: 'poliklinik',

        init: function () {
            this.bindEvents();
            this.loadEntity('poliklinik');
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
            const box = document.getElementById('masterAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.querySelectorAll('#masterTabs button[data-entity]').forEach((btn) => {
                btn.addEventListener('shown.bs.tab', () => {
                    this._activeEntity = btn.getAttribute('data-entity') || 'poliklinik';
                    this.loadEntity(this._activeEntity);
                });
            });

            document.getElementById('btnMasterSearch')?.addEventListener('click', () => this.loadEntity(this._activeEntity));
            document.getElementById('masterSearchInput')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.loadEntity(this._activeEntity);
                }
            });

            document.getElementById('formPoli')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('poliklinik', {
                    kd_poli: document.getElementById('poliKd').value,
                    nm_poli: document.getElementById('poliNm').value,
                    registrasi: document.getElementById('poliReg').value,
                    registrasilama: document.getElementById('poliRegLama').value,
                    status: document.getElementById('poliStatus').value,
                    allow_bpjs: document.getElementById('poliBpjs').value,
                });
            });

            document.getElementById('formDokter')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('dokter', {
                    kd_dokter: document.getElementById('dokterKd').value,
                    nm_dokter: document.getElementById('dokterNm').value,
                    jk: document.getElementById('dokterJk').value,
                    no_ijn_praktek: document.getElementById('dokterSip').value,
                    no_telp: document.getElementById('dokterTelp').value,
                    status: document.getElementById('dokterStatus').value,
                });
            });

            document.getElementById('formPenjab')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('penjab', {
                    kd_pj: document.getElementById('penjabKd').value,
                    png_jawab: document.getElementById('penjabNm').value,
                    nama_perusahaan: document.getElementById('penjabPerusahaan').value,
                    status: document.getElementById('penjabStatus').value,
                });
            });

            document.getElementById('formTarif')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('jns_perawatan', {
                    kd_jenis_prw: document.getElementById('tarifKd').value,
                    nm_perawatan: document.getElementById('tarifNm').value,
                    material: document.getElementById('tarifMaterial').value,
                    tarif_tindakandr: document.getElementById('tarifDr').value,
                    total_byrdr: document.getElementById('tarifTotal').value,
                    status: document.getElementById('tarifStatus').value,
                });
            });

            document.getElementById('formJadwal')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('jadwal', {
                    kd_dokter: document.getElementById('jadwalDokter').value,
                    kd_poli: document.getElementById('jadwalPoli').value,
                    hari_kerja: document.getElementById('jadwalHari').value,
                    kuota: document.getElementById('jadwalKuota').value,
                    jam_mulai: document.getElementById('jadwalMulai').value,
                    jam_selesai: document.getElementById('jadwalSelesai').value,
                    prefik: document.getElementById('jadwalPrefik').value,
                    room: document.getElementById('jadwalRoom').value,
                });
            });

            document.getElementById('formBangsal')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('bangsal', {
                    kd_bangsal: document.getElementById('bangsalKd').value,
                    nm_bangsal: document.getElementById('bangsalNm').value,
                    status: document.getElementById('bangsalStatus').value,
                });
            });

            document.getElementById('formKamar')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('kamar', {
                    kd_kamar: document.getElementById('kamarKd').value,
                    kd_bangsal: document.getElementById('kamarBangsal').value,
                    kelas: document.getElementById('kamarKelas').value,
                    trf_kamar: document.getElementById('kamarTarif').value,
                    status: document.getElementById('kamarStatus').value,
                });
            });

            document.getElementById('formPenyakit')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveEntity('penyakit', {
                    kd_penyakit: document.getElementById('penyakitKd').value,
                    nm_penyakit: document.getElementById('penyakitNm').value,
                    nm_indonesia: document.getElementById('penyakitIdn').value,
                    status: document.getElementById('penyakitStatus').value,
                    skdi_level: document.getElementById('penyakitSkdi').value,
                });
            });
        },

        loadEntity: function (entity) {
            const q = document.getElementById('masterSearchInput')?.value.trim() || '';
            this.apiFetch(`/api/v1/master/${encodeURIComponent(entity)}?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(res => {
                    const rows = (res && res.data) ? res.data : [];
                    this.renderTable(entity, rows);
                })
                .catch(() => this.showAlert('danger', 'Gagal memuat data master.'));
        },

        saveEntity: function (entity, payload) {
            this.apiFetch(`/api/v1/master/${encodeURIComponent(entity)}/save`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Proses selesai.');
                if (res.status === 'success') this.loadEntity(entity);
            })
            .catch(() => this.showAlert('danger', 'Gagal menyimpan data master.'));
        },

        deleteEntity: function (entity, keys) {
            this.apiFetch(`/api/v1/master/${encodeURIComponent(entity)}/delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(keys),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Proses hapus selesai.');
                if (res.status === 'success') this.loadEntity(entity);
            })
            .catch(() => this.showAlert('danger', 'Gagal menghapus data master.'));
        },

        renderTable: function (entity, rows) {
            const mapId = {
                poliklinik: 'tbodyPoli',
                dokter: 'tbodyDokter',
                penjab: 'tbodyPenjab',
                jns_perawatan: 'tbodyTarif',
                jadwal: 'tbodyJadwal',
                bangsal: 'tbodyBangsal',
                kamar: 'tbodyKamar',
                penyakit: 'tbodyPenyakit',
            };
            const tbody = document.getElementById(mapId[entity]);
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada data.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((r) => {
                const tr = document.createElement('tr');
                let delKeys = {};
                if (entity === 'poliklinik') {
                    delKeys = { kd_poli: r.kd_poli };
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kd_poli)}</td><td>${this.escapeHtml(r.nm_poli)}</td><td class="text-end">${this.escapeHtml(Number(r.registrasi || 0).toLocaleString('id-ID'))}</td><td class="text-end">${this.escapeHtml(Number(r.registrasilama || 0).toLocaleString('id-ID'))}</td><td>${this.escapeHtml(r.status === '1' ? 'Aktif' : 'Nonaktif')}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                } else if (entity === 'dokter') {
                    delKeys = { kd_dokter: r.kd_dokter };
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kd_dokter)}</td><td>${this.escapeHtml(r.nm_dokter)}</td><td>${this.escapeHtml(r.jk)}</td><td>${this.escapeHtml(r.no_ijn_praktek)}</td><td>${this.escapeHtml(r.status === '1' ? 'Aktif' : 'Nonaktif')}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                } else if (entity === 'penjab') {
                    delKeys = { kd_pj: r.kd_pj };
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kd_pj)}</td><td>${this.escapeHtml(r.png_jawab)}</td><td>${this.escapeHtml(r.nama_perusahaan)}</td><td>${this.escapeHtml(r.status === '1' ? 'Aktif' : 'Nonaktif')}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                } else if (entity === 'jns_perawatan') {
                    delKeys = { kd_jenis_prw: r.kd_jenis_prw };
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kd_jenis_prw)}</td><td>${this.escapeHtml(r.nm_perawatan)}</td><td class="text-end">Rp ${this.escapeHtml(Number(r.total_byrdr || 0).toLocaleString('id-ID'))}</td><td>${this.escapeHtml(r.status === '1' ? 'Aktif' : 'Nonaktif')}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                } else if (entity === 'bangsal') {
                    delKeys = { kd_bangsal: r.kd_bangsal };
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kd_bangsal)}</td><td>${this.escapeHtml(r.nm_bangsal)}</td><td>${this.escapeHtml(r.status === '1' ? 'Aktif' : 'Nonaktif')}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                } else if (entity === 'kamar') {
                    delKeys = { kd_kamar: r.kd_kamar };
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kd_kamar)}</td><td>${this.escapeHtml(r.nm_bangsal || r.kd_bangsal)}</td><td>${this.escapeHtml(r.kelas)}</td><td class="text-end">Rp ${this.escapeHtml(Number(r.trf_kamar || 0).toLocaleString('id-ID'))}</td><td>${this.escapeHtml(r.status)}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                } else if (entity === 'penyakit') {
                    delKeys = { kd_penyakit: r.kd_penyakit };
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.kd_penyakit)}</td><td>${this.escapeHtml(r.nm_penyakit)}</td><td>${this.escapeHtml(r.nm_indonesia)}</td><td>${this.escapeHtml(r.status)}</td><td>${this.escapeHtml(r.skdi_level)}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                } else {
                    delKeys = { kd_dokter: r.kd_dokter, kd_poli: r.kd_poli, hari_kerja: r.hari_kerja, jam_mulai: r.jam_mulai };
                    tr.innerHTML = `<td>${this.escapeHtml(r.nm_dokter || r.kd_dokter)}</td><td>${this.escapeHtml(r.nm_poli || r.kd_poli)}</td><td>${this.escapeHtml(r.hari_kerja)}</td><td>${this.escapeHtml(r.jam_mulai)} - ${this.escapeHtml(r.jam_selesai)}</td><td>${this.escapeHtml(r.kuota)}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                }
                tr.querySelector('.btn-del')?.addEventListener('click', () => this.deleteEntity(entity, delKeys));
                tbody.appendChild(tr);
            });
        },
    };

    document.addEventListener('DOMContentLoaded', () => MasterApp.init());
})(window, document);
