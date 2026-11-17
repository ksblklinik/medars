<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Ranap\UseCase\ManageKamarInapUseCase;
use App\Domain\Ranap\Repository\RawatInapRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class RawatInapController
{
    public function __construct(
        private RawatInapRepositoryInterface $repo,
        private ManageKamarInapUseCase $useCase,
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

        $body = $this->renderer->render('rawat_inap/index', [
            'judul' => 'Rawat Inap & Manajemen Kamar',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/rawat-inap',
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
    public function apiListInpatients(array $query = []): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listActiveInpatients($q)]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListKamar(array $query = []): array
    {
        $status = trim((string) ($query['status'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listKamar($status)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveKamar(array $payload): array
    {
        try {
            $this->repo->saveKamar($payload);
            return $this->jsonResponse(['status' => 'success', 'message' => 'Data kamar berhasil disimpan.']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCheckIn(array $payload): array
    {
        try {
            $this->useCase->checkIn(
                new NoRawat(trim((string) ($payload['no_rawat'] ?? ''))),
                trim((string) ($payload['kd_kamar'] ?? ''))
            );
            return $this->jsonResponse(['status' => 'success', 'message' => 'Pasien berhasil masuk kamar rawat inap.']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCheckOut(array $payload): array
    {
        try {
            $res = $this->useCase->checkOut(
                new NoRawat(trim((string) ($payload['no_rawat'] ?? ''))),
                trim((string) ($payload['kd_kamar'] ?? '')),
                trim((string) ($payload['stts_pulang'] ?? 'Membaik')) ?: 'Membaik',
                trim((string) ($payload['claim_type'] ?? 'Tunai')) ?: 'Tunai'
            );
            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Checkout selesai ({$res['lama']} hari, biaya kamar Rp " . number_format($res['ttl_biaya'], 0, ',', '.') . ').',
                'data' => $res,
            ]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
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
