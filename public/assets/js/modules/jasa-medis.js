(function (window, document) {
    'use strict';

    const JasaMedisApp = {
        init: function () {
            document.getElementById('btnJmFilter')?.addEventListener('click', () => this.load());
            document.getElementById('btnJmPrint')?.addEventListener('click', () => {
                const from = document.getElementById('jmFrom')?.value || '';
                const to = document.getElementById('jmTo')?.value || '';
                window.open(`/jasa-medis/cetak?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`, '_blank');
            });
            this.load();
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

        load: function () {
            const from = document.getElementById('jmFrom')?.value || '';
            const to = document.getElementById('jmTo')?.value || '';
            const dr = document.getElementById('jmDokter')?.value.trim() || '';
            const qs = new URLSearchParams({ from, to, kd_dokter: dr }).toString();

            this.apiFetch('/api/v1/jasa-medis/summary?' + qs)
                .then(res => res.json())
                .then(res => {
                    const fmt = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
                    const sBody = document.getElementById('tbodyJmSummary');
                    const summary = res.summary || [];
                    sBody.innerHTML = summary.length
                        ? summary.map(r => `<tr><td><strong>${this.escapeHtml(r.nm_dokter)}</strong> <small class="text-muted">(${this.escapeHtml(r.kd_dokter)})</small></td><td class="text-center">${this.escapeHtml(r.jumlah_tindakan)}</td><td class="text-end">${fmt(r.jasa_tunai)}</td><td class="text-end">${fmt(r.jasa_bpjs)}</td><td class="text-end">${fmt(r.jasa_piutang)}</td><td class="text-end fw-bold text-medical-teal">${fmt(r.total_jasa_dokter)}</td></tr>`).join('')
                        : '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada transaksi jasa medis.</td></tr>';

                    const dBody = document.getElementById('tbodyJmDetails');
                    const details = res.details || [];
                    dBody.innerHTML = details.length
                        ? details.map(d => `<tr><td>${this.escapeHtml(d.tgl_perawatan)}</td><td class="font-monospace">${this.escapeHtml(d.no_rawat)}</td><td>${this.escapeHtml(d.nm_pasien || '-')}</td><td>${this.escapeHtml(d.nm_dokter)}</td><td>${this.escapeHtml(d.nm_perawatan)}</td><td><span class="badge bg-info text-dark">${this.escapeHtml(d.jenis_klaim)}</span></td><td class="text-end">${fmt(d.tarif_tindakandr)}</td></tr>`).join('')
                        : '<tr><td colspan="7" class="text-center text-muted py-3">Tidak ada rincian tindakan.</td></tr>';
                })
                .catch(() => {});
        },
    };

    document.addEventListener('DOMContentLoaded', () => JasaMedisApp.init());
})(window, document);
