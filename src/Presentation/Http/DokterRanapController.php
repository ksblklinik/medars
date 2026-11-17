<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Rme\UseCase\SaveTindakanDokterUseCase;
use App\Application\Support\UseCase\CreateLabOrderUseCase;
use App\Application\Support\UseCase\CreateRadiologyOrderUseCase;
use App\Domain\Ranap\Repository\DokterRanapRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class DokterRanapController
{
    public function __construct(
        private DokterRanapRepositoryInterface $repo,
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

        $body = $this->renderer->render('dokter_ranap/index', [
            'judul' => 'Dokter Rawat Inap (CPPT & Visite)',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/dokter-ranap',
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
    public function apiPatients(array $query = []): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listRanapPatients($q)]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListCppt(array $query = []): array
    {
        $nr = trim((string) ($query['no_rawat'] ?? ''));
        if ($nr === '') {
            return $this->jsonResponse(['status' => 'success', 'data' => []]);
        }
        return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listCppt($nr)]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveCppt(?UserAccount $actor, array $payload): array
    {
        try {
            $payload['nip'] = $actor?->getUsername() ?? (string) ($payload['nip'] ?? 'DR01');
            $this->repo->saveCppt($payload);
            return $this->jsonResponse(['status' => 'success', 'message' => 'CPPT Ranap berhasil disimpan.']);
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
            return $this->jsonResponse(['status' => 'error', 'message' => 'Layanan tindakan ranap belum dikonfigurasi.'], 503);
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
            return $this->jsonResponse(['status' => 'error', 'message' => 'Layanan order lab ranap belum dikonfigurasi.'], 503);
        }
        try {
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $res = $this->labOrderUseCase->execute(
                noRawat: new NoRawat((string) ($payload['no_rawat'] ?? '')),
                dokterPerujuk: trim((string) ($payload['dokter_perujuk'] ?? ($actor?->getUsername() ?? 'DR001'))),
                items: $items,
                status: 'ranap'
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
            return $this->jsonResponse(['status' => 'error', 'message' => 'Layanan order radiologi ranap belum dikonfigurasi.'], 503);
        }
        try {
            $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $res = $this->radOrderUseCase->execute(
                noRawat: new NoRawat((string) ($payload['no_rawat'] ?? '')),
                dokterPerujuk: trim((string) ($payload['dokter_perujuk'] ?? ($actor?->getUsername() ?? 'DR001'))),
                items: $items,
                status: 'ranap'
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
