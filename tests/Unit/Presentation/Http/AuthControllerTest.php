<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\InvalidCredentialsException;
use App\Domain\Security\Exception\RateLimitExceededException;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Domain\Security\Repository\UserRepositoryInterface;
use App\Infrastructure\Security\CsrfGuard;
use App\Infrastructure\Security\SessionAuthService;
use App\Presentation\Http\AuthController;
use App\Presentation\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class AuthControllerTest extends TestCase
{
    private array $session;
    private SessionAuthService $authService;
    private CsrfGuard $csrfGuard;
    private ViewRenderer $viewRenderer;
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->session = [];

        $user = new UserAccount(
            id: 1,
            username: 'admin',
            passwordHash: password_hash('AdminKsm2026!', PASSWORD_BCRYPT),
            email: 'admin@medars.local',
            fullname: 'Super Admin',
            role: 'admin',
            access: ['all'],
            cap: ['all'],
            status: '1'
        );

        $userRepo = new class($user) implements UserRepositoryInterface {
            public function __construct(private ?UserAccount $user) {}
            public function findByUsername(string $username): ?UserAccount {
                return $username === $this->user?->getUsername() ? $this->user : null;
            }
            public function findById(int $id): ?UserAccount {
                return $id === $this->user?->getId() ? $this->user : null;
            }
            public function findAll(): array {
                return $this->user ? [$this->user] : [];
            }
            public function save(UserAccount $user): void {}
            public function recordLoginSuccess(int $userId, string $ipAddress): void {}
            public function recordLoginFailure(string $username, string $ipAddress): void {}
            public function isRateLimited(string $username, string $ipAddress, int $maxAttempts = 5, int $decaySeconds = 300): bool {
                return false;
            }
        };

        $auditRepo = new class implements AuditLogRepositoryInterface {
            public function log(int $userId, string $username, string $action, string $details, string $ipAddress): void {}
            public function getRecentLogs(int $limit = 50, ?string $action = null): array { return []; }
        };

        $this->authService = new SessionAuthService($userRepo, $auditRepo, $this->session);
        $this->csrfGuard = new CsrfGuard($this->session);
        $this->viewRenderer = new ViewRenderer();

        $this->controller = new AuthController(
            authService: $this->authService,
            csrfGuard: $this->csrfGuard,
            viewRenderer: $this->viewRenderer
        );
    }

    public function testShowLoginRendersLoginPage(): void
    {
        $response = $this->controller->showLogin();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Login SIMRS', $response->getBody());
    }

    public function testPostLoginFailsWithInvalidCsrf(): void
    {
        $response = $this->controller->postLogin([
            'username' => 'admin',
            'password' => 'AdminKsm2026!',
            '_csrf_token' => 'invalid-token',
        ]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Token keamanan CSRF tidak valid', $response->getBody());
    }

    public function testPostLoginSuccessRedirects(): void
    {
        $token = $this->csrfGuard->generateToken();
        $response = $this->controller->postLogin([
            'username' => 'admin',
            'password' => 'AdminKsm2026!',
            '_csrf_token' => $token,
        ], '127.0.0.1');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/pendaftaran', $response->getHeader('Location'));
        $this->assertSame(1, $this->session['user_id']);
    }

    public function testPostLoginFailedShowsError(): void
    {
        $token = $this->csrfGuard->generateToken();
        $response = $this->controller->postLogin([
            'username' => 'admin',
            'password' => 'WrongPassword',
            '_csrf_token' => $token,
        ], '127.0.0.1');

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Username atau kata sandi tidak valid', $response->getBody());
    }

    public function testLogoutClearsSessionAndRedirects(): void
    {
        $this->session['user_id'] = 1;
        $response = $this->controller->logout('127.0.0.1');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/auth/login', $response->getHeader('Location'));
        $this->assertEmpty($this->session);
    }
}
