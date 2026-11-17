<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Repository\DokterTindakanRepositoryInterface;
use PDO;

final readonly class PdoDokterTindakanRepository implements DokterTindakanRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function searchTarifTindakan(string $query = '', int $limit = 20): array
    {
        $trimmed = trim($query);
        if ($trimmed === '') {
            $stmt = $this->pdo->prepare("SELECT * FROM jns_perawatan WHERE status = '1' ORDER BY nm_perawatan ASC LIMIT :lim");
            $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $like = '%' . $trimmed . '%';
        $stmt = $this->pdo->prepare(
            "SELECT * FROM jns_perawatan WHERE status = '1' AND (kd_jenis_prw LIKE :q1 OR nm_perawatan LIKE :q2) ORDER BY nm_perawatan ASC LIMIT :lim"
        );
        $stmt->bindValue(':q1', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findTarifByKode(string $kdJenisPrw): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM jns_perawatan WHERE kd_jenis_prw = :kd LIMIT 1');
        $stmt->execute([':kd' => $kdJenisPrw]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function saveTindakan(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $tglPerawatan,
        string $jamRawat,
        float $material,
        float $tarifDokter,
        float $biayaRawat,
        string $jenisKlaim,
    ): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rawat_jl_dr
             (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat, material, tarif_tindakandr, biaya_rawat, stts_bayar, jenis_klaim)
             VALUES (:nr, :kd, :dr, :tgl, :jam, :mat, :tdr, :biaya, 'Belum', :klaim)"
        );
        $stmt->execute([
            ':nr' => $noRawat->value(),
            ':kd' => $kdJenisPrw,
            ':dr' => $kdDokter,
            ':tgl' => $tglPerawatan,
            ':jam' => $jamRawat,
            ':mat' => $material,
            ':tdr' => $tarifDokter,
            ':biaya' => $biayaRawat,
            ':klaim' => $jenisKlaim,
        ]);
    }

    public function listByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, COALESCE(j.nm_perawatan, r.kd_jenis_prw) AS nm_perawatan, COALESCE(d.nm_dokter, r.kd_dokter) AS nm_dokter
             FROM rawat_jl_dr r
             LEFT JOIN jns_perawatan j ON r.kd_jenis_prw = j.kd_jenis_prw
             LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
             WHERE r.no_rawat = :nr
             ORDER BY r.tgl_perawatan DESC, r.jam_rawat DESC'
        );
        $stmt->execute([':nr' => $noRawat->value()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findTindakanRow(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $tglPerawatan,
        string $jamRawat,
    ): ?array {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rawat_jl_dr
             WHERE no_rawat = :nr AND kd_jenis_prw = :kd AND kd_dokter = :dr AND tgl_perawatan = :tgl AND jam_rawat = :jam
             LIMIT 1'
        );
        $stmt->execute([
            ':nr' => $noRawat->value(),
            ':kd' => $kdJenisPrw,
            ':dr' => $kdDokter,
            ':tgl' => $tglPerawatan,
            ':jam' => $jamRawat,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function deleteTindakan(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $tglPerawatan,
        string $jamRawat,
    ): void {
        $stmt = $this->pdo->prepare(
            'DELETE FROM rawat_jl_dr
             WHERE no_rawat = :nr AND kd_jenis_prw = :kd AND kd_dokter = :dr AND tgl_perawatan = :tgl AND jam_rawat = :jam'
        );
        $stmt->execute([
            ':nr' => $noRawat->value(),
            ':kd' => $kdJenisPrw,
            ':dr' => $kdDokter,
            ':tgl' => $tglPerawatan,
            ':jam' => $jamRawat,
        ]);
    }

    public function saveResume(array $data): void
    {
        $nr = (string) ($data['no_rawat'] ?? '');
        $del = $this->pdo->prepare('DELETE FROM resume_pasien WHERE no_rawat = :nr');
        $del->execute([':nr' => $nr]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO resume_pasien
             (no_rawat, kd_dokter, keluhan_utama, jalannya_penyakit, pemeriksaan_penunjang, hasil_laborat, diagnosa_utama, kd_diagnosa_utama, kondisi_pulang, obat_pulang)
             VALUES (:nr, :dr, :keluhan, :jalan, :penunjang, :lab, :diag, :kddiag, :kondisi, :obat)'
        );
        $stmt->execute([
            ':nr' => $nr,
            ':dr' => (string) ($data['kd_dokter'] ?? ''),
            ':keluhan' => (string) ($data['keluhan_utama'] ?? ''),
            ':jalan' => (string) ($data['jalannya_penyakit'] ?? ''),
            ':penunjang' => (string) ($data['pemeriksaan_penunjang'] ?? ''),
            ':lab' => (string) ($data['hasil_laborat'] ?? ''),
            ':diag' => (string) ($data['diagnosa_utama'] ?? '-'),
            ':kddiag' => (string) ($data['kd_diagnosa_utama'] ?? '-'),
            ':kondisi' => (string) ($data['kondisi_pulang'] ?? 'Membaik'),
            ':obat' => (string) ($data['obat_pulang'] ?? ''),
        ]);
    }

    public function findResume(NoRawat $noRawat): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM resume_pasien WHERE no_rawat = :nr LIMIT 1');
        $stmt->execute([':nr' => $noRawat->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
