-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 09, 2025 at 08:38 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `absensi_siswa`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int NOT NULL,
  `siswa_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `status` enum('Hadir','Sakit','Izin','Terlambat','Alpha') COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  `bukti_foto` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `bukti_file` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approval_status` enum('Pending','Approved','Rejected') COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `siswa_id`, `tanggal`, `jam_masuk`, `status`, `keterangan`, `bukti_foto`, `bukti_file`, `approval_status`, `created_at`, `updated_at`) VALUES
(2, 2, '2025-03-03', '07:15:00', 'Terlambat', NULL, NULL, NULL, 'Approved', '2025-03-03 01:24:04', '2025-03-03 01:24:04'),
(3, 3, '2025-03-03', '00:00:00', 'Sakit', NULL, NULL, NULL, 'Approved', '2025-03-03 01:24:04', '2025-03-06 01:04:44'),
(4, 4, '2025-03-03', '06:55:00', 'Hadir', NULL, NULL, NULL, 'Approved', '2025-03-03 01:24:04', '2025-03-03 01:24:04'),
(5, 5, '2025-03-02', '07:05:00', 'Hadir', NULL, NULL, NULL, 'Approved', '2025-03-03 01:24:04', '2025-03-03 01:24:04'),
(6, 6, '2025-03-02', '00:00:00', 'Izin', NULL, NULL, NULL, 'Approved', '2025-03-03 01:24:04', '2025-03-09 07:39:09'),
(7, 7, '2025-03-01', '07:30:00', 'Terlambat', NULL, NULL, NULL, 'Approved', '2025-03-03 01:24:04', '2025-03-03 01:24:04'),
(9, 11, '2025-03-03', '23:15:00', 'Hadir', '', NULL, NULL, 'Approved', '2025-03-03 09:15:51', '2025-03-03 09:15:51'),
(31, 2, '2025-03-04', '22:44:00', 'Hadir', '', NULL, NULL, 'Approved', '2025-03-04 08:44:10', '2025-03-06 01:04:38'),
(32, 19, '2025-03-04', '23:09:00', 'Hadir', '', NULL, NULL, 'Approved', '2025-03-04 09:10:02', '2025-03-04 09:10:02'),
(33, 14, '2025-03-05', '18:48:00', 'Hadir', '', NULL, NULL, 'Approved', '2025-03-05 11:48:28', '2025-03-05 11:48:28'),
(46, 12, '2025-03-09', '14:39:00', 'Hadir', 'aduhh', NULL, NULL, 'Pending', '2025-03-09 07:39:57', '2025-03-09 07:39:57'),
(48, 2, '2025-03-09', NULL, 'Sakit', 'sakit buk', '', NULL, 'Pending', '2025-03-09 07:44:00', '2025-03-09 07:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int NOT NULL,
  `user_type` enum('admin','siswa') COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` int NOT NULL,
  `activity_type` enum('login','logout','create','update','delete','approval','absensi') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_type`, `user_id`, `activity_type`, `description`, `created_at`) VALUES
