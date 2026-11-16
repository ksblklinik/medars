<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Infrastructure\Persistence\PdoAuditLogRepository;
use App\Infrastructure\Persistence\PdoUserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoUserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoUserRepository $userRepo;
    private PdoAuditLogRepository $auditRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Skema mlite_users sesuai ksmlite
        $this->pdo->exec("
            CREATE TABLE mlite_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                email TEXT NOT NULL,
                no_telp TEXT DEFAULT '',
                fullname TEXT NOT NULL,
                role TEXT NOT NULL,
                cap TEXT DEFAULT '',
                access TEXT DEFAULT '',
                avatar TEXT DEFAULT '',
                status TEXT DEFAULT '1',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE mlite_login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                attempt_time INTEGER NOT NULL
            );

            CREATE TABLE mlite_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                username TEXT NOT NULL,
                action TEXT NOT NULL,
                details TEXT NOT NULL,
                ip_address TEXT NOT NULL,
                created_at TEXT NOT NULL
            );
        ");

        $this->userRepo = new PdoUserRepository($this->pdo);
        $this->auditRepo = new PdoAuditLogRepository($this->pdo);
    }

    public function testSaveAndFindUser(): void
    {
        $hash = password_hash('PassKsm2026', PASSWORD_BCRYPT);
        $user = new UserAccount(
            id: null,
            username: 'loket1',
            passwordHash: $hash,
            email: 'loket1@medars.local',
            fullname: 'Petugas Loket 1',
            role: 'staff',
            access: ['pendaftaran', 'pasien'],
            cap: ['POL01'],
            avatar: '',
            status: '1'
        );

        $this->userRepo->save($user);

        $fetched = $this->userRepo->findByUsername('loket1');
        $this->assertNotNull($fetched);
        $this->assertNotNull($fetched->getId());
        $this->assertSame('loket1', $fetched->getUsername());
        $this->assertSame('Petugas Loket 1', $fetched->getFullname());
        $this->assertTrue($fetched->verifyPassword('PassKsm2026'));
        $this->assertTrue($fetched->canAccessModule('pendaftaran'));
        $this->assertFalse($fetched->canAccessModule('kasir'));
        $this->assertTrue($fetched->canAccessPoli('POL01'));

        $fetchedById = $this->userRepo->findById($fetched->getId());
        $this->assertNotNull($fetchedById);
        $this->assertSame('loket1', $fetchedById->getUsername());
    }

    public function testUpdateExistingUser(): void
    {
        $hash = password_hash('OldPass123', PASSWORD_BCRYPT);
        $user = new UserAccount(
            id: null,
            username: 'farmasi1',
            passwordHash: $hash,
            email: 'farmasi@medars.local',
            fullname: 'Apoteker Joko',
            role: 'farmasi',
            access: ['farmasi'],
            cap: ['all'],
            avatar: '',
            status: '1'
        );
        $this->userRepo->save($user);
        $saved = $this->userRepo->findByUsername('farmasi1');

        $newHash = password_hash('NewPass456', PASSWORD_BCRYPT);
        $updatedUser = new UserAccount(
            id: $saved->getId(),
            username: 'farmasi1',
            passwordHash: $newHash,
            email: 'joko.apotek@medars.local',
            fullname: 'Apoteker Joko Widodo, S.Farm',
            role: 'farmasi',
            access: ['farmasi', 'rawat_jalan'],
            cap: ['all'],
            avatar: 'joko.jpg',
            status: '1'
        );
        $this->userRepo->save($updatedUser);

        $reFetched = $this->userRepo->findById($saved->getId());
        $this->assertSame('Apoteker Joko Widodo, S.Farm', $reFetched->getFullname());
        $this->assertTrue($reFetched->verifyPassword('NewPass456'));
        $this->assertTrue($reFetched->canAccessModule('rawat_jalan'));
    }

    public function testAntiLockoutPreventsDeletingSuperadmin(): void
    {
        // Masukkan superadmin ID 1
        $this->pdo->exec("
            INSERT INTO mlite_users (id, username, password, email, fullname, role, status)
            VALUES (1, 'admin', 'hash', 'admin@medars.local', 'Super Admin', 'admin', '1')
        ");

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Superadmin utama (ID: 1) dilindungi dan tidak dapat dihapus.');

        $this->userRepo->delete(1, currentUserId: 2);
    }

    public function testAntiLockoutPreventsDeletingSelf(): void
    {
        // Masukkan user ID 3
        $this->pdo->exec("
            INSERT INTO mlite_users (id, username, password, email, fullname, role, status)
            VALUES (3, 'user3', 'hash', 'u3@medars.local', 'User 3', 'staff', '1')
        ");

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');

        $this->userRepo->delete(3, currentUserId: 3);
    }

    public function testRateLimitingAndFailureRecording(): void
    {
        $ip = '10.0.0.5';
        $username = 'dokter_spesialis';

        $this->assertFalse($this->userRepo->isRateLimited($username, $ip, maxAttempts: 3, decaySeconds: 60));

        $this->userRepo->recordLoginFailure($username, $ip);
        $this->userRepo->recordLoginFailure($username, $ip);
        $this->assertFalse($this->userRepo->isRateLimited($username, $ip, maxAttempts: 3, decaySeconds: 60));

        $this->userRepo->recordLoginFailure($username, $ip);
        $this->assertTrue($this->userRepo->isRateLimited($username, $ip, maxAttempts: 3, decaySeconds: 60));

        // Setelah login success, percobaan gagal dibersihkan
        $this->userRepo->recordLoginSuccess(10, $ip);
        $this->assertFalse($this->userRepo->isRateLimited($username, $ip, maxAttempts: 3, decaySeconds: 60));
    }

    public function testAuditLogPersistsToDatabase(): void
    {
        $this->auditRepo->log(
            userId: 1,
            username: 'admin',
            action: 'USER_CREATE',
            details: 'Menambahkan user baru: dokter1',
            ipAddress: '192.168.1.15'
        );

        $stmt = $this->pdo->query("SELECT * FROM mlite_logs WHERE user_id = 1");
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotEmpty($log);
        $this->assertSame('USER_CREATE', $log['action']);
        $this->assertSame('Menambahkan user baru: dokter1', $log['details']);
        $this->assertSame('192.168.1.15', $log['ip_address']);
    }
}
