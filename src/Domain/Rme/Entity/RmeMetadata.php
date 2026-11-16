<?php

declare(strict_types=1);

namespace App\Domain\Rme\Entity;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class RmeMetadata
{
    public function __construct(
        public NoRawat $noRawat,
        public string $tglPerawatan,
        public string $jamRawat,
        public string $metaKey,
        public string $metaValueJson,
        public bool $isMetaDirty = false,
    ) {
        if ($this->metaKey !== '' && $this->metaValueJson !== '' && $this->metaValueJson !== '{}') {
            json_decode($this->metaValueJson, true, 512, JSON_THROW_ON_ERROR);
        }
    }

    public function shouldPersist(): bool
    {
        if (!$this->isMetaDirty) {
            return false;
        }

        $key = trim($this->metaKey);
        $val = trim($this->metaValueJson);

        if ($key === '' || $val === '' || $val === '{}') {
            return false;
        }

        $decoded = json_decode($val, true);
        return is_array($decoded) && $decoded !== [];
    }
}
