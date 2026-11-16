<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Domain\Setting\Service\SettingService;
use App\Infrastructure\Security\CsrfGuard;
use App\Infrastructure\Security\RbacGuard;
use App\Presentation\View\ViewRenderer;

final class SettingsController
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly CsrfGuard $csrfGuard,
        private readonly RbacGuard $rbacGuard,
        private readonly AuditLogRepositoryInterface $auditLogRepo,
        private readonly ViewRenderer $viewRenderer,
    ) {
    }

    public function index(?UserAccount $currentUser): Response
    {
        $this->rbacGuard->assertCanAccessModule($currentUser, 'settings');

        $general = $this->settingService->getAll('general');
        $pendaftaran = $this->settingService->getAll('pendaftaran');
        $farmasi = $this->settingService->getAll('farmasi');
        $bpjs = $this->settingService->getAll('bpjs');
        $csrfInput = $this->csrfGuard->tokenInputHtml();

        $body = $this->viewRenderer->render('settings/index', [
            'judul' => 'Pengaturan Sistem',
            'current_module' => 'settings',
            'general' => $general,
            'pendaftaran' => $pendaftaran,
            'farmasi' => $farmasi,
            'bpjs' => $bpjs,
            'csrf_input' => $csrfInput,
            'navigations' => [
                ['title' => 'Dashboard', 'icon' => 'fa-gauge', 'route' => '/'],
                ['title' => 'Pendaftaran', 'icon' => 'fa-user-plus', 'route' => '/registration'],
                ['title' => 'Pengguna', 'icon' => 'fa-users', 'route' => '/users'],
                ['title' => 'Pengaturan', 'icon' => 'fa-gear', 'route' => '/settings'],
            ],
        ]);

        return Response::html($body, 200);
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function save(?UserAccount $currentUser, array $postData, string $ipAddress = ''): Response
    {
        $this->rbacGuard->assertCanAccessModule($currentUser, 'settings');

        $token = (string) ($postData['_csrf_token'] ?? '');
        if (!$this->csrfGuard->validateToken($token)) {
            return Response::html('Token CSRF tidak valid.', 403);
        }

        $settings = (array) ($postData['settings'] ?? []);

        foreach ($settings as $module => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            foreach ($fields as $field => $val) {
                $this->settingService->set((string) $module, (string) $field, (string) $val);
            }
        }

        $this->auditLogRepo->log(
            $currentUser?->getId() ?? 0,
            $currentUser?->getUsername() ?? 'system',
            'SETTINGS_UPDATE',
            'Memperbarui konfigurasi sistem',
            $ipAddress
        );

        return Response::redirect('/settings');
    }
}
