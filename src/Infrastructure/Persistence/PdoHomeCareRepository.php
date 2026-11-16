<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Communication\Repository\HomeCareRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use PDO;

final readonly class PdoHomeCareRepository implements HomeCareRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function hasActivePreceptorSk(string $nip, string $institusi): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM mlite_homecare_preceptor_sk
             WHERE nip = :nip AND institusi = :inst AND status = 'Aktif'"
        );
        $stmt->execute([
            ':nip' => $nip,
            ':inst' => $institusi,
        ]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function assignPreceptor(
        NoRawat $noRawat,
        string $nip,
        string $institusi,
        string $nim,
        string $supervisorId,
    ): array {
        $nr = $noRawat->value();
        $stmt = $this->pdo->prepare(
            'UPDATE mlite_homecare_kunjungan
             SET preceptor_nip = :nip, mahasiswa_nim = :nim, supervisor_id = :sup
             WHERE no_rawat = :nr'
        );
        $stmt->execute([
            ':nip' => $nip,
            ':nim' => $nim,
            ':sup' => $supervisorId,
            ':nr' => $nr,
        ]);

        return [
            'no_rawat' => $nr,
            'preceptor_nip' => $nip,
            'institusi' => $institusi,
            'mahasiswa_nim' => $nim,
            'supervisor_id' => $supervisorId,
        ];
    }

    public function listKunjungan(string $fromDate, string $toDate): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT hk.*, p.nm_pasien, p.tgl_lahir, p.jk, d.nm_dokter AS nm_dpjp
             FROM mlite_homecare_kunjungan hk
             LEFT JOIN reg_periksa rp ON hk.no_rawat = rp.no_rawat
             LEFT JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
             LEFT JOIN dokter d ON hk.kd_dokter_dpjp = d.kd_dokter
             WHERE hk.tgl_kunjungan BETWEEN :from AND :to
             ORDER BY hk.tgl_kunjungan DESC, hk.jam_kunjungan DESC'
        );
        $stmt->execute([':from' => $fromDate, ':to' => $toDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createKunjungan(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_homecare_kunjungan
             (no_rawat, tgl_kunjungan, jam_kunjungan, kd_dokter_dpjp, preceptor_nip, mahasiswa_nim, supervisor_id, diagnosis, status_layanan, status_bayar, payment_gateway, created_at)
             VALUES (:nr, :tgl, :jam, :dpjp, :prec, :nim, :sup, :diag, :stts, :bayar, :gw, :created)'
        );
        $stmt->execute([
            ':nr' => (string) ($data['no_rawat'] ?? ''),
            ':tgl' => (string) ($data['tgl_kunjungan'] ?? date('Y-m-d')),
            ':jam' => (string) ($data['jam_kunjungan'] ?? date('H:i:s')),
            ':dpjp' => (string) ($data['kd_dokter_dpjp'] ?? ''),
            ':prec' => (string) ($data['preceptor_nip'] ?? ''),
            ':nim' => (string) ($data['mahasiswa_nim'] ?? ''),
            ':sup' => (string) ($data['supervisor_id'] ?? ''),
            ':diag' => (string) ($data['diagnosis'] ?? ''),
            ':stts' => (string) ($data['status_layanan'] ?? 'Terjadwal'),
            ':bayar' => (string) ($data['status_bayar'] ?? 'Belum'),
            ':gw' => (string) ($data['payment_gateway'] ?? 'Kasera'),
            ':created' => $now,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        return array_merge($data, ['id' => $id, 'created_at' => $now]);
    }
}
