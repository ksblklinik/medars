(function (window, document) {
    'use strict';

    const KepegawaianApp = {
        _activeEntity: 'pegawai',

        init: function () {
            this.bindEvents();
            this.loadList('pegawai');
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
            const box = document.getElementById('sdmAlertContainer');
            if (!box) return;
            box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
        },

        bindEvents: function () {
            document.querySelectorAll('#sdmTabs button[data-entity]').forEach((btn) => {
                btn.addEventListener('shown.bs.tab', () => {
                    this._activeEntity = btn.getAttribute('data-entity') || 'pegawai';
                    this.loadList(this._activeEntity);
                });
            });

            document.getElementById('btnSdmSearch')?.addEventListener('click', () => this.loadList(this._activeEntity));
            document.getElementById('btnPrintSdm')?.addEventListener('click', () => {
                window.open(`/kepegawaian/cetak?entity=${encodeURIComponent(this._activeEntity)}`, '_blank');
            });

            document.getElementById('formPegawai')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.save('pegawai', {
                    nik: document.getElementById('pegNik').value,
                    nama: document.getElementById('pegNama').value,
                    jk: document.getElementById('pegJk').value,
                    jbtn: document.getElementById('pegJbtn').value,
                    departemen: document.getElementById('pegDept').value,
                    status: document.getElementById('pegStatus').value,
                });
            });

            document.getElementById('formPetugas')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.save('petugas', {
                    nip: document.getElementById('ptgNip').value,
                    nama: document.getElementById('ptgNama').value,
                    jk: document.getElementById('ptgJk').value,
                    jbtn: document.getElementById('ptgJbtn').value,
                    no_telp: document.getElementById('ptgTelp').value,
                    status: document.getElementById('ptgStatus').value,
                });
            });

            document.getElementById('formBerkasSdm')?.addEventListener('submit', (e) => {
                e.preventDefault();
                const fileInput = document.getElementById('berkasFile');
                const file = fileInput?.files?.[0];
                if (!file) return this.showAlert('danger', 'Pilih berkas terlebih dahulu.');
                const reader = new FileReader();
                reader.onload = () => {
                    this.save('sdm_berkas', {
                        nik: document.getElementById('berkasNik').value,
                        jenis_berkas: document.getElementById('berkasJenis').value,
                        tgl_kadaluarsa: document.getElementById('berkasExp').value,
                        nama_file: file.name,
                        mime_type: file.type || 'application/pdf',
                        file_size: file.size || 0,
                        content_base64: String(reader.result || ''),
                    });
                    document.getElementById('formBerkasSdm')?.reset();
                };
                reader.readAsDataURL(file);
            });
        },

        loadList: function (entity) {
            const q = document.getElementById('sdmSearchInput')?.value.trim() || '';
            this.apiFetch(`/api/v1/kepegawaian/${encodeURIComponent(entity)}?q=${encodeURIComponent(q)}`)
                .then(res => res.json())
                .then(res => this.renderTable(entity, (res && res.data) ? res.data : []))
                .catch(() => this.showAlert('danger', 'Gagal memuat data SDM.'));
        },

        save: function (entity, payload) {
            this.apiFetch(`/api/v1/kepegawaian/${encodeURIComponent(entity)}/save`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') this.loadList(entity);
            })
            .catch(() => this.showAlert('danger', 'Gagal menyimpan data SDM.'));
        },

        deleteRow: function (entity, keys) {
            this.apiFetch(`/api/v1/kepegawaian/${encodeURIComponent(entity)}/delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(keys),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success') this.loadList(entity);
            })
            .catch(() => this.showAlert('danger', 'Gagal menghapus data SDM.'));
        },

        renderTable: function (entity, rows) {
            const idMap = { pegawai: 'tbodyPegawai', petugas: 'tbodyPetugas', sdm_berkas: 'tbodyBerkasSdm' };
            const tbody = document.getElementById(idMap[entity] || 'tbodyPegawai');
            if (!tbody) return;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Belum ada data SDM.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            rows.forEach((r) => {
                const tr = document.createElement('tr');
                if (entity === 'pegawai') {
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.nik)}</td><td>${this.escapeHtml(r.nama)}</td><td>${this.escapeHtml(r.jbtn)}</td><td>${this.escapeHtml(r.departemen)}</td><td>${this.escapeHtml(r.status)}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                    tr.querySelector('.btn-del')?.addEventListener('click', () => this.deleteRow('pegawai', { nik: r.nik }));
                } else if (entity === 'sdm_berkas') {
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.nik)}</td><td><span class="badge bg-info text-dark">${this.escapeHtml(r.jenis_berkas)}</span></td><td>${this.escapeHtml(r.nama_file)}</td><td>${this.escapeHtml(r.tgl_kadaluarsa || '-')}</td><td>${this.escapeHtml(r.uploaded_at)}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                    tr.querySelector('.btn-del')?.addEventListener('click', () => this.deleteRow('sdm_berkas', { id: r.id }));
                } else {
                    tr.innerHTML = `<td class="font-monospace">${this.escapeHtml(r.nip)}</td><td>${this.escapeHtml(r.nama)}</td><td>${this.escapeHtml(r.jbtn)}</td><td>${this.escapeHtml(r.no_telp)}</td><td>${this.escapeHtml(r.status === '1' ? 'Aktif' : 'Nonaktif')}</td><td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-del">Hapus</button></td>`;
                    tr.querySelector('.btn-del')?.addEventListener('click', () => this.deleteRow('petugas', { nip: r.nip }));
                }
                tbody.appendChild(tr);
            });
        },
    };

    document.addEventListener('DOMContentLoaded', () => KepegawaianApp.init());
})(window, document);
