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
    ) {}

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
        $from = trim((string) ($queryParams['from'] ?? $date));
        $to = trim((string) ($queryParams['to'] ?? $from));
        $statusFilter = trim((string) ($queryParams['status'] ?? ''));
        $statusLayanan = strtolower(trim((string) ($queryParams['status_layanan'] ?? '')));

        $sql = "
            SELECT ro.no_resep, ro.tgl_peresepan, ro.jam_peresepan, ro.no_rawat, ro.kd_dokter,
                   ro.tgl_perawatan, ro.jam, ro.status,
                   p.nm_pasien, p.no_rkm_medis, d.nm_dokter, r.kd_pj, pj.png_jawab
            FROM resep_obat ro
            LEFT JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            LEFT JOIN penjab pj ON r.kd_pj = pj.kd_pj
            WHERE ro.tgl_peresepan BETWEEN :from AND :to
        ";
        $params = [':from' => $from, ':to' => $to];
        if (in_array($statusLayanan, ['ralan', 'ranap'], true)) {
            $sql .= ' AND LOWER(ro.status) = :sl';
            $params[':sl'] = $statusLayanan;
        }
        $sql .= ' ORDER BY ro.tgl_peresepan DESC, ro.no_resep DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $tglR = (string) ($row['tgl_perawatan'] ?? '0000-00-00');
            $isDispensed = $tglR !== '' && $tglR !== '0000-00-00';
            $row['is_dispensed'] = $isDispensed;
            $row['is_bpjs'] = strtoupper((string) ($row['kd_pj'] ?? '')) === 'BPJ';

            if ($statusFilter === 'menunggu' && $isDispensed) {
                continue;
            }
            if ($statusFilter === 'selesai' && !$isDispensed) {
                continue;
            }
            $result[] = $row;
        }

        return $this->jsonResponse($result);
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
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiResepDetail(array $queryParams = []): array
    {
        $nr = trim((string) ($queryParams['no_resep'] ?? ''));
        if ($nr === '') {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Nomor resep wajib dipilih.'], 422);
        }

        $stmtNon = $this->pdo->prepare("
            SELECT rdn.kode_brng, db.nama_brng, rdn.jml, rdn.aturan_pakai
            FROM resep_dokter_nonracikan rdn
            LEFT JOIN databarang db ON rdn.kode_brng = db.kode_brng
            WHERE rdn.no_resep = :nr
        ");
        $stmtNon->execute([':nr' => $nr]);
        $nonRacikan = $stmtNon->fetchAll(\PDO::FETCH_ASSOC);

        $stmtRacik = $this->pdo->prepare("
            SELECT rdr.no_racik, rdr.nama_racik, rdr.jml_dr, rdr.aturan_pakai
            FROM resep_dokter_racikan rdr
            WHERE rdr.no_resep = :nr
            ORDER BY rdr.no_racik ASC
        ");
        $stmtRacik->execute([':nr' => $nr]);
        $racikan = $stmtRacik->fetchAll(\PDO::FETCH_ASSOC);

        if ($racikan !== []) {
            $stmtDet = $this->pdo->prepare("
                SELECT d.no_racik, d.kode_brng, db.nama_brng, d.jml, d.kandungan
                FROM resep_dokter_racikan_detail d
                LEFT JOIN databarang db ON d.kode_brng = db.kode_brng
                WHERE d.no_resep = :nr
            ");
            $stmtDet->execute([':nr' => $nr]);
            $allDet = $stmtDet->fetchAll(\PDO::FETCH_ASSOC);
            $byRacik = [];
            foreach ($allDet as $d) {
                $byRacik[(string) $d['no_racik']][] = $d;
            }
            foreach ($racikan as &$r) {
                $r['details'] = $byRacik[(string) $r['no_racik']] ?? [];
            }
        }

        return $this->jsonResponse([
            'no_resep' => $nr,
            'non_racikan' => $nonRacikan,
            'racikan' => $racikan,
        ]);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiCreateResep(?UserAccount $currentUser, array $postData = []): array
    {
        try {
            $kdDokter = ($currentUser !== null && strtolower($currentUser->getRole()) === 'dokter')
                ? $currentUser->getUsername()
                : trim((string) ($postData['kd_dokter'] ?? ($currentUser?->getUsername() ?? '')));
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
        } catch (\DomainException | \InvalidArgumentException $e) {
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
        } catch (InsufficientStockException | \DomainException | \InvalidArgumentException $e) {
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
        } catch (\DomainException | \InvalidArgumentException $e) {
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
        } catch (\DomainException | \InvalidArgumentException $e) {
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cetak Etiket Obat (paritas ksmlite getCetakLabelAll).
     *
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printEtiket(array $queryParams = []): array
    {
        $noResep = trim((string) ($queryParams['no_resep'] ?? ''));
        $header = $this->findResepHeader($noResep);
        if ($header === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Resep tidak ditemukan.</h3>',
            ];
        }

        $items = $this->findResepItems($noResep);
        $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $cards = '';
        foreach ($items as $it) {
            $cards .= '<div class="etiket-card">'
                . '<div class="etiket-head"><strong>INSTALASI FARMASI</strong><span>' . $e($header['tgl_peresepan'] ?? '') . '</span></div>'
                . '<div class="etiket-meta">No. Resep: <strong>' . $e($noResep) . '</strong> | RM: <strong>' . $e($header['no_rkm_medis'] ?? '-') . '</strong></div>'
                . '<div class="etiket-patient">Pasien: <strong>' . $e($header['nm_pasien'] ?? '-') . '</strong></div>'
                . '<hr>'
                . '<div class="etiket-drug">' . $e($it['nama']) . ' (Qty: ' . $e($it['qty']) . ')</div>'
                . '<div class="etiket-signa">Aturan Pakai: <strong>' . $e($it['aturan']) . '</strong></div>'
                . '</div>';
        }
        if ($cards === '') {
            $cards = '<p>Belum ada item obat pada resep ini.</p>';
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Etiket Obat ' . $e($noResep) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:16px;background:#f8f9fa}.etiket-card{width:340px;border:1px solid #222;background:#fff;padding:10px 12px;margin-bottom:12px;page-break-inside:avoid}.etiket-head{display:flex;justify-content:space-between;font-size:12px;border-bottom:1px solid #ccc;padding-bottom:4px;margin-bottom:6px}.etiket-meta,.etiket-patient{font-size:12px;margin-bottom:3px}.etiket-drug{font-size:13px;font-weight:700;margin:6px 0 4px}.etiket-signa{font-size:13px}@media print{.no-print{display:none}body{background:#fff;margin:0}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak Etiket</button></div>'
            . $cards
            . '</body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    /**
     * Cetak Salinan / Pratinjau E-Resep (paritas ksmlite anyCetakEresep).
     *
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function printResep(array $queryParams = []): array
    {
        $noResep = trim((string) ($queryParams['no_resep'] ?? ''));
        $header = $this->findResepHeader($noResep);
        if ($header === null) {
            return [
                'status' => 404,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h3>Resep tidak ditemukan.</h3>',
            ];
        }

        $items = $this->findResepItems($noResep);
        $e = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rowsHtml = '';
        foreach ($items as $idx => $it) {
            $rowsHtml .= '<tr>'
                . '<td>' . ($idx + 1) . '</td>'
                . '<td>R/ ' . $e($it['nama']) . '</td>'
                . '<td style="text-align:center">' . $e($it['qty']) . '</td>'
                . '<td>' . $e($it['aturan']) . '</td>'
                . '</tr>';
        }

        $html = '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>E-Resep ' . $e($noResep) . '</title>'
            . '<style>body{font-family:Arial,sans-serif;margin:20px;max-width:720px}h2{margin:0 0 4px;color:#00897b}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #ccc;padding:6px 8px;font-size:13px;text-align:left}th{background:#f1f5f9}@media print{.no-print{display:none}}</style>'
            . '</head><body>'
            . '<div class="no-print" style="margin-bottom:12px;"><button onclick="window.print()">Cetak E-Resep</button></div>'
            . '<h2>SALINAN E-RESEP FARMASI</h2>'
            . '<div style="font-size:13px;margin-bottom:10px;">'
            . 'No. Resep: <strong>' . $e($noResep) . '</strong> | Tanggal: ' . $e($header['tgl_peresepan'] ?? '') . '<br>'
            . 'No. Rawat: ' . $e($header['no_rawat'] ?? '-') . ' | Pasien: <strong>' . $e($header['nm_pasien'] ?? '-') . '</strong> (' . $e($header['no_rkm_medis'] ?? '-') . ')<br>'
            . 'Dokter Peresep: ' . $e($header['nm_dokter'] ?? ($header['kd_dokter'] ?? '-'))
            . '</div>'
            . '<table><thead><tr><th>No</th><th>Nama Obat / Racikan</th><th>Qty</th><th>Aturan Pakai</th></tr></thead><tbody>'
            . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="4">Tidak ada item.</td></tr>')
            . '</tbody></table>'
            . '<div style="margin-top:28px;text-align:right;font-size:13px;">Petugas Farmasi / Apoteker<br><br><br>(__________________________)</div>'
            . '</body></html>';

        return [
            'status' => 200,
            'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
            'body' => $html,
        ];
    }

    /**
     * @param array<string, mixed> $queryParams
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiListStokBatch(array $queryParams = []): array
    {
        $q = trim((string) ($queryParams['q'] ?? ''));
        $depo = trim((string) ($queryParams['kd_bangsal'] ?? ''));
        $sql = 'SELECT g.kode_brng, COALESCE(d.nama_brng, g.kode_brng) AS nama_brng, g.kd_bangsal, g.no_batch, g.no_faktur, g.stok
                FROM gudangbarang g
                LEFT JOIN databarang d ON d.kode_brng = g.kode_brng
                WHERE 1=1';
        $params = [];
        if ($depo !== '') {
            $sql .= ' AND g.kd_bangsal = :depo';
            $params[':depo'] = $depo;
        }
        if ($q !== '') {
            $sql .= ' AND (g.kode_brng LIKE :q OR COALESCE(d.nama_brng, \'\') LIKE :q OR g.no_batch LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY d.nama_brng ASC, g.kd_bangsal ASC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []]);
    }

    /**
     * @param array<string, mixed> $postData
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function apiAdjustStokBatch(?UserAccount $currentUser, array $postData = []): array
    {
        $kodeBrng = trim((string) ($postData['kode_brng'] ?? ''));
        $kdBangsal = trim((string) ($postData['kd_bangsal'] ?? ''));
        $noBatch = trim((string) ($postData['no_batch'] ?? ''));
        $noFaktur = trim((string) ($postData['no_faktur'] ?? ''));
        $stokBaru = (float) ($postData['stok_baru'] ?? -1);
        $keterangan = trim((string) ($postData['keterangan'] ?? 'Penyesuaian stok opname apotek'));

        if ($kodeBrng === '' || $kdBangsal === '' || $stokBaru < 0) {
            return $this->jsonResponse(['status' => 'error', 'message' => 'Kode barang, depo/bangsal, dan stok baru (>= 0) wajib diisi.'], 422);
        }

        $this->pdo->beginTransaction();
        try {
            $sel = $this->pdo->prepare(
                'SELECT stok FROM gudangbarang WHERE kode_brng = :kb AND kd_bangsal = :bg AND no_batch = :nb AND no_faktur = :nf LIMIT 1'
            );
            $sel->execute([':kb' => $kodeBrng, ':bg' => $kdBangsal, ':nb' => $noBatch, ':nf' => $noFaktur]);
            $stokAwal = $sel->fetchColumn();

            if ($stokAwal === false) {
                $stokAwalNum = 0.0;
                $ins = $this->pdo->prepare(
                    'INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok) VALUES (:kb, :bg, :nb, :nf, :st)'
                );
                $ins->execute([':kb' => $kodeBrng, ':bg' => $kdBangsal, ':nb' => $noBatch, ':nf' => $noFaktur, ':st' => $stokBaru]);
            } else {
                $stokAwalNum = (float) $stokAwal;
                $upd = $this->pdo->prepare(
                    'UPDATE gudangbarang SET stok = :st WHERE kode_brng = :kb AND kd_bangsal = :bg AND no_batch = :nb AND no_faktur = :nf'
                );
                $upd->execute([':st' => $stokBaru, ':kb' => $kodeBrng, ':bg' => $kdBangsal, ':nb' => $noBatch, ':nf' => $noFaktur]);
            }

            $diff = $stokBaru - $stokAwalNum;
            $masuk = $diff > 0 ? $diff : 0.0;
            $keluar = $diff < 0 ? abs($diff) : 0.0;

            $log = $this->pdo->prepare(
                "INSERT INTO riwayat_barang_medis (kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan)
                 VALUES (:kb, :sa, :m, :k, :sk, 'Penyesuaian Apotek', :tgl, :jam, :pt, :bg, 'Simpan', :nb, :nf, :ket)"
            );
            $log->execute([
                ':kb' => $kodeBrng,
                ':sa' => $stokAwalNum,
                ':m' => $masuk,
                ':k' => $keluar,
                ':sk' => $stokBaru,
                ':tgl' => date('Y-m-d'),
                ':jam' => date('H:i:s'),
                ':pt' => $currentUser?->getFullname() ?: ($currentUser?->getUsername() ?? 'Apoteker'),
                ':bg' => $kdBangsal,
                ':nb' => $noBatch,
                ':nf' => $noFaktur,
                ':ket' => $keterangan,
            ]);

            $this->pdo->commit();
            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Stok batch/depo berhasil disesuaikan.',
                'data' => ['kode_brng' => $kodeBrng, 'kd_bangsal' => $kdBangsal, 'stok_awal' => $stokAwalNum, 'stok_akhir' => $stokBaru],
            ]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->jsonResponse(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findResepHeader(string $noResep): ?array
    {
        if ($noResep === '') {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT ro.*, p.nm_pasien, p.no_rkm_medis, d.nm_dokter
            FROM resep_obat ro
            LEFT JOIN reg_periksa r ON ro.no_rawat = r.no_rawat
            LEFT JOIN pasien p ON r.no_rkm_medis = p.no_rkm_medis
            LEFT JOIN dokter d ON ro.kd_dokter = d.kd_dokter
            WHERE ro.no_resep = :nr
            LIMIT 1
        ");
        $stmt->execute([':nr' => $noResep]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * @return list<array{nama: string, qty: string, aturan: string}>
     */
    private function findResepItems(string $noResep): array
    {
        $items = [];
        $stmtNon = $this->pdo->prepare("
            SELECT rd.jml, rd.aturan_pakai, COALESCE(db.nama_brng, rd.kode_brng) AS nama_brng
            FROM resep_dokter_nonracikan rd
            LEFT JOIN databarang db ON rd.kode_brng = db.kode_brng
            WHERE rd.no_resep = :nr
        ");
        $stmtNon->execute([':nr' => $noResep]);
        foreach ($stmtNon->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'nama' => (string) $r['nama_brng'],
                'qty' => (string) $r['jml'],
                'aturan' => (string) ($r['aturan_pakai'] ?: '-'),
            ];
        }

        $stmtRacik = $this->pdo->prepare("
            SELECT nama_racik, jml_dr, aturan_pakai
            FROM resep_dokter_racikan
            WHERE no_resep = :nr
        ");
        $stmtRacik->execute([':nr' => $noResep]);
        foreach ($stmtRacik->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'nama' => '[Racikan] ' . (string) $r['nama_racik'],
                'qty' => (string) $r['jml_dr'] . ' bks',
                'aturan' => (string) ($r['aturan_pakai'] ?: '-'),
            ];
        }

        return $items;
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
