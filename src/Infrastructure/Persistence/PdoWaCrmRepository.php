<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Communication\Repository\WaCrmRepositoryInterface;
use PDO;

final class PdoWaCrmRepository implements WaCrmRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listCsChats(string $phone = ''): array
    {
        $sql = 'SELECT * FROM wagateway_cs_chat';
        $params = [];
        if (trim($phone) !== '') {
            $sql .= ' WHERE phone = :p';
            $params[':p'] = trim($phone);
        }
        $sql .= ' ORDER BY id ASC LIMIT 200';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveCsChat(string $phone, string $direction, string $message, string $senderName = ''): array
    {
        $dir = strtolower(trim($direction)) === 'in' ? 'in' : 'out';
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "INSERT INTO wagateway_cs_chat (session_name, phone, sender_name, direction, message, created_at)
             VALUES ('cs', :ph, :sn, :dir, :msg, :ca)"
        );
        $stmt->execute([
            ':ph' => trim($phone),
            ':sn' => trim($senderName) ?: ($dir === 'in' ? 'Pasien' : 'CS Klinik'),
            ':dir' => $dir,
            ':msg' => trim($message),
            ':ca' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'session_name' => 'cs',
            'phone' => trim($phone),
            'sender_name' => trim($senderName) ?: ($dir === 'in' ? 'Pasien' : 'CS Klinik'),
            'direction' => $dir,
            'message' => trim($message),
            'created_at' => $now,
        ];
    }

    public function listBroadcastTemplates(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM wagateway_broadcast_templates ORDER BY id DESC LIMIT 100');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveBroadcastTemplate(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $title = trim((string) ($data['title'] ?? ''));
        $session = trim((string) ($data['session_name'] ?? 'cs')) ?: 'cs';
        $template = trim((string) ($data['message_template'] ?? ''));
        $schedule = trim((string) ($data['schedule_at'] ?? $now)) ?: $now;
        $target = trim((string) ($data['target_filter'] ?? 'all')) ?: 'all';

        $stmt = $this->pdo->prepare(
            "INSERT INTO wagateway_broadcast_templates (title, session_name, message_template, schedule_at, target_filter, status, created_at)
             VALUES (:ti, :sn, :mt, :sa, :tf, 'scheduled', :ca)"
        );
        $stmt->execute([
            ':ti' => $title,
            ':sn' => $session,
            ':mt' => $template,
            ':sa' => $schedule,
            ':tf' => $target,
            ':ca' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'title' => $title,
            'session_name' => $session,
            'message_template' => $template,
            'schedule_at' => $schedule,
            'target_filter' => $target,
            'status' => 'scheduled',
        ];
    }

    public function generateAiDraftReply(string $incomingMessage): string
    {
        $msg = strtolower(trim($incomingMessage));
        if (str_contains($msg, 'jadwal') || str_contains($msg, 'poli') || str_contains($msg, 'dokter')) {
            return 'Halo Bapak/Ibu, jadwal poli kami buka Senin–Sabtu pukul 08.00–20.00 WIB. Silakan sebutkan nama poli/dokter yang dituju agar kami bantu cek slot antrean hari ini.';
        }
        if (str_contains($msg, 'lab') || str_contains($msg, 'hasil') || str_contains($msg, 'obat')) {
            return 'Halo Bapak/Ibu, hasil pemeriksaan laboratorium maupun status resep dapat dicek dengan menyebutkan No. RM atau Nama Lengkap + Tanggal Lahir ya.';
        }
        if (str_contains($msg, 'daftar') || str_contains($msg, 'antre') || str_contains($msg, 'bpjs')) {
            return 'Halo Bapak/Ibu, untuk pendaftaran online/BPJS dapat melalui Mobile JKN atau Portal Pasien kami. Jika butuh bantuan, silakan kirimkan No. RM / NIK Anda.';
        }
        return 'Terima kasih telah menghubungi Layanan CS SIMRS Medars. Mohon informasikan No. RM atau keperluan Anda agar dapat segera kami bantu.';
    }
}
