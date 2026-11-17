<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Rme\UseCase;

use App\Application\Rme\UseCase\RecommendClinicalPdtUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Entity\DiagnosaPasien;
use App\Domain\Rme\Repository\IcdGroundingRepositoryInterface;
use App\Domain\Support\Repository\SmartAgentRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class RecommendClinicalPdtUseCaseTest extends TestCase
{
    public function testRecommendReturnsKnowledgeMatchesAndGroundedIcd10(): void
    {
        $saRepo = new class () implements SmartAgentRepositoryInterface {
            public function listKnowledge(string $search = '', string $category = ''): array
            {
                if (stripos($search, 'demam') !== false) {
                    return [[
                        'id' => 1,
                        'title' => 'PDT Demam Berdarah Dengue',
                        'category' => 'PDT',
                        'icd10_code' => 'A91',
                        'content' => 'Monitor trombosit & hematokrit tiap 12 jam.',
                    ]];
                }
                return [];
            }

            public function saveKnowledge(array $data): array
            {
                return [];
            }

            public function deleteKnowledge(int $id): bool
            {
                return true;
            }

            public function listProfiles(): array
            {
                return [];
            }

            public function saveProfile(array $data): array
            {
                return [];
            }
        };

        $icdRepo = new class () implements IcdGroundingRepositoryInterface {
            public function searchSatuSehatIcd10(string $keyword, int $limit = 20): array
            {
                return [['kd_penyakit' => 'A91', 'nm_penyakit' => 'Dengue haemorrhagic fever']];
            }

            public function validateCode(string $kdPenyakit): ?array
            {
                return null;
            }

            public function saveDiagnosa(DiagnosaPasien $diagnosa): void
            {
            }

            public function findDiagnosaByNoRawat(NoRawat $noRawat): array
            {
                return [];
            }
        };

        $uc = new RecommendClinicalPdtUseCase($saRepo, $icdRepo);
        $res = $uc->recommend('Pasien demam tinggi 3 hari');

        self::assertCount(1, $res['knowledge_matches']);
        self::assertCount(1, $res['icd10_candidates']);
        self::assertStringContainsString('A91', $res['summary']);
    }
}
