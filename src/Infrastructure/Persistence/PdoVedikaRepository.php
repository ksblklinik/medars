<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\VedikaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use PDO;

final readonly class PdoVedikaRepository implements VedikaRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function registrationExists(NoRawat $noRawat): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM reg_periksa WHERE no_rawat = :nr');
        $stmt->execute([':nr' => $noRawat->value()]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function countSoapRalan(NoRawat $noRawat): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM pemeriksaan_ralan WHERE no_rawat = :nr');
        $stmt->execute([':nr' => $noRawat->value()]);
        return (int) $stmt->fetchColumn();
    }

    public function countDiagnosa(NoRawat $noRawat): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM diagnosa_pasien WHERE no_rawat = :nr');
        $stmt->execute([':nr' => $noRawat->value()]);
        return (int) $stmt->fetchColumn();
    }

    public function saveVerification(
        NoRawat $noRawat,
        string $noSep,
        string $jenisPelayanan,
        string $status,
        string $catatan,
        string $verifiedBy,
        string $verifiedAt,
    ): void {
        $nr = $noRawat->value();
        $del = $this->pdo->prepare('DELETE FROM mlite_vedika WHERE no_rawat = :nr');
        $del->execute([':nr' => $nr]);

        $ins = $this->pdo->prepare(
            'INSERT INTO mlite_vedika (no_rawat, no_sep, jenis_pelayanan, status, catatan, verified_by, verified_at)
             VALUES (:nr, :sep, :jp, :stts, :cat, :by, :at)'
        );
        $ins->execute([
            ':nr' => $nr,
            ':sep' => $noSep,
            ':jp' => $jenisPelayanan,
            ':stts' => $status,
            ':cat' => $catatan,
            ':by' => $verifiedBy,
            ':at' => $verifiedAt,
        ]);
    }

    public function listClaims(string $from, string $to, string $status = ''): array
    {
        $sql = "
            SELECT r.no_rawat, r.tgl_registrasi, r.status_lanjut, p.no_rkm_medis, p.nm_pasien,
                   pol.nm_poli, d.nm_dokter, v.no_sep, v.status AS vedika_status, v.catatan, v.verified_by, v.verified_at
            FROM reg_periksa r
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN mlite_vedika v ON r.no_rawat = v.no_rawat
            WHERE r.tgl_registrasi BETWEEN :from AND :to
              AND (UPPER(r.kd_pj) = 'BPJ' OR v.no_rawat IS NOT NULL)
        ";
        $params = [':from' => $from, ':to' => $to];
        if (trim($status) !== '') {
            $sql .= ' AND v.status = :st';
            $params[':st'] = trim($status);
        }
        $sql .= ' ORDER BY r.tgl_registrasi DESC, r.no_rawat DESC LIMIT 100';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBundleData(string $noRawat): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.no_rawat, r.tgl_registrasi, r.status_lanjut, p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk,
                   pol.nm_poli, d.nm_dokter, v.no_sep, v.status AS vedika_status, v.catatan
            FROM reg_periksa r
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN mlite_vedika v ON r.no_rawat = v.no_rawat
            WHERE r.no_rawat = :nr LIMIT 1
        ");
        $stmt->execute([':nr' => trim($noRawat)]);
        $visit = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($visit === false) {
            return null;
        }

        $soapStmt = $this->pdo->prepare('SELECT tgl_perawatan, jam_rawat, keluhan, pemeriksaan, penilaian, rtl FROM pemeriksaan_ralan WHERE no_rawat = :nr ORDER BY tgl_perawatan DESC LIMIT 5');
        $soapStmt->execute([':nr' => trim($noRawat)]);

        $diagStmt = $this->pdo->prepare('SELECT dp.kd_penyakit, COALESCE(py.nm_indonesia, py.nm_penyakit, dp.kd_penyakit) AS nm_penyakit, dp.prioritas FROM diagnosa_pasien dp LEFT JOIN penyakit py ON dp.kd_penyakit = py.kd_penyakit WHERE dp.no_rawat = :nr ORDER BY dp.prioritas ASC');
        $diagStmt->execute([':nr' => trim($noRawat)]);

        $billStmt = $this->pdo->prepare("SELECT item_name, category, qty, claim_type, total_amount FROM mlite_billing_ledger WHERE no_rawat = :nr AND status != 'Cancelled'");
        $billStmt->execute([':nr' => trim($noRawat)]);

        return [
            'visit' => $visit,
            'soap' => $soapStmt->fetchAll(PDO::FETCH_ASSOC),
            'diagnosa' => $diagStmt->fetchAll(PDO::FETCH_ASSOC),
            'billing' => $billStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
