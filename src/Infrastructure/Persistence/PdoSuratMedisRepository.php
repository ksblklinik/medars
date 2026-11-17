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
    private const ALLOWED_TABLES = ['surat_sakit', 'surat_hamil', 'surat_bebas_narkoba', 'surat_sehat', 'surat_cuti_hamil'];

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

    public function listSurat(string $type, string $q = ''): array
    {
        $like = '%' . trim($q) . '%';
        $sql = match ($type) {
            'sakit' => "
                SELECT s.no_surat, s.no_rawat, s.tanggalawal, s.tanggalakhir, s.lamasakit,
                       p.no_rkm_medis, p.nm_pasien
                FROM surat_sakit s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                WHERE s.no_surat LIKE :q OR s.no_rawat LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q
                ORDER BY s.tanggalawal DESC, s.no_surat DESC LIMIT 100
            ",
            'hamil' => "
                SELECT s.no_surat, s.no_rawat, s.tanggalperiksa, s.hasilperiksa,
                       p.no_rkm_medis, p.nm_pasien
                FROM surat_hamil s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                WHERE s.no_surat LIKE :q OR s.no_rawat LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q
                ORDER BY s.tanggalperiksa DESC, s.no_surat DESC LIMIT 100
            ",
            'narkoba' => "
                SELECT s.no_surat, s.no_rawat, s.tanggal, s.kategori, s.kd_dokter, s.keperluan,
                       p.no_rkm_medis, p.nm_pasien
                FROM surat_bebas_narkoba s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                WHERE s.no_surat LIKE :q OR s.no_rawat LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q
                ORDER BY s.tanggal DESC, s.no_surat DESC LIMIT 100
            ",
            'sehat' => "
                SELECT s.no_surat, s.no_rawat, s.tanggalsurat, s.berat, s.tinggi, s.tensi, s.suhu, s.butawarna, s.keperluan, s.kesimpulan,
                       p.no_rkm_medis, p.nm_pasien
                FROM surat_sehat s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                WHERE s.no_surat LIKE :q OR s.no_rawat LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q
                ORDER BY s.tanggalsurat DESC, s.no_surat DESC LIMIT 100
            ",
            'cuti_hamil' => "
                SELECT s.no_surat, s.no_rawat, s.keterangan_hamil, s.terhitung_mulai, s.perkiraan_lahir,
                       p.no_rkm_medis, p.nm_pasien
                FROM surat_cuti_hamil s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                WHERE s.no_surat LIKE :q OR s.no_rawat LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q
                ORDER BY s.terhitung_mulai DESC, s.no_surat DESC LIMIT 100
            ",
            default => throw new \InvalidArgumentException("Jenis surat tidak dikenal: '{$type}'."),
        };

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findSuratForPrint(string $type, string $noSurat): ?array
    {
        $sql = match ($type) {
            'sakit' => "
                SELECT s.*, p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk, p.alamat, d.nm_dokter
                FROM surat_sakit s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                WHERE s.no_surat = :ns LIMIT 1
            ",
            'hamil' => "
                SELECT s.*, p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk, p.alamat, d.nm_dokter
                FROM surat_hamil s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                WHERE s.no_surat = :ns LIMIT 1
            ",
            'narkoba' => "
                SELECT s.*, p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk, p.alamat, d.nm_dokter
                FROM surat_bebas_narkoba s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN dokter d ON s.kd_dokter = d.kd_dokter
                WHERE s.no_surat = :ns LIMIT 1
            ",
            'sehat' => "
                SELECT s.*, p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk, p.alamat, d.nm_dokter
                FROM surat_sehat s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                WHERE s.no_surat = :ns LIMIT 1
            ",
            'cuti_hamil' => "
                SELECT s.*, p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk, p.alamat, d.nm_dokter
                FROM surat_cuti_hamil s
                LEFT JOIN reg_periksa r ON s.no_rawat = r.no_rawat
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                WHERE s.no_surat = :ns LIMIT 1
            ",
            default => throw new \InvalidArgumentException("Jenis surat tidak dikenal: '{$type}'."),
        };

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':ns' => trim($noSurat)]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function listRujukanInternal(string $q = ''): array
    {
        $like = '%' . trim($q) . '%';
        $stmt = $this->pdo->prepare("
            SELECT ri.no_rawat, ri.kd_dokter, ri.kd_poli, ri.catatan,
                   p.nm_pasien, p.no_rkm_medis, pol.nm_poli, d.nm_dokter
            FROM rujukan_internal ri
            LEFT JOIN reg_periksa r ON ri.no_rawat = r.no_rawat
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON ri.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON ri.kd_dokter = d.kd_dokter
            WHERE ri.no_rawat LIKE :q OR COALESCE(p.nm_pasien, '') LIKE :q OR COALESCE(pol.nm_poli, '') LIKE :q
            ORDER BY ri.no_rawat DESC LIMIT 100
        ");
        $stmt->execute([':q' => $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $data */
    public function saveSuratSehat(array $data): string
    {
        $noSurat = $this->getNextSuratNumber('SKD-' . date('Ym') . '-', 'surat_sehat');
        $stmt = $this->pdo->prepare(
            'INSERT INTO surat_sehat (no_surat, no_rawat, tanggalsurat, berat, tinggi, tensi, suhu, butawarna, keperluan, kesimpulan)
             VALUES (:ns, :nr, :tgl, :bb, :tb, :td, :sh, :bw, :kp, :ks)'
        );
        $stmt->execute([
            ':ns' => $noSurat,
            ':nr' => trim((string) ($data['no_rawat'] ?? '')),
            ':tgl' => trim((string) ($data['tanggalsurat'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            ':bb' => trim((string) ($data['berat'] ?? '')),
            ':tb' => trim((string) ($data['tinggi'] ?? '')),
            ':td' => trim((string) ($data['tensi'] ?? '')),
            ':sh' => trim((string) ($data['suhu'] ?? '')),
            ':bw' => trim((string) ($data['butawarna'] ?? 'Tidak')) ?: 'Tidak',
            ':kp' => trim((string) ($data['keperluan'] ?? 'Administrasi')) ?: 'Administrasi',
            ':ks' => trim((string) ($data['kesimpulan'] ?? 'Sehat')) ?: 'Sehat',
        ]);
        return $noSurat;
    }

    /** @param array<string, mixed> $data */
    public function saveSuratCutiHamil(array $data): string
    {
        $noSurat = $this->getNextSuratNumber('SCH-' . date('Ym') . '-', 'surat_cuti_hamil');
        $stmt = $this->pdo->prepare(
            'INSERT INTO surat_cuti_hamil (no_surat, no_rawat, keterangan_hamil, terhitung_mulai, perkiraan_lahir)
             VALUES (:ns, :nr, :kh, :tm, :pl)'
        );
        $stmt->execute([
            ':ns' => $noSurat,
            ':nr' => trim((string) ($data['no_rawat'] ?? '')),
            ':kh' => trim((string) ($data['keterangan_hamil'] ?? 'Hamil Trimester III')) ?: 'Hamil Trimester III',
            ':tm' => trim((string) ($data['terhitung_mulai'] ?? date('Y-m-d'))) ?: date('Y-m-d'),
            ':pl' => trim((string) ($data['perkiraan_lahir'] ?? date('Y-m-d', strtotime('+30 days')))) ?: date('Y-m-d', strtotime('+30 days')),
        ]);
        return $noSurat;
    }
}
