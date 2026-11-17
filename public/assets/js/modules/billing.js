(function (window, document) {
    'use strict';

    const BillingApp = {
        _activeRow: null,

        init: function () {
            this.loadQueue();
            this.loadPosSales();
            this.bindEvents();
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

        showAlert: function (type, msg) {
            const c = document.getElementById('billingAlertContainer');
            if (!c) return;
            c.innerHTML = `
                <div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small" role="alert">
                    ${msg}
                    <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                </div>
            `;
        },

        apiFetch: function (url, options) {
            if (window.MedarsApp && typeof window.MedarsApp.apiFetch === 'function') {
                return window.MedarsApp.apiFetch(url, options);
            }
            return fetch(url, options);
        },

        bindEvents: function () {
            const btnRef = document.getElementById('btnRefreshBillingQueue');
            if (btnRef) btnRef.addEventListener('click', () => this.loadQueue());

            const btnFilter = document.getElementById('btnFilterBilling');
            if (btnFilter) btnFilter.addEventListener('click', () => this.loadQueue());
            ['billingFilterFrom', 'billingFilterTo', 'billingFilterLanjut'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('change', () => this.loadQueue());
            });

            const btnPrint = document.getElementById('btnPrintFaktur');
            if (btnPrint) btnPrint.addEventListener('click', () => this.printFaktur());

            const btnSettle = document.getElementById('btnSettlePiutang');
            if (btnSettle) btnSettle.addEventListener('click', () => this.settlePiutangActive());

            const btnPay = document.getElementById('btnPayBilling');
            if (btnPay) btnPay.addEventListener('click', () => this.payActive());

            const btnVoid = document.getElementById('btnVoidBilling');
            if (btnVoid) btnVoid.addEventListener('click', () => this.voidActive());

            const btnRec = document.getElementById('btnReconcileOrder');
            if (btnRec) btnRec.addEventListener('click', () => this.reconcileActive());

            const btnPos = document.getElementById('btnSubmitPos');
            if (btnPos) btnPos.addEventListener('click', () => this.submitPos());

            document.addEventListener('keydown', (e) => {
                if (e.key === 'F2') {
                    e.preventDefault();
                    this.payActive();
                } else if (e.key === 'F8') {
                    e.preventDefault();
                    this.reconcileActive();
                } else if (e.key === 'F9') {
                    e.preventDefault();
                    this.resetSelection();
                }
            });
        },

        resetSelection: function () {
            this._activeRow = null;
            document.getElementById('activeBillingNoRawat').value = '';
            document.getElementById('activeBillingBanner').innerText = 'Pilih kunjungan untuk melihat rincian tagihan';
            document.getElementById('billingItemsBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada item tagihan.</td></tr>';
            document.getElementById('btnPayBilling').disabled = true;
            document.getElementById('btnVoidBilling').disabled = true;
            document.getElementById('btnReconcileOrder').disabled = true;
            const btnPrint = document.getElementById('btnPrintFaktur');
            const btnSettle = document.getElementById('btnSettlePiutang');
            if (btnPrint) btnPrint.disabled = true;
            if (btnSettle) btnSettle.disabled = true;
        },

        loadQueue: function () {
            const tbody = document.getElementById('billingQueueBody');
            if (!tbody) return;

            const from = document.getElementById('billingFilterFrom')?.value || '';
            const to = document.getElementById('billingFilterTo')?.value || '';
            const sl = document.getElementById('billingFilterLanjut')?.value || '';
            const params = new URLSearchParams();
            if (from) params.set('from', from);
            if (to) params.set('to', to);
            if (sl) params.set('status_lanjut', sl);
            const qs = params.toString();

            this.apiFetch('/api/v1/kasir/queue' + (qs ? '?' + qs : ''))
                .then(res => res.json())
                .then(rows => {
                    if (!Array.isArray(rows) || rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Belum ada antrean kasir hari ini.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = '';
                    rows.forEach((r) => {
                        const tr = document.createElement('tr');
                        tr.style.cursor = 'pointer';
                        const badge = r.status_bayar === 'Sudah Bayar'
                            ? '<span class="badge bg-success">Lunas</span>'
                            : '<span class="badge bg-warning text-dark">Belum</span>';
                        tr.innerHTML = `
                            <td class="ps-2 fw-bold small">${this.escapeHtml(r.no_reg)}</td>
                            <td><div class="fw-semibold small">${this.escapeHtml(r.nm_pasien)}</div><small class="text-muted">${this.escapeHtml(r.no_rawat)}</small></td>
                            <td><small>${this.escapeHtml(r.png_jawab)}</small></td>
                            <td>${badge}</td>
                        `;
                        tr.addEventListener('click', () => this.selectVisit(r));
                        tbody.appendChild(tr);
                    });
                })
                .catch(() => this.showAlert('danger', 'Gagal memuat antrean kasir.'));
        },

        selectVisit: function (row) {
            this._activeRow = row;
            document.getElementById('activeBillingNoRawat').value = row.no_rawat;
            document.getElementById('activeBillingBanner').innerText = `${row.nm_pasien} | ${row.no_rawat} (${row.png_jawab})`;
            document.getElementById('btnPayBilling').disabled = false;
            document.getElementById('btnVoidBilling').disabled = false;
            document.getElementById('btnReconcileOrder').disabled = false;
            const btnPrint = document.getElementById('btnPrintFaktur');
            if (btnPrint) btnPrint.disabled = false;
            this.loadDetail(row.no_rawat);
        },

        printFaktur: function () {
            const nr = document.getElementById('activeBillingNoRawat')?.value || '';
            if (!nr) return;
            window.open('/kasir/faktur?no_rawat=' + encodeURIComponent(nr), '_blank');
        },

        settlePiutangActive: function () {
            const nr = document.getElementById('activeBillingNoRawat')?.value || '';
            if (!nr) return;
            this.apiFetch('/api/v1/kasir/settle-piutang', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: nr }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', this.escapeHtml(res.message || 'Proses pelunasan selesai.'));
                if (res.status === 'success') {
                    this.loadDetail(nr);
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses pelunasan piutang.'));
        },

        loadDetail: function (noRawat) {
            this.apiFetch('/api/v1/kasir/detail?no_rawat=' + encodeURIComponent(noRawat))
                .then(res => res.json())
                .then(data => {
                    const s = data.summary || {};
                    document.getElementById('sumTunai').innerText = 'Rp ' + Number(s['Tunai'] || 0).toLocaleString('id-ID');
                    document.getElementById('sumKapitasi').innerText = 'Rp ' + Number(s['Kapitasi'] || 0).toLocaleString('id-ID');
                    document.getElementById('sumNonKapitasi').innerText = 'Rp ' + Number(s['Non-Kapitasi'] || 0).toLocaleString('id-ID');
                    document.getElementById('sumPiutang').innerText = 'Rp ' + Number(s['Piutang'] || 0).toLocaleString('id-ID');
                    const btnSettle = document.getElementById('btnSettlePiutang');
                    if (btnSettle) btnSettle.disabled = Number(s['Piutang'] || 0) <= 0;

                    const tbody = document.getElementById('billingItemsBody');
                    const items = Array.isArray(data.items) ? data.items : [];
                    if (items.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada item tagihan di ledger.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = '';
                    items.forEach((i) => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${this.escapeHtml(i.item_name)}</td>
                            <td>${this.escapeHtml(i.category)}</td>
                            <td>${this.escapeHtml(i.qty)}</td>
                            <td><span class="badge bg-info text-dark">${this.escapeHtml(i.claim_type)}</span></td>
                            <td>Rp ${Number(i.total_amount).toLocaleString('id-ID')}</td>
                            <td><span class="badge bg-secondary">${this.escapeHtml(i.status)}</span></td>
                        `;
                        tbody.appendChild(tr);
                    });
                })
                .catch(() => this.showAlert('danger', 'Gagal memuat detail billing.'));
        },

        payActive: function () {
            const nr = document.getElementById('activeBillingNoRawat').value;
            if (!nr) {
                this.showAlert('warning', 'Pilih kunjungan pasien terlebih dahulu.');
                return;
            }
            this.apiFetch('/api/v1/kasir/pay', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: nr }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', this.escapeHtml(res.message));
                if (res.status === 'success') {
                    this.loadQueue();
                    this.loadDetail(nr);
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses pembayaran.'));
        },

        voidActive: function () {
            const nr = document.getElementById('activeBillingNoRawat').value;
            if (!nr) return;
            const reason = prompt('Masukkan alasan Void (wajib untuk audit trail):');
            if (!reason) return;

            this.apiFetch('/api/v1/kasir/void', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: nr, reason: reason }),
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const stamp = res.data && res.data.stamp ? ` — <strong>${this.escapeHtml(res.data.stamp)}</strong>` : '';
                    this.showAlert('warning', this.escapeHtml(res.message) + stamp);
                    this.loadQueue();
                    this.loadDetail(nr);
                } else {
                    this.showAlert('danger', this.escapeHtml(res.message));
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses void.'));
        },

        reconcileActive: function () {
            const nr = document.getElementById('activeBillingNoRawat').value;
            if (!nr) return;
            const reason = prompt('Alasan pembatalan order pending (Rekonsiliasi Harian):');
            if (!reason) return;

            this.apiFetch('/api/v1/kasir/reconcile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: nr, reason: reason }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'info' : 'danger', this.escapeHtml(res.message));
                if (res.status === 'success') {
                    this.loadDetail(nr);
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses rekonsiliasi.'));
        },

        submitPos: function () {
            const kode = document.getElementById('posKodeBrng').value.trim();
            const jml = parseFloat(document.getElementById('posJml').value || '1');
            const pm = document.getElementById('posPaymentMethod').value;
            if (!kode) {
                this.showAlert('warning', 'Masukkan kode barang terlebih dahulu.');
                return;
            }

            this.apiFetch('/api/v1/apotek-umum/pos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ payment_method: pm, items: [{ kode_brng: kode, jml: jml }] }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', this.escapeHtml(res.message));
                if (res.status === 'success') this.loadPosSales();
            })
            .catch(() => this.showAlert('danger', 'Gagal memproses POS Apotek Umum.'));
        },

        loadPosSales: function () {
            const tbody = document.getElementById('tbodyPosSales');
            if (!tbody) return;
            this.apiFetch('/api/v1/apotek-umum/sales')
                .then(res => res.json())
                .then(res => {
                    const rows = (res && res.data) ? res.data : [];
                    if (!rows.length) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">Belum ada transaksi POS hari ini.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = rows.map(s => `
                        <tr>
                            <td class="font-monospace">${this.escapeHtml(s.no_nota)}</td>
                            <td>${this.escapeHtml(s.tgl_jual)} ${this.escapeHtml(s.jam_jual)}</td>
                            <td>${this.escapeHtml(s.payment_method)}</td>
                            <td class="text-end">Rp ${this.escapeHtml(Number(s.total || 0).toLocaleString('id-ID'))}</td>
                            <td class="text-center"><a href="/apotek-umum/struk?no_nota=${encodeURIComponent(s.no_nota)}" target="_blank" class="btn btn-xs btn-outline-teal">Struk</a></td>
                        </tr>
                    `).join('');
                })
                .catch(() => {});
        }
    };

    document.addEventListener('DOMContentLoaded', () => BillingApp.init());
})(window, document);
