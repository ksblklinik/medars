/**
 * medars-app.js — Core Client Architecture for ksm-next
 * - Dynamic Viewport Slot Lifecycle (Anti Tumpuk-Tumpuk Hidden-Show)
 * - Single-Host Modal Manager (#appModalHost)
 * - Clinical Keyboard Hotkeys (F2, F4, F8, F9, Esc)
 */

(function (window, document) {
    'use strict';

    // 1. DYNAMIC VIEWPORT SLOT MANAGER
    const AppViewport = {
        _currentCleanup: null,

        mount: function (html, initCallback, cleanupCallback) {
            this.unmount();

            const slot = document.getElementById('moduleViewportSlot');
            if (!slot) return;

            slot.innerHTML = html;

            if (typeof cleanupCallback === 'function') {
                this._currentCleanup = cleanupCallback;
            }

            if (typeof initCallback === 'function') {
                try {
                    initCallback(slot);
                } catch (e) {
                    console.error('[AppViewport] Init error:', e);
                }
            }
        },

        unmount: function () {
            if (typeof this._currentCleanup === 'function') {
                try {
                    this._currentCleanup();
                } catch (e) {
                    console.error('[AppViewport] Cleanup error:', e);
                }
                this._currentCleanup = null;
            }

            const slot = document.getElementById('moduleViewportSlot');
            if (slot) {
                slot.innerHTML = '';
            }
        }
    };

    // 2. SINGLE-HOST DYNAMIC MODAL MANAGER (#appModalHost)
    const AppModal = {
        _bsModalInstance: null,
        _cleanupCallback: null,

        init: function () {
            const host = document.getElementById('appModalHost');
            if (!host) return;

            if (window.bootstrap && window.bootstrap.Modal) {
                this._bsModalInstance = new window.bootstrap.Modal(host);
            }

            // Clean modal lifecycle: Otomatis buang isi DOM saat modal tertutup
            host.addEventListener('hidden.bs.modal', () => {
                if (typeof this._cleanupCallback === 'function') {
                    try {
                        this._cleanupCallback();
                    } catch (e) {
                        console.error('[AppModal] Cleanup error:', e);
                    }
                    this._cleanupCallback = null;
                }

                const titleEl = document.getElementById('appModalHostTitle');
                const bodyEl = document.getElementById('appModalBody');
                if (titleEl) titleEl.innerText = 'Memuat Dialog...';
                if (bodyEl) {
                    bodyEl.innerHTML = '<div class="spinner-border text-medical-teal" role="status"><span class="visually-hidden">Memuat...</span></div>';
                }
            });

            // Auto-focus primary input saat modal terbuka (PRD 10)
            host.addEventListener('shown.bs.modal', () => {
                const primaryInput = host.querySelector('input:not([type=hidden]):not([disabled]), select:not([disabled])');
                if (primaryInput) {
                    primaryInput.focus();
                }
            });
        },

        open: function (title, contentHtml, onMountedCallback, cleanupCallback) {
            const host = document.getElementById('appModalHost');
            const titleEl = document.getElementById('appModalHostTitle');
            const bodyEl = document.getElementById('appModalBody');

            if (!host || !titleEl || !bodyEl) return;

            titleEl.innerText = title;
            bodyEl.innerHTML = contentHtml;
            this._cleanupCallback = cleanupCallback;

            if (this._bsModalInstance) {
                this._bsModalInstance.show();
            }

            if (typeof onMountedCallback === 'function') {
                setTimeout(() => onMountedCallback(bodyEl), 50);
            }
        },

        close: function () {
            if (this._bsModalInstance) {
                this._bsModalInstance.hide();
            }
        }
    };

    // 3. HOTKEYS OPERASIONAL CEPAT (PRD 10)
    const AppHotkeys = {
        init: function () {
            document.addEventListener('keydown', (e) => {
                // Jangan cegat jika user sedang mengetik di input biasa kecuali untuk hotkeys fungsi F
                const isInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName);

                switch (e.key) {
                    case 'F2': // Simpan / Bayar Transaksi
                        e.preventDefault();
                        this._triggerSubmitActiveForm();
                        break;

                    case 'F4': // Buka Modal Pencarian Pasien / E-Resep
                        e.preventDefault();
                        const searchTrigger = document.querySelector('[data-hotkey="F4"], .btn-cari-pasien, #btnCariPasien');
                        if (searchTrigger) searchTrigger.click();
                        break;

                    case 'F8': // Pending Draf
                        e.preventDefault();
                        const pendingTrigger = document.querySelector('[data-hotkey="F8"], .btn-pending-transaksi');
                        if (pendingTrigger) pendingTrigger.click();
                        break;

                    case 'F9': // Batal / Reset
                        e.preventDefault();
                        const resetTrigger = document.querySelector('[data-hotkey="F9"], .btn-batal-transaksi');
                        if (resetTrigger) resetTrigger.click();
                        break;

                    case 'Escape': // Tutup modal aktif
                        const activeModal = document.querySelector('.modal.show');
                        if (activeModal) {
                            AppModal.close();
                        }
                        break;
                }
            });
        },

        _triggerSubmitActiveForm: function () {
            const activeForm = document.querySelector('#moduleViewportSlot form');
            if (activeForm) {
                const submitBtn = activeForm.querySelector('button[type=submit], .btn-simpan, #btnSimpan');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                } else {
                    activeForm.requestSubmit();
                }
            }
        }
    };

    // 4. LIVE CLOCK (WIB)
    function startLiveClock() {
        const clockEl = document.getElementById('clockText');
        if (!clockEl) return;

        function update() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('id-ID', { hour12: false }) + ' WIB';
            clockEl.innerText = timeStr;
        }

        update();
        setInterval(update, 1000);
    }

    // 5. SERVER-SENT EVENTS (SSE) REAL-TIME CLIENT
    const AppSSE = {
        _source: null,
        _handlers: {},

        init: function (streamUrl) {
            if (typeof window.EventSource === 'undefined') return;

            const badge = document.getElementById('sseBadge');
            const statusText = document.getElementById('sseStatusText');

            this._source = new window.EventSource(streamUrl || '/api/stream');

            this._source.addEventListener('system.connected', () => {
                if (badge) {
                    badge.classList.remove('bg-danger-subtle', 'text-danger', 'border-danger');
                    badge.classList.add('bg-success-subtle', 'text-success', 'border-success');
                }
                if (statusText) statusText.innerText = 'SSE Online';
            });

            this._source.onerror = () => {
                if (badge) {
                    badge.classList.remove('bg-success-subtle', 'text-success', 'border-success');
                    badge.classList.add('bg-danger-subtle', 'text-danger', 'border-danger');
                }
                if (statusText) statusText.innerText = 'SSE Reconnecting...';
            };
        },

        on: function (eventName, callback) {
            if (!this._source || typeof callback !== 'function') return;
            this._source.addEventListener(eventName, (e) => {
                // Triple-Gate Protection (PRD 10 / frontend-ui-ux):
                // Jangan ganggu DOM jika modal sedang aktif terbuka
                if (document.querySelector('.modal.show')) return;
                try {
                    const payload = JSON.parse(e.data);
                    callback(payload);
                } catch (err) {
                    console.error('[AppSSE] JSON parse error:', err);
                }
            });
        }
    };

    // Inisialisasi saat DOM siap
    document.addEventListener('DOMContentLoaded', () => {
        AppModal.init();
        AppHotkeys.init();
        startLiveClock();
        AppSSE.init('/api/stream');
    });

    // Ekspor API ke global window untuk diakses modul-modul
    window.Medars = {
        viewport: AppViewport,
        modal: AppModal,
        hotkeys: AppHotkeys,
        sse: AppSSE
    };

})(window, document);
