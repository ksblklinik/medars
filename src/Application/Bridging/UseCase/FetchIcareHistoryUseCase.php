<?php

declare(strict_types=1);

namespace App\Application\Bridging\UseCase;

use App\Domain\Bridging\Repository\IcareRepositoryInterface;

final readonly class FetchIcareHistoryUseCase
{
    public function __construct(private IcareRepositoryInterface $repo)
    {
    }

    /**
     * @return array{ok: bool, status_code: int, no_rawat: string, no_peserta: string, nm_pasien: string, kd_dokter: string, nm_dokter: string, url: string, message: string}
     */
    public function execute(string $noRawat, string $mode = 'fkrtl'): array
    {
        $cleanNoRawat = trim($noRawat);
        if ($cleanNoRawat === '') {
            return $this->error(422, '', 'Nomor rawat wajib diisi.');
        }

        $visit = $this->repo->findVisitParticipant($cleanNoRawat);
        if ($visit === null) {
            return $this->error(404, $cleanNoRawat, 'Data kunjungan tidak ditemukan.');
        }

        $noPeserta = trim((string) ($visit['no_peserta'] ?? ''));
        if ($noPeserta === '') {
            $noPeserta = trim((string) ($visit['no_ktp'] ?? ''));
        }
        if ($noPeserta === '') {
            return $this->error(422, $cleanNoRawat, 'Nomor Kartu BPJS (no_peserta) maupun NIK pasien belum terisi.');
        }

        $kdDokter = trim((string) ($visit['kd_dokter'] ?? ''));
        $nmPasien = trim((string) ($visit['nm_pasien'] ?? '-'));
        $nmDokter = trim((string) ($visit['nm_dokter'] ?? '-'));

        $baseUrl = strtolower(trim($mode)) === 'fktp'
            ? 'https://pcarejkn.bpjs-kesehatan.go.id/icare/riwayat'
            : 'https://apijkn.bpjs-kesehatan.go.id/icare/riwayat';
        $viewerUrl = $baseUrl . '?param=' . rawurlencode($noPeserta) . '&kodedokter=' . rawurlencode($kdDokter);

        return [
            'ok' => true,
            'status_code' => 200,
            'no_rawat' => $cleanNoRawat,
            'no_peserta' => $noPeserta,
            'nm_pasien' => $nmPasien,
            'kd_dokter' => $kdDokter,
            'nm_dokter' => $nmDokter,
            'url' => $viewerUrl,
            'message' => 'Tautan i-Care JKN BPJS siap dibuka.',
        ];
    }

    /**
     * @return array{ok: bool, status_code: int, no_rawat: string, no_peserta: string, nm_pasien: string, kd_dokter: string, nm_dokter: string, url: string, message: string}
     */
    private function error(int $code, string $noRawat, string $message): array
    {
        return [
            'ok' => false,
            'status_code' => $code,
            'no_rawat' => $noRawat,
            'no_peserta' => '',
            'nm_pasien' => '',
            'kd_dokter' => '',
            'nm_dokter' => '',
            'url' => '',
            'message' => $message,
        ];
    }
}
