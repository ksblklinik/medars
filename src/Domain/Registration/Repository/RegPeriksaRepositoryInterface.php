<?php

declare(strict_types=1);

namespace App\Domain\Registration\Repository;

use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;

interface RegPeriksaRepositoryInterface
{
    public function findByNoRawat(NoRawat $noRawat): ?RegPeriksa;

    public function hasActiveRegistrationToday(
        NoRkmMedis $noRkmMedis,
        string $kdPoli,
        string $kdDokter,
        \DateTimeInterface $date,
        string $shift = ''
    ): bool;

    public function hasUnpaidPreviousVisit(NoRkmMedis $noRkmMedis, \DateTimeInterface $today): bool;

    /**
     * Menerbitkan nomor antrean berikutnya dengan reservasi nomor booking.
     * Mengambil MAX(reg_periksa, booking_registrasi) + 1.
     */
    public function getNextNoReg(
        string $kdPoli,
        string $kdDokter,
        \DateTimeInterface $date,
        string $shift = ''
    ): NoReg;

    public function getNextNoRawat(\DateTimeInterface $date): NoRawat;

    /**
     * Menyimpan data kunjungan dengan proteksi balapan (race condition).
     * Jika terjadi tabrakan nomor rawat atau no_reg pada jam sibuk, secara atomik meregenerasi nomor.
     * Jika $preserveNoReg = true (Mobile JKN), no_reg asli dipertahankan dan hanya no_rawat yang diregenerasi.
     */
    public function saveWithAtomicRetry(RegPeriksa $regPeriksa, int $maxRetries = 5, bool $preserveNoReg = false): RegPeriksa;

    /**
     * Memeriksa apakah pasien sudah memiliki rekam catatan klinis (SOAP, tindakan, resep obat, lab, radiologi).
     */
    public function hasClinicalRecords(NoRawat $noRawat): bool;

    public function countVisitsByPatient(NoRkmMedis $noRkmMedis): int;

    public function countVisitsByPatientAndPoli(NoRkmMedis $noRkmMedis, string $kdPoli): int;

    public function update(RegPeriksa $regPeriksa): void;

    public function updateStatus(NoRawat $noRawat, string $stts): void;

    /**
     * Mengalihkan dokter pada kunjungan dan memperbarui tindakan konsultasi awal jika ada.
     */
    public function reassignDoctor(NoRawat $noRawat, string $newKdDokter, string $oldKdDokter): void;
}
