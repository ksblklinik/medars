<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\RujukanInternal;
use App\Domain\Rme\Entity\SuratBebasNarkoba;
use App\Domain\Rme\Entity\SuratHamil;
use App\Domain\Rme\Entity\SuratSakit;
use App\Domain\Rme\Repository\SuratMedisRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class CreateSuratMedisUseCase
{
    public function __construct(
        private SuratMedisRepositoryInterface $suratRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function createSuratSakit(
        NoRawat $noRawat,
        \DateTimeImmutable $tanggalAwal,
        \DateTimeImmutable $tanggalAkhir
    ): SuratSakit {
        $prefix = 'SKT/' . $tanggalAwal->format('Y/m') . '/';

        return $this->executeWithAtomicRetry(function () use ($prefix, $noRawat, $tanggalAwal, $tanggalAkhir): SuratSakit {
            $noSurat = $this->suratRepo->getNextSuratNumber($prefix, 'surat_sakit');
            $surat = new SuratSakit($noSurat, $noRawat, $tanggalAwal, $tanggalAkhir);
            $this->suratRepo->saveSuratSakit($surat);
            return $surat;
        });
    }

    public function createSuratHamil(
        NoRawat $noRawat,
        \DateTimeImmutable $tanggalPeriksa,
        string $hasilPeriksa
    ): SuratHamil {
        $prefix = 'HML/' . $tanggalPeriksa->format('Y/m') . '/';

        return $this->executeWithAtomicRetry(function () use ($prefix, $noRawat, $tanggalPeriksa, $hasilPeriksa): SuratHamil {
            $noSurat = $this->suratRepo->getNextSuratNumber($prefix, 'surat_hamil');
            $surat = new SuratHamil($noSurat, $noRawat, $tanggalPeriksa, $hasilPeriksa);
            $this->suratRepo->saveSuratHamil($surat);
            return $surat;
        });
    }

    public function createSuratBebasNarkoba(
        NoRawat $noRawat,
        \DateTimeImmutable $tanggal,
        string $kategori,
        string $kdDokter,
        string $keperluan,
        string $opiat = 'NEGATIF',
        string $ganja = 'NEGATIF',
        string $amphetamin = 'NEGATIF',
        string $methamphetamin = 'NEGATIF',
        string $benzodiazepin = 'NEGATIF',
        string $cocain = 'NEGATIF'
    ): SuratBebasNarkoba {
        $prefix = 'NRK/' . $tanggal->format('Y/m') . '/';

        return $this->executeWithAtomicRetry(function () use (
            $prefix, $noRawat, $tanggal, $kategori, $kdDokter, $keperluan,
            $opiat, $ganja, $amphetamin, $methamphetamin, $benzodiazepin, $cocain
        ): SuratBebasNarkoba {
            $noSurat = $this->suratRepo->getNextSuratNumber($prefix, 'surat_bebas_narkoba');
            $surat = new SuratBebasNarkoba(
                $noSurat, $noRawat, $tanggal, $kategori, $kdDokter, $keperluan,
                $opiat, $ganja, $amphetamin, $methamphetamin, $benzodiazepin, $cocain
            );
            $this->suratRepo->saveSuratBebasNarkoba($surat);
            return $surat;
        });
    }

    public function createRujukanInternal(RujukanInternal $rujukan): void
    {
        $this->tx->transaction(function () use ($rujukan): void {
            $this->suratRepo->saveRujukanInternal($rujukan);
        });
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    private function executeWithAtomicRetry(callable $fn, int $maxRetries = 5): mixed
    {
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->tx->transaction($fn);
            } catch (\PDOException $e) {
                $isUniqueViolation = str_contains($e->getMessage(), 'UNIQUE')
                    || str_contains($e->getMessage(), 'Duplicate entry')
                    || $e->getCode() === '23000';

                if (!$isUniqueViolation || $attempt >= $maxRetries) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal menerbitkan nomor surat medis setelah beberapa percobaan.');
    }
}
