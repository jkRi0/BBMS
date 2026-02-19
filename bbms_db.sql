-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 18, 2026 at 02:08 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bbms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `password`) VALUES
(9, 'admin', '$2y$10$LMhJVJxH76j/A01wzjLpCuYKJNsYFEeJloNCEwYGw45FI9T70.y6y');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `assigned_place` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `assigned_place`, `created_at`) VALUES
(1, 'John Doe', 'Manila Branch', '2026-02-17 08:18:03'),
(2, 'Jane Smith', 'Cebu Branch', '2026-02-17 08:18:03'),
(3, 'Michael Brown', 'Davao Branch', '2026-02-17 08:18:03'),
(4, 'Emily Davis', 'Quezon City', '2026-02-17 08:18:03'),
(5, 'Chris Wilson', 'Makati Office', '2026-02-17 08:18:03'),
(6, 'Sarah Miller', 'Taguig Office', '2026-02-17 08:18:03'),
(7, 'David Taylor', 'Pasig Warehouse', '2026-02-17 08:18:03'),
(8, 'Jessica Moore', 'Ortigas Hub', '2026-02-17 08:18:03'),
(9, 'Kevin Anderson', 'Alabang Branch', '2026-02-17 08:18:03'),
(10, 'Laura Thomas', 'Baguio Office', '2026-02-17 08:18:03'),
(11, 'Robert Garcia', 'Batangas Hub', '2026-02-17 08:18:03'),
(12, 'Maria Santos', 'Laguna Branch', '2026-02-17 08:18:03'),
(13, 'William Lopez', 'Cavite Office', '2026-02-17 08:18:03'),
(14, 'Linda Perez', 'Pampanga Warehouse', '2026-02-17 08:18:03'),
(15, 'James Cruz', 'Bulacan Office', '2026-02-17 08:18:03');

-- --------------------------------------------------------

--
-- Table structure for table `profits`
--

CREATE TABLE `profits` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `profit_date` date DEFAULT curdate(),
  `amount` decimal(10,2) NOT NULL,
  `is_training` tinyint(1) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Normal',
  `other_status_text` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profits`
--

INSERT INTO `profits` (`id`, `employee_id`, `profit_date`, `amount`, `is_training`, `submitted_at`, `status`, `other_status_text`, `session_id`, `latitude`, `longitude`) VALUES
(331, 4, '2026-02-17', 1000.00, 0, '2026-02-17 10:25:51', 'Regular', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(333, 10, '2026-02-17', 0.00, 0, '2026-02-17 10:26:33', 'Regular', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(334, 9, '2026-02-17', 0.00, 0, '2026-02-17 10:26:38', 'Leave', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(335, 14, '2026-02-17', 0.00, 0, '2026-02-17 10:26:43', 'Sick', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(336, 11, '2026-02-17', 0.00, 0, '2026-02-17 10:26:53', 'Others', 'ewasm', 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(338, 8, '2026-02-17', 1000.00, 0, '2026-02-17 10:27:26', 'Regular', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(342, 15, '2026-02-17', 12345.00, 0, '2026-02-17 10:48:08', 'Regular', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(344, 5, '2026-02-16', 100.00, 0, '2026-02-17 10:57:52', 'Regular', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', 14.2668364, 121.1554307),
(346, 5, '2026-02-19', 69.00, 0, '2026-02-17 11:02:12', 'Regular', NULL, NULL, NULL, NULL),
(347, 3, '2026-02-17', 100.00, 0, '2026-02-17 11:49:02', 'Regular', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(348, 13, '2026-02-17', 15.00, 0, '2026-02-17 11:55:25', 'Regular', NULL, 'vq7cirgl4cf1pt39eh1uhcfis2', NULL, NULL),
(350, 7, '2026-02-17', 111.00, 0, '2026-02-17 14:00:07', 'Regular', NULL, 'r4ad19npkg9gqupnit43fnoe4a', 14.2668364, 121.1554307),
(354, 5, '2026-02-17', 50.00, 0, '2026-02-17 22:50:02', 'Regular', NULL, 'r4ad19npkg9gqupnit43fnoe4a', 14.2668541, 121.1554532),
(358, 5, '2026-02-18', 10.00, 0, '2026-02-18 00:06:39', 'Regular', NULL, 'r4ad19npkg9gqupnit43fnoe4a', NULL, NULL),
(361, 7, '2026-02-18', 10000.00, 0, '2026-02-18 00:27:43', 'Regular', NULL, 'r4ad19npkg9gqupnit43fnoe4a', 14.2535972, 121.1400728),
(362, 1, '2026-02-18', 900.00, 0, '2026-02-18 00:45:17', 'Regular', NULL, 'r4ad19npkg9gqupnit43fnoe4a', 14.2535943, 121.1400720),
(363, 4, '2026-02-18', 5000.00, 0, '2026-02-18 00:58:04', 'Regular', NULL, 'r4ad19npkg9gqupnit43fnoe4a', 14.2536003, 121.1400774);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `profits`
--
ALTER TABLE `profits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `profits`
--
ALTER TABLE `profits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=364;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `profits`
--
ALTER TABLE `profits`
  ADD CONSTRAINT `profits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
