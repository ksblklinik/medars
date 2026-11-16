<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Pharmacy\Entity\DetailPemberianObat;
use App\Domain\Pharmacy\Entity\ResepDokterNonRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikan;
use App\Domain\Pharmacy\Entity\ResepDokterRacikanDetail;
use App\Domain\Pharmacy\Entity\ResepObat;
use App\Domain\Pharmacy\Repository\ResepRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Domain\Registration\ValueObject\NoRawat;
use PDO;

final class PdoResepRepository implements ResepRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getNextNoResep(\DateTimeInterface $date): NoResep
    {
        $prefix = $date->format('Ymd');
        $stmt = $this->pdo->prepare("SELECT MAX(no_resep) FROM resep_obat WHERE no_resep LIKE :prefix");
        $stmt->execute([':prefix' => $prefix . '%']);
        $max = $stmt->fetchColumn();

        $next = 1;
        if (is_string($max) && str_starts_with($max, $prefix)) {
            $suffix = substr($max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return new NoResep($prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT));
    }

    public function findByNoResep(NoResep $noResep): ?ResepObat
    {
        $stmt = $this->pdo->prepare("SELECT * FROM resep_obat WHERE no_resep = :nr LIMIT 1");
        $stmt->execute([':nr' => $noResep->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrateHeader($row) : null;
    }

    public function findByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM resep_obat WHERE no_rawat = :nr ORDER BY tgl_peresepan DESC, jam_peresepan DESC");
        $stmt->execute([':nr' => $noRawat->value()]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrateHeader($row);
        }

        return $results;
    }

    public function findNonRacikan(NoResep $noResep): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM resep_dokter_nonracikan WHERE no_resep = :nr");
        $stmt->execute([':nr' => $noResep->value()]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new ResepDokterNonRacikan(
                noResep: $noResep,
                kodeBrng: (string) $row['kode_brng'],
                jml: (float) $row['jml'],
                aturanPakai: (string) ($row['aturan_pakai'] ?? '-')
            );
        }

        return $results;
    }

    public function findRacikan(NoResep $noResep): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM resep_dokter_racikan WHERE no_resep = :nr ORDER BY no_racik ASC");
        $stmt->execute([':nr' => $noResep->value()]);
        $headers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($headers === []) {
            return [];
        }

        $detailStmt = $this->pdo->prepare("SELECT * FROM resep_dokter_racikan_detail WHERE no_resep = :nr");
        $detailStmt->execute([':nr' => $noResep->value()]);
        $allDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

        $detailsByRacik = [];
        foreach ($allDetails as $d) {
            $racikKey = (string) $d['no_racik'];
            $detailsByRacik[$racikKey][] = new ResepDokterRacikanDetail(
                noResep: $noResep,
                noRacik: $racikKey,
                kodeBrng: (string) $d['kode_brng'],
                p1: (float) ($d['p1'] ?? 1.0),
                p2: (float) ($d['p2'] ?? 1.0),
                kandungan: (string) ($d['kandungan'] ?? ''),
                jml: (float) $d['jml']
            );
        }

        $results = [];
        foreach ($headers as $h) {
            $noRacik = (string) $h['no_racik'];
            $results[] = new ResepDokterRacikan(
                noResep: $noResep,
                noRacik: $noRacik,
                namaRacik: (string) $h['nama_racik'],
                kdRacik: (string) ($h['kd_racik'] ?? 'R01'),
                jmlDr: (int) $h['jml_dr'],
                aturanPakai: (string) ($h['aturan_pakai'] ?? '-'),
                keterangan: (string) ($h['keterangan'] ?? '-'),
                details: $detailsByRacik[$noRacik] ?? []
            );
        }

        return $results;
    }

    public function saveHeader(ResepObat $resep): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO resep_obat (no_resep, tgl_peresepan, jam_peresepan, no_rawat, kd_dokter, tgl_perawatan, jam, status)
            VALUES (:no_resep, :tgl_p, :jam_p, :no_rawat, :kd_dokter, :tgl_r, :jam_r, :status)
        ");
        $stmt->execute([
            ':no_resep' => $resep->noResep->value(),
            ':tgl_p' => $resep->tglPeresepan,
            ':jam_p' => $resep->jamPeresepan,
            ':no_rawat' => $resep->noRawat->value(),
            ':kd_dokter' => $resep->kdDokter,
            ':tgl_r' => $resep->tglPerawatan,
            ':jam_r' => $resep->jam,
            ':status' => $resep->status,
        ]);
    }

    public function saveNonRacikan(ResepDokterNonRacikan $item): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO resep_dokter_nonracikan (no_resep, kode_brng, jml, aturan_pakai)
            VALUES (:nr, :kd, :jml, :aturan)
        ");
        $stmt->execute([
            ':nr' => $item->noResep->value(),
            ':kd' => $item->kodeBrng,
            ':jml' => $item->jml,
            ':aturan' => $item->aturanPakai,
        ]);
    }

    public function saveRacikan(ResepDokterRacikan $racikan): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO resep_dokter_racikan (no_resep, no_racik, nama_racik, kd_racik, jml_dr, aturan_pakai, keterangan)
            VALUES (:nr, :noracik, :nama, :kdracik, :jmldr, :aturan, :ket)
        ");
        $stmt->execute([
            ':nr' => $racikan->noResep->value(),
            ':noracik' => $racikan->noRacik,
            ':nama' => $racikan->namaRacik,
            ':kdracik' => $racikan->kdRacik,
            ':jmldr' => $racikan->jmlDr,
            ':aturan' => $racikan->aturanPakai,
            ':ket' => $racikan->keterangan,
        ]);

        $detailStmt = $this->pdo->prepare("
            INSERT INTO resep_dokter_racikan_detail (no_resep, no_racik, kode_brng, p1, p2, kandungan, jml)
            VALUES (:nr, :noracik, :kd, :p1, :p2, :kand, :jml)
        ");

        foreach ($racikan->details as $d) {
            $detailStmt->execute([
                ':nr' => $d->noResep->value(),
                ':noracik' => $d->noRacik,
                ':kd' => $d->kodeBrng,
                ':p1' => $d->p1,
                ':p2' => $d->p2,
                ':kand' => $d->kandungan,
                ':jml' => $d->jml,
            ]);
        }
    }

    public function markDispensed(NoResep $noResep, string $tglPerawatan, string $jam): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE resep_obat SET tgl_perawatan = :tgl, jam = :jam WHERE no_resep = :nr
        ");
        $stmt->execute([
            ':tgl' => $tglPerawatan,
            ':jam' => $jam,
            ':nr' => $noResep->value(),
        ]);
    }

    public function saveObatRacikan(
        string $tglPerawatan,
        string $jam,
        NoRawat $noRawat,
        ResepDokterRacikan $racikan
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO obat_racikan (tgl_perawatan, jam, no_rawat, no_racik, nama_racik, kd_racik, jml_dr, aturan_pakai, keterangan)
            VALUES (:tgl, :jam, :nr, :noracik, :nama, :kdracik, :jmldr, :aturan, :ket)
        ");
        $stmt->execute([
            ':tgl' => $tglPerawatan,
            ':jam' => $jam,
            ':nr' => $noRawat->value(),
            ':noracik' => $racikan->noRacik,
            ':nama' => $racikan->namaRacik,
            ':kdracik' => $racikan->kdRacik,
            ':jmldr' => $racikan->jmlDr,
            ':aturan' => $racikan->aturanPakai,
            ':ket' => $racikan->keterangan,
        ]);
    }

    public function saveDetailPemberianObat(DetailPemberianObat $detail): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO detail_pemberian_obat (
                tgl_perawatan, jam, no_rawat, kode_brng, no_batch, no_faktur,
                h_beli, biaya_obat, jml, embalase, tuslah, total, status, kd_bangsal, jenis_klaim
            ) VALUES (
                :tgl, :jam, :nr, :kd, :batch, :faktur,
                :hbeli, :biaya, :jml, :emb, :tuslah, :total, :stts, :depo, :klaim
            )
        ");
        $stmt->execute([
            ':tgl' => $detail->tglPerawatan,
            ':jam' => $detail->jam,
            ':nr' => $detail->noRawat->value(),
            ':kd' => $detail->kodeBrng,
            ':batch' => $detail->noBatch,
            ':faktur' => $detail->noFaktur,
            ':hbeli' => $detail->hBeli,
            ':biaya' => $detail->biayaObat,
            ':jml' => $detail->jml,
            ':emb' => $detail->embalase,
            ':tuslah' => $detail->tuslah,
            ':total' => $detail->total,
            ':stts' => $detail->status,
            ':depo' => $detail->kdBangsal->value(),
            ':klaim' => $detail->jenisKlaim->value(),
        ]);
    }

    public function deleteHeaderAndItems(NoResep $noResep): void
    {
        $nr = $noResep->value();
        $this->pdo->prepare("DELETE FROM resep_dokter_racikan_detail WHERE no_resep = :nr")->execute([':nr' => $nr]);
        $this->pdo->prepare("DELETE FROM resep_dokter_racikan WHERE no_resep = :nr")->execute([':nr' => $nr]);
        $this->pdo->prepare("DELETE FROM resep_dokter_nonracikan WHERE no_resep = :nr")->execute([':nr' => $nr]);
        $this->pdo->prepare("DELETE FROM resep_obat WHERE no_resep = :nr")->execute([':nr' => $nr]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateHeader(array $row): ResepObat
    {
        return new ResepObat(
            noResep: new NoResep((string) $row['no_resep']),
            tglPeresepan: (string) $row['tgl_peresepan'],
            jamPeresepan: (string) $row['jam_peresepan'],
            noRawat: new NoRawat((string) $row['no_rawat']),
            kdDokter: (string) $row['kd_dokter'],
            tglPerawatan: (string) ($row['tgl_perawatan'] ?? '0000-00-00'),
            jam: (string) ($row['jam'] ?? '00:00:00'),
            status: (string) ($row['status'] ?? 'ralan')
        );
    }
}
