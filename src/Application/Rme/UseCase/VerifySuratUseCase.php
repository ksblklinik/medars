<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Rme\Repository\SuratMedisRepositoryInterface;

final readonly class VerifySuratUseCase
{
    public function __construct(private SuratMedisRepositoryInterface $repo)
    {
    }

    public static function encodeHash(string $type, string $noRawat, string $noSurat): string
    {
        $raw = strtolower(trim($type)) . '|' . trim($noRawat) . '|' . trim($noSurat);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @return array{valid: bool, title: string, type: string, data: array<string, mixed>|null, message: string}
     */
    public function verifyByHash(string $hash): array
    {
        $clean = trim($hash);
        if ($clean === '') {
            return $this->notFound('Kode verifikasi kosong.');
        }

        $b64 = strtr($clean, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($b64, true);
        if ($decoded === false || !str_contains($decoded, '|')) {
            return $this->notFound('Format token verifikasi tidak valid.');
        }

        $parts = explode('|', $decoded);
        $type = strtolower(trim($parts[0] ?? ''));
        $noRawat = trim($parts[1] ?? '');
        $noSurat = trim($parts[2] ?? '');

        if ($noSurat === '') {
            return $this->notFound('Nomor surat tidak tercantum dalam token.');
        }

        $res = $this->verifyDirect($type, $noSurat);
        if ($res['valid'] && $noRawat !== '' && isset($res['data']['no_rawat'])) {
            if ((string) $res['data']['no_rawat'] !== $noRawat) {
                return $this->notFound('Nomor rawat tidak cocok dengan dokumen.');
            }
        }
        return $res;
    }

    /**
     * @return array{valid: bool, title: string, type: string, data: array<string, mixed>|null, message: string}
     */
    public function verifyDirect(string $type, string $noSurat): array
    {
        $cleanType = strtolower(trim($type));
        $cleanNo = trim($noSurat);
        if (!in_array($cleanType, ['sakit', 'hamil', 'narkoba'], true) || $cleanNo === '') {
            return $this->notFound('Jenis atau nomor surat tidak valid.');
        }

        $row = $this->repo->findSuratForPrint($cleanType, $cleanNo);
        if ($row === null) {
            return $this->notFound('Dokumen surat medis tidak ditemukan atau tidak terdaftar.');
        }

        $title = match ($cleanType) {
            'hamil' => 'Surat Keterangan Pemeriksaan Kehamilan',
            'narkoba' => 'Surat Keterangan Bebas Narkoba',
            default => 'Surat Keterangan Sakit',
        };

        return [
            'valid' => true,
            'title' => $title,
            'type' => $cleanType,
            'data' => $row,
            'message' => 'Dokumen Terverifikasi Asli.',
        ];
    }

    /**
     * @return array{valid: bool, title: string, type: string, data: null, message: string}
     */
    private function notFound(string $message): array
    {
        return [
            'valid' => false,
            'title' => 'Verifikasi Gagal',
            'type' => '',
            'data' => null,
            'message' => $message,
        ];
    }
}
