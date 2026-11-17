<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Communication\Repository\HomeCareCarePlanRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;

final readonly class HomeCareExtrasController
{
    public function __construct(private HomeCareCarePlanRepositoryInterface $repo)
    {
    }

    public function apiCarePlanList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listCarePlans(trim((string) ($query['no_rawat'] ?? ''))),
        ]);
    }

    public function apiCarePlanSave(array $post): array
    {
        if (trim((string) ($post['no_rawat'] ?? '')) === '' || trim((string) ($post['care_plan'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'No. Rawat dan Rencana Asuhan (Care Plan) wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveCarePlan($post)]);
    }

    public function apiSignOffDpjp(?UserAccount $actor, array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID Care Plan tidak valid.']);
        }
        $res = $this->repo->signOffDpjp($id, $actor?->getUsername() ?? 'dpjp');
        return $res === null
            ? $this->json(404, ['status' => 'error', 'message' => 'Care Plan tidak ditemukan.'])
            : $this->json(200, ['status' => 'success', 'data' => $res]);
    }

    public function apiPayCarePlan(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID Care Plan tidak valid.']);
        }
        $gw = trim((string) ($post['payment_gateway'] ?? 'Kasera')) ?: 'Kasera';
        $ref = trim((string) ($post['payment_ref'] ?? ''));
        $res = $this->repo->payCarePlan($id, $gw, $ref);
        return $res === null
            ? $this->json(404, ['status' => 'error', 'message' => 'Care Plan tidak ditemukan.'])
            : $this->json(200, ['status' => 'success', 'data' => $res]);
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
