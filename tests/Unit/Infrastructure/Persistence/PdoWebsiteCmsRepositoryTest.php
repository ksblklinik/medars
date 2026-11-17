<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoWebsiteCmsRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoWebsiteCmsRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoWebsiteCmsRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoWebsiteCmsRepository($this->pdo);
    }

    public function testSaveAndListPublishedWebsitePages(): void
    {
        $saved = $this->repo->savePage([
            'slug' => 'layanan-unggulan',
            'title' => 'Layanan Unggulan Klinik',
            'content_html' => '<p>Poli Umum, Gigi, KIA, &amp; Homecare 24 Jam.</p>',
            'is_published' => 1,
        ]);

        $this->assertSame('layanan-unggulan', $saved['slug']);
        $this->assertCount(1, $this->repo->listPages(true));
        $this->assertNotNull($this->repo->findBySlug('layanan-unggulan'));
    }
}
