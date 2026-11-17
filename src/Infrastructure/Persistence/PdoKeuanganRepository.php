<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entity\JurnalDetail;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\Repository\KeuanganRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class PdoKeuanganRepository implements KeuanganRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private TransactionManagerInterface $tx,
    ) {
    }

    public function listRekening(): array
    {
        $stmt = $this->pdo->query('SELECT kd_rek, nm_rek, tipe, balance FROM mlite_rekening ORDER BY kd_rek ASC');
        return $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    public function saveRekening(string $kdRek, string $nmRek, string $tipe = 'Aset', string $balance = 'D'): void
    {
        $kd = trim($kdRek);
        $nm = trim($nmRek);
        if ($kd === '' || $nm === '') {
            throw new \InvalidArgumentException('Kode dan nama rekening wajib diisi.');
        }

        $chk = $this->pdo->prepare('SELECT 1 FROM mlite_rekening WHERE kd_rek = :k');
        $chk->execute([':k' => $kd]);
        $exists = $chk->fetchColumn() !== false;

        $sql = $exists
            ? 'UPDATE mlite_rekening SET nm_rek = :nm, tipe = :t, balance = :b WHERE kd_rek = :k'
            : 'INSERT INTO mlite_rekening (kd_rek, nm_rek, tipe, balance) VALUES (:k, :nm, :t, :b)';
        $this->pdo->prepare($sql)->execute([
            ':k' => $kd,
            ':nm' => $nm,
            ':t' => trim($tipe) ?: 'Aset',
            ':b' => trim($balance) === 'K' ? 'K' : 'D',
        ]);
    }

    public function listJurnal(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("
            SELECT j.no_jurnal, j.tgl_jurnal, j.jam_jurnal, j.no_bukti, j.keterangan,
                   d.kd_rek, COALESCE(r.nm_rek, d.kd_rek) AS nm_rek, d.debet, d.kredit
            FROM mlite_jurnal j
            INNER JOIN mlite_jurnal_detail d ON j.no_jurnal = d.no_jurnal
            LEFT JOIN mlite_rekening r ON d.kd_rek = r.kd_rek
            WHERE j.tgl_jurnal BETWEEN :from AND :to
            ORDER BY j.tgl_jurnal DESC, j.jam_jurnal DESC, d.id ASC
            LIMIT 200
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getBukuBesar(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare("
            SELECT d.kd_rek, COALESCE(r.nm_rek, d.kd_rek) AS nm_rek, COALESCE(r.tipe, '-') AS tipe,
                   SUM(d.debet) AS total_debet, SUM(d.kredit) AS total_kredit
            FROM mlite_jurnal_detail d
            INNER JOIN mlite_jurnal j ON d.no_jurnal = j.no_jurnal
            LEFT JOIN mlite_rekening r ON d.kd_rek = r.kd_rek
            WHERE j.tgl_jurnal BETWEEN :from AND :to
            GROUP BY d.kd_rek
            ORDER BY d.kd_rek ASC
        ");
        $stmt->execute([':from' => $from, ':to' => $to]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getLabaRugi(string $from, string $to): array
    {
        $rows = $this->getBukuBesar($from, $to);
        $pendapatan = 0.0;
        $beban = 0.0;
        foreach ($rows as $row) {
            $kd = (string) ($row['kd_rek'] ?? '');
            $debet = (float) ($row['total_debet'] ?? 0);
            $kredit = (float) ($row['total_kredit'] ?? 0);
            if (str_starts_with($kd, '4')) {
                $pendapatan += ($kredit - $debet);
            } elseif (str_starts_with($kd, '5') || str_starts_with($kd, '6')) {
                $beban += ($debet - $kredit);
            }
        }

        return [
            'total_pendapatan' => $pendapatan,
            'total_beban' => $beban,
            'laba_bersih' => $pendapatan - $beban,
        ];
    }

    public function postManualJurnal(
        string $noBukti,
        string $keterangan,
        string $kdDebet,
        string $kdKredit,
        float $nominal,
        ?\DateTimeImmutable $at = null
    ): string {
        if ($nominal <= 0) {
            throw new \InvalidArgumentException('Nominal jurnal harus lebih dari 0.');
        }
        if (trim($kdDebet) === '' || trim($kdKredit) === '' || trim($kdDebet) === trim($kdKredit)) {
            throw new \InvalidArgumentException('Akun debet dan kredit wajib diisi dan tidak boleh sama.');
        }

        $now = $at ?? new \DateTimeImmutable();
        return $this->tx->transaction(function () use ($noBukti, $keterangan, $kdDebet, $kdKredit, $nominal, $now): string {
            $noJurnal = $this->ledgerRepo->getNextJurnalNumber($now);
            $this->ledgerRepo->saveJurnal(new JurnalEntry(
                noJurnal: $noJurnal,
                tglJurnal: $now->format('Y-m-d'),
                jamJurnal: $now->format('H:i:s'),
                noBukti: trim($noBukti) ?: 'MANUAL-' . $now->format('His'),
                keterangan: trim($keterangan) ?: 'Entri Jurnal Manual',
                details: [
                    new JurnalDetail(trim($kdDebet), $nominal, 0.0),
                    new JurnalDetail(trim($kdKredit), 0.0, $nominal),
                ]
            ));
            return $noJurnal;
        });
    }
}
