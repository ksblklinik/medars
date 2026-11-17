<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Pharmacy\Repository\PharmacyReturRepositoryInterface;
use PDO;

final class PdoPharmacyReturRepository implements PharmacyReturRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function returDispensedObat(
        string $noResep,
        string $kodeBrng,
        float $qtyRetur,
        string $kdBangsal,
        string $petugasNip,
        string $alasan
    ): array {
        if ($qtyRetur <= 0) {
            throw new \InvalidArgumentException('Jumlah retur harus lebih dari 0.');
        }

        $resepStmt = $this->pdo->prepare('SELECT no_rawat, tgl_perawatan FROM resep_obat WHERE no_resep = :nr LIMIT 1');
        $resepStmt->execute([':nr' => $noResep]);
        $resep = $resepStmt->fetch(PDO::FETCH_ASSOC);
        if ($resep === false || empty($resep['tgl_perawatan']) || $resep['tgl_perawatan'] === '0000-00-00') {
            throw new \DomainException("Resep {$noResep} belum divalidasi/diserahkan atau tidak ditemukan.");
        }
        $noRawat = (string) $resep['no_rawat'];

        $dpoStmt = $this->pdo->prepare(
            'SELECT tgl_perawatan, jam, no_batch, no_faktur, biaya_obat, jml, kd_bangsal
             FROM detail_pemberian_obat
             WHERE no_rawat = :nr AND kode_brng = :kb
             ORDER BY tgl_perawatan DESC, jam DESC LIMIT 1'
        );
        $dpoStmt->execute([':nr' => $noRawat, ':kb' => $kodeBrng]);
        $dpo = $dpoStmt->fetch(PDO::FETCH_ASSOC);
        if ($dpo === false || (float) $dpo['jml'] < $qtyRetur) {
            throw new \DomainException("Jumlah retur ({$qtyRetur}) melebihi jumlah obat yang diserahkan.");
        }

        $depo = trim($kdBangsal) !== '' ? trim($kdBangsal) : (string) ($dpo['kd_bangsal'] ?: 'FAR01');
        $noBatch = (string) ($dpo['no_batch'] ?? '');
        $noFaktur = (string) ($dpo['no_faktur'] ?? '');
        $biayaObat = (float) $dpo['biaya_obat'];
        $sisaQty = (float) $dpo['jml'] - $qtyRetur;

        if ($sisaQty <= 0.0001) {
            $this->pdo->prepare(
                'DELETE FROM detail_pemberian_obat WHERE no_rawat = :nr AND kode_brng = :kb AND tgl_perawatan = :tp AND jam = :jm'
            )->execute([
                ':nr' => $noRawat,
                ':kb' => $kodeBrng,
                ':tp' => (string) $dpo['tgl_perawatan'],
                ':jm' => (string) $dpo['jam'],
            ]);
        } else {
            $this->pdo->prepare(
                'UPDATE detail_pemberian_obat SET jml = :jml, total = :tot
                 WHERE no_rawat = :nr AND kode_brng = :kb AND tgl_perawatan = :tp AND jam = :jm'
            )->execute([
                ':jml' => $sisaQty,
                ':tot' => $sisaQty * $biayaObat,
                ':nr' => $noRawat,
                ':kb' => $kodeBrng,
                ':tp' => (string) $dpo['tgl_perawatan'],
                ':jm' => (string) $dpo['jam'],
            ]);
        }

        // Kembalikan stok ke gudangbarang & catat riwayat_barang_medis
        $gbChk = $this->pdo->prepare(
            'SELECT stok FROM gudangbarang WHERE kode_brng = :kb AND kd_bangsal = :kd AND no_batch = :nb AND no_faktur = :nf LIMIT 1'
        );
        $gbChk->execute([':kb' => $kodeBrng, ':kd' => $depo, ':nb' => $noBatch, ':nf' => $noFaktur]);
        $stokAwal = $gbChk->fetchColumn();
        if ($stokAwal !== false) {
            $stokAkhir = (float) $stokAwal + $qtyRetur;
            $this->pdo->prepare(
                'UPDATE gudangbarang SET stok = :st WHERE kode_brng = :kb AND kd_bangsal = :kd AND no_batch = :nb AND no_faktur = :nf'
            )->execute([':st' => $stokAkhir, ':kb' => $kodeBrng, ':kd' => $depo, ':nb' => $noBatch, ':nf' => $noFaktur]);
        } else {
            $stokAwal = 0.0;
            $stokAkhir = $qtyRetur;
            $this->pdo->prepare(
                'INSERT INTO gudangbarang (kode_brng, kd_bangsal, no_batch, no_faktur, stok) VALUES (:kb, :kd, :nb, :nf, :st)'
            )->execute([':kb' => $kodeBrng, ':kd' => $depo, ':nb' => $noBatch, ':nf' => $noFaktur, ':st' => $stokAkhir]);
        }

        $nowTgl = date('Y-m-d');
        $nowJam = date('H:i:s');
        $this->pdo->prepare(
            "INSERT INTO riwayat_barang_medis (kode_brng, stok_awal, masuk, keluar, stok_akhir, posisi, tanggal, jam, petugas, kd_bangsal, status, no_batch, no_faktur, keterangan)
             VALUES (:kb, :sa, :msk, 0, :sak, 'Retur Obat', :tgl, :jam, :pet, :kd, 'Simpan', :nb, :nf, :ket)"
        )->execute([
            ':kb' => $kodeBrng,
            ':sa' => (float) $stokAwal,
            ':msk' => $qtyRetur,
            ':sak' => $stokAkhir,
            ':tgl' => $nowTgl,
            ':jam' => $nowJam,
            ':pet' => $petugasNip,
            ':kd' => $depo,
            ':nb' => $noBatch,
            ':nf' => $noFaktur,
            ':ket' => "Retur Resep {$noResep}: {$alasan}",
        ]);

        // Sesuaikan baris mlite_billing_ledger terkait resep+obat
        $provId = 'RSP|' . $noResep . '|' . $kodeBrng;
        $ledStmt = $this->pdo->prepare("SELECT id, qty, unit_price, status FROM mlite_billing_ledger WHERE provider_id = :pid AND status != 'Cancelled' LIMIT 1");
        $ledStmt->execute([':pid' => $provId]);
        $led = $ledStmt->fetch(PDO::FETCH_ASSOC);
        if ($led !== false && (string) $led['status'] === 'Pending') {
            $newQty = (float) $led['qty'] - $qtyRetur;
            if ($newQty <= 0.0001) {
                $this->pdo->prepare("UPDATE mlite_billing_ledger SET status = 'Cancelled' WHERE id = :id")->execute([':id' => (int) $led['id']]);
            } else {
                $this->pdo->prepare('UPDATE mlite_billing_ledger SET qty = :q, total_amount = :tot WHERE id = :id')->execute([
                    ':q' => $newQty,
                    ':tot' => $newQty * (float) $led['unit_price'],
                    ':id' => (int) $led['id'],
                ]);
            }
        }

        return [
            'no_resep' => $noResep,
            'no_rawat' => $noRawat,
            'kode_brng' => $kodeBrng,
            'qty_retur' => $qtyRetur,
            'stok_akhir' => $stokAkhir,
            'nominal_retur' => $qtyRetur * $biayaObat,
        ];
    }
}
