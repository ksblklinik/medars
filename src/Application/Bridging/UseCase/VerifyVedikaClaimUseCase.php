<?php

declare(strict_types=1);

namespace App\Application\Bridging\UseCase;

use App\Domain\Registration\ValueObject\NoRawat;
use PDO;

final readonly class VerifyVedikaClaimUseCase
{
    public function __construct(private PDO $pdo) {}

    /**
     * @return array{no_rawat: string, no_sep: string, status: string, catatan: string}
     */
    public function execute(
        NoRawat $noRawat,
        string $noSep,
        string $verifiedBy,
        string $jenisPelayanan = 'Ralan',
    ): array {
        $nr = $noRawat->value();
        $trimmedSep = trim($noSep);
        if ($trimmedSep === '') {
            throw new \InvalidArgumentException('Nomor SEP BPJS wajib diisi untuk verifikasi klaim Vedika.');
        }

        $regStmt = $this->pdo->prepare('SELECT COUNT(*) FROM reg_periksa WHERE no_rawat = :nr');
        $regStmt->execute([':nr' => $nr]);
        if ((int) $regStmt->fetchColumn() === 0) {
            throw new \DomainException("Data registrasi '{$nr}' tidak ditemukan.");
        }

        $soapStmt = $this->pdo->prepare('SELECT COUNT(*) FROM pemeriksaan_ralan WHERE no_rawat = :nr');
        $soapStmt->execute([':nr' => $nr]);
        $soapCount = (int) $soapStmt->fetchColumn();

        $diagStmt = $this->pdo->prepare('SELECT COUNT(*) FROM diagnosa_pasien WHERE no_rawat = :nr');
        $diagStmt->execute([':nr' => $nr]);
        $diagCount = (int) $diagStmt->fetchColumn();

        $issues = [];
        if ($soapCount === 0) {
            $issues[] = 'SOAP pemeriksaan belum diisi';
        }
        if ($diagCount === 0) {
            $issues[] = 'Diagnosa ICD-10 belum diisi';
        }

        $status = $issues === [] ? 'Layak' : 'Perlu Perbaikan';
        $catatan = $issues === [] ? 'Berkas klaim lengkap' : implode('; ', $issues);
        $now = date('Y-m-d H:i:s');

        // Portable upsert (DELETE + INSERT in or single check) compatible with both SQLite and MySQL 8.0
        $del = $this->pdo->prepare('DELETE FROM mlite_vedika WHERE no_rawat = :nr');
        $del->execute([':nr' => $nr]);

        $ins = $this->pdo->prepare(
            'INSERT INTO mlite_vedika (no_rawat, no_sep, jenis_pelayanan, status, catatan, verified_by, verified_at)
             VALUES (:nr, :sep, :jp, :stts, :cat, :by, :at)'
        );
        $ins->execute([
            ':nr' => $nr,
            ':sep' => $trimmedSep,
            ':jp' => $jenisPelayanan,
            ':stts' => $status,
            ':cat' => $catatan,
            ':by' => $verifiedBy,
            ':at' => $now,
        ]);

        return [
            'no_rawat' => $nr,
            'no_sep' => $trimmedSep,
            'status' => $status,
            'catatan' => $catatan,
        ];
    }
}
