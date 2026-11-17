<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Rme\Repository\OperasiRepositoryInterface;
use PDO;

final class PdoOperasiRepository implements OperasiRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPaket(string $search = ''): array
    {
        $sql = 'SELECT kode_paket, nm_perawatan, kategori, tarif_total, status FROM paket_operasi';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE kode_paket LIKE :q OR nm_perawatan LIKE :q OR kategori LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY nm_perawatan ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function savePaket(array $data): array
    {
        $kode = trim((string) ($data['kode_paket'] ?? ''));
        $nama = trim((string) ($data['nm_perawatan'] ?? ''));
        $kat = trim((string) ($data['kategori'] ?? 'Bedah Minor')) ?: 'Bedah Minor';
        $tarif = max(0.0, (float) ($data['tarif_total'] ?? 0));
        $status = trim((string) ($data['status'] ?? 'Aktif')) ?: 'Aktif';

        $check = $this->pdo->prepare('SELECT kode_paket FROM paket_operasi WHERE kode_paket = :k LIMIT 1');
        $check->execute([':k' => $kode]);
        if ($check->fetch(PDO::FETCH_ASSOC) !== false) {
            $stmt = $this->pdo->prepare(
                'UPDATE paket_operasi SET nm_perawatan = :n, kategori = :kat, tarif_total = :t, status = :s WHERE kode_paket = :k'
            );
            $stmt->execute([':n' => $nama, ':kat' => $kat, ':t' => $tarif, ':s' => $status, ':k' => $kode]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO paket_operasi (kode_paket, nm_perawatan, kategori, tarif_total, status) VALUES (:k, :n, :kat, :t, :s)'
            );
            $stmt->execute([':k' => $kode, ':n' => $nama, ':kat' => $kat, ':t' => $tarif, ':s' => $status]);
        }

        return [
            'kode_paket' => $kode,
            'nm_perawatan' => $nama,
            'kategori' => $kat,
            'tarif_total' => $tarif,
            'status' => $status,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBookings(string $from = '', string $to = ''): array
    {
        $sql = 'SELECT b.*, p.nm_perawatan, p.tarif_total
                FROM booking_operasi b
                LEFT JOIN paket_operasi p ON p.kode_paket = b.kode_paket';
        $params = [];
        if ($from !== '' && $to !== '') {
            $sql .= ' WHERE b.tanggal BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $sql .= ' ORDER BY b.tanggal DESC, b.jam_mulai ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveBooking(array $data): array
    {
        $noRawat = trim((string) ($data['no_rawat'] ?? ''));
        $kodePaket = trim((string) ($data['kode_paket'] ?? ''));
        $tanggal = trim((string) ($data['tanggal'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $jamMulai = trim((string) ($data['jam_mulai'] ?? '08:00')) ?: '08:00';
        $jamSelesai = trim((string) ($data['jam_selesai'] ?? '09:00')) ?: '09:00';
        $status = trim((string) ($data['status'] ?? 'Menunggu')) ?: 'Menunggu';
        $kdDokter = trim((string) ($data['kd_dokter'] ?? ''));
        $ruangOk = trim((string) ($data['kd_ruang_ok'] ?? 'OK-1')) ?: 'OK-1';

        $stmt = $this->pdo->prepare(
            'INSERT INTO booking_operasi (no_rawat, kode_paket, tanggal, jam_mulai, jam_selesai, status, kd_dokter, kd_ruang_ok)
             VALUES (:nr, :kp, :tgl, :jm, :js, :st, :kd, :ok)'
        );
        $stmt->execute([
            ':nr' => $noRawat,
            ':kp' => $kodePaket,
            ':tgl' => $tanggal,
            ':jm' => $jamMulai,
            ':js' => $jamSelesai,
            ':st' => $status,
            ':kd' => $kdDokter,
            ':ok' => $ruangOk,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'no_rawat' => $noRawat,
            'kode_paket' => $kodePaket,
            'tanggal' => $tanggal,
            'jam_mulai' => $jamMulai,
            'jam_selesai' => $jamSelesai,
            'status' => $status,
            'kd_dokter' => $kdDokter,
            'kd_ruang_ok' => $ruangOk,
        ];
    }

    public function updateBookingStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE booking_operasi SET status = :st WHERE id = :id');
        $stmt->execute([':st' => trim($status), ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listLaporan(string $noRawat = ''): array
    {
        $sql = 'SELECT * FROM laporan_operasi';
        $params = [];
        $q = trim($noRawat);
        if ($q !== '') {
            $sql .= ' WHERE no_rawat LIKE :q OR diagnosa_preop LIKE :q OR operator1 LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveLaporan(array $data): array
    {
        $noRawat = trim((string) ($data['no_rawat'] ?? ''));
        $kodePaket = trim((string) ($data['kode_paket'] ?? ''));
        $tanggal = trim((string) ($data['tanggal'] ?? date('Y-m-d H:i:s'))) ?: date('Y-m-d H:i:s');
        $preop = trim((string) ($data['diagnosa_preop'] ?? ''));
        $postop = trim((string) ($data['diagnosa_postop'] ?? ''));
        $jaringan = trim((string) ($data['jaringan_dieksekusi'] ?? ''));
        $pa = trim((string) ($data['permintaan_pa'] ?? 'Tidak')) ?: 'Tidak';
        $op1 = trim((string) ($data['operator1'] ?? ''));
        $asop1 = trim((string) ($data['asisten_operator1'] ?? ''));
        $anestesi = trim((string) ($data['dokter_anestesi'] ?? ''));
        $isi = trim((string) ($data['laporan_operasi'] ?? ''));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO laporan_operasi (no_rawat, kode_paket, tanggal, diagnosa_preop, diagnosa_postop, jaringan_dieksekusi, permintaan_pa, operator1, asisten_operator1, dokter_anestesi, laporan_operasi)
                 VALUES (:nr, :kp, :tgl, :pre, :post, :jar, :pa, :op1, :as1, :an, :isi)'
            );
            $stmt->execute([
                ':nr' => $noRawat,
                ':kp' => $kodePaket,
                ':tgl' => $tanggal,
                ':pre' => $preop,
                ':post' => $postop,
                ':jar' => $jaringan,
                ':pa' => $pa,
                ':op1' => $op1,
                ':as1' => $asop1,
                ':an' => $anestesi,
                ':isi' => $isi,
            ]);
            $laporanId = (int) $this->pdo->lastInsertId();

            if ($kodePaket !== '') {
                $pStmt = $this->pdo->prepare('SELECT nm_perawatan, tarif_total FROM paket_operasi WHERE kode_paket = :k LIMIT 1');
                $pStmt->execute([':k' => $kodePaket]);
                $paket = $pStmt->fetch(PDO::FETCH_ASSOC);
                if ($paket !== false && (float) $paket['tarif_total'] > 0) {
                    $claimType = 'Tunai';
                    $rStmt = $this->pdo->prepare('SELECT kd_pj FROM reg_periksa WHERE no_rawat = :nr LIMIT 1');
                    $rStmt->execute([':nr' => $noRawat]);
                    $kdPj = strtoupper((string) ($rStmt->fetchColumn() ?: ''));
                    if (str_contains($kdPj, 'BPJ')) {
                        $claimType = 'BPJS';
                    }

                    $lStmt = $this->pdo->prepare(
                        "INSERT INTO mlite_billing_ledger (no_rawat, item_code, item_name, category, qty, unit_price, total_amount, claim_type, status, provider_id, created_at)
                         VALUES (:nr, :ic, :in, 'Operasi', 1, :up, :ta, :ct, 'Pending', :pr, :ca)"
                    );
                    $lStmt->execute([
                        ':nr' => $noRawat,
                        ':ic' => $kodePaket,
                        ':in' => $paket['nm_perawatan'],
                        ':up' => (float) $paket['tarif_total'],
                        ':ta' => (float) $paket['tarif_total'],
                        ':ct' => $claimType,
                        ':pr' => $op1,
                        ':ca' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return [
            'id' => $laporanId,
            'no_rawat' => $noRawat,
            'kode_paket' => $kodePaket,
            'tanggal' => $tanggal,
            'diagnosa_preop' => $preop,
            'diagnosa_postop' => $postop,
            'operator1' => $op1,
        ];
    }
}
