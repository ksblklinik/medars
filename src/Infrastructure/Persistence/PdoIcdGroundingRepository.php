<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\DiagnosaPasien;
use App\Domain\Rme\Repository\IcdGroundingRepositoryInterface;
use PDO;

final class PdoIcdGroundingRepository implements IcdGroundingRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function searchSatuSehatIcd10(string $keyword, int $limit = 20): array
    {
        $like = '%' . trim($keyword) . '%';
        $stmt = $this->pdo->prepare("
            SELECT kd_penyakit, nm_penyakit, nm_indonesia, status, skdi_level
            FROM penyakit
            WHERE kd_penyakit LIKE :q1
               OR nm_penyakit LIKE :q2
               OR nm_indonesia LIKE :q3
            ORDER BY kd_penyakit ASC
            LIMIT :lim
        ");
        $stmt->bindValue(':q1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':q3', $like, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $skdi = (string) ($row['skdi_level'] ?? '4A');
            $row['referral_suggestion'] = $this->buildReferralSuggestion($skdi);
        }

        return $rows;
    }

    public function validateCode(string $kdPenyakit): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT kd_penyakit, nm_penyakit, nm_indonesia, status, skdi_level
            FROM penyakit
            WHERE kd_penyakit = :kd
            LIMIT 1
        ");
        $stmt->execute([':kd' => trim($kdPenyakit)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $skdi = (string) ($row['skdi_level'] ?? '4A');
        $row['referral_suggestion'] = $this->buildReferralSuggestion($skdi);
        return $row;
    }

    public function saveDiagnosa(DiagnosaPasien $dx): void
    {
        $check = $this->pdo->prepare("
            SELECT 1 FROM diagnosa_pasien
            WHERE no_rawat = :nr AND kd_penyakit = :kd AND status = :stts
            LIMIT 1
        ");
        $check->execute([
            ':nr' => $dx->noRawat->value(),
            ':kd' => $dx->kdPenyakit,
            ':stts' => $dx->status,
        ]);

        if ($check->fetchColumn()) {
            $sql = "
                UPDATE diagnosa_pasien
                SET prioritas = :prio, status_penyakit = :sp
                WHERE no_rawat = :nr AND kd_penyakit = :kd AND status = :stts
            ";
        } else {
            $sql = "
                INSERT INTO diagnosa_pasien (no_rawat, kd_penyakit, status, prioritas, status_penyakit)
                VALUES (:nr, :kd, :stts, :prio, :sp)
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nr' => $dx->noRawat->value(),
            ':kd' => $dx->kdPenyakit,
            ':stts' => $dx->status,
            ':prio' => $dx->prioritas,
            ':sp' => $dx->statusPenyakit,
        ]);
    }

    public function findDiagnosaByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM diagnosa_pasien
            WHERE no_rawat = :nr
            ORDER BY prioritas ASC
        ");
        $stmt->execute([':nr' => $noRawat->value()]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new DiagnosaPasien(
                noRawat: new NoRawat((string) $row['no_rawat']),
                kdPenyakit: (string) $row['kd_penyakit'],
                status: (string) $row['status'],
                prioritas: (int) $row['prioritas'],
                statusPenyakit: (string) ($row['status_penyakit'] ?? 'Baru')
            );
        }

        return $results;
    }

    private function buildReferralSuggestion(string $skdiLevel): string
    {
        $level = strtoupper(trim($skdiLevel));
        if ($level === '4A' || $level === '') {
            return '';
        }

        return "Kompetensi SKDI {$level}: Lakukan stabilisasi pra-rujukan dan pertimbangkan rujukan ke FKRTL/Spesialis sesuai indikasi klinis.";
    }
}
