<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Staff\Entity\Petugas;
use App\Domain\Staff\Repository\PetugasRepositoryInterface;
use PDO;

final class PdoPetugasRepository implements PetugasRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findAllActive(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM petugas WHERE status = '1' ORDER BY nama ASC");
        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByNip(string $nip): ?Petugas
    {
        $stmt = $this->pdo->prepare("SELECT * FROM petugas WHERE nip = :nip LIMIT 1");
        $stmt->execute([':nip' => $nip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(Petugas $petugas): void
    {
        $existing = $this->findByNip($petugas->getNip());

        if ($existing === null) {
            $sql = "
                INSERT INTO petugas (nip, nama, jk, jbtn, status, almt_tgl, no_telp)
                VALUES (:nip, :nama, :jk, :jbtn, :stts, :almt, :telp)
            ";
        } else {
            $sql = "
                UPDATE petugas
                SET nama = :nama,
                    jk = :jk,
                    jbtn = :jbtn,
                    status = :stts,
                    almt_tgl = :almt,
                    no_telp = :telp
                WHERE nip = :nip
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nip' => $petugas->getNip(),
            ':nama' => $petugas->getNama(),
            ':jk' => $petugas->getJk(),
            ':jbtn' => $petugas->getJbtn(),
            ':stts' => $petugas->getStatus(),
            ':almt' => $petugas->getAlmtTgl(),
            ':telp' => $petugas->getNoTelp(),
        ]);
    }

    public function deactivate(string $nip): void
    {
        $stmt = $this->pdo->prepare("UPDATE petugas SET status = '0' WHERE nip = :nip");
        $stmt->execute([':nip' => $nip]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Petugas
    {
        return new Petugas(
            nip: (string) $row['nip'],
            nama: (string) $row['nama'],
            jk: (string) $row['jk'],
            jbtn: (string) $row['jbtn'],
            status: (string) ($row['status'] ?? '1'),
            almtTgl: (string) ($row['almt_tgl'] ?? ''),
            noTelp: (string) ($row['no_telp'] ?? '')
        );
    }
}
