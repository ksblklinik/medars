<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Pharmacy\Entity\GudangBarang;
use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Pharmacy\Repository\GudangBarangRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use PDO;

final class PdoGudangBarangRepository implements GudangBarangRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getStock(string $kodeBrng, DepoBangsal $depo): float
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(stok), 0)
            FROM gudangbarang
            WHERE kode_brng = :kd AND kd_bangsal = :depo
        ");
        $stmt->execute([
            ':kd' => $kodeBrng,
            ':depo' => $depo->value(),
        ]);

        return (float) $stmt->fetchColumn();
    }

    public function deductStock(
        string $kodeBrng,
        DepoBangsal $depo,
        float $qty,
        string $tanggal,
        string $jam,
        string $petugas,
        string $keterangan = ''
    ): array {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah pengurangan stok harus > 0.');
        }

        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lockClause = $driver === 'mysql' ? ' FOR UPDATE' : '';

        $stmt = $this->pdo->prepare("
            SELECT * FROM gudangbarang
            WHERE kode_brng = :kd AND kd_bangsal = :depo AND stok > 0
            ORDER BY no_batch ASC, no_faktur ASC{$lockClause}
        ");
        $stmt->execute([
            ':kd' => $kodeBrng,
            ':depo' => $depo->value(),
        ]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalAvailable = 0.0;
        foreach ($batches as $b) {
            $totalAvailable += (float) $b['stok'];
        }

        if ($totalAvailable < $qty) {
            throw new InsufficientStockException(
                "Stok tidak mencukupi di depo {$depo->value()} untuk barang {$kodeBrng} (tersedia: {$totalAvailable}, diminta: {$qty})."
            );
        }

        $remainingToDeduct = $qty;
        $deductedBatches = [];

        foreach ($batches as $b) {
            if ($remainingToDeduct <= 0) {
                break;
            }

            $batchStock = (float) $b['stok'];
            $take = min($batchStock, $remainingToDeduct);

            $entity = new GudangBarang(
                kodeBrng: $kodeBrng,
                kdBangsal: $depo,
                noBatch: (string) $b['no_batch'],
                noFaktur: (string) $b['no_faktur'],
                stok: $batchStock
            );
            $updatedEntity = $entity->deduct($take);

            $upd = $this->pdo->prepare("
                UPDATE gudangbarang
                SET stok = :stok
                WHERE kode_brng = :kd AND kd_bangsal = :depo AND no_batch = :batch AND no_faktur = :faktur
            ");
            $upd->execute([
                ':stok' => $updatedEntity->stok,
                ':kd' => $kodeBrng,
                ':depo' => $depo->value(),
                ':batch' => $entity->noBatch,
                ':faktur' => $entity->noFaktur,
            ]);

            $logStmt = $this->pdo->prepare("
                INSERT INTO riwayat_barang_medis (
                    kode_brng, stok_awal, masuk, keluar, stok_akhir,
                    posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan
                ) VALUES (
                    :kd, :awal, 0, :keluar, :akhir,
                    'Pemberian Obat', :tgl, :jam, :petugas, :depo, 'Simpan', :batch, :faktur, :ket
                )
            ");
            $logStmt->execute([
                ':kd' => $kodeBrng,
                ':awal' => $batchStock,
                ':keluar' => $take,
                ':akhir' => $updatedEntity->stok,
                ':tgl' => $tanggal,
                ':jam' => $jam,
                ':petugas' => $petugas,
                ':depo' => $depo->value(),
                ':batch' => $entity->noBatch,
                ':faktur' => $entity->noFaktur,
                ':ket' => $keterangan,
            ]);

            $deductedBatches[] = [
                'no_batch' => $entity->noBatch,
                'no_faktur' => $entity->noFaktur,
                'qty' => $take,
            ];

            $remainingToDeduct -= $take;
        }

        return $deductedBatches;
    }
}
