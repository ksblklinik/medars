<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Entity\Odontogram;
use App\Domain\Rme\Repository\OdontogramRepositoryInterface;
use PDO;

final class PdoOdontogramRepository implements OdontogramRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findByNoRkmMedis(NoRkmMedis $noRkmMedis): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM mlite_odontogram
            WHERE no_rkm_medis = :rm
            ORDER BY id DESC
        ");
        $stmt->execute([':rm' => $noRkmMedis->value()]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new Odontogram(
                id: (int) $row['id'],
                noRkmMedis: new NoRkmMedis((string) $row['no_rkm_medis']),
                noRawat: new NoRawat((string) $row['no_rawat']),
                tglPemeriksaan: (string) $row['tgl_pemeriksaan'],
                nomorGigi: (string) $row['nomor_gigi'],
                kondisi: (string) $row['kondisi'],
                catatan: (string) ($row['catatan'] ?? ''),
                idUser: (int) ($row['id_user'] ?? 0)
            );
        }

        return $results;
    }

    public function save(Odontogram $odo): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mlite_odontogram (no_rkm_medis, no_rawat, tgl_pemeriksaan, nomor_gigi, kondisi, catatan, id_user)
            VALUES (:rm, :nr, :tgl, :gigi, :kondisi, :catatan, :uid)
        ");
        $stmt->execute([
            ':rm' => $odo->noRkmMedis->value(),
            ':nr' => $odo->noRawat->value(),
            ':tgl' => $odo->tglPemeriksaan,
            ':gigi' => $odo->nomorGigi,
            ':kondisi' => $odo->kondisi,
            ':catatan' => $odo->catatan,
            ':uid' => $odo->idUser,
        ]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM mlite_odontogram WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
}
