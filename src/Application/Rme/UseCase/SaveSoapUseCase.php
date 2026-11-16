<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Application\Rme\DTO\SaveSoapCommand;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Rme\Entity\PemeriksaanRalan;
use App\Domain\Rme\Entity\RmeMetadata;
use App\Domain\Rme\Repository\PemeriksaanRalanRepositoryInterface;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveSoapUseCase
{
    public function __construct(
        private PemeriksaanRalanRepositoryInterface $ralanRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function execute(SaveSoapCommand $cmd, UserAccount $actor, ?\DateTimeImmutable $now = null): PemeriksaanRalan
    {
        $reg = $this->regRepo->findByNoRawat($cmd->noRawat);
        if ($reg === null) {
            throw new \DomainException("Data registrasi kunjungan tidak ditemukan: {$cmd->noRawat->value()}.");
        }

        // 1. Validasi hak akses poliklinik (anti-IDOR lintas-poli; izinkan jika dokter pengganti aktif di reg_periksa)
        if (!$actor->canAccessPoli($reg->kdPoli) && $actor->getUsername() !== $reg->kdDokter) {
            throw new ForbiddenException("Anda tidak memiliki izin melayani poliklinik {$reg->kdPoli}.");
        }

        // 2. Validasi status kunjungan
        if ($reg->stts === 'Batal') {
            throw new \DomainException('Kunjungan pasien berstatus Batal dan tidak dapat diisi SOAP.');
        }

        // 3. Validasi batas waktu pengisian SOAP: izinkan lintas hari maksimal H-1 (shift malam)
        $referenceNow = $now ?? new \DateTimeImmutable('today');
        $regDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $reg->tglRegistrasi->format('Y-m-d'));
        if ($regDate !== false) {
            $diffDays = (int) $regDate->diff($referenceNow)->format('%r%a');
            if ($diffDays > 1) {
                throw new \DomainException('Batas waktu pengisian SOAP (maksimal H-1) untuk kunjungan lampau telah berakhir.');
            }
        }

        // 4. Ikat identitas pemeriksa (nip) mutlak dari sesi user yang login (anti-spoofing)
        $actorNip = $actor->getUsername();

        $soap = new PemeriksaanRalan(
            noRawat: $cmd->noRawat,
            tglPerawatan: $cmd->tglPerawatan,
            jamRawat: $cmd->jamRawat,
            tandaVital: new TandaVital(
                suhuTubuh: $cmd->suhuTubuh,
                tensi: $cmd->tensi,
                sistole: $cmd->sistole,
                diastole: $cmd->diastole,
                nadi: $cmd->nadi,
                respirasi: $cmd->respirasi,
                tinggi: $cmd->tinggi,
                berat: $cmd->berat,
                spo2: $cmd->spo2,
                gcs: $cmd->gcs,
                lingkarPerut: $cmd->lingkarPerut,
            ),
            kesadaran: Kesadaran::fromString($cmd->kesadaran),
            keluhan: $cmd->keluhan,
            pemeriksaan: $cmd->pemeriksaan,
            alergi: $cmd->alergi,
            rtl: $cmd->rtl,
            penilaian: $cmd->penilaian,
            instruksi: $cmd->instruksi,
            evaluasi: $cmd->evaluasi,
            nip: $actorNip,
        );

        $metadata = new RmeMetadata(
            noRawat: $cmd->noRawat,
            tglPerawatan: $cmd->tglPerawatan,
            jamRawat: $cmd->jamRawat,
            metaKey: $cmd->metaKey,
            metaValueJson: $cmd->metaValue,
            isMetaDirty: $cmd->isMetaDirty,
        );

        $this->tx->transaction(function () use ($soap, $metadata, $cmd): void {
            $this->ralanRepo->save($soap);

            // Smart Peek Shield: hanya simpan metadata jika isMetaDirty === true & payload valid non-kosong
            if ($metadata->shouldPersist()) {
                $this->ralanRepo->saveMetadata($metadata);
            }

            $this->regRepo->updateStatus($cmd->noRawat, 'Sudah');
        });

        return $soap;
    }
}
