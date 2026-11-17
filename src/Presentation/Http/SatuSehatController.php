<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Bridging\Repository\SatuSehatMappingRepositoryInterface;

final readonly class SatuSehatController
{
    private const ALLOWED_RESOURCES = ['Encounter', 'Condition', 'Observation', 'MedicationRequest', 'ServiceRequest'];

    public function __construct(private SatuSehatMappingRepositoryInterface $repo)
    {
    }

    public function apiMappingList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listMappings(
                trim((string) ($query['entity_type'] ?? '')),
                trim((string) ($query['q'] ?? ''))
            ),
        ]);
    }

    public function apiMappingSave(array $post): array
    {
        if (trim((string) ($post['local_code'] ?? '')) === '' || trim((string) ($post['ihs_id'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Kode Lokal dan IHS ID SatuSehat wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveMapping($post)]);
    }

    public function apiFhirEnqueue(array $post): array
    {
        $resourceType = trim((string) ($post['resource_type'] ?? ''));
        $noRawat = trim((string) ($post['no_rawat'] ?? ''));
        if (!in_array($resourceType, self::ALLOWED_RESOURCES, true) || $noRawat === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Resource Type FHIR dan No. Rawat wajib valid.']);
        }
        $payload = is_array($post['payload'] ?? null) ? $post['payload'] : ['note' => (string) ($post['note'] ?? '')];
        return $this->json(201, [
            'status' => 'success',
            'data' => $this->repo->enqueueFhirResource($resourceType, $noRawat, $payload),
        ]);
    }

    private function json(int $status, array $payload): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
}
