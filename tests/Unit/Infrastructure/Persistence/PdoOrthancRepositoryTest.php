<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoOrthancRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoOrthancRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoOrthancRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoOrthancRepository($this->pdo);
    }

    public function testSaveSearchUpdateInterpretationAndDeleteStudy(): void
    {
        $saved = $this->repo->saveStudy([
            'no_rawat' => '2026/11/17/000001',
            'study_uid' => '1.2.840.113619.2.55.3.604688119',
            'modality' => 'CR',
            'study_description' => 'Thorax PA',
            'patient_name' => 'Budi Santoso',
            'tgl_periksa' => '2026-11-17',
            'viewer_url' => 'http://localhost:8042/ohif/viewer?StudyInstanceUIDs=1.2.840.113619.2.55.3.604688119',
        ]);
        $id = (int) $saved['id'];

        $list = $this->repo->listStudies('Thorax');
        self::assertCount(1, $list);

        self::assertTrue($this->repo->updateHasilBaca($id, 'Cor dan pulmo dalam batas normal.', 'dr. Rad, Sp.Rad'));
        $updated = $this->repo->listStudies('Budi');
        self::assertSame('Cor dan pulmo dalam batas normal.', $updated[0]['hasil_baca']);

        self::assertTrue($this->repo->deleteStudy($id));
        self::assertSame([], $this->repo->listStudies());
    }
}
