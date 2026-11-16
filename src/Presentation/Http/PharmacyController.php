<?php

declare(strict_types=1);

namespace App\Presentation\Http;

use App\Application\Pharmacy\UseCase\CreateResepUseCase;
use App\Application\Pharmacy\UseCase\DeleteResepUseCase;
use App\Application\Pharmacy\UseCase\DispenseResepUseCase;
use App\Application\Pharmacy\UseCase\SaveCopyResepUseCase;
use App\Domain\Pharmacy\Exception\InsufficientStockException;
use App\Domain\Pharmacy\Repository\DataBarangRepositoryInterface;
use App\Domain\Pharmacy\Repository\GudangBarangRepositoryInterface;
use App\Domain\Pharmacy\ValueObject\DepoBangsal;
use App\Domain\Pharmacy\ValueObject\JenisKlaim;
use App\Domain\Pharmacy\ValueObject\NoResep;
use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Security\Entity\UserAccount;
use App\Presentation\View\ViewRenderer;

final readonly class PharmacyController
{
    public function __construct(
        private \PDO $pdo,
        private DataBarangRepositoryInterface $barangRepo,
        private GudangBarangRepositoryInterface $gudangRepo,
        private CreateResepUseCase $createResepUseCase,
        private DispenseResepUseCase $dispenseResepUseCase,
        private DeleteResepUseCase $deleteResepUseCase,
        private SaveCopyResepUseCase $copyResepUseCase,
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

        $body = $this->renderer->render('pharmacy/index', [
            'judul' => 'Farmasi & Validasi E-Resep',
            'klinik_nama' => 'SIMRS Medars',
            'current_module' => 'farmasi',
            'module_icon' => 'fa-pills',
            'user_name' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Apoteker'),
            'user_role' => $currentUser?->getRole() ?? 'farmasi',
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
    public function apiQueueResep(array $queryParams = []): array
    {
        $date = trim((string) ($queryParams['date'] ?? date('Y-m-d')));

        $sql = "
            SELECT ro.no_resep, ro.tgl_peresepan, ro.jam_peresepan, ro.no_rawat, ro.kd_dokter,
                   ro.tgl_perawatan, ro.jam, ro.status,
                   p.nm_pasien, p.no_rkm_medis, d.nm_dokter, r.kd_pj, pj.png_jawab
            FROM resep_obat ro
            LEFT JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE ro.tgl_peresepan = :tgl
            ORDER BY ro.no_resep DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':tgl' => $date]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $tglR = (string) ($row['tgl_perawatan'] ?? '0000-00-00');
            $row['is_dispensed'] = $tglR !== '' && $tglR !== '0000-00-00';
            $row['is_bpjs'] = strtoupper((string) ($row['kd_pj'] ?? '')) === 'BPJ';
        }

        return $this->jsonResponse($rows);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiSearchObat(array $queryParams = []): array
    {
        $q = trim((string) ($queryParams['q'] ?? ''));
        if ($q === '') {
            return $this->jsonResponse([]);
        }

        $isBpjs = filter_var($queryParams['is_bpjs'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $depoCode = trim((string) ($queryParams['depo'] ?? 'FAR01'));
        $depo = new DepoBangsal($depoCode !== '' ? $depoCode : 'FAR01');

        $items = $this->barangRepo->searchWithUniformPricing($q, $isBpjs, 20);
        foreach ($items as &$item) {
            $item['stok_depo'] = $this->gudangRepo->getStock((string) $item['kode_brng'], $depo);
        }

        return $this->jsonResponse($items);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCreateResep(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $kdDokter = trim((string) ($postData['kd_dokter'] ?? ($currentUser?->getUsername() ?? '')));
            $resep = $this->createResepUseCase->execute(
                noRawat: new NoRawat((string) ($postData['no_rawat'] ?? '')),
                kdDokter: $kdDokter,
                nonRacikanItems: (array) ($postData['non_racikan'] ?? []),
                racikanItems: (array) ($postData['racikan'] ?? []),
                status: (string) ($postData['status'] ?? 'ralan')
            );

            return $this->jsonResponse([
                'status' => 'success',
                'message' => "E-Resep {$resep->noResep->value()} berhasil dikirim ke Farmasi.",
                'data' => [
                    'no_resep' => $resep->noResep->value(),
                ],
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDispenseResep(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $noResep = new NoResep((string) ($postData['no_resep'] ?? ''));
            $depo = new DepoBangsal((string) ($postData['kd_bangsal'] ?? 'FAR01'));
            $klaim = JenisKlaim::fromString((string) ($postData['jenis_klaim'] ?? 'Tunai'));
            $petugas = $currentUser?->getUsername() ?? 'APOTEK';

            $this->dispenseResepUseCase->execute($noResep, $depo, $klaim, $petugas);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Resep {$noResep->value()} berhasil divalidasi & diserahkan dari depo {$depo->value()}.",
            ]);
        } catch (InsufficientStockException|\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiDeleteResep(array $postData = []): array
    {
        try {
            $noResep = new NoResep((string) ($postData['no_resep'] ?? ''));
            $this->deleteResepUseCase->execute($noResep);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Resep {$noResep->value()} berhasil dihapus.",
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCopyResep(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $source = new NoResep((string) ($postData['source_no_resep'] ?? ''));
            $target = new NoRawat((string) ($postData['target_no_rawat'] ?? ''));
            $kdDokter = (string) ($postData['kd_dokter'] ?? ($currentUser?->getUsername() ?? ''));

            $copied = $this->copyResepUseCase->execute($source, $target, $kdDokter);

            return $this->jsonResponse([
                'status' => 'success',
                'message' => "Copy resep berhasil dibuat: {$copied->noResep->value()}.",
                'data' => ['no_resep' => $copied->noResep->value()],
            ]);
        } catch (\DomainException|\InvalidArgumentException $e) {
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
