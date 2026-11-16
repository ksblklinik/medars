<?php

declare(strict_types=1);

namespace App\Domain\Master\Repository;

use App\Domain\Master\Entity\JenisPerawatan;

interface JenisPerawatanRepositoryInterface
{
    /**
     * @return list<JenisPerawatan>
     */
    public function findAllActive(): array;

    public function findByKode(string $kdJenisPrw): ?JenisPerawatan;

    public function save(JenisPerawatan $jenisPerawatan): void;
}
