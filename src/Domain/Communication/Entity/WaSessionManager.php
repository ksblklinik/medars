<?php

declare(strict_types=1);

namespace App\Domain\Communication\Entity;

use App\Domain\Communication\Service\WaClientInterface;

final readonly class WaSessionManager
{
    public function __construct(
        private ?WaClientInterface $client = null,
    ) {}

    public function isBotAllowedForSession(string $session): bool
    {
        return strtolower(trim($session)) === 'cs';
    }

    /**
     * @return array{system_online: bool, cs_online: bool, all_offline: bool, skip_qr: bool}
     */
    public function resolveQrGatePolicy(): array
    {
        if ($this->client === null) {
            return [
                'system_online' => false,
                'cs_online' => false,
                'all_offline' => true,
                'skip_qr' => true,
            ];
        }

        $sysStatus = strtoupper(trim($this->client->getSessionStatus('system')));
        $csStatus = strtoupper(trim($this->client->getSessionStatus('cs')));

        $sysOnline = in_array($sysStatus, ['ONLINE', 'CONNECTED', 'READY'], true);
        $csOnline = in_array($csStatus, ['ONLINE', 'CONNECTED', 'READY'], true);
        $allOffline = !$sysOnline && !$csOnline;

        return [
            'system_online' => $sysOnline,
            'cs_online' => $csOnline,
            'all_offline' => $allOffline,
            'skip_qr' => $allOffline,
        ];
    }
}
