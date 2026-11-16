<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use App\Infrastructure\Database\Exception\ReadOnlyConnectionException;
use App\Infrastructure\Database\ReadOnlyConnectionGuard;
use PHPUnit\Framework\TestCase;

final class ReadOnlyConnectionGuardTest extends TestCase
{
    private \PDO $pdo;
    private ReadOnlyConnectionGuard $guard;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        $this->pdo->exec('CREATE TABLE pasien (no_rkm_medis TEXT PRIMARY KEY, nm_pasien TEXT)');
        $this->pdo->exec("INSERT INTO pasien (no_rkm_medis, nm_pasien) VALUES ('000123', 'Budi Santoso')");

        $this->guard = new ReadOnlyConnectionGuard($this->pdo);
    }

    public function test_allows_select_queries_on_legacy_connection(): void
    {
        $result = $this->guard->query("SELECT * FROM pasien WHERE no_rkm_medis = '000123'");
        $this->assertNotEmpty($result);
        $this->assertSame('000123', $result[0]['no_rkm_medis']);
        $this->assertSame('Budi Santoso', $result[0]['nm_pasien']);
    }

    public function test_blocks_insert_query_on_legacy_connection(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);
        $this->expectExceptionMessage('Operasi mutasi dilarang pada koneksi basis data legacy (READ-ONLY)');

        $this->guard->execute("INSERT INTO pasien (no_rkm_medis, nm_pasien) VALUES ('000124', 'Ilegal Pasien')");
    }

    public function test_blocks_update_query_on_legacy_connection(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);
        $this->expectExceptionMessage('Operasi mutasi dilarang pada koneksi basis data legacy (READ-ONLY)');

        $this->guard->execute("UPDATE pasien SET nm_pasien = 'Perubahan Ilegal' WHERE no_rkm_medis = '000123'");
    }

    public function test_blocks_delete_query_on_legacy_connection(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);
        $this->expectExceptionMessage('Operasi mutasi dilarang pada koneksi basis data legacy (READ-ONLY)');

        $this->guard->execute("DELETE FROM pasien WHERE no_rkm_medis = '000123'");
    }

    public function test_blocks_drop_or_alter_query_on_legacy_connection(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);

        $this->guard->execute("DROP TABLE pasien");
    }

    public function test_blocks_comment_obfuscated_mutation_query(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);

        $this->guard->execute("/* bypass attempt */ DELETE FROM pasien WHERE no_rkm_medis = '000123'");
    }

    public function test_blocks_line_comment_obfuscated_mutation_query(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);

        $this->guard->execute("-- comment\nUPDATE pasien SET nm_pasien = 'X'");
    }

    public function test_blocks_multi_statement_sql_injection_attempt(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);

        $this->guard->query("SELECT * FROM pasien; DROP TABLE pasien;");
    }

    public function test_blocks_cte_with_mutation_keyword(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);

        $this->guard->execute("WITH target AS (SELECT no_rkm_medis FROM pasien) DELETE FROM pasien WHERE no_rkm_medis IN (SELECT no_rkm_medis FROM target)");
    }

    public function test_blocks_select_into_outfile(): void
    {
        $this->expectException(ReadOnlyConnectionException::class);

        $this->guard->query("SELECT * FROM pasien INTO OUTFILE '/tmp/dump.csv'");
    }

    public function test_allows_select_containing_mutation_words_inside_string_literal(): void
    {
        $result = $this->guard->query("SELECT * FROM pasien WHERE nm_pasien = 'DELETE; DROP TABLE'");
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }
}
