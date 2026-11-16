<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Communication\UseCase\AssignHomeCarePreceptorUseCase;
use App\Application\Communication\UseCase\ProcessInboundWaWebhookUseCase;
use App\Application\Communication\UseCase\SendWhatsAppMessageUseCase;
use App\Domain\Communication\Entity\HomeCarePrivacyShield;
use App\Domain\Communication\Repository\HomeCareRepositoryInterface;
use App\Domain\Communication\Repository\WaGatewayRepositoryInterface;
use App\Domain\Communication\ValueObject\IndonesianPhoneNumber;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class CommunicationController
{
    public function __construct(
        private WaGatewayRepositoryInterface $waRepo,
        private HomeCareRepositoryInterface $homeCareRepo,
        private SendWhatsAppMessageUseCase $sendWaUseCase,
        private ProcessInboundWaWebhookUseCase $inboundWebhookUseCase,
        private AssignHomeCarePreceptorUseCase $assignPreceptorUseCase,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {}

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function indexWa(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('communication/wa_index', [
            'judul' => 'WhatsApp Gateway & Anti-Banned Meta',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'wagateway',
            'module_icon' => 'fa-brands fa-whatsapp',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Admin WA'),
            'user_role' => $currentUser?->getRole() ?? 'admin',
            'csrf_token' => $csrfToken,
            'navigations' => $navigations,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function indexHomeCare(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('communication/homecare_index', [
            'judul' => 'Home Care & Trias Preceptorship',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'homecare',
            'module_icon' => 'fa-house-medical',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Nakes Home Care'),
            'user_role' => $currentUser?->getRole() ?? 'staff',
            'csrf_token' => $csrfToken,
            'navigations' => $navigations,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSendWa(UserAccount $sender, array $payload): array
    {
        try {
            $phone = new IndonesianPhoneNumber((string) ($payload['phone'] ?? ''));
            $msg = trim((string) ($payload['message'] ?? ''));
            $session = trim((string) ($payload['session'] ?? 'system')) ?: 'system';

            $result = $this->sendWaUseCase->execute($sender, $phone, $msg, $session);
            return $this->json(['ok' => true, 'result' => $result]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiInboundWebhook(array $payload): array
    {
        $session = trim((string) ($payload['session'] ?? 'system')) ?: 'system';
        $phone = trim((string) ($payload['phone'] ?? ''));
        $msg = trim((string) ($payload['message'] ?? ''));

        $result = $this->inboundWebhookUseCase->execute($session, $phone, $msg);
        return $this->json(['ok' => true, 'result' => $result]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListWaOutbox(array $query): array
    {
        $limit = (int) ($query['limit'] ?? 50);
        return $this->json([
            'ok' => true,
            'outbox' => $this->waRepo->listRecentOutbox($limit),
        ]);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListWaWhitelist(): array
    {
        return $this->json([
            'ok' => true,
            'whitelist' => $this->waRepo->listWhitelist(),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiAddWaWhitelist(array $payload): array
    {
        try {
            $phone = new IndonesianPhoneNumber((string) ($payload['phone'] ?? ''));
            $nama = trim((string) ($payload['nama'] ?? ''));
            $kategori = trim((string) ($payload['kategori'] ?? 'Pegawai')) ?: 'Pegawai';

            $this->waRepo->addToWhitelist($phone->value(), $nama, $kategori);
            return $this->json(['ok' => true, 'phone' => $phone->value()]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCreateHomeCare(array $payload): array
    {
        try {
            $created = $this->homeCareRepo->createKunjungan($payload);
            return $this->json(['ok' => true, 'kunjungan' => $created], 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiAssignPreceptor(array $payload): array
    {
        try {
            $noRawat = new NoRawat((string) ($payload['no_rawat'] ?? ''));
            $nip = trim((string) ($payload['preceptor_nip'] ?? ''));
            $inst = trim((string) ($payload['institusi'] ?? ''));
            $nim = trim((string) ($payload['mahasiswa_nim'] ?? ''));
            $sup = trim((string) ($payload['supervisor_id'] ?? ''));

            $assigned = $this->assignPreceptorUseCase->execute($noRawat, $nip, $inst, $nim, $sup);
            return $this->json(['ok' => true, 'assignment' => $assigned]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListHomeCare(array $query): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today));
        $to = trim((string) ($query['to'] ?? $today));

        return $this->json([
            'ok' => true,
            'visits' => $this->homeCareRepo->listKunjungan($from, $to),
        ]);
    }

    /**
     * Academic CI Supervisor view protected by Patient Privacy Shield.
     * De-identifies patient names & omits Medical Record numbers.
     *
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSupervisorVisits(array $query): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today));
        $to = trim((string) ($query['to'] ?? $today));

        $rawVisits = $this->homeCareRepo->listKunjungan($from, $to);
        $shielded = [];

        foreach ($rawVisits as $visit) {
            $name = (string) ($visit['nm_pasien'] ?? 'Pasien');
            $birth = (string) ($visit['tgl_lahir'] ?? '2000-01-01');
            $jk = (string) ($visit['jk'] ?? 'L');

            $birthYear = (int) substr($birth, 0, 4);
            $age = max(1, (int) date('Y') - $birthYear);

            $pseudonym = HomeCarePrivacyShield::pseudonymize($name, $age, $jk);

            // Strip sensitive clinical identifiers
            unset($visit['nm_pasien'], $visit['no_rkm_medis']);
            $visit['pseudonym_pasien'] = $pseudonym;
            $shielded[] = $visit;
        }

        return $this->json([
            'ok' => true,
            'visits' => $shielded,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function json(array $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => (string) json_encode($data, JSON_UNESCAPED_UNICODE),
        ];
    }
}
