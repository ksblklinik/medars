<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Communication\Repository\HomeCareCarePlanRepositoryInterface;
use PDO;

final class PdoHomeCareCarePlanRepository implements HomeCareCarePlanRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listCarePlans(string $noRawat = ''): array
    {
        $sql = 'SELECT * FROM mlite_homecare_careplan';
        $params = [];
        if (trim($noRawat) !== '') {
            $sql .= ' WHERE no_rawat = :nr';
            $params[':nr'] = trim($noRawat);
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveCarePlan(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "INSERT INTO mlite_homecare_careplan (kunjungan_id, no_rawat, care_plan, dpjp_signed, dpjp_signed_by, dpjp_signed_at, nominal_tagihan, payment_ref, payment_gateway, status_bayar, created_at)
             VALUES (:kid, :nr, :cp, 0, '', '', :nom, '', 'Kasera', 'Belum', :ca)"
        );
        $stmt->execute([
            ':kid' => (int) ($data['kunjungan_id'] ?? 0),
            ':nr' => trim((string) ($data['no_rawat'] ?? '')),
            ':cp' => trim((string) ($data['care_plan'] ?? '')),
            ':nom' => (float) ($data['nominal_tagihan'] ?? 0),
            ':ca' => $now,
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? [];
    }

    public function signOffDpjp(int $id, string $dpjpUsername): ?array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE mlite_homecare_careplan SET dpjp_signed = 1, dpjp_signed_by = :by, dpjp_signed_at = :at WHERE id = :id'
        );
        $stmt->execute([':by' => trim($dpjpUsername) ?: 'dpjp', ':at' => $now, ':id' => $id]);
        if ($stmt->rowCount() === 0) {
            return null;
        }
        return $this->findById($id);
    }

    public function payCarePlan(int $id, string $gateway, string $paymentRef = ''): ?array
    {
        $row = $this->findById($id);
        if ($row === null) {
            return null;
        }
        $gw = trim($gateway) !== '' ? trim($gateway) : 'Kasera';
        $ref = trim($paymentRef) !== '' ? trim($paymentRef) : (strtoupper(substr($gw, 0, 3)) . '-' . date('YmdHis'));

        $stmt = $this->pdo->prepare(
            "UPDATE mlite_homecare_careplan SET payment_gateway = :gw, payment_ref = :ref, status_bayar = 'Lunas' WHERE id = :id"
        );
        $stmt->execute([':gw' => $gw, ':ref' => $ref, ':id' => $id]);

        $updKunj = $this->pdo->prepare(
            "UPDATE mlite_homecare_kunjungan SET status_bayar = 'Lunas', payment_gateway = :gw WHERE no_rawat = :nr"
        );
        $updKunj->execute([':gw' => $gw, ':nr' => (string) $row['no_rawat']]);

        $nominal = (float) ($row['nominal_tagihan'] ?? 0);
        if ($nominal > 0) {
            $noBukti = 'HC-' . $id;
            $chkJ = $this->pdo->prepare('SELECT COUNT(*) FROM mlite_jurnal WHERE no_bukti = :nb');
            $chkJ->execute([':nb' => $noBukti]);
            if ((int) $chkJ->fetchColumn() === 0) {
                $noJurnal = 'JRN-' . date('YmdHis') . '-' . $id;
                $this->pdo->prepare(
                    'INSERT INTO mlite_jurnal (no_jurnal, tgl_jurnal, jam_jurnal, no_bukti, keterangan) VALUES (:nj, :tg, :jm, :nb, :ket)'
                )->execute([
                    ':nj' => $noJurnal,
                    ':tg' => date('Y-m-d'),
                    ':jm' => date('H:i:s'),
                    ':nb' => $noBukti,
                    ':ket' => "Pembayaran Homecare {$row['no_rawat']} ({$gw})",
                ]);
                $detStmt = $this->pdo->prepare('INSERT INTO mlite_jurnal_detail (no_jurnal, kd_rek, debet, kredit) VALUES (:nj, :kr, :db, :kd)');
                $detStmt->execute([':nj' => $noJurnal, ':kr' => '1101.01', ':db' => $nominal, ':kd' => 0]);
                $detStmt->execute([':nj' => $noJurnal, ':kr' => '4101.01', ':db' => 0, ':kd' => $nominal]);
            }
        }

        return $this->findById($id);
    }

    /** @return array<string, mixed>|null */
    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM mlite_homecare_careplan WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
