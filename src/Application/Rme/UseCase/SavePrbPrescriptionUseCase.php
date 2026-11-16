<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Application\Rme\DTO\SavePrbCommand;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Rme\Entity\PemeriksaanRalan;
use App\Domain\Rme\Entity\RmeMetadata;
use App\Domain\Rme\Repository\PemeriksaanRalanRepositoryInterface;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class SavePrbPrescriptionUseCase
{
    public function __construct(
        private PemeriksaanRalanRepositoryInterface $ralanRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    /**
     * @return array{tgl_perawatan: string, jam_rawat: string, rtl: string}
     */
    public function execute(SavePrbCommand $cmd, UserAccount $actor): array
    {
        $reg = $this->regRepo->findByNoRawat($cmd->noRawat);
        if ($reg === null) {
            throw new \DomainException("Data registrasi kunjungan tidak ditemukan: {$cmd->noRawat->value()}.");
        }

        if (!$actor->canAccessPoli($reg->kdPoli) && $actor->getUsername() !== $reg->kdDokter) {
            throw new ForbiddenException("Anda tidak memiliki izin melayani poliklinik {$reg->kdPoli}.");
        }

        $prbLines = [];
        foreach ($cmd->items as $idx => $item) {
            $num = $idx + 1;
            $prbLines[] = "{$num}. {$item['nama_obat']} ({$item['jumlah']}) - {$item['aturan_pakai']}";
        }
        $prbSummary = "[Resep PRB 30 Hari - {$cmd->diagnosaKronis}]\n" . implode("\n", $prbLines);

        $resultTriplet = ['tgl_perawatan' => '', 'jam_rawat' => '', 'rtl' => ''];

        $this->tx->transaction(function () use ($cmd, $actor, $prbSummary, &$resultTriplet): void {
            $existing = null;
            if ($cmd->tglPerawatan !== '' && $cmd->jamRawat !== '') {
                $existing = $this->ralanRepo->findByTriplet($cmd->noRawat, $cmd->tglPerawatan, $cmd->jamRawat);
            }
            if ($existing === null) {
                $existing = $this->ralanRepo->findLatestByNoRawat($cmd->noRawat);
            }

            if ($existing === null) {
                $tgl = $cmd->tglPerawatan !== '' ? $cmd->tglPerawatan : date('Y-m-d');
                $jam = $cmd->jamRawat !== '' ? $cmd->jamRawat : date('H:i:s');

                $soap = new PemeriksaanRalan(
                    noRawat: $cmd->noRawat,
                    tglPerawatan: $tgl,
                    jamRawat: $jam,
                    tandaVital: new TandaVital(),
                    kesadaran: Kesadaran::fromString('Compos Mentis'),
                    keluhan: '-',
                    pemeriksaan: '-',
                    alergi: '-',
                    rtl: $prbSummary,
                    penilaian: $cmd->diagnosaKronis !== '' ? $cmd->diagnosaKronis : '-',
                    instruksi: '-',
                    evaluasi: '-',
                    nip: $actor->getUsername(),
                );
            } else {
                $tgl = $existing->tglPerawatan;
                $jam = $existing->jamRawat;

                $mergedRtl = ($existing->rtl === '-' || trim($existing->rtl) === '')
                    ? $prbSummary
                    : (str_contains($existing->rtl, '[Resep PRB 30 Hari') ? $existing->rtl : $existing->rtl . "\n\n" . $prbSummary);

                $soap = new PemeriksaanRalan(
                    noRawat: $existing->noRawat,
                    tglPerawatan: $tgl,
                    jamRawat: $jam,
                    tandaVital: $existing->tandaVital,
                    kesadaran: $existing->kesadaran,
                    keluhan: $existing->keluhan,
                    pemeriksaan: $existing->pemeriksaan,
                    alergi: $existing->alergi,
                    rtl: $mergedRtl,
                    penilaian: $existing->penilaian,
                    instruksi: $existing->instruksi,
                    evaluasi: $existing->evaluasi,
                    nip: $existing->nip !== '' ? $existing->nip : $actor->getUsername(),
                );
            }

            $this->ralanRepo->save($soap);

            $metaJson = json_encode([
                'diagnosa_kronis' => $cmd->diagnosaKronis,
                'items' => $cmd->items,
                'catatan' => $cmd->catatan,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            $metadata = new RmeMetadata(
                noRawat: $cmd->noRawat,
                tglPerawatan: $tgl,
                jamRawat: $jam,
                metaKey: 'meta_prb',
                metaValueJson: $metaJson,
                isMetaDirty: true,
            );
            $this->ralanRepo->saveMetadata($metadata);

            $resultTriplet = [
                'tgl_perawatan' => $tgl,
                'jam_rawat' => $jam,
                'rtl' => $soap->rtl,
            ];
        });

        return $resultTriplet;
    }
}
