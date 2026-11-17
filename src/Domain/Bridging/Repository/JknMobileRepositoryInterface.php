<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Repository;

interface JknMobileRepositoryInterface
{
    public function verifyCredentials(string $username, string $password): bool;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function pasienBaru(array $payload): array;

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function ambilAntrean(array $payload): array;

    /** @return array<string, mixed> */
    public function statusAntrean(string $kodepoli, string $kodedokter, string $tanggalperiksa): array;

    /** @return array<string, mixed>|null */
    public function sisaAntrean(string $kodebooking): ?array;

    public function batalAntrean(string $kodebooking, string $keterangan): bool;

    /** @return array<string, mixed>|null */
    public function checkIn(string $kodebooking, int $waktuMs): ?array;

    /** @return array<string, mixed>|null */
    public function ambilAntreanFarmasi(string $kodebooking, string $jenisResep): ?array;

    /** @return array<string, mixed>|null */
    public function statusAntreanFarmasi(string $kodebooking): ?array;

    /** @return list<array<string, mixed>> */
    public function jadwalOperasiRs(string $tanggalAwal, string $tanggalAkhir): array;

    /** @return list<array<string, mixed>> */
    public function jadwalOperasiPasien(string $noKartu): array;

    /** @return array<string, mixed>|null */
    public function sisaAntreanFktp(string $noKartu, string $kodepoli, string $tanggalperiksa): ?array;

    public function batalAntreanFktp(string $noKartu, string $kodepoli, string $tanggalperiksa, string $keterangan): bool;
}
