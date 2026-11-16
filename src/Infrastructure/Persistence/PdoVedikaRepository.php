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
}
