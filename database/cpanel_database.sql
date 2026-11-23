-- =============================================================================
-- Queue Management System - cPanel Ready Database Export
-- Generated for cPanel / phpMyAdmin Cloud & Shared Hosting Import
--
-- IMPORTANT FOR CPANEL USERS:
-- 1. Create your database in cPanel -> "MySQL® Databases" (e.g. youruser_queuedb).
-- 2. Create a MySQL user and assign it to the database with ALL PRIVILEGES.
-- 3. Open phpMyAdmin, select your newly created database on the left sidebar.
-- 4. Click the "Import" tab at the top and select this file.
--
-- (Note: This SQL script intentionally DOES NOT contain 'CREATE DATABASE' or 'USE'
-- statements to prevent cPanel #1044 Access Denied permission errors)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- -----------------------------------------------------------------------------
-- Clean Drop existing tables if re-importing
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `queues`;
DROP TABLE IF EXISTS `counters`;
DROP TABLE IF EXISTS `users`;

-- -----------------------------------------------------------------------------
-- Table structure for table `users`
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `counters`
-- -----------------------------------------------------------------------------
CREATE TABLE `counters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `counter_number` VARCHAR(30) NOT NULL UNIQUE,
  `counter_name` VARCHAR(100) NOT NULL,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `current_staff_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cpanel_counter_staff` FOREIGN KEY (`current_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `queues`
-- -----------------------------------------------------------------------------
CREATE TABLE `queues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `queue_number` VARCHAR(20) NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
  `service_type` VARCHAR(100) NOT NULL DEFAULT 'General Service',
  `status` ENUM('waiting', 'calling', 'serving', 'completed', 'skipped') NOT NULL DEFAULT 'waiting',
  `counter_id` INT NULL,
  `staff_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `called_at` DATETIME NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  CONSTRAINT `fk_cpanel_queue_counter` FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cpanel_queue_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Dumping data for table `users`
-- Default Passwords:
-- admin  => admin123
-- staff1 => staff123
-- staff2 => staff123
-- -----------------------------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `role`, `status`) VALUES
(1, 'admin', '$2y$10$I.ku/e7JCufLz9qjjrQQRONXZNMSlrvgrkby0Uwx7V9GmWxBjt6tm', 'System Administrator', 'admin', 'active'),
(2, 'staff1', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'Sarah Jenkins', 'staff', 'active'),
(3, 'staff2', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'Michael Chen', 'staff', 'active');

-- -----------------------------------------------------------------------------
-- Dumping data for table `counters`
-- -----------------------------------------------------------------------------
INSERT INTO `counters` (`id`, `counter_number`, `counter_name`, `status`, `current_staff_id`) VALUES
(1, 'Counter 1', 'General Enquiries & Registration', 'open', 2),
(2, 'Counter 2', 'Payment & Billing Services', 'open', 3),
(3, 'Counter 3', 'Customer Support & Documentation', 'closed', NULL),
(4, 'Counter 4', 'Priority & Express Services', 'open', NULL);

-- (Queue records table starts clean with 0 records - ready for live customer tickets)

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
