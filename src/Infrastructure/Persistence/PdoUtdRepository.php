<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\UtdRepositoryInterface;
use PDO;

final class PdoUtdRepository implements UtdRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listKomponen(): array
    {
        $stmt = $this->pdo->query('SELECT kode, nama, lama, total FROM utd_komponen_darah ORDER BY nama ASC');
        return $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function saveKomponen(array $data): array
    {
        $kode = trim((string) ($data['kode'] ?? ''));
        $nama = trim((string) ($data['nama'] ?? ''));
        $lama = max(1, (int) ($data['lama'] ?? 35));
        $total = max(0.0, (float) ($data['total'] ?? 0));

        $check = $this->pdo->prepare('SELECT kode FROM utd_komponen_darah WHERE kode = :k LIMIT 1');
        $check->execute([':k' => $kode]);
        if ($check->fetch(PDO::FETCH_ASSOC) !== false) {
            $stmt = $this->pdo->prepare('UPDATE utd_komponen_darah SET nama = :n, lama = :l, total = :t WHERE kode = :k');
            $stmt->execute([':n' => $nama, ':l' => $lama, ':t' => $total, ':k' => $kode]);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO utd_komponen_darah (kode, nama, lama, total) VALUES (:k, :n, :l, :t)');
            $stmt->execute([':k' => $kode, ':n' => $nama, ':l' => $lama, ':t' => $total]);
        }

        return ['kode' => $kode, 'nama' => $nama, 'lama' => $lama, 'total' => $total];
    }

    public function listPendonor(string $search = ''): array
    {
        $sql = 'SELECT * FROM utd_pendonor';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE no_pendonor LIKE :q OR nama LIKE :q OR golongan_darah LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY nama ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function savePendonor(array $data): array
    {
        $no = trim((string) ($data['no_pendonor'] ?? ''));
        $nama = trim((string) ($data['nama'] ?? ''));
        $ktp = trim((string) ($data['no_ktp'] ?? ''));
        $jk = trim((string) ($data['jk'] ?? 'L')) ?: 'L';
        $gol = trim((string) ($data['golongan_darah'] ?? 'O')) ?: 'O';
        $resus = trim((string) ($data['resus'] ?? '+')) ?: '+';
        $telp = trim((string) ($data['no_telp'] ?? ''));
        $alamat = trim((string) ($data['alamat'] ?? ''));

        $check = $this->pdo->prepare('SELECT no_pendonor FROM utd_pendonor WHERE no_pendonor = :no LIMIT 1');
        $check->execute([':no' => $no]);
        if ($check->fetch(PDO::FETCH_ASSOC) !== false) {
            $stmt = $this->pdo->prepare(
                'UPDATE utd_pendonor SET nama = :n, no_ktp = :ktp, jk = :jk, golongan_darah = :g, resus = :r, no_telp = :t, alamat = :a WHERE no_pendonor = :no'
            );
            $stmt->execute([':n' => $nama, ':ktp' => $ktp, ':jk' => $jk, ':g' => $gol, ':r' => $resus, ':t' => $telp, ':a' => $alamat, ':no' => $no]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO utd_pendonor (no_pendonor, nama, no_ktp, jk, golongan_darah, resus, no_telp, alamat) VALUES (:no, :n, :ktp, :jk, :g, :r, :t, :a)'
            );
            $stmt->execute([':no' => $no, ':n' => $nama, ':ktp' => $ktp, ':jk' => $jk, ':g' => $gol, ':r' => $resus, ':t' => $telp, ':a' => $alamat]);
        }

        return ['no_pendonor' => $no, 'nama' => $nama, 'golongan_darah' => $gol, 'resus' => $resus];
    }

    public function listStokDarah(string $golongan = ''): array
    {
        $sql = 'SELECT s.*, k.nama AS nama_komponen
                FROM utd_stok_darah s
                LEFT JOIN utd_komponen_darah k ON k.kode = s.kode_komponen';
        $params = [];
        $g = trim($golongan);
        if ($g !== '') {
            $sql .= ' WHERE s.golongan_darah = :g';
            $params[':g'] = $g;
        }
        $sql .= ' ORDER BY s.tanggal_kadaluarsa ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveStokDarah(array $data): array
    {
        $noKantong = trim((string) ($data['no_kantong'] ?? ''));
        $kodeKomp = trim((string) ($data['kode_komponen'] ?? 'PRC'));
        $gol = trim((string) ($data['golongan_darah'] ?? 'O'));
        $resus = trim((string) ($data['resus'] ?? '+')) ?: '+';
        $aftap = trim((string) ($data['tanggal_aftap'] ?? date('Y-m-d')));
        $exp = trim((string) ($data['tanggal_kadaluarsa'] ?? date('Y-m-d', strtotime('+35 days'))));
        $asal = trim((string) ($data['asal_darah'] ?? 'PMI')) ?: 'PMI';
        $status = trim((string) ($data['status'] ?? 'Ada')) ?: 'Ada';

        $check = $this->pdo->prepare('SELECT no_kantong FROM utd_stok_darah WHERE no_kantong = :nk LIMIT 1');
        $check->execute([':nk' => $noKantong]);
        if ($check->fetch(PDO::FETCH_ASSOC) !== false) {
            $stmt = $this->pdo->prepare(
                'UPDATE utd_stok_darah SET kode_komponen = :kk, golongan_darah = :g, resus = :r, tanggal_aftap = :ta, tanggal_kadaluarsa = :tk, asal_darah = :ad, status = :st WHERE no_kantong = :nk'
            );
            $stmt->execute([':kk' => $kodeKomp, ':g' => $gol, ':r' => $resus, ':ta' => $aftap, ':tk' => $exp, ':ad' => $asal, ':st' => $status, ':nk' => $noKantong]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO utd_stok_darah (no_kantong, kode_komponen, golongan_darah, resus, tanggal_aftap, tanggal_kadaluarsa, asal_darah, status) VALUES (:nk, :kk, :g, :r, :ta, :tk, :ad, :st)'
            );
            $stmt->execute([':nk' => $noKantong, ':kk' => $kodeKomp, ':g' => $gol, ':r' => $resus, ':ta' => $aftap, ':tk' => $exp, ':ad' => $asal, ':st' => $status]);
        }

        return ['no_kantong' => $noKantong, 'kode_komponen' => $kodeKomp, 'golongan_darah' => $gol, 'status' => $status];
    }

    public function listPermintaan(string $search = ''): array
    {
        $sql = 'SELECT * FROM utd_permintaan_darah';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE no_rawat LIKE :q OR no_kantong LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function savePermintaan(array $data): array
    {
        $noRawat = trim((string) ($data['no_rawat'] ?? ''));
        $noKantong = trim((string) ($data['no_kantong'] ?? ''));
        $gol = trim((string) ($data['golongan_darah'] ?? 'O'));
        $petugas = trim((string) ($data['petugas'] ?? 'Sistem')) ?: 'Sistem';
        $tgl = trim((string) ($data['tanggal'] ?? date('Y-m-d'))) ?: date('Y-m-d');

        $stmt = $this->pdo->prepare(
            "INSERT INTO utd_permintaan_darah (no_rawat, no_kantong, golongan_darah, hasil_crossmatch, petugas, tanggal, status)
             VALUES (:nr, :nk, :g, 'Menunggu', :p, :tgl, 'Menunggu')"
        );
        $stmt->execute([':nr' => $noRawat, ':nk' => $noKantong, ':g' => $gol, ':p' => $petugas, ':tgl' => $tgl]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'no_rawat' => $noRawat,
            'golongan_darah' => $gol,
            'status' => 'Menunggu',
        ];
    }

    public function updateCrossmatch(int $id, string $noKantong, string $hasil, string $status): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE utd_permintaan_darah SET no_kantong = :nk, hasil_crossmatch = :h, status = :st WHERE id = :id'
            );
            $stmt->execute([':nk' => trim($noKantong), ':h' => trim($hasil), ':st' => trim($status), ':id' => $id]);
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            if ($noKantong !== '' && $status === 'Diserahkan') {
                $upd = $this->pdo->prepare("UPDATE utd_stok_darah SET status = 'Terpakai' WHERE no_kantong = :nk");
                $upd->execute([':nk' => trim($noKantong)]);
            } elseif ($noKantong !== '' && $hasil === 'Cocok') {
                $upd = $this->pdo->prepare("UPDATE utd_stok_darah SET status = 'Crossmatch' WHERE no_kantong = :nk");
                $upd->execute([':nk' => trim($noKantong)]);
            }

            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}
