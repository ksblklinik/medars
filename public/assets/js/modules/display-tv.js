/**
 * display-tv.js — Layar Display TV Antrean Poliklinik Real-Time SSE
 * Paritas visual & fungsional 100% dengan ksmlite
 * - Audio Chime & Web Speech Synthesis API (Bahasa Indonesia)
 * - Auto-reconnect SSE Stream
 */

(function (window, document) {
    'use strict';

    const DisplayTv = {
        init: function () {
            this.initClock();
            this.fetchQueueSummary();
            this.initSseStream();
            setInterval(() => this.fetchQueueSummary(), 15000);
        },

        initClock: function () {
            const clockEl = document.getElementById('tvLiveClock');
            if (!clockEl) return;

            const update = () => {
                const now = new Date();
                const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
                clockEl.innerText = `${timeStr} WIB`;
            };
            update();
            setInterval(update, 1000);
        },

        fetchQueueSummary: function () {
            fetch('/api/v1/queue/summary')
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data)) return;
                    data.forEach(item => {
                        this.updatePoliCard(item.kd_poli, item.current_queue, item.nm_dokter, item.room, item.waiting_queues);
                    });
                })
                .catch(() => {});
        },

        updatePoliCard: function (kdPoli, currentNo, doctor, room, waiting) {
            const currentEl = document.getElementById(`current_${kdPoli}`);
            const docEl = document.getElementById(`doc_${kdPoli}`);
            const roomEl = document.getElementById(`room_${kdPoli}`);
            const nextEl = document.getElementById(`next_${kdPoli}`);
            const statusEl = document.getElementById(`status_${kdPoli}`);

            if (currentEl) currentEl.innerText = currentNo || '---';
            if (docEl && doctor) docEl.innerText = doctor;
            if (roomEl && room) roomEl.innerText = room;
            if (nextEl) {
                nextEl.innerText = Array.isArray(waiting) && waiting.length > 0 ? waiting.join(', ') : '-';
            }
            if (statusEl) {
                statusEl.innerText = currentNo && currentNo !== '---' ? 'SEDANG DILAYANI' : 'MENUNGGU PANGGILAN';
            }
        },

        initSseStream: function () {
            if (!window.EventSource) return;

            const badge = document.getElementById('sseTvBadge');
            const es = new EventSource('/api/stream');

            es.addEventListener('open', () => {
                if (badge) {
                    badge.className = 'badge bg-success-subtle text-success border border-success px-3 py-2 small';
                    badge.innerHTML = '<i class="fa fa-circle-dot text-success animate-pulse me-1"></i> Real-Time SSE Aktif';
                }
            });

            es.addEventListener('queue.called', (e) => {
                try {
                    const data = JSON.parse(e.data);
                    this.onQueueCalled(data);
                } catch (err) {}
            });

            es.addEventListener('error', () => {
                if (badge) {
                    badge.className = 'badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 small';
                    badge.innerHTML = '<i class="fa fa-rotate me-1"></i> Reconnecting SSE...';
                }
            });
        },

        onQueueCalled: function (data) {
            const kdPoli = data.kd_poli;
            this.updatePoliCard(kdPoli, data.no_reg, data.nm_dokter, data.room, []);

            const card = document.getElementById(`cardPoli_${kdPoli}`);
            if (card) {
                card.classList.add('calling');
                setTimeout(() => card.classList.remove('calling'), 10000);
            }

            // Putar audio pengumuman (Chime + Text-to-Speech)
            this.playAnnouncement(data.sound_text || `Nomor antrean ${data.no_reg}, silakan menuju ke ${data.nm_poli}`);
            this.fetchQueueSummary();
        },

        playAnnouncement: function (text) {
            if (!('speechSynthesis' in window)) return;

            window.speechSynthesis.cancel();

            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'id-ID';
            utterance.rate = 0.9;
            utterance.pitch = 1.0;

            // Prioritaskan suara bahasa Indonesia jika tersedia
            const voices = window.speechSynthesis.getVoices();
            const idVoice = voices.find(v => v.lang.startsWith('id') || v.lang.startsWith('in'));
            if (idVoice) {
                utterance.voice = idVoice;
            }

            // Mainkan Web Audio chime sebelum berbicara
            this.playChimeTone(() => {
                window.speechSynthesis.speak(utterance);
            });
        },

        playChimeTone: function (callback) {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) {
                    if (callback) callback();
                    return;
                }
                const ctx = new AudioContext();
                const now = ctx.currentTime;

                const osc1 = ctx.createOscillator();
                const osc2 = ctx.createOscillator();
                const gain = ctx.createGain();

                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(587.33, now); // D5
                osc1.frequency.setValueAtTime(880, now + 0.3); // A5

                gain.gain.setValueAtTime(0.2, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.8);

                osc1.connect(gain);
                gain.connect(ctx.destination);

                osc1.start(now);
                osc1.stop(now + 0.8);

                setTimeout(() => {
                    if (callback) callback();
                }, 850);
            } catch (e) {
                if (callback) callback();
            }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => DisplayTv.init());
    } else {
        DisplayTv.init();
    }

    window.DisplayTv = DisplayTv;
})(window, document);
