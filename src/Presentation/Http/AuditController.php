<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Audit\Service\DataIntegrityAuditService;
use App\Domain\Audit\Service\LogRotationService;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class AuditController
{
    public function __construct(
        private DataIntegrityAuditService $integrityService,
        private LogRotationService $rotationService,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {}

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('audit/index', [
            'judul' => 'Audit Keamanan & Integritas Data (ACID)',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'audit',
            'module_icon' => 'fa-shield-halved',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Auditor'),
            'user_role' => $currentUser?->getRole() ?? 'admin',
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
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiOrphanCheck(): array
    {
        $audit = $this->integrityService->auditOrphans();
        return $this->json(['ok' => true, 'audit' => $audit]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBillingBalance(array $query): array
    {
        $noRawat = trim((string) ($query['no_rawat'] ?? ''));
        if ($noRawat === '') {
            return $this->json(['ok' => false, 'error' => 'No rawat wajib diisi.'], 422);
        }

        $balance = $this->integrityService->auditBillingLedgerBalance($noRawat);
        return $this->json(['ok' => true, 'balance' => $balance]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiStockBalance(array $query): array
    {
        $kodeBrng = trim((string) ($query['kode_brng'] ?? ''));
        $kdBangsal = trim((string) ($query['kd_bangsal'] ?? 'FAR01'));
        if ($kodeBrng === '') {
            return $this->json(['ok' => false, 'error' => 'Kode barang wajib diisi.'], 422);
        }

        $stock = $this->integrityService->auditStockBalance($kodeBrng, $kdBangsal);
        return $this->json(['ok' => true, 'stock' => $stock]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiRotateLogs(array $payload): array
    {
        try {
            $days = (int) ($payload['retention_days'] ?? LogRotationService::MIN_RETENTION_DAYS);
            $pruned = $this->rotationService->pruneLogs($days);

            return $this->json([
                'ok' => true,
                'pruned_count' => $pruned,
                'retention_days' => $days,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function json(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => (string) json_encode($data, JSON_UNESCAPED_UNICODE),
        ];
    }
}
