<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Repository\JasaMedisRepositoryInterface;

final readonly class PdoJasaMedisRepository implements JasaMedisRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function summarizeByDoctor(string $from, string $to, string $kdDokter = ''): array
    {
        $sql = "
            SELECT t.kd_dokter, COALESCE(d.nm_dokter, t.kd_dokter) AS nm_dokter,
                   COUNT(*) AS jumlah_tindakan,
                   SUM(t.tarif_tindakandr) AS total_jasa_dokter,
                   SUM(t.biaya_rawat) AS total_biaya,
                   SUM(CASE WHEN t.jenis_klaim = 'Tunai' THEN t.tarif_tindakandr ELSE 0 END) AS jasa_tunai,
                   SUM(CASE WHEN t.jenis_klaim IN ('Kapitasi', 'Non-Kapitasi') THEN t.tarif_tindakandr ELSE 0 END) AS jasa_bpjs,
                   SUM(CASE WHEN t.jenis_klaim = 'Piutang' THEN t.tarif_tindakandr ELSE 0 END) AS jasa_piutang
            FROM rawat_jl_dr t
            LEFT JOIN dokter d ON t.kd_dokter = d.kd_dokter
            LEFT JOIN reg_periksa r ON t.no_rawat = r.no_rawat
            WHERE t.tgl_perawatan BETWEEN :from AND :to
              AND COALESCE(r.stts, '') != 'Batal'
        ";
        $params = [':from' => $from, ':to' => $to];
        if (trim($kdDokter) !== '') {
            $sql .= ' AND t.kd_dokter = :kd';
            $params[':kd'] = trim($kdDokter);
        }
        $sql .= ' GROUP BY t.kd_dokter ORDER BY total_jasa_dokter DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function listDetails(string $from, string $to, string $kdDokter = ''): array
    {
        $sql = "
            SELECT t.no_rawat, t.kd_jenis_prw, COALESCE(j.nm_perawatan, t.kd_jenis_prw) AS nm_perawatan,
                   t.kd_dokter, COALESCE(d.nm_dokter, t.kd_dokter) AS nm_dokter,
                   t.tgl_perawatan, t.jam_rawat, t.tarif_tindakandr, t.biaya_rawat, t.jenis_klaim,
                   p.nm_pasien
            FROM rawat_jl_dr t
            LEFT JOIN jns_perawatan j ON t.kd_jenis_prw = j.kd_jenis_prw
            LEFT JOIN dokter d ON t.kd_dokter = d.kd_dokter
            LEFT JOIN reg_periksa r ON t.no_rawat = r.no_rawat
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            WHERE t.tgl_perawatan BETWEEN :from AND :to
              AND COALESCE(r.stts, '') != 'Batal'
        ";
        $params = [':from' => $from, ':to' => $to];
        if (trim($kdDokter) !== '') {
            $sql .= ' AND t.kd_dokter = :kd';
            $params[':kd'] = trim($kdDokter);
        }
        $sql .= ' ORDER BY t.tgl_perawatan DESC, t.jam_rawat DESC LIMIT 200';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
