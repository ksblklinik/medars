<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

final class PdoTransactionManager implements TransactionManagerInterface
{
    private int $transactionLevel = 0;

    public function __construct(
        private readonly \PDO $pdo
    ) {}

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT trans_sp_{$this->transactionLevel}");
        }

        $this->transactionLevel++;
    }

    public function commit(): void
    {
        if ($this->transactionLevel <= 0) {
            return;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            $this->pdo->commit();
        } else {
            $this->pdo->exec("RELEASE SAVEPOINT trans_sp_{$this->transactionLevel}");
        }
    }

    public function rollBack(): void
    {
        if ($this->transactionLevel <= 0) {
            return;
        }

        $this->transactionLevel--;

        if ($this->transactionLevel === 0) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } else {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT trans_sp_{$this->transactionLevel}");
        }
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0 || $this->pdo->inTransaction();
    }
}
