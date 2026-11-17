<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoWaCrmRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoWaCrmRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoWaCrmRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoWaCrmRepository($this->pdo);
    }

    public function testCsChatBroadcastTemplatesAndAiDraft(): void
    {
        $in = $this->repo->saveCsChat('628123456789', 'in', 'Halo admin, jadwal poli penyakit dalam hari ini jam berapa?', 'Budi');
        $this->assertSame('cs', $in['session_name']);
        $this->assertSame('in', $in['direction']);

        $draft = $this->repo->generateAiDraftReply($in['message']);
        $this->assertStringContainsString('poli', strtolower($draft));

        $out = $this->repo->saveCsChat('628123456789', 'out', $draft, 'CS Klinik');
        $this->assertSame('out', $out['direction']);
        $this->assertCount(2, $this->repo->listCsChats('628123456789'));

        $tpl = $this->repo->saveBroadcastTemplate([
            'title' => 'Pengingat Kontrol Besok',
            'session_name' => 'cs',
            'message_template' => 'Yth. {nama}, jangan lupa jadwal kontrol besok.',
            'schedule_at' => '2026-11-18 07:00',
            'target_filter' => 'kontrol_besok',
        ]);
        $this->assertSame('scheduled', $tpl['status']);
        $this->assertCount(1, $this->repo->listBroadcastTemplates());
    }
}
