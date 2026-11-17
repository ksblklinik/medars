<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\OrthancRepositoryInterface;
use PDO;

final class PdoOrthancRepository implements OrthancRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listStudies(string $search = ''): array
    {
        $sql = 'SELECT * FROM bridging_orthanc';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE no_rawat LIKE :q OR study_uid LIKE :q OR study_description LIKE :q OR patient_name LIKE :q OR modality LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveStudy(array $data): array
    {
        $noRawat = trim((string) ($data['no_rawat'] ?? ''));
        $studyUid = trim((string) ($data['study_uid'] ?? ''));
        $seriesUid = trim((string) ($data['series_uid'] ?? ''));
        $modality = trim((string) ($data['modality'] ?? 'CR')) ?: 'CR';
        $desc = trim((string) ($data['study_description'] ?? ''));
        $patient = trim((string) ($data['patient_name'] ?? ''));
        $tgl = trim((string) ($data['tgl_periksa'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $hasil = trim((string) ($data['hasil_baca'] ?? ''));
        $dokter = trim((string) ($data['dokter_radiologi'] ?? ''));
        $viewer = trim((string) ($data['viewer_url'] ?? ''));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO bridging_orthanc (no_rawat, study_uid, series_uid, modality, study_description, patient_name, tgl_periksa, hasil_baca, dokter_radiologi, viewer_url, created_at)
             VALUES (:nr, :su, :sr, :md, :sd, :pn, :tp, :hb, :dr, :vu, :ca)'
        );
        $stmt->execute([
            ':nr' => $noRawat, ':su' => $studyUid, ':sr' => $seriesUid, ':md' => $modality,
            ':sd' => $desc, ':pn' => $patient, ':tp' => $tgl, ':hb' => $hasil,
            ':dr' => $dokter, ':vu' => $viewer, ':ca' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'no_rawat' => $noRawat,
            'study_uid' => $studyUid,
            'modality' => $modality,
            'study_description' => $desc,
            'patient_name' => $patient,
            'tgl_periksa' => $tgl,
            'viewer_url' => $viewer,
        ];
    }

    public function updateHasilBaca(int $id, string $hasilBaca, string $dokterRadiologi): bool
    {
        $stmt = $this->pdo->prepare('UPDATE bridging_orthanc SET hasil_baca = :hb, dokter_radiologi = :dr WHERE id = :id');
        $stmt->execute([':hb' => trim($hasilBaca), ':dr' => trim($dokterRadiologi), ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteStudy(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM bridging_orthanc WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
