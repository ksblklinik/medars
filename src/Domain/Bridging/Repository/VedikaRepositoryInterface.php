<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

use App\Domain\Registration\ValueObject\NoRawat;

interface VedikaRepositoryInterface
{
    public function registrationExists(NoRawat $noRawat): bool;

    public function countSoapRalan(NoRawat $noRawat): int;

    public function countDiagnosa(NoRawat $noRawat): int;

    public function saveVerification(
        NoRawat $noRawat,
        string $noSep,
        string $jenisPelayanan,
        string $status,
        string $catatan,
        string $verifiedBy,
        string $verifiedAt,
    ): void;
}
