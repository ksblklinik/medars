<?php

declare(strict_types=1);

namespace App\Domain\Rme\ValueObject;

final readonly class Kesadaran
{
    public const VALID_VALUES = ['Compos Mentis', 'Somnolence', 'Sopor', 'Coma'];

    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(?string $raw): self
    {
        $trimmed = trim((string) $raw);
        if ($trimmed === '') {
            return new self('Compos Mentis');
        }

        if (!in_array($trimmed, self::VALID_VALUES, true)) {
            throw new \InvalidArgumentException("Nilai kesadaran tidak valid: '{$trimmed}'.");
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }
}
