<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Entity\JurnalDetail;
use App\Domain\Billing\Entity\JurnalEntry;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;
use PDO;

final class PdoBillingLedgerRepository implements BillingLedgerRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function addItem(BillingLedgerItem $item): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mlite_billing_ledger (
                no_rawat, item_code, item_name, category, qty, unit_price, total_amount,
                claim_type, status, provider_id, created_at
            ) VALUES (
                :nr, :code, :name, :cat, :qty, :price, :total,
                :claim, :status, :prov, :created
            )
        ");
        $stmt->execute([
            ':nr' => $item->noRawat->value(),
            ':code' => $item->itemCode,
            ':name' => $item->itemName,
            ':cat' => $item->category,
            ':qty' => $item->qty,
            ':price' => $item->unitPrice,
            ':total' => $item->totalAmount,
            ':claim' => $item->claimType->value(),
            ':status' => $item->status->value(),
            ':prov' => $item->providerId,
            ':created' => $item->createdAt !== '' ? $item->createdAt : date('Y-m-d H:i:s'),
        ]);
    }

    public function findByNoRawat(NoRawat $noRawat): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mlite_billing_ledger WHERE no_rawat = :nr ORDER BY id ASC");
        $stmt->execute([':nr' => $noRawat->value()]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results[] = new BillingLedgerItem(
                id: (int) $row['id'],
                noRawat: new NoRawat((string) $row['no_rawat']),
                itemCode: (string) $row['item_code'],
                itemName: (string) $row['item_name'],
                category: (string) $row['category'],
                qty: (float) $row['qty'],
                unitPrice: (float) $row['unit_price'],
                totalAmount: (float) $row['total_amount'],
                claimType: ClaimType::fromString((string) $row['claim_type']),
                status: LedgerStatus::fromString((string) $row['status']),
                providerId: (string) ($row['provider_id'] ?? ''),
                createdAt: (string) ($row['created_at'] ?? '')
            );
        }

        return $results;
    }

    public function updateStatusByNoRawat(NoRawat $noRawat, LedgerStatus $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE mlite_billing_ledger SET status = :stts WHERE no_rawat = :nr");
        $stmt->execute([
            ':stts' => $status->value(),
            ':nr' => $noRawat->value(),
        ]);
    }

    public function markPendingAsPaid(NoRawat $noRawat): void
    {
        $stmt = $this->pdo->prepare("UPDATE mlite_billing_ledger SET status = 'Paid' WHERE no_rawat = :nr AND status = 'Pending'");
        $stmt->execute([':nr' => $noRawat->value()]);
    }

    public function cancelPendingByProviderId(string $providerId): void
    {
        $stmt = $this->pdo->prepare("UPDATE mlite_billing_ledger SET status = 'Cancelled' WHERE provider_id = :prov AND status = 'Pending'");
        $stmt->execute([':prov' => $providerId]);
    }

    public function getNextJurnalNumber(\DateTimeInterface $date): string
    {
        $prefix = 'JRN-' . $date->format('Ymd') . '-';
        $stmt = $this->pdo->prepare("SELECT MAX(no_jurnal) FROM mlite_jurnal WHERE no_jurnal LIKE :prefix");
        $stmt->execute([':prefix' => $prefix . '%']);
        $max = $stmt->fetchColumn();

        $next = 1;
        if (is_string($max) && str_starts_with($max, $prefix)) {
            $suffix = substr($max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    public function saveJurnal(JurnalEntry $entry): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO mlite_jurnal (no_jurnal, tgl_jurnal, jam_jurnal, no_bukti, keterangan)
            VALUES (:no, :tgl, :jam, :bukti, :ket)
        ");
        $stmt->execute([
            ':no' => $entry->noJurnal,
            ':tgl' => $entry->tglJurnal,
            ':jam' => $entry->jamJurnal,
            ':bukti' => $entry->noBukti,
            ':ket' => $entry->keterangan,
        ]);

        $detStmt = $this->pdo->prepare("
            INSERT INTO mlite_jurnal_detail (no_jurnal, kd_rek, debet, kredit)
            VALUES (:no, :rek, :deb, :kre)
        ");
        foreach ($entry->details as $d) {
            $detStmt->execute([
                ':no' => $entry->noJurnal,
                ':rek' => $d->kdRek,
                ':deb' => $d->debet,
                ':kre' => $d->kredit,
            ]);
        }
    }

    public function findJurnalsByNoBukti(string $noBukti): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM mlite_jurnal WHERE no_bukti = :bukti ORDER BY no_jurnal ASC");
        $stmt->execute([':bukti' => $noBukti]);
        $headers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($headers === []) {
            return [];
        }

        $detStmt = $this->pdo->prepare("
            SELECT d.* FROM mlite_jurnal_detail d
            INNER JOIN mlite_jurnal j ON d.no_jurnal = j.no_jurnal
            WHERE j.no_bukti = :bukti
            ORDER BY d.id ASC
        ");
        $detStmt->execute([':bukti' => $noBukti]);
        $allDetails = $detStmt->fetchAll(PDO::FETCH_ASSOC);

        $byJurnal = [];
        foreach ($allDetails as $d) {
            $byJurnal[(string) $d['no_jurnal']][] = new JurnalDetail(
                kdRek: (string) $d['kd_rek'],
                debet: (float) $d['debet'],
                kredit: (float) $d['kredit']
            );
        }

        $results = [];
        foreach ($headers as $h) {
            $noJurnal = (string) $h['no_jurnal'];
            $results[] = new JurnalEntry(
                noJurnal: $noJurnal,
                tglJurnal: (string) $h['tgl_jurnal'],
                jamJurnal: (string) $h['jam_jurnal'],
                noBukti: (string) $h['no_bukti'],
                keterangan: (string) ($h['keterangan'] ?? ''),
                details: $byJurnal[$noJurnal] ?? []
            );
        }

        return $results;
    }
}
