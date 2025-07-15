-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 13, 2025 at 04:32 PM
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
-- Database: `jlgis`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_password` varchar(255) NOT NULL,
  PRIMARY KEY (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `admin_password`) VALUES
(1, '$2y$12$P4XPhTBGnsE3R4QTiyX.TetdOqIPz2HU8a1B2uixFnUG81XsqiXWC');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `unit` enum('pcs','set','copies') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `idx_items_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`item_id`, `item_name`, `category`, `description`, `unit`, `created_at`) VALUES
(1, 'Chair', 'Furniture', 'Wood', 'pcs', '2025-07-13 11:01:56'),
(2, 'Table', 'Furniture', 'Plastic', 'pcs', '2025-07-13 14:07:35');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `room_id` int(11) NOT NULL AUTO_INCREMENT,
  `room_name` varchar(100) NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `building_number` varchar(50) NOT NULL,
  `grade_level` varchar(50) DEFAULT NULL,
  `room_type` enum('Classroom','Office') NOT NULL,
  `teacher_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`room_id`),
  KEY `idx_rooms_building` (`building_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`room_id`, `room_name`, `room_number`, `building_number`, `grade_level`, `room_type`, `teacher_name`, `created_at`) VALUES
(2, 'Grade 2 - Apple', 'Room 205', '2', NULL, 'Classroom', 'Mr. John Smith', '2025-07-13 11:09:24'),
(3, 'Storage Room', 'Room 102', '1', NULL, 'Office', '', '2025-07-13 11:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `roominventory`
--

CREATE TABLE `roominventory` (
  `inventory_id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `expected_quantity` int(11) NOT NULL,
  `ownership` enum('School Property','Homeroom','PTA Donated','Personal') NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `year` varchar(9) DEFAULT NULL,
  PRIMARY KEY (`inventory_id`),
  KEY `idx_roominventory_room` (`room_id`),
  KEY `idx_roominventory_item` (`item_id`),
  KEY `idx_roominventory_ownership` (`ownership`),
  CONSTRAINT `roominventory_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE,
  CONSTRAINT `roominventory_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`item_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roominventory`
--

INSERT INTO `roominventory` (`inventory_id`, `room_id`, `item_id`, `quantity`, `expected_quantity`, `ownership`, `remarks`, `created_at`, `updated_at`) VALUES
(2, 3, 1, 5, 10, 'School Property', NULL, '2025-07-13 13:38:48', '2025-07-13 13:38:48'),
(3, 3, 2, 2, 2, 'School Property', NULL, '2025-07-13 14:08:13', '2025-07-13 14:08:13');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
