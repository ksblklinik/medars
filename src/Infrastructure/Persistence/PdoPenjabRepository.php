<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Master\Entity\Penjab;
use App\Domain\Master\Repository\PenjabRepositoryInterface;
use PDO;

final class PdoPenjabRepository implements PenjabRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findAllActive(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM penjab WHERE status = '1' ORDER BY png_jawab ASC");
        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByKode(string $kdPj): ?Penjab
    {
        $stmt = $this->pdo->prepare("SELECT * FROM penjab WHERE kd_pj = :kd LIMIT 1");
        $stmt->execute([':kd' => $kdPj]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(Penjab $penjab): void
    {
        $existing = $this->findByKode($penjab->getKdPj());

        if ($existing === null) {
            $sql = "
                INSERT INTO penjab (kd_pj, png_jawab, status, nama_perusahaan, alamat_asuransi, no_telp)
                VALUES (:kd, :png, :stts, :pt, :almt, :telp)
            ";
        } else {
            $sql = "
                UPDATE penjab
                SET png_jawab = :png,
                    status = :stts,
                    nama_perusahaan = :pt,
                    alamat_asuransi = :almt,
                    no_telp = :telp
                WHERE kd_pj = :kd
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':kd' => $penjab->getKdPj(),
            ':png' => $penjab->getPngJawab(),
            ':stts' => $penjab->getStatus(),
            ':pt' => $penjab->getNamaPerusahaan(),
            ':almt' => $penjab->getAlamatAsuransi(),
            ':telp' => $penjab->getNoTelp(),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Penjab
    {
        return new Penjab(
            kdPj: (string) $row['kd_pj'],
            pngJawab: (string) $row['png_jawab'],
            status: (string) ($row['status'] ?? '1'),
            namaPerusahaan: (string) ($row['nama_perusahaan'] ?? ''),
            alamatAsuransi: (string) ($row['alamat_asuransi'] ?? ''),
            noTelp: (string) ($row['no_telp'] ?? '')
        );
    }
}
