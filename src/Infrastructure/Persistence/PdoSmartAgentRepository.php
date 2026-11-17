<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Support\Repository\SmartAgentRepositoryInterface;
use PDO;

final class PdoSmartAgentRepository implements SmartAgentRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listKnowledge(string $search = '', string $category = ''): array
    {
        $sql = 'SELECT * FROM mlite_smartagent_knowledge WHERE 1=1';
        $params = [];
        $cat = trim($category);
        if ($cat !== '') {
            $sql .= ' AND category = :cat';
            $params[':cat'] = $cat;
        }
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' AND (title LIKE :q OR icd10_code LIKE :q OR keywords LIKE :q OR content LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveKnowledge(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        $category = trim((string) ($data['category'] ?? 'PDT')) ?: 'PDT';
        $icd = trim((string) ($data['icd10_code'] ?? ''));
        $keywords = trim((string) ($data['keywords'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        $by = trim((string) ($data['created_by'] ?? 'system')) ?: 'system';
        $now = date('Y-m-d H:i:s');

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                'UPDATE mlite_smartagent_knowledge
                 SET title = :t, category = :c, icd10_code = :i, keywords = :k, content = :ct, updated_at = :u
                 WHERE id = :id'
            );
            $stmt->execute([':t' => $title, ':c' => $category, ':i' => $icd, ':k' => $keywords, ':ct' => $content, ':u' => $now, ':id' => $id]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO mlite_smartagent_knowledge (title, category, icd10_code, keywords, content, created_by, updated_at)
                 VALUES (:t, :c, :i, :k, :ct, :cb, :u)'
            );
            $stmt->execute([':t' => $title, ':c' => $category, ':i' => $icd, ':k' => $keywords, ':ct' => $content, ':cb' => $by, ':u' => $now]);
            $id = (int) $this->pdo->lastInsertId();
        }

        return [
            'id' => $id,
            'title' => $title,
            'category' => $category,
            'icd10_code' => $icd,
            'keywords' => $keywords,
            'content' => $content,
            'updated_at' => $now,
        ];
    }

    public function deleteKnowledge(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM mlite_smartagent_knowledge WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function listProfiles(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM mlite_smartagent_profiles ORDER BY id DESC LIMIT 50');
        return $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function saveProfile(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $provider = trim((string) ($data['provider'] ?? 'vllm-local')) ?: 'vllm-local';
        $endpoint = trim((string) ($data['endpoint_url'] ?? 'http://127.0.0.1:8000/v1'));
        $model = trim((string) ($data['model_name'] ?? 'Qwen/Qwen2.5-7B-Instruct'));
        $active = isset($data['is_active']) ? (filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 1;

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_smartagent_profiles (name, provider, endpoint_url, model_name, is_active)
             VALUES (:n, :p, :e, :m, :a)'
        );
        $stmt->execute([':n' => $name, ':p' => $provider, ':e' => $endpoint, ':m' => $model, ':a' => $active]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'name' => $name,
            'provider' => $provider,
            'endpoint_url' => $endpoint,
            'model_name' => $model,
            'is_active' => $active === 1,
        ];
    }
}
