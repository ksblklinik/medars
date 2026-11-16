<?php

declare(strict_types=1);

namespace App\Application\Registration\UseCase;

use App\Application\Registration\DTO\CheckInMobileJknCommand;
use App\Application\Registration\Exception\BookingCheckInException;
use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\Repository\BookingRegistrasiRepositoryInterface;
use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\Repository\PoliklinikRepositoryInterface;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class CheckInMobileJknUseCase
{
    public function __construct(
        private PasienRepositoryInterface $pasienRepo,
        private PoliklinikRepositoryInterface $poliRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private BookingRegistrasiRepositoryInterface $bookingRepo,
        private TransactionManagerInterface $tx
    ) {}

    public function execute(CheckInMobileJknCommand $cmd): RegPeriksa
    {
        $today = $cmd->today ?? new \DateTimeImmutable('today');
        $noRm = new NoRkmMedis($cmd->noRkmMedis);

        $booking = $this->bookingRepo->findTodayPendingBooking($noRm, $today);

        if ($booking === null) {
            throw BookingCheckInException::notFound($cmd->noRkmMedis, $today->format('Y-m-d'));
        }

        $pasien = $this->pasienRepo->findByNoRkmMedis($booking->noRkmMedis);
        if ($pasien === null) {
            throw new \InvalidArgumentException("Pasien dengan nomor rekam medis '{$booking->noRkmMedis}' tidak ditemukan.");
        }

        $poliklinik = $this->poliRepo->findByKdPoli($booking->kdPoli);
        if ($poliklinik === null) {
            throw new \InvalidArgumentException("Poliklinik dengan kode '{$booking->kdPoli}' tidak ditemukan.");
        }

        // Tentukan dokter (tangani dokter pengganti jika ada)
        $finalKdDokter = !empty($booking->kdDokterPengganti) ? $booking->kdDokterPengganti : $booking->kdDokter;

        // Hitung riwayat kunjungan
        $totalVisits = $this->regRepo->countVisitsByPatient($pasien->noRkmMedis);
        $sttsDaftar = $totalVisits > 0 ? 'Lama' : 'Baru';

        $poliVisits = $this->regRepo->countVisitsByPatientAndPoli($pasien->noRkmMedis, $booking->kdPoli);
        $statusPoli = $poliVisits > 0 ? 'Lama' : 'Baru';

        $biayaReg = $poliklinik->getRegistrationFee($sttsDaftar);
        $umurInfo = $pasien->hitungUmurPada($today);

        // Terbitkan nomor rawat hari ini, namun TETAP PERTAHANKAN no_reg asli dari booking!
        $nextNoRawat = $this->regRepo->getNextNoRawat($today);

        $reg = new RegPeriksa(
            noRawat: $nextNoRawat,
            noReg: $booking->noReg, // Nomor antrean asli booking dipertahankan
            noRkmMedis: $pasien->noRkmMedis,
            kdPoli: $booking->kdPoli,
            kdDokter: $finalKdDokter,
            kdPj: $booking->kdPj,
            tglRegistrasi: $today,
            jamReg: $cmd->jamCheckIn ?? date('H:i:s'),
            biayaReg: $biayaReg,
            stts: 'Belum',
            sttsDaftar: $sttsDaftar,
            statusBayar: 'Belum Bayar',
            statusPoli: $statusPoli,
            statusLanjut: 'Ralan',
            umurDaftar: $umurInfo['umur'],
            sttsUmur: $umurInfo['satuan'],
            pJawab: $pasien->namaKeluarga,
            almtPj: $pasien->alamatPj,
            hubunganPj: $pasien->keluarga,
            prefik: $booking->prefik ?? '',
            room: $booking->room ?? '',
            shift: $booking->shift ?? ''
        );

        // Eksekusi atomik dalam transaksi ACID (preserveNoReg = true agar no_reg booking tidak berubah)
        return $this->tx->transaction(function () use ($reg, $pasien, $today): RegPeriksa {
            $saved = $this->regRepo->saveWithAtomicRetry($reg, 5, true);
            $this->bookingRepo->markAsRegistered($pasien->noRkmMedis, $today);

            return $saved;
        });
    }
}
