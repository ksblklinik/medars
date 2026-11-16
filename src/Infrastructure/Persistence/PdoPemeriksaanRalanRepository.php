<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\PemeriksaanRalan;
use App\Domain\Rme\Entity\RmeMetadata;
use App\Domain\Rme\Repository\PemeriksaanRalanRepositoryInterface;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;
use PDO;

final class PdoPemeriksaanRalanRepository implements PemeriksaanRalanRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findByTriplet(NoRawat $noRawat, string $tglPerawatan, string $jamRawat): ?PemeriksaanRalan
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pemeriksaan_ralan
            WHERE no_rawat = :nr AND tgl_perawatan = :tgl AND jam_rawat = :jam
            LIMIT 1
        ");
        $stmt->execute([
            ':nr' => $noRawat->value(),
            ':tgl' => $tglPerawatan,
            ':jam' => $jamRawat,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findLatestByNoRawat(NoRawat $noRawat): ?PemeriksaanRalan
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pemeriksaan_ralan
            WHERE no_rawat = :nr
            ORDER BY tgl_perawatan DESC, jam_rawat DESC
            LIMIT 1
        ");
        $stmt->execute([':nr' => $noRawat->value()]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    public function findByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pemeriksaan_ralan
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

    public function save(PemeriksaanRalan $p): void
    {
        $existing = $this->findByTriplet($p->noRawat, $p->tglPerawatan, $p->jamRawat);

        if ($existing === null) {
            $sql = "
                INSERT INTO pemeriksaan_ralan (
                    no_rawat, tgl_perawatan, jam_rawat,
                    suhu_tubuh, tensi, sistole, diastole, nadi, respirasi, tinggi, berat, spo2, gcs, lingkar_perut,
                    kesadaran, keluhan, pemeriksaan, alergi, rtl, penilaian, instruksi, evaluasi, nip
                ) VALUES (
                    :nr, :tgl, :jam,
                    :suhu, :tensi, :sistole, :diastole, :nadi, :resp, :tinggi, :berat, :spo2, :gcs, :lp,
                    :kesadaran, :keluhan, :pemeriksaan, :alergi, :rtl, :penilaian, :instruksi, :evaluasi, :nip
                )
            ";
        } else {
            $sql = "
                UPDATE pemeriksaan_ralan
                SET suhu_tubuh = :suhu,
                    tensi = :tensi,
                    sistole = :sistole,
                    diastole = :diastole,
                    nadi = :nadi,
                    respirasi = :resp,
                    tinggi = :tinggi,
                    berat = :berat,
                    spo2 = :spo2,
                    gcs = :gcs,
                    lingkar_perut = :lp,
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
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nr' => $p->noRawat->value(),
            ':tgl' => $p->tglPerawatan,
            ':jam' => $p->jamRawat,
            ':suhu' => $p->tandaVital->suhuTubuh,
            ':tensi' => $p->tandaVital->tensi,
            ':sistole' => $p->tandaVital->sistole,
            ':diastole' => $p->tandaVital->diastole,
            ':nadi' => $p->tandaVital->nadi,
            ':resp' => $p->tandaVital->respirasi,
            ':tinggi' => $p->tandaVital->tinggi,
            ':berat' => $p->tandaVital->berat,
            ':spo2' => $p->tandaVital->spo2,
            ':gcs' => $p->tandaVital->gcs,
            ':lp' => $p->tandaVital->lingkarPerut,
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

    public function saveMetadata(RmeMetadata $metadata): void
    {
        // Smart Peek Shield: tolak penulisan jika tidak ada perubahan atau payload kosong
        if (!$metadata->shouldPersist()) {
            return;
        }

        $del = $this->pdo->prepare("
            DELETE FROM mlite_pemeriksaan_ralan_metadata
            WHERE no_rawat = :nr AND tgl_perawatan = :tgl AND jam_rawat = :jam AND meta_key = :mkey
        ");
        $del->execute([
            ':nr' => $metadata->noRawat->value(),
            ':tgl' => $metadata->tglPerawatan,
            ':jam' => $metadata->jamRawat,
            ':mkey' => $metadata->metaKey,
        ]);

        $ins = $this->pdo->prepare("
            INSERT INTO mlite_pemeriksaan_ralan_metadata (no_rawat, tgl_perawatan, jam_rawat, meta_key, meta_value)
            VALUES (:nr, :tgl, :jam, :mkey, :mval)
        ");
        $ins->execute([
            ':nr' => $metadata->noRawat->value(),
            ':tgl' => $metadata->tglPerawatan,
            ':jam' => $metadata->jamRawat,
            ':mkey' => $metadata->metaKey,
            ':mval' => $metadata->metaValueJson,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PemeriksaanRalan
    {
        return new PemeriksaanRalan(
            noRawat: new NoRawat((string) $row['no_rawat']),
            tglPerawatan: (string) $row['tgl_perawatan'],
            jamRawat: (string) $row['jam_rawat'],
            tandaVital: new TandaVital(
                suhuTubuh: (string) ($row['suhu_tubuh'] ?? ''),
                tensi: (string) ($row['tensi'] ?? ''),
                sistole: (string) ($row['sistole'] ?? ''),
                diastole: (string) ($row['diastole'] ?? ''),
                nadi: (string) ($row['nadi'] ?? ''),
                respirasi: (string) ($row['respirasi'] ?? ''),
                tinggi: (string) ($row['tinggi'] ?? ''),
                berat: (string) ($row['berat'] ?? ''),
                spo2: (string) ($row['spo2'] ?? ''),
                gcs: (string) ($row['gcs'] ?? ''),
                lingkarPerut: (string) ($row['lingkar_perut'] ?? '')
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
