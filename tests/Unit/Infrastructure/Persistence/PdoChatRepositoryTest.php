<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoChatRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class PdoChatRepositoryTest extends TestCase
{
    private PDO $pdo;
    private PdoChatRepository $repo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
        $this->repo = new PdoChatRepository($this->pdo);
    }

    public function testConversationsMessagesAndQuickResponses(): void
    {
        $conv = $this->repo->createConversation([
            'title' => 'Koordinasi Farmasi & Poli',
            'type' => 'group',
            'created_by' => 'admin',
        ]);
        $convId = (int) $conv['id'];
        self::assertCount(1, $this->repo->listConversations());

        $msg = $this->repo->sendMessage([
            'conversation_id' => $convId,
            'sender_username' => 'admin',
            'sender_name' => 'Administrator',
            'message' => 'Stok Paracetamol tersedia di depo ralan.',
        ]);
        self::assertSame('Stok Paracetamol tersedia di depo ralan.', $msg['message']);
        self::assertCount(1, $this->repo->listMessages($convId));

        $qr = $this->repo->saveQuickResponse([
            'shortcut' => '/salam',
            'content' => 'Selamat pagi, ada yang bisa kami bantu?',
            'created_by' => 'admin',
        ]);
        self::assertCount(1, $this->repo->listQuickResponses());
        self::assertTrue($this->repo->deleteQuickResponse((int) $qr['id']));
        self::assertSame([], $this->repo->listQuickResponses());
    }
}
