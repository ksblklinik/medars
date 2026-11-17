<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\Registration\DTO\CheckInMobileJknCommand;
use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Domain\Bridging\Repository\JknMobileRepositoryInterface;
use PDO;

final class PdoJknMobileRepository implements JknMobileRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ?CheckInMobileJknUseCase $checkInUseCase = null,
    ) {
    }

    public function verifyCredentials(string $username, string $password): bool
    {
        $u = trim($username);
        if ($u === '' || $password === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT password FROM mlite_users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $u]);
        $hash = $stmt->fetchColumn();
        if ($hash === false) {
            return false;
        }
        return password_verify($password, (string) $hash);
    }

    public function pasienBaru(array $payload): array
    {
        $nik = trim((string) ($payload['nik'] ?? ''));
        $noKartu = trim((string) ($payload['nomorkartu'] ?? ''));
        if ($nik !== '') {
            $chk = $this->pdo->prepare('SELECT no_rkm_medis FROM pasien WHERE no_ktp = :nik LIMIT 1');
            $chk->execute([':nik' => $nik]);
            $existing = $chk->fetchColumn();
            if ($existing !== false) {
                return ['norm' => (string) $existing];
            }
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM pasien')->fetchColumn();
        $norm = str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
        $stmt = $this->pdo->prepare(
            'INSERT INTO pasien (no_rkm_medis, nm_pasien, no_ktp, jk, tgl_lahir, alamat, no_tlp, no_peserta, tgl_daftar)
             VALUES (:rm, :nm, :nik, :jk, :tl, :al, :hp, :nk, :td)'
        );
        $stmt->execute([
            ':rm' => $norm,
            ':nm' => trim((string) ($payload['nama'] ?? 'Pasien JKN')),
            ':nik' => $nik,
            ':jk' => strtoupper(substr(trim((string) ($payload['jeniskelamin'] ?? 'L')), 0, 1)) ?: 'L',
            ':tl' => trim((string) ($payload['tanggallahir'] ?? '1990-01-01')) ?: '1990-01-01',
            ':al' => trim((string) ($payload['alamat'] ?? '-')) ?: '-',
            ':hp' => trim((string) ($payload['nohp'] ?? '')),
            ':nk' => $noKartu,
            ':td' => date('Y-m-d'),
        ]);

        return ['norm' => $norm];
    }

    public function ambilAntrean(array $payload): array
    {
        $tgl = trim((string) ($payload['tanggalperiksa'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $kodepoli = trim((string) ($payload['kodepoli'] ?? 'UMUM')) ?: 'UMUM';
        $kodedokter = trim((string) ($payload['kodedokter'] ?? ''));

        $cntStmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM referensi_mobilejkn_bpjs WHERE tanggalperiksa = :tgl AND kodepoli = :kp'
        );
        $cntStmt->execute([':tgl' => $tgl, ':kp' => $kodepoli]);
        $angka = (int) $cntStmt->fetchColumn() + 1;
        $nomor = strtoupper(substr($kodepoli, 0, 1)) . $angka;
        $kodebooking = str_replace('-', '', $tgl) . strtoupper($kodepoli) . str_pad((string) $angka, 3, '0', STR_PAD_LEFT);

        $kuotaJkn = 20;
        $sisaJkn = max(0, $kuotaJkn - $angka);
        $estimasi = (int) (strtotime($tgl . ' 08:00:00') * 1000) + (($angka - 1) * 600000);

        $stmt = $this->pdo->prepare(
            'INSERT INTO referensi_mobilejkn_bpjs (nobooking, nomorkartu, nik, nohp, kodepoli, pasienbaru, norm, tanggalperiksa, kodedokter, jampraktek, jeniskunjungan, nomorreferensi, nomorantrean, angkaantrean, estimasidilayani, sisakuotajkn, kuotajkn, sisakuotanonjkn, kuotanonjkn, status, created_at)
             VALUES (:kb, :nk, :nik, :hp, :kp, :pb, :rm, :tgl, :kd, :jp, :jk, :nr, :na, :aa, :ed, :sj, :kj, 10, 10, :st, :ca)'
        );
        $stmt->execute([
            ':kb' => $kodebooking,
            ':nk' => trim((string) ($payload['nomorkartu'] ?? '')),
            ':nik' => trim((string) ($payload['nik'] ?? '')),
            ':hp' => trim((string) ($payload['nohp'] ?? '')),
            ':kp' => $kodepoli,
            ':pb' => (int) ($payload['pasienbaru'] ?? 0),
            ':rm' => trim((string) ($payload['norm'] ?? '')),
            ':tgl' => $tgl,
            ':kd' => $kodedokter,
            ':jp' => trim((string) ($payload['jampraktek'] ?? '08:00-12:00')),
            ':jk' => (int) ($payload['jeniskunjungan'] ?? 1),
            ':nr' => trim((string) ($payload['nomorreferensi'] ?? '')),
            ':na' => $nomor,
            ':aa' => $angka,
            ':ed' => $estimasi,
            ':sj' => $sisaJkn,
            ':kj' => $kuotaJkn,
            ':st' => 'Belum',
            ':ca' => date('Y-m-d H:i:s'),
        ]);

        $poliStmt = $this->pdo->prepare('SELECT nm_poli FROM poliklinik WHERE kd_poli = :kp LIMIT 1');
        $poliStmt->execute([':kp' => $kodepoli]);
        $nmPoli = (string) ($poliStmt->fetchColumn() ?: $kodepoli);

        $dokStmt = $this->pdo->prepare('SELECT nm_dokter FROM dokter WHERE kd_dokter = :kd LIMIT 1');
        $dokStmt->execute([':kd' => $kodedokter]);
        $nmDokter = (string) ($dokStmt->fetchColumn() ?: $kodedokter);

        // Sinkron ke booking_registrasi jika pasien/poli/dokter lokal sudah termapping & bridging aktif
        $norm = trim((string) ($payload['norm'] ?? ''));
        $simrsSynced = false;
        if ($norm !== '' && $this->isSimrsSyncEnabled()) {
            $hasPasien = $this->existsRow('SELECT 1 FROM pasien WHERE no_rkm_medis = :v LIMIT 1', $norm);
            $hasPoli = $this->existsRow('SELECT 1 FROM poliklinik WHERE kd_poli = :v LIMIT 1', $kodepoli);
            $hasDokter = $kodedokter !== '' && $this->existsRow('SELECT 1 FROM dokter WHERE kd_dokter = :v LIMIT 1', $kodedokter);
            if ($hasPasien && $hasPoli && $hasDokter) {
                $chkBk = $this->pdo->prepare('SELECT COUNT(*) FROM booking_registrasi WHERE no_rkm_medis = :rm AND tanggal_periksa = :tgl');
                $chkBk->execute([':rm' => $norm, ':tgl' => $tgl]);
                if ((int) $chkBk->fetchColumn() === 0) {
                    $this->pdo->prepare(
                        "INSERT INTO booking_registrasi (tanggal_booking, jam_booking, no_rkm_medis, tanggal_periksa, kd_dokter, kd_poli, no_reg, kd_pj, status)
                         VALUES (:tb, :jb, :rm, :tp, :kd, :kp, :nr, 'BPJ', 'Belum')"
                    )->execute([
                        ':tb' => date('Y-m-d'),
                        ':jb' => date('H:i:s'),
                        ':rm' => $norm,
                        ':tp' => $tgl,
                        ':kd' => $kodedokter,
                        ':kp' => $kodepoli,
                        ':nr' => str_pad((string) $angka, 3, '0', STR_PAD_LEFT),
                    ]);
                    $simrsSynced = true;
                }
            }
        }

        return [
            'nomorantrean' => $nomor,
            'angkaantrean' => $angka,
            'kodebooking' => $kodebooking,
            'norm' => $norm,
            'namapoli' => $nmPoli,
            'namadokter' => $nmDokter,
            'estimasidilayani' => $estimasi,
            'sisakuotajkn' => $sisaJkn,
            'kuotajkn' => $kuotaJkn,
            'sisakuotanonjkn' => 10,
            'kuotanonjkn' => 10,
            'simrs_synced' => $simrsSynced,
            'keterangan' => 'Peserta harap 60 menit lebih awal guna pencatatan administrasi.',
        ];
    }

    public function statusAntrean(string $kodepoli, string $kodedokter, string $tanggalperiksa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'Belum' THEN 1 ELSE 0 END) AS sisa
             FROM referensi_mobilejkn_bpjs
             WHERE kodepoli = :kp AND tanggalperiksa = :tgl AND status != 'Batal'"
        );
        $stmt->execute([':kp' => trim($kodepoli), ':tgl' => trim($tanggalperiksa)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'sisa' => 0];
        $total = (int) ($row['total'] ?? 0);
        $sisa = (int) ($row['sisa'] ?? 0);

        return [
            'namapoli' => $kodepoli,
            'namadokter' => $kodedokter,
            'totalantrean' => $total,
            'sisaantrean' => $sisa,
            'antreanpanggil' => $total > 0 ? strtoupper(substr($kodepoli, 0, 1)) . '1' : '-',
            'sisakuotajkn' => max(0, 20 - $total),
            'kuotajkn' => 20,
            'sisakuotanonjkn' => 10,
            'kuotanonjkn' => 10,
            'keterangan' => '',
        ];
    }

    public function sisaAntrean(string $kodebooking): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM referensi_mobilejkn_bpjs WHERE nobooking = :kb LIMIT 1');
        $stmt->execute([':kb' => trim($kodebooking)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $sisaStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM referensi_mobilejkn_bpjs WHERE tanggalperiksa = :tgl AND kodepoli = :kp AND status = 'Belum' AND angkaantrean < :aa"
        );
        $sisaStmt->execute([':tgl' => $row['tanggalperiksa'], ':kp' => $row['kodepoli'], ':aa' => (int) $row['angkaantrean']]);
        $sisa = (int) $sisaStmt->fetchColumn();

        return [
            'nomorantrean' => $row['nomorantrean'],
            'namapoli' => $row['kodepoli'],
            'namadokter' => $row['kodedokter'],
            'sisaantrean' => $sisa,
            'antreanpanggil' => '-',
            'waktutunggu' => $sisa * 600,
            'keterangan' => '',
        ];
    }

    public function batalAntrean(string $kodebooking, string $keterangan): bool
    {
        $rowStmt = $this->pdo->prepare('SELECT norm, tanggalperiksa FROM referensi_mobilejkn_bpjs WHERE nobooking = :kb LIMIT 1');
        $rowStmt->execute([':kb' => trim($kodebooking)]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            "UPDATE referensi_mobilejkn_bpjs SET status = 'Batal', keterangan_batal = :ket WHERE nobooking = :kb"
        );
        $stmt->execute([':ket' => trim($keterangan), ':kb' => trim($kodebooking)]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        if ($row !== false && !empty($row['norm'])) {
            $this->pdo->prepare(
                "UPDATE booking_registrasi SET status = 'Batal' WHERE no_rkm_medis = :rm AND tanggal_periksa = :tgl AND status = 'Belum'"
            )->execute([':rm' => (string) $row['norm'], ':tgl' => (string) $row['tanggalperiksa']]);
        }
        return true;
    }

    public function checkIn(string $kodebooking, int $waktuMs): ?array
    {
        $rowStmt = $this->pdo->prepare('SELECT norm, tanggalperiksa FROM referensi_mobilejkn_bpjs WHERE nobooking = :kb LIMIT 1');
        $rowStmt->execute([':kb' => trim($kodebooking)]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE referensi_mobilejkn_bpjs SET status = 'Checkin', validasi = :val WHERE nobooking = :kb"
        );
        $stmt->execute([':val' => (string) $waktuMs, ':kb' => trim($kodebooking)]);

        $noRawat = '';
        $norm = trim((string) ($row['norm'] ?? ''));
        $tglStr = trim((string) ($row['tanggalperiksa'] ?? ''));
        if ($this->checkInUseCase !== null && $norm !== '' && $tglStr !== '' && $this->isSimrsSyncEnabled()) {
            try {
                $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $tglStr) ?: null;
                $reg = $this->checkInUseCase->execute(new CheckInMobileJknCommand($norm, $dt));
                $noRawat = $reg->noRawat->value();
                $this->pdo->prepare('UPDATE referensi_mobilejkn_bpjs SET no_rawat = :nr WHERE nobooking = :kb')
                    ->execute([':nr' => $noRawat, ':kb' => trim($kodebooking)]);
            } catch (\Throwable) {
                // Tetap izinkan check-in Mobile JKN jika belum ada mapping booking_registrasi lokal
            }
        }

        return ['kodebooking' => trim($kodebooking), 'status' => 'Checkin', 'waktu' => $waktuMs, 'no_rawat' => $noRawat];
    }

    public function ambilAntreanFarmasi(string $kodebooking, string $jenisResep): ?array
    {
        $chk = $this->pdo->prepare('SELECT tanggalperiksa FROM referensi_mobilejkn_bpjs WHERE nobooking = :kb LIMIT 1');
        $chk->execute([':kb' => trim($kodebooking)]);
        $tgl = $chk->fetchColumn();
        if ($tgl === false) {
            return null;
        }
        $cnt = $this->pdo->prepare('SELECT COUNT(*) FROM referensi_mobilejkn_bpjs WHERE tanggalperiksa = :tgl AND antrean_farmasi > 0');
        $cnt->execute([':tgl' => $tgl]);
        $noFarmasi = (int) $cnt->fetchColumn() + 1;

        $upd = $this->pdo->prepare(
            'UPDATE referensi_mobilejkn_bpjs SET antrean_farmasi = :af, jenisresep = :jr WHERE nobooking = :kb'
        );
        $upd->execute([':af' => $noFarmasi, ':jr' => trim($jenisResep) ?: 'Non Racikan', ':kb' => trim($kodebooking)]);

        return [
            'jenisresep' => trim($jenisResep) ?: 'Non Racikan',
            'nomorantrean' => $noFarmasi,
            'keterangan' => '',
        ];
    }

    public function statusAntreanFarmasi(string $kodebooking): ?array
    {
        $stmt = $this->pdo->prepare('SELECT antrean_farmasi, jenisresep FROM referensi_mobilejkn_bpjs WHERE nobooking = :kb LIMIT 1');
        $stmt->execute([':kb' => trim($kodebooking)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'jenisresep' => $row['jenisresep'] ?: 'Non Racikan',
            'totalantrean' => (int) $row['antrean_farmasi'],
            'sisaantrean' => (int) $row['antrean_farmasi'] > 0 ? 1 : 0,
            'antreanpanggil' => (int) $row['antrean_farmasi'],
            'keterangan' => '',
        ];
    }

    public function jadwalOperasiRs(string $tanggalAwal, string $tanggalAkhir): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bo.no_rawat AS kodebooking, bo.tanggal AS tanggaloperasi, bo.kode_paket AS jenistindakan, COALESCE(rp.kd_poli, \'\') AS kodepoli, bo.status AS terlaksana
             FROM booking_operasi bo
             LEFT JOIN reg_periksa rp ON rp.no_rawat = bo.no_rawat
             WHERE bo.tanggal BETWEEN :ta AND :tk
             ORDER BY bo.tanggal ASC LIMIT 100'
        );
        $stmt->execute([':ta' => trim($tanggalAwal), ':tk' => trim($tanggalAkhir)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function jadwalOperasiPasien(string $noKartu): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT bo.no_rawat AS kodebooking, bo.tanggal AS tanggaloperasi, bo.kode_paket AS jenistindakan, bo.status AS terlaksana
             FROM booking_operasi bo
             INNER JOIN reg_periksa rp ON rp.no_rawat = bo.no_rawat
             INNER JOIN pasien p ON p.no_rkm_medis = rp.no_rkm_medis
             WHERE p.no_peserta = :nk
             ORDER BY bo.tanggal DESC LIMIT 50'
        );
        $stmt->execute([':nk' => trim($noKartu)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sisaAntreanFktp(string $noKartu, string $kodepoli, string $tanggalperiksa): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM referensi_mobilejkn_bpjs
             WHERE nomorkartu = :nk AND kodepoli = :kp AND tanggalperiksa = :tgl AND status != 'Batal'
             ORDER BY angkaantrean DESC LIMIT 1"
        );
        $stmt->execute([':nk' => trim($noKartu), ':kp' => trim($kodepoli), ':tgl' => trim($tanggalperiksa)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return $this->sisaAntrean((string) $row['nobooking']);
    }

    public function batalAntreanFktp(string $noKartu, string $kodepoli, string $tanggalperiksa, string $keterangan): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE referensi_mobilejkn_bpjs
             SET status = 'Batal', keterangan_batal = :ket
             WHERE nomorkartu = :nk AND kodepoli = :kp AND tanggalperiksa = :tgl"
        );
        $stmt->execute([
            ':ket' => trim($keterangan),
            ':nk' => trim($noKartu),
            ':kp' => trim($kodepoli),
            ':tgl' => trim($tanggalperiksa),
        ]);
        return $stmt->rowCount() > 0;
    }

    private function isSimrsSyncEnabled(): bool
    {
        $stmt = $this->pdo->prepare("SELECT value FROM mlite_settings WHERE module = 'jkn_mobile' AND field = 'sync_to_simrs' LIMIT 1");
        $stmt->execute();
        $val = $stmt->fetchColumn();
        return $val === false || !in_array(strtolower(trim((string) $val)), ['0', 'false', 'off', 'no'], true);
    }

    private function existsRow(string $sql, string $param): bool
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':v' => $param]);
        return $stmt->fetchColumn() !== false;
    }
}
