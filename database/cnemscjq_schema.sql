-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 21, 2026 at 07:51 AM
-- Server version: 11.4.13-MariaDB-cll-lve-log
-- PHP Version: 8.4.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cnemscjq_schema`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `reference` char(10) NOT NULL,
  `qr_token` char(32) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `movie_id` int(10) UNSIGNED NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `cinema` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `ticket_count` tinyint(3) UNSIGNED NOT NULL,
  `seat_list` varchar(80) NOT NULL,
  `ticket_price` int(10) UNSIGNED NOT NULL,
  `snacks_total` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','paid','expired','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `snack_status` enum('ordered','preparing','ready','sold') DEFAULT NULL,
  `snack_number` smallint(5) UNSIGNED DEFAULT NULL,
  `snack_scanned_at` datetime DEFAULT NULL,
  `snack_sold_at` datetime DEFAULT NULL,
  `paymongo_checkout_id` varchar(64) DEFAULT NULL,
  `paymongo_intent_id` varchar(64) DEFAULT NULL,
  `paymongo_card_intent_id` varchar(64) DEFAULT NULL,
  `paymongo_wallet_intent_id` varchar(64) DEFAULT NULL,
  `paymongo_payment_id` varchar(64) DEFAULT NULL,
  `paymongo_refund_id` varchar(64) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `scanned_at` datetime DEFAULT NULL,
  `scanned_by` int(10) UNSIGNED DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `refunded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `reference`, `qr_token`, `user_id`, `movie_id`, `show_date`, `show_time`, `cinema`, `ticket_count`, `seat_list`, `ticket_price`, `snacks_total`, `total`, `status`, `snack_status`, `snack_number`, `snack_scanned_at`, `snack_sold_at`, `paymongo_checkout_id`, `paymongo_intent_id`, `paymongo_card_intent_id`, `paymongo_wallet_intent_id`, `paymongo_payment_id`, `paymongo_refund_id`, `expires_at`, `paid_at`, `scanned_at`, `scanned_by`, `refunded_at`, `refunded_by`, `created_at`) VALUES
(347, 'CSF-RT38JS', '9488264bf78ee4987cea28b2587597e7', 248, 1, '2026-10-31', '18:10:00', 1, 1, 'F7', 220, 110, 330, 'paid', 'ordered', 1, NULL, NULL, NULL, 'pi_DwrdybfkRNqq8GeMLVFq7sbB', NULL, 'pi_9Hayb9xFwp2euf6xPoLwdgqk', 'pay_67G1zQZJvPG3DfZryfJrGWbf', NULL, '2026-10-16 14:16:21', '2026-10-16 14:06:40', NULL, NULL, NULL, NULL, '2026-10-16 14:06:21');

-- --------------------------------------------------------

--
-- Table structure for table `booking_seats`
--

