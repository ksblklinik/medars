<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Rme\DTO\SavePrbCommand;
use App\Application\Rme\DTO\SaveSoapCommand;
use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Application\Rme\UseCase\DeleteOdontogramUseCase;
use App\Application\Rme\UseCase\SaveOdontogramUseCase;
use App\Application\Rme\UseCase\SavePrbPrescriptionUseCase;
use App\Application\Rme\UseCase\SaveSoapUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Entity\DiagnosaPasien;
use App\Domain\Rme\Entity\Odontogram;
use App\Domain\Rme\Repository\IcdGroundingRepositoryInterface;
use App\Domain\Rme\Repository\OdontogramRepositoryInterface;
use App\Domain\Rme\Repository\RmeHistoryRepositoryInterface;
use App\Domain\Security\Entity\UserAccount;
use App\Domain\Security\Exception\ForbiddenException;
use App\Presentation\View\ViewRenderer;

final readonly class DokterRalanController
{
    public function __construct(
        private \PDO $pdo,
        private RmeHistoryRepositoryInterface $historyRepo,
        private IcdGroundingRepositoryInterface $icdRepo,
        private OdontogramRepositoryInterface $odoRepo,
        private SaveSoapUseCase $saveSoapUseCase,
        private SavePrbPrescriptionUseCase $savePrbUseCase,
        private SaveOdontogramUseCase $saveOdoUseCase,
        private DeleteOdontogramUseCase $deleteOdoUseCase,
        private CreateSuratMedisUseCase $suratUseCase,
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

        $body = $this->renderer->render('dokter_ralan/index', [
            'judul' => 'Rekam Medis Dokter Rawat Jalan',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'dokter_ralan',
            'module_icon' => 'fa-stethoscope',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Dokter'),
            'user_role' => $currentUser?->getRole() ?? 'dokter',
            'kd_dokter' => $currentUser?->getUsername() ?? '',
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
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiQueueList(?UserAccount $currentUser, array $queryParams = []): array
    {
        $date = trim((string) ($queryParams['date'] ?? date('Y-m-d')));
        $sql = "
            SELECT r.no_reg, r.no_rawat, r.no_rkm_medis, p.nm_pasien, p.jk, p.tgl_lahir,
                   r.kd_poli, pol.nm_poli, r.kd_dokter, d.nm_dokter, pj.png_jawab, r.stts
            FROM reg_periksa r
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN poliklinik pol ON r.kd_poli = pol.kd_poli
            LEFT JOIN dokter d ON r.kd_dokter = d.kd_dokter
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE r.tgl_registrasi = :tgl AND r.stts != 'Batal'
            ORDER BY r.no_reg ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tgl' => $date]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($currentUser !== null && !$currentUser->isAdmin()) {
            $rows = array_values(array_filter($rows, static function (array $row) use ($currentUser): bool {
                $poli = (string) ($row['kd_poli'] ?? '');
                $dokter = (string) ($row['kd_dokter'] ?? '');
                return $currentUser->canAccessPoli($poli) || $currentUser->getUsername() === $dokter;
            }));
        }

        return $this->jsonResponse($rows);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiHistory(array $queryParams = []): array
    {
        $rm = trim((string) ($queryParams['no_rkm_medis'] ?? ''));
        if ($rm === '') {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Nomor rekam medis wajib dipilih.'], 422);
        }

        try {
            $history = $this->historyRepo->getFullMedicalHistory(new NoRkmMedis($rm));
            return $this->jsonResponse($history);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveSoap(?UserAccount $currentUser, array $postData = []): array
    {
        if ($currentUser === null) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Autentikasi diperlukan.'], 401);
        }

        try {
            $cmd = SaveSoapCommand::fromWhitelistedPayload($postData);
            $saved = $this->saveSoapUseCase->execute($cmd, $currentUser);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Catatan SOAP berhasil disimpan.',
                'data' => [
                    'no_rawat' => $saved->noRawat->value(),
                    'tgl_perawatan' => $saved->tglPerawatan,
                    'jam_rawat' => $saved->jamRawat,
                    'nip' => $saved->nip,
                ],
            ]);
        } catch (ForbiddenException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSearchIcd10(array $queryParams = []): array
    {
        $q = trim((string) ($queryParams['q'] ?? ''));
        if ($q === '') {
            return $this->jsonResponse([]);
        }

        return $this->jsonResponse($this->icdRepo->searchSatuSehatIcd10($q, 20));
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveDiagnosa(array $postData = []): array
    {
        try {
            $dx = new DiagnosaPasien(
                noRawat: new NoRawat((string) ($postData['no_rawat'] ?? '')),
                kdPenyakit: (string) ($postData['kd_penyakit'] ?? ''),
                status: (string) ($postData['status'] ?? 'Ralan'),
                prioritas: (int) ($postData['prioritas'] ?? 1),
                statusPenyakit: (string) ($postData['status_penyakit'] ?? 'Baru')
            );
            $this->icdRepo->saveDiagnosa($dx);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Diagnosa {$dx->kdPenyakit} berhasil disimpan.",
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSavePrb(?UserAccount $currentUser, array $postData = []): array
    {
        if ($currentUser === null) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Autentikasi diperlukan.'], 401);
        }

        try {
            $cmd = SavePrbCommand::fromWhitelistedPayload($postData);
            $triplet = $this->savePrbUseCase->execute($cmd, $currentUser);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Resep PRB 30 Hari berhasil disimpan dan disinkronkan ke Planning SOAP.',
                'data' => $triplet,
            ]);
        } catch (ForbiddenException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveOdontogram(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $odo = new Odontogram(
                id: null,
                noRkmMedis: new NoRkmMedis((string) ($postData['no_rkm_medis'] ?? '')),
                noRawat: new NoRawat((string) ($postData['no_rawat'] ?? '')),
                tglPemeriksaan: (string) ($postData['tgl_pemeriksaan'] ?? date('Y-m-d')),
                nomorGigi: (string) ($postData['nomor_gigi'] ?? ''),
                kondisi: (string) ($postData['kondisi'] ?? ''),
                catatan: (string) ($postData['catatan'] ?? ''),
                idUser: (int) ($currentUser?->getId() ?? 0)
            );
            $this->saveOdoUseCase->execute($odo);

            return $this->jsonResponse(['status' => 'success', 'message' => 'Odontogram berhasil dicatat.']);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDeleteOdontogram(array $postData = []): array
    {
        try {
            $this->deleteOdoUseCase->execute((int) ($postData['id'] ?? 0));
            return $this->jsonResponse(['status' => 'success', 'message' => 'Riwayat odontogram berhasil dihapus.']);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCreateSuratSakit(array $postData = []): array
    {
        try {
            $t1Str = (string) ($postData['tanggal_awal'] ?? '');
            $t2Str = (string) ($postData['tanggal_akhir'] ?? '');

            $t1 = \DateTimeImmutable::createFromFormat('!Y-m-d', $t1Str);
            $t2 = \DateTimeImmutable::createFromFormat('!Y-m-d', $t2Str);

            if ($t1 === false || $t1->format('Y-m-d') !== $t1Str || $t2 === false || $t2->format('Y-m-d') !== $t2Str) {
                throw new \InvalidArgumentException('Format tanggal surat sakit tidak valid (wajib YYYY-MM-DD).');
            }

            $surat = $this->suratUseCase->createSuratSakit(
                new NoRawat((string) ($postData['no_rawat'] ?? '')),
                $t1,
                $t2
            );

            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Surat Keterangan Sakit berhasil diterbitkan.',
                'data' => [
                    'no_surat' => $surat->noSurat,
                    'lama_sakit' => $surat->lamaSakit,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
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
