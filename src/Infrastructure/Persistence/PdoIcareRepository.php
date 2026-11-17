<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\IcareRepositoryInterface;
use PDO;

final class PdoIcareRepository implements IcareRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findVisitParticipant(string $noRawat): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.no_rawat, r.kd_dokter, r.kd_poli, p.no_rkm_medis, p.nm_pasien, p.no_peserta, p.no_ktp, d.nm_dokter
             FROM reg_periksa r
             INNER JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
             LEFT JOIN dokter d ON d.kd_dokter = r.kd_dokter
             WHERE r.no_rawat = :nr
             LIMIT 1'
        );
        $stmt->execute([':nr' => $noRawat]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
