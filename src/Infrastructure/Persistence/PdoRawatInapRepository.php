<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Ranap\Repository\RawatInapRepositoryInterface;

final readonly class PdoRawatInapRepository implements RawatInapRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function listKamar(string $status = ''): array
    {
        $sql = "
            SELECT k.kd_kamar, k.kd_bangsal, b.nm_bangsal, k.trf_kamar, k.status, k.kelas, k.statusdata
            FROM kamar k
            LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            WHERE k.statusdata = '1'
        ";
        $params = [];
        if ($status !== '') {
            $sql .= ' AND k.status = :st';
            $params[':st'] = $status;
        }
        $sql .= ' ORDER BY k.kd_kamar ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveKamar(array $payload): void
    {
        $kdKamar = trim((string) ($payload['kd_kamar'] ?? ''));
        $kdBangsal = trim((string) ($payload['kd_bangsal'] ?? ''));
        if ($kdKamar === '' || $kdBangsal === '') {
            throw new \InvalidArgumentException('Kode kamar dan kode bangsal wajib diisi.');
        }

        $nmBangsal = trim((string) ($payload['nm_bangsal'] ?? $kdBangsal));
        $bExists = $this->pdo->prepare('SELECT 1 FROM bangsal WHERE kd_bangsal = :kb');
        $bExists->execute([':kb' => $kdBangsal]);
        if ($bExists->fetchColumn() === false) {
            $this->pdo->prepare('INSERT INTO bangsal (kd_bangsal, nm_bangsal, status) VALUES (:kb, :nb, \'1\')')
                ->execute([':kb' => $kdBangsal, ':nb' => $nmBangsal]);
        }

        $exists = $this->findKamar($kdKamar) !== null;
        $params = [
            ':kk' => $kdKamar,
            ':kb' => $kdBangsal,
            ':trf' => (float) ($payload['trf_kamar'] ?? 0),
            ':st' => trim((string) ($payload['status'] ?? 'KOSONG')) ?: 'KOSONG',
            ':kls' => trim((string) ($payload['kelas'] ?? 'Kelas 3')) ?: 'Kelas 3',
        ];
        $sql = $exists
            ? 'UPDATE kamar SET kd_bangsal = :kb, trf_kamar = :trf, status = :st, kelas = :kls WHERE kd_kamar = :kk'
            : 'INSERT INTO kamar (kd_kamar, kd_bangsal, trf_kamar, status, kelas, statusdata) VALUES (:kk, :kb, :trf, :st, :kls, \'1\')';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function listActiveInpatients(string $q = ''): array
    {
        $like = '%' . trim($q) . '%';
        $stmt = $this->pdo->prepare("
            SELECT ki.no_rawat, ki.kd_kamar, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar,
                   ki.lama, ki.trf_kamar, ki.ttl_biaya, ki.stts_pulang,
                   k.kelas, b.nm_bangsal, r.no_rkm_medis, p.nm_pasien, pj.png_jawab
            FROM kamar_inap ki
            LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
            LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            LEFT JOIN reg_periksa r ON ki.no_rawat = r.no_rawat
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE ki.no_rawat LIKE :q OR ki.kd_kamar LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q
            ORDER BY CASE WHEN ki.stts_pulang = '-' THEN 0 ELSE 1 END ASC, ki.tgl_masuk DESC, ki.jam_masuk DESC
            LIMIT 100
        ");
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findKamar(string $kdKamar): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM kamar WHERE kd_kamar = :kk LIMIT 1');
        $stmt->execute([':kk' => trim($kdKamar)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function insertKamarInap(
        string $noRawat,
        string $kdKamar,
        string $tglMasuk,
        string $jamMasuk,
        float $trfKamar
    ): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO kamar_inap (no_rawat, kd_kamar, tgl_masuk, jam_masuk, tgl_keluar, jam_keluar, lama, trf_kamar, ttl_biaya, stts_pulang)
            VALUES (:nr, :kk, :tm, :jm, '0000-00-00', '00:00:00', 1, :trf, :trf, '-')
        ");
        $stmt->execute([
            ':nr' => $noRawat,
            ':kk' => $kdKamar,
            ':tm' => $tglMasuk,
            ':jm' => $jamMasuk,
            ':trf' => $trfKamar,
        ]);
    }

    public function setKamarStatus(string $kdKamar, string $status): void
    {
        $this->pdo->prepare('UPDATE kamar SET status = :st WHERE kd_kamar = :kk')
            ->execute([':st' => $status, ':kk' => $kdKamar]);
    }

    public function markRegRanap(string $noRawat): void
    {
        $this->pdo->prepare("UPDATE reg_periksa SET status_lanjut = 'Ranap', stts = 'Dirawat' WHERE no_rawat = :nr")
            ->execute([':nr' => $noRawat]);
    }

    public function findActiveStay(string $noRawat, string $kdKamar): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM kamar_inap
            WHERE no_rawat = :nr AND kd_kamar = :kk AND stts_pulang = '-'
            ORDER BY tgl_masuk DESC, jam_masuk DESC LIMIT 1
        ");
        $stmt->execute([':nr' => $noRawat, ':kk' => $kdKamar]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function updateCheckoutStay(
        string $noRawat,
        string $kdKamar,
        string $tglMasuk,
        string $jamMasuk,
        string $tglKeluar,
        string $jamKeluar,
        int $lama,
        float $ttlBiaya,
        string $sttsPulang
    ): void {
        $this->pdo->prepare("
            UPDATE kamar_inap
            SET tgl_keluar = :tk, jam_keluar = :jk, lama = :lama, ttl_biaya = :ttl, stts_pulang = :sp
            WHERE no_rawat = :nr AND kd_kamar = :kk AND tgl_masuk = :tm AND jam_masuk = :jm
        ")->execute([
            ':tk' => $tglKeluar,
            ':jk' => $jamKeluar,
            ':lama' => $lama,
            ':ttl' => $ttlBiaya,
            ':sp' => $sttsPulang,
            ':nr' => $noRawat,
            ':kk' => $kdKamar,
            ':tm' => $tglMasuk,
            ':jm' => $jamMasuk,
        ]);
    }
}
