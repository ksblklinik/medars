<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entity\PosApotekUmumSale;
use App\Domain\Billing\Repository\PosApotekUmumRepositoryInterface;
use PDO;

final class PdoPosApotekUmumRepository implements PosApotekUmumRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getNextNoNota(\DateTimeInterface $date): string
    {
        $prefix = 'PO-APT-' . $date->format('Ymd');
        $stmt = $this->pdo->prepare("SELECT MAX(no_nota) FROM mlite_penjualan_apotek_umum WHERE no_nota LIKE :prefix");
        $stmt->execute([':prefix' => $prefix . '%']);
        $max = $stmt->fetchColumn();

        $next = 1;
        if (is_string($max) && str_starts_with($max, $prefix)) {
            $suffix = substr($max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function saveSale(PosApotekUmumSale $sale, array $items): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mlite_penjualan_apotek_umum (no_nota, tgl_jual, jam_jual, payment_method, total, petugas_nip, keterangan)
            VALUES (:nota, :tgl, :jam, :pm, :total, :nip, :ket)
        ");
        $stmt->execute([
            ':nota' => $sale->noNota,
            ':tgl' => $sale->tglJual,
            ':jam' => $sale->jamJual,
            ':pm' => $sale->paymentMethod->value(),
            ':total' => $sale->total,
            ':nip' => $sale->petugasNip,
            ':ket' => $sale->keterangan,
        ]);

        $detStmt = $this->pdo->prepare("
            INSERT INTO mlite_penjualan_apotek_umum_detail (no_nota, kode_brng, jml, harga_jual, subtotal)
            VALUES (:nota, :kd, :jml, :harga, :sub)
        ");

        foreach ($items as $item) {
            $detStmt->execute([
                ':nota' => $sale->noNota,
                ':kd' => (string) $item['kode_brng'],
                ':jml' => (float) $item['jml'],
                ':harga' => (float) $item['harga_jual'],
                ':sub' => (float) $item['subtotal'],
            ]);
        }
    }
}
