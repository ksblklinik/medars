<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Database;

use App\Infrastructure\Database\PdoTransactionManager;
use PHPUnit\Framework\TestCase;

final class TransactionManagerTest extends TestCase
{
    private \PDO $pdo;
    private PdoTransactionManager $transactionManager;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Buat tabel testing
        $this->pdo->exec('CREATE TABLE test_ledger (id INTEGER PRIMARY KEY, note TEXT, amount REAL)');
        
        $this->transactionManager = new PdoTransactionManager($this->pdo);
    }

    public function test_transaction_commits_successfully_when_callback_succeeds(): void
    {
        $result = $this->transactionManager->transaction(function () {
            $stmt = $this->pdo->prepare('INSERT INTO test_ledger (note, amount) VALUES (?, ?)');
            $stmt->execute(['Pendaftaran Kasir', 50000.0]);
            return 'SUCCESS';
        });

        $this->assertSame('SUCCESS', $result);

        $stmt = $this->pdo->query('SELECT COUNT(*) as cnt FROM test_ledger');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['cnt']);
    }

    public function test_transaction_rolls_back_entirely_when_callback_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Simulasi kegagalan di sub-operasi transaksi');

        try {
            $this->transactionManager->transaction(function () {
                $stmt = $this->pdo->prepare('INSERT INTO test_ledger (note, amount) VALUES (?, ?)');
                $stmt->execute(['Tagihan Pertama', 25000.0]);

                // Simulasi error di tengah jalan (misal validasi buku besar gagal)
                throw new \RuntimeException('Simulasi kegagalan di sub-operasi transaksi');
            });
        } finally {
            // Verifikasi data di-rollback total (tidak ada data menggantung)
            $stmt = $this->pdo->query('SELECT COUNT(*) as cnt FROM test_ledger');
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->assertSame(0, (int) $row['cnt'], 'Record harus 0 karena transaksi dibatalkan (rollback).');
        }
    }

    public function test_supports_nested_transactions_using_savepoints(): void
    {
        $this->transactionManager->transaction(function () {
            // Operasi level 1
            $stmt = $this->pdo->prepare('INSERT INTO test_ledger (note, amount) VALUES (?, ?)');
            $stmt->execute(['Registrasi Pasien', 10000.0]);

            // Operasi level 2 (nested transaction)
            $this->transactionManager->transaction(function () {
                $stmt2 = $this->pdo->prepare('INSERT INTO test_ledger (note, amount) VALUES (?, ?)');
                $stmt2->execute(['Biaya Cetak Kartu', 5000.0]);
            });
        });

        $stmt = $this->pdo->query('SELECT COUNT(*) as cnt FROM test_ledger');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(2, (int) $row['cnt'], 'Kedua operasi level bersarang harus berhasil tersimpan.');
    }

    public function test_nested_transaction_failure_rolls_back_to_savepoint(): void
    {
        $this->transactionManager->transaction(function () {
            // Operasi utama berhasil
            $stmt = $this->pdo->prepare('INSERT INTO test_ledger (note, amount) VALUES (?, ?)');
            $stmt->execute(['Registrasi Pasien Utama', 10000.0]);

            try {
                // Operasi opsional/cabang gagal di sub-level
                $this->transactionManager->transaction(function () {
                    $stmt2 = $this->pdo->prepare('INSERT INTO test_ledger (note, amount) VALUES (?, ?)');
                    $stmt2->execute(['Biaya Tambahan Gagal', 5000.0]);
                    throw new \RuntimeException('Sub-transaksi gagal');
                });
            } catch (\RuntimeException) {
                // Tangani kegagalan parsial
            }
        });

        $stmt = $this->pdo->query('SELECT COUNT(*) as cnt FROM test_ledger');
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(1, (int) $row['cnt'], 'Hanya transaksi level utama yang tersimpan, sub-transaksi di-rollback via savepoint.');
    }
}
