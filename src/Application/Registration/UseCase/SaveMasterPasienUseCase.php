<?php

declare(strict_types=1);

namespace App\Application\Registration\UseCase;

use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveMasterPasienUseCase
{
    public function __construct(
        private PasienRepositoryInterface $pasienRepo,
        private TransactionManagerInterface $tx,
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): Pasien
    {
        $rawRm = trim((string) ($input['no_rkm_medis'] ?? ''));
        $nik = trim((string) ($input['no_ktp'] ?? ''));
        $tglLahirStr = trim((string) ($input['tgl_lahir'] ?? ''));
        if ($tglLahirStr === '') {
            throw new \InvalidArgumentException('Tanggal lahir pasien wajib diisi.');
        }
        $tglLahir = new \DateTimeImmutable($tglLahirStr);

        return $this->tx->transaction(function () use ($rawRm, $nik, $tglLahir, $input): Pasien {
            $noRkmMedis = $rawRm !== ''
                ? new NoRkmMedis($rawRm)
                : $this->pasienRepo->getNextNoRkmMedis();

            if ($nik !== '' && $nik !== '-') {
                $existingByNik = $this->pasienRepo->findByNik($nik);
                if ($existingByNik !== null && $existingByNik->noRkmMedis->value() !== $noRkmMedis->value()) {
                    throw new \DomainException(
                        "NIK '{$nik}' sudah terdaftar pada pasien lain (No. RM {$existingByNik->noRkmMedis->value()})."
                    );
                }
            }

            $pasien = new Pasien(
                noRkmMedis: $noRkmMedis,
                nmPasien: trim((string) ($input['nm_pasien'] ?? '')),
                nik: $nik,
                jk: trim((string) ($input['jk'] ?? '-')) ?: '-',
                tglLahir: $tglLahir,
                alamat: trim((string) ($input['alamat'] ?? '-')) ?: '-',
                noTelp: trim((string) ($input['no_tlp'] ?? '-')) ?: '-',
                noPeserta: trim((string) ($input['no_peserta'] ?? '')),
                keluarga: trim((string) ($input['keluarga'] ?? 'DIRI SENDIRI')) ?: 'DIRI SENDIRI',
                namaKeluarga: trim((string) ($input['namakeluarga'] ?? '-')) ?: '-',
                alamatPj: trim((string) ($input['alamatpj'] ?? '-')) ?: '-',
            );

            $this->pasienRepo->saveOrUpdate($pasien, $input);

            return $pasien;
        });
    }
}
