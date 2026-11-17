<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Bridging\UseCase;

use App\Application\Bridging\UseCase\FetchIcareHistoryUseCase;
use App\Domain\Bridging\Repository\IcareRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class FetchIcareHistoryUseCaseTest extends TestCase
{
    public function testExecuteReturnsIcareUrlAndHandlesMissingParticipant(): void
    {
        $repo = new class () implements IcareRepositoryInterface {
            public function findVisitParticipant(string $noRawat): ?array
            {
                if ($noRawat === '2026/11/17/000040') {
                    return [
                        'no_rawat' => $noRawat,
                        'no_peserta' => '0001234567890',
                        'no_ktp' => '3201010101900001',
                        'nm_pasien' => 'Dian Pratama',
                        'kd_dokter' => 'DR01',
                        'nm_dokter' => 'dr. Andi',
                    ];
                }
                return null;
            }
        };

        $uc = new FetchIcareHistoryUseCase($repo);
        $ok = $uc->execute('2026/11/17/000040');
        self::assertTrue($ok['ok']);
        self::assertSame('0001234567890', $ok['no_peserta']);
        self::assertStringContainsString('0001234567890', $ok['url']);

        $notFound = $uc->execute('9999/01/01/000001');
        self::assertFalse($notFound['ok']);
        self::assertSame(404, $notFound['status_code']);
    }
}
