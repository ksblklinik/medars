<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Billing\UseCase\ProcessPosApotekUmumUseCase;
use App\Application\Billing\UseCase\ProcessSplitPaymentUseCase;
use App\Application\Billing\UseCase\ReconcilePendingOrderUseCase;
use App\Application\Billing\UseCase\SettlePiutangUseCase;
use App\Application\Billing\UseCase\VoidBillingTransactionUseCase;
use App\Domain\Billing\Entity\BillingLedgerItem;
use App\Domain\Billing\Repository\BillingLedgerRepositoryInterface;
use App\Domain\Billing\ValueObject\ClaimType;
use App\Domain\Billing\ValueObject\LedgerStatus;
use App\Domain\Billing\ValueObject\PosPaymentMethod;
use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class BillingController
{
    public function __construct(
        private \PDO $pdo,
        private BillingLedgerRepositoryInterface $ledgerRepo,
        private ProcessSplitPaymentUseCase $payUseCase,
        private VoidBillingTransactionUseCase $voidUseCase,
        private ReconcilePendingOrderUseCase $reconcileUseCase,
        private ProcessPosApotekUmumUseCase $posUseCase,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
        private ?SettlePiutangUseCase $settlePiutangUseCase = null,
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('billing/index', [
            'judul' => 'Kasir & Billing Ledger 4-Pilar',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'kasir',
            'module_icon' => 'fa-cash-register',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Kasir'),
            'user_role' => $currentUser?->getRole() ?? 'kasir',
            'csrf_token' => $csrfToken,
            'navigations' => $navigations,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiQueue(array $queryParams = []): array
    {
        $date = trim((string) ($queryParams['date'] ?? date('Y-m-d')));
        $from = trim((string) ($queryParams['from'] ?? $date));
        $to = trim((string) ($queryParams['to'] ?? $from));
        $statusBayar = trim((string) ($queryParams['status_bayar'] ?? ''));

        $sql = "
            SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.no_rkm_medis, p.nm_pasien, pol.nm_poli,
                   pj.png_jawab, r.status_bayar, r.status_lanjut
            FROM reg_periksa r
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.tgl_registrasi BETWEEN :from AND :to AND r.stts != 'Batal'
        ";
        $params = [':from' => $from, ':to' => $to];
        if ($statusBayar !== '') {
            $sql .= " AND r.status_bayar = :sb";
            $params[':sb'] = $statusBayar;
        }
        $sql .= " ORDER BY r.tgl_registrasi DESC, r.no_reg ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->jsonResponse($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDetail(array $queryParams = []): array
    {
        $nr = trim((string) ($queryParams['no_rawat'] ?? ''));
        if ($nr === '') {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Nomor rawat wajib dipilih.'], 422);
        }

        try {
            $items = $this->ledgerRepo->findByNoRawat(new NoRawat($nr));
            $summary = ['Tunai' => 0.0, 'Kapitasi' => 0.0, 'Non-Kapitasi' => 0.0, 'Piutang' => 0.0];
            $mapped = [];

            foreach ($items as $item) {
                if ($item->status->value() !== 'Cancelled') {
                    $summary[$item->claimType->value()] += $item->totalAmount;
                }
                $mapped[] = [
                    'id' => $item->id,
                    'item_code' => $item->itemCode,
                    'item_name' => $item->itemName,
                    'category' => $item->category,
                    'qty' => $item->qty,
                    'unit_price' => $item->unitPrice,
                    'total_amount' => $item->totalAmount,
                    'claim_type' => $item->claimType->value(),
                    'status' => $item->status->value(),
                ];
            }

            return $this->jsonResponse([
                'no_rawat' => $nr,
                'items' => $mapped,
                'summary' => $summary,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiAddLedgerItem(array $postData = []): array
    {
        try {
            $qty = (float) ($postData['qty'] ?? 1);
            $price = (float) ($postData['unit_price'] ?? 0);

            $this->ledgerRepo->addItem(new BillingLedgerItem(
                id: null,
                noRawat: new NoRawat((string) ($postData['no_rawat'] ?? '')),
                itemCode: (string) ($postData['item_code'] ?? ''),
                itemName: (string) ($postData['item_name'] ?? ''),
                category: (string) ($postData['category'] ?? 'Tindakan'),
                qty: $qty,
                unitPrice: $price,
                totalAmount: $qty * $price,
                claimType: ClaimType::fromString((string) ($postData['claim_type'] ?? 'Tunai')),
                status: LedgerStatus::fromString('Pending'),
                providerId: (string) ($postData['provider_id'] ?? '')
            ));

            return $this->jsonResponse(['status' => 'success', 'message' => 'Item tagihan ditambahkan ke ledger.']);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiPay(array $postData = []): array
    {
        try {
            $res = $this->payUseCase->execute(new NoRawat((string) ($postData['no_rawat'] ?? '')));
            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Pembayaran Split Billing & Jurnal Akuntansi berhasil diproses.',
                'data' => $res,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiVoid(?UserAccount $currentUser, array $postData = [], string $ipAddress = ''): array
    {
        try {
            $res = $this->voidUseCase->execute(
                noRawat: new NoRawat((string) ($postData['no_rawat'] ?? '')),
                userId: (int) ($currentUser?->getId() ?? 0),
                username: $currentUser?->getUsername() ?? 'kasir',
                reason: (string) ($postData['reason'] ?? ''),
                ipAddress: $ipAddress
            );

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Void transaksi berhasil & Jurnal Pembalik diterbitkan.',
                'data' => $res,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiReconcile(?UserAccount $currentUser, array $postData = [], string $ipAddress = ''): array
    {
        try {
            $this->reconcileUseCase->execute(
                noRawat: new NoRawat((string) ($postData['no_rawat'] ?? '')),
                userId: (int) ($currentUser?->getId() ?? 0),
                username: $currentUser?->getUsername() ?? 'kasir',
                reason: (string) ($postData['reason'] ?? ''),
                ipAddress: $ipAddress
            );

            return $this->jsonResponse(['status' => 'success', 'message' => 'Order pending dibatalkan melalui rekonsiliasi sadar.']);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiPosApotekUmum(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $sale = $this->posUseCase->execute(
                items: (array) ($postData['items'] ?? []),
                paymentMethod: PosPaymentMethod::fromString((string) ($postData['payment_method'] ?? 'Tunai')),
                petugasNip: $currentUser?->getUsername() ?? 'APT',
                keterangan: (string) ($postData['keterangan'] ?? '')
            );

            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Transaksi POS Apotek Umum {$sale->noNota} berhasil.",
                'data' => [
                    'no_nota' => $sale->noNota,
                    'total' => $sale->total,
                    'payment_method' => $sale->paymentMethod->value(),
                ],
            ]);
        } catch (InsufficientStockException|\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSettlePiutang(array $postData = []): array
    {
        try {
            if ($this->settlePiutangUseCase === null) {
                throw new \DomainException('Layanan pelunasan piutang belum dikonfigurasi.');
            }
            $res = $this->settlePiutangUseCase->execute(new NoRawat((string) ($postData['no_rawat'] ?? '')));
            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Pelunasan piutang berhasil dibukukan ke Kas.',
                'data' => $res,
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cetak Kuitansi / Faktur Rincian Billing 4-Pilar (paritas ksmlite anyFaktur).
     *
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printFaktur(array $queryParams = []): array
    {
        $nr = trim((string) ($queryParams['no_rawat'] ?? ''));
        if ($nr === '') {
            return [
                'status' => 422,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Nomor rawat wajib diisi.</h3>',
            ];
        }

        $stmt = $this->pdo->prepare("
            SELECT r.no_rawat, r.tgl_registrasi, r.status_bayar, p.no_rkm_medis, p.nm_pasien,
                   pol.nm_poli, d.nm_dokter, pj.png_jawab
            FROM reg_periksa r
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.no_rawat = :nr
            LIMIT 1
        ");
        $stmt->execute([':nr' => $nr]);
        $visit = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($visit === false) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Data kunjungan tidak ditemukan.</h3>',
            ];
        }

        $items = $this->ledgerRepo->findByNoRawat(new NoRawat($nr));
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rowsHtml = '';
        $summary = ['Tunai' => 0.0, 'Kapitasi' => 0.0, 'Non-Kapitasi' => 0.0, 'Piutang' => 0.0];
        foreach ($items as $it) {
            if ($it->status->value() === 'Cancelled' || $it->status->value() === 'Void') {
                continue;
            }
            $claim = $it->claimType->value();
            if (isset($summary[$claim])) {
                $summary[$claim] += $it->totalAmount;
            }
            $rowsHtml .= '<tr>'
                . '<td>' . $e($it->itemName) . '</td>'
                . '<td>' . $e($it->category) . '</td>'
                . '<td style="text-align:center">' . $e($it->qty) . '</td>'
                . '<td>' . $e($claim) . '</td>'
                . '<td style="text-align:right">Rp ' . number_format($it->totalAmount, 0, ',', '.') . '</td>'
                . '<td>' . $e($it->status->value()) . '</td>'
                . '</tr>';
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Faktur ' . $e($nr) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:20px;max-width:780px}h2{margin:0 0 4px;color:#00897b}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px}th{background:#f1f5f9;text-align:left}.sum{margin-top:12px;font-size:13px;border:1px solid #ccc;padding:10px;background:#fafafa}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Kuitansi / Faktur</button></div>'
            . '<h2>KUITANSI &amp; RINCIAN BILLING 4-PILAR</h2>'
            . '<div style="font-size:13px;">'
            . 'No. Rawat: <strong>' . $e($nr) . '</strong> | Tgl. Reg: ' . $e($visit['tgl_registrasi'] ?? '') . ' | Status: <strong>' . $e($visit['status_bayar'] ?? '-') . '</strong><br>'
            . 'Pasien: <strong>' . $e($visit['nm_pasien'] ?? '-') . '</strong> (' . $e($visit['no_rkm_medis'] ?? '-') . ')<br>'
            . 'Poli / Dokter: ' . $e($visit['nm_poli'] ?? '-') . ' / ' . $e($visit['nm_dokter'] ?? '-') . ' | Penjamin: ' . $e($visit['png_jawab'] ?? '-')
            . '</div>'
            . '<table><thead><tr><th>Item / Layanan</th><th>Kategori</th><th>Qty</th><th>Pilar</th><th style="text-align:right">Total</th><th>Status</th></tr></thead><tbody>'
            . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="6">Belum ada item tagihan.</td></tr>')
            . '</tbody></table>'
            . '<div class="sum">'
            . '<strong>Ringkasan 4-Pilar:</strong> '
            . '[T] Tunai: <strong>Rp ' . number_format($summary['Tunai'], 0, ',', '.') . '</strong> &bull; '
            . '[K] Kapitasi: <strong>Rp ' . number_format($summary['Kapitasi'], 0, ',', '.') . '</strong> &bull; '
            . '[NK] Non-Kapitasi: <strong>Rp ' . number_format($summary['Non-Kapitasi'], 0, ',', '.') . '</strong> &bull; '
            . '[P] Piutang: <strong>Rp ' . number_format($summary['Piutang'], 0, ',', '.') . '</strong>'
            . '</div>'
            . '<div style="margin-top:28px;text-align:right;font-size:13px;">Petugas Kasir<br><br><br>(__________________________)</div>'
            . '</body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    /**
     * @param mixed $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function jsonResponse(mixed $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($data, JSON_THROW_ON_ERROR),
        ];
    }
}
