<?php

declare(strict_types=1);

namespace App\Application\Support\UseCase;

use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Support\Entity\PermintaanRadiologi;
use App\Domain\Support\Repository\LabRadiologyRepositoryInterface;
use App\Infrastructure\Database\TransactionManagerInterface;

final readonly class CreateRadiologyOrderUseCase
{
    public function __construct(
        private LabRadiologyRepositoryInterface $labRepo,
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private TransactionManagerInterface $tx,
    ) {}

    /**
     * @param list<array{kd_jenis_prw: string, nm_perawatan: string, harga: float|int, claim_type: string}> $items
     * @return list<PermintaanRadiologi>
     */
    public function execute(
        NoRawat $noRawat,
        string $dokterPerujuk,
        array $items,
        ?\DateTimeImmutable $orderAt = null,
        string $status = 'ralan',
    ): array {
        if ($items === []) {
            throw new \InvalidArgumentException('Minimal satu item pemeriksaan radiologi harus dipilih.');
        }

        $now = $orderAt ?? new \DateTimeImmutable();
        $dateYmd = $now->format('Ymd');
        $tgl = $now->format('Y-m-d');
        $jam = $now->format('H:i:s');

        /** @var array<string, list<array{kd_jenis_prw: string, nm_perawatan: string, harga: float|int, claim_type: string}>> $grouped */
        $grouped = [];
        foreach ($items as $item) {
            $claimType = ClaimType::fromString((string) ($item['claim_type'] ?? 'Tunai'))->value();
            $grouped[$claimType][] = $item;
        }

        return $this->tx->transaction(function () use ($noRawat, $dokterPerujuk, $grouped, $dateYmd, $tgl, $jam, $status): array {
            $createdOrders = [];
            foreach ($grouped as $claimTypeStr => $groupItems) {
                $claimType = ClaimType::fromString($claimTypeStr);
                $noOrder = $this->labRepo->getNextOrderNumber('PR', $dateYmd);

                $order = new PermintaanRadiologi(
                    noOrder: $noOrder,
                    noRawat: $noRawat,
                    tglPermintaan: $tgl,
                    jamPermintaan: $jam,
                    dokterPerujuk: $dokterPerujuk,
                    jenisKlaim: $claimType,
                    status: $status,
                );
                $this->labRepo->saveRadiologiOrder($order);

                foreach ($groupItems as $item) {
                    $kdJenisPrw = (string) $item['kd_jenis_prw'];
                    $nmPerawatan = (string) $item['nm_perawatan'];
                    $harga = (float) $item['harga'];

                    $this->labRepo->saveRadiologiItem($noOrder, $kdJenisPrw, $claimType->value());

                    $this->ledgerRepo->addItem(new BillingLedgerItem(
                        id: null,
                        noRawat: $noRawat,
                        itemCode: $kdJenisPrw,
                        itemName: $nmPerawatan,
                        category: 'Radiologi',
                        qty: 1.0,
                        unitPrice: $harga,
                        totalAmount: $harga,
                        claimType: $claimType,
                        status: LedgerStatus::fromString('Pending'),
                        providerId: $noOrder,
                        createdAt: $tgl . ' ' . $jam,
                    ));
                }

                $createdOrders[] = $order;
            }

            return $createdOrders;
        });
    }
}
