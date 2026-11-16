<?php

declare(strict_types=1);

namespace App\Application\Communication\UseCase;

use App\Domain\Communication\Entity\WaSessionManager;
use App\Domain\Communication\Repository\WaGatewayRepositoryInterface;
use App\Domain\Communication\Service\WaClientInterface;

final readonly class ProcessInboundWaWebhookUseCase
{
    private WaSessionManager $sessionManager;

    public function __construct(
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

            // Catat verifikasi check-in ke wagateway_checkin tanpa memutasi reg_periksa.status_lanjut
            $recorded = $this->waRepo->recordQrCheckin($noRawat, $senderPhone, $sessionName);

            // ZERO-REPLY POLICY (Meta Anti-Banned): 0 outbound response
            return [
                'verified' => $recorded,
                'outbound_sent_count' => 0,
                'message' => $recorded
                    ? "Check-in berhasil dicatat untuk rawat {$noRawat} (Zero-Reply Policy aktif)."
                    : "Nomor rawat {$noRawat} tidak ditemukan.",
            ];
        }

        $botAllowed = $this->sessionManager->isBotAllowedForSession($sessionName);

        return [
            'verified' => false,
            'outbound_sent_count' => 0,
            'message' => $botAllowed ? 'Pesan diterima di sesi CS.' : 'Pesan diterima di sesi non-CS (Bot disabled).',
        ];
    }
}
