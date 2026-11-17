<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Registration\Repository\FrontOfficeRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class FrontOfficeController
{
    public function __construct(
        private FrontOfficeRepositoryInterface $repo,
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

        $body = $this->renderer->render('frontoffice/index', [
            'judul' => 'Front Office & Gatekeeper Antrean',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/frontoffice',
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
        $date = trim((string) ($query['date'] ?? date('Y-m-d')));
        if ($date === '') {
            $date = date('Y-m-d');
        }

        return $this->jsonResponse([
            'status' => 'success',
            'date' => $date,
            'stats' => $this->repo->getTodayStats($date),
            'bookings' => $this->repo->listTodayBookings($date),
            'visits' => $this->repo->listTodayVisits($date),
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
