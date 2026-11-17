(function (window, document) {
    'use strict';

    const IgdApp = {
        init: function () {
            this.bindEvents();
            this.loadVisits();
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
            const box = document.getElementById('igdAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.getElementById('btnIgdRefresh')?.addEventListener('click', () => this.loadVisits());
            ['igdFilterFrom', 'igdFilterTo'].forEach((id) => {
                document.getElementById(id)?.addEventListener('change', () => this.loadVisits());
            });
            document.getElementById('formIgdTriase')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.saveTriase();
            });
            document.getElementById('btnIgdDisposisi')?.addEventListener('click', () => this.updateDisposisi());
        },

        loadVisits: function () {
            const from = document.getElementById('igdFilterFrom')?.value || '';
            const to = document.getElementById('igdFilterTo')?.value || '';
            const qs = new URLSearchParams();
            if (from) qs.set('from', from);
            if (to) qs.set('to', to);

            this.apiFetch('/api/v1/igd/list' + (qs.toString() ? '?' + qs.toString() : ''))
                .then(res => res.json())
                .then(res => this.renderVisits(res.data || []))
                .catch(() => this.showAlert('danger', 'Gagal memuat antrean IGD.'));
        },

        renderVisits: function (rows) {
            const tbody = document.getElementById('tbodyIgdVisits');
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada pasien IGD pada periode ini.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((r) => {
                const zona = r.zona_triase || 'Hijau';
                const badge = zona === 'Merah' ? 'bg-danger' : (zona === 'Kuning' ? 'bg-warning text-dark' : (zona === 'Hitam' ? 'bg-dark' : 'bg-success'));
                const tr = document.createElement('tr');
                tr.style.cursor = 'pointer';
                tr.innerHTML = `
                    <td><span class="badge ${badge}">${this.escapeHtml(zona)}</span></td>
                    <td class="font-monospace">${this.escapeHtml(r.no_rawat)}</td>
                    <td><div class="fw-semibold">${this.escapeHtml(r.nm_pasien || '-')}</div><small class="text-muted">${this.escapeHtml(r.no_rkm_medis)}</small></td>
                    <td><div>${this.escapeHtml(r.keluhan_utama || '-')}</div><small class="text-muted">SpO2: ${this.escapeHtml(r.spo2 || '-')}%</small></td>
                    <td><span class="badge bg-secondary">${this.escapeHtml(r.disposisi || r.stts)}</span></td>
                `;
                tr.addEventListener('click', () => this.selectVisit(r));
                tbody.appendChild(tr);
            });
        },

        selectVisit: function (r) {
            const setVal = (id, v) => { const el = document.getElementById(id); if (el) el.value = v || ''; };
            setVal('igdNoRawat', r.no_rawat);
            setVal('igdZona', r.zona_triase || 'Hijau');
            setVal('igdCaraMasuk', r.cara_masuk || 'Jalan');
            setVal('igdKeluhan', r.keluhan_utama || '');
            setVal('igdTensi', r.tensi || '');
            setVal('igdNadi', r.nadi || '');
            setVal('igdSuhu', r.suhu || '');
            setVal('igdRr', r.respirasi || '');
            setVal('igdSpo2', r.spo2 || '');
            setVal('igdGcs', r.gcs || '15');
            if (r.disposisi) setVal('igdDisposisi', r.disposisi);
        },

        saveTriase: function () {
            const val = (id) => document.getElementById(id)?.value || '';
            this.apiFetch('/api/v1/igd/triase', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rawat: val('igdNoRawat'),
                    zona_triase: val('igdZona'),
                    cara_masuk: val('igdCaraMasuk'),
                    keluhan_utama: val('igdKeluhan'),
                    tensi: val('igdTensi'),
                    nadi: val('igdNadi'),
                    suhu: val('igdSuhu'),
                    respirasi: val('igdRr'),
                    spo2: val('igdSpo2'),
                    gcs: val('igdGcs'),
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Triase disimpan.');
                if (res.status === 'success') this.loadVisits();
            })
            .catch(() => this.showAlert('danger', 'Gagal menyimpan triase IGD.'));
        },

        updateDisposisi: function () {
            const nr = document.getElementById('igdNoRawat')?.value || '';
            const disp = document.getElementById('igdDisposisi')?.value || 'Observasi';
            if (!nr) {
                this.showAlert('warning', 'Pilih pasien IGD terlebih dahulu.');
                return;
            }
            this.apiFetch('/api/v1/igd/disposisi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rawat: nr,
                    disposisi: disp,
                    status_lanjut: disp === 'Rawat Inap' ? 'Ranap' : 'Ralan',
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Disposisi diperbarui.');
                if (res.status === 'success') this.loadVisits();
            })
            .catch(() => this.showAlert('danger', 'Gagal memperbarui disposisi IGD.'));
        },
    };

    document.addEventListener('DOMContentLoaded', () => IgdApp.init());
})(window, document);
