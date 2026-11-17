<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoSatuSehatMappingRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoSatuSehatMappingRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoSatuSehatMappingRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoSatuSehatMappingRepository($this->pdo);
    }

    public function testSaveIhsMappingAndEnqueueFhirResources(): void
    {
        $map = $this->repo->saveMapping([
            'entity_type' => 'location',
            'local_code' => 'INT',
            'local_name' => 'Poli Penyakit Dalam',
            'ihs_id' => 'loc-ihs-10001',
        ]);
        $this->assertSame('loc-ihs-10001', $map['ihs_id']);
        $this->assertCount(1, $this->repo->listMappings('location'));

        $obs = $this->repo->enqueueFhirResource('Observation', '2026/11/17/000201', ['code' => '8480-6', 'value' => 120]);
        $med = $this->repo->enqueueFhirResource('MedicationRequest', '2026/11/17/000201', ['medication' => 'Paracetamol']);
        $srv = $this->repo->enqueueFhirResource('ServiceRequest', '2026/11/17/000201', ['code' => 'LAB-01']);

        $this->assertSame('Observation', $obs['resource_type']);
        $this->assertSame('MedicationRequest', $med['resource_type']);
        $this->assertSame('ServiceRequest', $srv['resource_type']);
    }
}
