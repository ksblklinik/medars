<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\DashboardSummaryRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class PdoDashboardSummaryRepository implements DashboardSummaryRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array{tanggal: string, kunjungan: int, pendapatan: float}>
     */
    public function getSevenDayTrends(?string $endDate = null): array
    {
        $end = new DateTimeImmutable($endDate ?: date('Y-m-d'));
        $start = $end->modify('-6 days');
        $startStr = $start->format('Y-m-d');
        $endStr = $end->format('Y-m-d');

        $visitsMap = [];
        $vStmt = $this->pdo->prepare(
            'SELECT tgl_registrasi, COUNT(*) AS total
             FROM reg_periksa
             WHERE tgl_registrasi BETWEEN :start AND :end
             GROUP BY tgl_registrasi'
        );
        $vStmt->execute([':start' => $startStr, ':end' => $endStr]);
        foreach ($vStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $visitsMap[(string) $row['tgl_registrasi']] = (int) $row['total'];
        }

        $revMap = [];
        $rStmt = $this->pdo->prepare(
            "SELECT SUBSTR(created_at, 1, 10) AS tgl, COALESCE(SUM(total_amount), 0) AS total
             FROM mlite_billing_ledger
             WHERE SUBSTR(created_at, 1, 10) BETWEEN :start AND :end
               AND status IN ('Paid', 'Lunas', 'Settled')
             GROUP BY SUBSTR(created_at, 1, 10)"
        );
        $rStmt->execute([':start' => $startStr, ':end' => $endStr]);
        foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $revMap[(string) $row['tgl']] = (float) $row['total'];
        }

        $trends = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->modify("+{$i} days")->format('Y-m-d');
            $trends[] = [
                'tanggal' => $d,
                'kunjungan' => $visitsMap[$d] ?? 0,
                'pendapatan' => $revMap[$d] ?? 0.0,
            ];
        }
        return $trends;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listAnnouncements(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, judul, isi, prioritas, penulis, is_active, created_at
             FROM mlite_pengumuman
             WHERE is_active = 1
             ORDER BY id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $row['id'] = (int) $row['id'];
            $row['is_active'] = (int) $row['is_active'] === 1;
            return $row;
        }, $rows ?: []);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createAnnouncement(array $data): array
    {
        $judul = trim((string) ($data['judul'] ?? ''));
        $isi = trim((string) ($data['isi'] ?? ''));
        $prioritas = trim((string) ($data['prioritas'] ?? 'Info')) ?: 'Info';
        $penulis = trim((string) ($data['penulis'] ?? 'Admin')) ?: 'Admin';
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_pengumuman (judul, isi, prioritas, penulis, is_active, created_at)
             VALUES (:judul, :isi, :prioritas, :penulis, 1, :created_at)'
        );
        $stmt->execute([
            ':judul' => $judul,
            ':isi' => $isi,
            ':prioritas' => $prioritas,
            ':penulis' => $penulis,
            ':created_at' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'judul' => $judul,
            'isi' => $isi,
            'prioritas' => $prioritas,
            'penulis' => $penulis,
            'is_active' => true,
            'created_at' => $now,
        ];
    }

    public function deleteAnnouncement(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM mlite_pengumuman WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
