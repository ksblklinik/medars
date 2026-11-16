<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

interface TransactionManagerInterface
{
    /**
     * Menjalankan operasi callback di dalam transaksi database atomik.
     * Otomatis melakukan commit jika berhasil, atau rollback jika terjadi exception.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollBack(): void;

    public function inTransaction(): bool;
}
