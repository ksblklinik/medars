<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoSmartAgentRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoSmartAgentRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoSmartAgentRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoSmartAgentRepository($this->pdo);
    }

    public function testKnowledgeVaultAndModelProfiles(): void
    {
        $doc = $this->repo->saveKnowledge([
            'title' => 'PDT Demam Dengue',
            'category' => 'PDT',
            'icd10_code' => 'A90',
            'keywords' => 'demam tinggi trombositopenia dengue',
            'content' => 'Rehidrasi oral/IV, pantau hematokrit dan trombosit berkala.',
            'created_by' => 'dr.andi',
        ]);
        $id = (int) $doc['id'];
        self::assertCount(1, $this->repo->listKnowledge('demam', 'PDT'));

        $profile = $this->repo->saveProfile([
            'name' => 'vLLM Lokal RTX',
            'provider' => 'vllm-local',
            'endpoint_url' => 'http://127.0.0.1:8000/v1',
            'model_name' => 'Qwen/Qwen2.5-7B-Instruct',
        ]);
        self::assertSame('vllm-local', $profile['provider']);
        self::assertCount(1, $this->repo->listProfiles());

        self::assertTrue($this->repo->deleteKnowledge($id));
        self::assertSame([], $this->repo->listKnowledge());
    }
}
