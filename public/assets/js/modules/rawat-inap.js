(function (window, document) {
    'use strict';

    const RanapApp = {
        init: function () {
            this.bindEvents();
            this.loadAll();
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
            const box = document.getElementById('ranapAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.getElementById('btnRanapSearch')?.addEventListener('click', () => this.loadInpatients());
            document.getElementById('formRanapCheckIn')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.checkIn();
            });
            document.getElementById('formSaveKamar')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveKamar();
            });
        },

        loadAll: function () {
            this.loadInpatients();
            this.loadKamar();
        },

        loadInpatients: function () {
            const q = document.getElementById('ranapSearchInput')?.value.trim() || '';
            this.apiFetch('/api/v1/ranap/inpatients?q=' + encodeURIComponent(q))
                .then(res => res.json())
                .then(res => this.renderInpatients(res.data || []))
                .catch(() => this.showAlert('danger', 'Gagal memuat data pasien ranap.'));
        },

        loadKamar: function () {
            this.apiFetch('/api/v1/ranap/kamar')
                .then(res => res.json())
                .then(res => this.renderKamar(res.data || []))
                .catch(() => {});
        },

        renderInpatients: function (rows) {
            const tbody = document.getElementById('tbodyInpatients');
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada pasien rawat inap.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((r) => {
                const isActive = r.stts_pulang === '-';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="font-monospace">${this.escapeHtml(r.no_rawat)}</td>
                    <td><div class="fw-semibold">${this.escapeHtml(r.nm_pasien || '-')}</div><small class="text-muted">${this.escapeHtml(r.png_jawab || '-')}</small></td>
                    <td><strong>${this.escapeHtml(r.kd_kamar)}</strong><div class="small text-muted">${this.escapeHtml(r.nm_bangsal || '')}</div></td>
                    <td>${this.escapeHtml(r.tgl_masuk)}</td>
                    <td><span class="badge ${isActive ? 'bg-warning text-dark' : 'bg-success'}">${this.escapeHtml(isActive ? 'Dirawat' : r.stts_pulang)}</span></td>
                    <td class="text-center">${isActive ? '<button type="button" class="btn btn-xs btn-outline-teal btn-checkout">Pulangkan</button>' : '-'}</td>
                `;
                tr.querySelector('.btn-checkout')?.addEventListener('click', () => this.checkOut(r.no_rawat, r.kd_kamar));
                tbody.appendChild(tr);
            });
        },

        renderKamar: function (rows) {
            const tbody = document.getElementById('tbodyKamar');
            const select = document.getElementById('ranapKdKamar');
            if (select) {
                const empty = rows.filter(k => k.status === 'KOSONG');
                select.innerHTML = empty.length
                    ? empty.map(k => `<option value="${this.escapeHtml(k.kd_kamar)}">${this.escapeHtml(k.kd_kamar)} (${this.escapeHtml(k.nm_bangsal || k.kd_bangsal)} - Rp ${this.escapeHtml(Number(k.trf_kamar || 0).toLocaleString('id-ID'))})</option>`).join('')
                    : '<option value="">Tidak ada kamar kosong</option>';
            }
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada data kamar.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(k => `
                <tr>
                    <td class="font-monospace">${this.escapeHtml(k.kd_kamar)}</td>
                    <td>${this.escapeHtml(k.nm_bangsal || k.kd_bangsal)}</td>
                    <td>${this.escapeHtml(k.kelas)}</td>
                    <td class="text-end">Rp ${this.escapeHtml(Number(k.trf_kamar || 0).toLocaleString('id-ID'))}</td>
                    <td><span class="badge ${k.status === 'KOSONG' ? 'bg-success' : 'bg-danger'}">${this.escapeHtml(k.status)}</span></td>
                </tr>
            `).join('');
        },

        checkIn: function () {
            this.apiFetch('/api/v1/ranap/checkin', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rawat: document.getElementById('ranapNoRawat').value,
                    kd_kamar: document.getElementById('ranapKdKamar').value,
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Check-in selesai.');
                if (res.status === 'success') this.loadAll();
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses check-in kamar.'));
        },

        checkOut: function (noRawat, kdKamar) {
            this.apiFetch('/api/v1/ranap/checkout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: noRawat, kd_kamar: kdKamar, stts_pulang: 'Membaik' }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Checkout selesai.');
                if (res.status === 'success') this.loadAll();
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses checkout kamar.'));
        },

        saveKamar: function () {
            const val = (id) => document.getElementById(id)?.value || '';
            this.apiFetch('/api/v1/ranap/kamar/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    kd_kamar: val('kmrKode'),
                    kd_bangsal: val('kmrBangsal'),
                    nm_bangsal: val('kmrNmBangsal'),
                    kelas: val('kmrKelas'),
                    trf_kamar: val('kmrTarif'),
                    status: val('kmrStatus'),
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Kamar disimpan.');
                if (res.status === 'success') this.loadAll();
            })
            .catch(() => this.showAlert('danger', 'Gagal menyimpan data kamar.'));
        },
    };

    document.addEventListener('DOMContentLoaded', () => RanapApp.init());
})(window, document);
