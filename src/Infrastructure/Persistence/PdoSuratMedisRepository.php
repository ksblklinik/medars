<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Rme\Entity\RujukanInternal;
use App\Domain\Rme\Entity\SuratBebasNarkoba;
use App\Domain\Rme\Entity\SuratHamil;
use App\Domain\Rme\Entity\SuratSakit;
use App\Domain\Rme\Repository\SuratMedisRepositoryInterface;
use PDO;

final class PdoSuratMedisRepository implements SuratMedisRepositoryInterface
{
    private const ALLOWED_TABLES = ['surat_sakit', 'surat_hamil', 'surat_bebas_narkoba'];

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getNextSuratNumber(string $prefix, string $table): string
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) {
            throw new \InvalidArgumentException("Tabel surat tidak valid: '{$table}'.");
        }

        $like = $prefix . '%';
        $stmt = $this->pdo->prepare("SELECT MAX(no_surat) FROM {$table} WHERE no_surat LIKE :prefix");
        $stmt->execute([':prefix' => $like]);
        $max = $stmt->fetchColumn();

        $nextNum = 1;
        if (is_string($max) && str_starts_with($max, $prefix)) {
            $suffix = substr($max, strlen($prefix));
            if (is_numeric($suffix)) {
                $nextNum = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad((string) $nextNum, 4, '0', STR_PAD_LEFT);
    }

    public function saveSuratSakit(SuratSakit $surat): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO surat_sakit (no_surat, no_rawat, tanggalawal, tanggalakhir, lamasakit)
            VALUES (:ns, :nr, :t1, :t2, :lama)
        ");
        $stmt->execute([
            ':ns' => $surat->noSurat,
            ':nr' => $surat->noRawat->value(),
            ':t1' => $surat->tanggalAwal->format('Y-m-d'),
            ':t2' => $surat->tanggalAkhir->format('Y-m-d'),
            ':lama' => $surat->lamaSakit,
        ]);
    }

    public function saveSuratHamil(SuratHamil $surat): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO surat_hamil (no_surat, no_rawat, tanggalperiksa, hasilperiksa)
            VALUES (:ns, :nr, :tgl, :hasil)
        ");
        $stmt->execute([
            ':ns' => $surat->noSurat,
            ':nr' => $surat->noRawat->value(),
            ':tgl' => $surat->tanggalPeriksa->format('Y-m-d'),
            ':hasil' => $surat->hasilPeriksa,
        ]);
    }

    public function saveSuratBebasNarkoba(SuratBebasNarkoba $surat): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO surat_bebas_narkoba (
                no_surat, no_rawat, tanggal, kategori, kd_dokter, keperluan,
                opiat, ganja, amphetamin, methamphetamin, benzodiazepin, cocain
            ) VALUES (
                :ns, :nr, :tgl, :kat, :dr, :kep,
                :opiat, :ganja, :amp, :meth, :benzo, :cocain
            )
        ");
        $stmt->execute([
            ':ns' => $surat->noSurat,
            ':nr' => $surat->noRawat->value(),
            ':tgl' => $surat->tanggal->format('Y-m-d'),
            ':kat' => $surat->kategori,
            ':dr' => $surat->kdDokter,
            ':kep' => $surat->keperluan,
            ':opiat' => $surat->opiat,
            ':ganja' => $surat->ganja,
            ':amp' => $surat->amphetamin,
            ':meth' => $surat->methamphetamin,
            ':benzo' => $surat->benzodiazepin,
            ':cocain' => $surat->cocain,
        ]);
    }

    public function saveRujukanInternal(RujukanInternal $rujukan): void
    {
        $check = $this->pdo->prepare("
            SELECT 1 FROM rujukan_internal WHERE no_rawat = :nr AND kd_poli = :poli LIMIT 1
        ");
        $check->execute([
            ':nr' => $rujukan->noRawat->value(),
            ':poli' => $rujukan->kdPoli,
        ]);

        if ($check->fetchColumn()) {
            $sql = "UPDATE rujukan_internal SET kd_dokter = :dr, catatan = :cat WHERE no_rawat = :nr AND kd_poli = :poli";
        } else {
            $sql = "INSERT INTO rujukan_internal (no_rawat, kd_dokter, kd_poli, catatan) VALUES (:nr, :dr, :poli, :cat)";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nr' => $rujukan->noRawat->value(),
            ':dr' => $rujukan->kdDokter,
            ':poli' => $rujukan->kdPoli,
            ':cat' => $rujukan->catatan,
        ]);
    }
}
