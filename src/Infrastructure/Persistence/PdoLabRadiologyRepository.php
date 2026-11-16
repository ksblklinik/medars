<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Support\Entity\DetailPeriksaLab;
use App\Domain\Support\Entity\HasilRadiologi;
use App\Domain\Support\Entity\PermintaanLab;
use App\Domain\Support\Entity\PermintaanPemeriksaanLab;
use App\Domain\Support\Entity\PermintaanRadiologi;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use PDO;

final readonly class PdoLabRadiologyRepository implements LabRadiologyRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function getNextOrderNumber(string $prefix, string $dateYmd): string
    {
        $table = $prefix === 'PR' ? 'permintaan_radiologi' : 'permintaan_lab';
        $pattern = $prefix . $dateYmd . '%';

        $stmt = $this->pdo->prepare("SELECT MAX(noorder) FROM {$table} WHERE noorder LIKE :pattern");
        $stmt->execute([':pattern' => $pattern]);
        $max = $stmt->fetchColumn();

        $nextSeq = 1;
        if (is_string($max) && strlen($max) >= strlen($prefix . $dateYmd) + 4) {
            $nextSeq = (int) substr($max, -4) + 1;
        }

        return $prefix . $dateYmd . sprintf('%04d', $nextSeq);
    }

    public function saveLabOrder(PermintaanLab $order): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO permintaan_lab (noorder, no_rawat, tgl_permintaan, jam_permintaan, tgl_sampel, jam_sampel, tgl_hasil, jam_hasil, dokter_perujuk, status, jenis_klaim)
             VALUES (:noorder, :no_rawat, :tgl_permintaan, :jam_permintaan, :tgl_sampel, :jam_sampel, :tgl_hasil, :jam_hasil, :dokter_perujuk, :status, :jenis_klaim)'
        );
        $stmt->execute([
            ':noorder' => $order->noOrder,
            ':no_rawat' => $order->noRawat->value(),
            ':tgl_permintaan' => $order->tglPermintaan,
            ':jam_permintaan' => $order->jamPermintaan,
            ':tgl_sampel' => $order->tglSampel ?? '0000-00-00',
            ':jam_sampel' => $order->jamSampel ?? '00:00:00',
            ':tgl_hasil' => $order->tglHasil ?? '0000-00-00',
            ':jam_hasil' => $order->jamHasil ?? '00:00:00',
            ':dokter_perujuk' => $order->dokterPerujuk,
            ':status' => $order->status,
            ':jenis_klaim' => $order->jenisKlaim->value(),
        ]);
    }

    public function saveLabItem(PermintaanPemeriksaanLab $item): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO permintaan_pemeriksaan_lab (noorder, kd_jenis_prw, stts_bayar, jenis_klaim)
             VALUES (:noorder, :kd_jenis_prw, :stts_bayar, :jenis_klaim)'
        );
        $stmt->execute([
            ':noorder' => $item->noOrder,
            ':kd_jenis_prw' => $item->kdJenisPrw,
            ':stts_bayar' => $item->sttsBayar,
            ':jenis_klaim' => $item->jenisKlaim->value(),
        ]);
    }

    public function findLabOrder(string $noOrder): ?PermintaanLab
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permintaan_lab WHERE noorder = :noorder LIMIT 1');
        $stmt->execute([':noorder' => $noOrder]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return new PermintaanLab(
            noOrder: (string) $row['noorder'],
            noRawat: new NoRawat((string) $row['no_rawat']),
            tglPermintaan: (string) $row['tgl_permintaan'],
            jamPermintaan: (string) $row['jam_permintaan'],
            dokterPerujuk: (string) $row['dokter_perujuk'],
            jenisKlaim: ClaimType::fromString((string) $row['jenis_klaim']),
            status: (string) $row['status'],
            tglSampel: $row['tgl_sampel'] !== null ? (string) $row['tgl_sampel'] : null,
            jamSampel: $row['jam_sampel'] !== null ? (string) $row['jam_sampel'] : null,
            tglHasil: $row['tgl_hasil'] !== null ? (string) $row['tgl_hasil'] : null,
            jamHasil: $row['jam_hasil'] !== null ? (string) $row['jam_hasil'] : null,
        );
    }

    public function findLabItems(string $noOrder): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permintaan_pemeriksaan_lab WHERE noorder = :noorder');
        $stmt->execute([':noorder' => $noOrder]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $row) {
            $items[] = new PermintaanPemeriksaanLab(
                noOrder: (string) $row['noorder'],
                kdJenisPrw: (string) $row['kd_jenis_prw'],
                jenisKlaim: ClaimType::fromString((string) $row['jenis_klaim']),
                sttsBayar: (string) $row['stts_bayar'],
            );
        }
        return $items;
    }

    public function listLabOrdersByDate(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pl.*, rp.no_rkm_medis, COALESCE(ps.nm_pasien, "") AS nm_pasien, COALESCE(d.nm_dokter, pl.dokter_perujuk) AS nm_dokter
             FROM permintaan_lab pl
             LEFT JOIN reg_periksa rp ON pl.no_rawat = rp.no_rawat
             LEFT JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
             LEFT JOIN dokter d ON pl.dokter_perujuk = d.kd_dokter
             WHERE pl.tgl_permintaan BETWEEN :from AND :to
             ORDER BY pl.tgl_permintaan DESC, pl.jam_permintaan DESC, pl.noorder DESC'
        );
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLabResultsByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM detail_periksa_lab WHERE no_rawat = :nr ORDER BY tgl_periksa DESC, jam DESC'
        );
        $stmt->execute([':nr' => $noRawat->value()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($rows as $row) {
            $results[] = new DetailPeriksaLab(
                noRawat: new NoRawat((string) $row['no_rawat']),
                kdJenisPrw: (string) $row['kd_jenis_prw'],
                tglPeriksa: (string) $row['tgl_periksa'],
                jam: (string) $row['jam'],
                pemeriksaan: (string) $row['pemeriksaan'],
                nilai: (string) $row['nilai'],
                nilaiRujukan: (string) $row['nilai_rujukan'],
                keterangan: (string) $row['keterangan'],
            );
        }
        return $results;
    }

    public function saveLabResult(DetailPeriksaLab $detail): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO detail_periksa_lab (no_rawat, kd_jenis_prw, tgl_periksa, jam, pemeriksaan, nilai, nilai_rujukan, keterangan)
             VALUES (:no_rawat, :kd_jenis_prw, :tgl_periksa, :jam, :pemeriksaan, :nilai, :nilai_rujukan, :keterangan)'
        );
        $stmt->execute([
            ':no_rawat' => $detail->noRawat->value(),
            ':kd_jenis_prw' => $detail->kdJenisPrw,
            ':tgl_periksa' => $detail->tglPeriksa,
            ':jam' => $detail->jam,
            ':pemeriksaan' => $detail->pemeriksaan,
            ':nilai' => $detail->nilai,
            ':nilai_rujukan' => $detail->nilaiRujukan,
            ':keterangan' => $detail->keterangan,
        ]);
    }

    public function markLabSampledAndResulted(string $noOrder, string $tgl, string $jam): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE permintaan_lab
             SET tgl_sampel = CASE WHEN tgl_sampel = '0000-00-00' OR tgl_sampel IS NULL THEN :tgl_s ELSE tgl_sampel END,
                 jam_sampel = CASE WHEN jam_sampel = '00:00:00' OR jam_sampel IS NULL THEN :jam_s ELSE jam_sampel END,
                 tgl_hasil = :tgl_h,
                 jam_hasil = :jam_h
             WHERE noorder = :noorder"
        );
        $stmt->execute([
            ':tgl_s' => $tgl,
            ':jam_s' => $jam,
            ':tgl_h' => $tgl,
            ':jam_h' => $jam,
            ':noorder' => $noOrder,
        ]);
    }

    public function deleteLabOrder(string $noOrder): void
    {
        $stmtItems = $this->pdo->prepare('DELETE FROM permintaan_pemeriksaan_lab WHERE noorder = :noorder');
        $stmtItems->execute([':noorder' => $noOrder]);

        $stmtOrder = $this->pdo->prepare('DELETE FROM permintaan_lab WHERE noorder = :noorder');
        $stmtOrder->execute([':noorder' => $noOrder]);
    }

    public function saveRadiologiOrder(PermintaanRadiologi $order): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO permintaan_radiologi (noorder, no_rawat, tgl_permintaan, jam_permintaan, tgl_hasil, jam_hasil, dokter_perujuk, status, jenis_klaim)
             VALUES (:noorder, :no_rawat, :tgl_permintaan, :jam_permintaan, :tgl_hasil, :jam_hasil, :dokter_perujuk, :status, :jenis_klaim)'
        );
        $stmt->execute([
            ':noorder' => $order->noOrder,
            ':no_rawat' => $order->noRawat->value(),
            ':tgl_permintaan' => $order->tglPermintaan,
            ':jam_permintaan' => $order->jamPermintaan,
            ':tgl_hasil' => $order->tglHasil ?? '0000-00-00',
            ':jam_hasil' => $order->jamHasil ?? '00:00:00',
            ':dokter_perujuk' => $order->dokterPerujuk,
            ':status' => $order->status,
            ':jenis_klaim' => $order->jenisKlaim->value(),
        ]);
    }

    public function saveRadiologiItem(string $noOrder, string $kdJenisPrw, string $jenisKlaim): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO permintaan_pemeriksaan_radiologi (noorder, kd_jenis_prw, stts_bayar, jenis_klaim)
             VALUES (:noorder, :kd_jenis_prw, :stts_bayar, :jenis_klaim)'
        );
        $stmt->execute([
            ':noorder' => $noOrder,
            ':kd_jenis_prw' => $kdJenisPrw,
            ':stts_bayar' => 'Belum',
            ':jenis_klaim' => $jenisKlaim,
        ]);
    }

    public function findRadiologiOrder(string $noOrder): ?PermintaanRadiologi
    {
        $stmt = $this->pdo->prepare('SELECT * FROM permintaan_radiologi WHERE noorder = :noorder LIMIT 1');
        $stmt->execute([':noorder' => $noOrder]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return new PermintaanRadiologi(
            noOrder: (string) $row['noorder'],
            noRawat: new NoRawat((string) $row['no_rawat']),
            tglPermintaan: (string) $row['tgl_permintaan'],
            jamPermintaan: (string) $row['jam_permintaan'],
            dokterPerujuk: (string) $row['dokter_perujuk'],
            jenisKlaim: ClaimType::fromString((string) $row['jenis_klaim']),
            status: (string) $row['status'],
            tglHasil: $row['tgl_hasil'] !== null ? (string) $row['tgl_hasil'] : null,
            jamHasil: $row['jam_hasil'] !== null ? (string) $row['jam_hasil'] : null,
        );
    }

    public function listRadiologiOrdersByDate(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pr.*, rp.no_rkm_medis, COALESCE(ps.nm_pasien, "") AS nm_pasien, COALESCE(d.nm_dokter, pr.dokter_perujuk) AS nm_dokter
             FROM permintaan_radiologi pr
             LEFT JOIN reg_periksa rp ON pr.no_rawat = rp.no_rawat
             LEFT JOIN pasien ps ON rp.no_rkm_medis = ps.no_rkm_medis
             LEFT JOIN dokter d ON pr.dokter_perujuk = d.kd_dokter
             WHERE pr.tgl_permintaan BETWEEN :from AND :to
             ORDER BY pr.tgl_permintaan DESC, pr.jam_permintaan DESC, pr.noorder DESC'
        );
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveHasilRadiologi(HasilRadiologi $hasil): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO hasil_radiologi (no_rawat, tgl_periksa, jam, hasil)
             VALUES (:no_rawat, :tgl_periksa, :jam, :hasil)'
        );
        $stmt->execute([
            ':no_rawat' => $hasil->noRawat->value(),
            ':tgl_periksa' => $hasil->tglPeriksa,
            ':jam' => $hasil->jam,
            ':hasil' => $hasil->hasil,
        ]);
    }

    public function markRadiologiResulted(string $noOrder, string $tgl, string $jam): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE permintaan_radiologi SET tgl_hasil = :tgl, jam_hasil = :jam WHERE noorder = :noorder'
        );
        $stmt->execute([
            ':tgl' => $tgl,
            ':jam' => $jam,
            ':noorder' => $noOrder,
        ]);
    }
}
