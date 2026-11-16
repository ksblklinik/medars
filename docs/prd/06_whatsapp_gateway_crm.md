# PRD 06: WhatsApp Gateway, Anti-Banned Meta & CRM Multi-Session

## 1. Ikhtisar Domain
Domain ini mengelola transmisi notifikasi transaksional klinik (e-resep, kwitansi pembayaran, hasil lab, pengingat antrean), live chat customer service, chatbot AI, verifikasi QR check-in, dan proteksi anti-blokir Meta.

## 2. Aturan Bisnis & Spesifikasi Kunci (99% Fidelity)
1. **Arsitektur Dual-Session (`system` vs `cs`):**
   - Sesi 1 (`system`): Pengiriman notifikasi satu arah SIMRS dan penerimaan scan QR check-in.
   - Sesi 2 (`cs`): Percakapan interaktif dua arah CS dan AI Chatbot. **Bot AI dilarang merespons nomor sistem.**
2. **Dynamic Failover Antar-Sesi & Graceful Skip QR:**
   - Jika nomor Sistem offline, pengiriman darurat dialihkan ke nomor CS (dengan validasi Whitelist untuk staf non-admin).
   - Jika kedua sesi offline (`all_offline`), sistem mengembalikan flag `skip_qr = true` agar antrean pendaftaran loket tidak terblokir.
3. **10-Lapis Proteksi Anti-Banned Meta:**
   - Kunci pengiriman massal 100% hanya ke kontak **Whitelist (Tier 1)**.
   - Otorisasi kirim satuan: Admin bebas kirim; Non-Admin **HANYA** ke nomor Whitelist.
   - Kebijakan *Zero-Reply QR*: Pasien scan QR pendaftaran, server mencatat verifikasi tanpa membalas chat keluar ke pasien (0 outbound response).
   - Tautan QR wajib menggunakan *Conversational Dynamic Spintax* (dilarang teks seragam `REG-XXXXXX` telanjang).
   - Validasi Pra-Kirim (*Pre-Send Gate*): Regex seluler Indonesia `^628[1-9]` dan cek keaktifan akun WA (`isOnWhatsApp`) sebelum menembak HTTP ke Meta.
   - Circuit Breaker Otomatis: Hentikan pengiriman darurat jika gagal 3x beruntun atau respons HTTP 401/428/429/Banned.

## 3. Kontrak Pengujian TDD Wajib (Test Cases)
- `test_should_prevent_non_admin_from_sending_wa_to_non_whitelisted_number()`
- `test_should_never_send_outbound_reply_on_qr_checkin_verification()`
- `test_should_abort_bulk_sending_immediately_on_circuit_breaker_trigger()`
- `test_should_skip_qr_gate_gracefully_when_all_sessions_are_offline()`
- `test_should_isolate_bot_ai_responses_exclusively_to_cs_session()`
