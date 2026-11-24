-- ===================================================
-- Queue Management System Database Schema (Complete Version)
-- Supports Kiosk, Citizen Portal, Appointments, Applications,
-- Staff Counter Workstation, Live Display & Admin Simulator
-- Compatible with MySQL 5.7+ / MariaDB 10.x via phpMyAdmin
-- ===================================================

CREATE DATABASE IF NOT EXISTS `queue_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `queue_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `queues`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `appointments`;
DROP TABLE IF EXISTS `counters`;
DROP TABLE IF EXISTS `services`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------
-- Table 1: `users`
-- Roles: admin, staff, user (public citizen)
-- ---------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `ic_passport` VARCHAR(50) NULL,
  `phone` VARCHAR(30) NULL,
  `role` ENUM('admin', 'staff', 'user') NOT NULL DEFAULT 'user',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 2: `services`
-- Departmental service catalog (PR, PA, VA, WP, IE)
-- ---------------------------------------------------
CREATE TABLE `services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(10) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `est_duration` INT NOT NULL DEFAULT 15,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 3: `counters`
-- Service counter stations (Counter 01, 02, etc.)
-- ---------------------------------------------------
CREATE TABLE `counters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `counter_number` VARCHAR(30) NOT NULL UNIQUE,
  `counter_name` VARCHAR(100) NOT NULL,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `current_staff_id` INT NULL,
  `designated_service_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_counter_staff` FOREIGN KEY (`current_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_counter_service` FOREIGN KEY (`designated_service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 4: `appointments`
-- Online citizen appointments with time slots
-- ---------------------------------------------------
CREATE TABLE `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `appointment_ref` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `appointment_date` DATE NOT NULL,
  `time_slot` VARCHAR(30) NOT NULL,
  `remarks` TEXT NULL,
  `status` ENUM('pending', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'confirmed',
  `check_in_time` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_app_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 5: `applications`
-- Online passport/visa document submissions
-- ---------------------------------------------------
CREATE TABLE `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_ref` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `applicant_name` VARCHAR(100) NOT NULL,
  `applicant_ic` VARCHAR(50) NOT NULL,
  `applicant_phone` VARCHAR(30) NOT NULL,
  `status` ENUM('submitted', 'under_review', 'approved', 'rejected') NOT NULL DEFAULT 'submitted',
  `remarks` TEXT NULL,
  `submitted_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_appl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appl_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 6: `queues`
-- Daily ticketing & lifecycle (walk_in or appointment check-in)
-- ---------------------------------------------------
CREATE TABLE `queues` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `queue_number` VARCHAR(20) NOT NULL,
  `customer_name` VARCHAR(100) NOT NULL DEFAULT 'Walk-in Customer',
  `customer_ic` VARCHAR(50) NULL,
  `customer_phone` VARCHAR(30) NULL,
  `customer_type` ENUM('walk_in', 'appointment') NOT NULL DEFAULT 'walk_in',
  `service_id` INT NULL,
  `service_type` VARCHAR(100) NOT NULL DEFAULT 'General Service',
  `appointment_id` INT NULL,
  `status` ENUM('waiting', 'calling', 'serving', 'completed', 'skipped') NOT NULL DEFAULT 'waiting',
  `counter_id` INT NULL,
  `staff_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `called_at` DATETIME NULL,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  CONSTRAINT `fk_queue_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_queue_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_queue_counter` FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_queue_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Seed Standard Seed Data
-- ---------------------------------------------------

-- 1. Users (Admin, Staff 1-4, Public User)
-- Passwords:
-- admin: admin123
-- staff1, staff2, staff3, staff4: staff123
-- user: user123
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `ic_passport`, `phone`, `role`, `status`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$I.ku/e7JCufLz9qjjrQQRONXZNMSlrvgrkby0Uwx7V9GmWxBjt6tm', 'System Administrator', 'A00000000', '+60 12-000 0000', 'admin', 'active'),
(2, 'staff1', 'staff1@example.com', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'Staff Nurul Izzati', 'S11111111', '+60 13-111 2222', 'staff', 'active'),
(3, 'staff2', 'staff2@example.com', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'Michael Chen', 'S22222222', '+60 14-333 4444', 'staff', 'active'),
(4, 'staff3', 'staff3@example.com', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'Siti Sarah', 'S33333333', '+60 15-555 6666', 'staff', 'active'),
(5, 'staff4', 'staff4@example.com', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'David Wong', 'S44444444', '+60 16-777 8888', 'staff', 'active'),
(6, 'user', 'user@example.com', '$2y$10$nkGliQL3p2hzRgyr0rxBpucRFisLyCK64eW2wTEKgkhbnbAzylePm', 'Ahmad bin Abdullah', '900101-14-5567', '+60 17-123 4567', 'user', 'active');

-- 2. Services Catalog (Exactly 4 Services, 1:1 with 4 Counters)
INSERT INTO `services` (`id`, `code`, `name`, `description`, `est_duration`, `status`) VALUES
(1, 'PS', 'Passport Services', 'Comprehensive passport renewal, first-time application, and document collection.', 20, 'active'),
(2, 'VA', 'Visa Application', 'Long-term social visit pass, student pass, and entry visa processing.', 25, 'active'),
(3, 'WP', 'Work Permit', 'Employment pass and temporary employment visit pass renewal.', 20, 'active'),
(4, 'IE', 'Immigration Enquiry', 'General inquiries, special passes, compound settlement, and advisory.', 15, 'active');

-- 3. Counters (Exactly 4 Counters, 1:1 with 4 Staff and 4 Services)
INSERT INTO `counters` (`id`, `counter_number`, `counter_name`, `status`, `current_staff_id`, `designated_service_id`) VALUES
(1, 'Counter 01', 'Passport Renewal & Issuance', 'open', 2, 1),
(2, 'Counter 02', 'Visa & Expatriate Services', 'open', 3, 2),
(3, 'Counter 03', 'Work Permit Endorsement', 'open', 4, 3),
(4, 'Counter 04', 'Enquiry & Special Advisory', 'open', 5, 4);

-- 4. Sample Citizen Appointments
INSERT INTO `appointments` (`id`, `appointment_ref`, `user_id`, `service_id`, `appointment_date`, `time_slot`, `remarks`, `status`) VALUES
(1, 'APP-20261124-0001', 6, 1, CURDATE(), '08:30 - 09:30', 'Routine renewal before international travel', 'confirmed'),
(2, 'APP-20261124-0002', 6, 2, CURDATE(), '09:30 - 10:30', 'First time passport issue', 'confirmed'),
(3, 'APP-20261125-0003', 6, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30 - 11:30', 'Social visit visa inquiry', 'confirmed');

-- 5. Sample Citizen Applications
INSERT INTO `applications` (`id`, `application_ref`, `user_id`, `service_id`, `applicant_name`, `applicant_ic`, `applicant_phone`, `status`, `remarks`, `submitted_date`) VALUES
(1, 'IMP-20261121-1001', 6, 1, 'Ahmad bin Abdullah', '900101-14-5567', '+60 17-123 4567', 'approved', 'Documents verified online. Proceed to counter with appointment.', DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(2, 'IMP-20261121-1002', 6, 2, 'Siti Sarah binti Kassim', '950612-10-8890', '+60 19-876 5432', 'submitted', 'Awaiting counter verification.', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(3, 'IMP-20261119-1003', 6, 2, 'John Tan Wei Lun', '881123-08-3345', '+60 16-555 7788', 'approved', 'Visa entry documents confirmed.', DATE_SUB(CURDATE(), INTERVAL 5 DAY));
