<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\SatuSehatMappingRepositoryInterface;
use PDO;

final class PdoSatuSehatMappingRepository implements SatuSehatMappingRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listMappings(string $entityType = '', string $search = ''): array
    {
        $sql = 'SELECT * FROM mlite_satusehat_mapping WHERE 1=1';
        $params = [];
        if (trim($entityType) !== '') {
            $sql .= ' AND entity_type = :et';
            $params[':et'] = trim($entityType);
        }
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' AND (local_code LIKE :q OR local_name LIKE :q OR ihs_id LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveMapping(array $data): array
    {
        $entityType = strtolower(trim((string) ($data['entity_type'] ?? 'location'))) ?: 'location';
        $localCode = trim((string) ($data['local_code'] ?? ''));
        $localName = trim((string) ($data['local_name'] ?? ''));
        $ihsId = trim((string) ($data['ihs_id'] ?? ''));
        $updatedAt = date('Y-m-d H:i:s');

        $chk = $this->pdo->prepare(
            'SELECT id FROM mlite_satusehat_mapping WHERE entity_type = :et AND local_code = :lc LIMIT 1'
        );
        $chk->execute([':et' => $entityType, ':lc' => $localCode]);
        $existingId = $chk->fetchColumn();

        if ($existingId !== false) {
            $upd = $this->pdo->prepare(
                'UPDATE mlite_satusehat_mapping SET local_name = :ln, ihs_id = :ihs, updated_at = :ua WHERE id = :id'
            );
            $upd->execute([':ln' => $localName, ':ihs' => $ihsId, ':ua' => $updatedAt, ':id' => (int) $existingId]);
            $id = (int) $existingId;
        } else {
            $ins = $this->pdo->prepare(
                'INSERT INTO mlite_satusehat_mapping (entity_type, local_code, local_name, ihs_id, updated_at)
                 VALUES (:et, :lc, :ln, :ihs, :ua)'
            );
            $ins->execute([':et' => $entityType, ':lc' => $localCode, ':ln' => $localName, ':ihs' => $ihsId, ':ua' => $updatedAt]);
            $id = (int) $this->pdo->lastInsertId();
        }

        return [
            'id' => $id,
            'entity_type' => $entityType,
            'local_code' => $localCode,
            'local_name' => $localName,
            'ihs_id' => $ihsId,
            'updated_at' => $updatedAt,
        ];
    }

    public function enqueueFhirResource(string $resourceType, string $noRawat, array $payload): array
    {
        $now = date('Y-m-d H:i:s');
        $endpoint = '/fhir-r4/v1/' . trim($resourceType);
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $stmt = $this->pdo->prepare(
            "INSERT INTO bridging_sync_queue (provider, resource_type, no_rawat, endpoint, payload_json, status, attempts, created_at, updated_at)
             VALUES ('satusehat', :rt, :nr, :ep, :pj, 'Pending', 0, :ca, :ua)"
        );
        $stmt->execute([
            ':rt' => trim($resourceType),
            ':nr' => trim($noRawat),
            ':ep' => $endpoint,
            ':pj' => $jsonPayload,
            ':ca' => $now,
            ':ua' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'provider' => 'satusehat',
            'resource_type' => trim($resourceType),
            'no_rawat' => trim($noRawat),
            'endpoint' => $endpoint,
            'status' => 'Pending',
        ];
    }
}
