<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Domain\Security\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\CsrfGuard;
use App\Infrastructure\Security\RbacGuard;
use App\Presentation\View\ViewRenderer;

final class UsersController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly CsrfGuard $csrfGuard,
        private readonly RbacGuard $rbacGuard,
        private readonly AuditLogRepositoryInterface $auditLogRepo,
        private readonly ViewRenderer $viewRenderer,
    ) {
    }

    public function index(?UserAccount $currentUser): Response
    {
        $this->rbacGuard->assertCanAccessModule($currentUser, 'users');

        $users = $this->userRepo->findAll();
        $csrfInput = $this->csrfGuard->tokenInputHtml();
        $csrfToken = $this->csrfGuard->getToken();
        $navBuilder = new NavigationBuilder();

        $body = $this->viewRenderer->render('users/index', [
            'judul' => 'Manajemen Pengguna & Hak Akses',
            'current_module' => 'users',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Petugas Medis'),
            'user_role' => $currentUser?->getRole() ?? 'Medis',
            'users' => $users,
            'csrf_input' => $csrfInput,
            'csrf_token' => $csrfToken,
            'navigations' => $navBuilder->buildForUser($currentUser),
        ]);

        return Response::html($body, 200);
    }

    /**
     * @param array<string, mixed> $postData
     */
    public function save(?UserAccount $currentUser, array $postData, string $ipAddress = ''): Response
    {
        $this->rbacGuard->assertCanAccessModule($currentUser, 'users');

        $token = (string) ($postData['_csrf_token'] ?? '');
        if (!$this->csrfGuard->validateToken($token)) {
            return Response::html('Token CSRF tidak valid.', 403);
        }

        $id = !empty($postData['id']) ? (int) $postData['id'] : null;
        $username = trim((string) ($postData['username'] ?? ''));
        $rawPassword = (string) ($postData['password'] ?? '');
        $fullname = trim((string) ($postData['fullname'] ?? ''));
        $email = trim((string) ($postData['email'] ?? ''));
        $role = trim((string) ($postData['role'] ?? 'staff'));
        $accessRaw = $postData['access'] ?? '';
        $capRaw = $postData['cap'] ?? '';
        $avatar = (string) ($postData['avatar'] ?? '');
        $status = (string) ($postData['status'] ?? '1');

        // Anti-Self-Demotion Lockout untuk Superadmin Utama (ID: 1)
        if ($id === 1) {
            $role = 'admin';
            $status = '1';
        }

        $existing = $id !== null ? $this->userRepo->findById($id) : null;

        if ($rawPassword !== '') {
            $passwordHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        } elseif ($existing !== null) {
            $passwordHash = $existing->getPasswordHash();
        } else {
            $passwordHash = password_hash('Medars1234!', PASSWORD_BCRYPT);
        }

        $user = UserAccount::fromLegacy(
            id: $id,
            username: $username,
            passwordHash: $passwordHash,
            email: $email,
            fullname: $fullname,
            role: $role,
            accessRaw: $accessRaw,
            capRaw: $capRaw,
            avatar: $avatar,
            status: $status
        );

        $this->userRepo->save($user);

        $action = $id === null ? 'USER_CREATE' : 'USER_UPDATE';
        $this->auditLogRepo->log(
            $currentUser?->getId() ?? 0,
            $currentUser?->getUsername() ?? 'system',
            $action,
            "Menyimpan akun pengguna: {$username} (Role: {$role})",
            $ipAddress
        );

        return Response::redirect('/users');
    }

    public function delete(?UserAccount $currentUser, int $id, string $csrfToken, string $ipAddress = ''): Response
    {
        $this->rbacGuard->assertCanAccessModule($currentUser, 'users');

        if (!$this->csrfGuard->validateToken($csrfToken)) {
            return Response::html('Token CSRF tidak valid.', 403);
        }

        $target = $this->userRepo->findById($id);
        $targetName = $target?->getUsername() ?? (string) $id;

        $this->userRepo->delete($id, (int) ($currentUser?->getId() ?? 0));

        $this->auditLogRepo->log(
            $currentUser?->getId() ?? 0,
            $currentUser?->getUsername() ?? 'system',
            'USER_DELETE',
            "Menghapus pengguna: {$targetName} (ID: {$id})",
            $ipAddress
        );

        return Response::redirect('/users');
    }
}
