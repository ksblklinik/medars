<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\HfisAntrolRepositoryInterface;
use PDO;

final class PdoHfisAntrolRepository implements HfisAntrolRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listJadwalHfis(string $search = ''): array
    {
        $sql = 'SELECT * FROM mlite_hfis_jadwal';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE kd_poli_bpjs LIKE :q OR nm_poli_bpjs LIKE :q OR kd_dokter_bpjs LIKE :q OR nm_dokter_bpjs LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY hari ASC, id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveJadwalHfis(array $data): array
    {
        $kdPoli = trim((string) ($data['kd_poli_bpjs'] ?? ''));
        $nmPoli = trim((string) ($data['nm_poli_bpjs'] ?? ''));
        $kdDokter = trim((string) ($data['kd_dokter_bpjs'] ?? ''));
        $nmDokter = trim((string) ($data['nm_dokter_bpjs'] ?? ''));
        $hari = (int) ($data['hari'] ?? 1);
        $namaHari = strtoupper(trim((string) ($data['nama_hari'] ?? 'SENIN'))) ?: 'SENIN';
        $jadwal = trim((string) ($data['jadwal'] ?? '08:00-12:00')) ?: '08:00-12:00';
        $kapasitas = (int) ($data['kapasitas_pasien'] ?? 20);
        $libur = (int) ($data['libur'] ?? 0);
        $syncedAt = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_hfis_jadwal (kd_poli_bpjs, nm_poli_bpjs, kd_dokter_bpjs, nm_dokter_bpjs, hari, nama_hari, jadwal, kapasitas_pasien, libur, synced_at)
             VALUES (:kp, :nmp, :kd, :nmd, :hr, :nh, :jd, :kap, :lb, :sa)'
        );
        $stmt->execute([
            ':kp' => $kdPoli,
            ':nmp' => $nmPoli,
            ':kd' => $kdDokter,
            ':nmd' => $nmDokter,
            ':hr' => $hari,
            ':nh' => $namaHari,
            ':jd' => $jadwal,
            ':kap' => $kapasitas,
            ':lb' => $libur,
            ':sa' => $syncedAt,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'kd_poli_bpjs' => $kdPoli,
            'nm_poli_bpjs' => $nmPoli,
            'kd_dokter_bpjs' => $kdDokter,
            'nm_dokter_bpjs' => $nmDokter,
            'hari' => $hari,
            'nama_hari' => $namaHari,
            'jadwal' => $jadwal,
            'kapasitas_pasien' => $kapasitas,
            'synced_at' => $syncedAt,
        ];
    }

    public function listAntrolTasks(string $search = ''): array
    {
        $sql = 'SELECT * FROM mlite_antrol_log';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE kodebooking LIKE :q OR no_rawat LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function pushAntrolTask(array $data): array
    {
        $kodebooking = trim((string) ($data['kodebooking'] ?? ''));
        $noRawat = trim((string) ($data['no_rawat'] ?? ''));
        $taskId = (int) ($data['task_id'] ?? 1);
        $waktuMs = (int) ($data['waktu_ms'] ?? (int) round(microtime(true) * 1000));
        $statusSync = trim((string) ($data['status_sync'] ?? 'sent')) ?: 'sent';
        $code = trim((string) ($data['response_code'] ?? '200')) ?: '200';
        $msg = trim((string) ($data['response_msg'] ?? 'Ok')) ?: 'Ok';
        $createdAt = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_antrol_log (kodebooking, no_rawat, task_id, waktu_ms, status_sync, response_code, response_msg, created_at)
             VALUES (:kb, :nr, :tid, :wm, :st, :rc, :rm, :ca)'
        );
        $stmt->execute([
            ':kb' => $kodebooking,
            ':nr' => $noRawat,
            ':tid' => $taskId,
            ':wm' => $waktuMs,
            ':st' => $statusSync,
            ':rc' => $code,
            ':rm' => $msg,
            ':ca' => $createdAt,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'kodebooking' => $kodebooking,
            'no_rawat' => $noRawat,
            'task_id' => $taskId,
            'waktu_ms' => $waktuMs,
            'status_sync' => $statusSync,
            'response_code' => $code,
            'response_msg' => $msg,
        ];
    }
}
