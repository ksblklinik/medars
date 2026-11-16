<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Staff\Entity\Dokter;
use App\Domain\Staff\Repository\DokterRepositoryInterface;
use PDO;

final class PdoDokterRepository implements DokterRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findAllActive(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM dokter WHERE status = '1' ORDER BY nm_dokter ASC");
        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByKode(string $kdDokter): ?Dokter
    {
        $stmt = $this->pdo->prepare("SELECT * FROM dokter WHERE kd_dokter = :kd LIMIT 1");
        $stmt->execute([':kd' => $kdDokter]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(Dokter $dokter): void
    {
        $existing = $this->findByKode($dokter->getKdDokter());

        if ($existing === null) {
            $sql = "
                INSERT INTO dokter (kd_dokter, nm_dokter, jk, no_ijn_praktek, status, almt_tgl, no_telp)
                VALUES (:kd, :nm, :jk, :sip, :stts, :almt, :telp)
            ";
        } else {
            $sql = "
                UPDATE dokter
                SET nm_dokter = :nm,
                    jk = :jk,
                    no_ijn_praktek = :sip,
                    status = :stts,
                    almt_tgl = :almt,
                    no_telp = :telp
                WHERE kd_dokter = :kd
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':kd' => $dokter->getKdDokter(),
            ':nm' => $dokter->getNmDokter(),
            ':jk' => $dokter->getJk(),
            ':sip' => $dokter->getNoIjnPraktek(),
            ':stts' => $dokter->getStatus(),
            ':almt' => $dokter->getAlmtTgl(),
            ':telp' => $dokter->getNoTelp(),
        ]);
    }

    public function canHardDelete(string $kdDokter): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reg_periksa WHERE kd_dokter = :kd");
        $stmt->execute([':kd' => $kdDokter]);
        $count = (int) $stmt->fetchColumn();

        return $count === 0;
    }

    public function deactivate(string $kdDokter): void
    {
        $stmt = $this->pdo->prepare("UPDATE dokter SET status = '0' WHERE kd_dokter = :kd");
        $stmt->execute([':kd' => $kdDokter]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Dokter
    {
        return new Dokter(
            kdDokter: (string) $row['kd_dokter'],
            nmDokter: (string) $row['nm_dokter'],
            jk: (string) $row['jk'],
            noIjnPraktek: (string) ($row['no_ijn_praktek'] ?? ''),
            status: (string) ($row['status'] ?? '1'),
            almtTgl: (string) ($row['almt_tgl'] ?? ''),
            noTelp: (string) ($row['no_telp'] ?? '')
        );
    }
}
