<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Rme\Entity\Odontogram;
use App\Domain\Rme\Repository\OdontogramRepositoryInterface;

final readonly class SaveOdontogramUseCase
{
    public function __construct(
        private OdontogramRepositoryInterface $odontogramRepo,
    ) {
    }

    public function execute(Odontogram $odontogram): void
    {
        $this->odontogramRepo->save($odontogram);
    }
}
