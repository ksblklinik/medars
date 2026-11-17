<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\InventarisRepositoryInterface;

final readonly class PdoInventarisRepository implements InventarisRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function listAset(string $q = ''): array
    {
        $like = '%' . trim($q) . '%';
        $stmt = $this->pdo->prepare('
            SELECT * FROM mlite_inventaris
            WHERE kode_aset LIKE :q OR nama_aset LIKE :q OR kategori LIKE :q OR lokasi LIKE :q
            ORDER BY kode_aset ASC LIMIT 100
        ');
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveAset(array $payload): void
    {
        $kode = trim((string) ($payload['kode_aset'] ?? ''));
        $nama = trim((string) ($payload['nama_aset'] ?? ''));
        if ($kode === '' || $nama === '') {
            throw new \InvalidArgumentException('Kode dan nama aset wajib diisi.');
        }

        $chk = $this->pdo->prepare('SELECT 1 FROM mlite_inventaris WHERE kode_aset = :k');
        $chk->execute([':k' => $kode]);
        $exists = $chk->fetchColumn() !== false;

        $params = [
            ':k' => $kode,
            ':nm' => $nama,
            ':kat' => trim((string) ($payload['kategori'] ?? 'Alat Medis')) ?: 'Alat Medis',
            ':lok' => trim((string) ($payload['lokasi'] ?? '')),
            ':tgl' => trim((string) ($payload['tgl_perolehan'] ?? date('Y-m-d'))),
            ':val' => (float) ($payload['nilai_perolehan'] ?? 0),
            ':kon' => trim((string) ($payload['kondisi'] ?? 'Baik')) ?: 'Baik',
            ':st' => trim((string) ($payload['status'] ?? 'Aktif')) ?: 'Aktif',
        ];
        $sql = $exists
            ? 'UPDATE mlite_inventaris SET nama_aset = :nm, kategori = :kat, lokasi = :lok, tgl_perolehan = :tgl, nilai_perolehan = :val, kondisi = :kon, status = :st WHERE kode_aset = :k'
            : 'INSERT INTO mlite_inventaris (kode_aset, nama_aset, kategori, lokasi, tgl_perolehan, nilai_perolehan, kondisi, status) VALUES (:k, :nm, :kat, :lok, :tgl, :val, :kon, :st)';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function deleteAset(string $kodeAset): void
    {
        $k = trim($kodeAset);
        if ($k === '') {
            throw new \InvalidArgumentException('Kode aset wajib diisi.');
        }
        $this->pdo->prepare('DELETE FROM mlite_inventaris WHERE kode_aset = :k')->execute([':k' => $k]);
    }
}
