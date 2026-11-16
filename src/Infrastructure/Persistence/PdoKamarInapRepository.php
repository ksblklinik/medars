<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entity\KamarInap;
use App\Domain\Billing\Repository\KamarInapRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use PDO;

final class PdoKamarInapRepository implements KamarInapRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM kamar_inap WHERE no_rawat = :nr ORDER BY tgl_masuk DESC, jam_masuk DESC");
        $stmt->execute([':nr' => $noRawat->value()]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new KamarInap(
                noRawat: new NoRawat((string) $row['no_rawat']),
                kdKamar: (string) $row['kd_kamar'],
                tglMasuk: (string) $row['tgl_masuk'],
                jamMasuk: (string) $row['jam_masuk'],
                tglKeluar: (string) ($row['tgl_keluar'] ?? '0000-00-00'),
                jamKeluar: (string) ($row['jam_keluar'] ?? '00:00:00'),
                lama: (int) ($row['lama'] ?? 1),
                trfKamar: (float) ($row['trf_kamar'] ?? 0),
                ttlBiaya: (float) ($row['ttl_biaya'] ?? 0),
                sttsPulang: (string) ($row['stts_pulang'] ?? '-')
            );
        }

        return $results;
    }

    public function save(KamarInap $kamarInap): void
    {
        $check = $this->pdo->prepare("
            SELECT 1 FROM kamar_inap
            WHERE no_rawat = :nr AND kd_kamar = :kd AND tgl_masuk = :tgl AND jam_masuk = :jam
            LIMIT 1
        ");
        $check->execute([
            ':nr' => $kamarInap->noRawat->value(),
            ':kd' => $kamarInap->kdKamar,
            ':tgl' => $kamarInap->tglMasuk,
            ':jam' => $kamarInap->jamMasuk,
        ]);

        if ($check->fetchColumn()) {
            $sql = "
                UPDATE kamar_inap
                SET tgl_keluar = :tglk, jam_keluar = :jamk, lama = :lama,
                    trf_kamar = :trf, ttl_biaya = :ttl, stts_pulang = :stts
                WHERE no_rawat = :nr AND kd_kamar = :kd AND tgl_masuk = :tgl AND jam_masuk = :jam
            ";
        } else {
            $sql = "
                INSERT INTO kamar_inap (
                    no_rawat, kd_kamar, tgl_masuk, jam_masuk,
                    tgl_keluar, jam_keluar, lama, trf_kamar, ttl_biaya, stts_pulang
                ) VALUES (
                    :nr, :kd, :tgl, :jam,
                    :tglk, :jamk, :lama, :trf, :ttl, :stts
                )
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nr' => $kamarInap->noRawat->value(),
            ':kd' => $kamarInap->kdKamar,
            ':tgl' => $kamarInap->tglMasuk,
            ':jam' => $kamarInap->jamMasuk,
            ':tglk' => $kamarInap->tglKeluar,
            ':jamk' => $kamarInap->jamKeluar,
            ':lama' => $kamarInap->lama,
            ':trf' => $kamarInap->trfKamar,
            ':ttl' => $kamarInap->ttlBiaya,
            ':stts' => $kamarInap->sttsPulang,
        ]);
    }

    public function isPatientStillAdmitted(NoRawat $noRawat): bool
    {
        $rows = $this->findByNoRawat($noRawat);
        foreach ($rows as $r) {
            if ($r->isStillAdmitted()) {
                return true;
            }
        }

        return false;
    }
}