(7, 'admin', 1, 'delete', 'Admin menghapus data siswa: Ahmad Fadillah (2024001)', '2025-03-03 08:32:13'),
(8, 'admin', 1, 'delete', 'Admin menghapus data siswa: Putri Rahayu (2023001)', '2025-03-03 08:32:30'),
(9, 'admin', 1, 'create', 'Admin menambahkan siswa baru: Ahmad Fadilah (2023001)', '2025-03-03 08:33:00'),
(10, 'admin', 1, 'delete', 'Admin menghapus absensi Hadir untuk Hana Safira pada tanggal 03/03/2025', '2025-03-03 08:33:19'),
(11, 'admin', 1, 'logout', 'Admin logged out from the system', '2025-03-03 10:04:41'),
(12, 'admin', 1, 'login', 'Admin logged into the system', '2025-03-03 10:04:43'),
(13, 'admin', 1, 'logout', 'Admin logged out from the system', '2025-03-03 10:04:48'),
(14, 'admin', 1, 'login', 'Admin logged into the system', '2025-03-03 10:04:49'),
(15, 'admin', 1, 'logout', 'Admin logged out from the system', '2025-03-03 10:14:10'),
(16, 'admin', 1, 'login', 'Admin logged into the system', '2025-03-03 10:14:11'),
(17, 'admin', 1, 'logout', 'Admin Administrator logout dari sistem', '2025-03-03 10:17:06'),
(18, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-03 10:17:08'),
(19, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 10:30:08'),
(20, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 10:30:45'),
(21, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 10:30:48'),
(22, 'admin', 1, 'update', 'Admin mengubah password', '2025-03-03 10:43:30'),
(23, 'admin', 1, 'update', 'Admin mengubah password', '2025-03-03 10:43:45'),
(24, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 10:43:57'),
(25, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 10:45:45'),
(26, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 10:50:28'),
(27, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 10:50:35'),
(28, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 11:09:08'),
(29, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 11:09:15'),
(30, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 11:09:29'),
(31, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 11:09:38'),
(32, 'admin', 1, 'logout', 'Admin Administrator logout dari sistem', '2025-03-03 11:19:29'),
(33, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-03 11:19:31'),
(34, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-03 16:02:50'),
(35, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Kevin Wijaya pada tanggal 03/03/2025', '2025-03-03 16:15:51'),
(36, 'admin', 1, 'logout', 'Admin Administrator logout dari sistem', '2025-03-03 16:56:39'),
(37, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-03 16:57:31'),
(38, 'admin', 1, 'logout', 'Admin Administrator logout dari sistem', '2025-03-03 17:05:39'),
(39, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-04 07:32:47'),
(40, 'siswa', 2, 'logout', 'Siswa Budi Santoso logout dari sistem', '2025-03-04 07:43:47'),
(41, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-04 07:44:00'),
(42, 'siswa', 2, 'logout', 'Siswa Budi Santoso logout dari sistem', '2025-03-04 07:44:04'),
(43, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-04 07:44:34'),
(44, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-04 08:13:37'),
(45, 'siswa', 2, 'create', 'Siswa Budi Santoso mengisi absensi sebagai Hadir', '2025-03-04 08:18:28'),
(46, 'siswa', 2, 'create', 'Siswa Budi Santoso mengisi absensi sebagai Sakit', '2025-03-04 08:19:23'),
(47, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 12:33:46'),
(48, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengisi absensi sebagai Izin', '2025-03-04 12:33:58'),
(49, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 12:34:03'),
(50, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengisi absensi sebagai Hadir', '2025-03-04 12:34:17'),
(51, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengisi absensi sebagai Sakit', '2025-03-04 12:34:43'),
(52, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 12:34:59'),
(53, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Sakit', '2025-03-04 12:47:46'),
(54, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 12:47:53'),
(55, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 12:47:57'),
(56, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 12:48:01'),
(57, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Izin', '2025-03-04 12:48:04'),
(58, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 12:48:16'),
(59, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 12:48:18'),
(60, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 12:48:27'),
(61, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Sakit', '2025-03-04 12:48:40'),
(62, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Sakit dari siswa Budi Santoso', '2025-03-04 12:48:56'),
(63, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 13:10:20'),
(64, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 13:10:26'),
(65, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 13:21:54'),
(66, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso', '2025-03-04 13:22:07'),
(67, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 13:37:14'),
(68, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 13:37:30'),
(69, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 13:37:49'),
(70, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso', '2025-03-04 13:37:58'),
(71, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 13:46:13'),
(72, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 13:46:16'),
(73, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Sakit', '2025-03-04 13:52:41'),
(74, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 13:52:53'),
(75, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 14:10:30'),
(76, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-04 14:10:55'),
(77, 'siswa', 2, 'update', 'Siswa mengubah profil', '2025-03-04 14:31:30'),
(78, 'siswa', 2, 'update', 'Siswa mengubah profil', '2025-03-04 14:31:47'),
(79, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-04 14:33:23'),
(80, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso', '2025-03-04 14:33:30'),
(81, 'admin', 1, 'delete', 'Admin menghapus absensi Hadir untuk Budi Santoso pada tanggal 04/03/2025', '2025-03-04 14:35:21'),
(82, 'siswa', 2, 'update', 'Siswa mengubah profil', '2025-03-04 14:36:44'),
(83, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Budi Santoso pada tanggal 04/03/2025', '2025-03-04 15:44:10'),
(84, 'admin', 1, 'update', 'Admin mengedit absensi Budi Santoso tanggal 04/03/2025', '2025-03-04 15:45:00'),
(85, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso', '2025-03-04 15:45:13'),
(86, 'admin', 1, 'update', 'Admin mengedit absensi Budi Santoso tanggal 04/03/2025', '2025-03-04 16:09:53'),
(87, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Ahmad Fadilah pada tanggal 04/03/2025', '2025-03-04 16:10:02'),
(88, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-05 08:21:39'),
(89, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-05 08:21:59'),
(90, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Ahmad Fadilah pada tanggal 05/03/2025', '2025-03-05 08:38:31'),
(91, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Cindy Amelia pada tanggal 05/03/2025', '2025-03-05 08:39:06'),
(92, 'admin', 1, 'update', 'Admin mengedit absensi Ahmad Fadilah tanggal 04/03/2025', '2025-03-05 08:39:38'),
(93, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Ahmad Fadilah pada tanggal 05/03/2025', '2025-03-05 08:40:00'),
(94, 'admin', 1, 'update', 'Admin mengedit absensi Ahmad Fadilah tanggal 04/03/2025', '2025-03-05 08:40:15'),
(95, 'admin', 1, 'delete', 'Admin menghapus absensi Hadir untuk Ahmad Fadilah pada tanggal 04/03/2025', '2025-03-05 08:40:36'),
(96, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Hana Safira pada tanggal 05/03/2025', '2025-03-05 08:41:16'),
(97, 'admin', 1, 'update', 'Admin mengedit absensi Hana Safira tanggal 05/03/2025', '2025-03-05 08:42:10'),
(98, 'admin', 1, 'update', 'Admin mengedit absensi Hana Safira tanggal 05/03/2025', '2025-03-05 08:42:24'),
(99, 'admin', 1, 'update', 'Admin mengedit absensi Cindy Amelia tanggal 05/03/2025', '2025-03-05 08:42:33'),
(100, 'admin', 1, 'update', 'Admin mengedit absensi Cindy Amelia tanggal 05/03/2025', '2025-03-05 08:43:53'),
(101, 'admin', 1, 'update', 'Admin mengedit absensi Cindy Amelia tanggal 05/03/2025', '2025-03-05 08:44:08'),
(102, 'admin', 1, 'update', 'Admin mengedit absensi Cindy Amelia tanggal 05/03/2025', '2025-03-05 08:44:29'),
(103, 'siswa', 2, 'logout', 'Siswa Budi Santoso logout dari sistem', '2025-03-05 10:00:57'),
(104, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-05 10:14:50'),
(105, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 10:15:13'),
(106, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso', '2025-03-05 10:15:31'),
(107, 'admin', 1, 'update', 'Admin mengedit absensi Budi Santoso tanggal 04/03/2025', '2025-03-05 10:16:02'),
(108, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Izin', '2025-03-05 10:16:23'),
(109, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Sakit dari siswa Budi Santoso', '2025-03-05 10:16:29'),
(110, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Izin dari siswa Budi Santoso', '2025-03-05 10:16:30'),
(111, 'siswa', 2, 'logout', 'Siswa Budi Santoso logout dari sistem', '2025-03-05 10:35:48'),
(112, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-05 10:36:11'),
(113, 'siswa', 2, 'logout', 'Siswa Budi Santoso logout dari sistem', '2025-03-05 11:14:35'),
(114, 'siswa', 2, 'login', 'Siswa Budi Santoso melakukan login', '2025-03-05 11:14:55'),
(115, 'siswa', 2, 'logout', 'Siswa Budi Santoso logout dari sistem', '2025-03-05 11:43:06'),
(116, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-05 11:43:53'),
(117, 'admin', 1, 'logout', 'Admin Administrator logout dari sistem', '2025-03-05 11:44:17'),
(118, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-05 11:44:20'),
(119, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Budi Santoso pada tanggal 05/03/2025', '2025-03-05 11:47:01'),
(120, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Nina Amalia pada tanggal 05/03/2025', '2025-03-05 11:48:28'),
(121, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 12:12:06'),
(122, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-05 12:20:52'),
(123, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 13:27:57'),
(124, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-05 13:28:03'),
(125, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 13:44:40'),
(126, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso', '2025-03-05 13:44:51'),
(127, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 13:54:33'),
(128, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-05 13:54:38'),
(129, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 13:55:23'),
(130, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso', '2025-03-05 13:55:30'),
(131, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 14:15:01'),
(132, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-05 14:15:10'),
(133, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 14:15:20'),
(134, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso', '2025-03-05 14:15:26'),
(135, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-05 14:15:45'),
(136, 'siswa', 2, 'delete', 'Siswa Budi Santoso membatalkan pengajuan absensi', '2025-03-05 14:15:48'),
(137, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-06 00:41:10'),
(138, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-06 00:41:20'),
(139, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-06 00:41:30'),
(140, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso', '2025-03-06 00:41:41'),
(141, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-06 00:58:19'),
(142, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Hadir dari siswa Budi Santoso', '2025-03-06 00:58:30'),
(143, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso', '2025-03-06 01:04:38'),
(144, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Sakit dari siswa Cindy Amelia', '2025-03-06 01:04:44'),
(145, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Hadir', '2025-03-06 01:04:56'),
(146, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Hadir dari siswa Budi Santoso', '2025-03-06 01:05:07'),
(147, 'admin', 1, 'delete', 'Admin menghapus absensi Hadir untuk Budi Santoso pada tanggal 06/03/2025', '2025-03-06 01:18:30'),
(148, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-06 12:09:25'),
(149, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-07 15:13:59'),
(150, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-07 15:14:33'),
(151, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-08 08:22:44'),
(152, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Indra Kusuma pada tanggal 08/03/2025', '2025-03-08 08:33:57'),
(153, 'admin', 1, 'delete', 'Admin menghapus absensi Hadir untuk Indra Kusuma pada tanggal 08/03/2025', '2025-03-08 08:52:51'),
(154, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-08 16:44:42'),
(155, 'admin', 1, 'login', 'Admin Administrator login ke sistem', '2025-03-09 05:35:50'),
(156, 'siswa', 2, 'login', 'Siswa Budi Santoso login ke sistem', '2025-03-09 06:43:10'),
(157, 'admin', 1, 'approval', 'Admin Administrator menyetujui pengajuan Izin dari siswa Fani Azahra', '2025-03-09 07:39:09'),
(158, 'admin', 1, 'create', 'Admin menambahkan absensi Hadir untuk Luna Sari pada tanggal 09/03/2025', '2025-03-09 07:39:57'),
(159, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-09 07:41:39'),
(160, 'admin', 1, 'update', 'Admin mengubah profil', '2025-03-09 07:41:48'),
(161, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Sakit', '2025-03-09 07:43:02'),
(162, 'admin', 1, 'approval', 'Admin Administrator menolak pengajuan Sakit dari siswa Budi Santoso', '2025-03-09 07:43:43'),
(163, 'siswa', 2, 'absensi', 'Siswa Budi Santoso mengajukan absensi sebagai Sakit', '2025-03-09 07:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'assets/default/photo-profile.png',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `email`, `password`, `nama_lengkap`, `foto_profil`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@smkn40-jkt.sch.id', 'admin123', 'Administrator', 'uploads/admin/admin_1_1741506108.jpg', '2025-03-09 12:35:50', '2025-03-03 08:23:13', '2025-03-09 07:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id` int NOT NULL,
  `nis` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `kelas` enum('10','11','12') COLLATE utf8mb4_general_ci NOT NULL,
  `jurusan` enum('RPL','DKV','AK','BR','MP') COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `foto_profil` varchar(255) COLLATE utf8mb4_general_ci DEFAULT 'assets/default/photo-profile.png',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id`, `nis`, `nama_lengkap`, `kelas`, `jurusan`, `email`, `password`, `foto_profil`, `created_at`, `updated_at`) VALUES
(2, '2024002', 'Budi Santoso', '10', 'RPL', 'budi@siswa.smkn40jkt.sch.id', 'siswa_2024002', 'uploads/siswa/siswa_2_1741098707.png', '2025-03-03 08:24:03', '2025-03-04 14:31:47'),
(3, '2024003', 'Cindy Amelia', '10', 'RPL', 'cindy@siswa.smkn40jkt.sch.id', 'siswa_2024003', 'assets/default/photo-profile.png', '2025-03-03 08:24:03', '2025-03-03 08:24:03'),
(4, '2024004', 'Diana Putri', '10', 'DKV', 'diana@siswa.smkn40jkt.sch.id', 'diana_2024004', 'assets/default/photo-profile.png', '2025-03-03 08:24:03', '2025-03-03 08:24:03'),
(5, '2024005', 'Eko Prasetyo', '10', 'DKV', 'eko@siswa.smkn40jkt.sch.id', 'eko_2024005', 'assets/default/photo-profile.png', '2025-03-03 08:24:03', '2025-03-03 08:24:03'),
(6, '2024006', 'Fani Azahra', '10', 'DKV', 'fani@siswa.smkn40jkt.sch.id', 'fani_2024006', 'assets/default/photo-profile.png', '2025-03-03 08:24:03', '2025-03-03 08:24:03'),
(7, '2024007', 'Galih Pratama', '10', 'AK', 'galih@siswa.smkn40jkt.sch.id', 'siswa_2024007', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(8, '2024008', 'Hana Safira', '10', 'AK', 'hana@siswa.smkn40jkt.sch.id', 'siswa_2024008', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(9, '2024009', 'Indra Kusuma', '10', 'AK', 'indra@siswa.smkn40jkt.sch.id', 'siswa_2024009', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(10, '2024010', 'Jasmine Putri', '10', 'BR', 'jasmine@siswa.smkn40jkt.sch.id', 'siswa_2024010', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(11, '2024011', 'Kevin Wijaya', '10', 'BR', 'kevin@siswa.smkn40jkt.sch.id', 'siswa_2024011', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(12, '2024012', 'Luna Sari', '10', 'BR', 'luna@siswa.smkn40jkt.sch.id', 'siswa_2024012', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(13, '2024013', 'Mario Teguh', '10', 'MP', 'mario@siswa.smkn40jkt.sch.id', 'siswa_2024013', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(14, '2024014', 'Nina Amalia', '10', 'MP', 'nina@siswa.smkn40jkt.sch.id', 'siswa_2024014', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(15, '2024015', 'Oscar Putra', '10', 'MP', 'oscar@siswa.smkn40jkt.sch.id', 'siswa_2024015', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(17, '2023002', 'Qori Hidayat', '11', 'RPL', 'qori@siswa.smkn40jkt.sch.id', 'siswa_2023002', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(18, '2023003', 'Rama Putra', '11', 'RPL', 'rama@siswa.smkn40jkt.sch.id', 'siswa_2023003', 'assets/default/photo-profile.png', '2025-03-03 08:24:04', '2025-03-03 08:24:04'),
(19, '2023001', 'Ahmad Fadilah', '10', 'RPL', 'ahmetkutanis8@gmail.com', 'siswa_2023001', 'assets/default/photo-profile.png', '2025-03-03 08:33:00', '2025-03-03 08:33:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siswa_id` (`siswa_id`),
  ADD KEY `tanggal` (`tanggal`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_type` (`user_type`,`user_id`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nis` (`nis`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `fk_absensi_siswa` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE;
COMMIT;
