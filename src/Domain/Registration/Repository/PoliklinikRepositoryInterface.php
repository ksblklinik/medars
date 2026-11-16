<?php

declare(strict_types=1);

namespace App\Domain\Registration\Repository;

use App\Domain\Registration\Entity\PoliklinikSetting;

interface PoliklinikRepositoryInterface
{
    public function findByKdPoli(string $kdPoli): ?PoliklinikSetting;

    /**
     * @return array<PoliklinikSetting>
     */
    public function findAllActive(): array;

    public function save(PoliklinikSetting $setting): void;
}
