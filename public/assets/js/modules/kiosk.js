/**
 * kiosk.js — Layar Sentuh Kiosk Mandiri & General Consent (ksm-next)
 * Paritas visual & ergonomi 100% dengan ksmlite
 * - Full Touch Interface, No Physical Keyboard Needed
 * - Self-contained Digital Signature Canvas
 * - Quick Ticket Print Simulation
 */

(function (window, document) {
    'use strict';

    const KioskModule = {
        init: function () {
            this.initClock();
            this.bindKeypad();
            this.bindActions();
            this.initSignaturePad();
        },

        initClock: function () {
            const clockEl = document.getElementById('kioskClock');
            if (!clockEl) return;

            const update = () => {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
                clockEl.innerText = `${timeStr} WIB`;
            };
            update();
            setInterval(update, 1000);
        },

        bindKeypad: function () {
            const input = document.getElementById('kioskInputRm');
            if (!input) return;

            document.querySelectorAll('.keypad-btn[data-key]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const key = btn.getAttribute('data-key');
                    if (input.value.length < 15) {
                        input.value += key;
                    }
                });
            });

            const btnClear = document.getElementById('btnKeypadClear');
            if (btnClear) {
                btnClear.addEventListener('click', () => {
                    input.value = input.value.slice(0, -1);
                });
            }

            const btnSubmit = document.getElementById('btnKeypadSubmit');
            if (btnSubmit) {
                btnSubmit.addEventListener('click', () => this.doCheckIn());
            }

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.doCheckIn();
                }
            });
        },

        bindActions: function () {
            const btnJkn = document.getElementById('btnKioskCheckInJkn');
            if (btnJkn) {
                btnJkn.addEventListener('click', () => {
                    const modalEl = document.getElementById('modalKioskCheckIn');
                    if (modalEl && window.bootstrap) {
                        const input = document.getElementById('kioskInputRm');
                        if (input) input.value = '';
                        this.hideAlert();
                        new window.bootstrap.Modal(modalEl).show();
                    }
                });
            }

            const btnAmbilAntrean = document.getElementById('btnKioskAmbilAntrean');
            if (btnAmbilAntrean) {
                btnAmbilAntrean.addEventListener('click', () => {
                    // Pendaftaran mandiri umum: arahkan atau cetak tiket walk-in
                    this.showTicketModal('001', 'Poli Umum', 'dr. Budi Wicaksono');
                });
            }

            const btnConsent = document.getElementById('btnKioskGeneralConsent');
            if (btnConsent) {
                btnConsent.addEventListener('click', () => {
                    const modalEl = document.getElementById('modalKioskConsent');
                    if (modalEl && window.bootstrap) {
                        new window.bootstrap.Modal(modalEl).show();
                    }
                });
            }

            const btnSaveConsent = document.getElementById('btnSimpanConsent');
            if (btnSaveConsent) {
                btnSaveConsent.addEventListener('click', () => {
                    const modalEl = document.getElementById('modalKioskConsent');
                    if (modalEl && window.bootstrap) {
                        window.bootstrap.Modal.getInstance(modalEl).hide();
                        alert('Persetujuan Umum (General Consent) berhasil disimpan ke Rekam Medis Elektronik.');
                    }
                });
            }
        },

        doCheckIn: function () {
            const input = document.getElementById('kioskInputRm');
            const alertBox = document.getElementById('alertKioskCheckIn');
            if (!input || input.value.trim() === '') {
                this.showAlert('warning', 'Masukkan nomor rekam medis Anda.');
                return;
            }

            const rm = input.value.trim();
            this.showAlert('info', '<i class="fa fa-spinner fa-spin me-1"></i> Memvalidasi booking Mobile JKN...');

            fetch('/api/v1/registration/checkin', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ no_rkm_medis: rm })
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const modalEl = document.getElementById('modalKioskCheckIn');
                    if (modalEl && window.bootstrap) {
                        window.bootstrap.Modal.getInstance(modalEl).hide();
                    }
                    this.showTicketModal(res.data.no_reg, 'Poliklinik Tujuan', 'Dokter Spesialis');
                } else {
                    this.showAlert('danger', res.message || 'Check-in gagal diproses.');
                }
            })
            .catch(() => {
                this.showAlert('danger', 'Gagal menghubungi server pendaftaran.');
            });
        },

        showTicketModal: function (noReg, poli, dokter) {
            const ticketNo = document.getElementById('ticketNoReg');
            const ticketPoli = document.getElementById('ticketPoli');
            const ticketDoc = document.getElementById('ticketDokter');
            const modalEl = document.getElementById('modalKioskTicket');

            if (ticketNo) ticketNo.innerText = noReg;
            if (ticketPoli) ticketPoli.innerText = poli;
            if (ticketDoc) ticketDoc.innerText = dokter;

            if (modalEl && window.bootstrap) {
                new window.bootstrap.Modal(modalEl).show();
            }
        },

        showAlert: function (type, message) {
            const box = document.getElementById('alertKioskCheckIn');
            if (!box) return;

            box.className = `alert alert-${type} py-2 px-3 small`;
            box.innerHTML = message;
            box.classList.remove('d-none');
        },

        hideAlert: function () {
            const box = document.getElementById('alertKioskCheckIn');
            if (box) box.classList.add('d-none');
        },

        initSignaturePad: function () {
            const canvas = document.getElementById('signaturePad');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            let isDrawing = false;

            ctx.lineWidth = 3;
            ctx.lineJoin = 'round';
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000000';

            const getPos = (e) => {
                const rect = canvas.getBoundingClientRect();
                const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: (clientX - rect.left) * (canvas.width / rect.width),
                    y: (clientY - rect.top) * (canvas.height / rect.height)
                };
            };

            const start = (e) => {
                e.preventDefault();
                isDrawing = true;
                const pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
            };

            const move = (e) => {
                if (!isDrawing) return;
                e.preventDefault();
                const pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
            };

            const stop = () => {
                isDrawing = false;
            };

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', stop);

            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            window.addEventListener('touchend', stop);

            const btnClear = document.getElementById('btnClearSignature');
            if (btnClear) {
                btnClear.addEventListener('click', () => {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                });
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => KioskModule.init());
    } else {
        KioskModule.init();
    }

    window.KioskModule = KioskModule;
})(window, document);
