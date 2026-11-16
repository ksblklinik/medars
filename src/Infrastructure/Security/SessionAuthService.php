<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Closure;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\AccountDeactivatedException;
use App\Domain\Security\Exception\InvalidCredentialsException;
use App\Domain\Security\Exception\RateLimitExceededException;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Domain\Security\Repository\UserRepositoryInterface;

final class SessionAuthService
{
    private ?Closure $sessionRegenerator;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly AuditLogRepositoryInterface $auditLogRepository,
        private array &$session,
        ?callable $sessionRegenerator = null,
    ) {
        $this->sessionRegenerator = $sessionRegenerator !== null ? Closure::fromCallable($sessionRegenerator) : null;
    }

    public function login(string $username, string $password, string $ipAddress = ''): UserAccount
    {
        if ($this->userRepository->isRateLimited($username, $ipAddress)) {
            $this->auditLogRepository->log(
                0,
                $username,
                'LOGIN_BLOCKED',
                "Percobaan login diblokir karena melebihi batas percobaan (Rate Limited)",
                $ipAddress
            );
            throw new RateLimitExceededException('Terlalu banyak percobaan login yang gagal. Silakan coba kembali beberapa saat lagi.');
        }

        $user = $this->userRepository->findByUsername($username);

        if ($user === null || !$user->verifyPassword($password)) {
            $this->userRepository->recordLoginFailure($username, $ipAddress);
            $this->auditLogRepository->log(
                $user?->getId() ?? 0,
                $username,
                'LOGIN_FAILED',
                "Percobaan login gagal untuk username: {$username}",
                $ipAddress
            );
            throw new InvalidCredentialsException('Username atau kata sandi tidak valid.');
        }

        if (!$user->isActive()) {
            $this->auditLogRepository->log(
                $user->getId() ?? 0,
                $username,
                'LOGIN_REJECTED',
                "Percobaan login ditolak karena akun dinonaktifkan: {$username}",
                $ipAddress
            );
            throw new AccountDeactivatedException('Akun pengguna telah dinonaktifkan. Silakan hubungi Administrator.');
        }

        // Mitigasi Session Fixation
        if ($this->sessionRegenerator !== null) {
            ($this->sessionRegenerator)();
        } elseif (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->session['user_id'] = $user->getId();
        $this->session['username'] = $user->getUsername();
        $this->session['role'] = $user->getRole();
        $this->session['fullname'] = $user->getFullname();

        $this->userRepository->recordLoginSuccess($user->getId() ?? 0, $ipAddress);

        $this->auditLogRepository->log(
            $user->getId() ?? 0,
            $user->getUsername(),
            'LOGIN_SUCCESS',
            "Pengguna {$user->getUsername()} berhasil login",
            $ipAddress
        );

        return $user;
    }

    public function logout(string $ipAddress = ''): void
    {
        $userId = (int) ($this->session['user_id'] ?? 0);
        $username = (string) ($this->session['username'] ?? 'guest');

        if ($userId > 0) {
            $this->auditLogRepository->log(
                $userId,
                $username,
                'LOGOUT',
                "Pengguna {$username} melakukan logout",
                $ipAddress
            );
        }

        $this->session = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function currentUser(): ?UserAccount
    {
        $userId = $this->session['user_id'] ?? null;
        if (!is_int($userId) || $userId <= 0) {
            return null;
        }

        return $this->userRepository->findById($userId);
    }
}
