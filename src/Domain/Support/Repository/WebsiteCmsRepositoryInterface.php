<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

interface WebsiteCmsRepositoryInterface
{
    /** @return list<array<string, mixed>> */
    public function listPages(bool $publishedOnly = false): array;

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array;

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function savePage(array $data): array;
}
