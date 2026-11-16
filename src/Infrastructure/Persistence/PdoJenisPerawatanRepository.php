<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Master\Entity\JenisPerawatan;
use App\Domain\Master\Repository\JenisPerawatanRepositoryInterface;
use PDO;

final class PdoJenisPerawatanRepository implements JenisPerawatanRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findAllActive(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM jns_perawatan WHERE status = '1' ORDER BY nm_perawatan ASC");
        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByKode(string $kdJenisPrw): ?JenisPerawatan
    {
        $stmt = $this->pdo->prepare("SELECT * FROM jns_perawatan WHERE kd_jenis_prw = :kd LIMIT 1");
        $stmt->execute([':kd' => $kdJenisPrw]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(JenisPerawatan $jenisPerawatan): void
    {
        $existing = $this->findByKode($jenisPerawatan->getKdJenisPrw());

        if ($existing === null) {
            $sql = "
                INSERT INTO jns_perawatan (
                    kd_jenis_prw, nm_perawatan, kd_kategori, material,
                    tarif_tindakandr, tarif_tindakanpr, total_byrdr, total_byrpr, status
                ) VALUES (
                    :kd, :nm, :kat, :mat, :tdr, :tpr, :bdr, :bpr, :stts
                )
            ";
        } else {
            $sql = "
                UPDATE jns_perawatan
                SET nm_perawatan = :nm,
                    kd_kategori = :kat,
                    material = :mat,
                    tarif_tindakandr = :tdr,
                    tarif_tindakanpr = :tpr,
                    total_byrdr = :bdr,
                    total_byrpr = :bpr,
                    status = :stts
                WHERE kd_jenis_prw = :kd
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':kd' => $jenisPerawatan->getKdJenisPrw(),
            ':nm' => $jenisPerawatan->getNmPerawatan(),
            ':kat' => $jenisPerawatan->getKdKategori(),
            ':mat' => $jenisPerawatan->getMaterial(),
            ':tdr' => $jenisPerawatan->getTarifTindakanDr(),
            ':tpr' => $jenisPerawatan->getTarifTindakanPr(),
            ':bdr' => $jenisPerawatan->getTotalByrDr(),
            ':bpr' => $jenisPerawatan->getTotalByrPr(),
            ':stts' => $jenisPerawatan->getStatus(),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): JenisPerawatan
    {
        return new JenisPerawatan(
            kdJenisPrw: (string) $row['kd_jenis_prw'],
            nmPerawatan: (string) $row['nm_perawatan'],
            kdKategori: (string) $row['kd_kategori'],
            material: (float) ($row['material'] ?? 0.0),
            tarifTindakanDr: (float) ($row['tarif_tindakandr'] ?? 0.0),
            tarifTindakanPr: (float) ($row['tarif_tindakanpr'] ?? 0.0),
            totalByrDr: (float) ($row['total_byrdr'] ?? 0.0),
            totalByrPr: (float) ($row['total_byrpr'] ?? 0.0),
            status: (string) ($row['status'] ?? '1')
        );
    }
}
