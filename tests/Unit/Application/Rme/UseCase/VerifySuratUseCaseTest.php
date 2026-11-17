<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Rme\UseCase;

use App\Application\Rme\UseCase\VerifySuratUseCase;
use App\Domain\Rme\Entity\RujukanInternal;
use App\Domain\Rme\Entity\SuratBebasNarkoba;
use App\Domain\Rme\Entity\SuratHamil;
use App\Domain\Rme\Entity\SuratSakit;
use App\Domain\Rme\Repository\SuratMedisRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class VerifySuratUseCaseTest extends TestCase
{
    public function testVerifyByHashAndDirectLookup(): void
    {
        $repo = new class () implements SuratMedisRepositoryInterface {
            public function getNextSuratNumber(string $prefix, string $table): string
            {
                return $prefix . '001';
            }

            public function saveSuratSakit(SuratSakit $surat): void
            {
            }

            public function saveSuratHamil(SuratHamil $surat): void
            {
            }

            public function saveSuratBebasNarkoba(SuratBebasNarkoba $surat): void
            {
            }

            public function saveRujukanInternal(RujukanInternal $rujukan): void
            {
            }

            public function listSurat(string $type, string $q = ''): array
            {
                return [];
            }

            public function findSuratForPrint(string $type, string $noSurat): ?array
            {
                if ($type === 'sakit' && $noSurat === 'SKS-2026-001') {
                    return [
                        'no_surat' => 'SKS-2026-001',
                        'no_rawat' => '2026/05/01/000001',
                        'nm_pasien' => 'Budi Santoso',
                        'nm_dokter' => 'dr. Andi',
                        'tanggal_mulai' => '2026-05-01',
                    ];
                }
                return null;
            }

            public function listRujukanInternal(string $q = ''): array
            {
                return [];
            }
        };

        $useCase = new VerifySuratUseCase($repo);

        // 1. Valid URL-safe base64 hash (ksmlite format: jenis|no_rawat|no_surat)
        $hash = VerifySuratUseCase::encodeHash('sakit', '2026/05/01/000001', 'SKS-2026-001');
        $res = $useCase->verifyByHash($hash);
        self::assertTrue($res['valid']);
        self::assertSame('Surat Keterangan Sakit', $res['title']);
        self::assertSame('Budi Santoso', $res['data']['nm_pasien']);

        // 2. Direct lookup
        $direct = $useCase->verifyDirect('sakit', 'SKS-2026-001');
        self::assertTrue($direct['valid']);

        // 3. Invalid hash / unknown surat
        $invalid = $useCase->verifyByHash('invalid-base64');
        self::assertFalse($invalid['valid']);
        self::assertNull($invalid['data']);
    }
}
