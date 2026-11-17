<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\VclaimRepositoryInterface;
use PDO;

final class PdoVclaimRepository implements VclaimRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findPesertaLocal(string $noKartuOrNik): ?array
    {
        $q = trim($noKartuOrNik);
        if ($q === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT no_rkm_medis, nm_pasien, no_ktp, no_peserta, tgl_lahir, jk, alamat
             FROM pasien
             WHERE no_peserta = :q OR no_ktp = :q OR no_rkm_medis = :q
             LIMIT 1'
        );
        $stmt->execute([':q' => $q]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function listSep(string $from = '', string $to = '', string $search = ''): array
    {
        $sql = 'SELECT * FROM bridging_sep WHERE 1=1';
        $params = [];
        if ($from !== '' && $to !== '') {
            $sql .= ' AND tglsep BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' AND (no_sep LIKE :q OR no_rawat LIKE :q OR no_kartu LIKE :q OR nama_pasien LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY tglsep DESC, no_sep DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveSep(array $data): array
    {
        $noSep = trim((string) ($data['no_sep'] ?? ''));
        if ($noSep === '') {
            $noSep = '0112R001' . date('my') . 'V' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $noRawat = trim((string) ($data['no_rawat'] ?? ''));
        $tglSep = trim((string) ($data['tglsep'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $noKartu = trim((string) ($data['no_kartu'] ?? ''));
        $nama = trim((string) ($data['nama_pasien'] ?? ''));
        $kdPoli = trim((string) ($data['kdpolitujuan'] ?? ''));
        $nmPoli = trim((string) ($data['nmpolitujuan'] ?? ''));
        $diag = trim((string) ($data['diagawal'] ?? ''));
        $nmDiag = trim((string) ($data['nmdiagnosaawal'] ?? ''));
        $jns = trim((string) ($data['jnspelayanan'] ?? '2')) ?: '2';
        $kls = trim((string) ($data['klsrawat'] ?? '3')) ?: '3';
        $catatan = trim((string) ($data['catatan'] ?? ''));
        $user = trim((string) ($data['user_input'] ?? 'system')) ?: 'system';

        $stmt = $this->pdo->prepare(
            'INSERT INTO bridging_sep (no_sep, no_rawat, tglsep, no_kartu, nama_pasien, kdpolitujuan, nmpolitujuan, diagawal, nmdiagnosaawal, jnspelayanan, klsrawat, catatan, user_input)
             VALUES (:ns, :nr, :ts, :nk, :np, :kp, :nmp, :da, :nda, :jp, :kr, :ct, :ui)'
        );
        $stmt->execute([
            ':ns' => $noSep, ':nr' => $noRawat, ':ts' => $tglSep, ':nk' => $noKartu,
            ':np' => $nama, ':kp' => $kdPoli, ':nmp' => $nmPoli, ':da' => $diag,
            ':nda' => $nmDiag, ':jp' => $jns, ':kr' => $kls, ':ct' => $catatan, ':ui' => $user,
        ]);

        return [
            'no_sep' => $noSep,
            'no_rawat' => $noRawat,
            'tglsep' => $tglSep,
            'no_kartu' => $noKartu,
            'nama_pasien' => $nama,
            'kdpolitujuan' => $kdPoli,
            'nmpolitujuan' => $nmPoli,
            'diagawal' => $diag,
        ];
    }

    public function findSep(string $noSep): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bridging_sep WHERE no_sep = :ns LIMIT 1');
        $stmt->execute([':ns' => trim($noSep)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function listRujukan(string $search = ''): array
    {
        $sql = 'SELECT * FROM bridging_rujukan_bpjs';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE no_rujukan LIKE :q OR no_sep LIKE :q OR nm_ppk_dirujuk LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY tgl_rujukan DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveRujukan(array $data): array
    {
        $noRujukan = trim((string) ($data['no_rujukan'] ?? ''));
        if ($noRujukan === '') {
            $noRujukan = '0112R001' . date('my') . 'B' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $noSep = trim((string) ($data['no_sep'] ?? ''));
        $tgl = trim((string) ($data['tgl_rujukan'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $ppk = trim((string) ($data['ppk_dirujuk'] ?? ''));
        $nmPpk = trim((string) ($data['nm_ppk_dirujuk'] ?? ''));
        $jns = trim((string) ($data['jns_pelayanan'] ?? '2')) ?: '2';
        $diag = trim((string) ($data['diag_rujukan'] ?? ''));
        $tipe = trim((string) ($data['tipe_rujukan'] ?? '0')) ?: '0';
        $cat = trim((string) ($data['catatan'] ?? ''));

        $stmt = $this->pdo->prepare(
            'INSERT INTO bridging_rujukan_bpjs (no_rujukan, no_sep, tgl_rujukan, ppk_dirujuk, nm_ppk_dirujuk, jns_pelayanan, diag_rujukan, tipe_rujukan, catatan)
             VALUES (:nrj, :ns, :tgl, :ppk, :nppk, :jp, :dr, :tr, :ct)'
        );
        $stmt->execute([
            ':nrj' => $noRujukan, ':ns' => $noSep, ':tgl' => $tgl, ':ppk' => $ppk,
            ':nppk' => $nmPpk, ':jp' => $jns, ':dr' => $diag, ':tr' => $tipe, ':ct' => $cat,
        ]);

        return [
            'no_rujukan' => $noRujukan,
            'no_sep' => $noSep,
            'tgl_rujukan' => $tgl,
            'nm_ppk_dirujuk' => $nmPpk,
        ];
    }

    public function listSuratKontrol(string $search = ''): array
    {
        $sql = 'SELECT * FROM bridging_surat_kontrol_bpjs';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE no_surat LIKE :q OR no_sep LIKE :q OR nm_dokter_bpjs LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY tgl_rencana DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveSuratKontrol(array $data): array
    {
        $noSurat = trim((string) ($data['no_surat'] ?? ''));
        if ($noSurat === '') {
            $noSurat = '0112R001' . date('my') . 'K' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $noSep = trim((string) ($data['no_sep'] ?? ''));
        $tglSurat = trim((string) ($data['tgl_surat'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $tglRencana = trim((string) ($data['tgl_rencana'] ?? date('Y-m-d', strtotime('+7 days')))) ?: date('Y-m-d', strtotime('+7 days'));
        $kdDokter = trim((string) ($data['kd_dokter_bpjs'] ?? ''));
        $nmDokter = trim((string) ($data['nm_dokter_bpjs'] ?? ''));
        $kdPoli = trim((string) ($data['kd_poli_bpjs'] ?? ''));
        $nmPoli = trim((string) ($data['nm_poli_bpjs'] ?? ''));

        $stmt = $this->pdo->prepare(
            'INSERT INTO bridging_surat_kontrol_bpjs (no_surat, no_sep, tgl_surat, tgl_rencana, kd_dokter_bpjs, nm_dokter_bpjs, kd_poli_bpjs, nm_poli_bpjs)
             VALUES (:nsu, :nse, :ts, :tr, :kd, :nd, :kp, :np)'
        );
        $stmt->execute([
            ':nsu' => $noSurat, ':nse' => $noSep, ':ts' => $tglSurat, ':tr' => $tglRencana,
            ':kd' => $kdDokter, ':nd' => $nmDokter, ':kp' => $kdPoli, ':np' => $nmPoli,
        ]);

        return [
            'no_surat' => $noSurat,
            'no_sep' => $noSep,
            'tgl_rencana' => $tglRencana,
            'nm_dokter_bpjs' => $nmDokter,
            'nm_poli_bpjs' => $nmPoli,
        ];
    }
}
