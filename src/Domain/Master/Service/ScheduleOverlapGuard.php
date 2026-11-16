<?php

declare(strict_types=1);

namespace App\Domain\Master\Service;

use App\Domain\Master\Entity\JadwalPraktik;
use App\Domain\Master\Exception\ScheduleOverlapException;

final class ScheduleOverlapGuard
{
    /**
     * @param list<JadwalPraktik> $existingSchedules
     */
    public function assertNoOverlap(array $existingSchedules, JadwalPraktik $newSchedule): void
    {
        $newStart = $this->normalizeTime($newSchedule->getJamMulai());
        $newEnd = $this->normalizeTime($newSchedule->getJamSelesai());
        $newHari = $newSchedule->getHariKerja();
        $dokter = $newSchedule->getKdDokter();

        foreach ($existingSchedules as $existing) {
            if ($existing->getHariKerja() !== $newHari) {
                continue;
            }

            // Jika poli dan jam mulai sama persis, mungkin sedang update
            if ($existing->getKdPoli() === $newSchedule->getKdPoli() &&
                $this->normalizeTime($existing->getJamMulai()) === $newStart) {
                continue;
            }

            $existingStart = $this->normalizeTime($existing->getJamMulai());
            $existingEnd = $this->normalizeTime($existing->getJamSelesai());

            // Formula interval collision: (startA < endB) && (endA > startB)
            if ($newStart < $existingEnd && $newEnd > $existingStart) {
                throw new ScheduleOverlapException(
                    "Jadwal dokter {$dokter} pada hari {$newHari} bertabrakan dengan jadwal yang sudah ada " .
                    "({$existing->getJamMulai()} - {$existing->getJamSelesai()} di Poli {$existing->getKdPoli()})."
                );
            }
        }
    }

    private function normalizeTime(string $time): string
    {
        $parts = explode(':', trim($time));
        $h = str_pad($parts[0] ?? '00', 2, '0', STR_PAD_LEFT);
        $m = str_pad($parts[1] ?? '00', 2, '0', STR_PAD_LEFT);
        $s = str_pad($parts[2] ?? '00', 2, '0', STR_PAD_LEFT);

        return "{$h}:{$m}:{$s}";
    }
}
