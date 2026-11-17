<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Communication\Repository\ChatRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class ChatController
{
    public function __construct(
        private ChatRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {
    }

    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('chat/index', [
            'judul' => 'Live Chat Internal & CS',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/chat',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    public function apiConversations(): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listConversations()]);
    }

    public function apiConversationCreate(?UserAccount $actor, array $post): array
    {
        $title = trim((string) ($post['title'] ?? ''));
        if ($title === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Judul percakapan wajib diisi.']);
        }
        $created = $this->repo->createConversation([
            'title' => $title,
            'type' => (string) ($post['type'] ?? 'group'),
            'created_by' => $actor?->getUsername() ?? 'system',
        ]);
        return $this->json(201, ['status' => 'success', 'data' => $created]);
    }

    public function apiMessages(array $query = []): array
    {
        $cid = (int) ($query['conversation_id'] ?? 0);
        if ($cid <= 0) {
            return $this->json(422, ['status' => 'error', 'message' => 'ID percakapan wajib diisi.']);
        }
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listMessages($cid)]);
    }

    public function apiSend(?UserAccount $actor, array $post): array
    {
        $cid = (int) ($post['conversation_id'] ?? 0);
        $msg = trim((string) ($post['message'] ?? ''));
        if ($cid <= 0 || $msg === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Percakapan dan isi pesan wajib diisi.']);
        }
        $sent = $this->repo->sendMessage([
            'conversation_id' => $cid,
            'sender_username' => $actor?->getUsername() ?? 'system',
            'sender_name' => $actor?->getFullname() ?: ($actor?->getUsername() ?? 'System'),
            'message' => $msg,
        ]);
        return $this->json(201, ['status' => 'success', 'data' => $sent]);
    }

    public function apiQuickResponses(): array
    {
        return $this->json(200, ['status' => 'success', 'data' => $this->repo->listQuickResponses()]);
    }

    public function apiQuickResponseSave(?UserAccount $actor, array $post): array
    {
        $shortcut = trim((string) ($post['shortcut'] ?? ''));
        $content = trim((string) ($post['content'] ?? ''));
        if ($shortcut === '' || $content === '') {
            return $this->json(422, ['status' => 'error', 'message' => 'Shortcut dan isi respon cepat wajib diisi.']);
        }
        $saved = $this->repo->saveQuickResponse([
            'shortcut' => $shortcut,
            'content' => $content,
            'created_by' => $actor?->getUsername() ?? 'system',
        ]);
        return $this->json(201, ['status' => 'success', 'data' => $saved]);
    }

    public function apiQuickResponseDelete(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0 || !$this->repo->deleteQuickResponse($id)) {
            return $this->json(404, ['status' => 'error', 'message' => 'Respon cepat tidak ditemukan.']);
        }
        return $this->json(200, ['status' => 'success', 'message' => 'Respon cepat dihapus.']);
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
