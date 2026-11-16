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

    public static function buildQrCheckinLinkText(string $noRawat, string $pasienName = ''): string
    {
        $greeting = $pasienName !== '' ? "a.n. {$pasienName}" : '';
        $template = "{Halo|Selamat pagi|Assalamu'alaikum|Salam sehat} {Admin|Petugas Pendaftaran|Klinik}, "
            . "{saya konfirmasi kedatangan|mohon verifikasi antrean|izin check-in} {$greeting} "
            . "untuk kode kunjungan {$noRawat}.";

        return preg_replace('/\s+/', ' ', trim(self::parse($template))) ?? '';
    }
}
