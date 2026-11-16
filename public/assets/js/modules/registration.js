/**
 * registration.js — Client Module Pendaftaran Rawat Jalan (ksm-next)
 * Paritas visual & ergonomi 100% dengan ksmlite
 * - Hotkeys: F2 (Daftarkan), F4 (Cari Pasien), F9 (Kosongkan)
 * - Single-Host Modal (#appModalHost)
 * - Live Today Registrations Table
 */

(function (window, document) {
    'use strict';

    const RegistrationModule = {
        init: function () {
            this.bindEvents();
            this.loadTodayRegistrations();
        },

        bindEvents: function () {
            const btnCari = document.getElementById('btnCariPasien');
            if (btnCari) {
                btnCari.addEventListener('click', () => this.openSearchPatientModal());
            }

            const inputRm = document.getElementById('regNoRkmMedis');
            if (inputRm) {
                inputRm.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        this.openSearchPatientModal(inputRm.value);
                    }
                });
            }

            const selectPoli = document.getElementById('regKdPoli');
            if (selectPoli) {
                selectPoli.addEventListener('change', () => this.updateRegistrationFee());
            }

            const form = document.getElementById('regForm');
            if (form) {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.submitInSiteRegistration();
                });
            }

            const btnCheckIn = document.getElementById('btnCheckInMobileJkn');
            if (btnCheckIn) {
                btnCheckIn.addEventListener('click', () => this.submitMobileJknCheckIn());
            }

            const btnReset = document.getElementById('btnResetForm');
            if (btnReset) {
                btnReset.addEventListener('click', () => this.resetForm());
            }

            const btnRefresh = document.getElementById('btnRefreshList');
            if (btnRefresh) {
                btnRefresh.addEventListener('click', () => this.loadTodayRegistrations());
            }
        },

        openSearchPatientModal: function (initialQuery = '') {
            if (!window.AppModal) return;

            const modalHtml = `
                <div class="p-1">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                        <input type="text" id="modalSearchQuery" class="form-control" placeholder="Ketik No. RM, Nama, NIK, atau No. BPJS..." value="${initialQuery}">
                    </div>
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover table-sm small" id="tableModalPatients">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>No. RM</th>
                                    <th>Nama Pasien</th>
                                    <th>NIK / BPJS</th>
                                    <th>Tgl. Lahir</th>
                                    <th>Alamat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyModalPatients">
                                <tr><td colspan="6" class="text-center text-muted py-3">Ketik minimal 1 karakter untuk mencari pasien.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;

            window.AppModal.open('Pencarian Pasien (F4)', modalHtml, (modalBody) => {
                const searchInput = modalBody.querySelector('#modalSearchQuery');
                const tbody = modalBody.querySelector('#tbodyModalPatients');
                let debounceTimeout = null;

                const doSearch = (q) => {
                    if (!q || q.trim() === '') return;
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin me-1"></i> Mencari...</td></tr>';

                    fetch('/api/v1/registration/patients/search?q=' + encodeURIComponent(q))
                        .then(res => res.json())
                        .then(patients => {
                            if (!patients || patients.length === 0) {
                                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Pasien tidak ditemukan.</td></tr>';
                                return;
                            }

                            tbody.innerHTML = patients.map(p => `
                                <tr class="patient-row cursor-pointer" data-json='${JSON.stringify(p).replace(/'/g, "&#39;")}'>
                                    <td class="fw-bold font-monospace text-teal">${p.no_rkm_medis}</td>
                                    <td>${p.nm_pasien}</td>
                                    <td>${p.nik || p.no_peserta || '-'}</td>
                                    <td>${p.tgl_lahir}</td>
                                    <td class="text-truncate" style="max-width: 150px;">${p.alamat || '-'}</td>
                                    <td>
                                        <button type="button" class="btn btn-teal btn-sm py-0 px-2 btn-pilih-pasien">Pilih</button>
                                    </td>
                                </tr>
                            `).join('');

                            tbody.querySelectorAll('.patient-row').forEach(row => {
                                row.addEventListener('click', () => {
                                    const pData = JSON.parse(row.getAttribute('data-json'));
                                    this.selectPatient(pData);
                                    window.AppModal.close();
                                });
                            });
                        })
                        .catch(err => {
                            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-3">Gagal mengambil data pasien.</td></tr>';
                        });
                };

                if (searchInput) {
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(debounceTimeout);
                        debounceTimeout = setTimeout(() => doSearch(e.target.value), 300);
                    });

                    if (initialQuery.trim() !== '') {
                        doSearch(initialQuery);
                    }
                }
            });
        },

        selectPatient: function (p) {
            const inputRm = document.getElementById('regNoRkmMedis');
            const infoNm = document.getElementById('infoNmPasien');
            const infoNik = document.getElementById('infoNikPeserta');
            const infoUmurJk = document.getElementById('infoUmurJk');
            const infoAlamat = document.getElementById('infoAlamat');

            if (inputRm) inputRm.value = p.no_rkm_medis;
            if (infoNm) infoNm.innerText = p.nm_pasien;
            if (infoNik) infoNik.innerText = (p.nik || '-') + ' / ' + (p.no_peserta || '-');
            if (infoUmurJk) infoUmurJk.innerText = (p.tgl_lahir || '-') + ' (' + (p.jk || '-') + ')';
            if (infoAlamat) infoAlamat.innerText = p.alamat || '-';

            // Auto-select BPJS if patient has no_peserta
            if (p.no_peserta && p.no_peserta.trim() !== '') {
                const selectPj = document.getElementById('regKdPj');
                if (selectPj) {
                    const bpjOption = Array.from(selectPj.options).find(opt => opt.value === 'BPJ');
                    if (bpjOption) selectPj.value = 'BPJ';
                }
            }

            this.updateRegistrationFee();
        },

        updateRegistrationFee: function () {
            const selectPoli = document.getElementById('regKdPoli');
            const displayFee = document.getElementById('displayBiayaReg');
            if (!selectPoli || !displayFee) return;

            const selectedOption = selectPoli.options[selectPoli.selectedIndex];
            if (!selectedOption || !selectedOption.value) {
                displayFee.innerText = 'Rp 0';
                return;
            }

            const fee = parseFloat(selectedOption.getAttribute('data-tarif-baru') || '0');
            displayFee.innerText = 'Rp ' + fee.toLocaleString('id-ID');
        },

        submitInSiteRegistration: function () {
            const form = document.getElementById('regForm');
            if (!form) return;

            const formData = new FormData(form);
            const payload = Object.fromEntries(formData.entries());

            this.showAlert('info', '<i class="fa fa-spinner fa-spin me-1"></i> Memproses pendaftaran...');

            fetch('/api/v1/registration/insite', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    this.showAlert('success', `<strong>Sukses:</strong> Pendaftaran tersimpan! No. Antrean: <strong>${res.data.no_reg}</strong> | No. Rawat: <strong>${res.data.no_rawat}</strong>`);
                    this.resetForm();
                    this.loadTodayRegistrations();
                } else {
                    this.showAlert('danger', `<strong>Gagal:</strong> ${res.message}`);
                }
            })
            .catch(err => {
                this.showAlert('danger', 'Terjadi kesalahan sistem saat menghubungi server.');
            });
        },

        submitMobileJknCheckIn: function () {
            const inputRm = document.getElementById('regNoRkmMedis');
            if (!inputRm || inputRm.value.trim() === '') {
                this.showAlert('warning', 'Pilih atau masukkan nomor rekam medis pasien terlebih dahulu.');
                return;
            }

            const noRm = inputRm.value.trim();
            this.showAlert('info', '<i class="fa fa-spinner fa-spin me-1"></i> Memvalidasi check-in Mobile JKN...');

            fetch('/api/v1/registration/checkin', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rkm_medis: noRm })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    this.showAlert('success', `<strong>Check-in Berhasil:</strong> No. Antrean: <strong>${res.data.no_reg}</strong> | No. Rawat: <strong>${res.data.no_rawat}</strong>`);
                    this.resetForm();
                    this.loadTodayRegistrations();
                } else {
                    this.showAlert('danger', `<strong>Gagal Check-in:</strong> ${res.message}`);
                }
            })
            .catch(err => {
                this.showAlert('danger', 'Gagal memproses check-in Mobile JKN.');
            });
        },

        loadTodayRegistrations: function () {
            const tbody = document.getElementById('tbodyRegistrations');
            if (!tbody) return;

            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3"><i class="fa fa-spinner fa-spin me-1"></i> Memuat...</td></tr>';

            fetch('/api/v1/registration/today')
                .then(res => res.json())
                .then(rows => {
                    if (!rows || rows.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Belum ada pasien terdaftar untuk hari ini.</td></tr>';
                        return;
                    }

                    tbody.innerHTML = rows.map(r => {
                        const statusClass = this.getStatusBadgeClass(r.stts);
                        const bayarBadge = r.status_bayar === 'Sudah Bayar' 
                            ? '<span class="badge bg-success-subtle text-success">Lunas</span>'
                            : '<span class="badge bg-warning-subtle text-warning-emphasis">Belum Bayar</span>';

                        return `
                            <tr>
                                <td class="text-center">
                                    <span class="badge bg-medical-teal font-monospace fs-6">${r.no_reg}</span>
                                </td>
                                <td>
                                    <span class="font-monospace text-muted d-block small">${r.no_rawat}</span>
                                    ${bayarBadge}
                                </td>
                                <td>
                                    <span class="fw-bold text-dark d-block">${r.nm_pasien || '-'}</span>
                                    <span class="text-muted small font-monospace">${r.no_rkm_medis}</span>
                                </td>
                                <td>
                                    <span class="d-block fw-medium">${r.nm_poli || '-'}</span>
                                    <span class="text-muted small">${r.nm_dokter || '-'}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge ${statusClass}">${r.stts}</span>
                                </td>
                            </tr>
                        `;
                    }).join('');
                })
                .catch(err => {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Gagal memuat daftar kunjungan.</td></tr>';
                });
        },

        getStatusBadgeClass: function (stts) {
            switch (stts) {
                case 'Sudah':
                case 'Selesai': return 'bg-success';
                case 'Awal': return 'bg-info text-dark';
                case 'Pending': return 'bg-warning text-dark';
                case 'Batal':
                case 'Skip': return 'bg-danger';
                default: return 'bg-secondary';
            }
        },

        showAlert: function (type, message) {
            const alertBox = document.getElementById('alertRegistration');
            if (!alertBox) return;

            alertBox.className = `alert alert-${type} py-2 px-3 small`;
            alertBox.innerHTML = message;
            alertBox.classList.remove('d-none');
        },

        resetForm: function () {
            const form = document.getElementById('regForm');
            if (form) form.reset();

            const infoNm = document.getElementById('infoNmPasien');
            const infoNik = document.getElementById('infoNikPeserta');
            const infoUmurJk = document.getElementById('infoUmurJk');
            const infoAlamat = document.getElementById('infoAlamat');
            const displayFee = document.getElementById('displayBiayaReg');

            if (infoNm) infoNm.innerText = '-';
            if (infoNik) infoNik.innerText = '-';
            if (infoUmurJk) infoUmurJk.innerText = '-';
            if (infoAlamat) infoAlamat.innerText = '-';
            if (displayFee) displayFee.innerText = 'Rp 0';

            const alertBox = document.getElementById('alertRegistration');
            if (alertBox) alertBox.classList.add('d-none');
        }
    };

    // Auto-init saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => RegistrationModule.init());
    } else {
        RegistrationModule.init();
    }

    window.RegistrationModule = RegistrationModule;
})(window, document);
