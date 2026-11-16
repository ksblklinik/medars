<?php

declare(strict_types=1);

namespace App\Domain\Communication\Entity;

final class HomeCarePrivacyShield
{
    /**
     * De-identifies patient information for academic CI supervisor (e.g. "Ny. S*** (56 Th, P)").
     */
    public static function pseudonymize(string $nama, int $umur, string $jk): string
    {
        $cleanName = trim($nama);
        $firstChar = $cleanName !== '' ? mb_substr($cleanName, 0, 1) : 'X';
        $maskedName = strtoupper($firstChar) . '***';

        $gender = strtoupper(trim($jk)) === 'L' ? 'L' : 'P';

        if ($umur < 18) {
            $title = 'An.';
        } else {
            $title = $gender === 'L' ? 'Tn.' : 'Ny.';
        }

        return "{$title} {$maskedName} ({$umur} Th, {$gender})";
    }
}
