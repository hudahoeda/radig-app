-- Rapor Digital Backup (Pure PHP Method)
-- Host: localhost
-- Waktu: 2025-11-08 16:26:41
-- Database: `raporsmp`
-- ------------------------------------------------------

DROP TABLE IF EXISTS `catatan_guru_wali`;
CREATE TABLE `catatan_guru_wali` (
  `id_catatan` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int NOT NULL,
  `id_guru_wali` int NOT NULL,
  `tanggal_catatan` date NOT NULL,
  `kategori_catatan` enum('Akademik','Karakter','Keterampilan','Komunikasi Ortu','Lainnya') COLLATE utf8mb4_general_ci NOT NULL,
  `isi_catatan` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id_catatan`),
  KEY `id_siswa` (`id_siswa`),
  KEY `id_guru_wali` (`id_guru_wali`),
  CONSTRAINT `catatan_guru_wali_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `catatan_guru_wali_ibfk_2` FOREIGN KEY (`id_guru_wali`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id_pesan` int NOT NULL AUTO_INCREMENT,
  `percakapan_id` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `id_pengirim` int NOT NULL,
  `role_pengirim` enum('guru','siswa') COLLATE utf8mb4_general_ci NOT NULL,
  `id_penerima` int NOT NULL,
  `role_penerima` enum('guru','siswa') COLLATE utf8mb4_general_ci NOT NULL,
  `isi_pesan` text COLLATE utf8mb4_general_ci NOT NULL,
  `waktu_kirim` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_baca` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=belum dibaca, 1=sudah dibaca',
  PRIMARY KEY (`id_pesan`),
  KEY `percakapan_id` (`percakapan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `ekskul_kehadiran`;
CREATE TABLE `ekskul_kehadiran` (
  `id_kehadiran_ekskul` int NOT NULL AUTO_INCREMENT,
  `id_peserta_ekskul` int NOT NULL,
  `semester` int NOT NULL,
  `jumlah_hadir` int NOT NULL,
  `total_pertemuan` int NOT NULL,
  PRIMARY KEY (`id_kehadiran_ekskul`),
  UNIQUE KEY `unique_peserta_semester` (`id_peserta_ekskul`,`semester`),
  CONSTRAINT `ekskul_kehadiran_ibfk_1` FOREIGN KEY (`id_peserta_ekskul`) REFERENCES `ekskul_peserta` (`id_peserta_ekskul`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `ekskul_penilaian`;
CREATE TABLE `ekskul_penilaian` (
  `id_penilaian_ekskul` int NOT NULL AUTO_INCREMENT,
  `id_peserta_ekskul` int NOT NULL,
  `id_tujuan_ekskul` int NOT NULL,
  `nilai` enum('Sangat Baik','Baik','Cukup','Kurang') NOT NULL,
  PRIMARY KEY (`id_penilaian_ekskul`),
  UNIQUE KEY `unique_peserta_tujuan` (`id_peserta_ekskul`,`id_tujuan_ekskul`),
  KEY `id_tujuan_ekskul` (`id_tujuan_ekskul`),
  CONSTRAINT `ekskul_penilaian_ibfk_1` FOREIGN KEY (`id_peserta_ekskul`) REFERENCES `ekskul_peserta` (`id_peserta_ekskul`) ON DELETE CASCADE,
  CONSTRAINT `ekskul_penilaian_ibfk_2` FOREIGN KEY (`id_tujuan_ekskul`) REFERENCES `ekskul_tujuan` (`id_tujuan_ekskul`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `ekskul_peserta`;
CREATE TABLE `ekskul_peserta` (
  `id_peserta_ekskul` int NOT NULL AUTO_INCREMENT,
  `id_ekskul` int NOT NULL,
  `id_siswa` int NOT NULL,
  PRIMARY KEY (`id_peserta_ekskul`),
  UNIQUE KEY `unique_ekskul_siswa` (`id_ekskul`,`id_siswa`),
  KEY `id_siswa` (`id_siswa`),
  CONSTRAINT `ekskul_peserta_ibfk_1` FOREIGN KEY (`id_ekskul`) REFERENCES `ekstrakurikuler` (`id_ekskul`) ON DELETE CASCADE,
  CONSTRAINT `ekskul_peserta_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `ekskul_tujuan`;
CREATE TABLE `ekskul_tujuan` (
  `id_tujuan_ekskul` int NOT NULL AUTO_INCREMENT,
  `id_ekskul` int NOT NULL,
  `semester` int NOT NULL,
  `deskripsi_tujuan` text NOT NULL,
  PRIMARY KEY (`id_tujuan_ekskul`),
  KEY `id_ekskul` (`id_ekskul`),
  CONSTRAINT `ekskul_tujuan_ibfk_1` FOREIGN KEY (`id_ekskul`) REFERENCES `ekstrakurikuler` (`id_ekskul`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `ekstrakurikuler`;
CREATE TABLE `ekstrakurikuler` (
  `id_ekskul` int NOT NULL AUTO_INCREMENT,
  `nama_ekskul` varchar(100) NOT NULL,
  `id_pembina` int NOT NULL,
  `id_tahun_ajaran` int NOT NULL,
  PRIMARY KEY (`id_ekskul`),
  KEY `id_pembina` (`id_pembina`),
  KEY `id_tahun_ajaran` (`id_tahun_ajaran`),
  CONSTRAINT `ekstrakurikuler_ibfk_1` FOREIGN KEY (`id_pembina`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE,
  CONSTRAINT `ekstrakurikuler_ibfk_2` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id_tahun_ajaran`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `guru`;
CREATE TABLE `guru` (
  `id_guru` int NOT NULL AUTO_INCREMENT,
  `nip` varchar(30) DEFAULT NULL,
  `nama_guru` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guru') NOT NULL,
  `terakhir_login` datetime DEFAULT NULL,
  `foto_guru` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_guru`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `nip` (`nip`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;

INSERT INTO `guru` VALUES('1','000000','Administrator Sistem','admin','$2a$12$4d8PReO.YZSZBmthKAQ/EeuyeM0cYM6ITzbvIjEUdg1zXg.lpvjY.','admin','2025-11-08 16:23:17','guru_1_1762593940.png');

DROP TABLE IF EXISTS `guru_mengajar`;
CREATE TABLE `guru_mengajar` (
  `id_guru_mengajar` int NOT NULL AUTO_INCREMENT,
  `id_guru` int NOT NULL,
  `id_mapel` int NOT NULL,
  `id_kelas` int NOT NULL,
  `id_tahun_ajaran` int NOT NULL,
  PRIMARY KEY (`id_guru_mengajar`),
  UNIQUE KEY `unique_assignment_new` (`id_guru`,`id_mapel`,`id_kelas`,`id_tahun_ajaran`),
  KEY `guru_mengajar_ibfk_2` (`id_mapel`),
  KEY `guru_mengajar_ibfk_3` (`id_tahun_ajaran`),
  KEY `fk_guru_mengajar_kelas` (`id_kelas`),
  CONSTRAINT `fk_guru_mengajar_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `guru_mengajar_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE,
  CONSTRAINT `guru_mengajar_ibfk_2` FOREIGN KEY (`id_mapel`) REFERENCES `mata_pelajaran` (`id_mapel`) ON DELETE CASCADE,
  CONSTRAINT `guru_mengajar_ibfk_3` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id_tahun_ajaran`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `kelas`;
CREATE TABLE `kelas` (
  `id_kelas` int NOT NULL AUTO_INCREMENT,
  `nama_kelas` varchar(50) NOT NULL,
  `fase` varchar(5) NOT NULL DEFAULT 'D',
  `id_wali_kelas` int DEFAULT NULL,
  `id_tahun_ajaran` int DEFAULT NULL,
  PRIMARY KEY (`id_kelas`),
  UNIQUE KEY `uq_nama_kelas_tahun` (`nama_kelas`,`id_tahun_ajaran`),
  KEY `id_wali_kelas` (`id_wali_kelas`),
  KEY `id_tahun_ajaran` (`id_tahun_ajaran`),
  CONSTRAINT `kelas_ibfk_1` FOREIGN KEY (`id_wali_kelas`) REFERENCES `guru` (`id_guru`),
  CONSTRAINT `kelas_ibfk_2` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id_tahun_ajaran`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `kokurikuler_asesmen`;
CREATE TABLE `kokurikuler_asesmen` (
  `id_asesmen` int NOT NULL AUTO_INCREMENT,
  `id_target` int DEFAULT NULL,
  `id_siswa` int DEFAULT NULL,
  `id_guru_penilai` int DEFAULT NULL,
  `nilai_kualitatif` varchar(20) DEFAULT NULL,
  `catatan_guru` text,
  PRIMARY KEY (`id_asesmen`),
  UNIQUE KEY `unique_guru_target_siswa` (`id_guru_penilai`,`id_target`,`id_siswa`),
  KEY `id_siswa` (`id_siswa`),
  KEY `kokurikuler_asesmen_ibfk_1` (`id_target`),
  CONSTRAINT `fk_asesmen_guru` FOREIGN KEY (`id_guru_penilai`) REFERENCES `guru` (`id_guru`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `kokurikuler_asesmen_ibfk_1` FOREIGN KEY (`id_target`) REFERENCES `kokurikuler_target_dimensi` (`id_target`),
  CONSTRAINT `kokurikuler_asesmen_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`)
) ENGINE=InnoDB AUTO_INCREMENT=321 DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `kokurikuler_kegiatan`;
CREATE TABLE `kokurikuler_kegiatan` (
  `id_kegiatan` int NOT NULL AUTO_INCREMENT,
  `id_tahun_ajaran` int DEFAULT NULL,
  `id_kelas` int DEFAULT NULL,
  `semester` int DEFAULT NULL,
  `tema_kegiatan` varchar(255) NOT NULL,
  `bentuk_kegiatan` enum('Lintas Disiplin','G7KAIH','Cara Lainnya') NOT NULL,
  `id_koordinator` int DEFAULT NULL,
  PRIMARY KEY (`id_kegiatan`),
  KEY `id_tahun_ajaran` (`id_tahun_ajaran`),
  KEY `fk_kegiatan_kelas` (`id_kelas`),
  KEY `fk_kegiatan_koordinator` (`id_koordinator`),
  CONSTRAINT `fk_kegiatan_kelas` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_kegiatan_koordinator` FOREIGN KEY (`id_koordinator`) REFERENCES `guru` (`id_guru`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `kokurikuler_kegiatan_ibfk_1` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id_tahun_ajaran`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `kokurikuler_mapel_terlibat`;
CREATE TABLE `kokurikuler_mapel_terlibat` (
  `id_kegiatan` int NOT NULL,
  `id_mapel` int NOT NULL,
  PRIMARY KEY (`id_kegiatan`,`id_mapel`),
  KEY `fk_mapel_mapel` (`id_mapel`),
  CONSTRAINT `fk_mapel_kegiatan` FOREIGN KEY (`id_kegiatan`) REFERENCES `kokurikuler_kegiatan` (`id_kegiatan`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mapel_mapel` FOREIGN KEY (`id_mapel`) REFERENCES `mata_pelajaran` (`id_mapel`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `kokurikuler_target_dimensi`;
CREATE TABLE `kokurikuler_target_dimensi` (
  `id_target` int NOT NULL AUTO_INCREMENT,
  `id_kegiatan` int DEFAULT NULL,
  `nama_dimensi` varchar(50) NOT NULL,
  PRIMARY KEY (`id_target`),
  KEY `id_kegiatan` (`id_kegiatan`),
  CONSTRAINT `kokurikuler_target_dimensi_ibfk_1` FOREIGN KEY (`id_kegiatan`) REFERENCES `kokurikuler_kegiatan` (`id_kegiatan`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `kokurikuler_tim_penilai`;
CREATE TABLE `kokurikuler_tim_penilai` (
  `id_kegiatan` int NOT NULL,
  `id_guru` int NOT NULL,
  PRIMARY KEY (`id_kegiatan`,`id_guru`),
  KEY `fk_tim_guru` (`id_guru`),
  CONSTRAINT `fk_tim_guru` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_tim_kegiatan` FOREIGN KEY (`id_kegiatan`) REFERENCES `kokurikuler_kegiatan` (`id_kegiatan`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `mata_pelajaran`;
CREATE TABLE `mata_pelajaran` (
  `id_mapel` int NOT NULL AUTO_INCREMENT,
  `nama_mapel` varchar(100) NOT NULL,
  `kode_mapel` varchar(10) DEFAULT NULL,
  `urutan` int NOT NULL DEFAULT '99',
  PRIMARY KEY (`id_mapel`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=latin1;

INSERT INTO `mata_pelajaran` VALUES('1','Bahasa Inggris','BIG','11');
INSERT INTO `mata_pelajaran` VALUES('2','Pendidikan Agama Islam dan Budi Pekerti','PAI','1');
INSERT INTO `mata_pelajaran` VALUES('3','Pendidikan Pancasila','PP','6');
INSERT INTO `mata_pelajaran` VALUES('4','Bahasa Indonesia','BIN','7');
INSERT INTO `mata_pelajaran` VALUES('5','Matematika','MTK','8');
INSERT INTO `mata_pelajaran` VALUES('6','Ilmu Pengetahuan ALam','IPA','9');
INSERT INTO `mata_pelajaran` VALUES('7','Ilmu Pengetahuan Sosial','IPS','10');
INSERT INTO `mata_pelajaran` VALUES('8','Seni Rupa','SBdP','14');
INSERT INTO `mata_pelajaran` VALUES('9','Prakarya','PKy','15');
INSERT INTO `mata_pelajaran` VALUES('10','Informatika','INF','13');
INSERT INTO `mata_pelajaran` VALUES('11','Bahasa Daerah','Bader','16');
INSERT INTO `mata_pelajaran` VALUES('12','Pendidikan Jasmani Olahraga dan Kesehatan','PJOK','12');
INSERT INTO `mata_pelajaran` VALUES('13','Pendidikan Agama Kristen dan Budi Pekerti','PAK','2');
INSERT INTO `mata_pelajaran` VALUES('14','Pendidikan Agama Hindu dan Budi Pekerti','PAH','4');
INSERT INTO `mata_pelajaran` VALUES('15','Pendidikan Agama Budha dan Budi Pekerti','PAB','3');
INSERT INTO `mata_pelajaran` VALUES('16','Pendidikan Agama Katolik dan Budi Pekerti','PAKK','5');

DROP TABLE IF EXISTS `mutasi_keluar`;
CREATE TABLE `mutasi_keluar` (
  `id_mutasi_keluar` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int NOT NULL,
  `tanggal_keluar` date DEFAULT NULL,
  `kelas_ditinggalkan` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `alasan` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id_mutasi_keluar`),
  KEY `id_siswa` (`id_siswa`),
  CONSTRAINT `mutasi_keluar_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `mutasi_masuk`;
CREATE TABLE `mutasi_masuk` (
  `id_mutasi_masuk` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int NOT NULL,
  `sekolah_asal` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `diterima_di_kelas` varchar(10) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tahun_pelajaran` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id_mutasi_masuk`),
  KEY `id_siswa` (`id_siswa`),
  CONSTRAINT `mutasi_masuk_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `pengaturan`;
CREATE TABLE `pengaturan` (
  `id_pengaturan` int NOT NULL AUTO_INCREMENT,
  `nama_pengaturan` varchar(50) NOT NULL,
  `nilai_pengaturan` text,
  PRIMARY KEY (`id_pengaturan`),
  UNIQUE KEY `nama_pengaturan` (`nama_pengaturan`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;

INSERT INTO `pengaturan` VALUES('1','semester_aktif','1');
INSERT INTO `pengaturan` VALUES('2','tanggal_rapor','2025-12-20');
INSERT INTO `pengaturan` VALUES('3','fase_aktif','D');
INSERT INTO `pengaturan` VALUES('4','tampilkan_nilai_fase_a','tidak');
INSERT INTO `pengaturan` VALUES('5','watermark_file','watermark_1757716182.png');
INSERT INTO `pengaturan` VALUES('6','kkm','75');
INSERT INTO `pengaturan` VALUES('7','rapor_ukuran_kertas','F4');
INSERT INTO `pengaturan` VALUES('8','rapor_skema_warna','light_green');

DROP TABLE IF EXISTS `penilaian`;
CREATE TABLE `penilaian` (
  `id_penilaian` int NOT NULL AUTO_INCREMENT,
  `id_kelas` int NOT NULL,
  `id_mapel` int NOT NULL,
  `id_guru` int NOT NULL,
  `nama_penilaian` varchar(150) NOT NULL,
  `jenis_penilaian` enum('Formatif','Sumatif') NOT NULL,
  `subjenis_penilaian` enum('Sumatif TP','Sumatif Akhir Semester','Sumatif Akhir Tahun') DEFAULT NULL,
  `bobot_penilaian` int NOT NULL DEFAULT '1',
  `semester` int DEFAULT NULL,
  `tanggal_penilaian` date DEFAULT NULL,
  PRIMARY KEY (`id_penilaian`),
  KEY `id_kelas` (`id_kelas`),
  KEY `id_mapel` (`id_mapel`),
  KEY `id_guru` (`id_guru`),
  CONSTRAINT `penilaian_ibfk_1` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`),
  CONSTRAINT `penilaian_ibfk_2` FOREIGN KEY (`id_mapel`) REFERENCES `mata_pelajaran` (`id_mapel`),
  CONSTRAINT `penilaian_ibfk_4` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id_guru`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `penilaian_detail_nilai`;
CREATE TABLE `penilaian_detail_nilai` (
  `id_detail_nilai` int NOT NULL AUTO_INCREMENT,
  `id_penilaian` int NOT NULL,
  `id_siswa` int NOT NULL,
  `nilai` int NOT NULL,
  PRIMARY KEY (`id_detail_nilai`),
  UNIQUE KEY `unique_penilaian_siswa` (`id_penilaian`,`id_siswa`),
  KEY `id_siswa` (`id_siswa`),
  CONSTRAINT `penilaian_detail_nilai_ibfk_1` FOREIGN KEY (`id_penilaian`) REFERENCES `penilaian` (`id_penilaian`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_detail_nilai_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `penilaian_tp`;
CREATE TABLE `penilaian_tp` (
  `id_penilaian_tp` int NOT NULL AUTO_INCREMENT,
  `id_penilaian` int NOT NULL,
  `id_tp` int NOT NULL,
  PRIMARY KEY (`id_penilaian_tp`),
  KEY `idx_id_penilaian` (`id_penilaian`),
  KEY `idx_id_tp` (`id_tp`),
  CONSTRAINT `penilaian_tp_ibfk_1` FOREIGN KEY (`id_penilaian`) REFERENCES `penilaian` (`id_penilaian`) ON DELETE CASCADE,
  CONSTRAINT `penilaian_tp_ibfk_2` FOREIGN KEY (`id_tp`) REFERENCES `tujuan_pembelajaran` (`id_tp`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rapor`;
CREATE TABLE `rapor` (
  `id_rapor` int NOT NULL AUTO_INCREMENT,
  `id_siswa` int DEFAULT NULL,
  `id_kelas` int DEFAULT NULL,
  `id_tahun_ajaran` int DEFAULT NULL,
  `semester` int DEFAULT NULL,
  `sakit` int DEFAULT '0',
  `izin` int DEFAULT '0',
  `tanpa_keterangan` int DEFAULT '0',
  `catatan_wali_kelas` text,
  `deskripsi_kokurikuler` text,
  `deskripsi_ekstrakurikuler` text,
  `status` enum('Draft','Final') DEFAULT 'Draft',
  `tanggal_rapor` date DEFAULT NULL,
  PRIMARY KEY (`id_rapor`),
  UNIQUE KEY `unique_rapor_siswa` (`id_siswa`,`id_tahun_ajaran`,`semester`),
  KEY `id_kelas` (`id_kelas`),
  KEY `id_tahun_ajaran` (`id_tahun_ajaran`),
  CONSTRAINT `rapor_ibfk_1` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id_siswa`),
  CONSTRAINT `rapor_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`),
  CONSTRAINT `rapor_ibfk_3` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id_tahun_ajaran`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rapor_detail_akademik`;
CREATE TABLE `rapor_detail_akademik` (
  `id_rapor_detail` int NOT NULL AUTO_INCREMENT,
  `id_rapor` int DEFAULT NULL,
  `id_mapel` int DEFAULT NULL,
  `nilai_akhir` int DEFAULT NULL,
  `capaian_kompetensi` text,
  PRIMARY KEY (`id_rapor_detail`),
  UNIQUE KEY `unique_rapor_mapel` (`id_rapor`,`id_mapel`),
  KEY `id_mapel` (`id_mapel`),
  CONSTRAINT `rapor_detail_akademik_ibfk_1` FOREIGN KEY (`id_rapor`) REFERENCES `rapor` (`id_rapor`),
  CONSTRAINT `rapor_detail_akademik_ibfk_2` FOREIGN KEY (`id_mapel`) REFERENCES `mata_pelajaran` (`id_mapel`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `rapor_detail_ekskul`;
CREATE TABLE `rapor_detail_ekskul` (
  `id_rapor_ekskul` int NOT NULL AUTO_INCREMENT,
  `id_rapor` int DEFAULT NULL,
  `nama_ekskul` varchar(100) DEFAULT NULL,
  `keterangan` text,
  PRIMARY KEY (`id_rapor_ekskul`),
  KEY `id_rapor` (`id_rapor`),
  CONSTRAINT `rapor_detail_ekskul_ibfk_1` FOREIGN KEY (`id_rapor`) REFERENCES `rapor` (`id_rapor`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `sekolah`;
CREATE TABLE `sekolah` (
  `id_sekolah` int NOT NULL DEFAULT '1',
  `nama_sekolah` varchar(100) NOT NULL,
  `npsn` varchar(20) DEFAULT NULL,
  `jenjang` varchar(50) DEFAULT 'SMP',
  `nss` varchar(50) DEFAULT NULL,
  `alamat_legacy` text,
  `jalan` varchar(255) DEFAULT NULL,
  `desa_kelurahan` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `kabupaten_kota` varchar(100) DEFAULT NULL,
  `provinsi` varchar(100) DEFAULT NULL,
  `kode_pos` varchar(10) DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `fax` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `website` varchar(100) DEFAULT NULL,
  `nama_kepsek` varchar(100) DEFAULT NULL,
  `nip_kepsek` varchar(30) DEFAULT NULL,
  `jabatan_kepsek` varchar(100) DEFAULT NULL,
  `logo_sekolah` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_sekolah`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `sekolah` VALUES('1','SMP NEGERI 1 PAGELARAN','20561842','SMP','201051832150','Dusun Ngembul Desa Jombok Kecamatan Ngantang','Jalan - , RT 22 RW 9 , Dusun Ngembul','Desa Jombok','Ngantang','Malang','Jawa Timur','654392','081231598611','-','info@multischool.sch.id','https://multischool.sch.id','Budi Rahayu, S.Pd.SD.','19720816 1996052 001','Pembina Tingkat I','logo_1756884573_logosatap.png');

DROP TABLE IF EXISTS `siswa`;
CREATE TABLE `siswa` (
  `id_siswa` int NOT NULL AUTO_INCREMENT,
  `nisn` varchar(20) NOT NULL,
  `nis` varchar(20) DEFAULT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `nik` varchar(20) DEFAULT NULL,
  `agama` varchar(50) DEFAULT NULL,
  `alamat` text,
  `nama_ayah` varchar(100) DEFAULT NULL,
  `nama_ibu` varchar(100) DEFAULT NULL,
  `id_kelas` int DEFAULT NULL,
  `id_guru_wali` int DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status_siswa` enum('Aktif','Lulus','Pindah','Keluar') NOT NULL DEFAULT 'Aktif',
  `sekolah_asal` varchar(100) DEFAULT NULL,
  `diterima_tanggal` date DEFAULT NULL,
  `anak_ke` int DEFAULT NULL,
  `status_dalam_keluarga` varchar(50) DEFAULT NULL,
  `telepon_siswa` varchar(20) DEFAULT NULL,
  `pekerjaan_ayah` varchar(100) DEFAULT NULL,
  `pekerjaan_ibu` varchar(100) DEFAULT NULL,
  `nama_wali` varchar(100) DEFAULT NULL,
  `alamat_wali` text,
  `telepon_wali` varchar(20) DEFAULT NULL,
  `pekerjaan_wali` varchar(100) DEFAULT NULL,
  `foto_siswa` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_siswa`),
  UNIQUE KEY `nisn` (`nisn`),
  UNIQUE KEY `username` (`username`),
  KEY `id_kelas` (`id_kelas`),
  KEY `fk_guru_wali` (`id_guru_wali`),
  CONSTRAINT `fk_guru_wali` FOREIGN KEY (`id_guru_wali`) REFERENCES `guru` (`id_guru`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `tahun_ajaran`;
CREATE TABLE `tahun_ajaran` (
  `id_tahun_ajaran` int NOT NULL AUTO_INCREMENT,
  `tahun_ajaran` varchar(20) NOT NULL,
  `status` enum('Aktif','Tidak Aktif') NOT NULL,
  PRIMARY KEY (`id_tahun_ajaran`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;

INSERT INTO `tahun_ajaran` VALUES('1','2025/2026','Aktif');
INSERT INTO `tahun_ajaran` VALUES('2','2026/2027','Tidak Aktif');
INSERT INTO `tahun_ajaran` VALUES('3','2024/2025','Tidak Aktif');

DROP TABLE IF EXISTS `tp_kelas`;
CREATE TABLE `tp_kelas` (
  `id_tp_kelas` int NOT NULL AUTO_INCREMENT,
  `id_tp` int NOT NULL,
  `id_kelas` int NOT NULL,
  PRIMARY KEY (`id_tp_kelas`),
  UNIQUE KEY `unique_tp_kelas` (`id_tp`,`id_kelas`),
  KEY `id_kelas` (`id_kelas`),
  CONSTRAINT `tp_kelas_ibfk_1` FOREIGN KEY (`id_tp`) REFERENCES `tujuan_pembelajaran` (`id_tp`) ON DELETE CASCADE,
  CONSTRAINT `tp_kelas_ibfk_2` FOREIGN KEY (`id_kelas`) REFERENCES `kelas` (`id_kelas`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

DROP TABLE IF EXISTS `tujuan_pembelajaran`;
CREATE TABLE `tujuan_pembelajaran` (
  `id_tp` int NOT NULL AUTO_INCREMENT,
  `id_mapel` int DEFAULT NULL,
  `id_guru_pembuat` int DEFAULT NULL,
  `fase` varchar(5) NOT NULL,
  `kode_tp` varchar(20) DEFAULT NULL,
  `deskripsi_tp` text NOT NULL,
  `semester` int DEFAULT NULL,
  `id_tahun_ajaran` int DEFAULT NULL,
  PRIMARY KEY (`id_tp`),
  KEY `id_mapel` (`id_mapel`),
  KEY `id_tahun_ajaran` (`id_tahun_ajaran`),
  KEY `id_guru_pembuat` (`id_guru_pembuat`),
  CONSTRAINT `tujuan_pembelajaran_ibfk_1` FOREIGN KEY (`id_mapel`) REFERENCES `mata_pelajaran` (`id_mapel`),
  CONSTRAINT `tujuan_pembelajaran_ibfk_2` FOREIGN KEY (`id_tahun_ajaran`) REFERENCES `tahun_ajaran` (`id_tahun_ajaran`),
  CONSTRAINT `tujuan_pembelajaran_ibfk_3` FOREIGN KEY (`id_guru_pembuat`) REFERENCES `guru` (`id_guru`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

