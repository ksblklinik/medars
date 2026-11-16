<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Persistence;

use App\Domain\Registration\ValueObject\NoRawat;
use App\Domain\Registration\ValueObject\NoRkmMedis;
use App\Domain\Rme\Entity\DiagnosaPasien;
use App\Domain\Rme\Entity\Odontogram;
use App\Domain\Rme\Entity\PemeriksaanRalan;
use App\Domain\Rme\Entity\PemeriksaanRanap;
use App\Domain\Rme\Entity\RmeMetadata;
use App\Domain\Rme\ValueObject\Kesadaran;
use App\Domain\Rme\ValueObject\TandaVital;
use App\Infrastructure\Database\ReadOnlyConnectionGuard;
use App\Infrastructure\Database\SchemaMigrator;
use App\Infrastructure\Persistence\PdoIcdGroundingRepository;
use App\Infrastructure\Persistence\PdoOdontogramRepository;
use App\Infrastructure\Persistence\PdoPemeriksaanRalanRepository;
use App\Infrastructure\Persistence\PdoPemeriksaanRanapRepository;
use App\Infrastructure\Persistence\PdoRmeHistoryRepository;
use PDO;
use PHPUnit\Framework\TestCase;

class PdoRmeRepositoriesTest extends TestCase
{
    private PDO $nextPdo;
    private PDO $legacyPdo;
    private ReadOnlyConnectionGuard $legacyGuard;

