<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Setting\Repository\SettingRepositoryInterface;
use PDO;

final class PdoSettingRepository implements SettingRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function get(string $module, string $field): ?string
    {
        $stmt = $this->pdo->prepare("SELECT value FROM mlite_settings WHERE module = :mod AND field = :field LIMIT 1");
        $stmt->execute([
            ':mod' => $module,
            ':field' => $field,
        ]);

        $val = $stmt->fetchColumn();
        return $val !== false ? (string) $val : null;
    }

    public function getAll(string $module): array
    {
        $stmt = $this->pdo->prepare("SELECT field, value FROM mlite_settings WHERE module = :mod");
        $stmt->execute([':mod' => $module]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[$row['field']] = (string) $row['value'];
        }

        return $results;
    }

    public function set(string $module, string $field, string $value): void
    {
        $existing = $this->get($module, $field);

        if ($existing !== null) {
            $sql = "UPDATE mlite_settings SET value = :val WHERE module = :mod AND field = :field";
        } else {
            $sql = "INSERT INTO mlite_settings (module, field, value) VALUES (:mod, :field, :val)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':mod' => $module,
            ':field' => $field,
            ':val' => $value,
        ]);
    }
}
