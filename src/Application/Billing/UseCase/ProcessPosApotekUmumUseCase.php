<?php

declare(strict_types=1);

namespace App\Application\Billing\UseCase;

use App\Domain\Billing\Entity\PosApotekUmumSale;
use App\Domain\Billing\Repository\PosApotekUmumRepositoryInterface;
use App\Domain\Billing\ValueObject\PosPaymentMethod;
use App\Domain\Pharmacy\Repository\DataBarangRepositoryInterface;
use App\Domain\Pharmacy\Repository\GudangBarangRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class ProcessPosApotekUmumUseCase
{
    public function __construct(
        private DataBarangRepositoryInterface $barangRepo,
        private GudangBarangRepositoryInterface $gudangRepo,
        private PosApotekUmumRepositoryInterface $posRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    /**
     * @param list<array{kode_brng: string, jml: float|int}> $items
     */
    public function execute(
        array $items,
        PosPaymentMethod $paymentMethod,
        string $petugasNip,
        string $keterangan = '',
        ?\DateTimeImmutable $saleAt = null
    ): PosApotekUmumSale {
        if ($items === []) {
            throw new \InvalidArgumentException('Keranjang POS Apotek Umum tidak boleh kosong.');
        }

        $now = $saleAt ?? new \DateTimeImmutable();
        $tgl = $now->format('Y-m-d');
        $jam = $now->format('H:i:s');
        $depoApt = new DepoBangsal('APT');

        return $this->tx->transaction(function () use ($items, $paymentMethod, $petugasNip, $keterangan, $now, $tgl, $jam, $depoApt): PosApotekUmumSale {
            $noNota = $this->posRepo->getNextNoNota($now);
            $total = 0.0;
            $saleItems = [];

            foreach ($items as $item) {
                $kode = trim((string) ($item['kode_brng'] ?? ''));
                $qty = (float) ($item['jml'] ?? 0);
                if ($kode === '' || $qty <= 0) {
                    throw new \InvalidArgumentException('Kode barang dan jumlah POS harus valid.');
                }

                $barang = $this->barangRepo->findByKode($kode);
                if ($barang === null) {
                    throw new \DomainException("Barang tidak ditemukan: {$kode}.");
                }

                // Isolasi stok mutlak ke Depo 'APT'
                $this->gudangRepo->deductStock(
                    kodeBrng: $kode,
                    depo: $depoApt,
                    qty: $qty,
                    tanggal: $tgl,
                    jam: $jam,
                    petugas: $petugasNip,
                    keterangan: "POS Apotek Umum {$noNota}"
                );

                $hargaJual = $barang->pricing->jualBebas > 0 ? $barang->pricing->jualBebas : $barang->pricing->hargaTunai;
                $subtotal = $hargaJual * $qty;
                $total += $subtotal;

                $saleItems[] = [
                    'kode_brng' => $kode,
                    'jml' => $qty,
                    'harga_jual' => $hargaJual,
                    'subtotal' => $subtotal,
                ];
            }

            $sale = new PosApotekUmumSale(
                noNota: $noNota,
                tglJual: $tgl,
                jamJual: $jam,
                paymentMethod: $paymentMethod,
                total: $total,
                petugasNip: $petugasNip,
                keterangan: $keterangan
            );

            $this->posRepo->saveSale($sale, $saleItems);

            return $sale;
        });
    }
}
