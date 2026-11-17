<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\Repository\FrontOfficeRepositoryInterface;

final readonly class PdoFrontOfficeRepository implements FrontOfficeRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function getTodayStats(string $date): array
    {
        $stmtReg = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total_kunjungan,
                SUM(CASE WHEN stts = 'Belum' THEN 1 ELSE 0 END) AS belum_periksa,
                SUM(CASE WHEN stts = 'Sudah' THEN 1 ELSE 0 END) AS sudah_periksa,
                SUM(CASE WHEN stts = 'Batal' THEN 1 ELSE 0 END) AS batal,
                SUM(CASE WHEN status_bayar = 'Belum Bayar' AND stts != 'Batal' THEN 1 ELSE 0 END) AS belum_bayar
            FROM reg_periksa
            WHERE tgl_registrasi = :tgl
        ");
        $stmtReg->execute([':tgl' => $date]);
        $r = $stmtReg->fetch(\PDO::FETCH_ASSOC) ?: [];

        $stmtBook = $this->pdo->prepare("
            SELECT COUNT(*) FROM booking_registrasi
            WHERE tanggal_periksa = :tgl AND status = 'Belum'
        ");
        $stmtBook->execute([':tgl' => $date]);

        return [
            'total_kunjungan' => (int) ($r['total_kunjungan'] ?? 0),
            'belum_periksa' => (int) ($r['belum_periksa'] ?? 0),
            'sudah_periksa' => (int) ($r['sudah_periksa'] ?? 0),
            'batal' => (int) ($r['batal'] ?? 0),
            'booking_menunggu' => (int) $stmtBook->fetchColumn(),
            'belum_bayar' => (int) ($r['belum_bayar'] ?? 0),
        ];
    }

    public function listTodayBookings(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT b.no_rkm_medis, b.tanggal_periksa, b.kd_dokter, b.kd_poli, b.no_reg, b.kd_pj, b.status,
                   p.nm_pasien, pol.nm_poli, d.nm_dokter
            FROM booking_registrasi b
            LEFT JOIN pasien p ON b.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON b.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON b.kd_dokter = d.kd_dokter
            WHERE b.tanggal_periksa = :tgl
            ORDER BY b.no_reg ASC
        ");
        $stmt->execute([':tgl' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listTodayVisits(string $date): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.no_reg, r.no_rawat, r.no_rkm_medis, r.stts, r.status_bayar, r.jam_reg,
                   p.nm_pasien, pol.nm_poli, d.nm_dokter, pj.png_jawab
            FROM reg_periksa r
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.tgl_registrasi = :tgl
            ORDER BY r.no_reg ASC
        ");
        $stmt->execute([':tgl' => $date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
