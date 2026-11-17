<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Ranap\Repository\DokterRanapRepositoryInterface;

final readonly class PdoDokterRanapRepository implements DokterRanapRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function listRanapPatients(string $q = ''): array
    {
        $like = '%' . trim($q) . '%';
        $stmt = $this->pdo->prepare("
            SELECT ki.no_rawat, ki.kd_kamar, ki.tgl_masuk, k.kelas, b.nm_bangsal,
                   r.no_rkm_medis, p.nm_pasien, pj.png_jawab
            FROM kamar_inap ki
            LEFT JOIN kamar k ON ki.kd_kamar = k.kd_kamar
            LEFT JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
            LEFT JOIN reg_periksa r ON ki.no_rawat = r.no_rawat
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE ki.stts_pulang = '-'
              AND (ki.no_rawat LIKE :q OR ki.kd_kamar LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q)
            ORDER BY ki.tgl_masuk DESC, ki.jam_masuk DESC
            LIMIT 100
        ");
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listCppt(string $noRawat): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pemeriksaan_ranap
            WHERE no_rawat = :nr
            ORDER BY tgl_perawatan DESC, jam_rawat DESC
        ");
        $stmt->execute([':nr' => trim($noRawat)]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveCppt(array $payload): void
    {
        $nr = trim((string) ($payload['no_rawat'] ?? ''));
        $keluhan = trim((string) ($payload['keluhan'] ?? ''));
        if ($nr === '' || $keluhan === '') {
            throw new \InvalidArgumentException('Nomor rawat dan keluhan (S) CPPT wajib diisi.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO pemeriksaan_ranap (
                no_rawat, tgl_perawatan, jam_rawat, suhu_tubuh, tensi, nadi, respirasi,
                tinggi, berat, spo2, gcs, kesadaran, keluhan, pemeriksaan, alergi,
                rtl, penilaian, instruksi, evaluasi, nip
            ) VALUES (
                :nr, :tgl, :jam, :suhu, :tensi, :nadi, :rr,
                :tinggi, :berat, :spo2, :gcs, :kesadaran, :keluhan, :pemeriksaan, :alergi,
                :rtl, :penilaian, :instruksi, :evaluasi, :nip
            )
        ");
        $stmt->execute([
            ':nr' => $nr,
            ':tgl' => trim((string) ($payload['tgl_perawatan'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            ':jam' => trim((string) ($payload['jam_rawat'] ?? date('H:i:s'))) ?: date('H:i:s'),
            ':suhu' => trim((string) ($payload['suhu_tubuh'] ?? '-')) ?: '-',
            ':tensi' => trim((string) ($payload['tensi'] ?? '-')) ?: '-',
            ':nadi' => trim((string) ($payload['nadi'] ?? '-')) ?: '-',
            ':rr' => trim((string) ($payload['respirasi'] ?? '-')) ?: '-',
            ':tinggi' => trim((string) ($payload['tinggi'] ?? '-')) ?: '-',
            ':berat' => trim((string) ($payload['berat'] ?? '-')) ?: '-',
            ':spo2' => trim((string) ($payload['spo2'] ?? '-')) ?: '-',
            ':gcs' => trim((string) ($payload['gcs'] ?? '15')) ?: '15',
            ':kesadaran' => trim((string) ($payload['kesadaran'] ?? 'Compos Mentis')) ?: 'Compos Mentis',
            ':keluhan' => $keluhan,
            ':pemeriksaan' => trim((string) ($payload['pemeriksaan'] ?? '-')) ?: '-',
            ':alergi' => trim((string) ($payload['alergi'] ?? '-')) ?: '-',
            ':rtl' => trim((string) ($payload['rtl'] ?? '-')) ?: '-',
            ':penilaian' => trim((string) ($payload['penilaian'] ?? '-')) ?: '-',
            ':instruksi' => trim((string) ($payload['instruksi'] ?? '-')) ?: '-',
            ':evaluasi' => trim((string) ($payload['evaluasi'] ?? '-')) ?: '-',
            ':nip' => trim((string) ($payload['nip'] ?? 'DR01')) ?: 'DR01',
        ]);
    }
}
