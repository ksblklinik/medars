<?php

declare(strict_types=1);

namespace App\Domain\Support\Repository;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Support\Entity\DetailPeriksaLab;
use App\Domain\Support\Entity\HasilRadiologi;
use App\Domain\Support\Entity\PermintaanLab;
use App\Domain\Support\Entity\PermintaanPemeriksaanLab;
use App\Domain\Support\Entity\PermintaanRadiologi;

interface LabRadiologyRepositoryInterface
{
    public function getNextOrderNumber(string $prefix, string $dateYmd): string;

    public function saveLabOrder(PermintaanLab $order): void;

    public function saveLabItem(PermintaanPemeriksaanLab $item): void;

    public function findLabOrder(string $noOrder): ?PermintaanLab;

    /**
     * @return list<PermintaanPemeriksaanLab>
     */
    public function findLabItems(string $noOrder): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function listLabOrdersByDate(string $fromDate, string $toDate): array;

    /**
     * @return list<DetailPeriksaLab>
     */
    public function findLabResultsByNoRawat(NoRawat $noRawat): array;

    public function saveLabResult(DetailPeriksaLab $detail): void;

    public function markLabSampledAndResulted(string $noOrder, string $tgl, string $jam): void;

    public function deleteLabOrder(string $noOrder): void;

    public function saveRadiologiOrder(PermintaanRadiologi $order): void;

    public function saveRadiologiItem(string $noOrder, string $kdJenisPrw, string $jenisKlaim): void;

    public function findRadiologiOrder(string $noOrder): ?PermintaanRadiologi;

    /**
     * @return list<array<string, mixed>>
     */
    public function listRadiologiOrdersByDate(string $fromDate, string $toDate): array;

    public function saveHasilRadiologi(HasilRadiologi $hasil): void;

    /**
     * @return list<HasilRadiologi>
     */
    public function findRadiologiResultsByNoRawat(NoRawat $noRawat): array;

    public function markRadiologiResulted(string $noOrder, string $tgl, string $jam): void;

    public function deleteRadiologiOrder(string $noOrder): void;
}
