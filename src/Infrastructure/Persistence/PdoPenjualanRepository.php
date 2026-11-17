<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\PenjualanRepositoryInterface;
use PDO;
use RuntimeException;

final class PdoPenjualanRepository implements PenjualanRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBarang(string $search = ''): array
    {
        $sql = 'SELECT id, nama_barang, kode_satuan, stok, harga, keterangan FROM mlite_penjualan_barang';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE nama_barang LIKE :q OR keterangan LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY nama_barang ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function saveBarang(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $nama = trim((string) ($data['nama_barang'] ?? ''));
        $satuan = trim((string) ($data['kode_satuan'] ?? 'Pcs')) ?: 'Pcs';
        $stok = max(0, (int) ($data['stok'] ?? 0));
        $harga = max(0.0, (float) ($data['harga'] ?? 0));
        $ket = trim((string) ($data['keterangan'] ?? ''));

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                'UPDATE mlite_penjualan_barang SET nama_barang = :n, kode_satuan = :s, stok = :st, harga = :h, keterangan = :k WHERE id = :id'
            );
            $stmt->execute([':n' => $nama, ':s' => $satuan, ':st' => $stok, ':h' => $harga, ':k' => $ket, ':id' => $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO mlite_penjualan_barang (nama_barang, kode_satuan, stok, harga, keterangan) VALUES (:n, :s, :st, :h, :k)'
            );
            $stmt->execute([':n' => $nama, ':s' => $satuan, ':st' => $stok, ':h' => $harga, ':k' => $ket]);
            $id = (int) $this->pdo->lastInsertId();
        }

        return [
            'id' => $id,
            'nama_barang' => $nama,
            'kode_satuan' => $satuan,
            'stok' => $stok,
            'harga' => $harga,
            'keterangan' => $ket,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createOrder(array $data): array
    {
        $nama = trim((string) ($data['nama_pembeli'] ?? 'Umum')) ?: 'Umum';
        $alamat = trim((string) ($data['alamat_pembeli'] ?? ''));
        $telp = trim((string) ($data['nomor_telepon'] ?? ''));
        $ket = trim((string) ($data['keterangan'] ?? ''));
        $user = trim((string) ($data['id_user'] ?? 'system')) ?: 'system';
        $tgl = date('Y-m-d');
        $jam = date('H:i:s');

        $stmt = $this->pdo->prepare(
            "INSERT INTO mlite_penjualan (nama_pembeli, alamat_pembeli, nomor_telepon, tanggal, jam, id_user, keterangan, status)
             VALUES (:n, :a, :t, :tgl, :jam, :u, :k, 'Draft')"
        );
        $stmt->execute([
            ':n' => $nama,
            ':a' => $alamat,
            ':t' => $telp,
            ':tgl' => $tgl,
            ':jam' => $jam,
            ':u' => $user,
            ':k' => $ket,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'nama_pembeli' => $nama,
            'tanggal' => $tgl,
            'jam' => $jam,
            'status' => 'Draft',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listOrders(string $from = '', string $to = ''): array
    {
        $sql = 'SELECT p.*, COALESCE((SELECT SUM(harga_total) FROM mlite_penjualan_detail d WHERE d.id_penjualan = p.id), 0) AS subtotal
                FROM mlite_penjualan p';
        $params = [];
        if ($from !== '' && $to !== '') {
            $sql .= ' WHERE p.tanggal BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public function addItem(int $idPenjualan, int $idBarang, int $jumlah, string $user = 'system'): array
    {
        if ($jumlah <= 0) {
            throw new RuntimeException('Jumlah barang harus lebih dari 0.');
        }

        $this->pdo->beginTransaction();
        try {
            $bStmt = $this->pdo->prepare('SELECT id, nama_barang, stok, harga FROM mlite_penjualan_barang WHERE id = :id LIMIT 1');
            $bStmt->execute([':id' => $idBarang]);
            $barang = $bStmt->fetch(PDO::FETCH_ASSOC);
            if ($barang === false) {
                throw new RuntimeException('Barang tidak ditemukan.');
            }
            $stok = (int) $barang['stok'];
            if ($stok < $jumlah) {
                throw new RuntimeException("Stok {$barang['nama_barang']} tidak mencukupi (tersedia: {$stok}).");
            }

            $upd = $this->pdo->prepare('UPDATE mlite_penjualan_barang SET stok = stok - :j WHERE id = :id AND stok >= :j');
            $upd->execute([':j' => $jumlah, ':id' => $idBarang]);
            if ($upd->rowCount() === 0) {
                throw new RuntimeException('Stok barang berubah, silakan coba lagi.');
            }

            $harga = (float) $barang['harga'];
            $total = $harga * $jumlah;
            $tgl = date('Y-m-d');
            $jam = date('H:i:s');

            $ins = $this->pdo->prepare(
                'INSERT INTO mlite_penjualan_detail (id_penjualan, id_barang, nama_barang, harga, jumlah, harga_total, tanggal, jam, id_user)
                 VALUES (:p, :b, :nb, :h, :j, :ht, :tgl, :jam, :u)'
            );
            $ins->execute([
                ':p' => $idPenjualan,
                ':b' => $idBarang,
                ':nb' => $barang['nama_barang'],
                ':h' => $harga,
                ':j' => $jumlah,
                ':ht' => $total,
                ':tgl' => $tgl,
                ':jam' => $jam,
                ':u' => $user,
            ]);

            $detailId = (int) $this->pdo->lastInsertId();
            $this->pdo->commit();

            return [
                'id' => $detailId,
                'id_penjualan' => $idPenjualan,
                'id_barang' => $idBarang,
                'nama_barang' => $barang['nama_barang'],
                'harga' => $harga,
                'jumlah' => $jumlah,
                'harga_total' => $total,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function removeItem(int $detailId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id_barang, jumlah FROM mlite_penjualan_detail WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $detailId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                $this->pdo->rollBack();
                return false;
            }

            $rest = $this->pdo->prepare('UPDATE mlite_penjualan_barang SET stok = stok + :j WHERE id = :b');
            $rest->execute([':j' => (int) $row['jumlah'], ':b' => (int) $row['id_barang']]);

            $del = $this->pdo->prepare('DELETE FROM mlite_penjualan_detail WHERE id = :id');
            $del->execute([':id' => $detailId]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOrderDetail(int $idPenjualan): ?array
    {
        $hStmt = $this->pdo->prepare('SELECT * FROM mlite_penjualan WHERE id = :id LIMIT 1');
        $hStmt->execute([':id' => $idPenjualan]);
        $header = $hStmt->fetch(PDO::FETCH_ASSOC);
        if ($header === false) {
            return null;
        }

        $dStmt = $this->pdo->prepare('SELECT * FROM mlite_penjualan_detail WHERE id_penjualan = :id ORDER BY id ASC');
        $dStmt->execute([':id' => $idPenjualan]);
        $items = $dStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $bStmt = $this->pdo->prepare('SELECT * FROM mlite_penjualan_billing WHERE id_penjualan = :id ORDER BY id DESC LIMIT 1');
        $bStmt->execute([':id' => $idPenjualan]);
        $billing = $bStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'header' => $header,
            'items' => $items,
            'billing' => $billing,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settleOrder(int $idPenjualan, float $potongan, float $jumlahBayar, string $caraBayar, string $user = 'system'): array
    {
        $sumStmt = $this->pdo->prepare('SELECT COALESCE(SUM(harga_total), 0) FROM mlite_penjualan_detail WHERE id_penjualan = :id');
        $sumStmt->execute([':id' => $idPenjualan]);
        $total = (float) $sumStmt->fetchColumn();
        $harusBayar = max(0.0, $total - max(0.0, $potongan));
        $tgl = date('Y-m-d');
        $jam = date('H:i:s');

        $ins = $this->pdo->prepare(
            'INSERT INTO mlite_penjualan_billing (id_penjualan, jumlah_total, potongan, jumlah_harus_bayar, jumlah_bayar, cara_bayar, tanggal, jam, id_user)
             VALUES (:p, :t, :pot, :hb, :jb, :cb, :tgl, :jam, :u)'
        );
        $ins->execute([
            ':p' => $idPenjualan,
            ':t' => $total,
            ':pot' => max(0.0, $potongan),
            ':hb' => $harusBayar,
            ':jb' => max($harusBayar, $jumlahBayar),
            ':cb' => $caraBayar !== '' ? $caraBayar : 'Tunai',
            ':tgl' => $tgl,
            ':jam' => $jam,
            ':u' => $user,
        ]);

        $upd = $this->pdo->prepare("UPDATE mlite_penjualan SET status = 'Lunas' WHERE id = :id");
        $upd->execute([':id' => $idPenjualan]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'id_penjualan' => $idPenjualan,
            'jumlah_total' => $total,
            'potongan' => max(0.0, $potongan),
            'jumlah_harus_bayar' => $harusBayar,
            'jumlah_bayar' => max($harusBayar, $jumlahBayar),
            'cara_bayar' => $caraBayar !== '' ? $caraBayar : 'Tunai',
        ];
    }

    /**
     * @return array{total_transaksi: int, total_omzet: float, top_items: list<array<string, mixed>>}
     */
    public function getTurnoverReport(string $from, string $to): array
    {
        $sStmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total_transaksi, COALESCE(SUM(jumlah_harus_bayar), 0) AS total_omzet
             FROM mlite_penjualan_billing
             WHERE tanggal BETWEEN :from AND :to'
        );
        $sStmt->execute([':from' => $from, ':to' => $to]);
        $summary = $sStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_transaksi' => 0, 'total_omzet' => 0];

        $tStmt = $this->pdo->prepare(
            'SELECT nama_barang, SUM(jumlah) AS qty_terjual, SUM(harga_total) AS omzet
             FROM mlite_penjualan_detail
             WHERE tanggal BETWEEN :from AND :to
             GROUP BY nama_barang
             ORDER BY omzet DESC
             LIMIT 10'
        );
        $tStmt->execute([':from' => $from, ':to' => $to]);
        $topItems = $tStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_transaksi' => (int) ($summary['total_transaksi'] ?? 0),
            'total_omzet' => (float) ($summary['total_omzet'] ?? 0),
            'top_items' => $topItems,
        ];
    }
}
