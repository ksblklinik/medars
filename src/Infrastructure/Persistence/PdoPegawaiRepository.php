<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Staff\Entity\Pegawai;
use App\Domain\Staff\Repository\PegawaiRepositoryInterface;
use PDO;

final class PdoPegawaiRepository implements PegawaiRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function findAllActive(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM pegawai WHERE UPPER(status) = 'AKTIF' ORDER BY nama ASC");
        $results = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = $this->hydrate($row);
        }

        return $results;
    }

    public function findByNik(string $nik): ?Pegawai
    {
        $stmt = $this->pdo->prepare("SELECT * FROM pegawai WHERE nik = :nik LIMIT 1");
        $stmt->execute([':nik' => $nik]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function save(Pegawai $pegawai): void
    {
        $existing = $this->findByNik($pegawai->getNik());

        if ($existing === null) {
            $sql = "
                INSERT INTO pegawai (nik, nama, jk, jbtn, departemen, status)
                VALUES (:nik, :nama, :jk, :jbtn, :dept, :stts)
            ";
        } else {
            $sql = "
                UPDATE pegawai
                SET nama = :nama,
                    jk = :jk,
                    jbtn = :jbtn,
                    departemen = :dept,
                    status = :stts
                WHERE nik = :nik
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':nik' => $pegawai->getNik(),
            ':nama' => $pegawai->getNama(),
            ':jk' => $pegawai->getJk(),
            ':jbtn' => $pegawai->getJbtn(),
            ':dept' => $pegawai->getDepartemen(),
            ':stts' => $pegawai->getStatus(),
        ]);
    }

    public function deactivate(string $nik): void
    {
        $stmt = $this->pdo->prepare("UPDATE pegawai SET status = 'TIDAK AKTIF' WHERE nik = :nik");
        $stmt->execute([':nik' => $nik]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Pegawai
    {
        return new Pegawai(
            nik: (string) $row['nik'],
            nama: (string) $row['nama'],
            jk: (string) $row['jk'],
            jbtn: (string) $row['jbtn'],
            departemen: (string) ($row['departemen'] ?? ''),
            status: (string) ($row['status'] ?? 'AKTIF')
        );
    }
}
