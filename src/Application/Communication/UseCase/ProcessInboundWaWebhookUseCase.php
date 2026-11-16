<?php

declare(strict_types=1);

namespace App\Application\Communication\UseCase;

use App\Domain\Communication\Entity\WaSessionManager;
use App\Domain\Communication\Repository\WaGatewayRepositoryInterface;
use App\Domain\Communication\Service\WaClientInterface;
use PDO;

final readonly class ProcessInboundWaWebhookUseCase
{
    private WaSessionManager $sessionManager;

    public function __construct(
        private PDO $pdo,
        private WaGatewayRepositoryInterface $waRepo,
        private ?WaClientInterface $waClient = null,
    ) {
        $this->sessionManager = new WaSessionManager($this->waClient);
    }

    /**
     * @return array{verified: bool, outbound_sent_count: int, message: string}
     */
    public function execute(string $sessionName, string $senderPhone, string $messageText): array
    {
        $trimmed = trim($messageText);

        // QR Check-in Detection (e.g. "CHECKIN 2026/11/16/000001" or "REG-2026/11/16/000001")
        if (preg_match('/(?:CHECKIN|REG)[\s:-]+([0-9\/\-]+)/i', $trimmed, $matches) === 1) {
            $noRawat = trim($matches[1]);

            // Update registration / check-in record
            $stmt = $this->pdo->prepare('UPDATE reg_periksa SET status_lanjut = "Ralan" WHERE no_rawat = :nr');
            $stmt->execute([':nr' => $noRawat]);

            // ZERO-REPLY POLICY (Meta Anti-Banned):
            // Pasien scan QR, sistem hanya mencatat kedatangan tanpa membalas chat WA keluar (0 outbound response)
            return [
                'verified' => true,
                'outbound_sent_count' => 0,
                'message' => "Check-in berhasil dicatat untuk rawat {$noRawat} (Zero-Reply Policy aktif).",
            ];
        }

        // Jika bukan check-in dan sesi adalah CS, baru diizinkan respon bot jika diperlukan
        $botAllowed = $this->sessionManager->isBotAllowedForSession($sessionName);

        return [
            'verified' => false,
            'outbound_sent_count' => 0,
            'message' => $botAllowed ? 'Pesan diterima di sesi CS.' : 'Pesan diterima di sesi non-CS (Bot disabled).',
        ];
    }
}
