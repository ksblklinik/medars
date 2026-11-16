<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\PemeriksaanRanap;
use App\Domain\Rme\Repository\PemeriksaanRanapRepositoryInterface;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;
use PDO;

final class PdoPemeriksaanRanapRepository implements PemeriksaanRanapRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pemeriksaan_ranap
            WHERE no_rawat = :nr
            ORDER BY tgl_perawatan DESC, jam_rawat DESC
        ");
        $stmt->execute([':nr' => $noRawat->value()]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(PemeriksaanRanap $p): void
    {
        $check = $this->pdo->prepare("
            SELECT 1 FROM pemeriksaan_ranap
            WHERE no_rawat = :nr AND tgl_perawatan = :tgl AND jam_rawat = :jam
            LIMIT 1
        ");
        $check->execute([
            ':nr' => $p->noRawat->value(),
            ':tgl' => $p->tglPerawatan,
            ':jam' => $p->jamRawat,
        ]);

        if ($check->fetchColumn()) {
            $sql = "
                UPDATE pemeriksaan_ranap
                SET suhu_tubuh = :suhu,
                    tensi = :tensi,
                    nadi = :nadi,
                    respirasi = :resp,
                    tinggi = :tinggi,
                    berat = :berat,
                    spo2 = :spo2,
                    gcs = :gcs,
                    kesadaran = :kesadaran,
                    keluhan = :keluhan,
                    pemeriksaan = :pemeriksaan,
                    alergi = :alergi,
                    rtl = :rtl,
                    penilaian = :penilaian,
                    instruksi = :instruksi,
                    evaluasi = :evaluasi,
                    nip = :nip
                WHERE no_rawat = :nr AND tgl_perawatan = :tgl AND jam_rawat = :jam
            ";
        } else {
            $sql = "
                INSERT INTO pemeriksaan_ranap (
                    no_rawat, tgl_perawatan, jam_rawat,
                    suhu_tubuh, tensi, nadi, respirasi, tinggi, berat, spo2, gcs,
                    kesadaran, keluhan, pemeriksaan, alergi, rtl, penilaian, instruksi, evaluasi, nip
                ) VALUES (
                    :nr, :tgl, :jam,
                    :suhu, :tensi, :nadi, :resp, :tinggi, :berat, :spo2, :gcs,
                    :kesadaran, :keluhan, :pemeriksaan, :alergi, :rtl, :penilaian, :instruksi, :evaluasi, :nip
                )
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nr' => $p->noRawat->value(),
            ':tgl' => $p->tglPerawatan,
            ':jam' => $p->jamRawat,
            ':suhu' => $p->tandaVital->suhuTubuh,
            ':tensi' => $p->tandaVital->tensi,
            ':nadi' => $p->tandaVital->nadi,
            ':resp' => $p->tandaVital->respirasi,
            ':tinggi' => $p->tandaVital->tinggi,
            ':berat' => $p->tandaVital->berat,
            ':spo2' => $p->tandaVital->spo2,
            ':gcs' => $p->tandaVital->gcs,
            ':kesadaran' => $p->kesadaran->value(),
            ':keluhan' => $p->keluhan,
            ':pemeriksaan' => $p->pemeriksaan,
            ':alergi' => $p->alergi,
            ':rtl' => $p->rtl,
            ':penilaian' => $p->penilaian,
            ':instruksi' => $p->instruksi,
            ':evaluasi' => $p->evaluasi,
            ':nip' => $p->nip,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PemeriksaanRanap
    {
        return new PemeriksaanRanap(
            noRawat: new NoRawat((string) $row['no_rawat']),
            tglPerawatan: (string) $row['tgl_perawatan'],
            jamRawat: (string) $row['jam_rawat'],
            tandaVital: new TandaVital(
                suhuTubuh: (string) ($row['suhu_tubuh'] ?? ''),
                tensi: (string) ($row['tensi'] ?? ''),
                nadi: (string) ($row['nadi'] ?? ''),
                respirasi: (string) ($row['respirasi'] ?? ''),
                tinggi: (string) ($row['tinggi'] ?? ''),
                berat: (string) ($row['berat'] ?? ''),
                spo2: (string) ($row['spo2'] ?? ''),
                gcs: (string) ($row['gcs'] ?? '')
            ),
            kesadaran: Kesadaran::fromString((string) ($row['kesadaran'] ?? 'Compos Mentis')),
            keluhan: (string) ($row['keluhan'] ?? ''),
            pemeriksaan: (string) ($row['pemeriksaan'] ?? ''),
            alergi: (string) ($row['alergi'] ?? ''),
            rtl: (string) ($row['rtl'] ?? ''),
            penilaian: (string) ($row['penilaian'] ?? ''),
            instruksi: (string) ($row['instruksi'] ?? ''),
            evaluasi: (string) ($row['evaluasi'] ?? ''),
            nip: (string) ($row['nip'] ?? '')
        );
    }
}
