<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Registration\UseCase\DeleteMasterPasienUseCase;
use App\Application\Registration\UseCase\SaveMasterPasienUseCase;
use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class PasienController
{
    public function __construct(
        private PasienRepositoryInterface $pasienRepo,
        private SaveMasterPasienUseCase $saveUseCase,
        private DeleteMasterPasienUseCase $deleteUseCase,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
    ) {}

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('pasien/index', [
            'judul' => 'Master Pasien',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'pasien',
            'module_icon' => 'fa-address-card',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Petugas RM'),
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
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function cetakKartu(array $query): array
    {
        try {
            $rm = new NoRkmMedis(trim((string) ($query['no_rkm_medis'] ?? '')));
            $raw = $this->pasienRepo->findRawByNoRkmMedis($rm);
            if ($raw === null) {
                return [
                    'status' => 404,
                    'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                    'body' => '<h3>Data pasien tidak ditemukan.</h3>',
                ];
            }

            $body = $this->renderer->render('pasien/kartu', [
                'pasien' => $raw,
                'klinik_nama' => 'SIMRS Medars',
            ], false);

            return [
                'status' => 200,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => $body,
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'status' => 422,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'),
            ];
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiList(array $query): array
    {
        $q = trim((string) ($query['q'] ?? ''));
        $limit = max(1, min(100, (int) ($query['limit'] ?? 20)));
        $page = max(1, (int) ($query['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $res = $this->pasienRepo->listPaginated($q, $limit, $offset);

        return $this->json([
            'ok' => true,
            'rows' => $res['rows'],
            'total' => $res['total'],
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiMaxId(): array
    {
        $nextRm = $this->pasienRepo->getNextNoRkmMedis();
        return $this->json([
            'ok' => true,
            'next_no_rkm_medis' => $nextRm->value(),
        ]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDetail(array $query): array
    {
        try {
            $rm = new NoRkmMedis(trim((string) ($query['no_rkm_medis'] ?? '')));
            $raw = $this->pasienRepo->findRawByNoRkmMedis($rm);
            if ($raw === null) {
                return $this->json(['ok' => false, 'error' => 'Pasien tidak ditemukan.'], 404);
            }
            return $this->json(['ok' => true, 'pasien' => $raw]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiHistory(array $query): array
    {
        try {
            $rm = new NoRkmMedis(trim((string) ($query['no_rkm_medis'] ?? '')));
            return $this->json([
                'ok' => true,
                'history' => $this->pasienRepo->getVisitHistory($rm),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSave(array $payload): array
    {
        try {
            $pasien = $this->saveUseCase->execute($payload);
            return $this->json([
                'ok' => true,
                'pasien' => [
                    'no_rkm_medis' => $pasien->noRkmMedis->value(),
                    'nm_pasien' => $pasien->nmPasien,
                    'no_ktp' => $pasien->nik,
                    'jk' => $pasien->jk,
                    'tgl_lahir' => $pasien->tglLahir->format('Y-m-d'),
                    'alamat' => $pasien->alamat,
                    'no_tlp' => $pasien->noTelp,
                    'no_peserta' => $pasien->noPeserta,
                ],
            ]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDelete(array $payload): array
    {
        try {
            $rm = new NoRkmMedis(trim((string) ($payload['no_rkm_medis'] ?? '')));
            $this->deleteUseCase->execute($rm);
            return $this->json(['ok' => true, 'deleted' => $rm->value()]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
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
