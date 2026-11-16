<?php

declare(strict_types=1);

namespace App\Application\Pharmacy\UseCase;

use App\Domain\Pharmacy\Entity\DetailPemberianObat;
use App\Domain\Pharmacy\Repository\DataBarangRepositoryInterface;
use App\Domain\Pharmacy\Repository\GudangBarangRepositoryInterface;
use App\Domain\Pharmacy\Repository\ResepRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use App\Domain\Pharmacy\ValueObject\JenisKlaim;
use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class DispenseResepUseCase
{
    public function __construct(
        private ResepRepositoryInterface $resepRepo,
        private DataBarangRepositoryInterface $barangRepo,
        private GudangBarangRepositoryInterface $gudangRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function execute(
        NoResep $noResep,
        DepoBangsal $depo,
        JenisKlaim $jenisKlaim,
        string $petugasNip,
        ?\DateTimeImmutable $dispenseAt = null
    ): void {
        $resep = $this->resepRepo->findByNoResep($noResep);
        if ($resep === null) {
            throw new \DomainException("Resep tidak ditemukan: {$noResep->value()}.");
        }

        if ($resep->isDispensed()) {
            throw new \DomainException("Resep {$noResep->value()} sudah divalidasi/diserahkan sebelumnya.");
        }

        $nonRacikanItems = $this->resepRepo->findNonRacikan($noResep);
        $racikanItems = $this->resepRepo->findRacikan($noResep);

        $baseTime = $dispenseAt ?? new \DateTimeImmutable();
        $baseTgl = $baseTime->format('Y-m-d');
        $baseJam = $baseTime->format('H:i:s');

        // Offset +1 detik aman lintas pergantian hari (23:59:59 -> hari+1 00:00:00)
        $compoundedTime = $baseTime->modify('+1 second');
        $compoundedTgl = $compoundedTime->format('Y-m-d');
        $compoundedJam = $compoundedTime->format('H:i:s');

        $statusLanjut = strtolower($resep->status) === 'ranap' ? 'Ranap' : 'Ralan';

        $this->tx->transaction(function () use (
            $resep, $noResep, $depo, $jenisKlaim, $petugasNip, $statusLanjut,
            $nonRacikanItems, $racikanItems,
            $baseTgl, $baseJam, $compoundedTgl, $compoundedJam
        ): void {
            // 1. Proses Obat Jadi (Non-Racikan) pada timestamp dasar
            foreach ($nonRacikanItems as $item) {
                $barang = $this->barangRepo->findByKode($item->kodeBrng);
                if ($barang === null) {
                    throw new \DomainException("Master barang tidak ditemukan: {$item->kodeBrng}.");
                }

                $unitPrice = $barang->pricing->getPriceForClaim($jenisKlaim);
                $batches = $this->gudangRepo->deductStock(
                    kodeBrng: $item->kodeBrng,
                    depo: $depo,
                    qty: $item->jml,
                    tanggal: $baseTgl,
                    jam: $baseJam,
                    petugas: $petugasNip,
                    keterangan: "Resep {$noResep->value()}"
                );

                foreach ($batches as $batch) {
                    $qty = (float) $batch['qty'];
                    $this->resepRepo->saveDetailPemberianObat(new DetailPemberianObat(
                        tglPerawatan: $baseTgl,
                        jam: $baseJam,
                        noRawat: $resep->noRawat,
                        kodeBrng: $item->kodeBrng,
                        noBatch: (string) $batch['no_batch'],
                        noFaktur: (string) $batch['no_faktur'],
                        hBeli: $barang->pricing->hBeli,
                        biayaObat: $unitPrice,
                        jml: $qty,
                        embalase: 0.0,
                        tuslah: 0.0,
                        total: $unitPrice * $qty,
                        status: $statusLanjut,
                        kdBangsal: $depo,
                        jenisKlaim: $jenisKlaim
                    ));
                }
            }

            // 2. Proses Obat Racikan dengan offset +1 detik
            foreach ($racikanItems as $racikan) {
                $this->resepRepo->saveObatRacikan(
                    tglPerawatan: $compoundedTgl,
                    jam: $compoundedJam,
                    noRawat: $resep->noRawat,
                    racikan: $racikan
                );

                foreach ($racikan->details as $detail) {
                    $barang = $this->barangRepo->findByKode($detail->kodeBrng);
                    if ($barang === null) {
                        throw new \DomainException("Master bahan racikan tidak ditemukan: {$detail->kodeBrng}.");
                    }

                    $unitPrice = $barang->pricing->getPriceForClaim($jenisKlaim);
                    $batches = $this->gudangRepo->deductStock(
                        kodeBrng: $detail->kodeBrng,
                        depo: $depo,
                        qty: $detail->jml,
                        tanggal: $compoundedTgl,
                        jam: $compoundedJam,
                        petugas: $petugasNip,
                        keterangan: "Racikan {$racikan->namaRacik} ({$noResep->value()})"
                    );

                    foreach ($batches as $batch) {
                        $qty = (float) $batch['qty'];
                        $this->resepRepo->saveDetailPemberianObat(new DetailPemberianObat(
                            tglPerawatan: $compoundedTgl,
                            jam: $compoundedJam,
                            noRawat: $resep->noRawat,
                            kodeBrng: $detail->kodeBrng,
                            noBatch: (string) $batch['no_batch'],
                            noFaktur: (string) $batch['no_faktur'],
                            hBeli: $barang->pricing->hBeli,
                            biayaObat: $unitPrice,
                            jml: $qty,
                            embalase: 0.0,
                            tuslah: 0.0,
                            total: $unitPrice * $qty,
                            status: $statusLanjut,
                            kdBangsal: $depo,
                            jenisKlaim: $jenisKlaim
                        ));
                    }
                }
            }

            $this->resepRepo->markDispensed($noResep, $baseTgl, $baseJam);
        });
    }
}
