<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Billing\Repository\KeuanganRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class KeuanganController
{
    public function __construct(
        private KeuanganRepositoryInterface $repo,
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

        $body = $this->renderer->render('keuangan/index', [
            'judul' => 'Keuangan, Jurnal Umum & Laba Rugi',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/keuangan',
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
    public function apiOverview(array $query = []): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today)) ?: $today;
        $to = trim((string) ($query['to'] ?? $from)) ?: $from;

        return $this->jsonResponse([
            'status' => 'success',
            'period' => ['from' => $from, 'to' => $to],
            'jurnal' => $this->repo->listJurnal($from, $to),
            'buku_besar' => $this->repo->getBukuBesar($from, $to),
            'laba_rugi' => $this->repo->getLabaRugi($from, $to),
            'rekening' => $this->repo->listRekening(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiPostJurnal(array $payload): array
    {
        try {
            $noJurnal = $this->repo->postManualJurnal(
                (string) ($payload['no_bukti'] ?? ''),
                (string) ($payload['keterangan'] ?? ''),
                (string) ($payload['kd_debet'] ?? ''),
                (string) ($payload['kd_kredit'] ?? ''),
                (float) ($payload['nominal'] ?? 0)
            );
            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Jurnal {$noJurnal} berhasil dibukukan.",
                'data' => ['no_jurnal' => $noJurnal],
            ]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveRekening(array $payload): array
    {
        try {
            $this->repo->saveRekening(
                (string) ($payload['kd_rek'] ?? ''),
                (string) ($payload['nm_rek'] ?? ''),
                (string) ($payload['tipe'] ?? 'Aset'),
                (string) ($payload['balance'] ?? 'D')
            );
            return $this->jsonResponse(['status' => 'success', 'message' => 'Akun rekening (COA) berhasil disimpan.']);
        } catch (\InvalidArgumentException $e) {
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
