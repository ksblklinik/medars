(function (window, document) {
    'use strict';

    const ProfilApp = {
        init: function () {
            document.getElementById('formUpdateProfil')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.post('/api/v1/profil/update', {
                    fullname: document.getElementById('profilFullname').value,
                    email: document.getElementById('profilEmail').value,
                });
            });

            document.getElementById('formChangePassword')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.post('/api/v1/profil/password', {
                    current_password: document.getElementById('pwdCurrent').value,
                    new_password: document.getElementById('pwdNew').value,
                    confirm_password: document.getElementById('pwdConfirm').value,
                }, () => document.getElementById('formChangePassword').reset());
            });
        },

        escapeHtml: function (str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        },

        apiFetch: function (url, options) {
            return (window.MedarsApp && typeof window.MedarsApp.apiFetch === 'function')
                ? window.MedarsApp.apiFetch(url, options)
                : fetch(url, options);
        },

        showAlert: function (type, msg) {
            const box = document.getElementById('profilAlertContainer');
            if (box) {
                box.innerHTML = `<div class="alert alert-${this.escapeHtml(type)} alert-dismissible fade show py-2 small">${this.escapeHtml(msg)}<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button></div>`;
            }
        },

        post: function (url, payload, onOk) {
            this.apiFetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
            .then(res => res.json())
            .then(res => {
                this.showAlert(res.status === 'success' ? 'success' : 'danger', res.message || 'Selesai.');
                if (res.status === 'success' && typeof onOk === 'function') onOk();
            })
            .catch(() => this.showAlert('danger', 'Gagal menghubungi server profil.'));
        },
    };

    document.addEventListener('DOMContentLoaded', () => ProfilApp.init());
})(window, document);
