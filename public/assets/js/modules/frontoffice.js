(function (window, document) {
    'use strict';

    const FrontOfficeApp = {
        init: function () {
            this.bindEvents();
            this.loadOverview();
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
            const box = document.getElementById('foAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.getElementById('btnFoRefresh')?.addEventListener('click', () => this.loadOverview());
            document.getElementById('foFilterDate')?.addEventListener('change', () => this.loadOverview());
        },

        loadOverview: function () {
            const date = document.getElementById('foFilterDate')?.value || '';
            this.apiFetch('/api/v1/frontoffice/overview' + (date ? '?date=' + encodeURIComponent(date) : ''))
                .then(res => res.json())
                .then(res => {
                    const s = res.stats || {};
                    const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = String(val ?? 0); };
                    setTxt('foStatTotal', s.total_kunjungan);
                    setTxt('foStatBelum', s.belum_periksa);
                    setTxt('foStatSudah', s.sudah_periksa);
                    setTxt('foStatBooking', s.booking_menunggu);
                    setTxt('foStatUnpaid', s.belum_bayar);
                    setTxt('foStatBatal', s.batal);

                    this.renderBookings(res.bookings || []);
                    this.renderVisits(res.visits || []);
                })
                .catch(() => this.showAlert('danger', 'Gagal memuat data Front Office.'));
        },

        renderBookings: function (rows) {
            const tbody = document.getElementById('tbodyFoBookings');
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Tidak ada booking pada tanggal ini.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((b) => {
                const tr = document.createElement('tr');
                const canCheckIn = b.status === 'Belum';
                tr.innerHTML = `
                    <td class="fw-bold">${this.escapeHtml(b.no_reg)}</td>
                    <td><div class="fw-semibold">${this.escapeHtml(b.nm_pasien || b.no_rkm_medis)}</div><small class="text-muted">${this.escapeHtml(b.no_rkm_medis)}</small></td>
                    <td>${this.escapeHtml(b.nm_poli || b.kd_poli)}</td>
                    <td><span class="badge ${canCheckIn ? 'bg-warning text-dark' : 'bg-success'}">${this.escapeHtml(b.status)}</span></td>
                    <td class="text-center">${canCheckIn ? '<button type="button" class="btn btn-xs btn-teal text-white btn-checkin">Check-In</button>' : '-'}</td>
                `;
                tr.querySelector('.btn-checkin')?.addEventListener('click', () => this.checkInBooking(b));
                tbody.appendChild(tr);
            });
        },

        renderVisits: function (rows) {
            const tbody = document.getElementById('tbodyFoVisits');
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada kunjungan pada tanggal ini.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((v) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="fw-bold">${this.escapeHtml(v.no_reg)}</td>
                    <td class="font-monospace">${this.escapeHtml(v.no_rawat)}</td>
                    <td><div class="fw-semibold">${this.escapeHtml(v.nm_pasien || '-')}</div><small class="text-muted">${this.escapeHtml(v.png_jawab || '-')}</small></td>
                    <td><div>${this.escapeHtml(v.nm_poli || '-')}</div><small class="text-muted">${this.escapeHtml(v.nm_dokter || '-')}</small></td>
                    <td><span class="badge bg-secondary">${this.escapeHtml(v.stts)}</span></td>
                    <td class="text-center"><button type="button" class="btn btn-xs btn-outline-teal btn-call-tv">Panggil TV</button></td>
                `;
                tr.querySelector('.btn-call-tv')?.addEventListener('click', () => this.callQueue(v.no_rawat));
                tbody.appendChild(tr);
            });
        },

        checkInBooking: function (b) {
            this.apiFetch('/api/v1/registration/checkin', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rkm_medis: b.no_rkm_medis,
                    tanggal_periksa: b.tanggal_periksa,
                    kd_poli: b.kd_poli,
                    kd_dokter: b.kd_dokter,
                    kd_pj: b.kd_pj,
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Check-in selesai.');
                this.loadOverview();
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses check-in booking.'));
        },

        callQueue: function (noRawat) {
            this.apiFetch('/api/v1/queue/call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: noRawat }),
            })
            .then(res => res.json())
            .then(res => this.showAlert(res.status === 'success' ? 'info' : 'danger', res.message || `Memanggil ${noRawat} ke Display TV.`))
            .catch(() => this.showAlert('danger', 'Gagal memanggil antrean TV.'));
        },
    };

    document.addEventListener('DOMContentLoaded', () => FrontOfficeApp.init());
})(window, document);
