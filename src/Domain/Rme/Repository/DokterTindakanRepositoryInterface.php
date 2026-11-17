<?php

declare(strict_types=1);

namespace App\Domain\Rme\Repository;

use App\Domain\Registration\ValueObject\NoRawat;

interface DokterTindakanRepositoryInterface
{
    /**
     * @return list<array<string, mixed>>
     */
    public function searchTarifTindakan(string $query = '', int $limit = 20): array;

    /**
     * @return array<string, mixed>|null
     */
    public function findTarifByKode(string $kdJenisPrw): ?array;

    public function saveTindakan(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $tglPerawatan,
        string $jamRawat,
        float $material,
        float $tarifDokter,
        float $biayaRawat,
        string $jenisKlaim,
    ): void;

    /**
     * @return list<array<string, mixed>>
     */
    public function listByNoRawat(NoRawat $noRawat): array;

    public function findTindakanRow(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $tglPerawatan,
        string $jamRawat,
    ): ?array;

    public function deleteTindakan(
        NoRawat $noRawat,
        string $kdJenisPrw,
        string $kdDokter,
        string $tglPerawatan,
        string $jamRawat,
    ): void;

    /**
     * @param array<string, mixed> $data
     */
    public function saveResume(array $data): void;

    /**
     * @return array<string, mixed>|null
     */
    public function findResume(NoRawat $noRawat): ?array;
}
