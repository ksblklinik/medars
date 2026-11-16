<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Repository\RmeHistoryRepositoryInterface;
use App\Infrastructure\Database\ReadOnlyConnectionGuard;
use PDO;

final class PdoRmeHistoryRepository implements RmeHistoryRepositoryInterface
{
    public function __construct(
        private readonly PDO $nextPdo,
        private readonly ?ReadOnlyConnectionGuard $legacyGuard = null,
    ) {
    }

    public function getFullMedicalHistory(NoRkmMedis $noRkmMedis): array
    {
        $rm = $noRkmMedis->value();
        $nextRows = $this->fetchFromNext($rm);
        $legacyRows = $this->fetchFromLegacy($rm);

        $merged = array_merge($nextRows, $legacyRows);

        usort($merged, static function (array $a, array $b): int {
            $dateA = ($a['tgl_perawatan'] ?? $a['tgl_registrasi'] ?? '') . ' ' . ($a['jam_rawat'] ?? $a['jam_reg'] ?? '');
            $dateB = ($b['tgl_perawatan'] ?? $b['tgl_registrasi'] ?? '') . ' ' . ($b['jam_rawat'] ?? $b['jam_reg'] ?? '');
            return strcmp($dateB, $dateA);
        });

        return $merged;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFromNext(string $noRkmMedis): array
    {
        $sql = "
            SELECT
                r.no_rawat,
                r.tgl_registrasi,
                r.jam_reg,
                r.kd_dokter,
                d.nm_dokter,
                r.kd_poli,
                p.nm_poli,
                pr.tgl_perawatan,
                pr.jam_rawat,
                pr.suhu_tubuh,
                pr.tensi,
                pr.nadi,
                pr.respirasi,
                pr.keluhan,
                pr.pemeriksaan,
                pr.penilaian,
                pr.rtl,
                pr.instruksi,
                pr.evaluasi,
                pr.nip
            FROM reg_periksa r
            INNER JOIN pemeriksaan_ralan pr ON r.no_rawat = pr.no_rawat
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik p ON r.kd_poli = p.kd_poli
            WHERE r.no_rkm_medis = :rm
            ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
        ";

        $stmt = $this->nextPdo->prepare($sql);
        $stmt->execute([':rm' => $noRkmMedis]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['source'] = 'next';
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFromLegacy(string $noRkmMedis): array
    {
        if ($this->legacyGuard === null) {
            return [];
        }

        $sql = "
            SELECT
                r.no_rawat,
                r.tgl_registrasi,
                r.jam_reg,
                r.kd_dokter,
                d.nm_dokter,
                r.kd_poli,
                p.nm_poli,
                pr.tgl_perawatan,
                pr.jam_rawat,
                pr.suhu_tubuh,
                pr.tensi,
                pr.nadi,
                pr.respirasi,
                pr.keluhan,
                pr.pemeriksaan,
                pr.penilaian,
                pr.rtl,
                pr.instruksi,
                pr.evaluasi,
                pr.nip
            FROM reg_periksa r
            INNER JOIN pemeriksaan_ralan pr ON r.no_rawat = pr.no_rawat
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN poliklinik p ON r.kd_poli = p.kd_poli
            WHERE r.no_rkm_medis = :rm
            ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC
        ";

        $rows = $this->legacyGuard->query($sql, ['rm' => $noRkmMedis]);

        foreach ($rows as &$row) {
            $row['source'] = 'legacy';
        }

        return $rows;
    }
}
