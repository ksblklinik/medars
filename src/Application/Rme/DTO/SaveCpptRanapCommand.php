<?php

declare(strict_types=1);

namespace App\Application\Rme\DTO;

use App\Domain\Registration\ValueObject\NoRawat;

final readonly class SaveCpptRanapCommand
{
    private const ALLOWED_FIELDS = [
        'no_rawat', 'tgl_perawatan', 'jam_rawat',
        'suhu_tubuh', 'tensi', 'nadi', 'respirasi',
        'tinggi', 'berat', 'spo2', 'gcs', 'kesadaran',
        'keluhan', 'pemeriksaan', 'alergi',
        'rtl', 'penilaian', 'instruksi', 'evaluasi',
    ];

    public function __construct(
        public NoRawat $noRawat,
        public string $tglPerawatan,
        public string $jamRawat,
        public string $suhuTubuh = '',
        public string $tensi = '',
        public string $nadi = '',
        public string $respirasi = '',
        public string $tinggi = '',
        public string $berat = '',
        public string $spo2 = '',
        public string $gcs = '',
        public string $kesadaran = 'Compos Mentis',
        public string $keluhan = '',
        public string $pemeriksaan = '',
        public string $alergi = '',
        public string $rtl = '',
        public string $penilaian = '',
        public string $instruksi = '',
        public string $evaluasi = '',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromWhitelistedPayload(array $payload): self
    {
        foreach (array_keys($payload) as $key) {
            if (!in_array((string) $key, self::ALLOWED_FIELDS, true)) {
                throw new \InvalidArgumentException("Field tidak diizinkan pada payload CPPT Ranap: {$key}");
            }
        }

        $tgl = trim((string) ($payload['tgl_perawatan'] ?? ''));
        if ($tgl === '') {
            $tgl = date('Y-m-d');
        } else {
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $tgl);
            if ($dt === false || $dt->format('Y-m-d') !== $tgl) {
                throw new \InvalidArgumentException('Format tgl_perawatan tidak valid (wajib YYYY-MM-DD).');
            }
        }

        $jam = trim((string) ($payload['jam_rawat'] ?? ''));
        if ($jam === '') {
            $jam = date('H:i:s');
        }

        return new self(
            noRawat: new NoRawat((string) ($payload['no_rawat'] ?? '')),
            tglPerawatan: $tgl,
            jamRawat: $jam,
            suhuTubuh: (string) ($payload['suhu_tubuh'] ?? ''),
            tensi: (string) ($payload['tensi'] ?? ''),
            nadi: (string) ($payload['nadi'] ?? ''),
            respirasi: (string) ($payload['respirasi'] ?? ''),
            tinggi: (string) ($payload['tinggi'] ?? ''),
            berat: (string) ($payload['berat'] ?? ''),
            spo2: (string) ($payload['spo2'] ?? ''),
            gcs: (string) ($payload['gcs'] ?? ''),
            kesadaran: (string) ($payload['kesadaran'] ?? 'Compos Mentis'),
            keluhan: (string) ($payload['keluhan'] ?? ''),
            pemeriksaan: (string) ($payload['pemeriksaan'] ?? ''),
            alergi: (string) ($payload['alergi'] ?? ''),
            rtl: (string) ($payload['rtl'] ?? ''),
            penilaian: (string) ($payload['penilaian'] ?? ''),
            instruksi: (string) ($payload['instruksi'] ?? ''),
            evaluasi: (string) ($payload['evaluasi'] ?? ''),
        );
    }
}
