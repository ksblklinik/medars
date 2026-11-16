<?php

declare(strict_types=1);

namespace App\Modules\Exception;

final class ModuleManifestException extends \InvalidArgumentException
{
    public static function invalidSlug(string $slug): self
    {
        return new self("Format slug modul harus kebab-case (hanya huruf kecil dan tanda hubung): '{$slug}'");
    }

    public static function invalidVersion(string $version): self
    {
        return new self("Format versi harus mengikuti Semantic Versioning (MAJOR.MINOR.PATCH): '{$version}'");
    }
}
