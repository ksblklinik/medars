<?php

declare(strict_types=1);

namespace App\Application\Registration\Exception;

final class PenjaminDisallowedException extends \DomainException
{
    public function __construct(string $kdPoli, string $kdPj)
    {
        parent::__construct("Penjamin '{$kdPj}' tidak diperbolehkan pada poliklinik '{$kdPoli}' sesuai pengaturan.");
    }
}
