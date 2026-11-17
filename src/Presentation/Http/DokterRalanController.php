<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Rme\DTO\SavePrbCommand;
use App\Application\Rme\DTO\SaveSoapCommand;
use App\Application\Rme\UseCase\CreateSuratMedisUseCase;
use App\Application\Rme\UseCase\DeleteOdontogramUseCase;
use App\Application\Rme\UseCase\DeleteTindakanDokterUseCase;
use App\Application\Rme\UseCase\SaveOdontogramUseCase;
use App\Application\Rme\UseCase\SavePrbPrescriptionUseCase;
use App\Application\Rme\UseCase\SaveResumeMedisUseCase;
use App\Application\Rme\UseCase\SaveSoapUseCase;
use App\Application\Rme\UseCase\SaveTindakanDokterUseCase;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Entity\DiagnosaPasien;
use App\Domain\Rme\Entity\Odontogram;
use App\Domain\Rme\Repository\DokterTindakanRepositoryInterface;
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
        private ?DokterTindakanRepositoryInterface $tindakanRepo = null,
        private ?SaveTindakanDokterUseCase $saveTindakanUseCase = null,
        private ?DeleteTindakanDokterUseCase $deleteTindakanUseCase = null,
        private ?SaveResumeMedisUseCase $saveResumeUseCase = null,
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
    public function apiSaveDiagnosa(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $noRawatStr = (string) ($postData['no_rawat'] ?? '');
            $this->assertCanAccessNoRawat($currentUser, $noRawatStr);

            $dx = new DiagnosaPasien(
                noRawat: new NoRawat($noRawatStr),
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
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListOdontogram(array $queryParams = []): array
    {
        $rm = trim((string) ($queryParams['no_rkm_medis'] ?? ''));
        if ($rm === '') {
            return $this->jsonResponse([]);
        }

        try {
            $items = $this->odoRepo->findByNoRkmMedis(new NoRkmMedis($rm));
            $data = array_map(static fn(Odontogram $o): array => [
                'id' => $o->id,
                'no_rkm_medis' => $o->noRkmMedis->value(),
                'no_rawat' => $o->noRawat->value(),
                'tgl_pemeriksaan' => $o->tglPemeriksaan,
                'nomor_gigi' => $o->nomorGigi,
                'kondisi' => $o->kondisi,
                'catatan' => $o->catatan,
            ], $items);

            return $this->jsonResponse($data);
        } catch (\InvalidArgumentException $e) {
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
            $noRawatStr = (string) ($postData['no_rawat'] ?? '');
            $this->assertCanAccessNoRawat($currentUser, $noRawatStr);

            $odo = new Odontogram(
                id: null,
                noRkmMedis: new NoRkmMedis((string) ($postData['no_rkm_medis'] ?? '')),
                noRawat: new NoRawat($noRawatStr),
                tglPemeriksaan: (string) ($postData['tgl_pemeriksaan'] ?? date('Y-m-d')),
                nomorGigi: (string) ($postData['nomor_gigi'] ?? ''),
                kondisi: (string) ($postData['kondisi'] ?? ''),
                catatan: (string) ($postData['catatan'] ?? ''),
                idUser: (int) ($currentUser?->getId() ?? 0)
            );
            $this->saveOdoUseCase->execute($odo);

            return $this->jsonResponse(['status' => 'success', 'message' => 'Odontogram berhasil dicatat.']);
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
    public function apiDeleteOdontogram(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $id = (int) ($postData['id'] ?? 0);
            if ($id > 0 && $currentUser !== null) {
                $stmt = $this->pdo->prepare('SELECT no_rawat FROM mlite_odontogram WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $id]);
                $nr = $stmt->fetchColumn();
                if (is_string($nr) && $nr !== '') {
                    $this->assertCanAccessNoRawat($currentUser, $nr);
                }
            }

            $this->deleteOdoUseCase->execute($id);
            return $this->jsonResponse(['status' => 'success', 'message' => 'Riwayat odontogram berhasil dihapus.']);
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
    public function apiCreateSuratSakit(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $noRawatStr = (string) ($postData['no_rawat'] ?? '');
            $this->assertCanAccessNoRawat($currentUser, $noRawatStr);

            $t1Str = (string) ($postData['tanggal_awal'] ?? '');
            $t2Str = (string) ($postData['tanggal_akhir'] ?? '');

            $t1 = \DateTimeImmutable::createFromFormat('!Y-m-d', $t1Str);
            $t2 = \DateTimeImmutable::createFromFormat('!Y-m-d', $t2Str);

            if ($t1 === false || $t1->format('Y-m-d') !== $t1Str || $t2 === false || $t2->format('Y-m-d') !== $t2Str) {
                throw new \InvalidArgumentException('Format tanggal surat sakit tidak valid (wajib YYYY-MM-DD).');
            }

            $surat = $this->suratUseCase->createSuratSakit(
                new NoRawat($noRawatStr),
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
        } catch (ForbiddenException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 403);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    private function assertCanAccessNoRawat(?UserAccount $actor, string $noRawat): void
    {
        if ($actor === null) {
            throw new ForbiddenException('Autentikasi diperlukan.');
        }

        $nr = (new NoRawat($noRawat))->value();
        $stmt = $this->pdo->prepare('SELECT kd_poli, kd_dokter FROM reg_periksa WHERE no_rawat = :nr LIMIT 1');
        $stmt->execute([':nr' => $nr]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            throw new \DomainException("Data registrasi kunjungan tidak ditemukan: {$nr}.");
        }

        $kdPoli = (string) ($row['kd_poli'] ?? '');
        $kdDokter = (string) ($row['kd_dokter'] ?? '');

        if (!$actor->canAccessPoli($kdPoli) && $actor->getUsername() !== $kdDokter) {
            throw new ForbiddenException("Anda tidak memiliki izin melayani poliklinik {$kdPoli}.");
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSearchTindakan(array $query): array
    {
        if ($this->tindakanRepo === null) {
            return $this->jsonResponse(['status' => 'success', 'data' => []]);
        }
        $q = trim((string) ($query['q'] ?? ''));
        return $this->jsonResponse(['status' => 'success', 'data' => $this->tindakanRepo->searchTarifTindakan($q, 20)]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListTindakan(UserAccount $actor, array $query): array
    {
        $nr = trim((string) ($query['no_rawat'] ?? ''));
        if ($nr === '' || $this->tindakanRepo === null) {
            return $this->jsonResponse(['status' => 'success', 'data' => []]);
        }
        $this->assertCanAccessNoRawat($actor, $nr);
        return $this->jsonResponse(['status' => 'success', 'data' => $this->tindakanRepo->listByNoRawat(new NoRawat($nr))]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveTindakan(UserAccount $actor, array $payload): array
    {
        try {
            if ($this->saveTindakanUseCase === null) {
                throw new \DomainException('Layanan tindakan dokter belum dikonfigurasi.');
            }
            $nr = trim((string) ($payload['no_rawat'] ?? ''));
            $this->assertCanAccessNoRawat($actor, $nr);
            $kdJenisPrw = trim((string) ($payload['kd_jenis_prw'] ?? ''));
            $claimType = trim((string) ($payload['claim_type'] ?? 'Tunai')) ?: 'Tunai';

            $saved = $this->saveTindakanUseCase->execute(
                new NoRawat($nr),
                $kdJenisPrw,
                $actor->getUsername(),
                $claimType
            );
            return $this->jsonResponse(['status' => 'success', 'data' => $saved]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDeleteTindakan(UserAccount $actor, array $payload): array
    {
        try {
            if ($this->deleteTindakanUseCase === null) {
                throw new \DomainException('Layanan hapus tindakan belum dikonfigurasi.');
            }
            $nr = trim((string) ($payload['no_rawat'] ?? ''));
            $this->assertCanAccessNoRawat($actor, $nr);
            $this->deleteTindakanUseCase->execute(
                new NoRawat($nr),
                trim((string) ($payload['kd_jenis_prw'] ?? '')),
                trim((string) ($payload['kd_dokter'] ?? $actor->getUsername())),
                trim((string) ($payload['tgl_perawatan'] ?? '')),
                trim((string) ($payload['jam_rawat'] ?? '')),
            );
            return $this->jsonResponse(['status' => 'success']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiGetResume(UserAccount $actor, array $query): array
    {
        $nr = trim((string) ($query['no_rawat'] ?? ''));
        if ($nr === '' || $this->tindakanRepo === null) {
            return $this->jsonResponse(['status' => 'success', 'data' => null]);
        }
        $this->assertCanAccessNoRawat($actor, $nr);
        return $this->jsonResponse(['status' => 'success', 'data' => $this->tindakanRepo->findResume(new NoRawat($nr))]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSaveResume(UserAccount $actor, array $payload): array
    {
        try {
            if ($this->saveResumeUseCase === null) {
                throw new \DomainException('Layanan resume medis belum dikonfigurasi.');
            }
            $nr = trim((string) ($payload['no_rawat'] ?? ''));
            $this->assertCanAccessNoRawat($actor, $nr);
            $payload['kd_dokter'] = $actor->getUsername();
            $this->saveResumeUseCase->execute($payload);
            return $this->jsonResponse(['status' => 'success', 'message' => 'Resume medis tersimpan.']);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printResumeMedis(UserAccount $actor, array $query): array
    {
        $nr = trim((string) ($query['no_rawat'] ?? ''));
        if ($nr === '') {
            return ['status' => 422, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>No. Rawat wajib diisi.</h3>'];
        }
        $this->assertCanAccessNoRawat($actor, $nr);
        $stmt = $this->pdo->prepare(
            'SELECT rp.*, reg.tgl_registrasi, p.no_rkm_medis, p.nm_pasien, p.tgl_lahir, p.jk, d.nm_dokter
             FROM reg_periksa reg
             LEFT JOIN resume_pasien rp ON rp.no_rawat = reg.no_rawat
             LEFT JOIN pasien p ON p.no_rkm_medis = reg.no_rkm_medis
             LEFT JOIN dokter d ON d.kd_dokter = reg.kd_dokter
             WHERE reg.no_rawat = :nr LIMIT 1'
        );
        $stmt->execute([':nr' => $nr]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['status' => 404, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>Resume medis tidak ditemukan.</h3>'];
        }
        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? '-'), ENT_QUOTES, 'UTF-8');
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Resume Medis ' . $e($nr) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;max-width:720px;margin:24px auto;font-size:13px;}table{width:100%;border-collapse:collapse;}th,td{padding:6px;border:1px solid #ddd;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:10px;"><button onclick="window.print()">Cetak Resume Medis</button></div>'
            . '<h3 style="text-align:center;color:#00897b;">RESUME MEDIS PASIEN</h3><hr>'
            . '<table>'
            . '<tr><th align="left" width="30%">No. Rawat / No. RM</th><td>' . $e($nr) . ' / ' . $e($row['no_rkm_medis'] ?? '-') . '</td></tr>'
            . '<tr><th align="left">Nama Pasien</th><td><strong>' . $e($row['nm_pasien'] ?? '-') . '</strong> (' . $e($row['jk'] ?? '-') . ', Tgl Lahir: ' . $e($row['tgl_lahir'] ?? '-') . ')</td></tr>'
            . '<tr><th align="left">Keluhan Utama</th><td>' . nl2br($e($row['keluhan_utama'] ?? '-')) . '</td></tr>'
            . '<tr><th align="left">Jalannya Penyakit</th><td>' . nl2br($e($row['jalannya_penyakit'] ?? '-')) . '</td></tr>'
            . '<tr><th align="left">Pemeriksaan Penunjang</th><td>' . nl2br($e($row['pemeriksaan_penunjang'] ?? '-')) . '</td></tr>'
            . '<tr><th align="left">Hasil Laboratorium</th><td>' . nl2br($e($row['hasil_laborat'] ?? '-')) . '</td></tr>'
            . '<tr><th align="left">Diagnosa Utama (ICD-10)</th><td><strong>' . $e($row['kd_diagnosa_utama'] ?? '-') . '</strong> - ' . $e($row['diagnosa_utama'] ?? '-') . '</td></tr>'
            . '<tr><th align="left">Kondisi Pulang</th><td>' . $e($row['kondisi_pulang'] ?? '-') . '</td></tr>'
            . '<tr><th align="left">Obat Pulang &amp; Edukasi</th><td>' . nl2br($e($row['obat_pulang'] ?? '-')) . '</td></tr>'
            . '</table>'
            . '<div style="margin-top:32px;text-align:right;">Dokter Penanggung Jawab<br><br><br><strong>' . $e($row['nm_dokter'] ?? '-') . '</strong></div>'
            . '</body></html>';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printEresep(UserAccount $actor, array $query): array
    {
        $nr = trim((string) ($query['no_rawat'] ?? ''));
        if ($nr === '') {
            return ['status' => 422, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>No. Rawat wajib diisi.</h3>'];
        }
        $this->assertCanAccessNoRawat($actor, $nr);
        $stmt = $this->pdo->prepare(
            'SELECT ro.no_resep, ro.tgl_peresepan, ro.jam_peresepan, p.no_rkm_medis, p.nm_pasien, d.nm_dokter
             FROM resep_obat ro
             LEFT JOIN reg_periksa reg ON reg.no_rawat = ro.no_rawat
             LEFT JOIN pasien p ON p.no_rkm_medis = reg.no_rkm_medis
             LEFT JOIN dokter d ON d.kd_dokter = ro.kd_dokter
             WHERE ro.no_rawat = :nr ORDER BY ro.tgl_peresepan DESC, ro.jam_peresepan DESC LIMIT 1'
        );
        $stmt->execute([':nr' => $nr]);
        $resep = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($resep === false) {
            return ['status' => 404, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => '<h3>E-Resep untuk kunjungan ini belum tersedia.</h3>'];
        }

        $dStmt = $this->pdo->prepare(
            'SELECT rd.jml, rd.aturan_pakai, COALESCE(db.nama_brng, rd.kode_brng) AS nama_obat
             FROM resep_dokter_nonracikan rd
             LEFT JOIN databarang db ON db.kode_brng = rd.kode_brng
             WHERE rd.no_resep = :no'
        );
        $dStmt->execute([':no' => $resep['no_resep']]);
        $items = $dStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $e = static fn (mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        $rowsHtml = '';
        foreach ($items as $it) {
            $rowsHtml .= '<tr><td>' . $e($it['nama_obat']) . '</td><td align="right">' . $e($it['jml']) . '</td><td>' . $e($it['aturan_pakai']) . '</td></tr>';
        }
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>E-Resep ' . $e($resep['no_resep']) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;max-width:520px;margin:20px auto;font-size:13px;}table{width:100%;border-collapse:collapse;}th,td{padding:5px;border-bottom:1px solid #ddd;}@media print{.no-print{display:none;}}</style></head><body>'
            . '<div class="no-print" style="margin-bottom:10px;"><button onclick="window.print()">Cetak E-Resep</button></div>'
            . '<h3 style="text-align:center;color:#00897b;">RESEP ELEKTRONIK DOKTER</h3><hr>'
            . '<div>No. Resep: <strong>' . $e($resep['no_resep']) . '</strong> | Tgl: ' . $e($resep['tgl_peresepan']) . '</div>'
            . '<div>Pasien: <strong>' . $e($resep['nm_pasien']) . '</strong> (' . $e($resep['no_rkm_medis']) . ')</div><br>'
            . '<table><thead><tr><th align="left">Nama Obat</th><th align="right">Jml</th><th align="left">Aturan Pakai</th></tr></thead><tbody>' . $rowsHtml . '</tbody></table>'
            . '<div style="margin-top:28px;text-align:right;">Dokter Penulis Resep<br><br><br><strong>' . $e($resep['nm_dokter'] ?? '-') . '</strong></div>'
            . '</body></html>';
        return ['status' => 200, 'headers' => ['Content-Type' => 'text/html; charset=utf-8'], 'body' => $html];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiFullHistory(array $query): array
    {
        $noRm = trim((string) ($query['no_rkm_medis'] ?? ''));
        if ($noRm === '') {
            return $this->jsonResponse(['status' => 'success', 'data' => ['soap' => [], 'diagnosa' => [], 'resep' => [], 'tindakan' => []]]);
        }

        $soapStmt = $this->pdo->prepare(
            'SELECT pr.no_rawat, pr.tgl_perawatan, pr.jam_rawat, pr.keluhan, pr.pemeriksaan, pr.penilaian, pr.rtl
             FROM pemeriksaan_ralan pr
             INNER JOIN reg_periksa r ON r.no_rawat = pr.no_rawat
             WHERE r.no_rkm_medis = :rm ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC LIMIT 20'
        );
        $soapStmt->execute([':rm' => $noRm]);

        $diagStmt = $this->pdo->prepare(
            'SELECT dp.no_rawat, dp.kd_penyakit, COALESCE(p.nm_penyakit, dp.kd_penyakit) AS nm_penyakit, dp.prioritas
             FROM diagnosa_pasien dp
             INNER JOIN reg_periksa r ON r.no_rawat = dp.no_rawat
             LEFT JOIN penyakit p ON p.kd_penyakit = dp.kd_penyakit
             WHERE r.no_rkm_medis = :rm ORDER BY dp.no_rawat DESC, dp.prioritas ASC LIMIT 30'
        );
        $diagStmt->execute([':rm' => $noRm]);

        $resepStmt = $this->pdo->prepare(
            'SELECT ro.no_resep, ro.no_rawat, ro.tgl_peresepan, COALESCE(db.nama_brng, rd.kode_brng) AS nama_obat, rd.jml, rd.aturan_pakai
             FROM resep_obat ro
             INNER JOIN reg_periksa r ON r.no_rawat = ro.no_rawat
             INNER JOIN resep_dokter_nonracikan rd ON rd.no_resep = ro.no_resep
             LEFT JOIN databarang db ON db.kode_brng = rd.kode_brng
             WHERE r.no_rkm_medis = :rm ORDER BY ro.tgl_peresepan DESC LIMIT 30'
        );
        $resepStmt->execute([':rm' => $noRm]);

        $tindStmt = $this->pdo->prepare(
            'SELECT t.no_rawat, t.tgl_perawatan, COALESCE(j.nm_perawatan, t.kd_jenis_prw) AS nm_perawatan, t.biaya_rawat
             FROM rawat_jl_dr t
             INNER JOIN reg_periksa r ON r.no_rawat = t.no_rawat
             LEFT JOIN jns_perawatan j ON j.kd_jenis_prw = t.kd_jenis_prw
             WHERE r.no_rkm_medis = :rm ORDER BY t.tgl_perawatan DESC LIMIT 30'
        );
        $tindStmt->execute([':rm' => $noRm]);

        $racikanStmt = $this->pdo->prepare(
            "SELECT ro.no_resep, ro.no_rawat, ro.tgl_peresepan, rr.nama_racik AS nama_obat, rr.jml_dr AS jml, rr.aturan_pakai
             FROM resep_obat ro
             INNER JOIN reg_periksa r ON r.no_rawat = ro.no_rawat
             INNER JOIN resep_dokter_racikan rr ON rr.no_resep = ro.no_resep
             WHERE r.no_rkm_medis = :rm ORDER BY ro.tgl_peresepan DESC LIMIT 20"
        );
        $racikanStmt->execute([':rm' => $noRm]);

        $labStmt = $this->pdo->prepare(
            'SELECT dpl.no_rawat, dpl.tgl_periksa, dpl.jam, dpl.pemeriksaan, dpl.nilai, dpl.nilai_rujukan
             FROM detail_periksa_lab dpl
             INNER JOIN reg_periksa r ON r.no_rawat = dpl.no_rawat
             WHERE r.no_rkm_medis = :rm ORDER BY dpl.tgl_periksa DESC, dpl.jam DESC LIMIT 30'
        );
        $labStmt->execute([':rm' => $noRm]);

        $radStmt = $this->pdo->prepare(
            'SELECT hr.no_rawat, hr.tgl_periksa, hr.jam, hr.hasil
             FROM hasil_radiologi hr
             INNER JOIN reg_periksa r ON r.no_rawat = hr.no_rawat
             WHERE r.no_rkm_medis = :rm ORDER BY hr.tgl_periksa DESC, hr.jam DESC LIMIT 20'
        );
        $radStmt->execute([':rm' => $noRm]);

        $cpptStmt = $this->pdo->prepare(
            'SELECT pr.no_rawat, pr.tgl_perawatan, pr.jam_rawat, pr.keluhan, pr.pemeriksaan, pr.penilaian, pr.rtl, pr.nip
             FROM pemeriksaan_ranap pr
             INNER JOIN reg_periksa r ON r.no_rawat = pr.no_rawat
             WHERE r.no_rkm_medis = :rm ORDER BY pr.tgl_perawatan DESC, pr.jam_rawat DESC LIMIT 20'
        );
        $cpptStmt->execute([':rm' => $noRm]);

        $triaseStmt = $this->pdo->prepare(
            'SELECT t.no_rawat, t.tgl_kunjungan, t.jam_kunjungan, t.zona_triase, t.keluhan_utama, t.tensi, t.nadi, t.suhu, t.spo2, t.gcs, t.disposisi
             FROM data_triase_igd t
             INNER JOIN reg_periksa r ON r.no_rawat = t.no_rawat
             WHERE r.no_rkm_medis = :rm ORDER BY t.tgl_kunjungan DESC, t.jam_kunjungan DESC LIMIT 10'
        );
        $triaseStmt->execute([':rm' => $noRm]);

        $resepList = array_merge(
            $resepStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            $racikanStmt->fetchAll(\PDO::FETCH_ASSOC) ?: []
        );

        return $this->jsonResponse([
            'status' => 'success',
            'data' => [
                'soap' => $soapStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
                'diagnosa' => $diagStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
                'resep' => $resepList,
                'tindakan' => $tindStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
                'lab' => $labStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
                'radiologi' => $radStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
                'cppt_ranap' => $cpptStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
                'triase_igd' => $triaseStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [],
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
