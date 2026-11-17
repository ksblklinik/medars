<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Registration\DTO\CheckInMobileJknCommand;
use App\Application\Registration\DTO\RegisterInSiteCommand;
use App\Application\Registration\DTO\ShiftQueueCommand;
use App\Application\Registration\UseCase\CheckInMobileJknUseCase;
use App\Application\Registration\UseCase\ClinicalGuardShieldUseCase;
use App\Application\Registration\UseCase\RegisterInSiteUseCase;
use App\Application\Registration\UseCase\UpdateRegistrationStatusUseCase;
use App\Domain\Registration\Entity\BookingRegistrasi;
use App\Domain\Registration\Entity\RegPeriksa;
use App\Domain\Registration\Repository\PasienRepositoryInterface;
use App\Domain\Registration\Repository\PoliklinikRepositoryInterface;
use App\Domain\Registration\Repository\RegPeriksaRepositoryInterface;
use App\Domain\Registration\ValueObject\NoRawat;
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
        private ?NavigationBuilder $navBuilder = null,
        private ?UpdateRegistrationStatusUseCase $updateStatusUseCase = null,
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
        $from = trim((string) ($queryParams['from'] ?? $date)) ?: $date;
        $to = trim((string) ($queryParams['to'] ?? $from)) ?: $from;
        $sttsFilter = trim((string) ($queryParams['stts'] ?? ''));

        $where = 'r.tgl_registrasi BETWEEN :from AND :to';
        $params = ['from' => $from, 'to' => $to];

        if ($sttsFilter === 'all') {
            // show all including Batal
        } elseif ($sttsFilter !== '') {
            $where .= ' AND r.stts = :stts';
            $params['stts'] = $sttsFilter;
        } else {
            $where .= " AND r.stts != 'Batal'";
        }

        $sql = "SELECT r.no_reg, r.no_rawat, r.tgl_registrasi, r.no_rkm_medis, p.nm_pasien,
                       pol.nm_poli, d.nm_dokter, pj.png_jawab, r.stts, r.status_bayar
                FROM reg_periksa r
                LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
                LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
                LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
                LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
                WHERE {$where}
                ORDER BY r.tgl_registrasi DESC, r.no_reg ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->jsonResponse($rows);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiUpdateStatus(array $postData = []): array
    {
        try {
            if ($this->updateStatusUseCase === null) {
                throw new \DomainException('Layanan ubah status registrasi belum dikonfigurasi.');
            }
            $nr = new NoRawat((string) ($postData['no_rawat'] ?? ''));
            $stts = trim((string) ($postData['stts'] ?? ''));
            $kdPoli = isset($postData['kd_poli']) ? trim((string) $postData['kd_poli']) : null;
            $kdDokter = isset($postData['kd_dokter']) ? trim((string) $postData['kd_dokter']) : null;

            $this->updateStatusUseCase->execute($nr, $stts, $kdPoli, $kdDokter);

            return $this->jsonResponse(['status' => 'success', 'message' => 'Status kunjungan berhasil diperbarui.']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
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
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiChangePoliDokter(array $post): array
    {
        $noRawat = trim((string) ($post['no_rawat'] ?? ''));
        $kdPoli = trim((string) ($post['kd_poli'] ?? ''));
        $kdDokter = trim((string) ($post['kd_dokter'] ?? ''));
        if ($noRawat === '' || $kdPoli === '' || $kdDokter === '') {
            return $this->jsonResponse(['status' => 'error', 'message' => 'No. Rawat, Poliklinik, dan Dokter wajib diisi.'], 422);
        }
        $stmt = $this->pdo->prepare('UPDATE reg_periksa SET kd_poli = :kp, kd_dokter = :kd WHERE no_rawat = :nr');
        $stmt->execute([':kp' => $kdPoli, ':kd' => $kdDokter, ':nr' => $noRawat]);
        if ($stmt->rowCount() === 0) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Kunjungan tidak ditemukan.'], 404);
        }
        return $this->jsonResponse(['status' => 'success', 'message' => 'Poliklinik & Dokter diperbarui.']);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBerkasList(array $query = []): array
    {
        $noRawat = trim((string) ($query['no_rawat'] ?? ''));
        $sql = 'SELECT id, no_rawat, kode, nama_file, mime_type, lokasi_file, uploaded_at FROM berkas_digital_perawatan';
        $params = [];
        if ($noRawat !== '') {
            $sql .= ' WHERE no_rawat = :nr';
            $params[':nr'] = $noRawat;
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []]);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBerkasUpload(array $post): array
    {
        $noRawat = trim((string) ($post['no_rawat'] ?? ''));
        $kode = trim((string) ($post['kode'] ?? 'BERKAS')) ?: 'BERKAS';
        $namaFile = trim((string) ($post['nama_file'] ?? ''));
        $mime = trim((string) ($post['mime_type'] ?? 'application/pdf')) ?: 'application/pdf';
        if ($noRawat === '' || $namaFile === '') {
            return $this->jsonResponse(['status' => 'error', 'message' => 'No. Rawat dan nama file wajib diisi.'], 422);
        }
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true)) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Format berkas harus PDF/JPG/PNG/WEBP.'], 422);
        }
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($namaFile)) ?: 'berkas';
        $lokasi = 'berkas_ralan/' . str_replace('/', '_', $noRawat) . '_' . uniqid('', true) . '_' . $safeName;
        $rawBase64 = trim((string) ($post['content_base64'] ?? ''));
        if ($rawBase64 !== '') {
            if (str_contains($rawBase64, ',')) {
                $rawBase64 = substr($rawBase64, strpos($rawBase64, ',') + 1);
            }
            $binary = base64_decode($rawBase64, true);
            if ($binary !== false) {
                $dir = dirname(__DIR__, 3) . '/storage/berkas_ralan';
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                file_put_contents(dirname(__DIR__, 3) . '/storage/' . $lokasi, $binary);
            }
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO berkas_digital_perawatan (no_rawat, kode, nama_file, mime_type, lokasi_file, uploaded_at)
             VALUES (:nr, :k, :nf, :mt, :lf, :ua)'
        );
        $stmt->execute([
            ':nr' => $noRawat,
            ':k' => $kode,
            ':nf' => $safeName,
            ':mt' => $mime,
            ':lf' => $lokasi,
            ':ua' => date('Y-m-d H:i:s'),
        ]);
        return $this->jsonResponse(['status' => 'success', 'data' => ['id' => (int) $this->pdo->lastInsertId(), 'nama_file' => $safeName]], 201);
    }

    /**
     * @param array<string, mixed> $post
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiBerkasDelete(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'ID berkas tidak valid.'], 422);
        }
        $stmt = $this->pdo->prepare('DELETE FROM berkas_digital_perawatan WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $this->jsonResponse(['status' => $stmt->rowCount() > 0 ? 'success' : 'error'], $stmt->rowCount() > 0 ? 200 : 404);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printAntrean(array $query = []): array
    {
        $noRawat = trim((string) ($query['no_rawat'] ?? ''));
        $stmt = $this->pdo->prepare(
            'SELECT r.no_rawat, r.no_reg, r.tgl_registrasi, r.jam_reg, r.stts, p.no_rkm_medis, p.nm_pasien, pol.nm_poli, d.nm_dokter, pj.png_jawab
             FROM reg_periksa r
             LEFT JOIN pasien p ON p.no_rkm_medis = r.no_rkm_medis
             LEFT JOIN poliklinik pol ON pol.kd_poli = r.kd_poli
             LEFT JOIN dokter d ON d.kd_dokter = r.kd_dokter
             LEFT JOIN penjab pj ON pj.kd_pj = r.kd_pj
             WHERE r.no_rawat = :nr LIMIT 1'
        );
        $stmt->execute([':nr' => $noRawat]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['status' => 404, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>Data kunjungan tidak ditemukan.</h3>'];
        }
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Bukti Antrean ' . $e($row['no_reg']) . '</title>'
            . '<style>body{font-family:monospace;max-width:340px;margin:20px auto;font-size:13px;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:8px;"><button onclick="window.print()">Cetak Antrean</button></div>'
            . '<h3 style="text-align:center;margin:0;">BUKTI ANTREAN RAWAT JALAN</h3><hr>'
            . '<div style="text-align:center;font-size:28px;font-weight:bold;margin:8px 0;">' . $e($row['no_reg']) . '</div>'
            . '<div>No. Rawat: ' . $e($row['no_rawat']) . '</div>'
            . '<div>No. RM: ' . $e($row['no_rkm_medis']) . '</div>'
            . '<div>Pasien: <strong>' . $e($row['nm_pasien']) . '</strong></div>'
            . '<div>Poliklinik: ' . $e($row['nm_poli']) . '</div>'
            . '<div>Dokter: ' . $e($row['nm_dokter']) . '</div>'
            . '<div>Penjamin: ' . $e($row['png_jawab']) . '</div>'
            . '<div>Tanggal: ' . $e($row['tgl_registrasi']) . ' ' . $e($row['jam_reg']) . '</div>'
            . '</body></html>';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
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
