<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Rme\UseCase\SaveTindakanDokterUseCase;
use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\CreateRadiologyOrderUseCase;
use App\Domain\Igd\Repository\IgdRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class IgdController
{
    public function __construct(
        private IgdRepositoryInterface $repo,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
        private ?SaveTindakanDokterUseCase $tindakanUseCase = null,
        private ?CreateLabOrderUseCase $labOrderUseCase = null,
        private ?CreateRadiologyOrderUseCase $radOrderUseCase = null,
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('igd/index', [
            'judul' => 'Instalasi Gawat Darurat (IGD) & Triase',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/igd',
            'csrf_token' => $csrfToken,
        ]);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiList(array $query = []): array
    {
        $today = date('Y-m-d');
        $from = trim((string) ($query['from'] ?? $today)) ?: $today;
        $to = trim((string) ($query['to'] ?? $from)) ?: $from;

        return $this->jsonResponse([
            'status' => 'success',
            'data' => $this->repo->listIgdVisits($from, $to),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveTriase(array $payload): array
    {
        try {
            $this->repo->saveTriase($payload);
            return $this->jsonResponse(['status' => 'success', 'message' => 'Data triase IGD berhasil disimpan.']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiUpdateDisposisi(array $payload): array
    {
        try {
            $nr = trim((string) ($payload['no_rawat'] ?? ''));
            $disp = trim((string) ($payload['disposisi'] ?? ''));
            $sl = trim((string) ($payload['status_lanjut'] ?? ($disp === 'Rawat Inap' ? 'Ranap' : 'Ralan')));
            $this->repo->updateDisposisi($nr, $disp, $sl);
            return $this->jsonResponse(['status' => 'success', 'message' => "Disposisi IGD diperbarui ke {$disp}."]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveTindakan(?UserAccount $actor, array $payload): array
    {
        if ($this->tindakanUseCase === null) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Layanan tindakan IGD belum dikonfigurasi.'], 503);
        }
        try {
            $res = $this->tindakanUseCase->execute(
                noRawat: new NoRawat((string) ($payload['no_rawat'] ?? '')),
                kdJenisPrw: trim((string) ($payload['kd_jenis_prw'] ?? '')),
                kdDokter: trim((string) ($payload['kd_dokter'] ?? ($actor?->getUsername() ?? 'DR001'))),
                claimType: trim((string) ($payload['claim_type'] ?? 'Tunai')) ?: 'Tunai'
            );
            return $this->jsonResponse(['status' => 'success', 'data' => $res], 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiOrderLab(?UserAccount $actor, array $payload): array
    {
        if ($this->labOrderUseCase === null) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Layanan order lab IGD belum dikonfigurasi.'], 503);
        }
        try {
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $res = $this->labOrderUseCase->execute(
                noRawat: new NoRawat((string) ($payload['no_rawat'] ?? '')),
                dokterPerujuk: trim((string) ($payload['dokter_perujuk'] ?? ($actor?->getUsername() ?? 'DR001'))),
                items: $items,
                status: 'ralan'
            );
            return $this->jsonResponse(['status' => 'success', 'orders' => array_map(static fn($o): array => [
                'no_order' => $o->noOrder,
                'no_rawat' => $o->noRawat->value(),
                'jenis_klaim' => $o->jenisKlaim->value(),
            ], $res)], 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiOrderRadiologi(?UserAccount $actor, array $payload): array
    {
        if ($this->radOrderUseCase === null) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Layanan order radiologi IGD belum dikonfigurasi.'], 503);
        }
        try {
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $res = $this->radOrderUseCase->execute(
                noRawat: new NoRawat((string) ($payload['no_rawat'] ?? '')),
                dokterPerujuk: trim((string) ($payload['dokter_perujuk'] ?? ($actor?->getUsername() ?? 'DR001'))),
                items: $items,
                status: 'ralan'
            );
            return $this->jsonResponse(['status' => 'success', 'orders' => array_map(static fn($o): array => [
                'no_order' => $o->noOrder,
                'no_rawat' => $o->noRawat->value(),
                'jenis_klaim' => $o->jenisKlaim->value(),
            ], $res)], 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param mixed $data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function jsonResponse(mixed $data, int $status = 200): array
    {
        return [
            'status' => $status,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($data, JSON_THROW_ON_ERROR),
        ];
    }
}
