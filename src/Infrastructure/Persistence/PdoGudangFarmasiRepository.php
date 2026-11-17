<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Pharmacy\Repository\GudangFarmasiRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class PdoGudangFarmasiRepository implements GudangFarmasiRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function listBarang(string $q = ''): array
    {
        $like = '%' . trim($q) . '%';
        $stmt = $this->pdo->prepare("
            SELECT db.*, COALESCE(SUM(gb.stok), 0) AS total_stok
            FROM databarang db
            LEFT JOIN gudangbarang gb ON db.kode_brng = gb.kode_brng
            WHERE db.kode_brng LIKE :q OR db.nama_brng LIKE :q
            GROUP BY db.kode_brng
            ORDER BY db.nama_brng ASC
            LIMIT 100
        ");
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveBarang(array $payload): void
    {
        $kode = trim((string) ($payload['kode_brng'] ?? ''));
        $nama = trim((string) ($payload['nama_brng'] ?? ''));
        if ($kode === '' || $nama === '') {
            throw new \InvalidArgumentException('Kode dan nama obat/barang wajib diisi.');
        }

        $chk = $this->pdo->prepare('SELECT 1 FROM databarang WHERE kode_brng = :k');
        $chk->execute([':k' => $kode]);
        $exists = $chk->fetchColumn() !== false;

        $params = [
            ':k' => $kode,
            ':nm' => $nama,
            ':sat' => trim((string) ($payload['kode_sat'] ?? 'TAB')) ?: 'TAB',
            ':dasar' => (float) ($payload['dasar'] ?? ($payload['h_beli'] ?? 0)),
            ':hb' => (float) ($payload['h_beli'] ?? 0),
            ':ht' => (float) ($payload['harga_tunai'] ?? 0),
            ':hk' => (float) ($payload['harga_kapitasi'] ?? 0),
            ':hnk' => (float) ($payload['harga_nonkapitasi'] ?? 0),
            ':jb' => (float) ($payload['jualbebas'] ?? ($payload['harga_tunai'] ?? 0)),
            ':st' => trim((string) ($payload['status'] ?? '1')) ?: '1',
        ];
        $sql = $exists
            ? 'UPDATE databarang SET nama_brng = :nm, kode_sat = :sat, dasar = :dasar, h_beli = :hb, harga_tunai = :ht, harga_kapitasi = :hk, harga_nonkapitasi = :hnk, jualbebas = :jb, status = :st WHERE kode_brng = :k'
            : 'INSERT INTO databarang (kode_brng, nama_brng, kode_sat, dasar, h_beli, harga_tunai, harga_kapitasi, harga_nonkapitasi, jualbebas, status) VALUES (:k, :nm, :sat, :dasar, :hb, :ht, :hk, :hnk, :jb, :st)';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function receiveStock(
        string $kodeBrng,
        string $kdBangsal,
        float $qty,
        string $noBatch,
        string $noFaktur,
        string $petugas,
        string $keterangan
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah penerimaan stok harus lebih dari 0.');
        }

        $this->tx->transaction(function () use ($kodeBrng, $kdBangsal, $qty, $noBatch, $noFaktur, $petugas, $keterangan): void {
            $stokAwal = $this->getDepoStock($kodeBrng, $kdBangsal);
            $this->upsertBatchStock($kodeBrng, $kdBangsal, $noBatch, $noFaktur, $qty);
            $this->insertRiwayat($kodeBrng, $stokAwal, $qty, 0.0, $stokAwal + $qty, 'Penerimaan', $petugas, $kdBangsal, $noBatch, $noFaktur, $keterangan);
        });
    }

    public function mutateStock(
        string $kodeBrng,
        string $fromBangsal,
        string $toBangsal,
        float $qty,
        string $petugas,
        string $keterangan
    ): void {
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Jumlah mutasi stok harus lebih dari 0.');
        }
        if ($fromBangsal === $toBangsal) {
            throw new \InvalidArgumentException('Depo asal dan tujuan tidak boleh sama.');
        }

        $this->tx->transaction(function () use ($kodeBrng, $fromBangsal, $toBangsal, $qty, $petugas, $keterangan): void {
            $srcAwal = $this->getDepoStock($kodeBrng, $fromBangsal);
            if ($srcAwal < $qty) {
                throw new InsufficientStockException("Stok {$kodeBrng} di depo {$fromBangsal} tidak mencukupi ({$srcAwal} < {$qty}).");
            }

            $this->deductFromDepo($kodeBrng, $fromBangsal, $qty);
            $this->insertRiwayat($kodeBrng, $srcAwal, 0.0, $qty, $srcAwal - $qty, 'Mutasi Keluar', $petugas, $fromBangsal, '', '', $keterangan);

            $dstAwal = $this->getDepoStock($kodeBrng, $toBangsal);
            $this->upsertBatchStock($kodeBrng, $toBangsal, '', '', $qty);
            $this->insertRiwayat($kodeBrng, $dstAwal, $qty, 0.0, $dstAwal + $qty, 'Mutasi Masuk', $petugas, $toBangsal, '', '', $keterangan);
        });
    }

    public function listKartuStok(string $kodeBrng = '', string $kdBangsal = ''): array
    {
        $sql = "
            SELECT r.*, db.nama_brng
            FROM riwayat_barang_medis r
            LEFT JOIN databarang db ON r.kode_brng = db.kode_brng
            WHERE 1=1
        ";
        $params = [];
        if (trim($kodeBrng) !== '') {
            $sql .= ' AND r.kode_brng = :kb';
            $params[':kb'] = trim($kodeBrng);
        }
        if (trim($kdBangsal) !== '') {
            $sql .= ' AND r.kd_bangsal = :dep';
            $params[':dep'] = trim($kdBangsal);
        }
        $sql .= ' ORDER BY r.id DESC LIMIT 100';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getDepoStock(string $kodeBrng, string $kdBangsal): float
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(SUM(stok), 0) FROM gudangbarang WHERE kode_brng = :kb AND kd_bangsal = :dep');
        $stmt->execute([':kb' => $kodeBrng, ':dep' => $kdBangsal]);
        return (float) $stmt->fetchColumn();
    }

    private function upsertBatchStock(string $kodeBrng, string $kdBangsal, string $noBatch, string $noFaktur, float $delta): void
    {
        $chk = $this->pdo->prepare('SELECT stok FROM gudangbarang WHERE kode_brng = :kb AND kd_bangsal = :dep AND no_batch = :nb AND no_faktur = :nf');
        $chk->execute([':kb' => $kodeBrng, ':dep' => $kdBangsal, ':nb' => $noBatch, ':nf' => $noFaktur]);
        $cur = $chk->fetchColumn();
        if ($cur !== false) {
            $this->pdo->prepare('UPDATE gudangbarang SET stok = stok + :d WHERE kode_brng = :kb AND kd_bangsal = :dep AND no_batch = :nb AND no_faktur = :nf')
                ->execute([':d' => $delta, ':kb' => $kodeBrng, ':dep' => $kdBangsal, ':nb' => $noBatch, ':nf' => $noFaktur]);
        } else {
            $this->pdo->prepare('INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok) VALUES (:kb, :dep, :nb, :nf, :s)')
                ->execute([':kb' => $kodeBrng, ':dep' => $kdBangsal, ':nb' => $noBatch, ':nf' => $noFaktur, ':s' => $delta]);
        }
    }

    private function deductFromDepo(string $kodeBrng, string $kdBangsal, float $qty): void
    {
        $rows = $this->pdo->prepare('SELECT no_batch, no_faktur, stok FROM gudangbarang WHERE kode_brng = :kb AND kd_bangsal = :dep AND stok > 0 ORDER BY no_batch ASC');
        $rows->execute([':kb' => $kodeBrng, ':dep' => $kdBangsal]);
        $rem = $qty;
        foreach ($rows->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            if ($rem <= 0) {
                break;
            }
            $take = min($rem, (float) $r['stok']);
            $this->pdo->prepare('UPDATE gudangbarang SET stok = stok - :t WHERE kode_brng = :kb AND kd_bangsal = :dep AND no_batch = :nb AND no_faktur = :nf')
                ->execute([':t' => $take, ':kb' => $kodeBrng, ':dep' => $kdBangsal, ':nb' => $r['no_batch'], ':nf' => $r['no_faktur']]);
            $rem -= $take;
        }
    }

    private function insertRiwayat(
        string $kodeBrng,
        float $stokAwal,
        float $masuk,
        float $keluar,
        float $stokAkhir,
        string $posisi,
        string $petugas,
        string $kdBangsal,
        string $noBatch,
        string $noFaktur,
        string $keterangan
    ): void {
        $this->pdo->prepare("
            INSERT INTO riwayat_barang_medis (kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan)
            VALUES (:kb, :sa, :m, :k, :sak, :pos, :tgl, :jam, :ptg, :dep, 'Simpan', :nb, :nf, :ket)
        ")->execute([
            ':kb' => $kodeBrng,
            ':sa' => $stokAwal,
            ':m' => $masuk,
            ':k' => $keluar,
            ':sak' => $stokAkhir,
            ':pos' => $posisi,
            ':tgl' => date('Y-m-d'),
            ':jam' => date('H:i:s'),
            ':ptg' => $petugas,
            ':dep' => $kdBangsal,
            ':nb' => $noBatch,
            ':nf' => $noFaktur,
            ':ket' => $keterangan,
        ]);
    }
}
