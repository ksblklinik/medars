<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Repository\PresensiRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class PresensiController
{
    public function __construct(
        private PresensiRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(UserAccount $currentUser, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('presensi/index', [
            'judul' => 'Presensi & Absensi Shift Pegawai',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/presensi',
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
    public function apiList(array $query = []): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? date('Y-m-01'))) ?: date('Y-m-01');
        $to = trim((string) ($query['to'] ?? $today)) ?: $today;
        $nip = trim((string) ($query['nip'] ?? ''));

        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listPresensi($from, $to, $nip)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiClockIn(UserAccount $actor, array $payload): array
    {
        try {
            $nip = trim((string) ($payload['nip'] ?? $actor->getUsername())) ?: $actor->getUsername();
            $this->repo->clockIn(
                $nip,
                trim((string) ($payload['shift'] ?? 'Pagi')) ?: 'Pagi',
                trim((string) ($payload['lokasi'] ?? 'Klinik')) ?: 'Klinik',
                trim((string) ($payload['keterangan'] ?? ''))
            );
            return $this->jsonResponse(['status' => 'success', 'message' => "Absen masuk ({$nip}) berhasil dicatat."]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiClockOut(UserAccount $actor, array $payload): array
    {
        try {
            $nip = trim((string) ($payload['nip'] ?? $actor->getUsername())) ?: $actor->getUsername();
            $this->repo->clockOut($nip);
            return $this->jsonResponse(['status' => 'success', 'message' => "Absen pulang ({$nip}) berhasil dicatat."]);
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
