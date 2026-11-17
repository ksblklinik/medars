<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Rme\Repository\RuangTindakanRepositoryInterface;
use PDO;

final class PdoRuangTindakanRepository implements RuangTindakanRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFollowup(string $from = '', string $to = '', string $search = ''): array
    {
        $sql = 'SELECT * FROM mlite_followup_operatif WHERE 1=1';
        $params = [];
        if ($from !== '' && $to !== '') {
            $sql .= ' AND tgl_tindakan BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' AND (no_rawat LIKE :q OR kd_jenis_prw LIKE :q OR diagnosis LIKE :q OR nm_petugas LIKE :q)';
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
    public function saveFollowup(array $data, string $actorRole = 'medis'): array
    {
        $noRawat = trim((string) ($data['no_rawat'] ?? ''));
        $kdJenisPrw = trim((string) ($data['kd_jenis_prw'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'Menunggu')) ?: 'Menunggu';

        if (strtolower($actorRole) === 'paramedis' && $status === 'Selesai') {
            $status = 'Draft';
        }

        $tglApprove = '';
        if ($status === 'Selesai' && in_array(strtolower($actorRole), ['medis', 'admin', 'dokter'], true)) {
            $tglApprove = date('Y-m-d H:i:s');
        }

        $tglOrder = trim((string) ($data['tgl_order'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $tglTindakan = trim((string) ($data['tgl_tindakan'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $diagnosis = trim((string) ($data['diagnosis'] ?? '-')) ?: '-';
        $laporan = trim((string) ($data['laporan_operasi'] ?? '-')) ?: '-';
        $catatan = trim((string) ($data['catatan'] ?? '-')) ?: '-';
        $kdDokter = trim((string) ($data['kd_dokter'] ?? '-')) ?: '-';
        $p1 = trim((string) ($data['kd_paramedis1'] ?? ''));
        $p2 = trim((string) ($data['kd_paramedis2'] ?? ''));
        $petugas = trim((string) ($data['nm_petugas'] ?? 'Sistem')) ?: 'Sistem';

        $check = $this->pdo->prepare('SELECT id FROM mlite_followup_operatif WHERE no_rawat = :nr AND kd_jenis_prw = :kp LIMIT 1');
        $check->execute([':nr' => $noRawat, ':kp' => $kdJenisPrw]);
        $existingId = $check->fetchColumn();

        if ($existingId !== false) {
            $id = (int) $existingId;
            $stmt = $this->pdo->prepare(
                'UPDATE mlite_followup_operatif
                 SET tgl_order = :to, tgl_tindakan = :tt, diagnosis = :dg, laporan_operasi = :lp, catatan = :ct,
                     kd_dokter = :kd, kd_paramedis1 = :p1, kd_paramedis2 = :p2, nm_petugas = :np, tgl_approve = :ta, status = :st
                 WHERE id = :id'
            );
            $stmt->execute([
                ':to' => $tglOrder, ':tt' => $tglTindakan, ':dg' => $diagnosis, ':lp' => $laporan,
                ':ct' => $catatan, ':kd' => $kdDokter, ':p1' => $p1, ':p2' => $p2,
                ':np' => $petugas, ':ta' => $tglApprove, ':st' => $status, ':id' => $id,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO mlite_followup_operatif
                 (no_rawat, kd_jenis_prw, tgl_order, tgl_tindakan, diagnosis, laporan_operasi, catatan, kd_dokter, kd_paramedis1, kd_paramedis2, nm_petugas, tgl_approve, status)
                 VALUES (:nr, :kp, :to, :tt, :dg, :lp, :ct, :kd, :p1, :p2, :np, :ta, :st)'
            );
            $stmt->execute([
                ':nr' => $noRawat, ':kp' => $kdJenisPrw, ':to' => $tglOrder, ':tt' => $tglTindakan,
                ':dg' => $diagnosis, ':lp' => $laporan, ':ct' => $catatan, ':kd' => $kdDokter,
                ':p1' => $p1, ':p2' => $p2, ':np' => $petugas, ':ta' => $tglApprove, ':st' => $status,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            if ($noRawat !== '' && $kdJenisPrw !== '') {
                $tarifStmt = $this->pdo->prepare('SELECT nm_perawatan, total_byrdr FROM jns_perawatan WHERE kd_jenis_prw = :kp LIMIT 1');
                $tarifStmt->execute([':kp' => $kdJenisPrw]);
                $tarif = $tarifStmt->fetch(PDO::FETCH_ASSOC);
                if ($tarif !== false && (float) ($tarif['total_byrdr'] ?? 0) > 0) {
                    $biaya = (float) $tarif['total_byrdr'];
                    $this->pdo->prepare(
                        "INSERT INTO mlite_billing_ledger (no_rawat, item_code, item_name, category, qty, unit_price, total_amount, claim_type, status, provider_id, created_at)
                         VALUES (:nr, :ic, :in, 'Tindakan', 1, :up, :tot, 'Tunai', 'Pending', :pid, :ca)"
                    )->execute([
                        ':nr' => $noRawat,
                        ':ic' => $kdJenisPrw,
                        ':in' => (string) $tarif['nm_perawatan'],
                        ':up' => $biaya,
                        ':tot' => $biaya,
                        ':pid' => 'RTD|' . $id,
                        ':ca' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        return [
            'id' => $id,
            'no_rawat' => $noRawat,
            'kd_jenis_prw' => $kdJenisPrw,
            'tgl_tindakan' => $tglTindakan,
            'diagnosis' => $diagnosis,
            'status' => $status,
            'tgl_approve' => $tglApprove,
        ];
    }

    public function deleteFollowup(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM mlite_followup_operatif WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mlite_followup_operatif WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
