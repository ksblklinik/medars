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
            document.getElementById('btnRanapDocRefresh')?.addEventListener('click', () => this.loadPatients());
            document.getElementById('formCpptRanap')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveCppt();
            });
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
