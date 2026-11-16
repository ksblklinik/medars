<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Rme\Repository\OdontogramRepositoryInterface;

final readonly class DeleteOdontogramUseCase
{
    public function __construct(
        private OdontogramRepositoryInterface $odontogramRepo,
    ) {
    }

    public function execute(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('ID riwayat odontogram tidak valid.');
        }

        $this->odontogramRepo->deleteById($id);
    }
}
