<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Master\Entity\JadwalPraktik;
use App\Domain\Master\Repository\JadwalRepositoryInterface;
use App\Domain\Master\Service\ScheduleOverlapGuard;
use PDO;

final class PdoJadwalRepository implements JadwalRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ScheduleOverlapGuard $overlapGuard,
    ) {
    }

    public function findByDokterAndHari(string $kdDokter, string $hariKerja): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM jadwal
            WHERE kd_dokter = :kd AND UPPER(hari_kerja) = :hari
            ORDER BY jam_mulai ASC
        ");
        $stmt->execute([
            ':kd' => $kdDokter,
            ':hari' => strtoupper($hariKerja),
        ]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findAllByHari(string $hariKerja): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM jadwal
            WHERE UPPER(hari_kerja) = :hari
            ORDER BY kd_poli ASC, jam_mulai ASC
        ");
        $stmt->execute([':hari' => strtoupper($hariKerja)]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function save(JadwalPraktik $jadwal): void
    {
        // 1. Ambil jadwal dokter ini pada hari yang sama untuk pengecekan tabrakan
        $existing = $this->findByDokterAndHari($jadwal->getKdDokter(), $jadwal->getHariKerja());

        // 2. Validasi Anti-Overlap
        $this->overlapGuard->assertNoOverlap($existing, $jadwal);

        // 3. Simpan ke database (Portabel SQLite & MySQL 8.0)
        $checkStmt = $this->pdo->prepare("
            SELECT 1 FROM jadwal
            WHERE kd_dokter = :kd_dokter
              AND kd_poli = :kd_poli
              AND UPPER(hari_kerja) = :hari_kerja
              AND jam_mulai = :jam_mulai
            LIMIT 1
        ");
        $checkStmt->execute([
            ':kd_dokter' => $jadwal->getKdDokter(),
            ':kd_poli' => $jadwal->getKdPoli(),
            ':hari_kerja' => $jadwal->getHariKerja(),
            ':jam_mulai' => $jadwal->getJamMulai(),
        ]);

        if ($checkStmt->fetchColumn()) {
            $sql = "
                UPDATE jadwal
                SET jam_selesai = :jam_selesai,
                    kuota = :kuota,
                    prefik = :prefik,
                    room = :room
                WHERE kd_dokter = :kd_dokter
                  AND kd_poli = :kd_poli
                  AND UPPER(hari_kerja) = :hari_kerja
                  AND jam_mulai = :jam_mulai
            ";
        } else {
            $sql = "
                INSERT INTO jadwal (kd_dokter, kd_poli, hari_kerja, jam_mulai, jam_selesai, kuota, prefik, room)
                VALUES (:kd_dokter, :kd_poli, :hari_kerja, :jam_mulai, :jam_selesai, :kuota, :prefik, :room)
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':kd_dokter' => $jadwal->getKdDokter(),
            ':kd_poli' => $jadwal->getKdPoli(),
            ':hari_kerja' => $jadwal->getHariKerja(),
            ':jam_mulai' => $jadwal->getJamMulai(),
            ':jam_selesai' => $jadwal->getJamSelesai(),
            ':kuota' => $jadwal->getKuota(),
            ':prefik' => $jadwal->getPrefik(),
            ':room' => $jadwal->getRoom(),
        ]);
    }

    public function delete(string $kdDokter, string $kdPoli, string $hariKerja, string $jamMulai): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM jadwal
            WHERE kd_dokter = :kd_dokter
              AND kd_poli = :kd_poli
              AND UPPER(hari_kerja) = :hari
              AND jam_mulai = :jam_mulai
        ");
        $stmt->execute([
            ':kd_dokter' => $kdDokter,
            ':kd_poli' => $kdPoli,
            ':hari' => strtoupper($hariKerja),
            ':jam_mulai' => $jamMulai,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): JadwalPraktik
    {
        return new JadwalPraktik(
            kdDokter: (string) $row['kd_dokter'],
            kdPoli: (string) $row['kd_poli'],
            hariKerja: (string) $row['hari_kerja'],
            jamMulai: (string) $row['jam_mulai'],
            jamSelesai: (string) $row['jam_selesai'],
            kuota: (int) ($row['kuota'] ?? 0),
            prefik: (string) ($row['prefik'] ?? ''),
            room: (string) ($row['room'] ?? '')
        );
    }
}
