<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Pharmacy\Entity\DataBarang;
use App\Domain\Pharmacy\Repository\DataBarangRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\UniformPricing;
use PDO;

final class PdoDataBarangRepository implements DataBarangRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findByKode(string $kodeBrng): ?DataBarang
    {
        $stmt = $this->pdo->prepare("SELECT * FROM databarang WHERE kode_brng = :kd LIMIT 1");
        $stmt->execute([':kd' => $kodeBrng]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function searchWithUniformPricing(string $keyword, bool $isBpjs = false, int $limit = 20): array
    {
        $like = '%' . trim($keyword) . '%';
        $stmt = $this->pdo->prepare("
            SELECT * FROM databarang
            WHERE status = '1'
              AND (kode_brng LIKE :q1 OR nama_brng LIKE :q2)
            ORDER BY nama_brng ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':q1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $item = $this->hydrate($row);
            $results[] = [
                'kode_brng' => $item->kodeBrng,
                'nama_brng' => $item->namaBrng,
                'kode_sat' => $item->kodeSat,
                'harga_tunai' => $item->pricing->hargaTunai,
                'harga_kapitasi' => $item->pricing->hargaKapitasi,
                'harga_nonkapitasi' => $item->pricing->hargaNonKapitasi,
                'jualbebas' => $item->pricing->jualBebas,
                'h_beli' => $item->pricing->hBeli,
                'claim_options' => $item->pricing->getAvailableClaimOptions($isBpjs),
            ];
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): DataBarang
    {
        return new DataBarang(
            kodeBrng: (string) $row['kode_brng'],
            namaBrng: (string) $row['nama_brng'],
            kodeSat: (string) ($row['kode_sat'] ?? 'TAB'),
            pricing: new UniformPricing(
                hargaTunai: (float) ($row['harga_tunai'] ?? 0),
                hargaKapitasi: (float) ($row['harga_kapitasi'] ?? 0),
                hargaNonKapitasi: (float) ($row['harga_nonkapitasi'] ?? 0),
                jualBebas: (float) ($row['jualbebas'] ?? 0),
                dasar: (float) ($row['dasar'] ?? 0),
                hBeli: (float) ($row['h_beli'] ?? 0)
            ),
            status: (string) ($row['status'] ?? '1')
        );
    }
}
