<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Security;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\AccountDeactivatedException;
use App\Domain\Security\Exception\InvalidCredentialsException;
use App\Domain\Security\Exception\RateLimitExceededException;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Domain\Security\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\SessionAuthService;
use PHPUnit\Framework\TestCase;

class SessionAuthServiceTest extends TestCase
{
    private array $sessionStorage;
    private UserRepositoryInterface $userRepository;
    private AuditLogRepositoryInterface $auditLogRepository;
    private SessionAuthService $authService;
    private bool $sessionRegenerated = false;

    protected function setUp(): void
    {
        $this->sessionStorage = [];
        $this->sessionRegenerated = false;

        $users = [
            'admin' => new UserAccount(
                id: 1,
                username: 'admin',
                passwordHash: password_hash('AdminKsm2026!', PASSWORD_BCRYPT),
                email: 'admin@medars.local',
                fullname: 'Super Admin',
                role: 'admin',
                access: ['all'],
                cap: ['all'],
                status: '1'
            ),
            'banned' => new UserAccount(
                id: 2,
                username: 'banned',
                passwordHash: password_hash('Pass1234!', PASSWORD_BCRYPT),
                email: 'banned@medars.local',
                fullname: 'Banned User',
                role: 'staff',
                access: ['all'],
                cap: [],
                status: '0'
            ),
        ];

        $failedAttempts = [];
        $auditLogs = [];

        $this->userRepository = new class($users, $failedAttempts) implements UserRepositoryInterface {
            public function __construct(public array $users, public array &$failedAttempts) {}

            public function findByUsername(string $username): ?UserAccount
            {
                return $this->users[$username] ?? null;
            }

            public function findById(int $id): ?UserAccount
            {
                foreach ($this->users as $u) {
                    if ($u->getId() === $id) {
                        return $u;
                    }
                }
                return null;
            }

            public function findAll(): array
            {
                return array_values($this->users);
            }

            public function save(UserAccount $user): void
            {
                $this->users[$user->getUsername()] = $user;
            }

            public function recordLoginSuccess(int $userId, string $ipAddress): void
            {
                // clear failure count
            }

            public function recordLoginFailure(string $username, string $ipAddress): void
            {
                $key = "{$username}_{$ipAddress}";
                $this->failedAttempts[$key] = ($this->failedAttempts[$key] ?? 0) + 1;
            }

            public function isRateLimited(string $username, string $ipAddress, int $maxAttempts = 5, int $decaySeconds = 300): bool
            {
                $key = "{$username}_{$ipAddress}";
                return ($this->failedAttempts[$key] ?? 0) >= $maxAttempts;
            }
        };

        $this->auditLogRepository = new class($auditLogs) implements AuditLogRepositoryInterface {
            public function __construct(public array &$auditLogs) {}

            public function log(int $userId, string $username, string $action, string $details, string $ipAddress): void
            {
                $this->auditLogs[] = [
                    'userId' => $userId,
                    'username' => $username,
                    'action' => $action,
                    'details' => $details,
                    'ipAddress' => $ipAddress,
                    'createdAt' => date('Y-m-d H:i:s'),
                ];
            }

            public function getRecentLogs(int $limit = 50, ?string $action = null): array
            {
                return $this->auditLogs;
            }
        };

        $sessionRegenerator = function (): void {
            $this->sessionRegenerated = true;
        };

        $this->authService = new SessionAuthService(
            userRepository: $this->userRepository,
            auditLogRepository: $this->auditLogRepository,
            session: $this->sessionStorage,
            sessionRegenerator: $sessionRegenerator
        );
    }

    public function testSuccessfulLogin(): void
    {
        $user = $this->authService->login('admin', 'AdminKsm2026!', '127.0.0.1');

        $this->assertSame('admin', $user->getUsername());
        $this->assertTrue($this->sessionRegenerated);
        $this->assertSame(1, $this->sessionStorage['user_id']);
        $this->assertSame('admin', $this->sessionStorage['username']);
        $this->assertSame('admin', $this->sessionStorage['role']);

        $currentUser = $this->authService->currentUser();
        $this->assertNotNull($currentUser);
        $this->assertSame('admin', $currentUser->getUsername());
    }

    public function testLoginWithWrongPasswordThrowsException(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Username atau kata sandi tidak valid.');

        $this->authService->login('admin', 'WrongPass', '127.0.0.1');
    }

    public function testLoginWithUnknownUsernameThrowsException(): void
    {
        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('Username atau kata sandi tidak valid.');

        $this->authService->login('unknown_user', 'AnyPass', '127.0.0.1');
    }

    public function testLoginWithDeactivatedAccountThrowsException(): void
    {
        $this->expectException(AccountDeactivatedException::class);
        $this->expectExceptionMessage('Akun pengguna telah dinonaktifkan.');

        $this->authService->login('banned', 'Pass1234!', '127.0.0.1');
    }

    public function testRateLimitingBlocksAfter5FailedAttempts(): void
    {
        $ip = '192.168.1.100';

        for ($i = 1; $i <= 5; $i++) {
            try {
                $this->authService->login('admin', 'WrongPassword', $ip);
            } catch (InvalidCredentialsException $e) {
                // Expected
            }
        }

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Terlalu banyak percobaan login yang gagal.');

        // Attempt ke-6 harus di-block
        $this->authService->login('admin', 'AdminKsm2026!', $ip);
    }

    public function testLogoutClearsSessionAndLogsAction(): void
    {
        $this->authService->login('admin', 'AdminKsm2026!', '127.0.0.1');
        $this->assertNotEmpty($this->sessionStorage);

        $this->authService->logout('127.0.0.1');
        $this->assertEmpty($this->sessionStorage);
        $this->assertNull($this->authService->currentUser());
    }
}
