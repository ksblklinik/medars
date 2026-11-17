<?php
$csrfToken = (string) ($csrf_token ?? '');
?>
<div class="container-fluid py-3" id="smartAgentApp" data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSaCopilot" type="button">Asisten Klinis &amp; Rekomendasi ICD-10</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSaVault" type="button">Knowledge Vault (PDT)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSaProfiles" type="button">Profil Model AI (vLLM / Cloud)</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tabSaCopilot">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header text-white fw-semibold" style="background-color:#00897b;">
                    <i class="fa-solid fa-robot me-1"></i> Konsultasi RAG Knowledge Vault &amp; Grounding ICD-10
                </div>
                <div class="card-body">
                    <form id="saRecommendForm" class="d-flex gap-2 mb-3">
                        <input type="text" id="saQueryInput" class="form-control form-control-sm" placeholder="Contoh: pasien demam tinggi 3 hari nyeri sendi trombosit turun" required>
                        <button type="submit" class="btn btn-sm text-white text-nowrap" style="background-color:#00897b;">Analisis Klinis</button>
                    </form>
                    <div id="saRecommendResult" class="small text-muted">Masukkan keluhan pasien untuk melihat rujukan Pedoman Diagnosa Terapi (PDT) dan kandidat kode ICD-10 SatuSehat.</div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabSaVault">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Tambah Dokumen PDT / SOP</div>
                        <div class="card-body">
                            <form id="saVaultForm">
                                <div class="mb-2"><label class="form-label small">Judul Pedoman *</label><input type="text" id="saTitle" class="form-control form-control-sm" required></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label small">Kategori</label><select id="saCat" class="form-select form-select-sm"><option value="PDT">PDT</option><option value="SOP">SOP</option><option value="Farmakologi">Farmakologi</option></select></div>
                                    <div class="col-6"><label class="form-label small">Kode ICD-10</label><input type="text" id="saIcd" class="form-control form-control-sm" placeholder="A90"></div>
                                </div>
                                <div class="mb-2"><label class="form-label small">Keywords</label><input type="text" id="saKw" class="form-control form-control-sm" placeholder="demam, trombosit, dengue"></div>
                                <div class="mb-3"><label class="form-label small">Isi Pedoman Terapi *</label><textarea id="saContent" class="form-control form-control-sm" rows="3" required></textarea></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan ke Vault</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Dokumen Knowledge Vault</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Judul</th><th>Kategori</th><th>ICD-10</th><th>Ringkasan</th><th></th></tr></thead>
                                <tbody id="saVaultBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabSaProfiles">
            <div class="row g-3">
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Tambah Profil Model AI</div>
                        <div class="card-body">
                            <form id="saProfileForm">
                                <div class="mb-2"><label class="form-label small">Nama Profil *</label><input type="text" id="saPrName" class="form-control form-control-sm" required></div>
                                <div class="mb-2"><label class="form-label small">Provider</label><select id="saPrProv" class="form-select form-select-sm"><option value="vllm-local">vLLM Lokal</option><option value="openrouter">OpenRouter Cloud</option><option value="ollama">Ollama</option></select></div>
                                <div class="mb-2"><label class="form-label small">Endpoint URL</label><input type="url" id="saPrUrl" class="form-control form-control-sm" value="http://127.0.0.1:8000/v1"></div>
                                <div class="mb-3"><label class="form-label small">Model Name</label><input type="text" id="saPrModel" class="form-control form-control-sm" value="Qwen/Qwen2.5-7B-Instruct"></div>
                                <button type="submit" class="btn btn-sm text-white w-100" style="background-color:#00897b;">Simpan Profil</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white fw-semibold">Daftar Profil Model AI</div>
                        <div class="card-body p-0">
                            <table class="table table-sm table-hover mb-0 small">
                                <thead class="table-light"><tr><th>Nama</th><th>Provider</th><th>Model</th><th>Endpoint</th></tr></thead>
                                <tbody id="saProfilesBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/smartagent.js?v=<?= time() ?>"></script>
