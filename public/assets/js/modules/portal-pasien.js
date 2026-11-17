(function (window, document) {
    'use strict';

    const PortalApp = {
        _rm: '',
        _tl: '',

        init: function () {
            document.getElementById('formPortalLookup')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.lookup();
            });
            document.getElementById('formPortalBooking')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.createBooking();
            });
        },

        escapeHtml: function (str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        },

        showAlert: function (type, msg) {
            const box = document.getElementById('portalAlert');
            if (box) {
                box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} py-2 small">${this.escapeHtml(msg)}</div>`;
            }
        },

        lookup: function () {
            this._rm = document.getElementById('portalRm').value.trim();
            this._tl = document.getElementById('portalTglLahir').value;
            fetch('/api/v1/portal-pasien/lookup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rkm_medis: this._rm, tgl_lahir: this._tl }),
            })
            .then(res => res.json())
            .then(res => {
                if (res.status !== 'success') {
                    this.showAlert('danger', res.message || 'Pasien tidak ditemukan.');
                    return;
                }
                const d = res.data;
                document.getElementById('portalContent').classList.remove('d-none');
                document.getElementById('portalPatientBanner').innerHTML = `Selamat datang, <strong>${this.escapeHtml(d.patient.nm_pasien)}</strong> (No. RM: ${this.escapeHtml(d.patient.no_rkm_medis)})`;

                const poliSel = document.getElementById('bookPoli');
                poliSel.innerHTML = (d.polis || []).map(p => `<option value="${this.escapeHtml(p.kd_poli)}">${this.escapeHtml(p.nm_poli)}</option>`).join('');
                const dokSel = document.getElementById('bookDokter');
                dokSel.innerHTML = (d.dokters || []).map(dr => `<option value="${this.escapeHtml(dr.kd_dokter)}">${this.escapeHtml(dr.nm_dokter)}</option>`).join('');

                const vBody = document.getElementById('tbodyPortalVisits');
                const rows = [].concat(
                    (d.bookings || []).map(b => ({ tgl: b.tanggal_periksa, poli: b.nm_poli, dr: b.nm_dokter, stts: 'Booking #' + b.no_reg })),
                    (d.visits || []).map(v => ({ tgl: v.tgl_registrasi, poli: v.nm_poli, dr: v.nm_dokter, stts: v.stts }))
                );
                vBody.innerHTML = rows.length
                    ? rows.map(r => `<tr><td>${this.escapeHtml(r.tgl)}</td><td>${this.escapeHtml(r.poli)} / ${this.escapeHtml(r.dr)}</td><td><span class="badge bg-secondary">${this.escapeHtml(r.stts)}</span></td></tr>`).join('')
                    : '<tr><td colspan="3" class="text-center text-muted py-2">Belum ada riwayat.</td></tr>';

                const lBody = document.getElementById('tbodyPortalLabs');
                const labs = d.lab_results || [];
                lBody.innerHTML = labs.length
                    ? labs.map(l => `<tr><td>${this.escapeHtml(l.tgl_periksa)}</td><td>${this.escapeHtml(l.pemeriksaan)}</td><td class="fw-bold">${this.escapeHtml(l.nilai)}</td><td>${this.escapeHtml(l.nilai_rujukan)}</td></tr>`).join('')
                    : '<tr><td colspan="4" class="text-center text-muted py-2">Belum ada hasil lab.</td></tr>';
            })
            .catch(() => this.showAlert('danger', 'Gagal memuat portal pasien.'));
        },

        createBooking: function () {
            fetch('/api/v1/portal-pasien/booking', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rkm_medis: this._rm,
                    tgl_lahir: this._tl,
                    tanggal_periksa: document.getElementById('bookTgl').value,
                    kd_poli: document.getElementById('bookPoli').value,
                    kd_dokter: document.getElementById('bookDokter').value,
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Booking diproses.');
                if (res.status === 'success') this.lookup();
            })
            .catch(() => this.showAlert('danger', 'Gagal mengirim booking online.'));
        },
    };

    document.addEventListener('DOMContentLoaded', () => PortalApp.init());
})(window, document);
