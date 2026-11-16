<?php

declare(strict_types=1);

namespace App\Domain\Billing\Repository;

use App\Application\Rme\UseCase\RanapStatusCheckerInterface;
use App\Domain\Billing\Entity\KamarInap;
use App\Domain\Registration\ValueObject\NoRawat;

interface KamarInapRepositoryInterface extends RanapStatusCheckerInterface
{
    /**
     * @return list<KamarInap>
     */
    public function findByNoRawat(NoRawat $noRawat): array;

    public function save(KamarInap $kamarInap): void;
}
