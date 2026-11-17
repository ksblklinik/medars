<?php

declare(strict_types=1);

namespace App\Application\Registration\UseCase;

use App\Application\Registration\DTO\RegisterInSiteCommand;
use App\Application\Registration\Exception\DuplicateRegistrationException;
use App\Application\Registration\Exception\PenjaminDisallowedException;
use App\Application\Registration\Exception\UnpaidArrearsException;
use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\Entity\BookingRegistrasi;
use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\Repository\BookingRegistrasiRepositoryInterface;
use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\Repository\PoliklinikRepositoryInterface;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRkmMedis;

final readonly class RegisterInSiteUseCase
{
    public function __construct(
        private PasienRepositoryInterface $pasienRepo,
        private PoliklinikRepositoryInterface $poliRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private BookingRegistrasiRepositoryInterface $bookingRepo,
        private ?BillingLedgerRepositoryInterface $ledgerRepo = null,
    ) {}

    public function execute(RegisterInSiteCommand $cmd): ?RegPeriksa
    {
        $noRm = new NoRkmMedis($cmd->noRkmMedis);
        $pasien = $this->pasienRepo->findByNoRkmMedis($noRm);
        if ($pasien === null) {
            throw new \InvalidArgumentException("Pasien dengan nomor rekam medis '{$cmd->noRkmMedis}' tidak ditemukan.");
        }

        $poliklinik = $this->poliRepo->findByKdPoli($cmd->kdPoli);
        if ($poliklinik === null) {
            throw new \InvalidArgumentException("Poliklinik dengan kode '{$cmd->kdPoli}' tidak ditemukan.");
        }

        if (!$poliklinik->isPenjaminAllowed($cmd->kdPj)) {
            throw new PenjaminDisallowedException($cmd->kdPoli, $cmd->kdPj);
        }

        $today = new \DateTimeImmutable('today');
        $tglRegistrasi = $cmd->tglRegistrasi ?? $today;

        // Jika pendaftaran untuk tanggal masa depan, otomatis alihkan ke BookingRegistrasi
        if ($tglRegistrasi->format('Y-m-d') > $today->format('Y-m-d')) {
            $nextNoReg = $this->regRepo->getNextNoReg($cmd->kdPoli, $cmd->kdDokter, $tglRegistrasi, $cmd->shift);
            $booking = new BookingRegistrasi(
                noRkmMedis: $pasien->noRkmMedis,
                tanggalPeriksa: $tglRegistrasi,
                kdPoli: $cmd->kdPoli,
                kdDokter: $cmd->kdDokter,
                noReg: $nextNoReg,
                kdPj: $cmd->kdPj,
                status: 'Belum',
                shift: $cmd->shift,
                prefik: $cmd->prefik,
                room: $cmd->room,
                tanggalBooking: $today,
                jamBooking: $cmd->jamReg ?? date('H:i:s')
            );
            $this->bookingRepo->save($booking);

            return null;
        }

        // 1. Guard Idempotensi (Anti Double-Submit)
        if ($this->regRepo->hasActiveRegistrationToday($pasien->noRkmMedis, $cmd->kdPoli, $cmd->kdDokter, $tglRegistrasi, $cmd->shift)) {
            throw new DuplicateRegistrationException(
                $pasien->noRkmMedis->value(),
                $cmd->kdPoli,
                $cmd->kdDokter,
                $tglRegistrasi->format('Y-m-d')
            );
        }

        // 2. Guard Tunggakan Pembayaran Kunjungan Sebelumnya
        if ($this->regRepo->hasUnpaidPreviousVisit($pasien->noRkmMedis, $today)) {
            throw new UnpaidArrearsException($pasien->noRkmMedis->value());
        }

        // 3. Tentukan status riwayat kunjungan pasien
        $totalVisits = $this->regRepo->countVisitsByPatient($pasien->noRkmMedis);
        $sttsDaftar = $totalVisits > 0 ? 'Lama' : 'Baru';

        $poliVisits = $this->regRepo->countVisitsByPatientAndPoli($pasien->noRkmMedis, $cmd->kdPoli);
        $statusPoli = $poliVisits > 0 ? 'Lama' : 'Baru';

        // 4. Hitung biaya registrasi & umur
        $biayaReg = $poliklinik->getRegistrationFee($sttsDaftar);
        $umurInfo = $pasien->hitungUmurPada($tglRegistrasi);

        // 5. Terbitkan nomor antrean & nomor rawat
        $nextNoReg = $this->regRepo->getNextNoReg($cmd->kdPoli, $cmd->kdDokter, $tglRegistrasi, $cmd->shift);
        $nextNoRawat = $this->regRepo->getNextNoRawat($tglRegistrasi);

        $reg = new RegPeriksa(
            noRawat: $nextNoRawat,
            noReg: $nextNoReg,
            noRkmMedis: $pasien->noRkmMedis,
            kdPoli: $cmd->kdPoli,
            kdDokter: $cmd->kdDokter,
            kdPj: $cmd->kdPj,
            tglRegistrasi: $tglRegistrasi,
            jamReg: $cmd->jamReg ?? date('H:i:s'),
            biayaReg: $biayaReg,
            stts: 'Belum',
            sttsDaftar: $sttsDaftar,
            statusBayar: 'Belum Bayar',
            statusPoli: $statusPoli,
            statusLanjut: 'Ralan',
            umurDaftar: $umurInfo['umur'],
            sttsUmur: $umurInfo['satuan'],
            pJawab: $cmd->pJawab !== '-' ? $cmd->pJawab : $pasien->namaKeluarga,
            almtPj: $cmd->almtPj !== '-' ? $cmd->almtPj : $pasien->alamatPj,
            hubunganPj: $cmd->hubunganPj !== '-' ? $cmd->hubunganPj : $pasien->keluarga,
            prefik: $cmd->prefik,
            room: $cmd->room,
            shift: $cmd->shift
        );

        $saved = $this->regRepo->saveWithAtomicRetry($reg);
        if ($this->ledgerRepo !== null && $biayaReg > 0) {
            $claimType = strtoupper($cmd->kdPj) === 'BPJ' ? 'Kapitasi' : 'Tunai';
            $this->ledgerRepo->addItem(new BillingLedgerItem(
                id: null,
                noRawat: $saved->noRawat,
                itemCode: 'REG-' . $cmd->kdPoli,
                itemName: 'Biaya Registrasi ' . $poliklinik->nmPoli,
                category: 'Registrasi',
                qty: 1.0,
                unitPrice: $biayaReg,
                totalAmount: $biayaReg,
                claimType: ClaimType::fromString($claimType),
                status: LedgerStatus::fromString('Pending'),
                providerId: 'REG|' . $saved->noRawat->value(),
                createdAt: $tglRegistrasi->format('Y-m-d') . ' ' . $saved->jamReg,
            ));
        }

        return $saved;
    }
}
