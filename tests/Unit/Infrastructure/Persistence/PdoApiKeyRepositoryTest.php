<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoApiKeyRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoApiKeyRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoApiKeyRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoApiKeyRepository($this->pdo);
    }

    public function testCreateListVerifyToggleAndDeleteKey(): void
    {
        $created = $this->repo->createKey([
            'label' => 'Bridging Mobile JKN',
            'username' => 'jkn_service',
            'methods' => 'GET,POST',
            'ip_range' => '10.0.0.0/24',
            'exp_time' => '2030-12-31 23:59:59',
        ]);

        self::assertArrayHasKey('raw_key', $created);
        $rawKey = (string) $created['raw_key'];
        self::assertStringStartsWith('ksm_', $rawKey);
        self::assertGreaterThan(20, strlen($rawKey));

        // Verify plaintext is NOT stored in DB
        $row = $this->pdo->query('SELECT api_key_hash, key_prefix FROM mlite_api_keys LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        self::assertNotFalse($row);
        self::assertNotSame($rawKey, $row['api_key_hash']);
        self::assertSame(hash('sha256', $rawKey), $row['api_key_hash']);

        // List keys
        $list = $this->repo->listKeys('JKN');
        self::assertCount(1, $list);
        self::assertSame('Bridging Mobile JKN', $list[0]['label']);
        self::assertArrayNotHasKey('api_key_hash', $list[0]);

        // Verify raw key works while active
        $verified = $this->repo->verifyKey($rawKey);
        self::assertNotNull($verified);
        self::assertSame('jkn_service', $verified['username']);

        // Disable key
        $id = (int) $created['id'];
        self::assertTrue($this->repo->toggleActive($id, false));
        self::assertNull($this->repo->verifyKey($rawKey));

        // Re-enable and delete
        self::assertTrue($this->repo->toggleActive($id, true));
        self::assertNotNull($this->repo->verifyKey($rawKey));
        self::assertTrue($this->repo->deleteKey($id));
        self::assertSame([], $this->repo->listKeys());
    }
}
