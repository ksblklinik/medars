<?php

declare(strict_types=1);

namespace App\Application\Pharmacy\UseCase;

use App\Domain\Pharmacy\Entity\ResepDokterNonRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikanDetail;
use App\Domain\Pharmacy\Entity\ResepObat;
use App\Domain\Pharmacy\Repository\ResepRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveCopyResepUseCase
{
    public function __construct(
        private ResepRepositoryInterface $resepRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function execute(
        NoResep $sourceNoResep,
        NoRawat $targetNoRawat,
        string $kdDokter,
        ?\DateTimeImmutable $copyAt = null
    ): ResepObat {
        $source = $this->resepRepo->findByNoResep($sourceNoResep);
        if ($source === null) {
            throw new \DomainException("Resep sumber tidak ditemukan: {$sourceNoResep->value()}.");
        }

        $nonRacikan = $this->resepRepo->findNonRacikan($sourceNoResep);
        $racikan = $this->resepRepo->findRacikan($sourceNoResep);

        $now = $copyAt ?? new \DateTimeImmutable();

        return $this->tx->transaction(function () use ($now, $targetNoRawat, $kdDokter, $source, $nonRacikan, $racikan): ResepObat {
            $newNoResep = $this->resepRepo->getNextNoResep($now);

            $newResep = new ResepObat(
                noResep: $newNoResep,
                tglPeresepan: $now->format('Y-m-d'),
                jamPeresepan: $now->format('H:i:s'),
                noRawat: $targetNoRawat,
                kdDokter: $kdDokter,
                tglPerawatan: '0000-00-00',
                jam: '00:00:00',
                status: $source->status
            );
            $this->resepRepo->saveHeader($newResep);

            foreach ($nonRacikan as $item) {
                $this->resepRepo->saveNonRacikan(new ResepDokterNonRacikan(
                    noResep: $newNoResep,
                    kodeBrng: $item->kodeBrng,
                    jml: $item->jml,
                    aturanPakai: $item->aturanPakai
                ));
            }

            foreach ($racikan as $r) {
                $newDetails = [];
                foreach ($r->details as $d) {
                    $newDetails[] = new ResepDokterRacikanDetail(
                        noResep: $newNoResep,
                        noRacik: $r->noRacik,
                        kodeBrng: $d->kodeBrng,
                        p1: $d->p1,
                        p2: $d->p2,
                        kandungan: $d->kandungan,
                        jml: $d->jml
                    );
                }
                $this->resepRepo->saveRacikan(new ResepDokterRacikan(
                    noResep: $newNoResep,
                    noRacik: $r->noRacik,
                    namaRacik: $r->namaRacik,
                    kdRacik: $r->kdRacik,
                    jmlDr: $r->jmlDr,
                    aturanPakai: $r->aturanPakai,
                    keterangan: $r->keterangan,
                    details: $newDetails
                ));
            }

            return $newResep;
        });
    }
}
