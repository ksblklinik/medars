(function (window, document) {
    'use strict';

    const DokterRanapApp = {
        init: function () {
            this.bindEvents();
            this.loadPatients();
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
            const box = document.getElementById('ranapDocAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            this._resepItems = [];
            document.getElementById('btnRanapDocRefresh')?.addEventListener('click', () => this.loadPatients());
            document.getElementById('formCpptRanap')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveCppt();
            });
            document.getElementById('btnRanapSearchObat')?.addEventListener('click', () => this.searchObat());
            document.getElementById('btnRanapKirimResep')?.addEventListener('click', () => this.kirimResepRanap());
            document.getElementById('formRanapResume')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveResumePulang();
            });
            document.getElementById('btnRanapPrintResume')?.addEventListener('click', () => {
                const nr = document.getElementById('cpptNoRawat')?.value.trim();
                if (!nr) return this.showAlert('warning', 'Pilih pasien ranap terlebih dahulu.');
                window.open('/dokter-ranap/resume/cetak?no_rawat=' + encodeURIComponent(nr), '_blank');
            });
        },

        searchObat: function () {
            const q = document.getElementById('ranapObatSearch')?.value.trim() || '';
            this.apiFetch('/api/v1/dokter-ranap/obat/search?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(res => {
                    const box = document.getElementById('ranapObatResults');
                    const rows = res.data || [];
                    if (!box) return;
                    box.classList.remove('d-none');
                    box.innerHTML = rows.map(o => `<button type="button" class="list-group-item list-group-item-action js-add-ranap-obat" data-kode="${this.escapeHtml(o.kode_brng)}" data-nama="${this.escapeHtml(o.nama_brng)}">${this.escapeHtml(o.nama_brng)} (${this.escapeHtml(o.kode_brng)})</button>`).join('') || '<div class="p-2 text-muted">Obat tidak ditemukan.</div>';
                    box.querySelectorAll('.js-add-ranap-obat').forEach(btn => {
                        btn.addEventListener('click', () => {
                            this._resepItems.push({ kode_brng: btn.dataset.kode, nama_brng: btn.dataset.nama, jml: 10, aturan_pakai: '3x1 sesudah makan' });
                            document.getElementById('ranapResepDraft').innerHTML = this._resepItems.map(i => `<div>&bull; ${this.escapeHtml(i.nama_brng)} (${Number(i.jml)}) - ${this.escapeHtml(i.aturan_pakai)}</div>`).join('');
                        });
                    });
                });
        },

        kirimResepRanap: function () {
            const nr = document.getElementById('cpptNoRawat')?.value.trim();
            if (!nr || !this._resepItems.length) return this.showAlert('warning', 'Pilih pasien dan item obat terlebih dahulu.');
            this.apiFetch('/api/v1/dokter-ranap/eresep/create', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: nr, non_racikan: this._resepItems }),
            })
            .then(r => r.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'E-Resep Ranap terkirim.');
                if (res.status === 'success') {
                    this._resepItems = [];
                    document.getElementById('ranapResepDraft').innerHTML = 'Belum ada item obat dipilih.';
                }
            });
        },

        saveResumePulang: function () {
            const nr = document.getElementById('cpptNoRawat')?.value.trim();
            if (!nr) return this.showAlert('warning', 'Pilih pasien ranap terlebih dahulu.');
            this.apiFetch('/api/v1/dokter-ranap/resume/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rawat: nr,
                    keluhan_utama: document.getElementById('ranapResKeluhan').value,
                    jalannya_penyakit: document.getElementById('ranapResJalan').value,
                    kd_diagnosa_utama: document.getElementById('ranapResKdDiag').value,
                    diagnosa_utama: document.getElementById('ranapResDiag').value,
                    kondisi_pulang: document.getElementById('ranapResKondisi').value,
                    obat_pulang: document.getElementById('ranapResObat').value,
                }),
            })
            .then(r => r.json())
            .then(res => this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Resume pulang tersimpan.'));
        },

        loadPatients: function () {
            const q = document.getElementById('ranapDocSearch')?.value.trim() || '';
            this.apiFetch('/api/v1/dokter-ranap/patients?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(res => this.renderPatients(res.data || []))
                .catch(() => this.showAlert('danger', 'Gagal memuat pasien ranap.'));
        },

        renderPatients: function (rows) {
            const tbody = document.getElementById('tbodyRanapDocPatients');
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">Belum ada pasien rawat inap aktif.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((r) => {
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.innerHTML = `
                    <td><strong>${this.escapeHtml(r.kd_kamar)}</strong><div class="small text-muted">${this.escapeHtml(r.nm_bangsal || '')}</div></td>
                    <td><div class="fw-semibold">${this.escapeHtml(r.nm_pasien || '-')}</div><small class="text-muted">${this.escapeHtml(r.no_rkm_medis)}</small></td>
                    <td class="font-monospace">${this.escapeHtml(r.no_rawat)}</td>
                `;
                tr.addEventListener('click', () => this.selectPatient(r));
                tbody.appendChild(tr);
            });
        },

        selectPatient: function (r) {
            document.getElementById('cpptNoRawat').value = r.no_rawat;
            document.getElementById('activeRanapDocBanner').innerText = `${r.kd_kamar} | ${r.nm_pasien} (${r.no_rawat})`;
            this.loadCppt(r.no_rawat);
        },

        loadCppt: function (noRawat) {
            const list = document.getElementById('cpptHistoryList');
            if (!list || !noRawat) return;
            this.apiFetch('/api/v1/dokter-ranap/cppt?no_rawat=' + encodeURIComponent(noRawat))
                .then(res => res.json())
                .then(res => {
                    const rows = res.data || [];
                    if (!rows.length) {
                        list.innerHTML = '<div class="list-group-item text-muted">Belum ada catatan CPPT.</div>';
                        return;
                    }
                    list.innerHTML = rows.map(c => `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between"><strong class="text-medical-teal">${this.escapeHtml(c.tgl_perawatan)} ${this.escapeHtml(c.jam_rawat)}</strong><span class="badge bg-secondary">${this.escapeHtml(c.nip)}</span></div>
                            <div><strong>S:</strong> ${this.escapeHtml(c.keluhan)} | <strong>O:</strong> ${this.escapeHtml(c.pemeriksaan)}</div>
                            <div><strong>A:</strong> ${this.escapeHtml(c.penilaian)} | <strong>P:</strong> ${this.escapeHtml(c.rtl)}</div>
                        </div>
                    `).join('');
                })
                .catch(() => {});
        },

        saveCppt: function () {
            const val = (id) => document.getElementById(id)?.value || '';
            const nr = val('cpptNoRawat');
            if (!nr) {
                this.showAlert('warning', 'Pilih pasien rawat inap terlebih dahulu.');
                return;
            }
            this.apiFetch('/api/v1/dokter-ranap/cppt/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rawat: nr,
                    tensi: val('cpptTensi'),
                    nadi: val('cpptNadi'),
                    suhu_tubuh: val('cpptSuhu'),
                    respirasi: val('cpptRr'),
                    spo2: val('cpptSpo2'),
                    gcs: val('cpptGcs'),
                    keluhan: val('cpptS'),
                    pemeriksaan: val('cpptO'),
                    penilaian: val('cpptA'),
                    rtl: val('cpptP'),
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'CPPT disimpan.');
                if (res.status === 'success') this.loadCppt(nr);
            })
            .catch(() => this.showAlert('danger', 'Gagal menyimpan CPPT Ranap.'));
        },
    };

    document.addEventListener('DOMContentLoaded', () => DokterRanapApp.init());
})(window, document);
