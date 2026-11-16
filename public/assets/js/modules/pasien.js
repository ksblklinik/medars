(() => {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    let currentPage = 1;

    function escapeHtml(val) {
        return String(val ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function apiFetch(url, options = {}) {
        const headers = Object.assign(
            { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            options.headers || {}
        );
        const res = await fetch(url, Object.assign({}, options, { headers }));
        return res.json();
    }

    async function loadPasienList() {
        const tbody = document.getElementById('pasienTableBody');
        if (!tbody) return;
        const q = document.getElementById('pasienSearchInput')?.value.trim() || '';
        const data = await apiFetch(`/api/v1/pasien/list?q=${encodeURIComponent(q)}&page=${currentPage}&limit=20`);
        if (!data.ok) return;

        document.getElementById('pasienTotalBadge').textContent = `Total: ${data.total} Pasien`;
        document.getElementById('pasienPageInfo').textContent = `Halaman ${data.page}`;

        if (!data.rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4 small">Tidak ada data pasien ditemukan.</td></tr>';
            return;
        }

        tbody.innerHTML = data.rows.map((p) => `<tr>
            <td class="ps-2 fw-bold text-medical-teal">${escapeHtml(p.no_rkm_medis)}</td>
            <td>
                <div class="fw-semibold">${escapeHtml(p.nm_pasien)}</div>
                <div class="small text-muted">${escapeHtml(p.no_ktp || '-')}</div>
            </td>
            <td class="small">${escapeHtml(p.jk)} · ${escapeHtml(p.tgl_lahir)}</td>
            <td class="small">
                <div>${escapeHtml(p.no_tlp || '-')}</div>
                <div class="text-muted">${escapeHtml(p.png_jawab || p.kd_pj)} ${p.no_peserta ? '· ' + escapeHtml(p.no_peserta) : ''}</div>
            </td>
            <td class="text-end pe-2">
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-primary btn-edit-pasien" data-rm="${escapeHtml(p.no_rkm_medis)}" title="Edit"><i class="fa fa-pen"></i></button>
                    <button type="button" class="btn btn-outline-info btn-history-pasien" data-rm="${escapeHtml(p.no_rkm_medis)}" title="Riwayat"><i class="fa fa-clock-rotate-left"></i></button>
                    <a href="/pasien/kartu?no_rkm_medis=${encodeURIComponent(p.no_rkm_medis)}" target="_blank" class="btn btn-outline-success" title="Kartu Pasien"><i class="fa fa-id-card"></i></a>
                    <button type="button" class="btn btn-outline-danger btn-del-pasien" data-rm="${escapeHtml(p.no_rkm_medis)}" title="Hapus"><i class="fa fa-trash"></i></button>
                </div>
            </td>
        </tr>`).join('');

        tbody.querySelectorAll('.btn-edit-pasien').forEach((btn) => {
            btn.addEventListener('click', () => loadDetailIntoForm(btn.dataset.rm));
        });
        tbody.querySelectorAll('.btn-history-pasien').forEach((btn) => {
            btn.addEventListener('click', () => openHistoryModal(btn.dataset.rm));
        });
        tbody.querySelectorAll('.btn-del-pasien').forEach((btn) => {
            btn.addEventListener('click', () => deletePasien(btn.dataset.rm));
        });
    }

    async function loadDetailIntoForm(noRm) {
        const data = await apiFetch(`/api/v1/pasien/detail?no_rkm_medis=${encodeURIComponent(noRm)}`);
        if (!data.ok || !data.pasien) return;
        const p = data.pasien;
        document.getElementById('pasNoRm').value = p.no_rkm_medis || '';
        document.getElementById('pasNik').value = p.no_ktp || '';
        document.getElementById('pasNama').value = p.nm_pasien || '';
        document.getElementById('pasJk').value = p.jk || 'L';
        document.getElementById('pasTmpLahir').value = p.tmp_lahir || '';
        document.getElementById('pasTglLahir').value = p.tgl_lahir || '';
        document.getElementById('pasGolDarah').value = p.gol_darah || '-';
        document.getElementById('pasAgama').value = p.agama || '-';
        document.getElementById('pasSttsNikah').value = p.stts_nikah || 'BELUM MENIKAH';
        document.getElementById('pasTelp').value = p.no_tlp || '';
        document.getElementById('pasIbu').value = p.nm_ibu || '-';
        document.getElementById('pasKdPj').value = p.kd_pj || 'UMU';
        document.getElementById('pasNoPeserta').value = p.no_peserta || '';
        document.getElementById('pasAlamat').value = p.alamat || '';
        document.getElementById('pasKeluarga').value = p.keluarga || 'DIRI SENDIRI';
        document.getElementById('pasNamaKeluarga').value = p.namakeluarga || '-';
    }

    async function openHistoryModal(noRm) {
        const data = await apiFetch(`/api/v1/pasien/history?no_rkm_medis=${encodeURIComponent(noRm)}`);
        const rows = data.ok ? data.history : [];
        const html = rows.length
            ? `<div class="table-responsive"><table class="table table-sm table-striped text-start small mb-0">
                <thead><tr><th>Tgl</th><th>No. Rawat</th><th>Poli / Dokter</th><th>Penjamin</th><th>Status</th></tr></thead>
                <tbody>${rows.map((r) => `<tr>
                    <td>${escapeHtml(r.tgl_registrasi)}</td>
                    <td>${escapeHtml(r.no_rawat)}</td>
                    <td>${escapeHtml(r.nm_poli)} · ${escapeHtml(r.nm_dokter)}</td>
                    <td>${escapeHtml(r.png_jawab)}</td>
                    <td><span class="badge bg-secondary">${escapeHtml(r.stts)}</span></td>
                </tr>`).join('')}</tbody>
               </table></div>`
            : '<div class="text-muted small py-3">Belum ada riwayat kunjungan untuk pasien ini.</div>';

        if (window.Medars && window.Medars.modal) {
            window.Medars.modal.open(`Riwayat Kunjungan Pasien (${noRm})`, html);
        }
    }

    async function deletePasien(noRm) {
        const data = await apiFetch('/api/v1/pasien/delete', {
            method: 'POST',
            body: JSON.stringify({ no_rkm_medis: noRm }),
        });
        const fb = document.getElementById('pasienFeedback');
        if (fb) {
            fb.innerHTML = data.ok
                ? `<div class="alert alert-info py-2 small">Pasien ${escapeHtml(noRm)} berhasil dihapus.</div>`
                : `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        }
        loadPasienList();
    }

    document.getElementById('btnPasienMaxId')?.addEventListener('click', async () => {
        const data = await apiFetch('/api/v1/pasien/max-id');
        if (data.ok) {
            document.getElementById('pasNoRm').value = data.next_no_rkm_medis;
        }
    });

    document.getElementById('btnResetPasien')?.addEventListener('click', () => {
        document.getElementById('formMasterPasien')?.reset();
        document.getElementById('pasNoRm').value = '';
    });

    document.getElementById('formMasterPasien')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            no_rkm_medis: document.getElementById('pasNoRm').value.trim(),
            no_ktp: document.getElementById('pasNik').value.trim(),
            nm_pasien: document.getElementById('pasNama').value.trim(),
            jk: document.getElementById('pasJk').value,
            tmp_lahir: document.getElementById('pasTmpLahir').value.trim(),
            tgl_lahir: document.getElementById('pasTglLahir').value,
            gol_darah: document.getElementById('pasGolDarah').value,
            agama: document.getElementById('pasAgama').value,
            stts_nikah: document.getElementById('pasSttsNikah').value,
            no_tlp: document.getElementById('pasTelp').value.trim(),
            nm_ibu: document.getElementById('pasIbu').value.trim(),
            kd_pj: document.getElementById('pasKdPj').value,
            no_peserta: document.getElementById('pasNoPeserta').value.trim(),
            alamat: document.getElementById('pasAlamat').value.trim(),
            keluarga: document.getElementById('pasKeluarga').value,
            namakeluarga: document.getElementById('pasNamaKeluarga').value.trim(),
        };
        const data = await apiFetch('/api/v1/pasien/save', {
            method: 'POST',
            body: JSON.stringify(payload),
        });
        const fb = document.getElementById('pasienFeedback');
        if (data.ok) {
            document.getElementById('pasNoRm').value = data.pasien.no_rkm_medis;
            if (fb) {
                fb.innerHTML = `<div class="alert alert-success py-2 small">Data pasien <strong>${escapeHtml(data.pasien.no_rkm_medis)}</strong> tersimpan.</div>`;
            }
            loadPasienList();
        } else if (fb) {
            fb.innerHTML = `<div class="alert alert-danger py-2 small">${escapeHtml(data.error)}</div>`;
        }
    });

    document.getElementById('btnSearchPasien')?.addEventListener('click', () => { currentPage = 1; loadPasienList(); });
    document.getElementById('btnRefreshPasien')?.addEventListener('click', loadPasienList);
    document.getElementById('btnPasienPrev')?.addEventListener('click', () => { if (currentPage > 1) { currentPage--; loadPasienList(); } });
    document.getElementById('btnPasienNext')?.addEventListener('click', () => { currentPage++; loadPasienList(); });

    loadPasienList();
})();
