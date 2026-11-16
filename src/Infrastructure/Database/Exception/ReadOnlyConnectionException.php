<?php

declare(strict_types=1);

namespace App\Infrastructure\Database\Exception;

final class ReadOnlyConnectionException extends \RuntimeException
{
    public static function mutationBlocked(string $query): self
    {
        return new self("Operasi mutasi dilarang pada koneksi basis data legacy (READ-ONLY). Query yang ditolak: {$query}");
    }
}
