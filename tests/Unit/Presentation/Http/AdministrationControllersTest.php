<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Repository\AuditLogRepositoryInterface;
use App\Domain\Security\Repository\UserRepositoryInterface;
use App\Domain\Setting\Repository\SettingRepositoryInterface;
use App\Domain\Setting\Service\SettingService;
use App\Infrastructure\Security\CsrfGuard;
use App\Infrastructure\Security\RbacGuard;
use App\Presentation\Http\SettingsController;
use App\Presentation\Http\UsersController;
use App\Presentation\View\ViewRenderer;
use PHPUnit\Framework\TestCase;

class AdministrationControllersTest extends TestCase
{
    private array $session;
    private SettingService $settingService;
    private CsrfGuard $csrfGuard;
    private RbacGuard $rbacGuard;
    private ViewRenderer $renderer;
    private UserRepositoryInterface $userRepo;
    private AuditLogRepositoryInterface $auditRepo;
    private array $users = [];
    private array $settingsStorage = [];

    protected function setUp(): void
    {
        $this->session = [
            'user_id' => 1,
            'username' => 'admin',
            'role' => 'admin',
        ];

        $adminUser = new UserAccount(
            id: 1,
            username: 'admin',
            passwordHash: 'hash',
            email: 'admin@medars.local',
            fullname: 'Admin Utama',
            role: 'admin',
            access: ['all'],
            cap: ['all'],
            status: '1'
        );

        $this->users = [
            1 => $adminUser,
            2 => new UserAccount(
                id: 2,
                username: 'staff1',
                passwordHash: 'hash',
                email: 'staff@medars.local',
                fullname: 'Staff Pendaftaran',
                role: 'staff',
                access: ['pendaftaran'],
                cap: ['POL01'],
                status: '1'
            ),
        ];

        $this->userRepo = new class($this->users) implements UserRepositoryInterface {
            public function __construct(public array &$users) {}
            public function findByUsername(string $username): ?UserAccount {
                foreach ($this->users as $u) {
                    if ($u->getUsername() === $username) return $u;
                }
                return null;
            }
            public function findById(int $id): ?UserAccount {
                return $this->users[$id] ?? null;
            }
            public function save(UserAccount $user): void {
                $id = $user->getId() ?? (count($this->users) + 1);
                $this->users[$id] = $user;
            }
            public function delete(int $id, int $currentUserId): void {
                if ($id === 1) throw new \App\Domain\Security\Exception\ForbiddenException('Anti-lockout superadmin');
                if ($id === $currentUserId) throw new \App\Domain\Security\Exception\ForbiddenException('Anti-lockout self');
                unset($this->users[$id]);
            }
            public function recordLoginSuccess(int $userId, string $ipAddress): void {}
            public function recordLoginFailure(string $username, string $ipAddress): void {}
            public function isRateLimited(string $username, string $ipAddress, int $maxAttempts = 5, int $decaySeconds = 300): bool { return false; }
            public function findAll(): array { return array_values($this->users); }
        };

        $this->auditRepo = new class implements AuditLogRepositoryInterface {
            public array $logs = [];
            public function log(int $userId, string $username, string $action, string $details, string $ipAddress): void {
                $this->logs[] = compact('userId', 'username', 'action', 'details', 'ipAddress');
            }
            public function getRecentLogs(int $limit = 50, ?string $action = null): array {
                return $this->logs;
            }
        };

        $this->settingsStorage = [
            'general.nama_instansi' => 'Klinik Medars',
        ];

        $settingRepo = new class($this->settingsStorage) implements SettingRepositoryInterface {
            public function __construct(public array &$storage) {}
            public function get(string $module, string $field): ?string {
                return $this->storage["{$module}.{$field}"] ?? null;
            }
            public function getAll(string $module): array {
                $result = [];
                $prefix = "{$module}.";
                foreach ($this->storage as $k => $v) {
                    if (str_starts_with($k, $prefix)) {
                        $result[substr($k, strlen($prefix))] = $v;
                    }
                }
                return $result;
            }
            public function set(string $module, string $field, string $value): void {
                $this->storage["{$module}.{$field}"] = $value;
            }
        };

        $this->settingService = new SettingService($settingRepo);
        $this->csrfGuard = new CsrfGuard($this->session);
        $this->rbacGuard = new RbacGuard();
        $this->renderer = new ViewRenderer();
    }

    public function testSettingsControllerIndexAndSave(): void
    {
        $controller = new SettingsController(
            settingService: $this->settingService,
            csrfGuard: $this->csrfGuard,
            rbacGuard: $this->rbacGuard,
            auditLogRepo: $this->auditRepo,
            viewRenderer: $this->renderer
        );

        $currentUser = $this->users[1];

        // 1. Index
        $res = $controller->index($currentUser);
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('Pengaturan Sistem', $res->getBody());

        // 2. Save
        $token = $this->csrfGuard->generateToken();
        $saveRes = $controller->save($currentUser, [
            '_csrf_token' => $token,
            'settings' => [
                'general' => [
                    'nama_instansi' => 'RS Ksm Next-Gen Sehat',
                    'alamat' => 'Jl. Kesehatan No. 1',
                ],
            ],
        ], '127.0.0.1');

        $this->assertSame(302, $saveRes->getStatusCode());
        $this->assertSame('RS Ksm Next-Gen Sehat', $this->settingService->get('general', 'nama_instansi'));
    }

    public function testUsersControllerSaveAndDelete(): void
    {
        $controller = new UsersController(
            userRepo: $this->userRepo,
            csrfGuard: $this->csrfGuard,
            rbacGuard: $this->rbacGuard,
            auditLogRepo: $this->auditRepo,
            viewRenderer: $this->renderer
        );

        $currentUser = $this->users[1];

        // 1. Index
        $indexRes = $controller->index($currentUser);
        $this->assertSame(200, $indexRes->getStatusCode());
        $this->assertStringContainsString('Manajemen Pengguna', $indexRes->getBody());

        // 2. Add New User
        $token = $this->csrfGuard->generateToken();
        $addRes = $controller->save($currentUser, [
            '_csrf_token' => $token,
            'username' => 'dokter_baru',
            'password' => 'PassDokter123!',
            'fullname' => 'dr. Baru Sp.A',
            'email' => 'dokter@medars.local',
            'role' => 'dokter',
            'access' => 'dokter_ralan, rekam_medis',
            'cap' => 'POL01',
            'status' => '1',
        ], '127.0.0.1');

        $this->assertSame(302, $addRes->getStatusCode());
        $created = $this->userRepo->findByUsername('dokter_baru');
        $this->assertNotNull($created);
        $this->assertSame('dr. Baru Sp.A', $created->getFullname());
        $this->assertTrue($created->verifyPassword('PassDokter123!'));

        // 3. Delete user 2 (staff1)
        $deleteRes = $controller->delete($currentUser, 2, $token, '127.0.0.1');
        $this->assertSame(302, $deleteRes->getStatusCode());
        $this->assertNull($this->userRepo->findById(2));
    }
}
