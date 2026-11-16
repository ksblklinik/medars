<?php

declare(strict_types=1);

namespace App\Application\Registration\UseCase;

use App\Application\Registration\DTO\ReassignDoctorCommand;
use App\Application\Registration\DTO\ShiftQueueCommand;
use App\Application\Registration\Exception\ClinicalGuardBlockedException;
use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;

final readonly class ClinicalGuardShieldUseCase
{
    public function __construct(
        private RegPeriksaRepositoryInterface $regRepo
    ) {}

    public function shiftQueue(ShiftQueueCommand $cmd): RegPeriksa
    {
        $noRawat = new NoRawat($cmd->noRawat);
        $reg = $this->regRepo->findByNoRawat($noRawat);

        if ($reg === null) {
            throw new \InvalidArgumentException("Kunjungan dengan nomor rawat '{$cmd->noRawat}' tidak ditemukan.");
        }

        if ($reg->isServed()) {
            throw new ClinicalGuardBlockedException($cmd->noRawat, 'Pasien sudah dalam pelayanan atau selesai.');
        }

        if ($this->regRepo->hasClinicalRecords($noRawat)) {
            throw new ClinicalGuardBlockedException(
                $cmd->noRawat,
                'Pasien sudah memiliki catatan SOAP dokter, tindakan medis/paramedis, e-resep, atau order lab/radiologi.'
            );
        }

        $newNoReg = new NoReg($cmd->newNoReg);
        $updated = new RegPeriksa(
            noRawat: $reg->noRawat,
            noReg: $newNoReg,
            noRkmMedis: $reg->noRkmMedis,
            kdPoli: $reg->kdPoli,
            kdDokter: $reg->kdDokter,
            kdPj: $reg->kdPj,
            tglRegistrasi: $reg->tglRegistrasi,
            jamReg: $reg->jamReg,
            biayaReg: $reg->biayaReg,
            stts: $reg->stts,
            sttsDaftar: $reg->sttsDaftar,
            statusBayar: $reg->statusBayar,
            statusPoli: $reg->statusPoli,
            statusLanjut: $reg->statusLanjut,
            umurDaftar: $reg->umurDaftar,
            sttsUmur: $reg->sttsUmur,
            pJawab: $reg->pJawab,
            almtPj: $reg->almtPj,
            hubunganPj: $reg->hubunganPj,
            prefik: $reg->prefik,
            room: $reg->room,
            shift: $cmd->newShift ?? $reg->shift
        );

        $this->regRepo->update($updated);

        return $updated;
    }

    public function reassignDoctor(ReassignDoctorCommand $cmd): void
    {
        $noRawat = new NoRawat($cmd->noRawat);
        $reg = $this->regRepo->findByNoRawat($noRawat);

        if ($reg === null) {
            throw new \InvalidArgumentException("Kunjungan dengan nomor rawat '{$cmd->noRawat}' tidak ditemukan.");
        }

        if ($reg->isServed()) {
            throw new ClinicalGuardBlockedException($cmd->noRawat, 'Pasien sudah dalam pelayanan atau selesai.');
        }

        if ($this->regRepo->hasClinicalRecords($noRawat)) {
            throw new ClinicalGuardBlockedException(
                $cmd->noRawat,
                'Pasien sudah memiliki catatan SOAP dokter, tindakan medis/paramedis, e-resep, atau order lab/radiologi.'
            );
        }

        $this->regRepo->reassignDoctor($noRawat, $cmd->newKdDokter, $reg->kdDokter);
    }
}
