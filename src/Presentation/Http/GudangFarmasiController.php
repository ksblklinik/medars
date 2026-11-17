<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Pharmacy\Repository\GudangFarmasiRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class GudangFarmasiController
{
    public function __construct(
        private GudangFarmasiRepositoryInterface $repo,
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

        $body = $this->renderer->render('farmasi_gudang/index', [
            'judul' => 'Gudang Farmasi & Kartu Stok',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/farmasi-gudang',
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
    public function apiListBarang(array $query = []): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listBarang($q)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveBarang(array $payload): array
    {
        try {
            $this->repo->saveBarang($payload);
            return $this->jsonResponse(['status' => 'success', 'message' => 'Data obat/barang berhasil disimpan.']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiReceiveStock(?UserAccount $actor, array $payload): array
    {
        try {
            $this->repo->receiveStock(
                trim((string) ($payload['kode_brng'] ?? '')),
                trim((string) ($payload['kd_bangsal'] ?? 'FAR01')) ?: 'FAR01',
                (float) ($payload['qty'] ?? 0),
                trim((string) ($payload['no_batch'] ?? '')),
                trim((string) ($payload['no_faktur'] ?? '')),
                $actor?->getUsername() ?? 'APT',
                trim((string) ($payload['keterangan'] ?? 'Penerimaan Gudang'))
            );
            return $this->jsonResponse(['status' => 'success', 'message' => 'Penerimaan stok berhasil dicatat.']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiMutateStock(?UserAccount $actor, array $payload): array
    {
        try {
            $this->repo->mutateStock(
                trim((string) ($payload['kode_brng'] ?? '')),
                trim((string) ($payload['from_bangsal'] ?? 'FAR01')) ?: 'FAR01',
                trim((string) ($payload['to_bangsal'] ?? 'APT')) ?: 'APT',
                (float) ($payload['qty'] ?? 0),
                $actor?->getUsername() ?? 'APT',
                trim((string) ($payload['keterangan'] ?? 'Mutasi Antar Depo'))
            );
            return $this->jsonResponse(['status' => 'success', 'message' => 'Mutasi stok antar depo berhasil.']);
        } catch (InsufficientStockException|\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiKartuStok(array $query = []): array
    {
        return $this->jsonResponse([
            'status' => 'success',
            'data' => $this->repo->listKartuStok(
                trim((string) ($query['kode_brng'] ?? '')),
                trim((string) ($query['kd_bangsal'] ?? ''))
            ),
        ]);
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
