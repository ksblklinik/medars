<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Bridging\Repository\PcareRepositoryInterface;
use PDO;

final class PdoPcareRepository implements PcareRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listPendaftaran(string $from = '', string $to = '', string $search = ''): array
    {
        $sql = 'SELECT * FROM pcare_pendaftaran WHERE 1=1';
        $params = [];
        if ($from !== '' && $to !== '') {
            $sql .= ' AND tglDaftar BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' AND (no_rawat LIKE :q OR noKartu LIKE :q OR nmPasien LIKE :q OR noUrut LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function savePendaftaran(array $data): array
    {
        $tgl = trim((string) ($data['tglDaftar'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $noUrut = trim((string) ($data['noUrut'] ?? ''));
        if ($noUrut === '') {
            $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM pcare_pendaftaran WHERE tglDaftar = :tgl');
            $countStmt->execute([':tgl' => $tgl]);
            $noUrut = 'A' . str_pad((string) ((int) $countStmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO pcare_pendaftaran (no_rawat, tglDaftar, noKartu, nmPasien, kdPoli, nmPoli, keluhan, kdSadar, sistole, diastole, beratBadan, tinggiBadan, respRate, heartRate, noUrut, status, created_at)
             VALUES (:nr, :tgl, :nk, :np, :kp, :nmp, :kel, :ks, :sis, :dia, :bb, :tb, :rr, :hr, :nu, :st, :ca)'
        );
        $stmt->execute([
            ':nr' => trim((string) ($data['no_rawat'] ?? '')),
            ':tgl' => $tgl,
            ':nk' => trim((string) ($data['noKartu'] ?? '')),
            ':np' => trim((string) ($data['nmPasien'] ?? '')),
            ':kp' => trim((string) ($data['kdPoli'] ?? '001')) ?: '001',
            ':nmp' => trim((string) ($data['nmPoli'] ?? 'Poli Umum')) ?: 'Poli Umum',
            ':kel' => trim((string) ($data['keluhan'] ?? '')),
            ':ks' => trim((string) ($data['kdSadar'] ?? '01')) ?: '01',
            ':sis' => (int) ($data['sistole'] ?? 120),
            ':dia' => (int) ($data['diastole'] ?? 80),
            ':bb' => (float) ($data['beratBadan'] ?? 60),
            ':tb' => (float) ($data['tinggiBadan'] ?? 165),
            ':rr' => (int) ($data['respRate'] ?? 20),
            ':hr' => (int) ($data['heartRate'] ?? 80),
            ':nu' => $noUrut,
            ':st' => 'Terkirim',
            ':ca' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'noUrut' => $noUrut,
            'tglDaftar' => $tgl,
            'noKartu' => trim((string) ($data['noKartu'] ?? '')),
            'nmPasien' => trim((string) ($data['nmPasien'] ?? '')),
            'status' => 'Terkirim',
        ];
    }

    public function listKunjungan(string $from = '', string $to = '', string $search = ''): array
    {
        $sql = 'SELECT * FROM pcare_kunjungan WHERE 1=1';
        $params = [];
        if ($from !== '' && $to !== '') {
            $sql .= ' AND tglDaftar BETWEEN :from AND :to';
            $params[':from'] = $from;
            $params[':to'] = $to;
        }
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' AND (noKunjungan LIKE :q OR no_rawat LIKE :q OR noKartu LIKE :q OR nmPasien LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY tglDaftar DESC, noKunjungan DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findKunjungan(string $noKunjungan): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM pcare_kunjungan WHERE noKunjungan = :nk LIMIT 1');
        $stmt->execute([':nk' => trim($noKunjungan)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function saveKunjungan(array $data): array
    {
        $noKunjungan = trim((string) ($data['noKunjungan'] ?? ''));
        if ($noKunjungan === '') {
            $noKunjungan = '01120101' . date('my') . 'Y' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $tgl = trim((string) ($data['tglDaftar'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $tglPulang = trim((string) ($data['tglPulang'] ?? $tgl)) ?: $tgl;
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO pcare_kunjungan (noKunjungan, no_rawat, tglDaftar, noKartu, nmPasien, kdPoli, nmPoli, keluhan, kdDiagnosa1, nmDiagnosa1, kdStatusPulang, nmStatusPulang, tglPulang, kdDokter, nmDokter, created_at)
             VALUES (:nkj, :nr, :tgl, :nk, :np, :kp, :nmp, :kel, :kd1, :nd1, :ksp, :nsp, :tp, :kdd, :nmd, :ca)'
        );
        $stmt->execute([
            ':nkj' => $noKunjungan,
            ':nr' => trim((string) ($data['no_rawat'] ?? '')),
            ':tgl' => $tgl,
            ':nk' => trim((string) ($data['noKartu'] ?? '')),
            ':np' => trim((string) ($data['nmPasien'] ?? '')),
            ':kp' => trim((string) ($data['kdPoli'] ?? '001')) ?: '001',
            ':nmp' => trim((string) ($data['nmPoli'] ?? 'Poli Umum')) ?: 'Poli Umum',
            ':kel' => trim((string) ($data['keluhan'] ?? '')),
            ':kd1' => trim((string) ($data['kdDiagnosa1'] ?? '')),
            ':nd1' => trim((string) ($data['nmDiagnosa1'] ?? '')),
            ':ksp' => trim((string) ($data['kdStatusPulang'] ?? '3')) ?: '3',
            ':nsp' => trim((string) ($data['nmStatusPulang'] ?? 'Berobat Jalan')) ?: 'Berobat Jalan',
            ':tp' => $tglPulang,
            ':kdd' => trim((string) ($data['kdDokter'] ?? '')),
            ':nmd' => trim((string) ($data['nmDokter'] ?? '')),
            ':ca' => $now,
        ]);

        return [
            'noKunjungan' => $noKunjungan,
            'no_rawat' => trim((string) ($data['no_rawat'] ?? '')),
            'tglDaftar' => $tgl,
            'noKartu' => trim((string) ($data['noKartu'] ?? '')),
            'nmPasien' => trim((string) ($data['nmPasien'] ?? '')),
        ];
    }

    public function listRujukan(string $search = ''): array
    {
        $sql = 'SELECT r.*, k.noKartu, k.nmPasien, k.kdDiagnosa1, k.nmDiagnosa1 FROM pcare_rujukan r LEFT JOIN pcare_kunjungan k ON k.noKunjungan = r.noKunjungan';
        $params = [];
        $q = trim($search);
        if ($q !== '') {
            $sql .= ' WHERE r.noKunjungan LIKE :q OR r.no_rawat LIKE :q OR r.nmppk LIKE :q OR k.nmPasien LIKE :q';
            $params[':q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY r.tglEstRujuk DESC, r.noKunjungan DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findRujukan(string $noKunjungan): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, k.noKartu, k.nmPasien, k.tglDaftar, k.kdDiagnosa1, k.nmDiagnosa1, k.nmDokter
             FROM pcare_rujukan r
             LEFT JOIN pcare_kunjungan k ON k.noKunjungan = r.noKunjungan
             WHERE r.noKunjungan = :nkj LIMIT 1'
        );
        $stmt->execute([':nkj' => trim($noKunjungan)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function saveRujukan(array $data): array
    {
        $noKunjungan = trim((string) ($data['noKunjungan'] ?? ''));
        $tglEst = trim((string) ($data['tglEstRujuk'] ?? date('Y-m-d'))) ?: date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO pcare_rujukan (noKunjungan, no_rawat, tglEstRujuk, kdppk, nmppk, kdSubSpesialis, nmSubSpesialis, catatan, created_at)
             VALUES (:nkj, :nr, :tgl, :kp, :np, :ks, :ns, :ct, :ca)'
        );
        $stmt->execute([
            ':nkj' => $noKunjungan,
            ':nr' => trim((string) ($data['no_rawat'] ?? '')),
            ':tgl' => $tglEst,
            ':kp' => trim((string) ($data['kdppk'] ?? '')),
            ':np' => trim((string) ($data['nmppk'] ?? '')),
            ':ks' => trim((string) ($data['kdSubSpesialis'] ?? '')),
            ':ns' => trim((string) ($data['nmSubSpesialis'] ?? '')),
            ':ct' => trim((string) ($data['catatan'] ?? '')),
            ':ca' => $now,
        ]);

        return [
            'noKunjungan' => $noKunjungan,
            'tglEstRujuk' => $tglEst,
            'nmppk' => trim((string) ($data['nmppk'] ?? '')),
            'nmSubSpesialis' => trim((string) ($data['nmSubSpesialis'] ?? '')),
        ];
    }

    public function listObat(string $noKunjungan = ''): array
    {
        $sql = 'SELECT * FROM pcare_obat';
        $params = [];
        if (trim($noKunjungan) !== '') {
            $sql .= ' WHERE noKunjungan = :nkj';
            $params[':nkj'] = trim($noKunjungan);
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveObat(array $data): array
    {
        $kdObatSK = trim((string) ($data['kdObatSK'] ?? ''));
        if ($kdObatSK === '') {
            $kdObatSK = 'OBT' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO pcare_obat (noKunjungan, kdObatSK, kdObat, nmObat, signa1, signa2, jmlObat, created_at)
             VALUES (:nkj, :sk, :ko, :no, :s1, :s2, :jml, :ca)'
        );
        $stmt->execute([
            ':nkj' => trim((string) ($data['noKunjungan'] ?? '')),
            ':sk' => $kdObatSK,
            ':ko' => trim((string) ($data['kdObat'] ?? '')),
            ':no' => trim((string) ($data['nmObat'] ?? '')),
            ':s1' => (int) ($data['signa1'] ?? 3),
            ':s2' => (int) ($data['signa2'] ?? 1),
            ':jml' => (float) ($data['jmlObat'] ?? 1),
            ':ca' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'noKunjungan' => trim((string) ($data['noKunjungan'] ?? '')),
            'kdObatSK' => $kdObatSK,
            'nmObat' => trim((string) ($data['nmObat'] ?? '')),
        ];
    }

    public function listTindakan(string $noKunjungan = ''): array
    {
        $sql = 'SELECT * FROM pcare_tindakan';
        $params = [];
        if (trim($noKunjungan) !== '') {
            $sql .= ' WHERE noKunjungan = :nkj';
            $params[':nkj'] = trim($noKunjungan);
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveTindakan(array $data): array
    {
        $kdTindakanSK = trim((string) ($data['kdTindakanSK'] ?? ''));
        if ($kdTindakanSK === '') {
            $kdTindakanSK = 'TDK' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO pcare_tindakan (noKunjungan, kdTindakanSK, kdTindakan, nmTindakan, biaya, keterangan, created_at)
             VALUES (:nkj, :sk, :kt, :nt, :bi, :ket, :ca)'
        );
        $stmt->execute([
            ':nkj' => trim((string) ($data['noKunjungan'] ?? '')),
            ':sk' => $kdTindakanSK,
            ':kt' => trim((string) ($data['kdTindakan'] ?? '')),
            ':nt' => trim((string) ($data['nmTindakan'] ?? '')),
            ':bi' => (float) ($data['biaya'] ?? 0),
            ':ket' => trim((string) ($data['keterangan'] ?? '')),
            ':ca' => $now,
        ]);

        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'noKunjungan' => trim((string) ($data['noKunjungan'] ?? '')),
            'kdTindakanSK' => $kdTindakanSK,
            'nmTindakan' => trim((string) ($data['nmTindakan'] ?? '')),
        ];
    }
}
