<?php

declare(strict_types=1);

namespace Tests\Unit\Bridging;

use App\Application\Bridging\UseCase\ProcessBridgingQueueUseCase;
use App\Application\Bridging\UseCase\QueueSatuSehatSyncUseCase;
use App\Application\Communication\UseCase\SendWhatsAppMessageUseCase;
use App\Domain\Bridging\Service\BpjsDecryptor;
use App\Domain\Bridging\Service\BpjsGatewayService;
use App\Domain\Bridging\Service\HttpClientInterface;
use App\Domain\Bridging\Service\LzString;
use App\Domain\Bridging\Service\SatuSehatAuthService;
use App\Domain\Bridging\ValueObject\BridgingCredentials;
use App\Domain\Bridging\ValueObject\TimeoutPolicy;
use App\Domain\Communication\Entity\WaSpintax;
use App\Domain\Communication\Service\WaClientInterface;
use App\Domain\Communication\ValueObject\IndonesianPhoneNumber;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoBridgingQueueRepository;
use App\Infrastructure\Persistence\PdoWaGatewayRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * TDD suite for Poin 5: T3 (SatuSehat OAuth2), T4 (BPJS AES+LZString), T5 (WA Anti-Banned completeness).
 */
class Poin5CompletionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->pdo))->migrate();
    }

    // ---- T3: SatuSehat OAuth2 client_credentials exchange ----

    public function testSatuSehatExchangesOAuth2TokenAndSendsBearerOnFhirSync(): void
    {
        $queueRepo = new PdoBridgingQueueRepository($this->pdo);
        (new QueueSatuSehatSyncUseCase($queueRepo))->execute(
            new NoRawat('2026/11/16/000001'),
            'P001',
            'N001',
            'J06.9',
            'ISPA Akut'
        );

        $spyClient = new class implements HttpClientInterface {
            /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
            public array $requests = [];

            public function request(string $method, string $url, array $headers, ?string $body, TimeoutPolicy $timeout): array
            {
                $this->requests[] = compact('method', 'url', 'headers', 'body');
                if (str_contains($url, 'accesstoken')) {
                    return [
                        'status' => 200,
                        'body' => json_encode(['access_token' => 'oauth2-real-token-xyz', 'expires_in' => '3600']),
                    ];
                }
                return ['status' => 200, 'body' => json_encode(['id' => 'bundle-ok-1'])];
            }
        };

        $fhirCreds = new BridgingCredentials('org-1', 'client-id-1', 'client-secret-1', 'https://api-satusehat.kemkes.go.id/fhir-r4/v1');
        $authService = new SatuSehatAuthService(
            authUrl: 'https://api-satusehat.kemkes.go.id/oauth2/v1',
            clientId: 'client-id-1',
            clientSecret: 'client-secret-1',
            httpClient: $spyClient,
            timeoutPolicy: new TimeoutPolicy(2, 4),
        );

        $worker = new ProcessBridgingQueueUseCase($queueRepo, $spyClient, $fhirCreds, new TimeoutPolicy(2, 4), $authService);
        $summary = $worker->processPending(10);

        $this->assertSame(1, $summary['synced']);
        $this->assertCount(2, $spyClient->requests);
        $this->assertStringContainsString('/accesstoken?grant_type=client_credentials', $spyClient->requests[0]['url']);
        $this->assertSame('Bearer oauth2-real-token-xyz', $spyClient->requests[1]['headers']['Authorization']);
    }

    // ---- T4: BPJS AES-256-CBC + LZString Decryption ----

    public function testBpjsDecryptorAndGatewayDecryptRealEncryptedPayload(): void
    {
        $consId = '123456';
        $secretKey = 'mySecretKeyBpjs';
        $timestamp = '1700000000';
        $plainJson = json_encode(['peserta' => ['nama' => 'BUDI SANTOSO', 'noKartu' => '0001234567890']]);

        // Encrypt exactly like BPJS VClaim/PCare v2 server
        $keyString = $consId . $secretKey . $timestamp;
        $keyHash = hex2bin(hash('sha256', $keyString));
        $iv = substr((string) $keyHash, 0, 16);
        $compressed = LzString::compressToEncodedURIComponent((string) $plainJson);
        $encrypted = base64_encode((string) openssl_encrypt($compressed, 'AES-256-CBC', (string) $keyHash, OPENSSL_RAW_DATA, $iv));

        $decrypted = BpjsDecryptor::decrypt($encrypted, $consId, $secretKey, $timestamp);
        $this->assertSame($plainJson, $decrypted);

        $spyClient = new class($encrypted) implements HttpClientInterface {
            public function __construct(private string $enc) {}
            public function request(string $method, string $url, array $headers, ?string $body, TimeoutPolicy $timeout): array
            {
                return [
                    'status' => 200,
                    'body' => json_encode(['metaData' => ['code' => 200, 'message' => 'OK'], 'response' => $this->enc]),
                ];
            }
        };

        $creds = new BridgingCredentials($consId, $secretKey, 'userkey-1', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest');
        $gateway = new BpjsGatewayService($creds, $spyClient, new TimeoutPolicy(2, 4));
        $res = $gateway->get('/Peserta/nokartu/0001234567890/tglSEP/2026-11-16', (int) $timestamp);

        $this->assertTrue($res['ok']);
        $this->assertSame('BUDI SANTOSO', $res['response']['peserta']['nama']);
    }

    // ---- T5: WA Bulk Whitelist-only (even Admin), Immediate 401/429 Trip, isOnWhatsApp, Spintax QR ----

    public function testWaBulkEnforcesWhitelistEvenForAdminAndTripsImmediatelyOn429(): void
    {
        $waRepo = new PdoWaGatewayRepository($this->pdo);
        $waRepo->addToWhitelist('6281111111111', 'Pasien WL 1', 'Pasien');
        $waRepo->addToWhitelist('6282222222222', 'Pasien WL 2', 'Pasien');

        $client = new class implements WaClientInterface {
            public int $calls = 0;
            public function isOnWhatsApp(string $phone): bool { return $phone !== '6289999999999'; }
            public function sendMessage(string $session, string $phone, string $message): array
            {
                $this->calls++;
                return ['status' => 429, 'error' => 'Rate limited / Banned'];
            }
            public function getSessionStatus(string $session): string { return 'ONLINE'; }
        };

        $admin = new UserAccount(1, 'admin', 'p', 'a@m.l', 'Admin', 'admin', ['all'], ['all']);
        $useCase = new SendWhatsAppMessageUseCase($waRepo, $client);

        $summary = $useCase->sendBulk($admin, [
            new IndonesianPhoneNumber('083333333333'), // Non-whitelist -> skipped even for Admin!
            new IndonesianPhoneNumber('081111111111'), // Whitelist -> hits 429 -> IMMEDIATE trip!
            new IndonesianPhoneNumber('082222222222'), // Aborted due to immediate circuit breaker!
        ], 'Broadcast Info');

        $this->assertSame(1, $client->calls, '429 wajib langsung trip circuit breaker di percobaan pertama.');
        $this->assertTrue($summary['circuit_breaker_tripped']);
        $this->assertSame(1, $summary['skipped_non_whitelist']);
        $this->assertSame(1, $summary['aborted_count']);

        // Pre-send isOnWhatsApp gate
        $notRegistered = $useCase->execute($admin, new IndonesianPhoneNumber('089999999999'), 'Tes');
        $this->assertSame('Failed', $notRegistered['status']);
        $this->assertStringContainsString('tidak terdaftar', (string) $notRegistered['error']);

        // Spintax QR link
        $qrText = WaSpintax::buildQrCheckinLinkText('2026/11/16/000001', 'Budi');
        $this->assertStringContainsString('2026/11/16/000001', $qrText);
        $this->assertStringNotContainsString('{', $qrText);
    }
}
