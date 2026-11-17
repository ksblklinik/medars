<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\VeronisaRepositoryInterface;
use PDO;

final class PdoVeronisaRepository implements VeronisaRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listClaims(string $from = '', string $to = '', string $search = ''): array
    {
        $sql = 'SELECT * FROM mlite_veronisa WHERE 1=1';
        $params = [];
        if ($from !== '' && $to !== '') {
            $sql .= ' AND tanggal BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' AND (no_rawat LIKE :q OR no_rkm_medis LIKE :q OR nosep LIKE :q OR catatan LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveClaim(array $data): array
    {
        $tgl = trim((string) ($data['tanggal'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $rm = trim((string) ($data['no_rkm_medis'] ?? ''));
        $rawat = trim((string) ($data['no_rawat'] ?? ''));
        $sep = trim((string) ($data['nosep'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'Pengajuan')) ?: 'Pengajuan';
        $catatan = trim((string) ($data['catatan'] ?? ''));
        $user = trim((string) ($data['username'] ?? 'system')) ?: 'system';

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_veronisa (tanggal, no_rkm_medis, no_rawat, nosep, status, catatan, username)
             VALUES (:t, :rm, :nr, :sep, :st, :ct, :u)'
        );
        $stmt->execute([':t' => $tgl, ':rm' => $rm, ':nr' => $rawat, ':sep' => $sep, ':st' => $status, ':ct' => $catatan, ':u' => $user]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'tanggal' => $tgl,
            'no_rkm_medis' => $rm,
            'no_rawat' => $rawat,
            'nosep' => $sep,
            'status' => $status,
            'catatan' => $catatan,
            'username' => $user,
        ];
    }

    public function updateStatus(int $id, string $status, string $catatan = ''): bool
    {
        $stmt = $this->pdo->prepare('UPDATE mlite_veronisa SET status = :st, catatan = :ct WHERE id = :id');
        $stmt->execute([':st' => trim($status), ':ct' => trim($catatan), ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteClaim(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM mlite_veronisa WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function findClaimById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mlite_veronisa WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function listObatMapping(string $search = ''): array
    {
        $sql = 'SELECT * FROM mlite_veronisa_obat_mapping';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE kode_brng LIKE :q OR kode_dpho LIKE :q OR nama_dpho LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY nama_dpho ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveObatMapping(array $data): array
    {
        $kodeBrng = trim((string) ($data['kode_brng'] ?? ''));
        $kodeDpho = trim((string) ($data['kode_dpho'] ?? ''));
        $namaDpho = trim((string) ($data['nama_dpho'] ?? ''));
        $harga = max(0.0, (float) ($data['harga_dpho'] ?? 0));
        $status = trim((string) ($data['status'] ?? 'Aktif')) ?: 'Aktif';

        $check = $this->pdo->prepare('SELECT kode_brng FROM mlite_veronisa_obat_mapping WHERE kode_brng = :kb LIMIT 1');
        $check->execute([':kb' => $kodeBrng]);
        if ($check->fetch(PDO::FETCH_ASSOC) !== false) {
            $stmt = $this->pdo->prepare(
                'UPDATE mlite_veronisa_obat_mapping SET kode_dpho = :kd, nama_dpho = :nd, harga_dpho = :hd, status = :st WHERE kode_brng = :kb'
            );
            $stmt->execute([':kd' => $kodeDpho, ':nd' => $namaDpho, ':hd' => $harga, ':st' => $status, ':kb' => $kodeBrng]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO mlite_veronisa_obat_mapping (kode_brng, kode_dpho, nama_dpho, harga_dpho, status) VALUES (:kb, :kd, :nd, :hd, :st)'
            );
            $stmt->execute([':kb' => $kodeBrng, ':kd' => $kodeDpho, ':nd' => $namaDpho, ':hd' => $harga, ':st' => $status]);
        }

        return [
            'kode_brng' => $kodeBrng,
            'kode_dpho' => $kodeDpho,
            'nama_dpho' => $namaDpho,
            'harga_dpho' => $harga,
            'status' => $status,
        ];
    }
}
