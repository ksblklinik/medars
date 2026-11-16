-- MySQL dump 10.13  Distrib 8.0.44, for Linux (x86_64)
--
-- Host: localhost    Database: mlite
-- ------------------------------------------------------
-- Server version	8.0.44

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `reg_periksa`
--

DROP TABLE IF EXISTS `reg_periksa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reg_periksa` (
  `no_reg` varchar(8) DEFAULT NULL,
  `prefik` varchar(10) DEFAULT '',
  `room` varchar(50) DEFAULT '',
  `no_rawat` varchar(17) NOT NULL,
  `tgl_registrasi` date DEFAULT NULL,
  `jam_reg` time DEFAULT NULL,
  `shift` varchar(20) DEFAULT NULL,
  `kd_dokter` varchar(20) DEFAULT NULL,
  `no_rkm_medis` varchar(15) DEFAULT NULL,
  `kd_poli` char(5) DEFAULT NULL,
  `p_jawab` varchar(100) DEFAULT NULL,
  `almt_pj` varchar(200) DEFAULT NULL,
  `hubunganpj` varchar(20) DEFAULT NULL,
  `biaya_reg` double DEFAULT NULL,
  `stts` enum('Belum','Sudah','Batal','Berkas Diterima','Dirujuk','Meninggal','Dirawat','Pulang Paksa','Awal','Pending','Skip') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `stts_daftar` enum('-','Lama','Baru') NOT NULL,
  `status_lanjut` enum('Ralan','Ranap') NOT NULL,
  `kd_pj` char(3) NOT NULL,
  `umurdaftar` int DEFAULT NULL,
  `sttsumur` enum('Th','Bl','Hr') DEFAULT NULL,
  `status_bayar` enum('Sudah Bayar','Belum Bayar') NOT NULL,
  `status_poli` enum('Lama','Baru') NOT NULL,
  `jam_input` time DEFAULT NULL,
  `kd_dokter_berhalangan` varchar(20) DEFAULT NULL,
  `nip` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`no_rawat`),
  KEY `no_rkm_medis` (`no_rkm_medis`),
  KEY `kd_poli` (`kd_poli`),
  KEY `kd_pj` (`kd_pj`),
  KEY `status_lanjut` (`status_lanjut`),
  KEY `kd_dokter` (`kd_dokter`),
  KEY `status_bayar` (`status_bayar`) USING BTREE,
  CONSTRAINT `reg_periksa_ibfk_3` FOREIGN KEY (`kd_poli`) REFERENCES `poliklinik` (`kd_poli`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `reg_periksa_ibfk_4` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `reg_periksa_ibfk_6` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE,
  CONSTRAINT `reg_periksa_ibfk_7` FOREIGN KEY (`no_rkm_medis`) REFERENCES `pasien` (`no_rkm_medis`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pasien`
--

DROP TABLE IF EXISTS `pasien`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pasien` (
  `no_rkm_medis` varchar(15) NOT NULL,
  `nm_pasien` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `no_ktp` varchar(20) DEFAULT NULL,
  `no_satu_sehat` varchar(30) DEFAULT NULL,
  `jk` enum('L','P','-') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `tmp_lahir` varchar(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `nm_ibu` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `alamat` varchar(300) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `rt` varchar(5) DEFAULT '-',
  `rw` varchar(5) DEFAULT '-',
  `gol_darah` enum('A','B','O','AB','-') DEFAULT NULL,
  `pekerjaan` varchar(30) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `stts_nikah` enum('-','BELUM MENIKAH','MENIKAH','CERAI HIDUP','CERAI MATI') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `agama` varchar(12) DEFAULT NULL,
  `tgl_daftar` date DEFAULT NULL,
  `no_tlp` varchar(40) DEFAULT NULL,
  `umur` varchar(30) NOT NULL,
  `pnd` enum('-','TS','TK','SD','SMP','SMA','D1','D2','D3','D4','S1','S2','S3') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `keluarga` enum('-','AYAH','IBU','ISTERI','SUAMI','KERABAT','ANAK','WALI PASIEN') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `namakeluarga` varchar(50) NOT NULL,
  `kd_pj` char(3) NOT NULL,
  `no_peserta` varchar(25) DEFAULT NULL,
  `kd_kel` varchar(100) NOT NULL,
  `kd_kec` int NOT NULL,
  `kd_kab` int NOT NULL,
  `pekerjaanpj` varchar(35) NOT NULL,
  `alamatpj` varchar(100) NOT NULL,
  `kelurahanpj` varchar(60) NOT NULL,
  `kecamatanpj` varchar(60) NOT NULL,
  `kabupatenpj` varchar(60) NOT NULL,
  `kd_perusahaan` varchar(8) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `suku_bangsa` int NOT NULL,
  `bahasa_pasien` int NOT NULL,
  `cacat_fisik` int NOT NULL,
  `email` varchar(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '-',
  `nip` varchar(30) NOT NULL,
  `kd_prop` int NOT NULL,
  `propinsipj` varchar(30) NOT NULL,
  `is_nik_valid` tinyint(1) DEFAULT NULL,
  `is_wa_aktif` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`no_rkm_medis`),
  KEY `kd_pj` (`kd_pj`),
  KEY `kd_kec` (`kd_kec`),
  KEY `kd_kab` (`kd_kab`),
  KEY `nm_pasien` (`nm_pasien`),
  KEY `alamat` (`alamat`),
  KEY `kd_kel_2` (`kd_kel`),
  KEY `no_ktp` (`no_ktp`),
  KEY `no_peserta` (`no_peserta`),
  KEY `suku_bangsa` (`suku_bangsa`) USING BTREE,
  KEY `bahasa_pasien` (`bahasa_pasien`) USING BTREE,
  KEY `cacat_fisik` (`cacat_fisik`) USING BTREE,
  KEY `kd_prop` (`kd_prop`) USING BTREE,
  KEY `perusahaan_pasien` (`kd_perusahaan`) USING BTREE,
  CONSTRAINT `pasien_ibfk_1` FOREIGN KEY (`kd_pj`) REFERENCES `penjab` (`kd_pj`) ON UPDATE CASCADE,
  CONSTRAINT `pasien_ibfk_3` FOREIGN KEY (`kd_kec`) REFERENCES `kecamatan` (`kd_kec`) ON UPDATE CASCADE,
  CONSTRAINT `pasien_ibfk_4` FOREIGN KEY (`kd_kab`) REFERENCES `kabupaten` (`kd_kab`) ON UPDATE CASCADE,
  CONSTRAINT `pasien_ibfk_5` FOREIGN KEY (`kd_perusahaan`) REFERENCES `perusahaan_pasien` (`kd_perusahaan`) ON UPDATE CASCADE,
  CONSTRAINT `pasien_ibfk_6` FOREIGN KEY (`suku_bangsa`) REFERENCES `suku_bangsa` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pasien_ibfk_7` FOREIGN KEY (`bahasa_pasien`) REFERENCES `bahasa_pasien` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pasien_ibfk_8` FOREIGN KEY (`cacat_fisik`) REFERENCES `cacat_fisik` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `pasien_ibfk_9` FOREIGN KEY (`kd_prop`) REFERENCES `propinsi` (`kd_prop`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pemeriksaan_ralan`
--

DROP TABLE IF EXISTS `pemeriksaan_ralan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pemeriksaan_ralan` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `suhu_tubuh` varchar(5) DEFAULT NULL,
  `tensi` varchar(15) NOT NULL,
  `sistole` varchar(3) DEFAULT NULL,
  `diastole` varchar(3) DEFAULT NULL,
  `nadi` varchar(3) DEFAULT NULL,
  `respirasi` varchar(3) DEFAULT NULL,
  `tinggi` varchar(5) DEFAULT NULL,
  `berat` varchar(5) DEFAULT NULL,
  `spo2` varchar(3) NOT NULL,
  `gcs` varchar(10) DEFAULT NULL,
  `kesadaran` enum('Compos Mentis','Somnolence','Sopor','Coma') NOT NULL,
  `keluhan` varchar(2000) DEFAULT NULL,
  `pemeriksaan` varchar(2000) DEFAULT NULL,
  `alergi` varchar(50) DEFAULT NULL,
  `lingkar_perut` varchar(5) DEFAULT NULL,
  `rtl` varchar(2000) NOT NULL,
  `penilaian` varchar(2000) NOT NULL,
  `instruksi` varchar(2000) NOT NULL,
  `evaluasi` varchar(2000) NOT NULL,
  `nip` varchar(20) NOT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_perawatan`,`jam_rawat`) USING BTREE,
  KEY `no_rawat` (`no_rawat`),
  KEY `nip` (`nip`) USING BTREE,
  CONSTRAINT `pemeriksaan_ralan_ibfk_1` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `pemeriksaan_ralan_ibfk_2` FOREIGN KEY (`nip`) REFERENCES `pegawai` (`nik`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resep_obat`
--

DROP TABLE IF EXISTS `resep_obat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resep_obat` (
  `no_resep` varchar(14) NOT NULL DEFAULT '',
  `tgl_perawatan` date DEFAULT NULL,
  `jam` time DEFAULT NULL,
  `no_rawat` varchar(17) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT '',
  `kd_dokter` varchar(20) NOT NULL,
  `tgl_peresepan` date DEFAULT NULL,
  `jam_peresepan` time DEFAULT NULL,
  `status` enum('ralan','ranap') DEFAULT NULL,
  `tgl_penyerahan` date NOT NULL,
  `jam_penyerahan` time NOT NULL,
  PRIMARY KEY (`no_resep`),
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_dokter` (`kd_dokter`),
  CONSTRAINT `resep_obat_ibfk_3` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON UPDATE CASCADE,
  CONSTRAINT `resep_obat_ibfk_4` FOREIGN KEY (`kd_dokter`) REFERENCES `dokter` (`kd_dokter`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resep_dokter_nonracikan`
--

DROP TABLE IF EXISTS `resep_dokter_nonracikan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resep_dokter_nonracikan` (
  `no_resep` varchar(14) DEFAULT NULL,
  `kode_brng` varchar(15) DEFAULT NULL,
  `jml` double DEFAULT NULL,
  `aturan_pakai` varchar(150) DEFAULT NULL,
  `petugas` varchar(50) DEFAULT NULL,
  KEY `no_resep` (`no_resep`),
  KEY `kode_brng` (`kode_brng`),
  CONSTRAINT `resep_dokter_nonracikan_ibfk_1` FOREIGN KEY (`no_resep`) REFERENCES `resep_obat` (`no_resep`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resep_dokter_nonracikan_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resep_dokter_racikan`
--

DROP TABLE IF EXISTS `resep_dokter_racikan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resep_dokter_racikan` (
  `no_resep` varchar(14) NOT NULL,
  `no_racik` varchar(2) NOT NULL,
  `nama_racik` varchar(100) NOT NULL,
  `kd_racik` varchar(3) NOT NULL,
  `jml_dr` int NOT NULL,
  `aturan_pakai` varchar(150) NOT NULL,
  `keterangan` varchar(50) NOT NULL,
  `petugas` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`no_resep`,`no_racik`),
  KEY `kd_racik` (`kd_racik`),
  CONSTRAINT `resep_dokter_racikan_ibfk_1` FOREIGN KEY (`no_resep`) REFERENCES `resep_obat` (`no_resep`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resep_dokter_racikan_ibfk_2` FOREIGN KEY (`kd_racik`) REFERENCES `metode_racik` (`kd_racik`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resep_dokter_racikan_detail`
--

DROP TABLE IF EXISTS `resep_dokter_racikan_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resep_dokter_racikan_detail` (
  `no_resep` varchar(14) NOT NULL,
  `no_racik` varchar(2) NOT NULL,
  `kode_brng` varchar(15) NOT NULL,
  `p1` double DEFAULT NULL,
  `p2` double DEFAULT NULL,
  `kandungan` varchar(10) DEFAULT NULL,
  `jml` double DEFAULT NULL,
  PRIMARY KEY (`no_resep`,`no_racik`,`kode_brng`),
  KEY `kode_brng` (`kode_brng`),
  CONSTRAINT `resep_dokter_racikan_detail_ibfk_1` FOREIGN KEY (`no_resep`) REFERENCES `resep_obat` (`no_resep`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `resep_dokter_racikan_detail_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `detail_pemberian_obat`
--

DROP TABLE IF EXISTS `detail_pemberian_obat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pemberian_obat` (
  `tgl_perawatan` date NOT NULL DEFAULT '0000-00-00',
  `jam` time NOT NULL DEFAULT '00:00:00',
  `no_rawat` varchar(17) NOT NULL DEFAULT '',
  `kode_brng` varchar(15) NOT NULL,
  `h_beli` double DEFAULT NULL,
  `biaya_obat` double DEFAULT NULL,
  `jml` double NOT NULL,
  `embalase` double DEFAULT NULL,
  `tuslah` double DEFAULT NULL,
  `total` double NOT NULL,
  `status` enum('Ralan','Ranap') DEFAULT NULL,
  `kd_bangsal` char(5) DEFAULT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  `jenis_klaim` enum('Kapitasi','Non-Kapitasi','Tunai') DEFAULT 'Tunai',
  PRIMARY KEY (`tgl_perawatan`,`jam`,`no_rawat`,`kode_brng`,`no_batch`,`no_faktur`) USING BTREE,
  KEY `no_rawat` (`no_rawat`),
  KEY `kd_obat` (`kode_brng`),
  KEY `tgl_perawatan` (`tgl_perawatan`),
  KEY `jam` (`jam`),
  KEY `jml` (`jml`),
  KEY `tambahan` (`embalase`),
  KEY `total` (`total`),
  KEY `biaya_obat` (`biaya_obat`),
  KEY `kd_bangsal` (`kd_bangsal`),
  KEY `tuslah` (`tuslah`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  CONSTRAINT `detail_pemberian_obat_ibfk_3` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `detail_pemberian_obat_ibfk_4` FOREIGN KEY (`no_rawat`) REFERENCES `reg_periksa` (`no_rawat`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `detail_pemberian_obat_ibfk_5` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `databarang`
--

DROP TABLE IF EXISTS `databarang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `databarang` (
  `kode_brng` varchar(15) NOT NULL DEFAULT '',
  `nama_brng` varchar(80) DEFAULT NULL,
  `kode_satbesar` char(4) NOT NULL,
  `kode_sat` char(4) DEFAULT NULL,
  `letak_barang` varchar(100) DEFAULT NULL,
  `dasar` double NOT NULL,
  `h_beli` double DEFAULT NULL,
  `jualbebas` double DEFAULT NULL,
  `stokminimal` double DEFAULT NULL,
  `kdjns` char(4) DEFAULT NULL,
  `isi` double NOT NULL,
  `kapasitas` double NOT NULL,
  `expire` date DEFAULT NULL,
  `status` enum('0','1') NOT NULL,
  `sediaan` varchar(20) DEFAULT '',
  `kode_industri` char(5) DEFAULT NULL,
  `kode_kategori` char(4) DEFAULT NULL,
  `kode_golongan` char(4) DEFAULT NULL,
  `harga_kapitasi` double DEFAULT '0',
  `harga_nonkapitasi` double DEFAULT '0',
  `harga_tunai` double DEFAULT '0',
  `zat_aktif` text,
  `khasiat` text,
  PRIMARY KEY (`kode_brng`),
  KEY `kode_sat` (`kode_sat`),
  KEY `kdjns` (`kdjns`),
  KEY `nama_brng` (`nama_brng`),
  KEY `letak_barang` (`letak_barang`),
  KEY `h_beli` (`h_beli`),
  KEY `stok` (`stokminimal`),
  KEY `kapasitas` (`kapasitas`),
  KEY `kode_industri` (`kode_industri`),
  KEY `jualbebas` (`jualbebas`),
  KEY `expire` (`expire`),
  KEY `status` (`status`),
  KEY `kode_kategori` (`kode_kategori`),
  KEY `kode_golongan` (`kode_golongan`),
  KEY `kode_satbesar` (`kode_satbesar`) USING BTREE,
  FULLTEXT KEY `ft_search` (`nama_brng`,`zat_aktif`,`khasiat`),
  CONSTRAINT `databarang_ibfk_2` FOREIGN KEY (`kdjns`) REFERENCES `jenis` (`kdjns`) ON UPDATE CASCADE,
  CONSTRAINT `databarang_ibfk_3` FOREIGN KEY (`kode_sat`) REFERENCES `kodesatuan` (`kode_sat`) ON UPDATE CASCADE,
  CONSTRAINT `databarang_ibfk_4` FOREIGN KEY (`kode_industri`) REFERENCES `industrifarmasi` (`kode_industri`) ON UPDATE CASCADE,
  CONSTRAINT `databarang_ibfk_5` FOREIGN KEY (`kode_kategori`) REFERENCES `kategori_barang` (`kode`) ON UPDATE CASCADE,
  CONSTRAINT `databarang_ibfk_6` FOREIGN KEY (`kode_golongan`) REFERENCES `golongan_barang` (`kode`) ON UPDATE CASCADE,
  CONSTRAINT `databarang_ibfk_7` FOREIGN KEY (`kode_satbesar`) REFERENCES `kodesatuan` (`kode_sat`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gudangbarang`
--

DROP TABLE IF EXISTS `gudangbarang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudangbarang` (
  `kode_brng` varchar(15) NOT NULL,
  `kd_bangsal` char(5) NOT NULL DEFAULT '',
  `stok` double NOT NULL,
  `no_batch` varchar(20) NOT NULL,
  `no_faktur` varchar(20) NOT NULL,
  PRIMARY KEY (`kode_brng`,`kd_bangsal`,`no_batch`,`no_faktur`) USING BTREE,
  KEY `kode_brng` (`kode_brng`),
  KEY `stok` (`stok`),
  KEY `kd_bangsal` (`kd_bangsal`) USING BTREE,
  CONSTRAINT `gudangbarang_ibfk_1` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `gudangbarang_ibfk_2` FOREIGN KEY (`kode_brng`) REFERENCES `databarang` (`kode_brng`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mlite_billing_ledger`
--

DROP TABLE IF EXISTS `mlite_billing_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mlite_billing_ledger` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_rawat` varchar(17) NOT NULL,
  `item_type` varchar(50) NOT NULL,
  `item_id` varchar(50) NOT NULL,
  `item_key` varchar(100) NOT NULL,
  `item_name` varchar(200) NOT NULL DEFAULT '',
  `provider_id` varchar(50) NOT NULL DEFAULT '',
  `staff_id` varchar(50) NOT NULL DEFAULT '',
  `status` enum('Pending','Paid','Approved','Bundled','Cancelled','Expired') NOT NULL DEFAULT 'Pending',
  `claim_type` enum('Tunai','Kapitasi','Non-Kapitasi','Piutang') NOT NULL DEFAULT 'Tunai',
  `price` double NOT NULL DEFAULT '0',
  `kd_billing` varchar(20) NOT NULL DEFAULT '',
  `user_id` varchar(50) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item` (`no_rawat`,`item_type`,`item_id`,`item_key`)
) ENGINE=InnoDB AUTO_INCREMENT=169337 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jns_perawatan_inap`
--

DROP TABLE IF EXISTS `jns_perawatan_inap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jns_perawatan_inap` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) DEFAULT NULL,
  `kd_kategori` char(5) NOT NULL,
  `material` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_tindakandr` double DEFAULT NULL,
  `tarif_tindakanpr` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `kd_bangsal` char(5) NOT NULL,
  `status` enum('0','1') NOT NULL,
  `kelas` enum('-','Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') NOT NULL,
  `harga_kapitasi` double DEFAULT '0',
  `harga_nonkapitasi` double DEFAULT '0',
  `harga_tunai` double DEFAULT '0',
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `kd_bangsal` (`kd_bangsal`),
  KEY `kd_kategori` (`kd_kategori`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `material` (`material`),
  KEY `tarif_tindakandr` (`tarif_tindakandr`),
  KEY `tarif_tindakanpr` (`tarif_tindakanpr`),
  KEY `bhp` (`bhp`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `status` (`status`),
  CONSTRAINT `jns_perawatan_inap_ibfk_7` FOREIGN KEY (`kd_kategori`) REFERENCES `kategori_perawatan` (`kd_kategori`) ON UPDATE CASCADE,
  CONSTRAINT `jns_perawatan_inap_ibfk_9` FOREIGN KEY (`kd_bangsal`) REFERENCES `bangsal` (`kd_bangsal`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jns_perawatan_lab`
--

DROP TABLE IF EXISTS `jns_perawatan_lab`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jns_perawatan_lab` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) DEFAULT NULL,
  `bagian_rs` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_perujuk` double NOT NULL,
  `tarif_tindakan_dokter` double NOT NULL,
  `tarif_tindakan_petugas` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `status` enum('0','1') NOT NULL,
  `kelas` enum('-','Rawat Jalan','Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') NOT NULL,
  `kategori` enum('PK','PA','MB') NOT NULL,
  `harga_kapitasi` double DEFAULT '0',
  `harga_nonkapitasi` double DEFAULT '0',
  `harga_tunai` double DEFAULT '0',
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `tarif_perujuk` (`tarif_perujuk`),
  KEY `tarif_tindakan_dokter` (`tarif_tindakan_dokter`),
  KEY `tarif_tindakan_petugas` (`tarif_tindakan_petugas`),
  KEY `bagian_rs` (`bagian_rs`),
  KEY `bhp` (`bhp`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jns_perawatan_radiologi`
--

DROP TABLE IF EXISTS `jns_perawatan_radiologi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jns_perawatan_radiologi` (
  `kd_jenis_prw` varchar(15) NOT NULL,
  `nm_perawatan` varchar(80) DEFAULT NULL,
  `bagian_rs` double DEFAULT NULL,
  `bhp` double NOT NULL,
  `tarif_perujuk` double NOT NULL,
  `tarif_tindakan_dokter` double NOT NULL,
  `tarif_tindakan_petugas` double DEFAULT NULL,
  `kso` double DEFAULT NULL,
  `menejemen` double DEFAULT NULL,
  `status` enum('0','1') NOT NULL,
  `kelas` enum('-','Rawat Jalan','Kelas 1','Kelas 2','Kelas 3','Kelas Utama','Kelas VIP','Kelas VVIP') NOT NULL,
  `harga_kapitasi` double DEFAULT '0',
  `harga_nonkapitasi` double DEFAULT '0',
  `harga_tunai` double DEFAULT '0',
  PRIMARY KEY (`kd_jenis_prw`),
  KEY `nm_perawatan` (`nm_perawatan`),
  KEY `bagian_rs` (`bagian_rs`),
  KEY `tarif_perujuk` (`tarif_perujuk`),
  KEY `tarif_tindakan_dokter` (`tarif_tindakan_dokter`),
  KEY `tarif_tindakan_petugas` (`tarif_tindakan_petugas`),
  KEY `bhp` (`bhp`),
  KEY `kso` (`kso`),
  KEY `menejemen` (`menejemen`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mlite_jurnal`
--

DROP TABLE IF EXISTS `mlite_jurnal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mlite_jurnal` (
  `no_jurnal` varchar(20) NOT NULL,
  `no_bukti` varchar(20) DEFAULT NULL,
  `tgl_jurnal` date DEFAULT NULL,
  `jenis` enum('U','P') DEFAULT NULL,
  `kegiatan` varchar(250) NOT NULL,
  `keterangan` varchar(350) DEFAULT NULL,
  PRIMARY KEY (`no_jurnal`),
  KEY `no_bukti` (`no_bukti`),
  KEY `tgl_jurnal` (`tgl_jurnal`),
  KEY `jenis` (`jenis`),
  KEY `keterangan` (`keterangan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mlite_wagateway_inbox`
--

DROP TABLE IF EXISTS `mlite_wagateway_inbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mlite_wagateway_inbox` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key_from_me` tinyint(1) DEFAULT '0',
  `status` varchar(20) DEFAULT 'sent',
  `sentiment` varchar(20) DEFAULT NULL,
  `message_id` varchar(100) DEFAULT NULL,
  `instance_id` varchar(50) DEFAULT NULL,
  `sender_number` varchar(50) DEFAULT NULL,
  `sender_name` varchar(100) DEFAULT NULL,
  `message` text,
  `file_url` text,
  `message_type` varchar(50) DEFAULT NULL,
  `quoted_id` varchar(100) DEFAULT NULL,
  `quoted_sender` varchar(50) DEFAULT NULL,
  `quoted_msg` text,
  `received_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` tinyint(1) DEFAULT '0',
  `original_message` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_message_unique` (`message_id`),
  KEY `idx_instance_id` (`instance_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22394 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mlite_wa_verified_numbers`
--

DROP TABLE IF EXISTS `mlite_wa_verified_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mlite_wa_verified_numbers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `no_sistem` varchar(20) NOT NULL COMMENT 'Nomor WA Server RS/Klinik',
  `no_pasien` varchar(20) NOT NULL COMMENT 'Nomor WA Pasien Terverifikasi',
  `no_rkm_medis` varchar(20) NOT NULL COMMENT 'Nomor Rekam Medis Pasien',
  `status_verifikasi` enum('terverifikasi','blocklist','unreachable') DEFAULT 'terverifikasi',
  `tgl_verifikasi` datetime NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pasien_norm` (`no_pasien`,`no_rkm_medis`),
  KEY `idx_no_pasien` (`no_pasien`),
  KEY `idx_norm` (`no_rkm_medis`)
) ENGINE=InnoDB AUTO_INCREMENT=20167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mlite_harga_apotek_umum`
--

DROP TABLE IF EXISTS `mlite_harga_apotek_umum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mlite_harga_apotek_umum` (
  `kode_brng` varchar(15) NOT NULL,
  `h_beli` double NOT NULL DEFAULT '0',
  `margin_persen` double NOT NULL DEFAULT '20',
  `stok_minimal` double NOT NULL DEFAULT '5',
  `harga_jual` double NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL,
  `user_id` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`kode_brng`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mlite_pemeriksaan_ralan_metadata`
--

DROP TABLE IF EXISTS `mlite_pemeriksaan_ralan_metadata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mlite_pemeriksaan_ralan_metadata` (
  `no_rawat` varchar(17) NOT NULL,
  `tgl_perawatan` date NOT NULL,
  `jam_rawat` time NOT NULL,
  `meta_key` varchar(50) NOT NULL,
  `meta_value` text NOT NULL,
  PRIMARY KEY (`no_rawat`,`tgl_perawatan`,`jam_rawat`,`meta_key`),
  CONSTRAINT `fk_pemeriksaan_metadata` FOREIGN KEY (`no_rawat`, `tgl_perawatan`, `jam_rawat`) REFERENCES `pemeriksaan_ralan` (`no_rawat`, `tgl_perawatan`, `jam_rawat`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-16 12:26:39
