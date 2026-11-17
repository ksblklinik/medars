<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Support\Repository\ManajemenAnalyticsRepositoryInterface;
use App\Domain\Support\Repository\OperationalReportRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class ManajemenController
{
    public function __construct(
        private ManajemenAnalyticsRepositoryInterface $repo,
        private OperationalReportRepositoryInterface $reportRepo,
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

        $body = $this->renderer->render('manajemen/index', [
            'judul' => 'Dashboard Eksekutif & LEAN Analytics Board',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/manajemen',
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
    public function apiDashboard(array $query = []): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? date('Y-m-01'))) ?: date('Y-m-01');
        $to = trim((string) ($query['to'] ?? $today)) ?: $today;

        return $this->jsonResponse([
            'status' => 'success',
            'period' => ['from' => $from, 'to' => $to],
            'kpi' => $this->repo->getExecutiveKpi($from, $to),
            'lean_poli' => $this->repo->getLeanPoliMetrics($from, $to),
            'daily_trend' => $this->repo->getDailyTrend($from, $to),
            'revenue_by_claim' => $this->reportRepo->getRevenueByClaimType($from, $to),
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
