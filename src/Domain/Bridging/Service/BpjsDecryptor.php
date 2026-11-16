<?php

declare(strict_types=1);

namespace App\Domain\Bridging\Service;

final class BpjsDecryptor
{
    public static function decrypt(
        string $encryptedResponse,
        string $consId,
        string $secretKey,
        string $timestamp,
    ): string {
        $keyString = $consId . $secretKey . $timestamp;
        $keyHash = hex2bin(hash('sha256', $keyString));
        if ($keyHash === false) {
            return '';
        }

        $iv = substr($keyHash, 0, 16);
        $raw = base64_decode($encryptedResponse, true);
        if ($raw === false) {
            return '';
        }

        $decrypted = openssl_decrypt($raw, 'AES-256-CBC', $keyHash, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            return '';
        }

        return LzString::decompressFromEncodedURIComponent($decrypted);
    }
}
