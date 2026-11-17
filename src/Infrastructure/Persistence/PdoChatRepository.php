<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Communication\Repository\ChatRepositoryInterface;
use PDO;

final class PdoChatRepository implements ChatRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listConversations(): array
    {
        $stmt = $this->pdo->query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM mlite_chat_messages m WHERE m.conversation_id = c.id) AS total_messages
             FROM mlite_chat_conversations c
             ORDER BY c.id DESC LIMIT 100'
        );
        return $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function createConversation(array $data): array
    {
        $title = trim((string) ($data['title'] ?? 'Percakapan Baru')) ?: 'Percakapan Baru';
        $type = trim((string) ($data['type'] ?? 'direct')) ?: 'direct';
        $by = trim((string) ($data['created_by'] ?? 'system')) ?: 'system';
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_chat_conversations (title, type, created_by, created_at) VALUES (:t, :tp, :cb, :ca)'
        );
        $stmt->execute([':t' => $title, ':tp' => $type, ':cb' => $by, ':ca' => $now]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'title' => $title,
            'type' => $type,
            'created_by' => $by,
            'created_at' => $now,
        ];
    }

    public function listMessages(int $conversationId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM mlite_chat_messages WHERE conversation_id = :cid ORDER BY id ASC LIMIT :lim'
        );
        $stmt->bindValue(':cid', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sendMessage(array $data): array
    {
        $cid = (int) ($data['conversation_id'] ?? 0);
        $sender = trim((string) ($data['sender_username'] ?? 'system')) ?: 'system';
        $senderName = trim((string) ($data['sender_name'] ?? $sender)) ?: $sender;
        $msg = trim((string) ($data['message'] ?? ''));
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_chat_messages (conversation_id, sender_username, sender_name, message, is_read, created_at)
             VALUES (:cid, :su, :sn, :m, 0, :ca)'
        );
        $stmt->execute([':cid' => $cid, ':su' => $sender, ':sn' => $senderName, ':m' => $msg, ':ca' => $now]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'conversation_id' => $cid,
            'sender_username' => $sender,
            'sender_name' => $senderName,
            'message' => $msg,
            'created_at' => $now,
        ];
    }

    public function listQuickResponses(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM mlite_chat_quick_responses ORDER BY shortcut ASC LIMIT 100');
        return $stmt !== false ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function saveQuickResponse(array $data): array
    {
        $shortcut = trim((string) ($data['shortcut'] ?? ''));
        $content = trim((string) ($data['content'] ?? ''));
        $by = trim((string) ($data['created_by'] ?? 'system')) ?: 'system';

        $stmt = $this->pdo->prepare(
            'INSERT INTO mlite_chat_quick_responses (shortcut, content, created_by) VALUES (:s, :c, :cb)'
        );
        $stmt->execute([':s' => $shortcut, ':c' => $content, ':cb' => $by]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'shortcut' => $shortcut,
            'content' => $content,
            'created_by' => $by,
        ];
    }

    public function deleteQuickResponse(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM mlite_chat_quick_responses WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
