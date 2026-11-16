<div class="row g-3" id="bridgingWorkspace">
    <!-- Panel 1: Zero-Latency Circuit Breaker & Antrean SatuSehat FHIR -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2 d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-network-wired me-1"></i> Bridging BPJS &amp; Circuit Breaker</h6>
                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnRefreshBridging">
                    <i class="fa fa-rotate"></i>
                </button>
            </div>
            <div class="card-body">
                <div id="bpjsCircuitBadge" class="alert alert-secondary py-2 small mb-2">Memeriksa status Circuit Breaker...</div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="small fw-semibold">Antrean Sinkronisasi SatuSehat FHIR</span>
                    <button type="button" class="btn btn-xs btn-medical-teal" id="btnProcessQueue">
                        <i class="fa fa-play me-1"></i> Proses Antrean
                    </button>
                </div>
                <div style="max-height: 280px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Provider</th>
                                <th>No. Rawat</th>
                                <th>Resource</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="bridgingQueueBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel 2: HFIS LEAN Board & Verifikasi Klaim Vedika -->
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-clock me-1"></i> Kalkulator HFIS LEAN Board</h6>
            </div>
            <div class="card-body">
                <form id="formLeanEstimate" class="row g-2 mb-2">
                    <div class="col-md-3">
                        <label class="form-label small">Shift</label>
                        <select class="form-select form-select-sm" id="leanShift">
                            <option value="Pagi">Pagi</option>
                            <option value="Sore">Sore</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small">Kode Poli</label>
                        <input type="text" class="form-control form-control-sm" id="leanKdPoli" value="UMU01">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">No. Reg</label>
                        <input type="number" class="form-control form-control-sm" id="leanNoReg" value="5" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Durasi (m)</label>
                        <input type="number" class="form-control form-control-sm" id="leanDuration" value="5" min="1">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-medical-teal">Hitung Estimasi HFIS</button>
                    </div>
                </form>
                <div id="leanEstimateOutput" class="small text-muted"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-2">
                <h6 class="m-0 fw-bold text-medical-teal"><i class="fa fa-file-medical-alt me-1"></i> Verifikasi Klaim Vedika BPJS</h6>
            </div>
            <div class="card-body">
                <form id="formVedikaVerify" class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small">No. Rawat</label>
                        <input type="text" class="form-control form-control-sm" id="vedikaNoRawat" placeholder="2026/11/16/000001" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">No. SEP BPJS</label>
                        <input type="text" class="form-control form-control-sm" id="vedikaNoSep" placeholder="0123R001..." required>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-medical-teal">Verifikasi Berkas Vedika</button>
                    </div>
                </form>
                <div id="vedikaFeedback" class="mt-2"></div>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/modules/bridging.js" defer></script>