CREATE TABLE `booking_seats` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `movie_id` int(10) UNSIGNED NOT NULL,
  `show_date` date NOT NULL,
  `show_time` time NOT NULL,
  `seat_code` varchar(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_seats`
--

INSERT INTO `booking_seats` (`id`, `booking_id`, `movie_id`, `show_date`, `show_time`, `seat_code`) VALUES
(381, 347, 1, '2026-10-31', '18:10:00', 'F7');

-- --------------------------------------------------------

--
-- Table structure for table `booking_snacks`
--

CREATE TABLE `booking_snacks` (
  `booking_id` int(10) UNSIGNED NOT NULL,
  `snack_id` int(10) UNSIGNED NOT NULL,
  `quantity` tinyint(3) UNSIGNED NOT NULL,
  `unit_price` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_snacks`
--

INSERT INTO `booking_snacks` (`booking_id`, `snack_id`, `quantity`, `unit_price`) VALUES
(347, 1, 1, 110);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bucket` varchar(40) NOT NULL,
  `identifier` varchar(190) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `bucket`, `identifier`, `ip_address`, `attempted_at`) VALUES
(3399, 'otp', 'myrtepacia@gmail.com', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 18:47:56'),
(3400, 'reset', 'tepacia18@gmail.com', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 18:49:25'),
(3401, 'seats', '', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 18:53:34'),
(3402, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 18:53:39'),
(3403, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 18:53:44'),
(3404, 'pay-check', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 18:53:46'),
(3405, 'settle', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 18:53:51'),
(3406, 'seats', '', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:09:51'),
(3407, 'seats', '', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:09:53'),
(3408, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:09:54'),
(3409, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:09:59'),
(3410, 'pay-check', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:10:01'),
(3411, 'settle', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:10:06'),
(3412, 'reset', 'tepacia18@gmail.com', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:11:51'),
(3413, 'otp', 'myrtepacia@gmail.com', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:12:27'),
(3414, 'seats', '', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:26:00'),
(3415, 'seats', '', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:26:07'),
(3416, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:26:08'),
(3417, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:26:13'),
(3418, 'pay-check', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:26:15'),
(3419, 'settle', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:26:19'),
(3420, 'seats', '', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:34:55'),
(3421, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:34:59'),
(3422, 'checkout', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:35:03'),
(3423, 'pay-check', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:35:06'),
(3424, 'settle', 'user:248', '2405:8d40:4cc8:2997:fdf2:2c26:c41:6fcb', '2026-10-14 19:35:10'),
(3425, 'seats', '', '49.145.43.200', '2026-10-14 23:19:28'),
(3426, 'seats', '', '49.145.43.200', '2026-10-14 23:19:36'),
(3427, 'seats', '', '49.145.43.200', '2026-10-14 23:19:46'),
(3428, 'seats', '', '49.145.43.200', '2026-10-14 23:19:56'),
(3429, 'seats', '', '49.145.43.200', '2026-10-14 23:20:06'),
(3430, 'signin', 'marygwenxx@gmail.com', '49.145.43.200', '2026-10-14 23:20:38'),
(3431, 'signin', 'marygwenxx@gmail.com', '49.145.43.200', '2026-10-14 23:22:10'),
(3432, 'otp', 'marygwenxx@gmail.com', '49.145.43.200', '2026-10-14 23:24:01'),
(3433, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:51:45'),
(3434, 'checkout', 'user:248', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:51:48'),
(3435, 'checkout', 'user:248', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:51:54'),
(3436, 'pay-check', 'user:248', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:51:56'),
(3437, 'settle', 'user:248', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:52:01'),
(3438, 'scan', '', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 01:52:17'),
(3439, 'settle', 'user:248', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:52:57'),
(3440, 'settle', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 01:53:29'),
(3441, 'scan', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:54:01'),
(3442, 'scan', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:58:39'),
(3443, 'scan', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 01:59:31'),
(3444, 'scan', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 02:01:08'),
(3445, 'scan', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 02:02:21'),
(3446, 'seats', '', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 02:05:53'),
(3447, 'checkout', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 02:06:00'),
(3448, 'seats', '', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 02:06:07'),
(3449, 'checkout', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 02:06:11'),
(3450, 'checkout', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 02:06:17'),
(3451, 'pay-check', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 02:06:19'),
(3452, 'settle', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 02:06:25'),
(3453, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:36:08'),
(3454, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:36:15'),
(3455, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:36:25'),
(3456, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:36:35'),
(3457, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:38:04'),
(3458, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:38:04'),
(3459, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:38:05'),
(3460, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:38:16'),
(3461, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:38:26'),
(3462, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:38:33'),
(3463, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:39:34'),
(3464, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:39:34'),
(3465, 'seats', '', '2405:8d40:4cd3:3476:99d7:f28a:451e:554e', '2026-10-15 03:39:45'),
(3466, 'seats', '', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:49:21'),
(3467, 'seats', '', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:49:25'),
(3468, 'checkout', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:49:28'),
(3469, 'pay-check', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:49:35'),
(3470, 'checkout', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:49:37'),
(3471, 'pay-check', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:49:39'),
(3472, 'settle', 'user:248', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:49:48'),
(3473, 'otp', 'myrtepacia@gmail.com', '2405:8d40:4cd3:3476:18de:6911:c03b:952', '2026-10-15 03:51:11'),
(3474, 'seats', '', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:30:37'),
(3475, 'seats', '', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:30:39'),
(3476, 'checkout', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:30:50'),
(3477, 'pay-check', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:30:59'),
(3478, 'checkout', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:31:00'),
(3479, 'pay-check', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:31:03'),
(3480, 'settle', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:31:13'),
(3481, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:31:52'),
(3482, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:32:24'),
(3483, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:32:25'),
(3484, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:32:29'),
(3485, 'settle', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:32:38'),
(3486, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:32:43'),
(3487, 'seats', '', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:32:55'),
(3488, 'checkout', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:32:58'),
(3489, 'checkout', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:33:06'),
(3490, 'pay-check', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:33:09'),
(3491, 'settle', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:33:17'),
(3492, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:33:22'),
(3493, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:33:24'),
(3494, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:33:28'),
(3495, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:34:03'),
(3496, 'seats', '', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:34:49'),
(3497, 'seats', '', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:34:52'),
(3498, 'checkout', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:34:52'),
(3499, 'checkout', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:34:58'),
(3500, 'pay-check', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:35:01'),
(3501, 'settle', 'user:248', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:35:05'),
(3502, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:35:08'),
(3503, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:35:11'),
(3504, 'scan', '', '2405:8d40:4cd8:f2f:18de:9717:83e:667', '2026-10-15 14:35:23'),
(3505, 'reset', 'tepacia18@gmail.com', '2405:8d40:4cd8:f2f:b9d1:c898:79f4:bd46', '2026-10-15 14:36:04'),
(3506, 'seats', '', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:28:36'),
(3507, 'seats', '', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:28:43'),
(3508, 'seats', '', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:28:53'),
(3509, 'seats', '', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:29:03'),
(3510, 'checkout', 'user:248', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:29:23'),
(3511, 'checkout', 'user:248', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:29:29'),
(3512, 'pay-check', 'user:248', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:29:32'),
(3513, 'settle', 'user:248', '2001:fd8:c208:9d00:b11a:e8df:a2dc:cdd', '2026-10-15 19:29:36'),
(3514, 'seats', '', '2001:fd8:c208:9d00:e063:399b:533e:7191', '2026-10-16 00:25:12'),
(3515, 'seats', '', '2001:fd8:c208:9d00:e063:399b:533e:7191', '2026-10-16 00:25:19'),
(3516, 'seats', '', '2001:fd8:c208:9d00:e063:399b:533e:7191', '2026-10-16 00:25:45'),
(3517, 'settle', 'user:248', '2001:fd8:c208:9d00:e063:399b:533e:7191', '2026-10-16 00:25:47'),
(3518, 'settle', 'user:248', '2001:fd8:c208:9d00:e063:399b:533e:7191', '2026-10-16 01:01:13'),
(3519, 'settle', 'user:248', '2001:fd8:c208:9d00:8456:d5c2:a6e3:f893', '2026-10-16 01:38:05'),
(3520, 'otp', 'myrtepacia@gmail.com', '180.190.4.47', '2026-10-16 14:03:22'),
(3521, 'reset', 'tepacia18@gmail.com', '180.190.4.47', '2026-10-16 14:04:30'),
(3523, 'seats', '', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:05:55'),
(3524, 'seats', '', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:05:58'),
(3525, 'seats', '', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:02'),
(3526, 'seats', '', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:06'),
(3527, 'seats', '', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:13'),
(3528, 'checkout', 'user:248', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:21'),
(3529, 'pay-check', 'user:248', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:30'),
(3530, 'checkout', 'user:248', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:31'),
(3531, 'pay-check', 'user:248', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:33'),
(3532, 'settle', 'user:248', '2001:fd8:c208:9d00:a95d:f9bb:849a:bf78', '2026-10-16 14:06:39'),
(3534, 'seats', '', '110.54.173.202', '2026-10-19 14:16:28'),
(3535, 'seats', '', '110.54.173.202', '2026-10-19 14:16:31'),
(3536, 'seats', '', '110.54.173.202', '2026-10-19 14:16:41'),
(3537, 'seats', '', '112.203.129.175', '2026-10-21 12:51:54'),
(3538, 'seats', '', '112.203.129.175', '2026-10-21 12:51:58'),
(3539, 'settle', 'user:248', '2405:8d40:4cd2:75b4:c5ad:73cf:bce6:ac75', '2026-10-21 18:46:20'),
(3540, 'seats', '', '2405:8d40:4cd2:75b4:c5ad:73cf:bce6:ac75', '2026-10-21 19:49:25');

-- --------------------------------------------------------

--
-- Table structure for table `movies`
--

CREATE TABLE `movies` (
  `id` int(10) UNSIGNED NOT NULL,
  `slug` varchar(120) NOT NULL,
  `title` varchar(150) NOT NULL,
  `genre` varchar(100) NOT NULL,
  `duration_minutes` smallint(5) UNSIGNED NOT NULL,
  `rating` enum('G','PG','PG-13','R-13','R-16','R-18') NOT NULL,
  `price` int(10) UNSIGNED NOT NULL,
  `status` enum('now_showing','upcoming') NOT NULL,
  `opens_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `poster_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `movies`
--

INSERT INTO `movies` (`id`, `slug`, `title`, `genre`, `duration_minutes`, `rating`, `price`, `status`, `opens_on`, `ends_on`, `poster_path`, `is_active`, `created_at`) VALUES
(1, 'eternal-sunshine', 'Eternal Sunshine of the Spotless Mind', 'Romance, Drama', 108, 'PG-13', 220, 'now_showing', NULL, NULL, 'assets/img/posters/eternal-sunshine.jpg', 1, '2026-09-28 01:06:42'),
(2, 'pieces-of-us', 'Pieces of Us', 'Comedy, Romance, Drama', 112, 'PG-13', 220, 'now_showing', NULL, NULL, 'assets/img/posters/Pieces-of-us.jpg', 1, '2026-09-28 01:06:42'),
(3, 'the-reckoning', 'The Reckoning', 'Action, Thriller', 122, 'R-16', 220, 'now_showing', NULL, NULL, 'assets/img/posters/The-Reckoning.jpg', 1, '2026-09-28 01:06:42'),
(4, 'movie-test', 'Movie Test', '', 0, 'R-16', 1, 'now_showing', NULL, NULL, '', 0, '2026-09-28 01:06:42'),
(5, 'hush', 'Hush', 'Horror, Thriller', 82, 'R-16', 220, 'upcoming', '2026-11-09', NULL, 'assets/img/posters/Hush.jpg', 1, '2026-09-28 01:06:42'),
(6, 'broken-of-love', 'Broken [of] Love', 'Romance, Drama', 105, 'PG-13', 220, 'upcoming', '2026-11-17', NULL, 'assets/img/posters/Broken-of-Love.jpg', 1, '2026-09-28 01:06:42'),
(7, 'maleficent', 'Maleficent', 'Dark Fantasy, Adventure, Drama', 153, 'PG', 220, 'now_showing', NULL, NULL, 'assets/img/posters/Maleficent.jpg', 1, '2026-10-02 13:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `showtimes`
--

CREATE TABLE `showtimes` (
  `id` int(10) UNSIGNED NOT NULL,
  `movie_id` int(10) UNSIGNED NOT NULL,
  `cinema` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `start_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `showtimes`
--

INSERT INTO `showtimes` (`id`, `movie_id`, `cinema`, `start_time`) VALUES
(36, 2, 1, '12:00:00'),
(37, 2, 1, '16:05:00'),
(38, 2, 1, '20:10:00'),
(39, 5, 1, '22:15:00'),
(43, 3, 2, '10:00:00'),
(44, 3, 2, '14:25:00'),
(45, 3, 2, '18:50:00'),
(46, 4, 2, '12:15:00'),
(47, 4, 2, '16:40:00'),
(48, 4, 2, '21:05:00'),
(146, 1, 1, '10:00:00'),
(147, 1, 1, '14:05:00'),
(148, 1, 1, '18:10:00'),
(149, 6, 2, '12:25:00'),
(150, 6, 2, '16:50:00'),
(151, 6, 2, '21:15:00'),
(153, 7, 2, '12:15:00'),
(154, 7, 2, '16:40:00'),
(155, 7, 2, '21:05:00');

-- --------------------------------------------------------

--
-- Table structure for table `snacks`
--

CREATE TABLE `snacks` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `detail` varchar(150) NOT NULL DEFAULT '',
  `category_id` int(10) UNSIGNED NOT NULL,
  `price` int(10) UNSIGNED NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `snacks`
--

INSERT INTO `snacks` (`id`, `name`, `detail`, `category_id`, `price`, `sort_order`, `is_active`) VALUES
(1, 'Popcorn, Regular', 'Salted, 64 oz', 1, 110, 1, 1),
(2, 'Popcorn, Large', 'Cheese or Caramel, 85 oz', 1, 145, 2, 1),
(3, 'Soda', '22 oz', 2, 70, 3, 1),
(4, 'Water ', '22 oz', 2, 25, 4, 1),
(5, 'Chocolate Bar', '90 g', 3, 60, 5, 1),
(6, 'Gummy Candy', 'Assorted, 100 g', 3, 55, 6, 1),
(7, 'Solo Combo', 'Regular popcorn and regular soda', 4, 180, 7, 1),
(8, 'Barkada Combo', '2 large popcorn and 2 large sodas', 4, 480, 8, 1),
(9, 'Iced Tea', 'Lemon, 22 oz', 2, 75, 9, 1),
(10, 'Lemonade', 'Fresh, 22 oz', 2, 80, 10, 1),
(11, 'Orange Juice', '16 oz', 2, 85, 11, 1),
(12, 'Hot Coffee', 'Brewed, 12 oz', 2, 90, 12, 1);

-- --------------------------------------------------------

--
-- Table structure for table `snack_categories`
--

CREATE TABLE `snack_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(50) NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `snack_categories`
--

INSERT INTO `snack_categories` (`id`, `name`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Popcorn', 1, 1, '2026-10-14 15:35:55'),
(2, 'Drinks', 2, 1, '2026-10-14 15:35:55'),
(3, 'Candy', 3, 1, '2026-10-14 15:35:55'),
(4, 'Combos', 4, 1, '2026-10-14 15:35:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','admin','scanner') NOT NULL DEFAULT 'customer',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `mobile`, `password_hash`, `role`, `created_at`, `last_login_at`) VALUES
(1, 'CSFlix Admin', 'admin@gmail.com', NULL, '$argon2id$v=19$m=65536,t=4,p=1$eUdrZHFzVWg2NTE1WUkzcg$zDo9ZNprzS9DY82fcQ1ll7ln13YKxPJU3HpSk9mh6Gk', 'admin', '2026-09-28 01:06:42', '2026-10-18 16:33:15'),
(2, 'Ticket Scanner', 'scanner@gmail.com', NULL, '$argon2id$v=19$m=65536,t=4,p=1$cElQWXJZOHpnQmJ5VnV6dA$wRw2G5rfEmpl1AVrpsbxB5ehIYtb0GUSB7VnYGPy8A4', 'scanner', '2026-09-28 01:06:42', '2026-10-02 20:42:24'),
(248, 'Henmyr Vincent L. Tepacia', 'tepacia18@gmail.com', '09940398477', '$argon2id$v=19$m=65536,t=4,p=1$cWNBR0doR0tid3FNaXFUcA$5JORClpTcx/LNA43z1/a38Z6H0NLmRNjGAPoRtWT3HA', 'customer', '2026-10-10 12:38:04', '2026-10-21 19:48:09'),
(250, 'Samantha May Wenham', 'wsamanthamay@gmail.com', '09069739089', '$argon2id$v=19$m=65536,t=4,p=1$TGpRUldOR3dscWMyM21ENA$g7pReSb6QlZcfteeyEievNU9DS7SLg4QDKk6qC9a36M', 'customer', '2026-10-14 13:04:36', '2026-10-14 13:04:36');

-- --------------------------------------------------------

--
-- Table structure for table `webhook_events`
--

CREATE TABLE `webhook_events` (
  `event_id` varchar(64) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_bookings_reference` (`reference`),
  ADD UNIQUE KEY `uq_bookings_checkout` (`paymongo_checkout_id`),
  ADD UNIQUE KEY `uq_bookings_intent` (`paymongo_intent_id`),
  ADD UNIQUE KEY `uq_bookings_card_intent` (`paymongo_card_intent_id`),
  ADD UNIQUE KEY `uq_bookings_wallet_intent` (`paymongo_wallet_intent_id`),
  ADD KEY `idx_bookings_user` (`user_id`,`created_at`),
  ADD KEY `idx_bookings_status` (`status`,`expires_at`),
  ADD KEY `idx_bookings_snacks` (`status`,`snack_status`),
  ADD KEY `fk_bookings_movie` (`movie_id`),
  ADD KEY `fk_bookings_scanned_by` (`scanned_by`),
  ADD KEY `fk_bookings_refunded_by` (`refunded_by`);

--
-- Indexes for table `booking_seats`
--
ALTER TABLE `booking_seats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seat_per_showing` (`movie_id`,`show_date`,`show_time`,`seat_code`),
  ADD KEY `idx_booking_seats_booking` (`booking_id`);

--
-- Indexes for table `booking_snacks`
--
ALTER TABLE `booking_snacks`
  ADD PRIMARY KEY (`booking_id`,`snack_id`),
  ADD KEY `fk_booking_snacks_snack` (`snack_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attempts_lookup` (`bucket`,`identifier`,`attempted_at`),
  ADD KEY `idx_attempts_ip` (`bucket`,`ip_address`,`attempted_at`);

--
-- Indexes for table `movies`
--
ALTER TABLE `movies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_movies_slug` (`slug`),
  ADD KEY `idx_movies_listing` (`is_active`,`status`);

--
-- Indexes for table `showtimes`
--
ALTER TABLE `showtimes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_showtimes` (`movie_id`,`start_time`);

--
-- Indexes for table `snacks`
--
ALTER TABLE `snacks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_snacks_category` (`category_id`,`is_active`,`sort_order`);

--
-- Indexes for table `snack_categories`
--
ALTER TABLE `snack_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- Indexes for table `webhook_events`
--
ALTER TABLE `webhook_events`
  ADD PRIMARY KEY (`event_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=348;

--
-- AUTO_INCREMENT for table `booking_seats`
--
ALTER TABLE `booking_seats`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=382;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3541;

--
-- AUTO_INCREMENT for table `movies`
--
ALTER TABLE `movies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `showtimes`
--
ALTER TABLE `showtimes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `snacks`
--
ALTER TABLE `snacks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `snack_categories`
--
ALTER TABLE `snack_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=251;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`),
  ADD CONSTRAINT `fk_bookings_refunded_by` FOREIGN KEY (`refunded_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_bookings_scanned_by` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `booking_seats`
--
ALTER TABLE `booking_seats`
  ADD CONSTRAINT `fk_booking_seats_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `booking_snacks`
--
ALTER TABLE `booking_snacks`
  ADD CONSTRAINT `fk_booking_snacks_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_booking_snacks_snack` FOREIGN KEY (`snack_id`) REFERENCES `snacks` (`id`);

--
-- Constraints for table `showtimes`
--
ALTER TABLE `showtimes`
  ADD CONSTRAINT `fk_showtimes_movie` FOREIGN KEY (`movie_id`) REFERENCES `movies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `snacks`
--
ALTER TABLE `snacks`
  ADD CONSTRAINT `fk_snacks_category` FOREIGN KEY (`category_id`) REFERENCES `snack_categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
