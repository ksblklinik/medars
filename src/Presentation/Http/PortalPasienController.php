<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Registration\Repository\PortalPasienRepositoryInterface;
use App\Presentation\View\ViewRenderer;

final readonly class PortalPasienController
{
    public function __construct(
        private PortalPasienRepositoryInterface $repo,
        private ViewRenderer $renderer,
    ) {
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(): array
    {
        $body = $this->renderer->render('portal_pasien/index', [
            'judul' => 'Portal Pasien Mandiri - SIMRS Medars',
        ], false);

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
    public function apiLookup(array $payload): array
    {
        $rm = trim((string) ($payload['no_rkm_medis'] ?? ''));
        $tl = trim((string) ($payload['tgl_lahir'] ?? ''));
        if ($rm === '' || $tl === '') {
            return $this->jsonResponse(['status' => 'error', 'message' => 'No. Rekam Medis dan Tanggal Lahir wajib diisi.'], 422);
        }

        $data = $this->repo->verifyAndLoadPortal($rm, $tl);
        if ($data === null) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Data pasien tidak ditemukan atau tanggal lahir tidak sesuai.'], 404);
        }

        return $this->jsonResponse(['status' => 'success', 'data' => $data]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBooking(array $payload): array
    {
        try {
            $res = $this->repo->createOnlineBooking(
                (string) ($payload['no_rkm_medis'] ?? ''),
                (string) ($payload['tgl_lahir'] ?? ''),
                (string) ($payload['tanggal_periksa'] ?? ''),
                (string) ($payload['kd_poli'] ?? ''),
                (string) ($payload['kd_dokter'] ?? ''),
                (string) ($payload['kd_pj'] ?? 'A09')
            );
            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Booking berhasil! Nomor antrean Anda: {$res['no_reg']} ({$res['tanggal_periksa']}).",
                'data' => $res,
            ]);
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
