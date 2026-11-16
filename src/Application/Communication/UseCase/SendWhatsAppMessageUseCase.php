<?php

declare(strict_types=1);

namespace App\Application\Communication\UseCase;

use App\Domain\Communication\Repository\WaGatewayRepositoryInterface;
use App\Domain\Communication\Service\WaClientInterface;
use App\Domain\Communication\ValueObject\IndonesianPhoneNumber;
use App\Domain\Security\Entity\UserAccount;

final readonly class SendWhatsAppMessageUseCase
{
    private const IMMEDIATE_TRIP_HTTP_CODES = [401, 428, 429];

    public function __construct(
        private WaGatewayRepositoryInterface $waRepo,
        private WaClientInterface $waClient,
    ) {}

    /**
     * @return array{status: string, http_status?: int, message_id?: ?string, error?: ?string}
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

        // 10-Lapis Rule: Admin bebas kirim satuan; Non-admin WAJIB nomor Whitelist
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
                    'http_status' => 503,
                    'message_id' => null,
                    'error' => $errorMsg,
                ];
            }
            $targetSession = $fallbackSession;
        }

        // Pre-Send Gate: verifikasi nomor terdaftar di WhatsApp
        if (!$this->waClient->isOnWhatsApp($phoneStr)) {
            $errorMsg = "Nomor {$phoneStr} tidak terdaftar di WhatsApp.";
            $this->waRepo->recordOutbox($targetSession, $phoneStr, $message, 'Failed', $errorMsg);
            return [
                'status' => 'Failed',
                'http_status' => 422,
                'message_id' => null,
                'error' => $errorMsg,
            ];
        }

        $res = $this->waClient->sendMessage($targetSession, $phoneStr, $message);
        $httpStatus = (int) ($res['status'] ?? 500);
        $isOk = $httpStatus >= 200 && $httpStatus < 300;

        $outboxStatus = $isOk ? 'Sent' : 'Failed';
        $errorMsg = $isOk ? null : ($res['error'] ?? 'Gagal mengirim pesan');

        $this->waRepo->recordOutbox($targetSession, $phoneStr, $message, $outboxStatus, $errorMsg);

        return [
            'status' => $outboxStatus,
            'http_status' => $httpStatus,
            'message_id' => $res['message_id'] ?? null,
            'error' => $errorMsg,
        ];
    }

    /**
     * PRD 06: Pengiriman massal (bulk) 100% dikunci hanya untuk nomor Whitelist Tier-1 (berlaku untuk semua role termasuk Admin),
     * dan langsung memicu circuit breaker pada HTTP 401/428/429/Banned atau 3x gagal beruntun.
     *
     * @param list<IndonesianPhoneNumber> $recipients
     * @return array{sent_count: int, failed_count: int, aborted_count: int, skipped_non_whitelist: int, circuit_breaker_tripped: bool}
     */
    public function sendBulk(UserAccount $sender, array $recipients, string $message): array
    {
        $consecutiveFailures = 0;
        $sentCount = 0;
        $failedCount = 0;
        $abortedCount = 0;
        $skippedNonWhitelist = 0;
        $circuitTripped = false;

        foreach ($recipients as $phone) {
            if ($circuitTripped) {
                $abortedCount++;
                continue;
            }

            if (!$this->waRepo->isWhitelisted($phone->value())) {
                $skippedNonWhitelist++;
                continue;
            }

            $res = $this->execute($sender, $phone, $message);

            if ($res['status'] === 'Sent') {
                $sentCount++;
                $consecutiveFailures = 0;
            } else {
                $failedCount++;
                $consecutiveFailures++;
                $httpCode = (int) ($res['http_status'] ?? 500);
                $errLower = strtolower((string) ($res['error'] ?? ''));

                if (
                    in_array($httpCode, self::IMMEDIATE_TRIP_HTTP_CODES, true)
                    || str_contains($errLower, 'banned')
                    || $consecutiveFailures >= 3
                ) {
                    $circuitTripped = true;
                }
            }
        }

        return [
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'aborted_count' => $abortedCount,
            'skipped_non_whitelist' => $skippedNonWhitelist,
            'circuit_breaker_tripped' => $circuitTripped,
        ];
    }
}
