<?php

declare(strict_types=1);

namespace App\Application\Bridging\UseCase;

use App\Domain\Bridging\Repository\VedikaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class VerifyVedikaClaimUseCase
{
    public function __construct(
        private VedikaRepositoryInterface $vedikaRepo,
        private TransactionManagerInterface $tx,
    ) {}

    /**
     * @return array{no_rawat: string, no_sep: string, status: string, catatan: string}
     */
    public function execute(
        NoRawat $noRawat,
        string $noSep,
        string $verifiedBy,
        string $jenisPelayanan = 'Ralan',
    ): array {
        $nr = $noRawat->value();
        $trimmedSep = trim($noSep);
        if ($trimmedSep === '') {
            throw new \InvalidArgumentException('Nomor SEP BPJS wajib diisi untuk verifikasi klaim Vedika.');
        }

        if (!$this->vedikaRepo->registrationExists($noRawat)) {
            throw new \DomainException("Data registrasi '{$nr}' tidak ditemukan.");
        }

        $issues = [];
        if ($this->vedikaRepo->countSoapRalan($noRawat) === 0) {
            $issues[] = 'SOAP pemeriksaan belum diisi';
        }
        if ($this->vedikaRepo->countDiagnosa($noRawat) === 0) {
            $issues[] = 'Diagnosa ICD-10 belum diisi';
        }

        $status = $issues === [] ? 'Layak' : 'Perlu Perbaikan';
        $catatan = $issues === [] ? 'Berkas klaim lengkap' : implode('; ', $issues);
        $now = date('Y-m-d H:i:s');

        $this->tx->transaction(function () use ($noRawat, $trimmedSep, $jenisPelayanan, $status, $catatan, $verifiedBy, $now): void {
            $this->vedikaRepo->saveVerification($noRawat, $trimmedSep, $jenisPelayanan, $status, $catatan, $verifiedBy, $now);
        });

        return [
            'no_rawat' => $nr,
            'no_sep' => $trimmedSep,
            'status' => $status,
            'catatan' => $catatan,
        ];
    }
}
