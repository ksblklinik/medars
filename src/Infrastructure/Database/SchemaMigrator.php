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
