<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\Entity\Pasien;
use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Infrastructure\Database\ReadOnlyConnectionGuard;

final readonly class PdoPasienRepository implements PasienRepositoryInterface
{
    public function __construct(
        private \PDO $nextPdo,
        private ?ReadOnlyConnectionGuard $legacyGuard = null
    ) {}

    public function findByNoRkmMedis(NoRkmMedis $noRkmMedis): ?Pasien
    {
        $sql = 'SELECT * FROM pasien WHERE no_rkm_medis = :rm LIMIT 1';

        $stmt = $this->nextPdo->prepare($sql);
        $stmt->execute(['rm' => $noRkmMedis->value()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $this->hydrate($row);
        }

        if ($this->legacyGuard !== null) {
            $legacyRows = $this->legacyGuard->query($sql, ['rm' => $noRkmMedis->value()]);
            if (!empty($legacyRows)) {
                return $this->hydrate($legacyRows[0]);
            }
        }

        return null;
    }

    public function findByNik(string $nik): ?Pasien
    {
        $sql = 'SELECT * FROM pasien WHERE no_ktp = :nik LIMIT 1';

        $stmt = $this->nextPdo->prepare($sql);
        $stmt->execute(['nik' => $nik]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $this->hydrate($row);
        }

        if ($this->legacyGuard !== null) {
            $legacyRows = $this->legacyGuard->query($sql, ['nik' => $nik]);
            if (!empty($legacyRows)) {
                return $this->hydrate($legacyRows[0]);
            }
        }

        return null;
    }

    public function findByNoPeserta(string $noPeserta): ?Pasien
    {
        $sql = 'SELECT * FROM pasien WHERE no_peserta = :no_peserta LIMIT 1';

        $stmt = $this->nextPdo->prepare($sql);
        $stmt->execute(['no_peserta' => $noPeserta]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $this->hydrate($row);
        }

        if ($this->legacyGuard !== null) {
            $legacyRows = $this->legacyGuard->query($sql, ['no_peserta' => $noPeserta]);
            if (!empty($legacyRows)) {
                return $this->hydrate($legacyRows[0]);
            }
        }

        return null;
    }

    /**
     * @return array<Pasien>
     */
    public function search(string $query, int $limit = 20): array
    {
        $sql = 'SELECT * FROM pasien 
                WHERE no_rkm_medis LIKE :q OR nm_pasien LIKE :q 
                ORDER BY no_rkm_medis DESC 
                LIMIT ' . (int) $limit;

        $stmt = $this->nextPdo->prepare($sql);
        $stmt->execute(['q' => '%' . $query . '%']);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [];
        $seenRm = [];

        foreach ($rows as $row) {
            $pasien = $this->hydrate($row);
            $results[] = $pasien;
            $seenRm[$pasien->noRkmMedis->value()] = true;
        }

        // Jika masih kurang dari limit dan legacy connection tersedia, cari juga di legacy
        if (count($results) < $limit && $this->legacyGuard !== null) {
            $remaining = $limit - count($results);
            $legacySql = 'SELECT * FROM pasien 
                          WHERE no_rkm_medis LIKE :q OR nm_pasien LIKE :q 
                          ORDER BY no_rkm_medis DESC 
                          LIMIT ' . (int) $remaining;

            $legacyRows = $this->legacyGuard->query($legacySql, ['q' => '%' . $query . '%']);
            foreach ($legacyRows as $row) {
                $rm = (string) $row['no_rkm_medis'];
                if (!isset($seenRm[$rm])) {
                    $results[] = $this->hydrate($row);
                    $seenRm[$rm] = true;
                }
            }
        }

        return $results;
    }

    public function save(Pasien $pasien): void
    {
        $sql = 'INSERT INTO pasien (
                    no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir,
                    alamat, no_tlp, no_peserta, keluarga, namakeluarga, alamatpj
                ) VALUES (
                    :no_rkm_medis, :nm_pasien, :no_ktp, :jk, :tgl_lahir,
                    :alamat, :no_tlp, :no_peserta, :keluarga, :namakeluarga, :alamatpj
                )';

        $stmt = $this->nextPdo->prepare($sql);
        $stmt->execute([
            'no_rkm_medis' => $pasien->noRkmMedis->value(),
            'nm_pasien' => $pasien->nmPasien,
            'no_ktp' => $pasien->nik,
            'jk' => $pasien->jk,
            'tgl_lahir' => $pasien->tglLahir->format('Y-m-d'),
            'alamat' => $pasien->alamat,
            'no_tlp' => $pasien->noTelp,
            'no_peserta' => $pasien->noPeserta,
            'keluarga' => $pasien->keluarga,
            'namakeluarga' => $pasien->namaKeluarga,
            'alamatpj' => $pasien->alamatPj,
        ]);
    }

    public function getNextNoRkmMedis(): NoRkmMedis
    {
        $castType = $this->getCastType($this->nextPdo);
        $sql = "SELECT ifnull(MAX(CAST(no_rkm_medis AS {$castType})), 0) FROM pasien";

        $nextMax = (int) $this->nextPdo->query($sql)->fetchColumn();
        $legacyMax = 0;

        if ($this->legacyGuard !== null) {
            $legacyCastType = 'INTEGER'; // Default SQLite, atau cek driver jika koneksi langsung
            $legacySql = "SELECT ifnull(MAX(CAST(no_rkm_medis AS {$legacyCastType})), 0) FROM pasien";
            $legacyResult = $this->legacyGuard->query($legacySql);
            if (!empty($legacyResult)) {
                $firstRow = reset($legacyResult);
                $legacyMax = (int) reset($firstRow);
            }
        }

        $highest = max($nextMax, $legacyMax);

        return NoRkmMedis::fromSequence($highest + 1);
    }

    private function getCastType(\PDO $pdo): string
    {
        return $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Pasien
    {
        return new Pasien(
            noRkmMedis: new NoRkmMedis((string) $row['no_rkm_medis']),
            nmPasien: (string) $row['nm_pasien'],
            nik: (string) ($row['no_ktp'] ?? '-'),
            jk: (string) ($row['jk'] ?? 'L'),
            tglLahir: new \DateTimeImmutable((string) $row['tgl_lahir']),
            alamat: (string) ($row['alamat'] ?? '-'),
            noTelp: (string) ($row['no_tlp'] ?? '-'),
            noPeserta: (string) ($row['no_peserta'] ?? ''),
            keluarga: (string) ($row['keluarga'] ?? '-'),
            namaKeluarga: (string) ($row['namakeluarga'] ?? '-'),
            alamatPj: (string) ($row['alamatpj'] ?? '-')
        );
    }
}
