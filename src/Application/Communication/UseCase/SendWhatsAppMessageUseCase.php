<?php

declare(strict_types=1);

namespace App\Application\Communication\UseCase;

use App\Domain\Communication\Repository\WaGatewayRepositoryInterface;
use App\Domain\Communication\Service\WaClientInterface;
use App\Domain\Communication\ValueObject\IndonesianPhoneNumber;
use App\Domain\Security\Entity\UserAccount;

final readonly class SendWhatsAppMessageUseCase
{
    public function __construct(
        private WaGatewayRepositoryInterface $waRepo,
        private WaClientInterface $waClient,
    ) {}

    /**
     * @return array{status: string, message_id?: string, error?: string}
     */
    public function execute(
        UserAccount $sender,
        IndonesianPhoneNumber $phone,
        string $message,
        string $preferredSession = 'system',
    ): array {
        if (trim($message) === '') {
            throw new \InvalidArgumentException('Pesan WhatsApp tidak boleh kosong.');
        }

        $phoneStr = $phone->value();

        // 10-Lapis Rule: Admin bebas kirim; Non-admin WAJIB nomor Whitelist
        if (!$sender->isAdmin() && !$this->waRepo->isWhitelisted($phoneStr)) {
            throw new \DomainException(
                "Proteksi Anti-Banned: Staf non-admin hanya diizinkan mengirim pesan WhatsApp ke nomor kontak Whitelist klinik ({$phoneStr})."
            );
        }

        // Cek status sesi & failover
        $onlineStatuses = ['ONLINE', 'CONNECTED', 'READY'];
        $targetSession = $preferredSession;
        $primaryStatus = strtoupper(trim($this->waClient->getSessionStatus($targetSession)));
        if (!in_array($primaryStatus, $onlineStatuses, true)) {
            $fallbackSession = $preferredSession === 'system' ? 'cs' : 'system';
            $fallbackStatus = strtoupper(trim($this->waClient->getSessionStatus($fallbackSession)));
            if (!in_array($fallbackStatus, $onlineStatuses, true)) {
                $errorMsg = 'Semua sesi WA sedang OFFLINE.';
                $this->waRepo->recordOutbox($targetSession, $phoneStr, $message, 'Failed', $errorMsg);
                return [
                    'status' => 'Failed',
                    'message_id' => null,
                    'error' => $errorMsg,
                ];
            }
            $targetSession = $fallbackSession;
        }

        $res = $this->waClient->sendMessage($targetSession, $phoneStr, $message);
        $isOk = ($res['status'] ?? 500) >= 200 && ($res['status'] ?? 500) < 300;

        $outboxStatus = $isOk ? 'Sent' : 'Failed';
        $errorMsg = $isOk ? null : ($res['error'] ?? 'Gagal mengirim pesan');

        $this->waRepo->recordOutbox($targetSession, $phoneStr, $message, $outboxStatus, $errorMsg);

        return [
            'status' => $outboxStatus,
            'message_id' => $res['message_id'] ?? null,
            'error' => $errorMsg,
        ];
    }

    /**
     * @param list<IndonesianPhoneNumber> $recipients
     * @return array{sent_count: int, failed_count: int, aborted_count: int, circuit_breaker_tripped: bool}
     */
    public function sendBulk(UserAccount $sender, array $recipients, string $message): array
    {
        $consecutiveFailures = 0;
        $sentCount = 0;
        $failedCount = 0;
        $abortedCount = 0;
        $circuitTripped = false;

        $total = count($recipients);
        for ($i = 0; $i < $total; $i++) {
            if ($circuitTripped) {
                $abortedCount++;
                continue;
            }

            $phone = $recipients[$i];
            $res = $this->execute($sender, $phone, $message);

            if ($res['status'] === 'Sent') {
                $sentCount++;
                $consecutiveFailures = 0;
            } else {
                $failedCount++;
                $consecutiveFailures++;
                if ($consecutiveFailures >= 3) {
                    $circuitTripped = true;
                }
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'aborted_count' => $abortedCount,
            'circuit_breaker_tripped' => $circuitTripped,
        ];
    }
}
