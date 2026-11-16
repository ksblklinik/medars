<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Domain\Registration\Repository\PoliklinikRepositoryInterface;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Infrastructure\Realtime\EventBroadcasterInterface;
use App\Infrastructure\Realtime\EventMessage;
use App\Presentation\View\ViewRenderer;

final readonly class DisplayQueueController
{
    public function __construct(
        private \PDO $pdo,
        private PoliklinikRepositoryInterface $poliRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private EventBroadcasterInterface $broadcaster,
        private ViewRenderer $renderer
    ) {}

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(): array
    {
        $poliklinik = $this->poliRepo->findAllActive();

        $body = $this->renderer->render('display/index', [
            'judul' => 'Display Antrean Poliklinik',
            'klinik_nama' => 'SIMRS Medars',
            'poliklinik' => $poliklinik,
        ], false);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function kiosk(): array
    {
        $body = $this->renderer->render('kiosk/index', [
            'judul' => 'Layar Kiosk Mandiri',
            'klinik_nama' => 'SIMRS Medars',
        ], false);

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiQueueSummary(array $queryParams = []): array
    {
        $date = (string) ($queryParams['date'] ?? date('Y-m-d'));
        $poliklinik = $this->poliRepo->findAllActive();

        $summary = [];

        foreach ($poliklinik as $poli) {
            // 1. Antrean yang sedang dipanggil
            $sqlCurrent = "SELECT r.no_reg, r.no_rawat, d.nm_dokter, r.room 
                           FROM reg_periksa r
                           LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                           WHERE r.kd_poli = :poli 
                             AND r.tgl_registrasi = :tgl 
                             AND r.stts IN ('Awal', 'Dipanggil') 
                           ORDER BY r.jam_reg DESC 
                           LIMIT 1";

            $stmtCurrent = $this->pdo->prepare($sqlCurrent);
            $stmtCurrent->execute(['poli' => $poli->kdPoli, 'tgl' => $date]);
            $currentRow = $stmtCurrent->fetch(\PDO::FETCH_ASSOC);

            // 2. Daftar antrean berikutnya yang sedang menunggu
            $sqlWaiting = "SELECT r.no_reg 
                           FROM reg_periksa r 
                           WHERE r.kd_poli = :poli 
                             AND r.tgl_registrasi = :tgl 
                             AND r.stts = 'Belum' 
                           ORDER BY r.no_reg ASC 
                           LIMIT 5";

            $stmtWaiting = $this->pdo->prepare($sqlWaiting);
            $stmtWaiting->execute(['poli' => $poli->kdPoli, 'tgl' => $date]);
            $waitingRows = $stmtWaiting->fetchAll(\PDO::FETCH_COLUMN);

            $summary[] = [
                'kd_poli' => $poli->kdPoli,
                'nm_poli' => $poli->nmPoli,
                'current_queue' => $currentRow ? (string) $currentRow['no_reg'] : '---',
                'current_rawat' => $currentRow ? (string) $currentRow['no_rawat'] : null,
                'nm_dokter' => $currentRow ? (string) ($currentRow['nm_dokter'] ?? 'Dokter Bertugas') : 'Dokter Bertugas',
                'room' => $currentRow ? (string) ($currentRow['room'] ?? 'Ruang Layanan') : 'Ruang Layanan',
                'waiting_queues' => $waitingRows ?: [],
            ];
        }

        return $this->jsonResponse($summary);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCallQueue(array $postData = []): array
    {
        $noRawat = (string) ($postData['no_rawat'] ?? '');
        $room = (string) ($postData['room'] ?? '');

        $sql = 'SELECT r.no_reg, r.no_rawat, r.kd_poli, pol.nm_poli, d.nm_dokter, r.room
                FROM reg_periksa r
                LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                WHERE r.no_rawat = :rawat LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rawat' => $noRawat]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => "Kunjungan dengan nomor rawat '{$noRawat}' tidak ditemukan.",
            ], 404);
        }

        $finalRoom = $room !== '' ? $room : ($row['room'] ?: 'Ruang Periksa');

        // Update status menjadi 'Awal' (sedang dipanggil di depan poli)
        $sqlUpdate = "UPDATE reg_periksa SET stts = 'Awal', room = :room WHERE no_rawat = :rawat";
        $stmtUpdate = $this->pdo->prepare($sqlUpdate);
        $stmtUpdate->execute(['room' => $finalRoom, 'rawat' => $noRawat]);

        $noReg = (string) $row['no_reg'];
        $nmPoli = (string) ($row['nm_poli'] ?? 'Poliklinik');
        $soundText = "Nomor antrean {$noReg}, silakan menuju ke {$nmPoli} {$finalRoom}";

        // Broadcast event real-time SSE ke seluruh layar Display TV
        $eventMsg = new EventMessage(
            event: 'queue.called',
            data: [
                'no_rawat' => $noRawat,
                'no_reg' => $noReg,
                'kd_poli' => (string) $row['kd_poli'],
                'nm_poli' => $nmPoli,
                'nm_dokter' => (string) ($row['nm_dokter'] ?? ''),
                'room' => $finalRoom,
                'sound_text' => $soundText,
                'timestamp' => date('c'),
            ]
        );
        $this->broadcaster->publish('antrean', $eventMsg);

        return $this->jsonResponse([
            'status' => 'success',
            'message' => 'Panggilan antrean berhasil disiarkan.',
            'data' => [
                'no_rawat' => $noRawat,
                'no_reg' => $noReg,
                'nm_poli' => $nmPoli,
                'sound_text' => $soundText,
            ],
        ]);
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
