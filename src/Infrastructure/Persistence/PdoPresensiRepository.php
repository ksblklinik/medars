<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Security\Repository\PresensiRepositoryInterface;

final readonly class PdoPresensiRepository implements PresensiRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function clockIn(
        string $nip,
        string $shift = 'Pagi',
        string $lokasi = 'Klinik',
        string $keterangan = '',
        ?string $tgl = null,
        ?string $jam = null
    ): void {
        $n = trim($nip);
        if ($n === '') {
            throw new \InvalidArgumentException('NIP / Username pegawai wajib diisi.');
        }
        $date = $tgl ?? date('Y-m-d');
        $time = $jam ?? date('H:i:s');

        $chk = $this->pdo->prepare('SELECT 1 FROM mlite_presensi WHERE nip = :n AND tgl_presensi = :t LIMIT 1');
        $chk->execute([':n' => $n, ':t' => $date]);
        if ($chk->fetchColumn() !== false) {
            throw new \DomainException("Pegawai '{$n}' sudah melakukan absen masuk pada {$date}.");
        }

        $this->pdo->prepare("
            INSERT INTO mlite_presensi (nip, tgl_presensi, jam_masuk, jam_pulang, shift, status, lokasi, keterangan)
            VALUES (:n, :t, :jm, '', :sh, 'Hadir', :lok, :ket)
        ")->execute([
            ':n' => $n,
            ':t' => $date,
            ':jm' => $time,
            ':sh' => trim($shift) ?: 'Pagi',
            ':lok' => trim($lokasi) ?: 'Klinik',
            ':ket' => trim($keterangan),
        ]);
    }

    public function clockOut(string $nip, ?string $tgl = null, ?string $jam = null): void
    {
        $n = trim($nip);
        $date = $tgl ?? date('Y-m-d');
        $time = $jam ?? date('H:i:s');

        $stmt = $this->pdo->prepare("
            UPDATE mlite_presensi SET jam_pulang = :jp
            WHERE nip = :n AND tgl_presensi = :t AND jam_pulang = ''
        ");
        $stmt->execute([':jp' => $time, ':n' => $n, ':t' => $date]);
        if ($stmt->rowCount() === 0) {
            throw new \DomainException("Belum ada absen masuk aktif untuk '{$n}' pada {$date}.");
        }
    }

    public function listPresensi(string $from, string $to, string $nip = ''): array
    {
        $sql = 'SELECT * FROM mlite_presensi WHERE tgl_presensi BETWEEN :from AND :to';
        $params = [':from' => $from, ':to' => $to];
        if (trim($nip) !== '') {
            $sql .= ' AND nip = :n';
            $params[':n'] = trim($nip);
        }
        $sql .= ' ORDER BY tgl_presensi DESC, id DESC LIMIT 100';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
