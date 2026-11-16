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
                WHERE no_rkm_medis LIKE :q 
                   OR nm_pasien LIKE :q 
                   OR no_ktp LIKE :q 
                   OR no_peserta LIKE :q 
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
                          WHERE no_rkm_medis LIKE :q 
                             OR nm_pasien LIKE :q 
                             OR no_ktp LIKE :q 
                             OR no_peserta LIKE :q 
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

    public function saveOrUpdate(Pasien $pasien, array $extraFields = []): void
    {
        $rm = $pasien->noRkmMedis->value();
        $existsStmt = $this->nextPdo->prepare('SELECT COUNT(*) FROM pasien WHERE no_rkm_medis = :rm');
        $existsStmt->execute([':rm' => $rm]);
        $exists = ((int) $existsStmt->fetchColumn()) > 0;

        $tmpLahir = trim((string) ($extraFields['tmp_lahir'] ?? ''));
        $nmIbu = trim((string) ($extraFields['nm_ibu'] ?? '-')) ?: '-';
        $golDarah = trim((string) ($extraFields['gol_darah'] ?? '-')) ?: '-';
        $pekerjaan = trim((string) ($extraFields['pekerjaan'] ?? '-')) ?: '-';
        $sttsNikah = trim((string) ($extraFields['stts_nikah'] ?? 'BELUM MENIKAH')) ?: 'BELUM MENIKAH';
        $agama = trim((string) ($extraFields['agama'] ?? '-')) ?: '-';
        $pnd = trim((string) ($extraFields['pnd'] ?? '-')) ?: '-';
        $kdPj = trim((string) ($extraFields['kd_pj'] ?? 'UMU')) ?: 'UMU';
        $tglDaftar = trim((string) ($extraFields['tgl_daftar'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $umurInfo = $pasien->hitungUmurPada(new \DateTimeImmutable());
        $umurStr = $umurInfo['umur'] . ' ' . $umurInfo['satuan'];

        $params = [
            ':no_rkm_medis' => $rm,
            ':nm_pasien' => $pasien->nmPasien,
            ':no_ktp' => $pasien->nik,
            ':jk' => $pasien->jk,
            ':tmp_lahir' => $tmpLahir,
            ':tgl_lahir' => $pasien->tglLahir->format('Y-m-d'),
            ':nm_ibu' => $nmIbu,
            ':alamat' => $pasien->alamat,
            ':gol_darah' => $golDarah,
            ':pekerjaan' => $pekerjaan,
            ':stts_nikah' => $sttsNikah,
            ':agama' => $agama,
            ':tgl_daftar' => $tglDaftar,
            ':no_tlp' => $pasien->noTelp,
            ':umur' => $umurStr,
            ':pnd' => $pnd,
            ':keluarga' => $pasien->keluarga,
            ':namakeluarga' => $pasien->namaKeluarga,
            ':kd_pj' => $kdPj,
            ':no_peserta' => $pasien->noPeserta,
            ':alamatpj' => $pasien->alamatPj,
        ];

        if ($exists) {
            unset($params[':tgl_daftar']);
            $sql = 'UPDATE pasien SET
                        nm_pasien = :nm_pasien,
                        no_ktp = :no_ktp,
                        jk = :jk,
                        tmp_lahir = :tmp_lahir,
                        tgl_lahir = :tgl_lahir,
                        nm_ibu = :nm_ibu,
                        alamat = :alamat,
                        gol_darah = :gol_darah,
                        pekerjaan = :pekerjaan,
                        stts_nikah = :stts_nikah,
                        agama = :agama,
                        no_tlp = :no_tlp,
                        umur = :umur,
                        pnd = :pnd,
                        keluarga = :keluarga,
                        namakeluarga = :namakeluarga,
                        kd_pj = :kd_pj,
                        no_peserta = :no_peserta,
                        alamatpj = :alamatpj
                    WHERE no_rkm_medis = :no_rkm_medis';
        } else {
            $sql = 'INSERT INTO pasien (
                        no_rkm_medis, nm_pasien, no_ktp, jk, tmp_lahir, tgl_lahir, nm_ibu,
                        alamat, gol_darah, pekerjaan, stts_nikah, agama, tgl_daftar, no_tlp,
                        umur, pnd, keluarga, namakeluarga, kd_pj, no_peserta, alamatpj
                    ) VALUES (
                        :no_rkm_medis, :nm_pasien, :no_ktp, :jk, :tmp_lahir, :tgl_lahir, :nm_ibu,
                        :alamat, :gol_darah, :pekerjaan, :stts_nikah, :agama, :tgl_daftar, :no_tlp,
                        :umur, :pnd, :keluarga, :namakeluarga, :kd_pj, :no_peserta, :alamatpj
                    )';
        }

        $this->nextPdo->prepare($sql)->execute($params);
    }

    public function findRawByNoRkmMedis(NoRkmMedis $noRkmMedis): ?array
    {
        $stmt = $this->nextPdo->prepare('SELECT * FROM pasien WHERE no_rkm_medis = :rm LIMIT 1');
        $stmt->execute([':rm' => $noRkmMedis->value()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function listPaginated(string $query = '', int $limit = 20, int $offset = 0): array
    {
        $trimmed = trim($query);
        $where = '';
        $params = [];
        if ($trimmed !== '') {
            $where = 'WHERE p.no_rkm_medis LIKE :q1 OR p.nm_pasien LIKE :q2 OR p.no_ktp LIKE :q3 OR p.no_peserta LIKE :q4 OR p.no_tlp LIKE :q5';
            $like = '%' . $trimmed . '%';
            $params = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like];
        }

        $countStmt = $this->nextPdo->prepare("SELECT COUNT(*) FROM pasien p {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT p.*, COALESCE(pj.png_jawab, p.kd_pj) AS png_jawab
                FROM pasien p
                LEFT JOIN penjab pj ON p.kd_pj = pj.kd_pj
                {$where}
                ORDER BY p.no_rkm_medis DESC
                LIMIT :lim OFFSET :off";
        $stmt = $this->nextPdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', max(1, $limit), \PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $offset), \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
        ];
    }

    public function hasRegistrations(NoRkmMedis $noRkmMedis): bool
    {
        $stmt = $this->nextPdo->prepare('SELECT COUNT(*) FROM reg_periksa WHERE no_rkm_medis = :rm');
        $stmt->execute([':rm' => $noRkmMedis->value()]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function delete(NoRkmMedis $noRkmMedis): void
    {
        $stmt = $this->nextPdo->prepare('DELETE FROM pasien WHERE no_rkm_medis = :rm');
        $stmt->execute([':rm' => $noRkmMedis->value()]);
    }

    public function getVisitHistory(NoRkmMedis $noRkmMedis): array
    {
        $stmt = $this->nextPdo->prepare(
            'SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.stts, r.status_bayar,
                    COALESCE(pl.nm_poli, r.kd_poli) AS nm_poli,
                    COALESCE(d.nm_dokter, r.kd_dokter) AS nm_dokter,
                    COALESCE(pj.png_jawab, r.kd_pj) AS png_jawab
             FROM reg_periksa r
             LEFT JOIN poliklinik pl ON r.kd_poli = pl.kd_poli
             LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
             LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
             WHERE r.no_rkm_medis = :rm
             ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC
             LIMIT 50'
        );
        $stmt->execute([':rm' => $noRkmMedis->value()]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getNextNoRkmMedis(): NoRkmMedis
    {
        $castType = $this->getCastType($this->nextPdo);
        $sql = "SELECT ifnull(MAX(CAST(no_rkm_medis AS {$castType})), 0) FROM pasien";

        $nextMax = (int) $this->nextPdo->query($sql)->fetchColumn();
        $legacyMax = 0;

        if ($this->legacyGuard !== null) {
            $legacyCastType = $this->getCastType($this->legacyGuard->pdo());
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
        $rawJk = strtoupper(trim((string) ($row['jk'] ?? '-')));
        $jk = in_array($rawJk, ['L', 'P', '-'], true) ? $rawJk : '-';

        $rawTgl = trim((string) ($row['tgl_lahir'] ?? ''));
        $tglLahir = ($rawTgl === '' || $rawTgl === '0000-00-00')
            ? new \DateTimeImmutable('1970-01-01')
            : new \DateTimeImmutable($rawTgl);

        return new Pasien(
            noRkmMedis: new NoRkmMedis((string) $row['no_rkm_medis']),
            nmPasien: (string) $row['nm_pasien'],
            nik: (string) ($row['no_ktp'] ?? '-'),
            jk: $jk,
            tglLahir: $tglLahir,
            alamat: (string) ($row['alamat'] ?? '-'),
            noTelp: (string) ($row['no_tlp'] ?? '-'),
            noPeserta: (string) ($row['no_peserta'] ?? ''),
            keluarga: (string) ($row['keluarga'] ?? '-'),
            namaKeluarga: (string) ($row['namakeluarga'] ?? '-'),
            alamatPj: (string) ($row['alamatpj'] ?? '-')
        );
    }
}
