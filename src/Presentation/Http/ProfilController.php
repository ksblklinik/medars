<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Repository\UserRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class ProfilController
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
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

        $body = $this->renderer->render('profil/index', [
            'judul' => 'Profil Pengguna & Keamanan Akun',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/profil',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiUpdateProfile(UserAccount $currentUser, array $payload): array
    {
        $fullname = trim((string) ($payload['fullname'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        if ($fullname === '') {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Nama lengkap wajib diisi.'], 422);
        }

        $updated = new UserAccount(
            id: $currentUser->getId(),
            username: $currentUser->getUsername(),
            passwordHash: $currentUser->getPasswordHash(),
            email: $email !== '' ? $email : $currentUser->getEmail(),
            fullname: $fullname,
            role: $currentUser->getRole(),
            access: $currentUser->getAccess(),
            cap: $currentUser->getCap(),
            avatar: $currentUser->getAvatar(),
            status: $currentUser->getStatus(),
        );
        $this->userRepo->save($updated);

        return $this->jsonResponse([
            'status' => 'success',
            'message' => 'Profil pengguna berhasil diperbarui.',
            'data' => ['fullname' => $fullname, 'email' => $updated->getEmail()],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiChangePassword(UserAccount $currentUser, array $payload): array
    {
        $current = (string) ($payload['current_password'] ?? '');
        $new = (string) ($payload['new_password'] ?? '');
        $confirm = (string) ($payload['confirm_password'] ?? '');

        if (!password_verify($current, $currentUser->getPasswordHash())) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Password saat ini tidak sesuai.'], 422);
        }
        if (strlen($new) < 8) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Password baru minimal 8 karakter.'], 422);
        }
        if ($new !== $confirm) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Konfirmasi password baru tidak cocok.'], 422);
        }

        $updated = new UserAccount(
            id: $currentUser->getId(),
            username: $currentUser->getUsername(),
            passwordHash: password_hash($new, PASSWORD_BCRYPT),
            email: $currentUser->getEmail(),
            fullname: $currentUser->getFullname(),
            role: $currentUser->getRole(),
            access: $currentUser->getAccess(),
            cap: $currentUser->getCap(),
            avatar: $currentUser->getAvatar(),
            status: $currentUser->getStatus(),
        );
        $this->userRepo->save($updated);

        return $this->jsonResponse(['status' => 'success', 'message' => 'Password berhasil diperbarui.']);
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
