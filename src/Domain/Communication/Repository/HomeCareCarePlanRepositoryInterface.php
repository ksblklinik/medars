<?php

declare(strict_types=1);

namespace App\Domain\Communication\Repository;

interface HomeCareCarePlanRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listCarePlans(string $noRawat = ''): array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function saveCarePlan(array $data): array;

    /** @return array<string, mixed>|null */
    public function signOffDpjp(int $id, string $dpjpUsername): ?array;

    /** @return array<string, mixed>|null */
    public function payCarePlan(int $id, string $gateway, string $paymentRef = ''): ?array;
}
