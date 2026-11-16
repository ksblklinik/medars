<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\Http;

use App\Application\Registration\UseCase\DeleteMasterPasienUseCase;
use App\Application\Registration\UseCase\SaveMasterPasienUseCase;
use App\Infrastructure\Database\PdoTransactionManager;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoPasienRepository;
use App\Presentation\Http\PasienController;
use App\Presentation\View\ViewRenderer;
use PDO;
use PHPUnit\Framework\TestCase;

class PasienControllerTest extends TestCase
{
    private PDO $pdo;
    private PasienController $controller;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();

        $repo = new PdoPasienRepository($this->pdo);
        $tx = new PdoTransactionManager($this->pdo);
        $renderer = new ViewRenderer(dirname(__DIR__, 4) . '/src/Presentation/View/templates');

        $this->controller = new PasienController(
            $repo,
            new SaveMasterPasienUseCase($repo, $tx),
            new DeleteMasterPasienUseCase($repo, $tx),
            $renderer,
        );
    }

    public function testIndexListSaveCardAndDeleteMasterPasien(): void
    {
        $page = $this->controller->index(null, 'csrf-123');
        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Master Pasien', $page['body']);

        $saveRes = $this->controller->apiSave([
            'nm_pasien' => 'Ahmad Fauzi',
            'no_ktp' => '3201010101850001',
            'jk' => 'L',
            'tgl_lahir' => '1985-04-10',
            'alamat' => 'Jl. Sudirman 5',
            'no_tlp' => '081299998888',
        ]);
        $this->assertSame(200, $saveRes['status']);
        $saved = json_decode($saveRes['body'], true);
        $this->assertSame('000001', $saved['pasien']['no_rkm_medis']);

        $listRes = $this->controller->apiList(['q' => 'Ahmad']);
        $this->assertSame(200, $listRes['status']);
        $list = json_decode($listRes['body'], true);
        $this->assertSame(1, $list['total']);

        $cardRes = $this->controller->cetakKartu(['no_rkm_medis' => '000001']);
        $this->assertSame(200, $cardRes['status']);
        $this->assertStringContainsString('Ahmad Fauzi', $cardRes['body']);
        $this->assertStringContainsString('000001', $cardRes['body']);

        $delRes = $this->controller->apiDelete(['no_rkm_medis' => '000001']);
        $this->assertSame(200, $delRes['status']);
    }
}
