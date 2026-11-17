<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\PenjualanRepositoryInterface;
use App\Presentation\View\ViewRenderer;
use Throwable;

final readonly class PenjualanController
{
    public function __construct(
        private PenjualanRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
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

        $body = $this->renderer->render('penjualan/index', [
            'judul' => 'Penjualan Non-Resep & Alkes',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/penjualan',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBarangList(array $query = []): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listBarang($q)]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBarangSave(array $post): array
    {
        $nama = trim((string) ($post['nama_barang'] ?? ''));
        if ($nama === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Nama barang wajib diisi.']);
        }
        $saved = $this->repo->saveBarang($post);
        return $this->json(200, ['status' => 'success', 'data' => $saved]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiOrders(array $query = []): array
    {
        $from = trim((string) ($query['from'] ?? date('Y-m-d')));
        $to = trim((string) ($query['to'] ?? date('Y-m-d')));
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listOrders($from, $to),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiOrderDetail(array $query = []): array
    {
        $id = (int) ($query['id'] ?? 0);
        $detail = $id > 0 ? $this->repo->getOrderDetail($id) : null;
        if ($detail === null) {
            return $this->json(404, ['status' => 'error', 'message' => 'Order penjualan tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $detail]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiOrderCreate(?UserAccount $actor, array $post): array
    {
        $post['id_user'] = $actor?->getUsername() ?? 'system';
        $order = $this->repo->createOrder($post);
        return $this->json(201, ['status' => 'success', 'data' => $order]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiAddItem(?UserAccount $actor, array $post): array
    {
        $idPenjualan = (int) ($post['id_penjualan'] ?? 0);
        $idBarang = (int) ($post['id_barang'] ?? 0);
        $jumlah = (int) ($post['jumlah'] ?? 1);
        if ($idPenjualan <= 0 || $idBarang <= 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID penjualan dan barang wajib diisi.']);
        }
        try {
            $item = $this->repo->addItem($idPenjualan, $idBarang, $jumlah, $actor?->getUsername() ?? 'system');
            return $this->json(201, ['status' => 'success', 'data' => $item]);
        } catch (Throwable $e) {
            return $this->json(422, ['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiRemoveItem(array $post): array
    {
        $detailId = (int) ($post['detail_id'] ?? 0);
        if ($detailId <= 0 || !$this->repo->removeItem($detailId)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Item penjualan tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Item dihapus & stok dikembalikan.']);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSettle(?UserAccount $actor, array $post): array
    {
        $idPenjualan = (int) ($post['id_penjualan'] ?? 0);
        if ($idPenjualan <= 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID penjualan tidak valid.']);
        }
        $billing = $this->repo->settleOrder(
            $idPenjualan,
            (float) ($post['potongan'] ?? 0),
            (float) ($post['jumlah_bayar'] ?? 0),
            trim((string) ($post['cara_bayar'] ?? 'Tunai')),
            $actor?->getUsername() ?? 'system'
        );
        return $this->json(200, ['status' => 'success', 'data' => $billing]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiReport(array $query = []): array
    {
        $from = trim((string) ($query['from'] ?? date('Y-m-01')));
        $to = trim((string) ($query['to'] ?? date('Y-m-d')));
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->getTurnoverReport($from, $to),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printFaktur(array $query = []): array
    {
        $id = (int) ($query['id'] ?? 0);
        $detail = $id > 0 ? $this->repo->getOrderDetail($id) : null;
        if ($detail === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Faktur penjualan tidak ditemukan.</h3>',
            ];
        }

        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $h = $detail['header'];
        $items = $detail['items'];
        $b = $detail['billing'];

        $rowsHtml = '';
        foreach ($items as $it) {
            $rowsHtml .= '<tr><td>' . $e($it['nama_barang']) . '</td><td align="right">' . (int) $it['jumlah']
                . '</td><td align="right">Rp ' . number_format((float) $it['harga'], 0, ',', '.')
                . '</td><td align="right">Rp ' . number_format((float) $it['harga_total'], 0, ',', '.') . '</td></tr>';
        }

        $total = $b !== null ? (float) $b['jumlah_harus_bayar'] : array_sum(array_map(static fn (array $i): float => (float) $i['harga_total'], $items));
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Faktur Penjualan #' . $id . '</title>'
            . '<style>body{font-family:monospace;max-width:480px;margin:20px auto;font-size:13px;}table{width:100%;border-collapse:collapse;}th,td{padding:4px;border-bottom:1px dashed #ccc;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:10px;"><button onclick="window.print()">Cetak Faktur</button></div>'
            . '<h3 style="text-align:center;margin:0;">FAKTUR PENJUALAN NON-RESEP</h3>'
            . '<div style="text-align:center;">No. Order #' . $id . ' &middot; ' . $e($h['tanggal']) . ' ' . $e($h['jam']) . '</div><hr>'
            . '<div>Pembeli: <strong>' . $e($h['nama_pembeli']) . '</strong> (' . $e($h['status']) . ')</div><br>'
            . '<table><thead><tr><th align="left">Barang</th><th align="right">Qty</th><th align="right">Harga</th><th align="right">Total</th></tr></thead><tbody>'
            . $rowsHtml . '</tbody></table><hr>'
            . '<div style="text-align:right;font-size:15px;"><strong>Total Bayar: Rp ' . number_format($total, 0, ',', '.') . '</strong></div>'
            . ($b !== null ? '<div style="text-align:right;">Cara Bayar: ' . $e($b['cara_bayar']) . '</div>' : '')
            . '</body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function json(int $status, array $payload): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
