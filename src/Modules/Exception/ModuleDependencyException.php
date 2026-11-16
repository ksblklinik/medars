<?php

declare(strict_types=1);

namespace App\Modules\Exception;

final class ModuleDependencyException extends \RuntimeException
{
    public static function missingDependency(string $module, string $dependency): self
    {
        return new self("Prasyarat dependensi modul belum terpenuhi: {$dependency} (dibutuhkan oleh '{$module}')");
    }

    public static function circularDependency(string $cyclePath): self
    {
        return new self("Terdeteksi ketergantungan melingkar (circular dependency): {$cyclePath}");
    }
}
