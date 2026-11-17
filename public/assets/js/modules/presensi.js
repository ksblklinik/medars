(function (window, document) {
    'use strict';

    const PresensiApp = {
        init: function () {
            document.getElementById('btnClockIn')?.addEventListener('click', () => this.clockIn());
            document.getElementById('btnClockOut')?.addEventListener('click', () => this.clockOut());
            document.getElementById('btnRefreshPresensi')?.addEventListener('click', () => this.load());
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
            const box = document.getElementById('presensiAlert');
            if (box) box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} py-2 small">${this.escapeHtml(msg)}</div>`;
        },

        clockIn: function () {
            this.apiFetch('/api/v1/presensi/clock-in', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    shift: document.getElementById('presShift').value,
                    lokasi: document.getElementById('presLokasi').value,
                    keterangan: document.getElementById('presKet').value,
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') this.load();
            });
        },

        clockOut: function () {
            this.apiFetch('/api/v1/presensi/clock-out', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({}),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') this.load();
            });
        },

        load: function () {
            this.apiFetch('/api/v1/presensi/list')
                .then(res => res.json())
                .then(res => {
                    const tbody = document.getElementById('tbodyPresensi');
                    const rows = res.data || [];
                    tbody.innerHTML = rows.length
                        ? rows.map(r => `<tr><td>${this.escapeHtml(r.tgl_presensi)}</td><td class="fw-semibold">${this.escapeHtml(r.nip)}</td><td>${this.escapeHtml(r.shift)}</td><td>${this.escapeHtml(r.jam_masuk)}</td><td>${this.escapeHtml(r.jam_pulang || '-')}</td><td>${this.escapeHtml(r.lokasi)}</td></tr>`).join('')
                        : '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada data presensi.</td></tr>';
                });
        },
    };

    document.addEventListener('DOMContentLoaded', () => PresensiApp.init());
})(window, document);
