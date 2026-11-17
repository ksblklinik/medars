<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Igd\Repository\IgdRepositoryInterface;

final readonly class PdoIgdRepository implements IgdRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function listIgdVisits(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.no_rkm_medis, r.stts, r.status_lanjut, r.status_bayar,
                   p.nm_pasien, p.jk, d.nm_dokter, pj.png_jawab,
                   t.zona_triase, t.cara_masuk, t.keluhan_utama, t.tensi, t.nadi, t.suhu, t.respirasi, t.spo2, t.gcs, t.disposisi
            FROM reg_periksa r
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            LEFT JOIN data_triase_igd t ON r.no_rawat = t.no_rawat
            WHERE (r.kd_poli = 'IGDK' OR t.no_rawat IS NOT NULL)
              AND r.tgl_registrasi BETWEEN :from AND :to
            ORDER BY
                CASE COALESCE(t.zona_triase, 'Hijau')
                    WHEN 'Merah' THEN 1
                    WHEN 'Kuning' THEN 2
                    WHEN 'Hijau' THEN 3
                    WHEN 'Hitam' THEN 4
                    ELSE 5
                END ASC,
                r.tgl_registrasi DESC, r.jam_reg ASC
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findTriase(string $noRawat): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM data_triase_igd WHERE no_rawat = :nr LIMIT 1');
        $stmt->execute([':nr' => trim($noRawat)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function saveTriase(array $payload): void
    {
        $nr = trim((string) ($payload['no_rawat'] ?? ''));
        $keluhan = trim((string) ($payload['keluhan_utama'] ?? ''));
        if ($nr === '' || $keluhan === '') {
            throw new \InvalidArgumentException('Nomor rawat dan keluhan utama triase wajib diisi.');
        }

        $zona = trim((string) ($payload['zona_triase'] ?? 'Hijau'));
        if (!in_array($zona, ['Merah', 'Kuning', 'Hijau', 'Hitam'], true)) {
            throw new \InvalidArgumentException("Zona triase tidak valid: '{$zona}'.");
        }

        $exists = $this->findTriase($nr) !== null;
        $params = [
            ':nr' => $nr,
            ':tgl' => trim((string) ($payload['tgl_kunjungan'] ?? date('Y-m-d'))),
            ':jam' => trim((string) ($payload['jam_kunjungan'] ?? date('H:i:s'))),
            ':cm' => trim((string) ($payload['cara_masuk'] ?? 'Jalan')),
            ':zona' => $zona,
            ':kel' => $keluhan,
            ':td' => trim((string) ($payload['tensi'] ?? '')),
            ':nd' => trim((string) ($payload['nadi'] ?? '')),
            ':suhu' => trim((string) ($payload['suhu'] ?? '')),
            ':rr' => trim((string) ($payload['respirasi'] ?? '')),
            ':spo2' => trim((string) ($payload['spo2'] ?? '')),
            ':gcs' => trim((string) ($payload['gcs'] ?? '15')),
            ':disp' => trim((string) ($payload['disposisi'] ?? 'Observasi')),
            ':cat' => trim((string) ($payload['catatan'] ?? '')),
        ];

        $sql = $exists
            ? 'UPDATE data_triase_igd SET tgl_kunjungan = :tgl, jam_kunjungan = :jam, cara_masuk = :cm, zona_triase = :zona, keluhan_utama = :kel, tensi = :td, nadi = :nd, suhu = :suhu, respirasi = :rr, spo2 = :spo2, gcs = :gcs, disposisi = :disp, catatan = :cat WHERE no_rawat = :nr'
            : 'INSERT INTO data_triase_igd (no_rawat, tgl_kunjungan, jam_kunjungan, cara_masuk, zona_triase, keluhan_utama, tensi, nadi, suhu, respirasi, spo2, gcs, disposisi, catatan) VALUES (:nr, :tgl, :jam, :cm, :zona, :kel, :td, :nd, :suhu, :rr, :spo2, :gcs, :disp, :cat)';

        $this->pdo->prepare($sql)->execute($params);
    }

    public function updateDisposisi(string $noRawat, string $disposisi, string $statusLanjut = 'Ralan'): void
    {
        $nr = trim($noRawat);
        if ($nr === '' || trim($disposisi) === '') {
            throw new \InvalidArgumentException('Nomor rawat dan disposisi IGD wajib diisi.');
        }
        $sl = $statusLanjut === 'Ranap' ? 'Ranap' : 'Ralan';
        $stts = match ($disposisi) {
            'Pulang' => 'Sudah',
            'Meninggal' => 'Meninggal',
            'Rujuk' => 'Dirujuk',
            'Rawat Inap' => 'Dirawat',
            default => 'Belum',
        };

        $this->pdo->prepare('UPDATE reg_periksa SET status_lanjut = :sl, stts = :stts WHERE no_rawat = :nr')
            ->execute([':sl' => $sl, ':stts' => $stts, ':nr' => $nr]);

        $this->pdo->prepare('UPDATE data_triase_igd SET disposisi = :d WHERE no_rawat = :nr')
            ->execute([':d' => trim($disposisi), ':nr' => $nr]);
    }
}
