<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Queue\Repository\AnjunganQueueRepositoryInterface;
use PDO;

final class PdoAnjunganQueueRepository implements AnjunganQueueRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function issueTicket(
        string $type,
        string $noRawat = '',
        string $noRkmMedis = '',
        string $nmPasien = '',
        string $namaLoket = ''
    ): array {
        $normType = ucfirst(strtolower(trim($type))) ?: 'Poli';
        $today = date('Y-m-d');
        $prefix = match ($normType) {
            'Farmasi' => 'F',
            'Kasir' => 'K',
            default => 'P',
        };

        $cntStmt = $this->pdo->prepare('SELECT COUNT(*) FROM mlite_antrian_loket WHERE type = :t AND postdate = :d');
        $cntStmt->execute([':t' => $normType, ':d' => $today]);
        $seq = (int) $cntStmt->fetchColumn() + 1;
        $noantrian = $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
        $nowTime = date('H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_antrian_loket (type, noantrian, no_rawat, no_rkm_medis, nm_pasien, nama_loket, postdate, start_time, end_time, status)
             VALUES (:t, :na, :nr, :rm, :np, :nl, :pd, :st, \'\', 0)'
        );
        $stmt->execute([
            ':t' => $normType,
            ':na' => $noantrian,
            ':nr' => trim($noRawat),
            ':rm' => trim($noRkmMedis),
            ':np' => trim($nmPasien),
            ':nl' => trim($namaLoket) ?: ($normType . ' 1'),
            ':pd' => $today,
            ':st' => $nowTime,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'type' => $normType,
            'noantrian' => $noantrian,
            'no_rawat' => trim($noRawat),
            'no_rkm_medis' => trim($noRkmMedis),
            'nm_pasien' => trim($nmPasien),
            'nama_loket' => trim($namaLoket) ?: ($normType . ' 1'),
            'postdate' => $today,
            'start_time' => $nowTime,
            'status' => 0,
        ];
    }

    public function listByType(string $type, string $date = ''): array
    {
        $normType = ucfirst(strtolower(trim($type))) ?: 'Poli';
        $d = trim($date) !== '' ? trim($date) : date('Y-m-d');
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mlite_antrian_loket WHERE type = :t AND postdate = :d ORDER BY id ASC LIMIT 100'
        );
        $stmt->execute([':t' => $normType, ':d' => $d]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function callNext(string $type, string $namaLoket = ''): ?array
    {
        $normType = ucfirst(strtolower(trim($type))) ?: 'Poli';
        $today = date('Y-m-d');
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mlite_antrian_loket WHERE type = :t AND postdate = :d AND status = 0 ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([':t' => $normType, ':d' => $today]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $loket = trim($namaLoket) !== '' ? trim($namaLoket) : (string) ($row['nama_loket'] ?: $normType . ' 1');
        $upd = $this->pdo->prepare(
            'UPDATE mlite_antrian_loket SET status = 1, nama_loket = :nl, end_time = :et WHERE id = :id'
        );
        $upd->execute([':nl' => $loket, ':et' => date('H:i:s'), ':id' => (int) $row['id']]);
        $row['status'] = 1;
        $row['nama_loket'] = $loket;
        return $row;
    }

    public function findTicket(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mlite_antrian_loket WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
