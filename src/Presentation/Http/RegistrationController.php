<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Registration\DTO\CheckInMobileJknCommand;
use App\Application\Registration\DTO\RegisterInSiteCommand;
use App\Application\Registration\DTO\ShiftQueueCommand;
use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Application\Registration\UseCase\RegisterInSiteUseCase;
use App\Domain\Registration\Entity\BookingRegistrasi;
use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\Repository\PoliklinikRepositoryInterface;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Infrastructure\Realtime\EventBroadcasterInterface;
use App\Infrastructure\Realtime\EventMessage;
use App\Presentation\View\ViewRenderer;

final readonly class RegistrationController
{
    public function __construct(
        private \PDO $pdo,
        private PasienRepositoryInterface $pasienRepo,
        private PoliklinikRepositoryInterface $poliRepo,
        private RegPeriksaRepositoryInterface $regRepo,
        private RegisterInSiteUseCase $registerInSiteUseCase,
        private CheckInMobileJknUseCase $checkInMobileJknUseCase,
        private ClinicalGuardShieldUseCase $clinicalGuardUseCase,
        private ViewRenderer $renderer,
        private ?EventBroadcasterInterface $broadcaster = null,
        private ?NavigationBuilder $navBuilder = null
    ) {}

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function index(?UserAccount $currentUser = null, string $csrfToken = ''): array
    {
        $poliklinik = $this->poliRepo->findAllActive();

        $stmtDokter = $this->pdo->query("SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter ASC");
        $dokter = $stmtDokter ? $stmtDokter->fetchAll(\PDO::FETCH_ASSOC) : [];

        $stmtPenjab = $this->pdo->query("SELECT kd_pj, png_jawab FROM penjab WHERE status = '1' ORDER BY png_jawab ASC");
        $penjab = $stmtPenjab ? $stmtPenjab->fetchAll(\PDO::FETCH_ASSOC) : [];

        $navigations = $this->navBuilder !== null
            ? $this->navBuilder->buildForUser($currentUser)
            : (new NavigationBuilder())->buildForUser($currentUser);

        $body = $this->renderer->render('registration/index', [
            'judul' => 'Pendaftaran Rawat Jalan',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'pendaftaran',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Petugas Medis'),
            'user_role' => $currentUser?->getRole() ?? 'Medis',
            'csrf_token' => $csrfToken,
            'poliklinik' => $poliklinik,
            'dokter' => $dokter,
            'penjab' => $penjab,
            'navigations' => $navigations,
        ]);

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
    public function apiSearchPatients(array $queryParams = []): array
    {
        $query = trim((string) ($queryParams['q'] ?? ''));
        if ($query === '') {
            return $this->jsonResponse([]);
        }

        $patients = $this->pasienRepo->search($query, 20);

        $results = array_map(static fn($p) => [
            'no_rkm_medis' => $p->noRkmMedis->value(),
            'nm_pasien' => $p->nmPasien,
            'nik' => $p->nik,
            'jk' => $p->jk === 'L' ? 'Laki-Laki' : 'Perempuan',
            'tgl_lahir' => $p->tglLahir->format('Y-m-d'),
            'alamat' => $p->alamat,
            'no_tlp' => $p->noTelp,
            'no_peserta' => $p->noPeserta,
            'keluarga' => $p->keluarga,
            'namakeluarga' => $p->namaKeluarga,
            'alamatpj' => $p->alamatPj,
        ], $patients);

        return $this->jsonResponse($results);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiRegisterInSite(array $postData = []): array
    {
        try {
            $tglReg = !empty($postData['tgl_registrasi'])
                ? $this->parseStrictDate((string) $postData['tgl_registrasi'], 'tgl_registrasi')
                : null;

            $cmd = new RegisterInSiteCommand(
                noRkmMedis: (string) ($postData['no_rkm_medis'] ?? ''),
                kdPoli: (string) ($postData['kd_poli'] ?? ''),
                kdDokter: (string) ($postData['kd_dokter'] ?? ''),
                kdPj: (string) ($postData['kd_pj'] ?? ''),
                tglRegistrasi: $tglReg,
                jamReg: !empty($postData['jam_reg']) ? (string) $postData['jam_reg'] : null,
                shift: (string) ($postData['shift'] ?? ''),
                prefik: (string) ($postData['prefik'] ?? ''),
                room: (string) ($postData['room'] ?? ''),
                pJawab: (string) ($postData['p_jawab'] ?? '-'),
                almtPj: (string) ($postData['almt_pj'] ?? '-'),
                hubunganPj: (string) ($postData['hubunganpj'] ?? '-')
            );

            $result = $this->registerInSiteUseCase->execute($cmd);

            if ($result instanceof RegPeriksa) {
                $this->broadcastDoctorQueueUpdate($result);
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Pendaftaran pasien berhasil diproses.',
                    'data' => [
                        'no_rawat' => $result->noRawat->value(),
                        'no_reg' => $result->noReg->value(),
                    ],
                ]);
            }

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Booking pendaftaran masa depan berhasil disimpan.',
                'data' => [
                    'no_rawat' => null,
                    'no_reg' => $result instanceof BookingRegistrasi ? $result->noReg->value() : null,
                ],
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCheckInMobileJkn(array $postData = []): array
    {
        try {
            $today = !empty($postData['today'])
                ? $this->parseStrictDate((string) $postData['today'], 'today')
                : null;

            $cmd = new CheckInMobileJknCommand(
                noRkmMedis: (string) ($postData['no_rkm_medis'] ?? ''),
                today: $today
            );

            $result = $this->checkInMobileJknUseCase->execute($cmd);
            $this->broadcastDoctorQueueUpdate($result);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Check-in pasien Mobile JKN berhasil.',
                'data' => [
                    'no_rawat' => $result->noRawat->value(),
                    'no_reg' => $result->noReg->value(),
                ],
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListToday(array $queryParams = []): array
    {
        $date = (string) ($queryParams['date'] ?? date('Y-m-d'));

        $sql = "SELECT r.no_reg, r.no_rawat, r.no_rkm_medis, p.nm_pasien, 
                       pol.nm_poli, d.nm_dokter, pj.png_jawab, r.stts, r.status_bayar
                FROM reg_periksa r
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
                WHERE r.tgl_registrasi = :tgl AND r.stts != 'Batal'
                ORDER BY r.no_reg ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['tgl' => $date]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->jsonResponse($rows);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiShiftQueue(array $postData = []): array
    {
        try {
            $cmd = new ShiftQueueCommand(
                noRawat: (string) ($postData['no_rawat'] ?? ''),
                newNoReg: (string) ($postData['new_no_reg'] ?? ''),
                newShift: isset($postData['new_shift']) ? (string) $postData['new_shift'] : null
            );

            $result = $this->clinicalGuardUseCase->shiftQueue($cmd);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Nomor antrean berhasil digeser.',
                'data' => [
                    'no_rawat' => $result->noRawat->value(),
                    'no_reg' => $result->noReg->value(),
                ],
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function parseStrictDate(string $dateStr, string $fieldName): \DateTimeImmutable
    {
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateStr);
        if ($dt === false || $dt->format('Y-m-d') !== $dateStr) {
            throw new \InvalidArgumentException("Format tanggal {$fieldName} tidak valid (wajib YYYY-MM-DD yang valid pada kalender).");
        }

        return $dt;
    }

    private function broadcastDoctorQueueUpdate(RegPeriksa $reg): void
    {
        if ($this->broadcaster === null) {
            return;
        }

        $channel = 'dokter_' . $reg->kdDokter;
        $this->broadcaster->publish($channel, new EventMessage(
            event: 'pasien_ralan_updated',
            data: [
                'no_rawat' => $reg->noRawat->value(),
                'no_reg' => $reg->noReg->value(),
                'kd_poli' => $reg->kdPoli,
                'kd_dokter' => $reg->kdDokter,
                'tgl_registrasi' => $reg->tglRegistrasi->format('Y-m-d'),
            ]
        ));
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
