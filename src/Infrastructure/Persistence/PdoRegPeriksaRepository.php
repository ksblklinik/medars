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
        $sql = 'SELECT COUNT(*) FROM reg_periksa 
                WHERE no_rkm_medis = :rm 
                  AND kd_poli = :poli 
                  AND kd_dokter = :dokter 
                  AND tgl_registrasi = :tgl 
                  AND stts != "Batal"';

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
        $sql = 'SELECT COUNT(*) FROM reg_periksa 
                WHERE no_rkm_medis = :rm 
                  AND status_bayar = "Belum Bayar" 
                  AND stts != "Batal" 
                  AND tgl_registrasi < :today';

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

    public function saveWithAtomicRetry(RegPeriksa $regPeriksa, int $maxRetries = 5): RegPeriksa
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
                    // Regenerasi nomor rawat dan nomor registrasi berikutnya
                    $nextRawat = $this->getNextNoRawat($current->tglRegistrasi);
                    $nextReg = $this->getNextNoReg($current->kdPoli, $current->kdDokter, $current->tglRegistrasi, $current->shift);

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
        $tables = [
            'pemeriksaan_ralan',
            'rawat_jl_dr',
            'rawat_jl_pr',
            'rawat_jl_drpr',
            'resep_obat',
            'periksa_lab',
            'periksa_radiologi',
        ];

        foreach ($tables as $table) {
            try {
                $sql = "SELECT 1 FROM {$table} WHERE no_rawat = :rawat LIMIT 1";
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
