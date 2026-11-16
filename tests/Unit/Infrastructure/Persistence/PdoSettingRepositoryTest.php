<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Persistence\PdoAuditLogRepository;
use App\Infrastructure\Persistence\PdoSettingRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoSettingRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoSettingRepository $settingRepo;
    private PdoAuditLogRepository $auditRepo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE mlite_settings (
                module TEXT NOT NULL,
                field TEXT NOT NULL,
                value TEXT NOT NULL,
                PRIMARY KEY (module, field)
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

        $this->settingRepo = new PdoSettingRepository($this->pdo);
        $this->auditRepo = new PdoAuditLogRepository($this->pdo);
    }

    public function testSetAndGetSetting(): void
    {
        $this->settingRepo->set('general', 'nama_instansi', 'Klinik Medars Pratama');
        $this->settingRepo->set('general', 'alamat', 'Jl. Sudirman 100');

        $this->assertSame('Klinik Medars Pratama', $this->settingRepo->get('general', 'nama_instansi'));
        $this->assertSame('Jl. Sudirman 100', $this->settingRepo->get('general', 'alamat'));
        $this->assertNull($this->settingRepo->get('general', 'non_existing'));

        // Update value
        $this->settingRepo->set('general', 'nama_instansi', 'RS Ksm Next-Gen');
        $this->assertSame('RS Ksm Next-Gen', $this->settingRepo->get('general', 'nama_instansi'));

        // Get all
        $all = $this->settingRepo->getAll('general');
        $this->assertCount(2, $all);
        $this->assertSame('RS Ksm Next-Gen', $all['nama_instansi']);
    }

    public function testAuditLogRecentListingAndFilter(): void
    {
        $this->auditRepo->log(1, 'admin', 'LOGIN', 'Admin login sukses', '127.0.0.1');
        $this->auditRepo->log(1, 'admin', 'SETTINGS_UPDATE', 'Ubah nama instansi', '127.0.0.1');
        $this->auditRepo->log(2, 'kasir1', 'TRANSACTION', 'Input tagihan rawat jalan', '192.168.1.50');

        $logs = $this->auditRepo->getRecentLogs(limit: 10);
        $this->assertCount(3, $logs);

        // Filter by action
        $filtered = $this->auditRepo->getRecentLogs(limit: 10, action: 'SETTINGS_UPDATE');
        $this->assertCount(1, $filtered);
        $this->assertSame('SETTINGS_UPDATE', $filtered[0]['action']);
    }
}
