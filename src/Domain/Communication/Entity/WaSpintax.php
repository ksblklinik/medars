<?php

declare(strict_types=1);

namespace App\Domain\Communication\Entity;

final class WaSpintax
{
    public static function parse(string $text): string
    {
        $pattern = '/\{([^{}]+)\}/';

        while (preg_match($pattern, $text)) {
            $text = (string) preg_replace_callback($pattern, function (array $matches): string {
                $parts = explode('|', $matches[1]);
                return trim($parts[array_rand($parts)]);
            }, $text);
        }

        return $text;
    }
}
