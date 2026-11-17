(function (window, document) {
    'use strict';

    const KeuanganApp = {
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
            const box = document.getElementById('keuAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.getElementById('btnKeuFilter')?.addEventListener('click', () => this.loadOverview());
            ['keuFilterFrom', 'keuFilterTo'].forEach((id) => {
                document.getElementById(id)?.addEventListener('change', () => this.loadOverview());
            });
            document.getElementById('formPostJurnal')?.addEventListener('submit', (e) => {
                e.preventDefault();
                const val = (id) => document.getElementById(id)?.value || '';
                this.post('/api/v1/keuangan/jurnal', {
                    no_bukti: val('jrnBukti'),
                    kd_debet: val('jrnDebet'),
                    kd_kredit: val('jrnKredit'),
                    nominal: val('jrnNominal'),
                    keterangan: val('jrnKet'),
                });
            });
            document.getElementById('formSaveCoa')?.addEventListener('submit', (e) => {
                e.preventDefault();
                const val = (id) => document.getElementById(id)?.value || '';
                this.post('/api/v1/keuangan/rekening', {
                    kd_rek: val('coaKd'),
                    nm_rek: val('coaNm'),
                    tipe: val('coaTipe'),
                    balance: val('coaBal'),
                });
            });
        },

        post: function (url, payload) {
            this.apiFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') this.loadOverview();
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses transaksi keuangan.'));
        },

        loadOverview: function () {
            const from = document.getElementById('keuFilterFrom')?.value || '';
            const to = document.getElementById('keuFilterTo')?.value || '';
            const qs = new URLSearchParams();
            if (from) qs.set('from', from);
            if (to) qs.set('to', to);

            this.apiFetch('/api/v1/keuangan/overview' + (qs.toString() ? '?' + qs.toString() : ''))
                .then(res => res.json())
                .then(res => {
                    const lr = res.laba_rugi || {};
                    const fmt = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
                    document.getElementById('keuPendapatan').innerText = fmt(lr.total_pendapatan);
                    document.getElementById('keuBeban').innerText = fmt(lr.total_beban);
                    document.getElementById('keuLaba').innerText = fmt(lr.laba_bersih);

                    const jBody = document.getElementById('tbodyJurnal');
                    const jRows = res.jurnal || [];
                    jBody.innerHTML = jRows.length
                        ? jRows.map(j => `<tr><td class="font-monospace">${this.escapeHtml(j.no_jurnal)}</td><td>${this.escapeHtml(j.tgl_jurnal)}</td><td>${this.escapeHtml(j.kd_rek)} - ${this.escapeHtml(j.nm_rek)}</td><td>${this.escapeHtml(j.keterangan)}</td><td class="text-end">${fmt(j.debet)}</td><td class="text-end">${fmt(j.kredit)}</td></tr>`).join('')
                        : '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada jurnal pada periode ini.</td></tr>';

                    const bBody = document.getElementById('tbodyBukuBesar');
                    const bRows = res.buku_besar || [];
                    bBody.innerHTML = bRows.length
                        ? bRows.map(b => `<tr><td class="font-monospace">${this.escapeHtml(b.kd_rek)}</td><td>${this.escapeHtml(b.nm_rek)}</td><td>${this.escapeHtml(b.tipe)}</td><td class="text-end">${fmt(b.total_debet)}</td><td class="text-end">${fmt(b.total_kredit)}</td></tr>`).join('')
                        : '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada mutasi akun.</td></tr>';

                    const cBody = document.getElementById('tbodyCoa');
                    const cRows = res.rekening || [];
                    cBody.innerHTML = cRows.length
                        ? cRows.map(c => `<tr><td class="font-monospace">${this.escapeHtml(c.kd_rek)}</td><td>${this.escapeHtml(c.nm_rek)}</td><td>${this.escapeHtml(c.tipe)}</td><td>${this.escapeHtml(c.balance)}</td></tr>`).join('')
                        : '<tr><td colspan="4" class="text-center text-muted py-3">Belum ada master akun COA.</td></tr>';
                })
                .catch(() => this.showAlert('danger', 'Gagal memuat laporan keuangan.'));
        },
    };

    document.addEventListener('DOMContentLoaded', () => KeuanganApp.init());
})(window, document);
