(function (window, document) {
    'use strict';

    const PharmacyApp = {
        _searchTimer: null,
        _searchAbort: null,
        _activeResep: null,

        init: function () {
            this.loadQueue();
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
            const c = document.getElementById('pharmacyAlertContainer');
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
            const btnRef = document.getElementById('btnRefreshResepQueue');
            if (btnRef) btnRef.addEventListener('click', () => this.loadQueue());

            const btnDisp = document.getElementById('btnDispenseResep');
            if (btnDisp) btnDisp.addEventListener('click', () => this.dispenseActiveResep());

            const btnDel = document.getElementById('btnDeleteResep');
            if (btnDel) btnDel.addEventListener('click', () => this.deleteActiveResep());

            const searchInput = document.getElementById('obatSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    const q = searchInput.value.trim();
                    clearTimeout(this._searchTimer);
                    if (this._searchAbort) this._searchAbort.abort();
                    if (q === '') {
                        document.getElementById('obatSearchBody').innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2">Ketik kata kunci obat untuk melihat harga & stok depo.</td></tr>';
                        return;
                    }
                    // Debounce 200ms tanpa batasan panjang karakter minimal (mendukung B6, Zn, CTM)
                    this._searchTimer = setTimeout(() => this.searchObat(q), 200);
                });
            }
        },

        loadQueue: function () {
            const tbody = document.getElementById('resepQueueBody');
            if (!tbody) return;

            this.apiFetch('/api/v1/farmasi/queue')
                .then(res => res.json())
                .then(rows => {
                    if (!Array.isArray(rows) || rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Belum ada E-Resep masuk hari ini.</td></tr>';
                        return;
                    }
                    tbody.innerHTML = '';
                    rows.forEach((r) => {
                        const tr = document.createElement('tr');
                        tr.style.cursor = 'pointer';
                        const badge = r.is_dispensed
                            ? '<span class="badge bg-success">Diserahkan</span>'
                            : '<span class="badge bg-warning text-dark">Menunggu</span>';
                        tr.innerHTML = `
                            <td class="ps-2 fw-bold small">${this.escapeHtml(r.no_resep)}</td>
                            <td><div class="fw-semibold small">${this.escapeHtml(r.nm_pasien || r.no_rawat)}</div><small class="text-muted">${this.escapeHtml(r.nm_dokter || r.kd_dokter)}</small></td>
                            <td><small>${this.escapeHtml(r.png_jawab || r.kd_pj)}</small></td>
                            <td>${badge}</td>
                        `;
                        tr.addEventListener('click', () => this.selectResep(r));
                        tbody.appendChild(tr);
                    });
                })
                .catch(() => this.showAlert('danger', 'Gagal memuat antrean E-Resep.'));
        },

        selectResep: function (row) {
            this._activeResep = row;
            document.getElementById('activeNoResep').value = row.no_resep;
            document.getElementById('activeResepBanner').innerText = `${row.no_resep} | ${row.nm_pasien || row.no_rawat} (${row.png_jawab || row.kd_pj})`;

            if (row.is_bpjs) {
                document.getElementById('dispenseJenisKlaim').value = 'Kapitasi';
                document.getElementById('toggleSearchBpjs').checked = true;
            } else {
                document.getElementById('dispenseJenisKlaim').value = 'Tunai';
                document.getElementById('toggleSearchBpjs').checked = false;
            }

            document.getElementById('btnDispenseResep').disabled = !!row.is_dispensed;
            document.getElementById('btnDeleteResep').disabled = !!row.is_dispensed;
        },

        searchObat: function (q) {
            this._searchAbort = new AbortController();
            const tbody = document.getElementById('obatSearchBody');
            const isBpjs = document.getElementById('toggleSearchBpjs').checked ? '1' : '0';
            const depo = document.getElementById('dispenseDepo').value;

            this.apiFetch(`/api/v1/farmasi/obat/search?q=${encodeURIComponent(q)}&is_bpjs=${isBpjs}&depo=${encodeURIComponent(depo)}`, {
                signal: this._searchAbort.signal,
            })
            .then(res => res.json())
            .then(items => {
                if (!Array.isArray(items) || items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-2">Obat tidak ditemukan.</td></tr>';
                    return;
                }
                tbody.innerHTML = '';
                items.forEach((item) => {
                    const opts = Array.isArray(item.claim_options) ? item.claim_options : [];
                    if (opts.length === 0) {
                        const tr = document.createElement('tr');
                        tr.setAttribute('data-selected_klaim', 'Tunai');
                        tr.innerHTML = `
                            <td>${this.escapeHtml(item.kode_brng)}</td>
                            <td>${this.escapeHtml(item.nama_brng)}</td>
                            <td>${this.escapeHtml(item.stok_depo)}</td>
                            <td>Rp ${Number(item.harga_tunai || 0).toLocaleString('id-ID')}</td>
                        `;
                        tbody.appendChild(tr);
                    } else {
                        opts.forEach((opt) => {
                            const tr = document.createElement('tr');
                            tr.setAttribute('data-selected_klaim', opt.claim_type);
                            tr.innerHTML = `
                                <td>${this.escapeHtml(item.kode_brng)}</td>
                                <td>${this.escapeHtml(item.nama_brng)}</td>
                                <td>${this.escapeHtml(item.stok_depo)}</td>
                                <td><span class="badge bg-info text-dark">${this.escapeHtml(opt.claim_type)}</span> Rp ${Number(opt.price).toLocaleString('id-ID')}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }
                });
            })
            .catch(err => {
                if (err.name !== 'AbortError') console.error(err);
            });
        },

        dispenseActiveResep: function () {
            const noResep = document.getElementById('activeNoResep').value;
            if (!noResep) {
                this.showAlert('warning', 'Pilih E-Resep terlebih dahulu.');
                return;
            }

            this.apiFetch('/api/v1/farmasi/resep/dispense', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_resep: noResep,
                    kd_bangsal: document.getElementById('dispenseDepo').value,
                    jenis_klaim: document.getElementById('dispenseJenisKlaim').value,
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', this.escapeHtml(res.message));
                if (res.status === 'success') {
                    this.loadQueue();
                    document.getElementById('btnDispenseResep').disabled = true;
                    document.getElementById('btnDeleteResep').disabled = true;
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat validasi resep.'));
        },

        deleteActiveResep: function () {
            const noResep = document.getElementById('activeNoResep').value;
            if (!noResep) return;
            if (!confirm('Hapus E-Resep ' + noResep + '?')) return;

            this.apiFetch('/api/v1/farmasi/resep/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_resep: noResep }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', this.escapeHtml(res.message));
                if (res.status === 'success') {
                    document.getElementById('activeNoResep').value = '';
                    this.loadQueue();
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat menghapus resep.'));
        }
    };

    document.addEventListener('DOMContentLoaded', () => PharmacyApp.init());
})(window, document);
