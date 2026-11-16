<?php

declare(strict_types=1);

namespace App\Application\Pharmacy\UseCase;

use App\Domain\Pharmacy\Entity\ResepDokterNonRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikanDetail;
use App\Domain\Pharmacy\Entity\ResepObat;
use App\Domain\Pharmacy\Repository\ResepRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class CreateResepUseCase
{
    public function __construct(
        private ResepRepositoryInterface $resepRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    /**
     * @param list<array{kode_brng: string, jml: float|int, aturan_pakai?: string}> $nonRacikanItems
     * @param list<array{
     *     nama_racik: string,
     *     kd_racik?: string,
     *     jml_dr: int,
     *     aturan_pakai?: string,
     *     keterangan?: string,
     *     details: list<array{kode_brng: string, jml: float|int, p1?: float, p2?: float, kandungan?: string}>
     * }> $racikanItems
     */
    public function execute(
        NoRawat $noRawat,
        string $kdDokter,
        array $nonRacikanItems = [],
        array $racikanItems = [],
        string $status = 'ralan',
        ?\DateTimeImmutable $peresepanAt = null,
        int $maxRetries = 5
    ): ResepObat {
        if ($nonRacikanItems === [] && $racikanItems === []) {
            throw new \InvalidArgumentException('E-Resep harus berisi minimal 1 item obat jadi atau racikan.');
        }

        $now = $peresepanAt ?? new \DateTimeImmutable();
        $tgl = $now->format('Y-m-d');
        $jam = $now->format('H:i:s');

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->tx->transaction(function () use (
                    $now, $tgl, $jam, $noRawat, $kdDokter, $status, $nonRacikanItems, $racikanItems
                ): ResepObat {
                    $noResep = $this->resepRepo->getNextNoResep($now);

                    $resep = new ResepObat(
                        noResep: $noResep,
                        tglPeresepan: $tgl,
                        jamPeresepan: $jam,
                        noRawat: $noRawat,
                        kdDokter: $kdDokter,
                        tglPerawatan: '0000-00-00',
                        jam: '00:00:00',
                        status: $status
                    );
                    $this->resepRepo->saveHeader($resep);

                    foreach ($nonRacikanItems as $item) {
                        $this->resepRepo->saveNonRacikan(new ResepDokterNonRacikan(
                            noResep: $noResep,
                            kodeBrng: (string) $item['kode_brng'],
                            jml: (float) $item['jml'],
                            aturanPakai: (string) ($item['aturan_pakai'] ?? '-')
                        ));
                    }

                    foreach ($racikanItems as $idx => $racik) {
                        $noRacik = (string) ($idx + 1);
                        $details = [];
                        foreach ($racik['details'] as $d) {
                            $details[] = new ResepDokterRacikanDetail(
                                noResep: $noResep,
                                noRacik: $noRacik,
                                kodeBrng: (string) $d['kode_brng'],
                                p1: (float) ($d['p1'] ?? 1.0),
                                p2: (float) ($d['p2'] ?? 1.0),
                                kandungan: (string) ($d['kandungan'] ?? '-'),
                                jml: (float) $d['jml']
                            );
                        }

                        $this->resepRepo->saveRacikan(new ResepDokterRacikan(
                            noResep: $noResep,
                            noRacik: $noRacik,
                            namaRacik: (string) $racik['nama_racik'],
                            kdRacik: (string) ($racik['kd_racik'] ?? 'R01'),
                            jmlDr: (int) $racik['jml_dr'],
                            aturanPakai: (string) ($racik['aturan_pakai'] ?? '-'),
                            keterangan: (string) ($racik['keterangan'] ?? '-'),
                            details: $details
                        ));
                    }

                    return $resep;
                });
            } catch (\PDOException $e) {
                $isUnique = str_contains($e->getMessage(), 'UNIQUE')
                    || str_contains($e->getMessage(), 'Duplicate entry')
                    || $e->getCode() === '23000';
                if (!$isUnique || $attempt >= $maxRetries) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal menerbitkan nomor resep.');
    }
}
