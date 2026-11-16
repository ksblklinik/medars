(function (window, document) {
    'use strict';

    const DokterRalan = {
        _icdTimer: null,
        _icdAbortController: null,
        _activePatient: null,

        init: function () {
            this.loadQueue();
            this.bindEvents();
            this.initDoctorSse();
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

        showAlert: function (type, message) {
            const container = document.getElementById('rmeAlertContainer');
            if (!container) return;
            container.innerHTML = `
                <div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small" role="alert">
                    ${message}
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

        initDoctorSse: function () {
            const ws = document.getElementById('dokterRalanWorkspace');
            const kdDokter = ws ? ws.getAttribute('data-dokter') : '';
            if (!kdDokter || !window.MedarsApp || !window.MedarsApp.sse) return;

            window.MedarsApp.sse.init('/api/stream?channel=' + encodeURIComponent('dokter_' + kdDokter));
            window.MedarsApp.sse.on('pasien_ralan_updated', () => {
                this.loadQueue();
            });
        },

        bindEvents: function () {
            const btnRefresh = document.getElementById('btnRefreshPoliQueue');
            if (btnRefresh) {
                btnRefresh.addEventListener('click', () => this.loadQueue());
            }

            const btnSaveSoap = document.getElementById('btnSaveSoapMain');
            if (btnSaveSoap) {
                btnSaveSoap.addEventListener('click', () => this.saveSoap());
            }

            const btnCall = document.getElementById('btnCallPatient');
            if (btnCall) {
                btnCall.addEventListener('click', () => this.callActivePatient());
            }

            const icdInput = document.getElementById('icdSearchInput');
            if (icdInput) {
                icdInput.addEventListener('input', (e) => {
                    const q = e.target.value.trim();
                    clearTimeout(this._icdTimer);
                    if (this._icdAbortController) {
                        this._icdAbortController.abort();
                    }
                    if (q.length < 2) {
                        const list = document.getElementById('icdSearchResults');
                        if (list) list.innerHTML = '';
                        return;
                    }
                    // Debounce 200ms + AbortController anti-lag
                    this._icdTimer = setTimeout(() => this.searchIcd10(q), 200);
                });
            }

            // Smart Peek Shield: tandai is_meta_dirty hanya jika field metadata benar-benar diubah
            document.querySelectorAll('.kia-meta-field').forEach((el) => {
                el.addEventListener('input', () => {
                    const flag = document.getElementById('soapIsMetaDirty');
                    const key = document.getElementById('soapMetaKey');
                    if (flag) flag.value = '1';
                    if (key) key.value = 'meta_kebidanan';
                });
            });

            const btnSavePrb = document.getElementById('btnSavePrb');
            if (btnSavePrb) {
                btnSavePrb.addEventListener('click', () => this.savePrb());
            }

            const btnSaveOdo = document.getElementById('btnSaveOdo');
            if (btnSaveOdo) {
                btnSaveOdo.addEventListener('click', () => this.saveOdontogram());
            }

            const btnSurat = document.getElementById('btnCreateSuratSakit');
            if (btnSurat) {
                btnSurat.addEventListener('click', () => this.createSuratSakit());
            }

            document.addEventListener('keydown', (e) => {
                if (e.key === 'F2') {
                    e.preventDefault();
                    this.saveSoap();
                }
            });
        },

        loadQueue: function () {
            const tbody = document.getElementById('poliQueueBody');
            if (!tbody) return;

            this.apiFetch('/api/v1/dokter-ralan/queue')
                .then(res => res.json())
                .then(rows => {
                    if (!Array.isArray(rows) || rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3 small">Belum ada pasien di antrean poli hari ini.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = '';
                    rows.forEach((row) => {
                        const tr = document.createElement('tr');
                        tr.style.cursor = 'pointer';
                        const badgeClass = row.stts === 'Sudah' ? 'bg-success' : 'bg-warning text-dark';

                        tr.innerHTML = `
                            <td class="ps-2 fw-bold">${this.escapeHtml(row.no_reg)}</td>
                            <td>
                                <div class="fw-semibold small">${this.escapeHtml(row.nm_pasien)}</div>
                                <small class="text-muted">${this.escapeHtml(row.no_rkm_medis)}</small>
                            </td>
                            <td><small>${this.escapeHtml(row.nm_poli || row.kd_poli)}</small></td>
                            <td><span class="badge ${badgeClass}">${this.escapeHtml(row.stts)}</span></td>
                        `;

                        tr.addEventListener('click', () => this.selectPatient(row));
                        tbody.appendChild(tr);
                    });
                })
                .catch(err => console.error('Gagal memuat antrean poli:', err));
        },

        selectPatient: function (row) {
            this._activePatient = row;
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
            const timeStr = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

            const form = document.getElementById('soapForm');
            if (form) form.reset();

            document.getElementById('soapNoRawat').value = row.no_rawat;
            document.getElementById('soapNoRkmMedis').value = row.no_rkm_medis;
            document.getElementById('soapTglPerawatan').value = todayStr;
            document.getElementById('soapJamRawat').value = timeStr;
            document.getElementById('soapIsMetaDirty').value = '0';
            document.getElementById('soapMetaKey').value = '';
            const icdList = document.getElementById('icdSearchResults');
            if (icdList) icdList.innerHTML = '';

            const banner = document.getElementById('activePatientBanner');
            if (banner) {
                banner.innerText = `${row.no_reg} | ${row.nm_pasien} (RM: ${row.no_rkm_medis}) — ${row.no_rawat}`;
            }

            document.getElementById('btnSaveSoapMain').disabled = false;
            document.getElementById('btnCallPatient').disabled = false;

            this.loadSmartPeekHistory(row.no_rkm_medis);
            this.loadOdontogram(row.no_rkm_medis);
        },

        loadSmartPeekHistory: function (noRkmMedis) {
            const panel = document.getElementById('smartPeekPanel');
            const badge = document.getElementById('smartPeekCount');
            if (!panel) return;

            panel.innerHTML = '<div class="text-center text-muted small py-3"><i class="fa fa-spinner fa-spin"></i> Mengambil riwayat (Next + Legacy)...</div>';

            this.apiFetch('/api/v1/dokter-ralan/history?no_rkm_medis=' + encodeURIComponent(noRkmMedis))
                .then(res => res.json())
                .then(rows => {
                    if (!Array.isArray(rows) || rows.length === 0) {
                        if (badge) badge.innerText = '0 Kunjungan';
                        panel.innerHTML = '<div class="text-center text-muted small py-3">Belum ada riwayat pemeriksaan tercatat.</div>';
                        return;
                    }

                    if (badge) badge.innerText = rows.length + ' Kunjungan';
                    panel.innerHTML = '';

                    rows.forEach((item) => {
                        const srcBadge = item.source === 'legacy'
                            ? '<span class="badge bg-secondary">Legacy</span>'
                            : '<span class="badge bg-info text-dark">Next</span>';

                        const card = document.createElement('div');
                        card.className = 'border rounded p-2 mb-2 bg-light';
                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small text-medical-teal">${this.escapeHtml(item.tgl_perawatan)} ${this.escapeHtml(item.jam_rawat)}</strong>
                                ${srcBadge}
                            </div>
                            <div class="small text-muted mb-1">${this.escapeHtml(item.nm_poli || '')} &bull; ${this.escapeHtml(item.nm_dokter || item.nip)}</div>
                            <div class="small"><strong>S:</strong> ${this.escapeHtml(item.keluhan)}</div>
                            <div class="small"><strong>O:</strong> ${this.escapeHtml(item.pemeriksaan)} (TD: ${this.escapeHtml(item.tensi)})</div>
                            <div class="small"><strong>A:</strong> ${this.escapeHtml(item.penilaian)}</div>
                            <div class="small"><strong>P:</strong> ${this.escapeHtml(item.rtl)}</div>
                        `;
                        panel.appendChild(card);
                    });
                })
                .catch(() => {
                    panel.innerHTML = '<div class="text-center text-danger small py-3">Gagal memuat riwayat medis.</div>';
                });
        },

        loadOdontogram: function (noRkmMedis) {
            const list = document.getElementById('odontogramHistoryList');
            if (!list) return;

            this.apiFetch('/api/v1/dokter-ralan/odontogram?no_rkm_medis=' + encodeURIComponent(noRkmMedis))
                .then(res => res.json())
                .then(rows => {
                    if (!Array.isArray(rows) || rows.length === 0) {
                        list.innerHTML = '<div class="list-group-item text-muted">Belum ada catatan odontogram.</div>';
                        return;
                    }
                    list.innerHTML = '';
                    rows.forEach((item) => {
                        const div = document.createElement('div');
                        div.className = 'list-group-item d-flex justify-content-between align-items-center py-1';
                        div.innerHTML = `
                            <span>Gigi <strong>${this.escapeHtml(item.nomor_gigi)}</strong>: <span class="badge bg-info text-dark">${this.escapeHtml(item.kondisi)}</span> (${this.escapeHtml(item.tgl_pemeriksaan)})</span>
                            <button type="button" class="btn btn-xs btn-outline-danger btn-del-odo" title="Hapus"><i class="fa fa-trash"></i></button>
                        `;
                        const delBtn = div.querySelector('.btn-del-odo');
                        if (delBtn) {
                            delBtn.addEventListener('click', () => this.deleteOdontogram(item.id, noRkmMedis));
                        }
                        list.appendChild(div);
                    });
                })
                .catch(() => {
                    list.innerHTML = '<div class="list-group-item text-danger">Gagal memuat odontogram.</div>';
                });
        },

        deleteOdontogram: function (id, noRkmMedis) {
            this.apiFetch('/api/v1/dokter-ralan/odontogram/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', this.escapeHtml(res.message));
                if (res.status === 'success') {
                    this.loadOdontogram(noRkmMedis);
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat menghapus odontogram.'));
        },

        saveSoap: function () {
            const noRawat = document.getElementById('soapNoRawat').value;
            if (!noRawat) return;

            const form = document.getElementById('soapForm');
            const fd = new FormData(form);
            const payload = Object.fromEntries(fd.entries());
            payload.is_meta_dirty = document.getElementById('soapIsMetaDirty').value === '1';

            if (payload.is_meta_dirty && payload.meta_key === 'meta_kebidanan') {
                payload.meta_value = JSON.stringify({
                    gravida: document.getElementById('kiaG').value,
                    para: document.getElementById('kiaP').value,
                    abortus: document.getElementById('kiaA').value,
                    djj: document.getElementById('kiaDjj').value,
                    tfu: document.getElementById('kiaTfu').value,
                });
            } else {
                payload.meta_key = '';
                payload.meta_value = '';
            }

            this.apiFetch('/api/v1/dokter-ralan/soap', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    this.showAlert('success', this.escapeHtml(res.message));
                    document.getElementById('soapIsMetaDirty').value = '0';
                    this.loadQueue();
                    if (this._activePatient) {
                        this.loadSmartPeekHistory(this._activePatient.no_rkm_medis);
                    }
                } else {
                    this.showAlert('danger', this.escapeHtml(res.message));
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat menyimpan SOAP.'));
        },

        searchIcd10: function (query) {
            this._icdAbortController = new AbortController();
            const list = document.getElementById('icdSearchResults');
            if (!list) return;

            this.apiFetch('/api/v1/dokter-ralan/icd10/search?q=' + encodeURIComponent(query), {
                signal: this._icdAbortController.signal,
            })
            .then(res => res.json())
            .then(rows => {
                list.innerHTML = '';
                if (!Array.isArray(rows) || rows.length === 0) {
                    list.innerHTML = '<div class="list-group-item text-muted">Tidak ditemukan kode ICD-10.</div>';
                    return;
                }
                rows.forEach((item) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action';
                    const ref = item.referral_suggestion
                        ? `<div class="text-warning-emphasis small mt-1"><i class="fa fa-circle-info"></i> ${this.escapeHtml(item.referral_suggestion)}</div>`
                        : '';
                    btn.innerHTML = `
                        <div class="d-flex justify-content-between">
                            <strong>${this.escapeHtml(item.kd_penyakit)} - ${this.escapeHtml(item.nm_indonesia || item.nm_penyakit)}</strong>
                            <span class="badge bg-secondary">SKDI ${this.escapeHtml(item.skdi_level || '4A')}</span>
                        </div>
                        ${ref}
                    `;
                    btn.addEventListener('click', () => {
                        const penilaian = document.getElementById('soapPenilaian');
                        if (penilaian) {
                            penilaian.value = `${item.kd_penyakit} - ${item.nm_indonesia || item.nm_penyakit}`;
                        }
                        if (item.referral_suggestion) {
                            const rtl = document.getElementById('soapRtl');
                            if (rtl && !rtl.value.includes('SKDI')) {
                                rtl.value = (rtl.value ? rtl.value + '\n' : '') + item.referral_suggestion;
                            }
                        }

                        const noRawat = document.getElementById('soapNoRawat').value;
                        if (noRawat) {
                            this.apiFetch('/api/v1/dokter-ralan/diagnosa', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    no_rawat: noRawat,
                                    kd_penyakit: item.kd_penyakit,
                                    status: 'Ralan',
                                    prioritas: 1,
                                }),
                            })
                            .then(res => res.json())
                            .then(res => {
                                this.showAlert(
                                    res.status === 'success' ? 'success' : 'warning',
                                    res.status === 'success' ? this.escapeHtml(res.message) : this.escapeHtml(res.message)
                                );
                            });
                        } else {
                            this.showAlert('info', `Diagnosa <strong>${this.escapeHtml(item.kd_penyakit)}</strong> dipilih.`);
                        }
                    });
                    list.appendChild(btn);
                });
            })
            .catch(err => {
                if (err.name !== 'AbortError') console.error(err);
            });
        },

        savePrb: function () {
            const noRawat = document.getElementById('soapNoRawat').value;
            if (!noRawat) return;

            const payload = {
                no_rawat: noRawat,
                tgl_perawatan: document.getElementById('soapTglPerawatan').value,
                jam_rawat: document.getElementById('soapJamRawat').value,
                diagnosa_kronis: document.getElementById('prbDiagnosa').value,
                items: [{
                    nama_obat: document.getElementById('prbObat').value,
                    jumlah: parseInt(document.getElementById('prbJumlah').value || '30', 10),
                    aturan_pakai: document.getElementById('prbAturan').value,
                }],
            };

            this.apiFetch('/api/v1/dokter-ralan/prb', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success' && res.data) {
                    // Selaraskan triplet & teks RTL form SOAP utama agar tidak menimpa atau membuat baris duplikat
                    document.getElementById('soapTglPerawatan').value = res.data.tgl_perawatan;
                    document.getElementById('soapJamRawat').value = res.data.jam_rawat;
                    if (res.data.rtl) {
                        document.getElementById('soapRtl').value = res.data.rtl;
                    }
                    this.showAlert('success', this.escapeHtml(res.message));
                } else {
                    this.showAlert('danger', this.escapeHtml(res.message));
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat menyimpan SOAP.'));
        },

        saveOdontogram: function () {
            const noRawat = document.getElementById('soapNoRawat').value;
            const noRm = document.getElementById('soapNoRkmMedis').value;
            if (!noRawat || !noRm) return;

            this.apiFetch('/api/v1/dokter-ralan/odontogram/save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rawat: noRawat,
                    no_rkm_medis: noRm,
                    nomor_gigi: document.getElementById('odoGigi').value,
                    kondisi: document.getElementById('odoKondisi').value,
                }),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', this.escapeHtml(res.message));
                if (res.status === 'success') {
                    this.loadOdontogram(noRm);
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat menyimpan odontogram.'));
        },

        createSuratSakit: function () {
            const noRawat = document.getElementById('soapNoRawat').value;
            if (!noRawat) return;

            this.apiFetch('/api/v1/dokter-ralan/surat-sakit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    no_rawat: noRawat,
                    tanggal_awal: document.getElementById('suratTglAwal').value,
                    tanggal_akhir: document.getElementById('suratTglAkhir').value,
                }),
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    this.showAlert('success', `Surat Sakit <strong>${this.escapeHtml(res.data.no_surat)}</strong> (${this.escapeHtml(res.data.lama_sakit)}) berhasil diterbitkan.`);
                } else {
                    this.showAlert('danger', this.escapeHtml(res.message));
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat menyimpan SOAP.'));
        },

        callActivePatient: function () {
            if (!this._activePatient) return;

            this.apiFetch('/api/v1/queue/call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rawat: this._activePatient.no_rawat }),
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    this.showAlert('info', `Memanggil antrean <strong>${this.escapeHtml(this._activePatient.no_reg)}</strong> ke Display TV.`);
                }
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server saat memanggil antrean TV.'));
        }
    };

    document.addEventListener('DOMContentLoaded', () => DokterRalan.init());
})(window, document);
