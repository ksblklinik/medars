<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Communication\Repository\WaGatewayRepositoryInterface;
use PDO;

final readonly class PdoWaGatewayRepository implements WaGatewayRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function isWhitelisted(string $phone): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM wagateway_whitelist WHERE no_hp = :p AND status = 'Aktif'"
        );
        $stmt->execute([':p' => $phone]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function addToWhitelist(string $phone, string $nama, string $kategori = 'Pegawai'): void
    {
        // Portable delete + insert
        $del = $this->pdo->prepare('DELETE FROM wagateway_whitelist WHERE no_hp = :p');
        $del->execute([':p' => $phone]);

        $stmt = $this->pdo->prepare(
            "INSERT INTO wagateway_whitelist (no_hp, nama, kategori, status)
             VALUES (:p, :nama, :cat, 'Aktif')"
        );
        $stmt->execute([
            ':p' => $phone,
            ':nama' => $nama,
            ':cat' => $kategori,
        ]);
    }

    public function recordOutbox(
        string $session,
        string $phone,
        string $message,
        string $status,
        ?string $error = null,
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO wagateway_outbox (session_name, no_hp, pesan, status, error_message, created_at)
             VALUES (:s, :p, :msg, :stts, :err, :created)'
        );
        $stmt->execute([
            ':s' => $session,
            ':p' => $phone,
            ':msg' => $message,
            ':stts' => $status,
            ':err' => $error,
            ':created' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function recordQrCheckin(string $noRawat, string $senderPhone, string $sessionName): bool
    {
        $check = $this->pdo->prepare('SELECT COUNT(*) FROM reg_periksa WHERE no_rawat = :nr');
        $check->execute([':nr' => $noRawat]);
        if ((int) $check->fetchColumn() === 0) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO wagateway_checkin (no_rawat, sender_phone, session_name, verified_at)
             VALUES (:nr, :phone, :sess, :at)'
        );
        $stmt->execute([
            ':nr' => $noRawat,
            ':phone' => $senderPhone,
            ':sess' => $sessionName,
            ':at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    public function listRecentOutbox(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM wagateway_outbox ORDER BY id DESC LIMIT :lim');
        $stmt->bindValue(':lim', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listWhitelist(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM wagateway_whitelist ORDER BY nama ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
