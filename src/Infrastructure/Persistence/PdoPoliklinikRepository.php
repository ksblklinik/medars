<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\Entity\PoliklinikSetting;
use App\Domain\Registration\Repository\PoliklinikRepositoryInterface;

final readonly class PdoPoliklinikRepository implements PoliklinikRepositoryInterface
{
    public function __construct(
        private \PDO $pdo
    ) {}

    public function findByKdPoli(string $kdPoli): ?PoliklinikSetting
    {
        $sql = 'SELECT * FROM poliklinik WHERE kd_poli = :kd_poli LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['kd_poli' => $kdPoli]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @return array<PoliklinikSetting>
     */
    public function findAllActive(): array
    {
        $sql = "SELECT * FROM poliklinik WHERE status = '1' ORDER BY nm_poli ASC";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn(array $row) => $this->hydrate($row), $rows);
    }

    public function save(PoliklinikSetting $setting): void
    {
        $sql = "INSERT INTO poliklinik (kd_poli, nm_poli, registrasi, registrasilama, status, allow_bpjs)
                VALUES (:kd_poli, :nm_poli, :registrasi, :registrasilama, '1', :allow_bpjs)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'kd_poli' => $setting->kdPoli,
            'nm_poli' => $setting->nmPoli,
            'registrasi' => $setting->registrasiBaru,
            'registrasilama' => $setting->registrasiLama,
            'allow_bpjs' => $setting->allowBpjs ? 1 : 0,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): PoliklinikSetting
    {
        // allow_bpjs default true jika kolom belum ada di schema legacy
        $allowBpjs = true;
        if (isset($row['allow_bpjs'])) {
            $allowBpjs = (bool) $row['allow_bpjs'];
        }

        return new PoliklinikSetting(
            kdPoli: (string) $row['kd_poli'],
            nmPoli: (string) $row['nm_poli'],
            registrasiBaru: (float) ($row['registrasi'] ?? 0.0),
            registrasiLama: (float) ($row['registrasilama'] ?? 0.0),
            allowBpjs: $allowBpjs
        );
    }
}
