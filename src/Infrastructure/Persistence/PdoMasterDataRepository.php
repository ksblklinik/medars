<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Master\Repository\MasterDataRepositoryInterface;

final readonly class PdoMasterDataRepository implements MasterDataRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function list(string $entity, string $q = ''): array
    {
        $like = '%' . trim($q) . '%';
        return match ($entity) {
            'poliklinik' => $this->fetchAll(
                'SELECT kd_poli, nm_poli, registrasi, registrasilama, status, allow_bpjs
                 FROM poliklinik
                 WHERE kd_poli LIKE :q OR nm_poli LIKE :q
                 ORDER BY kd_poli ASC LIMIT 100',
                [':q' => $like]
            ),
            'dokter' => $this->fetchAll(
                'SELECT kd_dokter, nm_dokter, jk, no_ijn_praktek, status, almt_tgl, no_telp
                 FROM dokter
                 WHERE kd_dokter LIKE :q OR nm_dokter LIKE :q
                 ORDER BY nm_dokter ASC LIMIT 100',
                [':q' => $like]
            ),
            'penjab' => $this->fetchAll(
                'SELECT kd_pj, png_jawab, status, nama_perusahaan, alamat_asuransi, no_telp
                 FROM penjab
                 WHERE kd_pj LIKE :q OR png_jawab LIKE :q
                 ORDER BY kd_pj ASC LIMIT 100',
                [':q' => $like]
            ),
            'jns_perawatan' => $this->fetchAll(
                'SELECT kd_jenis_prw, nm_perawatan, kd_kategori, material, tarif_tindakandr, tarif_tindakanpr, total_byrdr, total_byrpr, status
                 FROM jns_perawatan
                 WHERE kd_jenis_prw LIKE :q OR nm_perawatan LIKE :q
                 ORDER BY nm_perawatan ASC LIMIT 100',
                [':q' => $like]
            ),
            'jadwal' => $this->fetchAll(
                'SELECT j.kd_dokter, d.nm_dokter, j.kd_poli, p.nm_poli, j.hari_kerja, j.jam_mulai, j.jam_selesai, j.kuota, j.prefik, j.room
                 FROM jadwal j
                 LEFT JOIN dokter d ON j.kd_dokter = d.kd_dokter
                 LEFT JOIN poliklinik p ON j.kd_poli = p.kd_poli
                 WHERE j.kd_dokter LIKE :q OR j.kd_poli LIKE :q OR j.hari_kerja LIKE :q OR COALESCE(d.nm_dokter, \'\') LIKE :q
                 ORDER BY j.hari_kerja ASC, j.jam_mulai ASC LIMIT 100',
                [':q' => $like]
            ),
            'pegawai' => $this->fetchAll(
                'SELECT nik, nama, jk, jbtn, departemen, status
                 FROM pegawai
                 WHERE nik LIKE :q OR nama LIKE :q OR jbtn LIKE :q OR departemen LIKE :q
                 ORDER BY nama ASC LIMIT 100',
                [':q' => $like]
            ),
            'petugas' => $this->fetchAll(
                'SELECT nip, nama, jk, jbtn, status, almt_tgl, no_telp
                 FROM petugas
                 WHERE nip LIKE :q OR nama LIKE :q OR jbtn LIKE :q
                 ORDER BY nama ASC LIMIT 100',
                [':q' => $like]
            ),
            'bangsal' => $this->fetchAll(
                'SELECT kd_bangsal, nm_bangsal, status
                 FROM bangsal
                 WHERE kd_bangsal LIKE :q OR nm_bangsal LIKE :q
                 ORDER BY nm_bangsal ASC LIMIT 100',
                [':q' => $like]
            ),
            'kamar' => $this->fetchAll(
                'SELECT k.kd_kamar, k.kd_bangsal, COALESCE(b.nm_bangsal, k.kd_bangsal) AS nm_bangsal, k.trf_kamar, k.status, k.kelas, k.statusdata
                 FROM kamar k
                 LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
                 WHERE k.kd_kamar LIKE :q OR k.kd_bangsal LIKE :q OR k.kelas LIKE :q OR COALESCE(b.nm_bangsal, \'\') LIKE :q
                 ORDER BY k.kd_kamar ASC LIMIT 100',
                [':q' => $like]
            ),
            'penyakit' => $this->fetchAll(
                'SELECT kd_penyakit, nm_penyakit, nm_indonesia, status, skdi_level
                 FROM penyakit
                 WHERE kd_penyakit LIKE :q OR nm_penyakit LIKE :q OR nm_indonesia LIKE :q
                 ORDER BY kd_penyakit ASC LIMIT 100',
                [':q' => $like]
            ),
            'sdm_berkas' => $this->fetchAll(
                'SELECT id, nik, jenis_berkas, nama_file, mime_type, file_size, tgl_kadaluarsa, uploaded_at
                 FROM mlite_sdm_berkas
                 WHERE nik LIKE :q OR jenis_berkas LIKE :q OR nama_file LIKE :q
                 ORDER BY id DESC LIMIT 100',
                [':q' => $like]
            ),
            default => throw new \InvalidArgumentException("Entitas master tidak dikenal: '{$entity}'."),
        };
    }

    public function save(string $entity, array $payload): void
    {
        match ($entity) {
            'poliklinik' => $this->savePoliklinik($payload),
            'dokter' => $this->saveDokter($payload),
            'penjab' => $this->savePenjab($payload),
            'jns_perawatan' => $this->saveTarif($payload),
            'jadwal' => $this->saveJadwal($payload),
            'pegawai' => $this->savePegawai($payload),
            'petugas' => $this->savePetugas($payload),
            'bangsal' => $this->saveBangsal($payload),
            'kamar' => $this->saveKamar($payload),
            'penyakit' => $this->savePenyakit($payload),
            'sdm_berkas' => $this->saveSdmBerkas($payload),
            default => throw new \InvalidArgumentException("Entitas master tidak dikenal: '{$entity}'."),
        };
    }

    public function delete(string $entity, array $keys): void
    {
        match ($entity) {
            'poliklinik' => $this->deletePoliklinik((string) ($keys['kd_poli'] ?? '')),
            'dokter' => $this->deleteDokter((string) ($keys['kd_dokter'] ?? '')),
            'penjab' => $this->deletePenjab((string) ($keys['kd_pj'] ?? '')),
            'jns_perawatan' => $this->deleteTarif((string) ($keys['kd_jenis_prw'] ?? '')),
            'jadwal' => $this->deleteJadwal($keys),
            'pegawai' => $this->deletePegawai((string) ($keys['nik'] ?? '')),
            'petugas' => $this->deletePetugas((string) ($keys['nip'] ?? '')),
            'bangsal' => $this->deleteBangsal((string) ($keys['kd_bangsal'] ?? '')),
            'kamar' => $this->deleteKamar((string) ($keys['kd_kamar'] ?? '')),
            'penyakit' => $this->deletePenyakit((string) ($keys['kd_penyakit'] ?? '')),
            'sdm_berkas' => $this->deleteSdmBerkas((int) ($keys['id'] ?? 0)),
            default => throw new \InvalidArgumentException("Entitas master tidak dikenal: '{$entity}'."),
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function savePoliklinik(array $p): void
    {
        $kd = trim((string) ($p['kd_poli'] ?? ''));
        $nm = trim((string) ($p['nm_poli'] ?? ''));
        if ($kd === '' || $nm === '') {
            throw new \InvalidArgumentException('Kode dan nama poliklinik wajib diisi.');
        }

        $exists = $this->exists('SELECT 1 FROM poliklinik WHERE kd_poli = :k', [':k' => $kd]);
        $params = [
            ':k' => $kd,
            ':nm' => $nm,
            ':reg' => (float) ($p['registrasi'] ?? 0),
            ':reg_lama' => (float) ($p['registrasilama'] ?? 0),
            ':stts' => (string) ($p['status'] ?? '1'),
            ':bpjs' => (int) ($p['allow_bpjs'] ?? 1),
        ];
        $sql = $exists
            ? 'UPDATE poliklinik SET nm_poli = :nm, registrasi = :reg, registrasilama = :reg_lama, status = :stts, allow_bpjs = :bpjs WHERE kd_poli = :k'
            : 'INSERT INTO poliklinik (kd_poli, nm_poli, registrasi, registrasilama, status, allow_bpjs) VALUES (:k, :nm, :reg, :reg_lama, :stts, :bpjs)';
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function saveDokter(array $p): void
    {
        $kd = trim((string) ($p['kd_dokter'] ?? ''));
        $nm = trim((string) ($p['nm_dokter'] ?? ''));
        if ($kd === '' || $nm === '') {
            throw new \InvalidArgumentException('Kode dan nama dokter wajib diisi.');
        }

        $exists = $this->exists('SELECT 1 FROM dokter WHERE kd_dokter = :k', [':k' => $kd]);
        $params = [
            ':k' => $kd,
            ':nm' => $nm,
            ':jk' => (string) ($p['jk'] ?? 'L'),
            ':sip' => (string) ($p['no_ijn_praktek'] ?? ''),
            ':stts' => (string) ($p['status'] ?? '1'),
            ':almt' => (string) ($p['almt_tgl'] ?? ''),
            ':telp' => (string) ($p['no_telp'] ?? ''),
        ];
        $sql = $exists
            ? 'UPDATE dokter SET nm_dokter = :nm, jk = :jk, no_ijn_praktek = :sip, status = :stts, almt_tgl = :almt, no_telp = :telp WHERE kd_dokter = :k'
            : 'INSERT INTO dokter (kd_dokter, nm_dokter, jk, no_ijn_praktek, status, almt_tgl, no_telp) VALUES (:k, :nm, :jk, :sip, :stts, :almt, :telp)';
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function savePenjab(array $p): void
    {
        $kd = trim((string) ($p['kd_pj'] ?? ''));
        $nm = trim((string) ($p['png_jawab'] ?? ''));
        if ($kd === '' || $nm === '') {
            throw new \InvalidArgumentException('Kode dan nama penjamin wajib diisi.');
        }

        $exists = $this->exists('SELECT 1 FROM penjab WHERE kd_pj = :k', [':k' => $kd]);
        $params = [
            ':k' => $kd,
            ':nm' => $nm,
            ':stts' => (string) ($p['status'] ?? '1'),
            ':perusahaan' => (string) ($p['nama_perusahaan'] ?? ''),
            ':almt' => (string) ($p['alamat_asuransi'] ?? ''),
            ':telp' => (string) ($p['no_telp'] ?? ''),
        ];
        $sql = $exists
            ? 'UPDATE penjab SET png_jawab = :nm, status = :stts, nama_perusahaan = :perusahaan, alamat_asuransi = :almt, no_telp = :telp WHERE kd_pj = :k'
            : 'INSERT INTO penjab (kd_pj, png_jawab, status, nama_perusahaan, alamat_asuransi, no_telp) VALUES (:k, :nm, :stts, :perusahaan, :almt, :telp)';
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function saveTarif(array $p): void
    {
        $kd = trim((string) ($p['kd_jenis_prw'] ?? ''));
        $nm = trim((string) ($p['nm_perawatan'] ?? ''));
        if ($kd === '' || $nm === '') {
            throw new \InvalidArgumentException('Kode dan nama tindakan wajib diisi.');
        }

        $material = (float) ($p['material'] ?? 0);
        $tarifDr = (float) ($p['tarif_tindakandr'] ?? 0);
        $tarifPr = (float) ($p['tarif_tindakanpr'] ?? 0);
        $totalDr = isset($p['total_byrdr']) ? (float) $p['total_byrdr'] : ($material + $tarifDr);
        $totalPr = isset($p['total_byrpr']) ? (float) $p['total_byrpr'] : ($material + $tarifPr);

        $exists = $this->exists('SELECT 1 FROM jns_perawatan WHERE kd_jenis_prw = :k', [':k' => $kd]);
        $params = [
            ':k' => $kd,
            ':nm' => $nm,
            ':kat' => (string) ($p['kd_kategori'] ?? ''),
            ':mat' => $material,
            ':tdr' => $tarifDr,
            ':tpr' => $tarifPr,
            ':bdr' => $totalDr,
            ':bpr' => $totalPr,
            ':stts' => (string) ($p['status'] ?? '1'),
        ];
        $sql = $exists
            ? 'UPDATE jns_perawatan SET nm_perawatan = :nm, kd_kategori = :kat, material = :mat, tarif_tindakandr = :tdr, tarif_tindakanpr = :tpr, total_byrdr = :bdr, total_byrpr = :bpr, status = :stts WHERE kd_jenis_prw = :k'
            : 'INSERT INTO jns_perawatan (kd_jenis_prw, nm_perawatan, kd_kategori, material, tarif_tindakandr, tarif_tindakanpr, total_byrdr, total_byrpr, status) VALUES (:k, :nm, :kat, :mat, :tdr, :tpr, :bdr, :bpr, :stts)';
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function saveJadwal(array $p): void
    {
        $kdDokter = trim((string) ($p['kd_dokter'] ?? ''));
        $kdPoli = trim((string) ($p['kd_poli'] ?? ''));
        $hari = strtoupper(trim((string) ($p['hari_kerja'] ?? '')));
        $jamMulai = trim((string) ($p['jam_mulai'] ?? ''));
        $jamSelesai = trim((string) ($p['jam_selesai'] ?? ''));
        if ($kdDokter === '' || $kdPoli === '' || $hari === '' || $jamMulai === '' || $jamSelesai === '') {
            throw new \InvalidArgumentException('Dokter, poliklinik, hari kerja, jam mulai, dan jam selesai wajib diisi.');
        }

        $exists = $this->exists(
            'SELECT 1 FROM jadwal WHERE kd_dokter = :d AND kd_poli = :p AND hari_kerja = :h AND jam_mulai = :jm',
            [':d' => $kdDokter, ':p' => $kdPoli, ':h' => $hari, ':jm' => $jamMulai]
        );
        $params = [
            ':d' => $kdDokter,
            ':p' => $kdPoli,
            ':h' => $hari,
            ':jm' => $jamMulai,
            ':js' => $jamSelesai,
            ':kuota' => (int) ($p['kuota'] ?? 0),
            ':prefik' => (string) ($p['prefik'] ?? ''),
            ':room' => (string) ($p['room'] ?? ''),
        ];
        $sql = $exists
            ? 'UPDATE jadwal SET jam_selesai = :js, kuota = :kuota, prefik = :prefik, room = :room WHERE kd_dokter = :d AND kd_poli = :p AND hari_kerja = :h AND jam_mulai = :jm'
            : 'INSERT INTO jadwal (kd_dokter, kd_poli, hari_kerja, jam_mulai, jam_selesai, kuota, prefik, room) VALUES (:d, :p, :h, :jm, :js, :kuota, :prefik, :room)';
        $this->pdo->prepare($sql)->execute($params);
    }

    private function deletePoliklinik(string $kdPoli): void
    {
        $kd = trim($kdPoli);
        if ($kd === '') {
            throw new \InvalidArgumentException('Kode poliklinik wajib diisi.');
        }
        if ($this->exists('SELECT 1 FROM reg_periksa WHERE kd_poli = :k', [':k' => $kd])) {
            throw new \DomainException("Poliklinik '{$kd}' sudah dipakai di riwayat registrasi dan tidak dapat dihapus.");
        }
        $this->pdo->prepare('DELETE FROM poliklinik WHERE kd_poli = :k')->execute([':k' => $kd]);
    }

    private function deleteDokter(string $kdDokter): void
    {
        $kd = trim($kdDokter);
        if ($kd === '') {
            throw new \InvalidArgumentException('Kode dokter wajib diisi.');
        }
        if ($this->exists('SELECT 1 FROM reg_periksa WHERE kd_dokter = :k', [':k' => $kd])) {
            throw new \DomainException("Dokter '{$kd}' sudah memiliki riwayat kunjungan dan tidak dapat dihapus.");
        }
        $this->pdo->prepare('DELETE FROM dokter WHERE kd_dokter = :k')->execute([':k' => $kd]);
    }

    private function deletePenjab(string $kdPj): void
    {
        $kd = trim($kdPj);
        if ($kd === '') {
            throw new \InvalidArgumentException('Kode penjamin wajib diisi.');
        }
        if ($this->exists('SELECT 1 FROM reg_periksa WHERE kd_pj = :k', [':k' => $kd])) {
            throw new \DomainException("Penjamin '{$kd}' sudah dipakai di riwayat registrasi dan tidak dapat dihapus.");
        }
        $this->pdo->prepare('DELETE FROM penjab WHERE kd_pj = :k')->execute([':k' => $kd]);
    }

    private function deleteTarif(string $kdJenisPrw): void
    {
        $kd = trim($kdJenisPrw);
        if ($kd === '') {
            throw new \InvalidArgumentException('Kode tindakan wajib diisi.');
        }
        if ($this->exists('SELECT 1 FROM rawat_jl_dr WHERE kd_jenis_prw = :k', [':k' => $kd])) {
            throw new \DomainException("Tarif '{$kd}' sudah tercatat pada tindakan pasien dan tidak dapat dihapus.");
        }
        $this->pdo->prepare('DELETE FROM jns_perawatan WHERE kd_jenis_prw = :k')->execute([':k' => $kd]);
    }

    /**
     * @param array<string, mixed> $keys
     */
    private function deleteJadwal(array $keys): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM jadwal WHERE kd_dokter = :d AND kd_poli = :p AND hari_kerja = :h AND jam_mulai = :jm'
        );
        $stmt->execute([
            ':d' => trim((string) ($keys['kd_dokter'] ?? '')),
            ':p' => trim((string) ($keys['kd_poli'] ?? '')),
            ':h' => strtoupper(trim((string) ($keys['hari_kerja'] ?? ''))),
            ':jm' => trim((string) ($keys['jam_mulai'] ?? '')),
        ]);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function savePegawai(array $p): void
    {
        $nik = trim((string) ($p['nik'] ?? ''));
        $nama = trim((string) ($p['nama'] ?? ''));
        if ($nik === '' || $nama === '') {
            throw new \InvalidArgumentException('NIK dan nama pegawai wajib diisi.');
        }

        $exists = $this->exists('SELECT 1 FROM pegawai WHERE nik = :k', [':k' => $nik]);
        $params = [
            ':k' => $nik,
            ':nm' => $nama,
            ':jk' => (string) ($p['jk'] ?? 'Pria'),
            ':jbtn' => (string) ($p['jbtn'] ?? ''),
            ':dept' => (string) ($p['departemen'] ?? ''),
            ':stts' => (string) ($p['status'] ?? 'AKTIF'),
        ];
        $sql = $exists
            ? 'UPDATE pegawai SET nama = :nm, jk = :jk, jbtn = :jbtn, departemen = :dept, status = :stts WHERE nik = :k'
            : 'INSERT INTO pegawai (nik, nama, jk, jbtn, departemen, status) VALUES (:k, :nm, :jk, :jbtn, :dept, :stts)';
        $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function savePetugas(array $p): void
    {
        $nip = trim((string) ($p['nip'] ?? ''));
        $nama = trim((string) ($p['nama'] ?? ''));
        if ($nip === '' || $nama === '') {
            throw new \InvalidArgumentException('NIP dan nama petugas wajib diisi.');
        }

        $exists = $this->exists('SELECT 1 FROM petugas WHERE nip = :k', [':k' => $nip]);
        $params = [
            ':k' => $nip,
            ':nm' => $nama,
            ':jk' => (string) ($p['jk'] ?? 'L'),
            ':jbtn' => (string) ($p['jbtn'] ?? ''),
            ':stts' => (string) ($p['status'] ?? '1'),
            ':almt' => (string) ($p['almt_tgl'] ?? ''),
            ':telp' => (string) ($p['no_telp'] ?? ''),
        ];
        $sql = $exists
            ? 'UPDATE petugas SET nama = :nm, jk = :jk, jbtn = :jbtn, status = :stts, almt_tgl = :almt, no_telp = :telp WHERE nip = :k'
            : 'INSERT INTO petugas (nip, nama, jk, jbtn, status, almt_tgl, no_telp) VALUES (:k, :nm, :jk, :jbtn, :stts, :almt, :telp)';
        $this->pdo->prepare($sql)->execute($params);
    }

    private function deletePegawai(string $nik): void
    {
        $k = trim($nik);
        if ($k === '') {
            throw new \InvalidArgumentException('NIK pegawai wajib diisi.');
        }
        $this->pdo->prepare('DELETE FROM pegawai WHERE nik = :k')->execute([':k' => $k]);
    }

    private function deletePetugas(string $nip): void
    {
        $k = trim($nip);
        if ($k === '') {
            throw new \InvalidArgumentException('NIP petugas wajib diisi.');
        }
        $this->pdo->prepare('DELETE FROM petugas WHERE nip = :k')->execute([':k' => $k]);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function saveBangsal(array $p): void
    {
        $k = trim((string) ($p['kd_bangsal'] ?? ''));
        $nm = trim((string) ($p['nm_bangsal'] ?? ''));
        if ($k === '' || $nm === '') {
            throw new \InvalidArgumentException('Kode bangsal dan nama bangsal wajib diisi.');
        }
        $exists = $this->exists('SELECT 1 FROM bangsal WHERE kd_bangsal = :k', [':k' => $k]);
        $sql = $exists
            ? 'UPDATE bangsal SET nm_bangsal = :nm, status = :st WHERE kd_bangsal = :k'
            : 'INSERT INTO bangsal (kd_bangsal, nm_bangsal, status) VALUES (:k, :nm, :st)';
        $this->pdo->prepare($sql)->execute([':k' => $k, ':nm' => $nm, ':st' => (string) ($p['status'] ?? '1')]);
    }

    private function deleteBangsal(string $kd): void
    {
        $k = trim($kd);
        if ($k === '') {
            throw new \InvalidArgumentException('Kode bangsal wajib diisi.');
        }
        $this->pdo->prepare('DELETE FROM bangsal WHERE kd_bangsal = :k')->execute([':k' => $k]);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function saveKamar(array $p): void
    {
        $k = trim((string) ($p['kd_kamar'] ?? ''));
        $kb = trim((string) ($p['kd_bangsal'] ?? ''));
        if ($k === '' || $kb === '') {
            throw new \InvalidArgumentException('Kode kamar dan kode bangsal wajib diisi.');
        }
        $exists = $this->exists('SELECT 1 FROM kamar WHERE kd_kamar = :k', [':k' => $k]);
        $params = [
            ':k' => $k,
            ':kb' => $kb,
            ':trf' => (float) ($p['trf_kamar'] ?? 0),
            ':st' => (string) ($p['status'] ?? 'KOSONG'),
            ':kls' => (string) ($p['kelas'] ?? 'Kelas 3'),
        ];
        $sql = $exists
            ? 'UPDATE kamar SET kd_bangsal = :kb, trf_kamar = :trf, status = :st, kelas = :kls WHERE kd_kamar = :k'
            : "INSERT INTO kamar (kd_kamar, kd_bangsal, trf_kamar, status, kelas, statusdata) VALUES (:k, :kb, :trf, :st, :kls, '1')";
        $this->pdo->prepare($sql)->execute($params);
    }

    private function deleteKamar(string $kd): void
    {
        $k = trim($kd);
        if ($k === '') {
            throw new \InvalidArgumentException('Kode kamar wajib diisi.');
        }
        $this->pdo->prepare('DELETE FROM kamar WHERE kd_kamar = :k')->execute([':k' => $k]);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function savePenyakit(array $p): void
    {
        $k = strtoupper(trim((string) ($p['kd_penyakit'] ?? '')));
        $nm = trim((string) ($p['nm_penyakit'] ?? ''));
        if ($k === '' || $nm === '') {
            throw new \InvalidArgumentException('Kode ICD-10 dan nama penyakit wajib diisi.');
        }
        $exists = $this->exists('SELECT 1 FROM penyakit WHERE kd_penyakit = :k', [':k' => $k]);
        $params = [
            ':k' => $k,
            ':nm' => $nm,
            ':idn' => trim((string) ($p['nm_indonesia'] ?? '')),
            ':st' => trim((string) ($p['status'] ?? 'Tidak Menular')) ?: 'Tidak Menular',
            ':skdi' => trim((string) ($p['skdi_level'] ?? '4A')) ?: '4A',
        ];
        $sql = $exists
            ? 'UPDATE penyakit SET nm_penyakit = :nm, nm_indonesia = :idn, status = :st, skdi_level = :skdi WHERE kd_penyakit = :k'
            : 'INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia, status, skdi_level) VALUES (:k, :nm, :idn, :st, :skdi)';
        $this->pdo->prepare($sql)->execute($params);
    }

    private function deletePenyakit(string $kd): void
    {
        $k = strtoupper(trim($kd));
        if ($k === '') {
            throw new \InvalidArgumentException('Kode ICD-10 wajib diisi.');
        }
        $this->pdo->prepare('DELETE FROM penyakit WHERE kd_penyakit = :k')->execute([':k' => $k]);
    }

    /**
     * @param array<string, mixed> $p
     */
    private function saveSdmBerkas(array $p): void
    {
        $nik = trim((string) ($p['nik'] ?? ''));
        $jenis = trim((string) ($p['jenis_berkas'] ?? 'STR')) ?: 'STR';
        $namaFile = trim((string) ($p['nama_file'] ?? ''));
        $mime = trim((string) ($p['mime_type'] ?? 'application/pdf')) ?: 'application/pdf';
        if ($nik === '' || $namaFile === '') {
            throw new \InvalidArgumentException('NIK/NIP dan nama file berkas wajib diisi.');
        }
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \InvalidArgumentException('Tipe berkas tidak diizinkan (hanya PDF/JPG/PNG/WEBP).');
        }
        $size = max(0, (int) ($p['file_size'] ?? 0));
        if ($size > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('Ukuran berkas maksimal 5 MB.');
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($namaFile)) ?: 'berkas';
        $storagePath = 'sdm_berkas/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $nik) . '_' . uniqid('', true) . '_' . $safeName;
        $rawBase64 = trim((string) ($p['content_base64'] ?? ''));
        if ($rawBase64 !== '') {
            if (str_contains($rawBase64, ',')) {
                $rawBase64 = substr($rawBase64, strpos($rawBase64, ',') + 1);
            }
            $binary = base64_decode($rawBase64, true);
            if ($binary !== false) {
                $size = strlen($binary);
                $fullDir = dirname(__DIR__, 3) . '/storage/sdm_berkas';
                if (!is_dir($fullDir)) {
                    mkdir($fullDir, 0775, true);
                }
                file_put_contents(dirname(__DIR__, 3) . '/storage/' . $storagePath, $binary);
            }
        }

        $this->pdo->prepare(
            'INSERT INTO mlite_sdm_berkas (nik, jenis_berkas, nama_file, mime_type, file_size, storage_path, tgl_kadaluarsa, uploaded_at)
             VALUES (:nik, :jb, :nf, :mt, :fs, :sp, :tk, :ua)'
        )->execute([
            ':nik' => $nik,
            ':jb' => $jenis,
            ':nf' => $safeName,
            ':mt' => $mime,
            ':fs' => $size,
            ':sp' => $storagePath,
            ':tk' => trim((string) ($p['tgl_kadaluarsa'] ?? '')),
            ':ua' => date('Y-m-d H:i:s'),
        ]);
    }

    private function deleteSdmBerkas(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID berkas SDM tidak valid.');
        }
        $this->pdo->prepare('DELETE FROM mlite_sdm_berkas WHERE id = :id')->execute([':id' => $id]);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function exists(string $sql, array $params): bool
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() !== false;
    }
}
