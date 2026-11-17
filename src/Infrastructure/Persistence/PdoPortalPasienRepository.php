<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\Repository\PortalPasienRepositoryInterface;

final readonly class PdoPortalPasienRepository implements PortalPasienRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function verifyAndLoadPortal(string $noRkmMedis, string $tglLahir): ?array
    {
        $stmt = $this->pdo->prepare('SELECT no_rkm_medis, nm_pasien, tgl_lahir, jk FROM pasien WHERE no_rkm_medis = :rm AND tgl_lahir = :tl LIMIT 1');
        $stmt->execute([':rm' => trim($noRkmMedis), ':tl' => trim($tglLahir)]);
        $patient = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($patient === false) {
            return null;
        }

        $visits = $this->pdo->prepare("
            SELECT r.no_rawat, r.tgl_registrasi, r.no_reg, r.stts, r.status_bayar, pol.nm_poli, d.nm_dokter
            FROM reg_periksa r
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            WHERE r.no_rkm_medis = :rm
            ORDER BY r.tgl_registrasi DESC, r.jam_reg DESC LIMIT 20
        ");
        $visits->execute([':rm' => trim($noRkmMedis)]);

        $labs = $this->pdo->prepare("
            SELECT l.no_rawat, l.kd_jenis_prw, l.pemeriksaan, l.tgl_periksa, l.nilai, l.nilai_rujukan, l.keterangan
            FROM detail_periksa_lab l
            INNER JOIN reg_periksa r ON l.no_rawat = r.no_rawat
            WHERE r.no_rkm_medis = :rm
            ORDER BY l.tgl_periksa DESC LIMIT 30
        ");
        $labs->execute([':rm' => trim($noRkmMedis)]);

        $bookings = $this->pdo->prepare("
            SELECT b.tanggal_periksa, b.no_reg, b.status, pol.nm_poli, d.nm_dokter
            FROM booking_registrasi b
            LEFT JOIN poliklinik pol ON b.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON b.kd_dokter = d.kd_dokter
            WHERE b.no_rkm_medis = :rm
            ORDER BY b.tanggal_periksa DESC LIMIT 10
        ");
        $bookings->execute([':rm' => trim($noRkmMedis)]);

        $polis = $this->pdo->query("SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli ASC");
        $dokters = $this->pdo->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter ASC");

        return [
            'patient' => $patient,
            'visits' => $visits->fetchAll(\PDO::FETCH_ASSOC),
            'lab_results' => $labs->fetchAll(\PDO::FETCH_ASSOC),
            'bookings' => $bookings->fetchAll(\PDO::FETCH_ASSOC),
            'polis' => $polis ? $polis->fetchAll(\PDO::FETCH_ASSOC) : [],
            'dokters' => $dokters ? $dokters->fetchAll(\PDO::FETCH_ASSOC) : [],
        ];
    }

    public function createOnlineBooking(
        string $noRkmMedis,
        string $tglLahir,
        string $tanggalPeriksa,
        string $kdPoli,
        string $kdDokter,
        string $kdPj = 'A09'
    ): array {
        $stmt = $this->pdo->prepare('SELECT 1 FROM pasien WHERE no_rkm_medis = :rm AND tgl_lahir = :tl LIMIT 1');
        $stmt->execute([':rm' => trim($noRkmMedis), ':tl' => trim($tglLahir)]);
        if ($stmt->fetchColumn() === false) {
            throw new \DomainException('Verifikasi No. RM dan Tanggal Lahir pasien tidak valid.');
        }
        if (trim($tanggalPeriksa) === '' || trim($kdPoli) === '' || trim($kdDokter) === '') {
            throw new \InvalidArgumentException('Tanggal periksa, poliklinik, dan dokter wajib dipilih.');
        }

        $dupStmt = $this->pdo->prepare("SELECT 1 FROM booking_registrasi WHERE no_rkm_medis = :rm AND tanggal_periksa = :tp AND status = 'Belum' LIMIT 1");
        $dupStmt->execute([':rm' => trim($noRkmMedis), ':tp' => trim($tanggalPeriksa)]);
        if ($dupStmt->fetchColumn() !== false) {
            throw new \DomainException('Anda sudah memiliki booking aktif pada tanggal periksa tersebut.');
        }

        $maxStmt = $this->pdo->prepare('SELECT MAX(CAST(no_reg AS INTEGER)) FROM booking_registrasi WHERE tanggal_periksa = :tp AND kd_poli = :kp');
        $maxStmt->execute([':tp' => trim($tanggalPeriksa), ':kp' => trim($kdPoli)]);
        $nextReg = str_pad((string) (((int) $maxStmt->fetchColumn()) + 1), 3, '0', STR_PAD_LEFT);

        $this->pdo->prepare("
            INSERT INTO booking_registrasi (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status)
            VALUES (:tb, :jb, :rm, :tp, :kd, :kp, :reg, :pj, 'Belum')
        ")->execute([
            ':tb' => date('Y-m-d'),
            ':jb' => date('H:i:s'),
            ':rm' => trim($noRkmMedis),
            ':tp' => trim($tanggalPeriksa),
            ':kd' => trim($kdDokter),
            ':kp' => trim($kdPoli),
            ':reg' => $nextReg,
            ':pj' => trim($kdPj) ?: 'A09',
        ]);

        return ['no_reg' => $nextReg, 'tanggal_periksa' => trim($tanggalPeriksa)];
    }
}
