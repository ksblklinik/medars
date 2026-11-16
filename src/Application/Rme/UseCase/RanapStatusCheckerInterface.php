<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Registration\ValueObject\NoRawat;

interface RanapStatusCheckerInterface
{
    public function isPatientStillAdmitted(NoRawat $noRawat): bool;
}
