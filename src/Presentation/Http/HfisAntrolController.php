<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Bridging\Repository\HfisAntrolRepositoryInterface;

final readonly class HfisAntrolController
{
    public function __construct(private HfisAntrolRepositoryInterface $repo)
    {
    }

    public function apiJadwalList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listJadwalHfis(trim((string) ($query['q'] ?? ''))),
        ]);
    }

    public function apiJadwalSync(array $post): array
    {
        if (trim((string) ($post['kd_poli_bpjs'] ?? '')) === '' || trim((string) ($post['kd_dokter_bpjs'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Kode Poli BPJS dan Kode Dokter BPJS wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveJadwalHfis($post)]);
    }

    public function apiAntrolList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listAntrolTasks(trim((string) ($query['q'] ?? ''))),
        ]);
    }

    public function apiAntrolPush(array $post): array
    {
        $kb = trim((string) ($post['kodebooking'] ?? ''));
        $taskId = (int) ($post['task_id'] ?? 0);
        if ($kb === '' || $taskId < 1 || $taskId > 7) {
            return $this->json(422, ['status' => 'error', 'message' => 'Kode Booking dan Task ID (1-7) wajib valid.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->pushAntrolTask($post)]);
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
