<?php

declare(strict_types=1);

namespace App\Domain\Pharmacy\ValueObject;

final readonly class DepoBangsal
{
    public string $kdBangsal;

    public function __construct(string $kdBangsal)
    {
        $trimmed = strtoupper(trim($kdBangsal));
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Kode depo bangsal tidak boleh kosong.');
        }

        $this->kdBangsal = $trimmed;
    }

    public function value(): string
    {
        return $this->kdBangsal;
    }

    public function isApotekUmum(): bool
    {
        return $this->kdBangsal === 'APT';
    }

    public function isIgd(): bool
    {
        return $this->kdBangsal === 'IGD';
    }
}
