(function (window, document) {
    'use strict';

    const ManajemenApp = {
        init: function () {
            document.getElementById('btnMnjFilter')?.addEventListener('click', () => this.load());
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
            const from = document.getElementById('mnjFrom')?.value || '';
            const to = document.getElementById('mnjTo')?.value || '';
            const qs = new URLSearchParams({ from, to }).toString();

            this.apiFetch('/api/v1/manajemen/dashboard?' + qs)
                .then(res => res.json())
                .then(res => {
                    const k = res.kpi || {};
                    const fmt = (v) => 'Rp ' + Number(v || 0).toLocaleString('id-ID');
                    document.getElementById('mnjKpiVisits').innerText = String(k.total_kunjungan ?? 0);
                    document.getElementById('mnjKpiRev').innerText = fmt(k.total_pendapatan);
                    document.getElementById('mnjKpiLead').innerText = `${k.avg_lead_time_minutes ?? 0} mnt`;
                    document.getElementById('mnjKpiOver').innerText = String(k.overtime_visits_count ?? 0);

                    const leanBody = document.getElementById('tbodyMnjLean');
                    const lean = res.lean_poli || [];
                    leanBody.innerHTML = lean.length
                        ? lean.map(l => `<tr><td>${this.escapeHtml(l.nm_poli)}</td><td class="text-center">${this.escapeHtml(l.total_pasien)}</td><td class="text-center">${this.escapeHtml(l.net_signed_avg_minutes)} mnt</td><td class="text-center">${this.escapeHtml(l.avg_soap_lead_minutes)} mnt</td><td class="text-center">${this.escapeHtml(l.overtime_count)}</td></tr>`).join('')
                        : '<tr><td colspan="5" class="text-center text-muted py-3">Belum ada data kunjungan.</td></tr>';

                    const claimBody = document.getElementById('tbodyMnjClaim');
                    const claims = res.revenue_by_claim || [];
                    claimBody.innerHTML = claims.length
                        ? claims.map(c => `<tr><td class="ps-2">${this.escapeHtml(c.claim_type)} (${this.escapeHtml(c.total_items)})</td><td class="text-end pe-2 fw-bold">${fmt(c.total_amount)}</td></tr>`).join('')
                        : '<tr><td class="text-center text-muted p-2">Belum ada transaksi.</td></tr>';

                    const trendBody = document.getElementById('tbodyMnjTrend');
                    const trend = res.daily_trend || [];
                    trendBody.innerHTML = trend.length
                        ? trend.map(t => `<tr><td class="ps-2">${this.escapeHtml(t.tanggal)}</td><td class="text-end pe-2 fw-bold">${this.escapeHtml(t.kunjungan)} pasien</td></tr>`).join('')
                        : '<tr><td class="text-center text-muted p-2">Belum ada tren.</td></tr>';
                })
                .catch(() => {});
        },
    };

    document.addEventListener('DOMContentLoaded', () => ManajemenApp.init());
})(window, document);
