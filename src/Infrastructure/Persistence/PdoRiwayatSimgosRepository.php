<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Clinical\Repository\RiwayatSimgosRepositoryInterface;
use PDO;

final class PdoRiwayatSimgosRepository implements RiwayatSimgosRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(string $query = '', string $from = '', string $to = ''): array
    {
        $sql = 'SELECT * FROM mlite_riwayat_simgos WHERE 1=1';
        $params = [];
        if (trim($from) !== '' && trim($to) !== '') {
            $sql .= ' AND tgl_kunjungan BETWEEN :from AND :to';
            $params[':from'] = trim($from);
            $params[':to'] = trim($to);
        }
        $q = trim($query);
        if ($q !== '') {
            $sql .= ' AND (nopen LIKE :q OR no_rkm_medis LIKE :q OR nik LIKE :q OR nm_pasien LIKE :q OR diagnosa_icd10 LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY tgl_kunjungan DESC, id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mlite_riwayat_simgos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function importRecord(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_riwayat_simgos (nopen, no_rkm_medis, nik, nm_pasien, tgl_kunjungan, unit_layanan, dokter, diagnosa_icd10, catatan_klinis, status_satusehat, created_at)
             VALUES (:np, :rm, :nik, :nm, :tgl, :ul, :dk, :dg, :ck, :ss, :ca)'
        );
        $stmt->execute([
            ':np' => trim((string) ($data['nopen'] ?? '')),
            ':rm' => trim((string) ($data['no_rkm_medis'] ?? '')),
            ':nik' => trim((string) ($data['nik'] ?? '')),
            ':nm' => trim((string) ($data['nm_pasien'] ?? '')),
            ':tgl' => trim((string) ($data['tgl_kunjungan'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            ':ul' => trim((string) ($data['unit_layanan'] ?? '')),
            ':dk' => trim((string) ($data['dokter'] ?? '')),
            ':dg' => trim((string) ($data['diagnosa_icd10'] ?? '')),
            ':ck' => trim((string) ($data['catatan_klinis'] ?? '')),
            ':ss' => trim((string) ($data['status_satusehat'] ?? 'Migrated')) ?: 'Migrated',
            ':ca' => $now,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? [];
    }
}