    protected function setUp(): void
    {
        $this->nextPdo = new PDO('sqlite::memory:');
        $this->nextPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->nextPdo))->migrate();

        $this->legacyPdo = new PDO('sqlite::memory:');
        $this->legacyPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new SchemaMigrator($this->legacyPdo))->migrate();

        $this->legacyGuard = new ReadOnlyConnectionGuard($this->legacyPdo);
    }

    public function testSaveAndUpdatePemeriksaanRalanWithCompositeTriplet(): void
    {
        $repo = new PdoPemeriksaanRalanRepository($this->nextPdo);
        $noRawat = new NoRawat('2026/11/16/000001');

        // Visite 1 (Perawat pukul 08:15:00)
        $soap1 = new PemeriksaanRalan(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '08:15:00',
            tandaVital: new TandaVital(suhuTubuh: '36.8', tensi: '120/80'),
            kesadaran: Kesadaran::fromString('Compos Mentis'),
            keluhan: 'Pusing sejak pagi',
            pemeriksaan: 'Keadaan umum baik',
            nip: 'PET001'
        );
        $repo->save($soap1);

        // Visite 2 (Dokter pukul 09:00:00 pada kunjungan yang sama -> TIDAK boleh menimpa visite 1)
        $soap2 = new PemeriksaanRalan(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '09:00:00',
            tandaVital: new TandaVital(suhuTubuh: '36.9', tensi: '120/80'),
            kesadaran: Kesadaran::fromString('Compos Mentis'),
            keluhan: 'Pusing berputar',
            pemeriksaan: 'Nistagmus negatif',
            penilaian: 'Vertigo perifer',
            rtl: 'Betahistine 3x6mg',
            nip: 'DR001'
        );
        $repo->save($soap2);

        $all = $repo->findByNoRawat($noRawat);
        $this->assertCount(2, $all);
        $this->assertSame('09:00:00', $all[0]->jamRawat); // Terbaru di atas
        $this->assertSame('08:15:00', $all[1]->jamRawat);

        // Update visite 2 pada triplet yang sama
        $soap2Updated = new PemeriksaanRalan(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '09:00:00',
            tandaVital: new TandaVital(suhuTubuh: '37.0', tensi: '125/80'),
            kesadaran: Kesadaran::fromString('Compos Mentis'),
            keluhan: 'Pusing berputar',
            pemeriksaan: 'Nistagmus negatif',
            penilaian: 'BPPV',
            rtl: 'Betahistine 3x6mg + Epley maneuver',
            nip: 'DR001'
        );
        $repo->save($soap2Updated);

        $latest = $repo->findLatestByNoRawat($noRawat);
        $this->assertNotNull($latest);
        $this->assertSame('BPPV', $latest->penilaian);
        $this->assertCount(2, $repo->findByNoRawat($noRawat));
    }

    public function testSmartPeekShieldOnMetadataRepository(): void
    {
        $repo = new PdoPemeriksaanRalanRepository($this->nextPdo);
        $noRawat = new NoRawat('2026/11/16/000001');

        // Smart Peek (isMetaDirty = false) -> dilarang menulis ke tabel metadata
        $peek = new RmeMetadata($noRawat, '2026-11-16', '09:00:00', 'meta_kebidanan', '{"gravida":1}', isMetaDirty: false);
        $repo->saveMetadata($peek);

        $count = (int) $this->nextPdo->query("SELECT COUNT(*) FROM mlite_pemeriksaan_ralan_metadata")->fetchColumn();
        $this->assertSame(0, $count);

        // Dirty metadata valid -> wajib tersimpan
        $dirty = new RmeMetadata($noRawat, '2026-11-16', '09:00:00', 'meta_kebidanan', '{"gravida":2}', isMetaDirty: true);
        $repo->saveMetadata($dirty);

        $countAfter = (int) $this->nextPdo->query("SELECT COUNT(*) FROM mlite_pemeriksaan_ralan_metadata")->fetchColumn();
        $this->assertSame(1, $countAfter);
    }

    public function testDualConnectionRmeHistorySmartPeekMergesLegacyAndNext(): void
    {
        // Seed riwayat lama di legacy DB
        $this->legacyPdo->exec("
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR001', 'dr. Budi');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj)
            VALUES ('001', '2025/05/10/000001', '2025-05-10', '08:00:00', 'DR001', '000001', 'UMU01', 'UMU');
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, keluhan, pemeriksaan, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2025/05/10/000001', '2025-05-10', '08:30:00', 'Batuk lama', 'Ronki basah', 'Foto Thorax', 'Suspek Bronkitis', '-', '-', 'DR001');
        ");

        // Seed kunjungan baru di next DB
        $this->nextPdo->exec("
            INSERT INTO dokter (kd_dokter, nm_dokter) VALUES ('DR002', 'dr. Ani');
            INSERT INTO poliklinik (kd_poli, nm_poli) VALUES ('UMU01', 'Poli Umum');
            INSERT INTO reg_periksa (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis, kd_poli, kd_pj)
            VALUES ('001', '2026/11/16/000001', '2026-11-16', '09:00:00', 'DR002', '000001', 'UMU01', 'UMU');
            INSERT INTO pemeriksaan_ralan (no_rawat, tgl_perawatan, jam_rawat, keluhan, pemeriksaan, rtl, penilaian, instruksi, evaluasi, nip)
            VALUES ('2026/11/16/000001', '2026-11-16', '09:15:00', 'Kontrol batuk', 'Vesikuler normal', 'Edukasi', 'Membaik', '-', '-', 'DR002');
        ");

        $historyRepo = new PdoRmeHistoryRepository($this->nextPdo, $this->legacyGuard);
        $history = $historyRepo->getFullMedicalHistory(new NoRkmMedis('000001'));

        $this->assertCount(2, $history);
        $this->assertSame('2026/11/16/000001', $history[0]['no_rawat']);
        $this->assertSame('next', $history[0]['source']);
        $this->assertSame('2025/05/10/000001', $history[1]['no_rawat']);
        $this->assertSame('legacy', $history[1]['source']);
    }

    public function testIcdGroundingDoesNotBlockSkdiCompetencyTier(): void
    {
        $this->nextPdo->exec("
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia, status, skdi_level)
            VALUES ('J06.9', 'Acute upper respiratory infection, unspecified', 'ISPA Akut', 'Tidak Menular', '4A');
            INSERT INTO penyakit (kd_penyakit, nm_penyakit, nm_indonesia, status, skdi_level)
            VALUES ('I21.9', 'Acute myocardial infarction, unspecified', 'Infark Miokard Akut', 'Tidak Menular', '3B');
        ");

        $icdRepo = new PdoIcdGroundingRepository($this->nextPdo);

        // SKDI 3B (kompetensi rujukan) tetap muncul dan TIDAK diblokir
        $results = $icdRepo->searchSatuSehatIcd10('Infark');
        $this->assertCount(1, $results);
        $this->assertSame('I21.9', $results[0]['kd_penyakit']);
        $this->assertSame('3B', $results[0]['skdi_level']);
        $this->assertNotEmpty($results[0]['referral_suggestion']);

        // Diagnosa tetap dapat disimpan
        $dx = new DiagnosaPasien(new NoRawat('2026/11/16/000001'), 'I21.9', 'Ralan', 1);
        $icdRepo->saveDiagnosa($dx);

        $savedCount = (int) $this->nextPdo->query("SELECT COUNT(*) FROM diagnosa_pasien WHERE kd_penyakit = 'I21.9'")->fetchColumn();
        $this->assertSame(1, $savedCount);
    }

    public function testOdontogramSaveAndDeleteByPrimaryKeyId(): void
    {
        $odoRepo = new PdoOdontogramRepository($this->nextPdo);
        $odo = new Odontogram(
            id: null,
            noRkmMedis: new NoRkmMedis('000001'),
            noRawat: new NoRawat('2026/11/16/000001'),
            tglPemeriksaan: '2026-11-16',
            nomorGigi: '18',
            kondisi: 'UNE',
            catatan: 'Belum erupsi',
            idUser: 5
        );

        $odoRepo->save($odo);
        $list = $odoRepo->findByNoRkmMedis(new NoRkmMedis('000001'));
        $this->assertCount(1, $list);
        $savedId = $list[0]->id;
        $this->assertNotNull($savedId);

        // Dokter jaga lain menghapus berdasarkan Primary Key id
        $odoRepo->deleteById($savedId);
        $this->assertCount(0, $odoRepo->findByNoRkmMedis(new NoRkmMedis('000001')));
    }

    public function testPemeriksaanRanapSaveAndList(): void
    {
        $ranapRepo = new PdoPemeriksaanRanapRepository($this->nextPdo);
        $noRawat = new NoRawat('2026/11/16/000002');

        $cppt = new PemeriksaanRanap(
            noRawat: $noRawat,
            tglPerawatan: '2026-11-16',
            jamRawat: '10:00:00',
            tandaVital: new TandaVital(suhuTubuh: '36.6', tensi: '120/80'),
            kesadaran: Kesadaran::fromString('Compos Mentis'),
            keluhan: 'Sesak berkurang',
            pemeriksaan: 'SpO2 98%',
            rtl: 'Lanjutkan terapi O2 nasal kanul',
            nip: 'DR001'
        );
        $ranapRepo->save($cppt);

        $rows = $ranapRepo->findByNoRawat($noRawat);
        $this->assertCount(1, $rows);
        $this->assertSame('Lanjutkan terapi O2 nasal kanul', $rows[0]->rtl);
    }
}
