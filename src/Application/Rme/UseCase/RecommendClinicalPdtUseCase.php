<?php

declare(strict_types=1);

namespace App\Application\Rme\UseCase;

use App\Domain\Rme\Repository\IcdGroundingRepositoryInterface;
use App\Domain\Support\Repository\SmartAgentRepositoryInterface;

final readonly class RecommendClinicalPdtUseCase
{
    public function __construct(
        private SmartAgentRepositoryInterface $smartAgentRepo,
        private IcdGroundingRepositoryInterface $icdRepo,
    ) {
    }

    /**
     * @return array{query: string, knowledge_matches: list<array<string, mixed>>, icd10_candidates: list<array<string, mixed>>, summary: string}
     */
    public function recommend(string $clinicalQuery): array
    {
        $q = trim($clinicalQuery);
        if ($q === '') {
            return [
                'query' => '',
                'knowledge_matches' => [],
                'icd10_candidates' => [],
                'summary' => 'Masukkan keluhan atau kata kunci klinis.',
            ];
        }

        $tokens = array_values(array_filter(
            preg_split('/[\s,;]+/', $q) ?: [],
            static fn (string $t): bool => mb_strlen($t) >= 3
        ));
        if ($tokens === []) {
            $tokens = [$q];
        }

        $knowledgeById = [];
        foreach (array_slice($tokens, 0, 5) as $tok) {
            foreach ($this->smartAgentRepo->listKnowledge($tok) as $doc) {
                $knowledgeById[(int) ($doc['id'] ?? 0)] = $doc;
            }
        }
        $knowledgeMatches = array_values($knowledgeById);

        $icdByCode = [];
        foreach (array_slice($tokens, 0, 3) as $tok) {
            foreach ($this->icdRepo->searchSatuSehatIcd10($tok, 5) as $icd) {
                $code = (string) ($icd['kd_penyakit'] ?? $icd['code'] ?? '');
                if ($code !== '') {
                    $icdByCode[$code] = $icd;
                }
            }
        }
        $icdCandidates = array_values($icdByCode);

        $summaryParts = [];
        if ($knowledgeMatches !== []) {
            $top = $knowledgeMatches[0];
            $summaryParts[] = 'Rujukan PDT: ' . (string) ($top['title'] ?? '') . ' (' . (string) ($top['icd10_code'] ?? '-') . ') — ' . (string) ($top['content'] ?? '');
        }
        if ($icdCandidates !== []) {
            $codes = array_map(static fn (array $c): string => (string) ($c['kd_penyakit'] ?? $c['code'] ?? ''), array_slice($icdCandidates, 0, 3));
            $summaryParts[] = 'Kandidat ICD-10: ' . implode(', ', array_filter($codes));
        }

        return [
            'query' => $q,
            'knowledge_matches' => $knowledgeMatches,
            'icd10_candidates' => $icdCandidates,
            'summary' => $summaryParts !== [] ? implode(' | ', $summaryParts) : 'Belum ada kecocokan PDT/ICD-10 di Knowledge Vault.',
        ];
    }
}
