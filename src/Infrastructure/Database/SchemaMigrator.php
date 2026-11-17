<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;

final class SchemaMigrator
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function migrate(): void
    {
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $isMysql = $driver === 'mysql';
        $autoIncPk = $isMysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $bigAutoIncPk = $isMysql ? 'BIGINT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $suffix = $isMysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        $statements = [
            // 1. Auth, RBAC, Settings & Audit
            "CREATE TABLE IF NOT EXISTS mlite_users (
                id {$autoIncPk},
                username VARCHAR(100) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(150) NOT NULL DEFAULT '',
                no_telp VARCHAR(30) NOT NULL DEFAULT '',
                fullname VARCHAR(150) NOT NULL DEFAULT '',
                role VARCHAR(50) NOT NULL DEFAULT 'staff',
                cap TEXT,
                access TEXT,
                avatar VARCHAR(255) NOT NULL DEFAULT '',
                status VARCHAR(5) NOT NULL DEFAULT '1',
                created_at VARCHAR(30) NOT NULL DEFAULT '',
                updated_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_settings (
                module VARCHAR(50) NOT NULL,
                field VARCHAR(100) NOT NULL,
                value TEXT NOT NULL,
                PRIMARY KEY (module, field)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_logs (
                id {$bigAutoIncPk},
                user_id INT NOT NULL DEFAULT 0,
                username VARCHAR(100) NOT NULL DEFAULT '',
                action VARCHAR(100) NOT NULL,
                details TEXT NOT NULL,
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                created_at VARCHAR(30) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_login_attempts (
                id {$bigAutoIncPk},
                username VARCHAR(100) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                attempt_time INT NOT NULL
            ){$suffix}",

            // 2. SDM / Kepegawaian
            "CREATE TABLE IF NOT EXISTS dokter (
                kd_dokter VARCHAR(20) PRIMARY KEY,
                nm_dokter VARCHAR(100) NOT NULL,
                jk VARCHAR(5) NOT NULL DEFAULT 'L',
                no_ijn_praktek VARCHAR(100) NOT NULL DEFAULT '',
                status VARCHAR(5) NOT NULL DEFAULT '1',
                almt_tgl VARCHAR(200) NOT NULL DEFAULT '',
                no_telp VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS petugas (
                nip VARCHAR(20) PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                jk VARCHAR(5) NOT NULL DEFAULT 'L',
                jbtn VARCHAR(100) NOT NULL DEFAULT '',
                status VARCHAR(5) NOT NULL DEFAULT '1',
                almt_tgl VARCHAR(200) NOT NULL DEFAULT '',
                no_telp VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS pegawai (
                nik VARCHAR(20) PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                jk VARCHAR(15) NOT NULL DEFAULT 'Pria',
                jbtn VARCHAR(100) NOT NULL DEFAULT '',
                departemen VARCHAR(100) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'AKTIF'
            ){$suffix}",

            // 3. Master Klinik & Tarif
            "CREATE TABLE IF NOT EXISTS poliklinik (
                kd_poli VARCHAR(10) PRIMARY KEY,
                nm_poli VARCHAR(100) NOT NULL,
                registrasi DECIMAL(15,2) NOT NULL DEFAULT 0,
                registrasilama DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(5) NOT NULL DEFAULT '1',
                allow_bpjs TINYINT NOT NULL DEFAULT 1
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS jadwal (
                kd_dokter VARCHAR(20) NOT NULL,
                kd_poli VARCHAR(10) NOT NULL,
                hari_kerja VARCHAR(15) NOT NULL,
                jam_mulai VARCHAR(10) NOT NULL,
                jam_selesai VARCHAR(10) NOT NULL,
                kuota INT NOT NULL DEFAULT 0,
                prefik VARCHAR(10) NOT NULL DEFAULT '',
                room VARCHAR(30) NOT NULL DEFAULT '',
                PRIMARY KEY (kd_dokter, kd_poli, hari_kerja, jam_mulai)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS penjab (
                kd_pj VARCHAR(10) PRIMARY KEY,
                png_jawab VARCHAR(100) NOT NULL,
                status VARCHAR(5) NOT NULL DEFAULT '1',
                nama_perusahaan VARCHAR(150) NOT NULL DEFAULT '',
                alamat_asuransi VARCHAR(200) NOT NULL DEFAULT '',
                no_telp VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS jns_perawatan (
                kd_jenis_prw VARCHAR(20) PRIMARY KEY,
                nm_perawatan VARCHAR(150) NOT NULL,
                kd_kategori VARCHAR(15) NOT NULL DEFAULT '',
                material DECIMAL(15,2) NOT NULL DEFAULT 0,
                tarif_tindakandr DECIMAL(15,2) NOT NULL DEFAULT 0,
                tarif_tindakanpr DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_byrdr DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_byrpr DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(5) NOT NULL DEFAULT '1'
            ){$suffix}",

            // 4. Pasien, Registrasi & Booking (Fase 1)
            "CREATE TABLE IF NOT EXISTS pasien (
                no_rkm_medis VARCHAR(15) PRIMARY KEY,
                nm_pasien VARCHAR(100) NOT NULL,
                no_ktp VARCHAR(20) NOT NULL DEFAULT '',
                jk VARCHAR(5) NOT NULL DEFAULT '-',
                tmp_lahir VARCHAR(50) NOT NULL DEFAULT '',
                tgl_lahir VARCHAR(15) NOT NULL,
                nm_ibu VARCHAR(100) NOT NULL DEFAULT '-',
                alamat VARCHAR(255) NOT NULL DEFAULT '',
                gol_darah VARCHAR(5) NOT NULL DEFAULT '-',
                pekerjaan VARCHAR(60) NOT NULL DEFAULT '-',
                stts_nikah VARCHAR(30) NOT NULL DEFAULT 'BELUM MENIKAH',
                agama VARCHAR(20) NOT NULL DEFAULT '-',
                tgl_daftar VARCHAR(15) NOT NULL DEFAULT '',
                no_tlp VARCHAR(30) NOT NULL DEFAULT '',
                umur VARCHAR(30) NOT NULL DEFAULT '0 Th',
                pnd VARCHAR(20) NOT NULL DEFAULT '-',
                keluarga VARCHAR(30) NOT NULL DEFAULT 'DIRI SENDIRI',
                namakeluarga VARCHAR(100) NOT NULL DEFAULT '-',
                kd_pj VARCHAR(10) NOT NULL DEFAULT 'UMU',
                no_peserta VARCHAR(30) NOT NULL DEFAULT '',
                kd_kel INT NOT NULL DEFAULT 0,
                kd_kec INT NOT NULL DEFAULT 0,
                kd_kab INT NOT NULL DEFAULT 0,
                pekerjaanpj VARCHAR(60) NOT NULL DEFAULT '-',
                alamatpj VARCHAR(255) NOT NULL DEFAULT '-',
                kelurahanpj VARCHAR(60) NOT NULL DEFAULT '-',
                kecamatanpj VARCHAR(60) NOT NULL DEFAULT '-',
                kabupatenpj VARCHAR(60) NOT NULL DEFAULT '-',
                perusahaan_pasien VARCHAR(20) NOT NULL DEFAULT '-',
                suku_bangsa INT NOT NULL DEFAULT 1,
                bahasa_pasien INT NOT NULL DEFAULT 1,
                cacat_fisik INT NOT NULL DEFAULT 1,
                email VARCHAR(100) NOT NULL DEFAULT '',
                nip VARCHAR(30) NOT NULL DEFAULT '-',
                kd_prop INT NOT NULL DEFAULT 0,
                propinsipj VARCHAR(60) NOT NULL DEFAULT '-'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS reg_periksa (
                no_reg VARCHAR(10) NOT NULL,
                no_rawat VARCHAR(20) PRIMARY KEY,
                tgl_registrasi VARCHAR(15) NOT NULL,
                jam_reg VARCHAR(10) NOT NULL,
                kd_dokter VARCHAR(20) NOT NULL,
                no_rkm_medis VARCHAR(15) NOT NULL,
                kd_poli VARCHAR(10) NOT NULL,
                p_jawab VARCHAR(100) NOT NULL DEFAULT '-',
                almt_pj VARCHAR(255) NOT NULL DEFAULT '-',
                hubunganpj VARCHAR(30) NOT NULL DEFAULT '-',
                biaya_reg DECIMAL(15,2) NOT NULL DEFAULT 0,
                stts VARCHAR(30) NOT NULL DEFAULT 'Belum',
                stts_daftar VARCHAR(15) NOT NULL DEFAULT 'Lama',
                status_lanjut VARCHAR(10) NOT NULL DEFAULT 'Ralan',
                kd_pj VARCHAR(10) NOT NULL,
                umurdaftar INT NOT NULL DEFAULT 0,
                sttsumur VARCHAR(5) NOT NULL DEFAULT 'Th',
                status_bayar VARCHAR(20) NOT NULL DEFAULT 'Belum Bayar',
                status_poli VARCHAR(15) NOT NULL DEFAULT 'Lama',
                prefik VARCHAR(10) NOT NULL DEFAULT '',
                room VARCHAR(30) NOT NULL DEFAULT '',
                shift VARCHAR(20) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS booking_registrasi (
                tanggal_booking VARCHAR(15) NOT NULL,
                jam_booking VARCHAR(10) NOT NULL,
                no_rkm_medis VARCHAR(15) NOT NULL,
                tanggal_periksa VARCHAR(15) NOT NULL,
                kd_dokter VARCHAR(20) NOT NULL,
                kd_poli VARCHAR(10) NOT NULL,
                no_reg VARCHAR(10) NOT NULL,
                kd_pj VARCHAR(10) NOT NULL,
                limit_reg INT NOT NULL DEFAULT 0,
                waktu_kunjungan VARCHAR(30) NOT NULL DEFAULT '',
                status VARCHAR(30) NOT NULL DEFAULT 'Belum',
                PRIMARY KEY (no_rkm_medis, tanggal_periksa)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS sse_events (
                id {$bigAutoIncPk},
                channel VARCHAR(64) NOT NULL,
                event VARCHAR(128) NOT NULL,
                payload TEXT NOT NULL,
                created_at VARCHAR(30) NOT NULL
            ){$suffix}",

            // 5. Rekam Medis Elektronik / RME (Fase 2)
            "CREATE TABLE IF NOT EXISTS pemeriksaan_ralan (
                no_rawat VARCHAR(20) NOT NULL,
                tgl_perawatan VARCHAR(15) NOT NULL,
                jam_rawat VARCHAR(10) NOT NULL,
                suhu_tubuh VARCHAR(10) NOT NULL DEFAULT '-',
                tensi VARCHAR(15) NOT NULL DEFAULT '-',
                sistole VARCHAR(10) NOT NULL DEFAULT '-',
                diastole VARCHAR(10) NOT NULL DEFAULT '-',
                nadi VARCHAR(10) NOT NULL DEFAULT '-',
                respirasi VARCHAR(10) NOT NULL DEFAULT '-',
                tinggi VARCHAR(10) NOT NULL DEFAULT '-',
                berat VARCHAR(10) NOT NULL DEFAULT '-',
                spo2 VARCHAR(10) NOT NULL DEFAULT '-',
                gcs VARCHAR(10) NOT NULL DEFAULT '-',
                kesadaran VARCHAR(30) NOT NULL DEFAULT 'Compos Mentis',
                keluhan TEXT NOT NULL,
                pemeriksaan TEXT NOT NULL,
                alergi VARCHAR(150) NOT NULL DEFAULT '-',
                lingkar_perut VARCHAR(10) NOT NULL DEFAULT '-',
                rtl TEXT NOT NULL,
                penilaian TEXT NOT NULL,
                instruksi TEXT NOT NULL,
                evaluasi TEXT NOT NULL,
                nip VARCHAR(20) NOT NULL,
                PRIMARY KEY (no_rawat, tgl_perawatan, jam_rawat)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS pemeriksaan_ranap (
                no_rawat VARCHAR(20) NOT NULL,
                tgl_perawatan VARCHAR(15) NOT NULL,
                jam_rawat VARCHAR(10) NOT NULL,
                suhu_tubuh VARCHAR(10) NOT NULL DEFAULT '-',
                tensi VARCHAR(15) NOT NULL DEFAULT '-',
                nadi VARCHAR(10) NOT NULL DEFAULT '-',
                respirasi VARCHAR(10) NOT NULL DEFAULT '-',
                tinggi VARCHAR(10) NOT NULL DEFAULT '-',
                berat VARCHAR(10) NOT NULL DEFAULT '-',
                spo2 VARCHAR(10) NOT NULL DEFAULT '-',
                gcs VARCHAR(10) NOT NULL DEFAULT '-',
                kesadaran VARCHAR(30) NOT NULL DEFAULT 'Compos Mentis',
                keluhan TEXT NOT NULL,
                pemeriksaan TEXT NOT NULL,
                alergi VARCHAR(150) NOT NULL DEFAULT '-',
                rtl TEXT NOT NULL,
                penilaian TEXT NOT NULL,
                instruksi TEXT NOT NULL,
                evaluasi TEXT NOT NULL,
                nip VARCHAR(20) NOT NULL,
                PRIMARY KEY (no_rawat, tgl_perawatan, jam_rawat)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_pemeriksaan_ralan_metadata (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                tgl_perawatan VARCHAR(15) NOT NULL,
                jam_rawat VARCHAR(10) NOT NULL,
                meta_key VARCHAR(100) NOT NULL,
                meta_value TEXT NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS penyakit (
                kd_penyakit VARCHAR(15) PRIMARY KEY,
                nm_penyakit VARCHAR(200) NOT NULL,
                ciri_ciri TEXT,
                keterangan TEXT,
                kd_ktg VARCHAR(15) NOT NULL DEFAULT '-',
                status VARCHAR(30) NOT NULL DEFAULT 'Menular',
                nm_indonesia VARCHAR(200) NOT NULL DEFAULT '',
                skdi_level VARCHAR(10) NOT NULL DEFAULT '4A'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS diagnosa_pasien (
                no_rawat VARCHAR(20) NOT NULL,
                kd_penyakit VARCHAR(15) NOT NULL,
                status VARCHAR(15) NOT NULL DEFAULT 'Ralan',
                prioritas TINYINT NOT NULL DEFAULT 1,
                status_penyakit VARCHAR(20) NOT NULL DEFAULT 'Baru',
                PRIMARY KEY (no_rawat, kd_penyakit, status)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_odontogram (
                id {$bigAutoIncPk},
                no_rkm_medis VARCHAR(15) NOT NULL,
                no_rawat VARCHAR(20) NOT NULL,
                tgl_pemeriksaan VARCHAR(15) NOT NULL,
                nomor_gigi VARCHAR(10) NOT NULL,
                kondisi VARCHAR(30) NOT NULL,
                catatan TEXT,
                id_user INT NOT NULL DEFAULT 0
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS surat_sakit (
                no_surat VARCHAR(50) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                tanggalawal VARCHAR(15) NOT NULL,
                tanggalakhir VARCHAR(15) NOT NULL,
                lamasakit VARCHAR(20) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS surat_hamil (
                no_surat VARCHAR(50) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                tanggalperiksa VARCHAR(15) NOT NULL,
                hasilperiksa VARCHAR(100) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS surat_sehat (
                no_surat VARCHAR(50) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                tanggalsurat VARCHAR(15) NOT NULL,
                berat VARCHAR(15) NOT NULL DEFAULT '',
                tinggi VARCHAR(15) NOT NULL DEFAULT '',
                tensi VARCHAR(20) NOT NULL DEFAULT '',
                suhu VARCHAR(15) NOT NULL DEFAULT '',
                butawarna VARCHAR(25) NOT NULL DEFAULT 'Tidak',
                keperluan VARCHAR(150) NOT NULL DEFAULT 'Administrasi',
                kesimpulan VARCHAR(30) NOT NULL DEFAULT 'Sehat'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS surat_cuti_hamil (
                no_surat VARCHAR(50) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                keterangan_hamil VARCHAR(150) NOT NULL DEFAULT '',
                terhitung_mulai VARCHAR(15) NOT NULL,
                perkiraan_lahir VARCHAR(15) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS surat_bebas_narkoba (
                no_surat VARCHAR(50) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                tanggal VARCHAR(15) NOT NULL,
                kategori VARCHAR(50) NOT NULL,
                kd_dokter VARCHAR(20) NOT NULL,
                keperluan VARCHAR(150) NOT NULL,
                opiat VARCHAR(20) NOT NULL DEFAULT 'NEGATIF',
                ganja VARCHAR(20) NOT NULL DEFAULT 'NEGATIF',
                amphetamin VARCHAR(20) NOT NULL DEFAULT 'NEGATIF',
                methamphetamin VARCHAR(20) NOT NULL DEFAULT 'NEGATIF',
                benzodiazepin VARCHAR(20) NOT NULL DEFAULT 'NEGATIF',
                cocain VARCHAR(20) NOT NULL DEFAULT 'NEGATIF'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS rujukan_internal (
                no_rawat VARCHAR(20) NOT NULL,
                kd_dokter VARCHAR(20) NOT NULL,
                kd_poli VARCHAR(10) NOT NULL,
                catatan TEXT,
                PRIMARY KEY (no_rawat, kd_poli)
            ){$suffix}",

            // 6. Farmasi, E-Resep & Gudang Multi-Depo (Fase 3)
            "CREATE TABLE IF NOT EXISTS databarang (
                kode_brng VARCHAR(20) PRIMARY KEY,
                nama_brng VARCHAR(150) NOT NULL,
                kode_sat VARCHAR(15) NOT NULL DEFAULT 'TAB',
                dasar DECIMAL(15,2) NOT NULL DEFAULT 0,
                h_beli DECIMAL(15,2) NOT NULL DEFAULT 0,
                harga_tunai DECIMAL(15,2) NOT NULL DEFAULT 0,
                harga_kapitasi DECIMAL(15,2) NOT NULL DEFAULT 0,
                harga_nonkapitasi DECIMAL(15,2) NOT NULL DEFAULT 0,
                jualbebas DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(5) NOT NULL DEFAULT '1'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS resep_obat (
                no_resep VARCHAR(14) PRIMARY KEY,
                tgl_peresepan VARCHAR(15) NOT NULL,
                jam_peresepan VARCHAR(10) NOT NULL,
                no_rawat VARCHAR(20) NOT NULL,
                kd_dokter VARCHAR(20) NOT NULL,
                tgl_perawatan VARCHAR(15) NOT NULL DEFAULT '0000-00-00',
                jam VARCHAR(10) NOT NULL DEFAULT '00:00:00',
                status VARCHAR(15) NOT NULL DEFAULT 'ralan'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS resep_dokter_nonracikan (
                no_resep VARCHAR(14) NOT NULL,
                kode_brng VARCHAR(20) NOT NULL,
                jml DECIMAL(15,2) NOT NULL,
                aturan_pakai VARCHAR(150) NOT NULL DEFAULT '-',
                PRIMARY KEY (no_resep, kode_brng)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS resep_dokter_racikan (
                no_resep VARCHAR(14) NOT NULL,
                no_racik VARCHAR(10) NOT NULL,
                nama_racik VARCHAR(150) NOT NULL,
                kd_racik VARCHAR(10) NOT NULL DEFAULT 'R01',
                jml_dr INT NOT NULL DEFAULT 1,
                aturan_pakai VARCHAR(150) NOT NULL DEFAULT '-',
                keterangan VARCHAR(200) NOT NULL DEFAULT '-',
                PRIMARY KEY (no_resep, no_racik)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS resep_dokter_racikan_detail (
                no_resep VARCHAR(14) NOT NULL,
                no_racik VARCHAR(10) NOT NULL,
                kode_brng VARCHAR(20) NOT NULL,
                p1 DECIMAL(10,2) NOT NULL DEFAULT 1,
                p2 DECIMAL(10,2) NOT NULL DEFAULT 1,
                kandungan VARCHAR(50) NOT NULL DEFAULT '-',
                jml DECIMAL(15,2) NOT NULL,
                PRIMARY KEY (no_resep, no_racik, kode_brng)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS obat_racikan (
                tgl_perawatan VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                no_rawat VARCHAR(20) NOT NULL,
                no_racik VARCHAR(10) NOT NULL,
                nama_racik VARCHAR(150) NOT NULL,
                kd_racik VARCHAR(10) NOT NULL DEFAULT 'R01',
                jml_dr INT NOT NULL DEFAULT 1,
                aturan_pakai VARCHAR(150) NOT NULL DEFAULT '-',
                keterangan VARCHAR(200) NOT NULL DEFAULT '-',
                PRIMARY KEY (tgl_perawatan, jam, no_rawat, no_racik)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS detail_pemberian_obat (
                tgl_perawatan VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                no_rawat VARCHAR(20) NOT NULL,
                kode_brng VARCHAR(20) NOT NULL,
                no_batch VARCHAR(30) NOT NULL DEFAULT '',
                no_faktur VARCHAR(30) NOT NULL DEFAULT '',
                h_beli DECIMAL(15,2) NOT NULL DEFAULT 0,
                biaya_obat DECIMAL(15,2) NOT NULL DEFAULT 0,
                jml DECIMAL(15,2) NOT NULL,
                embalase DECIMAL(15,2) NOT NULL DEFAULT 0,
                tuslah DECIMAL(15,2) NOT NULL DEFAULT 0,
                total DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(15) NOT NULL DEFAULT 'Ralan',
                kd_bangsal VARCHAR(10) NOT NULL,
                jenis_klaim VARCHAR(20) NOT NULL DEFAULT 'Tunai',
                PRIMARY KEY (tgl_perawatan, jam, no_rawat, kode_brng, no_batch, no_faktur)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS gudangbarang (
                kode_brng VARCHAR(20) NOT NULL,
                kd_bangsal VARCHAR(10) NOT NULL,
                no_batch VARCHAR(30) NOT NULL DEFAULT '',
                no_faktur VARCHAR(30) NOT NULL DEFAULT '',
                stok DECIMAL(15,2) NOT NULL DEFAULT 0,
                PRIMARY KEY (kode_brng, kd_bangsal, no_batch, no_faktur)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS riwayat_barang_medis (
                id {$bigAutoIncPk},
                kode_brng VARCHAR(20) NOT NULL,
                stok_awal DECIMAL(15,2) NOT NULL,
                masuk DECIMAL(15,2) NOT NULL DEFAULT 0,
                keluar DECIMAL(15,2) NOT NULL DEFAULT 0,
                stok_akhir DECIMAL(15,2) NOT NULL,
                posisi VARCHAR(50) NOT NULL,
                tanggal VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                petugas VARCHAR(50) NOT NULL,
                kd_bangsal VARCHAR(10) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Simpan',
                no_batch VARCHAR(30) NOT NULL DEFAULT '',
                no_faktur VARCHAR(30) NOT NULL DEFAULT '',
                keterangan VARCHAR(255) NOT NULL DEFAULT ''
            ){$suffix}",

            // 7. Kasir, Billing Ledger, Akuntansi & Rawat Inap (Fase 4)
            "CREATE TABLE IF NOT EXISTS mlite_billing_ledger (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                item_code VARCHAR(30) NOT NULL,
                item_name VARCHAR(150) NOT NULL,
                category VARCHAR(30) NOT NULL,
                qty DECIMAL(15,2) NOT NULL DEFAULT 1,
                unit_price DECIMAL(15,2) NOT NULL DEFAULT 0,
                total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
                claim_type VARCHAR(20) NOT NULL DEFAULT 'Tunai',
                status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                provider_id VARCHAR(30) NOT NULL DEFAULT '',
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_rekening (
                kd_rek VARCHAR(20) PRIMARY KEY,
                nm_rek VARCHAR(100) NOT NULL,
                tipe VARCHAR(30) NOT NULL DEFAULT 'Aset',
                balance VARCHAR(10) NOT NULL DEFAULT 'D'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_jurnal (
                no_jurnal VARCHAR(30) PRIMARY KEY,
                tgl_jurnal VARCHAR(15) NOT NULL,
                jam_jurnal VARCHAR(10) NOT NULL,
                no_bukti VARCHAR(50) NOT NULL,
                keterangan VARCHAR(255) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_jurnal_detail (
                id {$bigAutoIncPk},
                no_jurnal VARCHAR(30) NOT NULL,
                kd_rek VARCHAR(20) NOT NULL,
                debet DECIMAL(15,2) NOT NULL DEFAULT 0,
                kredit DECIMAL(15,2) NOT NULL DEFAULT 0
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS bangsal (
                kd_bangsal VARCHAR(10) PRIMARY KEY,
                nm_bangsal VARCHAR(100) NOT NULL,
                status VARCHAR(5) NOT NULL DEFAULT '1'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS kamar (
                kd_kamar VARCHAR(20) PRIMARY KEY,
                kd_bangsal VARCHAR(10) NOT NULL,
                trf_kamar DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'KOSONG',
                kelas VARCHAR(30) NOT NULL DEFAULT 'Kelas 3',
                statusdata VARCHAR(5) NOT NULL DEFAULT '1'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS kamar_inap (
                no_rawat VARCHAR(20) NOT NULL,
                kd_kamar VARCHAR(20) NOT NULL,
                tgl_masuk VARCHAR(15) NOT NULL,
                jam_masuk VARCHAR(10) NOT NULL,
                tgl_keluar VARCHAR(15) NOT NULL DEFAULT '0000-00-00',
                jam_keluar VARCHAR(10) NOT NULL DEFAULT '00:00:00',
                lama INT NOT NULL DEFAULT 1,
                trf_kamar DECIMAL(15,2) NOT NULL DEFAULT 0,
                ttl_biaya DECIMAL(15,2) NOT NULL DEFAULT 0,
                stts_pulang VARCHAR(50) NOT NULL DEFAULT '-',
                PRIMARY KEY (no_rawat, kd_kamar, tgl_masuk, jam_masuk)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_penjualan_apotek_umum (
                no_nota VARCHAR(30) PRIMARY KEY,
                tgl_jual VARCHAR(15) NOT NULL,
                jam_jual VARCHAR(10) NOT NULL,
                payment_method VARCHAR(30) NOT NULL,
                total DECIMAL(15,2) NOT NULL DEFAULT 0,
                petugas_nip VARCHAR(30) NOT NULL,
                keterangan VARCHAR(255) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_penjualan_apotek_umum_detail (
                id {$bigAutoIncPk},
                no_nota VARCHAR(30) NOT NULL,
                kode_brng VARCHAR(20) NOT NULL,
                jml DECIMAL(15,2) NOT NULL,
                harga_jual DECIMAL(15,2) NOT NULL,
                subtotal DECIMAL(15,2) NOT NULL
            ){$suffix}",

            // 8. Laboratorium & Radiologi (Fase 5)
            "CREATE TABLE IF NOT EXISTS permintaan_lab (
                noorder VARCHAR(20) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                tgl_permintaan VARCHAR(15) NOT NULL,
                jam_permintaan VARCHAR(10) NOT NULL,
                tgl_sampel VARCHAR(15) NOT NULL DEFAULT '0000-00-00',
                jam_sampel VARCHAR(10) NOT NULL DEFAULT '00:00:00',
                tgl_hasil VARCHAR(15) NOT NULL DEFAULT '0000-00-00',
                jam_hasil VARCHAR(10) NOT NULL DEFAULT '00:00:00',
                dokter_perujuk VARCHAR(20) NOT NULL,
                status VARCHAR(15) NOT NULL DEFAULT 'ralan',
                jenis_klaim VARCHAR(20) NOT NULL DEFAULT 'Tunai'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS permintaan_pemeriksaan_lab (
                noorder VARCHAR(20) NOT NULL,
                kd_jenis_prw VARCHAR(20) NOT NULL,
                stts_bayar VARCHAR(15) NOT NULL DEFAULT 'Belum',
                jenis_klaim VARCHAR(20) NOT NULL DEFAULT 'Tunai',
                PRIMARY KEY (noorder, kd_jenis_prw)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS detail_periksa_lab (
                no_rawat VARCHAR(20) NOT NULL,
                kd_jenis_prw VARCHAR(20) NOT NULL,
                tgl_periksa VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                pemeriksaan VARCHAR(100) NOT NULL,
                nilai VARCHAR(50) NOT NULL,
                nilai_rujukan VARCHAR(50) NOT NULL DEFAULT '-',
                keterangan VARCHAR(30) NOT NULL DEFAULT 'Normal',
                PRIMARY KEY (no_rawat, kd_jenis_prw, tgl_periksa, jam, pemeriksaan)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS template_laboratorium (
                id {$bigAutoIncPk},
                kd_jenis_prw VARCHAR(20) NOT NULL,
                pemeriksaan VARCHAR(120) NOT NULL,
                satuan VARCHAR(30) NOT NULL DEFAULT '',
                nilai_rujukan_ld VARCHAR(50) NOT NULL DEFAULT '-',
                nilai_rujukan_la VARCHAR(50) NOT NULL DEFAULT '-',
                nilai_rujukan_pd VARCHAR(50) NOT NULL DEFAULT '-',
                nilai_rujukan_pa VARCHAR(50) NOT NULL DEFAULT '-',
                biaya_item DECIMAL(15,2) NOT NULL DEFAULT 0
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS gambar_radiologi (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                tgl_periksa VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                lokasi_gambar VARCHAR(255) NOT NULL DEFAULT '',
                keterangan VARCHAR(150) NOT NULL DEFAULT '',
                uploaded_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS permintaan_radiologi (
                noorder VARCHAR(20) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                tgl_permintaan VARCHAR(15) NOT NULL,
                jam_permintaan VARCHAR(10) NOT NULL,
                tgl_hasil VARCHAR(15) NOT NULL DEFAULT '0000-00-00',
                jam_hasil VARCHAR(10) NOT NULL DEFAULT '00:00:00',
                dokter_perujuk VARCHAR(20) NOT NULL,
                status VARCHAR(15) NOT NULL DEFAULT 'ralan',
                jenis_klaim VARCHAR(20) NOT NULL DEFAULT 'Tunai'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS permintaan_pemeriksaan_radiologi (
                noorder VARCHAR(20) NOT NULL,
                kd_jenis_prw VARCHAR(20) NOT NULL,
                stts_bayar VARCHAR(15) NOT NULL DEFAULT 'Belum',
                jenis_klaim VARCHAR(20) NOT NULL DEFAULT 'Tunai',
                PRIMARY KEY (noorder, kd_jenis_prw)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS hasil_radiologi (
                no_rawat VARCHAR(20) NOT NULL,
                tgl_periksa VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                hasil TEXT NOT NULL,
                PRIMARY KEY (no_rawat, tgl_periksa, jam)
            ){$suffix}",

            // 9. Bridging BPJS, SatuSehat FHIR & Vedika (Fase 6)
            "CREATE TABLE IF NOT EXISTS bridging_sync_queue (
                id {$bigAutoIncPk},
                provider VARCHAR(30) NOT NULL,
                resource_type VARCHAR(50) NOT NULL,
                no_rawat VARCHAR(20) NOT NULL,
                endpoint VARCHAR(255) NOT NULL,
                payload_json TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                attempts INT NOT NULL DEFAULT 0,
                last_error TEXT,
                external_id VARCHAR(100) NOT NULL DEFAULT '',
                created_at VARCHAR(30) NOT NULL,
                updated_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_vedika (
                id {$autoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                no_sep VARCHAR(40) NOT NULL DEFAULT '',
                jenis_pelayanan VARCHAR(15) NOT NULL DEFAULT 'Ralan',
                status VARCHAR(30) NOT NULL DEFAULT 'Pengajuan',
                catatan TEXT,
                verified_by VARCHAR(50) NOT NULL DEFAULT '',
                verified_at VARCHAR(30) NOT NULL DEFAULT '',
                UNIQUE (no_rawat)
            ){$suffix}",

            // 10. WhatsApp Gateway CRM & Home Care Preceptorship (Fase 7)
            "CREATE TABLE IF NOT EXISTS wagateway_whitelist (
                no_hp VARCHAR(30) PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                kategori VARCHAR(50) NOT NULL DEFAULT 'Pegawai',
                status VARCHAR(10) NOT NULL DEFAULT 'Aktif'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS wagateway_outbox (
                id {$bigAutoIncPk},
                session_name VARCHAR(20) NOT NULL,
                no_hp VARCHAR(30) NOT NULL,
                pesan TEXT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                error_message TEXT,
                created_at VARCHAR(30) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS wagateway_checkin (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                sender_phone VARCHAR(30) NOT NULL,
                session_name VARCHAR(20) NOT NULL,
                verified_at VARCHAR(30) NOT NULL
            ){$suffix}",

            // 11. Tindakan Dokter Ralan & Resume Medis (Poli Deepening)
            "CREATE TABLE IF NOT EXISTS rawat_jl_dr (
                no_rawat VARCHAR(20) NOT NULL,
                kd_jenis_prw VARCHAR(20) NOT NULL,
                kd_dokter VARCHAR(20) NOT NULL,
                tgl_perawatan VARCHAR(15) NOT NULL,
                jam_rawat VARCHAR(10) NOT NULL,
                material DECIMAL(15,2) NOT NULL DEFAULT 0,
                tarif_tindakandr DECIMAL(15,2) NOT NULL DEFAULT 0,
                biaya_rawat DECIMAL(15,2) NOT NULL DEFAULT 0,
                stts_bayar VARCHAR(15) NOT NULL DEFAULT 'Belum',
                jenis_klaim VARCHAR(20) NOT NULL DEFAULT 'Tunai',
                PRIMARY KEY (no_rawat, kd_jenis_prw, kd_dokter, tgl_perawatan, jam_rawat)
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS resume_pasien (
                no_rawat VARCHAR(20) PRIMARY KEY,
                kd_dokter VARCHAR(20) NOT NULL,
                keluhan_utama TEXT NOT NULL,
                jalannya_penyakit TEXT,
                pemeriksaan_penunjang TEXT,
                hasil_laborat TEXT,
                diagnosa_utama VARCHAR(200) NOT NULL DEFAULT '-',
                kd_diagnosa_utama VARCHAR(20) NOT NULL DEFAULT '-',
                kondisi_pulang VARCHAR(50) NOT NULL DEFAULT 'Membaik',
                obat_pulang TEXT
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS data_triase_igd (
                no_rawat VARCHAR(20) PRIMARY KEY,
                tgl_kunjungan VARCHAR(15) NOT NULL,
                jam_kunjungan VARCHAR(15) NOT NULL,
                cara_masuk VARCHAR(30) NOT NULL DEFAULT 'Jalan',
                zona_triase VARCHAR(20) NOT NULL DEFAULT 'Hijau',
                keluhan_utama TEXT NOT NULL,
                tensi VARCHAR(20) NOT NULL DEFAULT '',
                nadi VARCHAR(10) NOT NULL DEFAULT '',
                suhu VARCHAR(10) NOT NULL DEFAULT '',
                respirasi VARCHAR(10) NOT NULL DEFAULT '',
                spo2 VARCHAR(10) NOT NULL DEFAULT '',
                gcs VARCHAR(15) NOT NULL DEFAULT '15',
                disposisi VARCHAR(30) NOT NULL DEFAULT 'Observasi',
                catatan TEXT
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_inventaris (
                kode_aset VARCHAR(30) PRIMARY KEY,
                nama_aset VARCHAR(150) NOT NULL,
                kategori VARCHAR(50) NOT NULL DEFAULT 'Medis',
                lokasi VARCHAR(100) NOT NULL DEFAULT '',
                tgl_perolehan VARCHAR(15) NOT NULL DEFAULT '',
                nilai_perolehan DECIMAL(15,2) NOT NULL DEFAULT 0,
                kondisi VARCHAR(30) NOT NULL DEFAULT 'Baik',
                status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_api_keys (
                id {$bigAutoIncPk},
                label VARCHAR(100) NOT NULL,
                username VARCHAR(50) NOT NULL DEFAULT '',
                api_key_hash VARCHAR(64) NOT NULL,
                key_prefix VARCHAR(20) NOT NULL,
                methods VARCHAR(100) NOT NULL DEFAULT 'GET',
                ip_range VARCHAR(100) NOT NULL DEFAULT '*',
                exp_time VARCHAR(25) NOT NULL DEFAULT '',
                is_active INT NOT NULL DEFAULT 1,
                created_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_modules (
                dir VARCHAR(60) PRIMARY KEY,
                sequence INT NOT NULL DEFAULT 0,
                is_active INT NOT NULL DEFAULT 1,
                is_core INT NOT NULL DEFAULT 0,
                updated_at VARCHAR(25) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_pengumuman (
                id {$bigAutoIncPk},
                judul VARCHAR(150) NOT NULL,
                isi TEXT NOT NULL,
                prioritas VARCHAR(20) NOT NULL DEFAULT 'Info',
                penulis VARCHAR(60) NOT NULL DEFAULT 'Admin',
                is_active INT NOT NULL DEFAULT 1,
                created_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_penjualan_barang (
                id {$bigAutoIncPk},
                nama_barang VARCHAR(150) NOT NULL,
                kode_satuan VARCHAR(20) NOT NULL DEFAULT 'Pcs',
                stok INT NOT NULL DEFAULT 0,
                harga DECIMAL(15,2) NOT NULL DEFAULT 0,
                keterangan VARCHAR(255) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_penjualan (
                id {$bigAutoIncPk},
                nama_pembeli VARCHAR(120) NOT NULL DEFAULT 'Umum',
                alamat_pembeli VARCHAR(255) NOT NULL DEFAULT '',
                nomor_telepon VARCHAR(30) NOT NULL DEFAULT '',
                tanggal VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                id_user VARCHAR(50) NOT NULL DEFAULT 'system',
                keterangan VARCHAR(255) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'Draft'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_penjualan_detail (
                id {$bigAutoIncPk},
                id_penjualan INT NOT NULL,
                id_barang INT NOT NULL,
                nama_barang VARCHAR(150) NOT NULL,
                harga DECIMAL(15,2) NOT NULL DEFAULT 0,
                jumlah INT NOT NULL DEFAULT 1,
                harga_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                tanggal VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                id_user VARCHAR(50) NOT NULL DEFAULT 'system'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_penjualan_billing (
                id {$bigAutoIncPk},
                id_penjualan INT NOT NULL,
                jumlah_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                potongan DECIMAL(15,2) NOT NULL DEFAULT 0,
                jumlah_harus_bayar DECIMAL(15,2) NOT NULL DEFAULT 0,
                jumlah_bayar DECIMAL(15,2) NOT NULL DEFAULT 0,
                cara_bayar VARCHAR(30) NOT NULL DEFAULT 'Tunai',
                tanggal VARCHAR(15) NOT NULL,
                jam VARCHAR(10) NOT NULL,
                id_user VARCHAR(50) NOT NULL DEFAULT 'system'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS paket_operasi (
                kode_paket VARCHAR(20) PRIMARY KEY,
                nm_perawatan VARCHAR(150) NOT NULL,
                kategori VARCHAR(40) NOT NULL DEFAULT 'Bedah Minor',
                tarif_total DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(15) NOT NULL DEFAULT 'Aktif'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS booking_operasi (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                kode_paket VARCHAR(20) NOT NULL,
                tanggal VARCHAR(15) NOT NULL,
                jam_mulai VARCHAR(10) NOT NULL DEFAULT '08:00',
                jam_selesai VARCHAR(10) NOT NULL DEFAULT '09:00',
                status VARCHAR(25) NOT NULL DEFAULT 'Menunggu',
                kd_dokter VARCHAR(20) NOT NULL DEFAULT '',
                kd_ruang_ok VARCHAR(20) NOT NULL DEFAULT 'OK-1'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS laporan_operasi (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                kode_paket VARCHAR(20) NOT NULL DEFAULT '',
                tanggal VARCHAR(20) NOT NULL,
                diagnosa_preop VARCHAR(200) NOT NULL DEFAULT '',
                diagnosa_postop VARCHAR(200) NOT NULL DEFAULT '',
                jaringan_dieksekusi VARCHAR(150) NOT NULL DEFAULT '',
                permintaan_pa VARCHAR(10) NOT NULL DEFAULT 'Tidak',
                operator1 VARCHAR(50) NOT NULL DEFAULT '',
                asisten_operator1 VARCHAR(50) NOT NULL DEFAULT '',
                dokter_anestesi VARCHAR(50) NOT NULL DEFAULT '',
                laporan_operasi TEXT NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_followup_operatif (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                kd_jenis_prw VARCHAR(20) NOT NULL,
                tgl_order VARCHAR(15) NOT NULL,
                tgl_tindakan VARCHAR(15) NOT NULL,
                diagnosis VARCHAR(200) NOT NULL DEFAULT '-',
                laporan_operasi TEXT NOT NULL,
                catatan TEXT NOT NULL,
                kd_dokter VARCHAR(20) NOT NULL DEFAULT '-',
                kd_paramedis1 VARCHAR(20) NOT NULL DEFAULT '',
                kd_paramedis2 VARCHAR(20) NOT NULL DEFAULT '',
                nm_petugas VARCHAR(80) NOT NULL DEFAULT 'Sistem',
                tgl_approve VARCHAR(25) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'Menunggu'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS utd_komponen_darah (
                kode VARCHAR(15) PRIMARY KEY,
                nama VARCHAR(100) NOT NULL,
                lama INT NOT NULL DEFAULT 35,
                total DECIMAL(15,2) NOT NULL DEFAULT 0
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS utd_pendonor (
                no_pendonor VARCHAR(20) PRIMARY KEY,
                nama VARCHAR(120) NOT NULL,
                no_ktp VARCHAR(25) NOT NULL DEFAULT '',
                jk VARCHAR(5) NOT NULL DEFAULT 'L',
                golongan_darah VARCHAR(5) NOT NULL DEFAULT 'O',
                resus VARCHAR(5) NOT NULL DEFAULT '+',
                no_telp VARCHAR(30) NOT NULL DEFAULT '',
                alamat VARCHAR(200) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS utd_stok_darah (
                no_kantong VARCHAR(25) PRIMARY KEY,
                kode_komponen VARCHAR(15) NOT NULL,
                golongan_darah VARCHAR(5) NOT NULL,
                resus VARCHAR(5) NOT NULL DEFAULT '+',
                tanggal_aftap VARCHAR(15) NOT NULL,
                tanggal_kadaluarsa VARCHAR(15) NOT NULL,
                asal_darah VARCHAR(80) NOT NULL DEFAULT 'PMI',
                status VARCHAR(20) NOT NULL DEFAULT 'Ada'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS utd_permintaan_darah (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                no_kantong VARCHAR(25) NOT NULL DEFAULT '',
                golongan_darah VARCHAR(5) NOT NULL,
                hasil_crossmatch VARCHAR(25) NOT NULL DEFAULT 'Menunggu',
                petugas VARCHAR(60) NOT NULL DEFAULT 'Sistem',
                tanggal VARCHAR(15) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'Menunggu'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_veronisa (
                id {$bigAutoIncPk},
                tanggal VARCHAR(15) NOT NULL,
                no_rkm_medis VARCHAR(20) NOT NULL DEFAULT '',
                no_rawat VARCHAR(20) NOT NULL,
                nosep VARCHAR(40) NOT NULL DEFAULT '',
                status VARCHAR(25) NOT NULL DEFAULT 'Pengajuan',
                catatan TEXT NOT NULL,
                username VARCHAR(50) NOT NULL DEFAULT 'system'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_veronisa_obat_mapping (
                kode_brng VARCHAR(20) PRIMARY KEY,
                kode_dpho VARCHAR(30) NOT NULL,
                nama_dpho VARCHAR(150) NOT NULL DEFAULT '',
                harga_dpho DECIMAL(15,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'Aktif'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_chat_conversations (
                id {$bigAutoIncPk},
                title VARCHAR(150) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'direct',
                created_by VARCHAR(50) NOT NULL DEFAULT 'system',
                created_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_chat_messages (
                id {$bigAutoIncPk},
                conversation_id INT NOT NULL,
                sender_username VARCHAR(50) NOT NULL,
                sender_name VARCHAR(100) NOT NULL DEFAULT '',
                message TEXT NOT NULL,
                is_read INT NOT NULL DEFAULT 0,
                created_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_chat_quick_responses (
                id {$bigAutoIncPk},
                shortcut VARCHAR(40) NOT NULL,
                content TEXT NOT NULL,
                created_by VARCHAR(50) NOT NULL DEFAULT 'system'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS bridging_orthanc (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                study_uid VARCHAR(120) NOT NULL,
                series_uid VARCHAR(120) NOT NULL DEFAULT '',
                modality VARCHAR(20) NOT NULL DEFAULT 'CR',
                study_description VARCHAR(200) NOT NULL DEFAULT '',
                patient_name VARCHAR(120) NOT NULL DEFAULT '',
                tgl_periksa VARCHAR(15) NOT NULL,
                hasil_baca TEXT NOT NULL,
                dokter_radiologi VARCHAR(80) NOT NULL DEFAULT '',
                viewer_url VARCHAR(255) NOT NULL DEFAULT '',
                created_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_smartagent_knowledge (
                id {$bigAutoIncPk},
                title VARCHAR(150) NOT NULL,
                category VARCHAR(30) NOT NULL DEFAULT 'PDT',
                icd10_code VARCHAR(15) NOT NULL DEFAULT '',
                keywords VARCHAR(255) NOT NULL DEFAULT '',
                content TEXT NOT NULL,
                created_by VARCHAR(50) NOT NULL DEFAULT 'system',
                updated_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_smartagent_profiles (
                id {$bigAutoIncPk},
                name VARCHAR(100) NOT NULL,
                provider VARCHAR(30) NOT NULL DEFAULT 'vllm-local',
                endpoint_url VARCHAR(255) NOT NULL DEFAULT 'http://127.0.0.1:8000/v1',
                model_name VARCHAR(120) NOT NULL DEFAULT 'Qwen/Qwen2.5-7B-Instruct',
                is_active INT NOT NULL DEFAULT 1
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_sdm_berkas (
                id {$bigAutoIncPk},
                nik VARCHAR(30) NOT NULL,
                jenis_berkas VARCHAR(40) NOT NULL DEFAULT 'STR',
                nama_file VARCHAR(150) NOT NULL,
                mime_type VARCHAR(80) NOT NULL DEFAULT 'application/pdf',
                file_size INT NOT NULL DEFAULT 0,
                storage_path VARCHAR(255) NOT NULL DEFAULT '',
                tgl_kadaluarsa VARCHAR(15) NOT NULL DEFAULT '',
                uploaded_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS berkas_digital_perawatan (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                kode VARCHAR(30) NOT NULL DEFAULT 'BERKAS',
                nama_file VARCHAR(150) NOT NULL,
                mime_type VARCHAR(80) NOT NULL DEFAULT 'application/pdf',
                lokasi_file VARCHAR(255) NOT NULL DEFAULT '',
                uploaded_at VARCHAR(25) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS bridging_sep (
                no_sep VARCHAR(40) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL,
                tglsep VARCHAR(15) NOT NULL,
                no_kartu VARCHAR(30) NOT NULL,
                nama_pasien VARCHAR(120) NOT NULL DEFAULT '',
                kdpolitujuan VARCHAR(15) NOT NULL DEFAULT '',
                nmpolitujuan VARCHAR(100) NOT NULL DEFAULT '',
                diagawal VARCHAR(15) NOT NULL DEFAULT '',
                nmdiagnosaawal VARCHAR(150) NOT NULL DEFAULT '',
                jnspelayanan VARCHAR(5) NOT NULL DEFAULT '2',
                klsrawat VARCHAR(5) NOT NULL DEFAULT '3',
                catatan VARCHAR(200) NOT NULL DEFAULT '',
                user_input VARCHAR(50) NOT NULL DEFAULT 'system'
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS bridging_rujukan_bpjs (
                no_rujukan VARCHAR(40) PRIMARY KEY,
                no_sep VARCHAR(40) NOT NULL,
                tgl_rujukan VARCHAR(15) NOT NULL,
                ppk_dirujuk VARCHAR(25) NOT NULL DEFAULT '',
                nm_ppk_dirujuk VARCHAR(150) NOT NULL DEFAULT '',
                jns_pelayanan VARCHAR(5) NOT NULL DEFAULT '2',
                diag_rujukan VARCHAR(15) NOT NULL DEFAULT '',
                tipe_rujukan VARCHAR(5) NOT NULL DEFAULT '0',
                catatan VARCHAR(200) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS bridging_surat_kontrol_bpjs (
                no_surat VARCHAR(40) PRIMARY KEY,
                no_sep VARCHAR(40) NOT NULL,
                tgl_surat VARCHAR(15) NOT NULL,
                tgl_rencana VARCHAR(15) NOT NULL,
                kd_dokter_bpjs VARCHAR(20) NOT NULL DEFAULT '',
                nm_dokter_bpjs VARCHAR(120) NOT NULL DEFAULT '',
                kd_poli_bpjs VARCHAR(15) NOT NULL DEFAULT '',
                nm_poli_bpjs VARCHAR(100) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS pcare_pendaftaran (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL DEFAULT '',
                tglDaftar VARCHAR(15) NOT NULL,
                noKartu VARCHAR(25) NOT NULL,
                nmPasien VARCHAR(100) NOT NULL DEFAULT '',
                kdPoli VARCHAR(15) NOT NULL DEFAULT '',
                nmPoli VARCHAR(80) NOT NULL DEFAULT '',
                keluhan VARCHAR(255) NOT NULL DEFAULT '',
                kdSadar VARCHAR(10) NOT NULL DEFAULT '01',
                sistole INTEGER NOT NULL DEFAULT 120,
                diastole INTEGER NOT NULL DEFAULT 80,
                beratBadan REAL NOT NULL DEFAULT 60,
                tinggiBadan REAL NOT NULL DEFAULT 165,
                respRate INTEGER NOT NULL DEFAULT 20,
                heartRate INTEGER NOT NULL DEFAULT 80,
                noUrut VARCHAR(15) NOT NULL DEFAULT '',
                status VARCHAR(30) NOT NULL DEFAULT 'Terkirim',
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS pcare_kunjungan (
                noKunjungan VARCHAR(40) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL DEFAULT '',
                tglDaftar VARCHAR(15) NOT NULL,
                noKartu VARCHAR(25) NOT NULL,
                nmPasien VARCHAR(100) NOT NULL DEFAULT '',
                kdPoli VARCHAR(15) NOT NULL DEFAULT '',
                nmPoli VARCHAR(80) NOT NULL DEFAULT '',
                keluhan VARCHAR(255) NOT NULL DEFAULT '',
                kdDiagnosa1 VARCHAR(15) NOT NULL DEFAULT '',
                nmDiagnosa1 VARCHAR(150) NOT NULL DEFAULT '',
                kdStatusPulang VARCHAR(10) NOT NULL DEFAULT '3',
                nmStatusPulang VARCHAR(80) NOT NULL DEFAULT 'Berobat Jalan',
                tglPulang VARCHAR(15) NOT NULL DEFAULT '',
                kdDokter VARCHAR(25) NOT NULL DEFAULT '',
                nmDokter VARCHAR(100) NOT NULL DEFAULT '',
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS pcare_rujukan (
                noKunjungan VARCHAR(40) PRIMARY KEY,
                no_rawat VARCHAR(20) NOT NULL DEFAULT '',
                tglEstRujuk VARCHAR(15) NOT NULL,
                kdppk VARCHAR(20) NOT NULL DEFAULT '',
                nmppk VARCHAR(120) NOT NULL DEFAULT '',
                kdSubSpesialis VARCHAR(20) NOT NULL DEFAULT '',
                nmSubSpesialis VARCHAR(100) NOT NULL DEFAULT '',
                catatan VARCHAR(255) NOT NULL DEFAULT '',
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS pcare_obat (
                id {$bigAutoIncPk},
                noKunjungan VARCHAR(40) NOT NULL,
                kdObatSK VARCHAR(30) NOT NULL DEFAULT '',
                kdObat VARCHAR(30) NOT NULL DEFAULT '',
                nmObat VARCHAR(120) NOT NULL DEFAULT '',
                signa1 INTEGER NOT NULL DEFAULT 3,
                signa2 INTEGER NOT NULL DEFAULT 1,
                jmlObat REAL NOT NULL DEFAULT 1,
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS pcare_tindakan (
                id {$bigAutoIncPk},
                noKunjungan VARCHAR(40) NOT NULL,
                kdTindakanSK VARCHAR(30) NOT NULL DEFAULT '',
                kdTindakan VARCHAR(30) NOT NULL DEFAULT '',
                nmTindakan VARCHAR(150) NOT NULL DEFAULT '',
                biaya REAL NOT NULL DEFAULT 0,
                keterangan VARCHAR(200) NOT NULL DEFAULT '',
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_hfis_jadwal (
                id {$bigAutoIncPk},
                kd_poli_bpjs VARCHAR(15) NOT NULL,
                nm_poli_bpjs VARCHAR(100) NOT NULL DEFAULT '',
                kd_dokter_bpjs VARCHAR(25) NOT NULL,
                nm_dokter_bpjs VARCHAR(120) NOT NULL DEFAULT '',
                hari INTEGER NOT NULL DEFAULT 1,
                nama_hari VARCHAR(15) NOT NULL DEFAULT 'SENIN',
                jadwal VARCHAR(25) NOT NULL DEFAULT '08:00-12:00',
                kapasitas_pasien INTEGER NOT NULL DEFAULT 20,
                libur INTEGER NOT NULL DEFAULT 0,
                synced_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_antrol_log (
                id {$bigAutoIncPk},
                kodebooking VARCHAR(40) NOT NULL,
                no_rawat VARCHAR(25) NOT NULL DEFAULT '',
                task_id INTEGER NOT NULL,
                waktu_ms BIGINT NOT NULL DEFAULT 0,
                status_sync VARCHAR(20) NOT NULL DEFAULT 'sent',
                response_code VARCHAR(10) NOT NULL DEFAULT '200',
                response_msg VARCHAR(200) NOT NULL DEFAULT 'Ok',
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS referensi_mobilejkn_bpjs (
                nobooking VARCHAR(40) PRIMARY KEY,
                no_rawat VARCHAR(25) NOT NULL DEFAULT '',
                nomorkartu VARCHAR(25) NOT NULL DEFAULT '',
                nik VARCHAR(25) NOT NULL DEFAULT '',
                nohp VARCHAR(25) NOT NULL DEFAULT '',
                kodepoli VARCHAR(15) NOT NULL DEFAULT '',
                pasienbaru INTEGER NOT NULL DEFAULT 0,
                norm VARCHAR(20) NOT NULL DEFAULT '',
                tanggalperiksa VARCHAR(15) NOT NULL,
                kodedokter VARCHAR(25) NOT NULL DEFAULT '',
                jampraktek VARCHAR(25) NOT NULL DEFAULT '',
                jeniskunjungan INTEGER NOT NULL DEFAULT 1,
                nomorreferensi VARCHAR(45) NOT NULL DEFAULT '',
                nomorantrean VARCHAR(20) NOT NULL DEFAULT '',
                angkaantrean INTEGER NOT NULL DEFAULT 1,
                estimasidilayani BIGINT NOT NULL DEFAULT 0,
                sisakuotajkn INTEGER NOT NULL DEFAULT 20,
                kuotajkn INTEGER NOT NULL DEFAULT 20,
                sisakuotanonjkn INTEGER NOT NULL DEFAULT 10,
                kuotanonjkn INTEGER NOT NULL DEFAULT 10,
                status VARCHAR(20) NOT NULL DEFAULT 'Belum',
                validasi VARCHAR(30) NOT NULL DEFAULT '',
                keterangan_batal VARCHAR(200) NOT NULL DEFAULT '',
                antrean_farmasi INTEGER NOT NULL DEFAULT 0,
                jenisresep VARCHAR(30) NOT NULL DEFAULT '',
                created_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_satusehat_mapping (
                id {$bigAutoIncPk},
                entity_type VARCHAR(30) NOT NULL,
                local_code VARCHAR(40) NOT NULL,
                local_name VARCHAR(120) NOT NULL DEFAULT '',
                ihs_id VARCHAR(100) NOT NULL,
                updated_at VARCHAR(30) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_presensi (
                id {$bigAutoIncPk},
                nip VARCHAR(30) NOT NULL,
                tgl_presensi VARCHAR(15) NOT NULL,
                jam_masuk VARCHAR(10) NOT NULL,
                jam_pulang VARCHAR(10) NOT NULL DEFAULT '',
                shift VARCHAR(20) NOT NULL DEFAULT 'Pagi',
                status VARCHAR(20) NOT NULL DEFAULT 'Hadir',
                lokasi VARCHAR(100) NOT NULL DEFAULT 'Klinik',
                keterangan VARCHAR(255) NOT NULL DEFAULT ''
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_homecare_kunjungan (
                id {$bigAutoIncPk},
                no_rawat VARCHAR(20) NOT NULL,
                tgl_kunjungan VARCHAR(15) NOT NULL,
                jam_kunjungan VARCHAR(10) NOT NULL,
                kd_dokter_dpjp VARCHAR(20) NOT NULL,
                preceptor_nip VARCHAR(20) NOT NULL DEFAULT '',
                mahasiswa_nim VARCHAR(30) NOT NULL DEFAULT '',
                supervisor_id VARCHAR(50) NOT NULL DEFAULT '',
                diagnosis TEXT,
                status_layanan VARCHAR(30) NOT NULL DEFAULT 'Terjadwal',
                status_bayar VARCHAR(20) NOT NULL DEFAULT 'Belum',
                payment_gateway VARCHAR(30) NOT NULL DEFAULT 'Kasera',
                created_at VARCHAR(30) NOT NULL
            ){$suffix}",

            "CREATE TABLE IF NOT EXISTS mlite_homecare_preceptor_sk (
                id {$autoIncPk},
                nip VARCHAR(20) NOT NULL,
                nama_nakes VARCHAR(100) NOT NULL,
                institusi VARCHAR(100) NOT NULL,
                no_sk VARCHAR(100) NOT NULL,
                status VARCHAR(10) NOT NULL DEFAULT 'Aktif'
            ){$suffix}",
        ];

        foreach ($statements as $sql) {
            $this->pdo->exec($sql);
        }
    }
}
