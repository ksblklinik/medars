<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Application\Rme\DTO\SaveCpptRanapCommand;
use App\Domain\Rme\Entity\PemeriksaanRanap;
use App\Domain\Rme\Repository\PemeriksaanRanapRepositoryInterface;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SaveCpptRanapUseCase
{
    public function __construct(
        private PemeriksaanRanapRepositoryInterface $ranapRepo,
        private RanapStatusCheckerInterface $ranapStatusChecker,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function execute(SaveCpptRanapCommand $cmd, UserAccount $actor): PemeriksaanRanap
    {
        if (!$this->ranapStatusChecker->isPatientStillAdmitted($cmd->noRawat)) {
            throw new \DomainException('Pasien rawat inap sudah pulang atau tidak aktif dirawat.');
        }

        $cppt = new PemeriksaanRanap(
            noRawat: $cmd->noRawat,
            tglPerawatan: $cmd->tglPerawatan,
            jamRawat: $cmd->jamRawat,
            tandaVital: new TandaVital(
                suhuTubuh: $cmd->suhuTubuh,
                tensi: $cmd->tensi,
                nadi: $cmd->nadi,
                respirasi: $cmd->respirasi,
                tinggi: $cmd->tinggi,
                berat: $cmd->berat,
                spo2: $cmd->spo2,
                gcs: $cmd->gcs,
            ),
            kesadaran: Kesadaran::fromString($cmd->kesadaran),
            keluhan: $cmd->keluhan,
            pemeriksaan: $cmd->pemeriksaan,
            alergi: $cmd->alergi,
            rtl: $cmd->rtl,
            penilaian: $cmd->penilaian,
            instruksi: $cmd->instruksi,
            evaluasi: $cmd->evaluasi,
            nip: $actor->getUsername(),
        );

        $this->tx->transaction(function () use ($cppt): void {
            $this->ranapRepo->save($cppt);
        });

        return $cppt;
    }
}
