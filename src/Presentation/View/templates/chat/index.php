<?php
$csrfToken = (string) ($csrf_token ?? '');
?>
<div class="container-fluid py-3" id="chatApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header text-white fw-semibold" style="background-color:#00897b;">Buat Kanal / Percakapan Baru</div>
                <div class="card-body">
                    <form id="chatConvForm" class="d-flex gap-2">
                        <input type="text" id="chatConvTitle" class="form-control form-control-sm" placeholder="Contoh: Koordinasi IGD - Farmasi" required>
                        <button type="submit" class="btn btn-sm text-white" style="background-color:#00897b;">Buat</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-semibold">Daftar Percakapan</div>
                <div class="list-group list-group-flush small" id="chatConvList"></div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold">Template Balasan Cepat (Quick Response)</div>
                <div class="card-body">
                    <form id="chatQrForm" class="row g-1 mb-2">
                        <div class="col-4"><input type="text" id="chatQrShortcut" class="form-control form-control-sm" placeholder="/salam" required></div>
                        <div class="col-6"><input type="text" id="chatQrContent" class="form-control form-control-sm" placeholder="Isi pesan..." required></div>
                        <div class="col-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100">+</button></div>
                    </form>
                    <div id="chatQrList" class="d-flex flex-wrap gap-1"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-semibold" id="chatActiveTitle">Pilih percakapan di sebelah kiri</div>
                <div class="card-body bg-light" id="chatMessagesBox" style="min-height:360px; max-height:480px; overflow-y:auto;">
                    <div class="text-center text-muted small py-5">Belum ada percakapan dipilih.</div>
                </div>
                <div class="card-footer bg-white">
                    <form id="chatSendForm" class="d-flex gap-2">
                        <input type="text" id="chatMsgInput" class="form-control form-control-sm" placeholder="Tulis pesan..." required>
                        <button type="submit" class="btn btn-sm text-white" style="background-color:#00897b;">Kirim</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/chat.js?v=<?= time() ?>"></script>
