<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Communication\Repository\WaCrmRepositoryInterface;

final readonly class WaCrmController
{
    public function __construct(private WaCrmRepositoryInterface $repo)
    {
    }

    public function apiCsChatsList(array $query = []): array
    {
        return $this->json(200, [
            'status' => 'success',
            'data' => $this->repo->listCsChats(trim((string) ($query['phone'] ?? ''))),
        ]);
    }

    public function apiCsChatSend(array $post): array
    {
        $phone = trim((string) ($post['phone'] ?? ''));
        $message = trim((string) ($post['message'] ?? ''));
        if ($phone === '' || $message === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Nomor WA dan pesan wajib diisi.']);
        }
        $direction = trim((string) ($post['direction'] ?? 'out')) ?: 'out';
        $sender = trim((string) ($post['sender_name'] ?? ''));
        return $this->json(201, [
            'status' => 'success',
            'data' => $this->repo->saveCsChat($phone, $direction, $message, $sender),
        ]);
    }

    public function apiBroadcastList(): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listBroadcastTemplates()]);
    }

    public function apiBroadcastSave(array $post): array
    {
        if (trim((string) ($post['title'] ?? '')) === '' || trim((string) ($post['message_template'] ?? '')) === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Judul dan isi template broadcast wajib diisi.']);
        }
        return $this->json(201, ['status' => 'success', 'data' => $this->repo->saveBroadcastTemplate($post)]);
    }

    public function apiAiDraft(array $post): array
    {
        $incoming = trim((string) ($post['incoming_message'] ?? ''));
        return $this->json(200, [
            'status' => 'success',
            'data' => ['draft' => $this->repo->generateAiDraftReply($incoming)],
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
