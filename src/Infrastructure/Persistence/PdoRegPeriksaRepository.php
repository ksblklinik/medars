<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoReg;
use App\Domain\Registration\ValueObject\NoRkmMedis;

final readonly class PdoRegPeriksaRepository implements RegPeriksaRepositoryInterface
{
    public function __construct(
        private \PDO $pdo
    ) {}

    public function findByNoRawat(NoRawat $noRawat): ?RegPeriksa
    {
        $sql = 'SELECT * FROM reg_periksa WHERE no_rawat = :no_rawat LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['no_rawat' => $noRawat->value()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function hasActiveRegistrationToday(
        NoRkmMedis $noRkmMedis,
        string $kdPoli,
        string $kdDokter,
        \DateTimeInterface $date,
        string $shift = ''
    ): bool {
        $sql = "SELECT COUNT(*) FROM reg_periksa 
                WHERE no_rkm_medis = :rm 
                  AND kd_poli = :poli 
                  AND kd_dokter = :dokter 
                  AND tgl_registrasi = :tgl 
                  AND stts != 'Batal'";

        $params = [
            'rm' => $noRkmMedis->value(),
            'poli' => $kdPoli,
            'dokter' => $kdDokter,
            'tgl' => $date->format('Y-m-d'),
        ];

        if ($shift !== '') {
            $sql .= ' AND shift = :shift';
            $params['shift'] = $shift;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function hasUnpaidPreviousVisit(NoRkmMedis $noRkmMedis, \DateTimeInterface $today): bool
    {
        $sql = "SELECT COUNT(*) FROM reg_periksa 
                WHERE no_rkm_medis = :rm 
                  AND status_bayar = 'Belum Bayar' 
                  AND stts != 'Batal' 
                  AND tgl_registrasi < :today";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rm' => $noRkmMedis->value(),
            'today' => $today->format('Y-m-d'),
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function getNextNoReg(
        string $kdPoli,
        string $kdDokter,
        \DateTimeInterface $date,
        string $shift = ''
    ): NoReg {
        $castType = $this->getCastType();

        // 1. Max queue di reg_periksa
        $sqlReg = "SELECT ifnull(MAX(CAST(SUBSTR(no_reg, -3) AS {$castType})), 0) 
                   FROM reg_periksa 
                   WHERE kd_poli = :poli AND kd_dokter = :dokter AND tgl_registrasi = :tgl";
        $paramsReg = [
            'poli' => $kdPoli,
            'dokter' => $kdDokter,
            'tgl' => $date->format('Y-m-d'),
        ];
        if ($shift !== '') {
            $sqlReg .= ' AND shift = :shift';
            $paramsReg['shift'] = $shift;
        }

        $stmtReg = $this->pdo->prepare($sqlReg);
        $stmtReg->execute($paramsReg);
        $maxReg = (int) $stmtReg->fetchColumn();

        // 2. Max queue di booking_registrasi (reservasi antrean)
        $sqlBooking = "SELECT ifnull(MAX(CAST(SUBSTR(no_reg, -3) AS {$castType})), 0) 
                       FROM booking_registrasi 
                       WHERE kd_poli = :poli AND kd_dokter = :dokter AND tanggal_periksa = :tgl AND status != 'Batal'";
        $paramsBooking = [
            'poli' => $kdPoli,
            'dokter' => $kdDokter,
            'tgl' => $date->format('Y-m-d'),
        ];
        if ($shift !== '') {
            $sqlBooking .= ' AND shift = :shift';
            $paramsBooking['shift'] = $shift;
        }

        $stmtBooking = $this->pdo->prepare($sqlBooking);
        $stmtBooking->execute($paramsBooking);
        $maxBooking = (int) $stmtBooking->fetchColumn();

        $highest = max($maxReg, $maxBooking);

        return NoReg::fromSequence($highest + 1);
    }

    public function getNextNoRawat(\DateTimeInterface $date): NoRawat
    {
        $castType = $this->getCastType();
        $sql = "SELECT ifnull(MAX(CAST(SUBSTR(no_rawat, -6) AS {$castType})), 0) 
                FROM reg_periksa 
                WHERE tgl_registrasi = :tgl";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tgl' => $date->format('Y-m-d')]);
        $maxSeq = (int) $stmt->fetchColumn();

        return NoRawat::generate($date, $maxSeq + 1);
    }

    public function saveWithAtomicRetry(RegPeriksa $regPeriksa, int $maxRetries = 5, bool $preserveNoReg = false): RegPeriksa
    {
        $current = $regPeriksa;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $sql = 'INSERT INTO reg_periksa (
                            no_reg, prefik, room, no_rawat, tgl_registrasi,
                            jam_reg, shift, kd_dokter, no_rkm_medis, kd_poli,
                            p_jawab, almt_pj, hubunganpj, biaya_reg, stts,
                            stts_daftar, status_lanjut, kd_pj, umurdaftar, sttsumur,
                            status_bayar, status_poli
                        ) VALUES (
                            :no_reg, :prefik, :room, :no_rawat, :tgl_registrasi,
                            :jam_reg, :shift, :kd_dokter, :no_rkm_medis, :kd_poli,
                            :p_jawab, :almt_pj, :hubunganpj, :biaya_reg, :stts,
                            :stts_daftar, :status_lanjut, :kd_pj, :umurdaftar, :sttsumur,
                            :status_bayar, :status_poli
                        )';

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'no_reg' => $current->noReg->value(),
                    'prefik' => $current->prefik,
                    'room' => $current->room,
                    'no_rawat' => $current->noRawat->value(),
                    'tgl_registrasi' => $current->tglRegistrasi->format('Y-m-d'),
                    'jam_reg' => $current->jamReg,
                    'shift' => $current->shift,
                    'kd_dokter' => $current->kdDokter,
                    'no_rkm_medis' => $current->noRkmMedis->value(),
                    'kd_poli' => $current->kdPoli,
                    'p_jawab' => $current->pJawab,
                    'almt_pj' => $current->almtPj,
                    'hubunganpj' => $current->hubunganPj,
                    'biaya_reg' => $current->biayaReg,
                    'stts' => $current->stts,
                    'stts_daftar' => $current->sttsDaftar,
                    'status_lanjut' => $current->statusLanjut,
                    'kd_pj' => $current->kdPj,
                    'umurdaftar' => $current->umurDaftar,
                    'sttsumur' => $current->sttsUmur,
                    'status_bayar' => $current->statusBayar,
                    'status_poli' => $current->statusPoli,
                ]);

                return $current;
            } catch (\PDOException $e) {
                // Tangani tabrakan UNIQUE constraint (no_rawat atau uq_reg_antrean)
                $isUniqueViolation = in_array($e->getCode(), ['23000', '19', '23505'], true)
                    || str_contains($e->getMessage(), 'UNIQUE constraint failed')
                    || str_contains($e->getMessage(), 'Duplicate entry');

                if ($isUniqueViolation && $attempt < $maxRetries) {
                    // Regenerasi nomor rawat (dan no_reg jika bukan Mobile JKN)
                    $nextRawat = $this->getNextNoRawat($current->tglRegistrasi);
                    $nextReg = $preserveNoReg
                        ? $current->noReg
                        : $this->getNextNoReg($current->kdPoli, $current->kdDokter, $current->tglRegistrasi, $current->shift);

                    $current = new RegPeriksa(
                        noRawat: $nextRawat,
                        noReg: $nextReg,
                        noRkmMedis: $current->noRkmMedis,
                        kdPoli: $current->kdPoli,
                        kdDokter: $current->kdDokter,
                        kdPj: $current->kdPj,
                        tglRegistrasi: $current->tglRegistrasi,
                        jamReg: $current->jamReg,
                        biayaReg: $current->biayaReg,
                        stts: $current->stts,
                        sttsDaftar: $current->sttsDaftar,
                        statusBayar: $current->statusBayar,
                        statusPoli: $current->statusPoli,
                        statusLanjut: $current->statusLanjut,
                        umurDaftar: $current->umurDaftar,
                        sttsUmur: $current->sttsUmur,
                        pJawab: $current->pJawab,
                        almtPj: $current->almtPj,
                        hubunganPj: $current->hubunganPj,
                        prefik: $current->prefik,
                        room: $current->room,
                        shift: $current->shift
                    );
                    continue;
                }

                throw $e;
            }
        }

        return $current;
    }

    public function hasClinicalRecords(NoRawat $noRawat): bool
    {
        $checks = [
            'pemeriksaan_ralan' => 'SELECT 1 FROM pemeriksaan_ralan WHERE no_rawat = :rawat LIMIT 1',
            'rawat_jl_dr' => "SELECT 1 FROM rawat_jl_dr WHERE no_rawat = :rawat AND kd_jenis_prw NOT IN ('RJ002', 'RJ003', 'RJ008') LIMIT 1",
            'rawat_jl_pr' => 'SELECT 1 FROM rawat_jl_pr WHERE no_rawat = :rawat LIMIT 1',
            'rawat_jl_drpr' => 'SELECT 1 FROM rawat_jl_drpr WHERE no_rawat = :rawat LIMIT 1',
            'resep_obat' => 'SELECT 1 FROM resep_obat WHERE no_rawat = :rawat LIMIT 1',
            'periksa_lab' => 'SELECT 1 FROM periksa_lab WHERE no_rawat = :rawat LIMIT 1',
            'periksa_radiologi' => 'SELECT 1 FROM periksa_radiologi WHERE no_rawat = :rawat LIMIT 1',
        ];

        foreach ($checks as $sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['rawat' => $noRawat->value()]);
                if ($stmt->fetchColumn() !== false) {
                    return true;
                }
            } catch (\PDOException) {
                // Jika tabel belum dibuat di lingkungan tertentu, abaikan tabel tersebut
                continue;
            }
        }

        return false;
    }

    public function countVisitsByPatient(NoRkmMedis $noRkmMedis): int
    {
        $sql = "SELECT COUNT(*) FROM reg_periksa WHERE no_rkm_medis = :rm AND stts != 'Batal'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rm' => $noRkmMedis->value()]);

        return (int) $stmt->fetchColumn();
    }

    public function countVisitsByPatientAndPoli(NoRkmMedis $noRkmMedis, string $kdPoli): int
    {
        $sql = "SELECT COUNT(*) FROM reg_periksa WHERE no_rkm_medis = :rm AND kd_poli = :poli AND stts != 'Batal'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rm' => $noRkmMedis->value(), 'poli' => $kdPoli]);

        return (int) $stmt->fetchColumn();
    }

    public function update(RegPeriksa $regPeriksa): void
    {
        $sql = 'UPDATE reg_periksa 
                SET no_reg = :no_reg,
                    kd_dokter = :kd_dokter,
                    kd_poli = :kd_poli,
                    shift = :shift,
                    stts = :stts,
                    status_bayar = :status_bayar
                WHERE no_rawat = :no_rawat';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'no_reg' => $regPeriksa->noReg->value(),
            'kd_dokter' => $regPeriksa->kdDokter,
            'kd_poli' => $regPeriksa->kdPoli,
            'shift' => $regPeriksa->shift,
            'stts' => $regPeriksa->stts,
            'status_bayar' => $regPeriksa->statusBayar,
            'no_rawat' => $regPeriksa->noRawat->value(),
        ]);
    }

    public function updateStatusAndAssignment(
        NoRawat $noRawat,
        string $stts,
        ?string $kdPoli = null,
        ?string $kdDokter = null,
    ): void {
        $sets = ['stts = :stts'];
        $params = [':stts' => $stts, ':nr' => $noRawat->value()];
        if ($kdPoli !== null && $kdPoli !== '') {
            $sets[] = 'kd_poli = :poli';
            $params[':poli'] = $kdPoli;
        }
        if ($kdDokter !== null && $kdDokter !== '') {
            $sets[] = 'kd_dokter = :dokter';
            $params[':dokter'] = $kdDokter;
        }
        $sql = 'UPDATE reg_periksa SET ' . implode(', ', $sets) . ' WHERE no_rawat = :nr';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function updateStatus(NoRawat $noRawat, string $stts): void
    {
        $stmt = $this->pdo->prepare('UPDATE reg_periksa SET stts = :stts WHERE no_rawat = :no_rawat');
        $stmt->execute([
            'stts' => $stts,
            'no_rawat' => $noRawat->value(),
        ]);
    }

    public function reassignDoctor(NoRawat $noRawat, string $newKdDokter, string $oldKdDokter): void
    {
        // 1. Update kunjungan dokter
        $sqlReg = 'UPDATE reg_periksa 
                   SET kd_dokter = :newDokter, 
                       kd_dokter_berhalangan = :oldDokter 
                   WHERE no_rawat = :no_rawat';
        $stmtReg = $this->pdo->prepare($sqlReg);
        $stmtReg->execute([
            'newDokter' => $newKdDokter,
            'oldDokter' => $oldKdDokter,
            'no_rawat' => $noRawat->value(),
        ]);

        // 2. Alihkan tindakan konsultasi awal (RJ002, RJ003, RJ008) ke dokter pengganti jika tabel ada
        try {
            $sqlTindakan = "UPDATE rawat_jl_dr 
                            SET kd_dokter = :newDokter 
                            WHERE no_rawat = :no_rawat 
                              AND kd_dokter = :oldDokter 
                              AND kd_jenis_prw IN ('RJ002', 'RJ003', 'RJ008')";
            $stmtTindakan = $this->pdo->prepare($sqlTindakan);
            $stmtTindakan->execute([
                'newDokter' => $newKdDokter,
                'oldDokter' => $oldKdDokter,
                'no_rawat' => $noRawat->value(),
            ]);
        } catch (\PDOException) {
            // Abaikan jika tabel tindakan rawat_jl_dr belum dipasang
        }
    }

    private function getCastType(): string
    {
        return $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): RegPeriksa
    {
        return new RegPeriksa(
            noRawat: new NoRawat((string) $row['no_rawat']),
            noReg: new NoReg((string) $row['no_reg']),
            noRkmMedis: new NoRkmMedis((string) $row['no_rkm_medis']),
            kdPoli: (string) $row['kd_poli'],
            kdDokter: (string) $row['kd_dokter'],
            kdPj: (string) $row['kd_pj'],
            tglRegistrasi: new \DateTimeImmutable((string) $row['tgl_registrasi']),
            jamReg: (string) $row['jam_reg'],
            biayaReg: (float) ($row['biaya_reg'] ?? 0.0),
            stts: (string) ($row['stts'] ?? 'Belum'),
            sttsDaftar: (string) ($row['stts_daftar'] ?? 'Baru'),
            statusBayar: (string) ($row['status_bayar'] ?? 'Belum Bayar'),
            statusPoli: (string) ($row['status_poli'] ?? 'Baru'),
            statusLanjut: (string) ($row['status_lanjut'] ?? 'Ralan'),
            umurDaftar: (int) ($row['umurdaftar'] ?? 0),
            sttsUmur: (string) ($row['sttsumur'] ?? 'Th'),
            pJawab: (string) ($row['p_jawab'] ?? '-'),
            almtPj: (string) ($row['almt_pj'] ?? '-'),
            hubunganPj: (string) ($row['hubunganpj'] ?? '-'),
            prefik: (string) ($row['prefik'] ?? ''),
            room: (string) ($row['room'] ?? ''),
            shift: (string) ($row['shift'] ?? '')
        );
    }
}
