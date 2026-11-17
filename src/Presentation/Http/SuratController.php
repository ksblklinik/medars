<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Rme\Repository\SuratMedisRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class SuratController
{
    public function __construct(
        private SuratMedisRepositoryInterface $repo,
        private CreateSuratMedisUseCase $createUseCase,
        private ViewRenderer $renderer,
        private ?NavigationBuilder $navBuilder = null,
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

        $body = $this->renderer->render('surat/index', [
            'judul' => 'Surat Keterangan Medis',
            'navigations' => $navigations,
            'current_user' => $currentUser,
            'current_path' => '/surat',
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
        try {
            $type = trim((string) ($query['type'] ?? 'sakit')) ?: 'sakit';
            $q = trim((string) ($query['q'] ?? ''));
            return $this->jsonResponse(['status' => 'success', 'data' => $this->repo->listSurat($type, $q)]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCreate(?UserAccount $actor, array $payload): array
    {
        try {
            $type = trim((string) ($payload['type'] ?? 'sakit'));
            $noRawat = new NoRawat(trim((string) ($payload['no_rawat'] ?? '')));

            $noSurat = match ($type) {
                'sakit' => $this->createUseCase->createSuratSakit(
                    $noRawat,
                    new \DateTimeImmutable((string) ($payload['tanggal_awal'] ?? date('Y-m-d'))),
                    new \DateTimeImmutable((string) ($payload['tanggal_akhir'] ?? date('Y-m-d')))
                )->noSurat,
                'hamil' => $this->createUseCase->createSuratHamil(
                    $noRawat,
                    new \DateTimeImmutable((string) ($payload['tanggal_periksa'] ?? date('Y-m-d'))),
                    trim((string) ($payload['hasil_periksa'] ?? 'Positif'))
                )->noSurat,
                'narkoba' => $this->createUseCase->createSuratBebasNarkoba(
                    $noRawat,
                    new \DateTimeImmutable((string) ($payload['tanggal'] ?? date('Y-m-d'))),
                    trim((string) ($payload['kategori'] ?? 'Umum')),
                    trim((string) ($payload['kd_dokter'] ?? ($actor?->getUsername() ?? 'DR01'))),
                    trim((string) ($payload['keperluan'] ?? 'Administrasi')),
                    trim((string) ($payload['opiat'] ?? 'NEGATIF')),
                    trim((string) ($payload['ganja'] ?? 'NEGATIF')),
                    trim((string) ($payload['amphetamin'] ?? 'NEGATIF')),
                    trim((string) ($payload['methamphetamin'] ?? 'NEGATIF')),
                    trim((string) ($payload['benzodiazepin'] ?? 'NEGATIF')),
                    trim((string) ($payload['cocain'] ?? 'NEGATIF'))
                )->noSurat,
                default => throw new \InvalidArgumentException("Jenis surat tidak dikenal: '{$type}'."),
            };

            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Surat berhasil diterbitkan ({$noSurat}).",
                'data' => ['no_surat' => $noSurat, 'type' => $type],
            ]);
        } catch (\Throwable $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printSurat(array $query = []): array
    {
        $type = trim((string) ($query['type'] ?? 'sakit'));
        $noSurat = trim((string) ($query['no_surat'] ?? ''));

        try {
            $row = $noSurat !== '' ? $this->repo->findSuratForPrint($type, $noSurat) : null;
        } catch (\InvalidArgumentException) {
            $row = null;
        }
        if ($row === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Surat keterangan medis tidak ditemukan.</h3>',
            ];
        }

        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $title = match ($type) {
            'hamil' => 'SURAT KETERANGAN PEMERIKSAAN KEHAMILAN',
            'narkoba' => 'SURAT KETERANGAN BEBAS NARKOBA',
            default => 'SURAT KETERANGAN SAKIT',
        };

        $detail = match ($type) {
            'hamil' => '<p>Berdasarkan pemeriksaan pada tanggal <strong>' . $e($row['tanggalperiksa']) . '</strong>, yang bersangkutan dinyatakan: <strong>' . $e($row['hasilperiksa']) . '</strong>.</p>',
            'narkoba' => '<p>Untuk keperluan: <strong>' . $e($row['keperluan']) . '</strong> (' . $e($row['kategori']) . '). Hasil uji laboratorium: Opiat <strong>' . $e($row['opiat']) . '</strong>, Ganja <strong>' . $e($row['ganja']) . '</strong>, Amphetamin <strong>' . $e($row['amphetamin']) . '</strong>, Methamphetamin <strong>' . $e($row['methamphetamin']) . '</strong>, Benzodiazepin <strong>' . $e($row['benzodiazepin']) . '</strong>, Cocain <strong>' . $e($row['cocain']) . '</strong>.</p>',
            default => '<p>Yang bersangkutan perlu beristirahat karena sakit selama <strong>' . $e($row['lamasakit']) . '</strong> terhitung mulai tanggal <strong>' . $e($row['tanggalawal']) . '</strong> sampai dengan <strong>' . $e($row['tanggalakhir']) . '</strong>.</p>',
        };

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>' . $e($title) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:24px;max-width:720px;line-height:1.5}h2{margin:0;color:#00897b;text-align:center}hr{border:none;border-top:2px solid #00897b;margin:8px 0 16px}.meta{font-size:13px;margin-bottom:14px}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Surat</button></div>'
            . '<h2>' . $e($title) . '</h2>'
            . '<div style="text-align:center;font-size:13px;">Nomor: <strong>' . $e($row['no_surat']) . '</strong></div><hr>'
            . '<div class="meta">'
            . 'No. Rawat: ' . $e($row['no_rawat']) . ' | No. RM: ' . $e($row['no_rkm_medis'] ?? '-') . '<br>'
            . 'Nama Pasien: <strong>' . $e($row['nm_pasien'] ?? '-') . '</strong> (' . $e($row['jk'] ?? '-') . ', Tgl Lahir: ' . $e($row['tgl_lahir'] ?? '-') . ')<br>'
            . 'Alamat: ' . $e($row['alamat'] ?? '-')
            . '</div>'
            . $detail
            . '<div style="margin-top:36px;text-align:right;font-size:13px;">Dokter Pemeriksa<br><br><br><strong>' . $e($row['nm_dokter'] ?? 'Dokter Penanggung Jawab') . '</strong></div>'
            . '</body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
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
