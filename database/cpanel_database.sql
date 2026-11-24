-- ===================================================
-- Queue Management System Database Script for cPanel
-- (Safe for cPanel phpMyAdmin import: No CREATE/USE DB)
-- ===================================================

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
-- ---------------------------------------------------
CREATE TABLE `counters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `counter_number` VARCHAR(30) NOT NULL UNIQUE,
  `counter_name` VARCHAR(100) NOT NULL,
  `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
  `current_staff_id` INT NULL,
  `designated_service_id` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cp_counter_staff` FOREIGN KEY (`current_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_counter_service` FOREIGN KEY (`designated_service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 4: `appointments`
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
  CONSTRAINT `fk_cp_app_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_app_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 5: `applications`
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
  CONSTRAINT `fk_cp_appl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cp_appl_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Table 6: `queues`
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
  CONSTRAINT `fk_cp_queue_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_queue_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_queue_counter` FOREIGN KEY (`counter_id`) REFERENCES `counters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cp_queue_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Seed Standard Seed Data
-- ---------------------------------------------------
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `ic_passport`, `phone`, `role`, `status`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$I.ku/e7JCufLz9qjjrQQRONXZNMSlrvgrkby0Uwx7V9GmWxBjt6tm', 'System Administrator', 'A00000000', '+60 12-000 0000', 'admin', 'active'),
(2, 'staff1', 'staff1@example.com', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'Staff Nurul Izzati', 'S11111111', '+60 13-111 2222', 'staff', 'active'),
(3, 'staff2', 'staff2@example.com', '$2y$10$RxT5eRtaBiGwcz/O2mOaPOFGodbnSjY55sjX.oifxJEVI9LxH8aOu', 'Michael Chen', 'S22222222', '+60 14-333 4444', 'staff', 'active'),
(4, 'user', 'user@example.com', '$2y$10$nkGliQL3p2hzRgyr0rxBpucRFisLyCK64eW2wTEKgkhbnbAzylePm', 'Ahmad bin Abdullah', '900101-14-5567', '+60 17-123 4567', 'user', 'active');

INSERT INTO `services` (`id`, `code`, `name`, `description`, `est_duration`, `status`) VALUES
(1, 'PR', 'Passport Renewal', 'Standard renewal for Malaysian international passport (e-Passport).', 15, 'active'),
(2, 'PA', 'Passport Application', 'New issuance of passport for first-time applicants or replacements.', 30, 'active'),
(3, 'VA', 'Visa Application', 'Long-term social visit pass, student pass, and entry visa processing.', 25, 'active'),
(4, 'WP', 'Work Permit', 'Employment pass and temporary employment visit pass renewal.', 20, 'active'),
(5, 'IE', 'Immigration Enquiry', 'General inquiries, special passes, compound settlement, and advisory.', 10, 'active');

INSERT INTO `counters` (`id`, `counter_number`, `counter_name`, `status`, `current_staff_id`, `designated_service_id`) VALUES
(1, 'Counter 01', 'Passport Renewal & Issuance', 'open', 2, 1),
(2, 'Counter 02', 'Visa & Expatriate Services', 'open', 3, 3),
(3, 'Counter 03', 'Work Permit Endorsement', 'open', NULL, 4),
(4, 'Counter 04', 'Enquiry & Special Cases', 'open', NULL, 5);

INSERT INTO `appointments` (`id`, `appointment_ref`, `user_id`, `service_id`, `appointment_date`, `time_slot`, `remarks`, `status`) VALUES
(1, 'APP-20261124-0001', 4, 1, CURDATE(), '08:30 - 09:30', 'Routine renewal before international travel', 'confirmed'),
(2, 'APP-20261124-0002', 4, 2, CURDATE(), '09:30 - 10:30', 'First time passport issue', 'confirmed'),
(3, 'APP-20261125-0003', 4, 3, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:30 - 11:30', 'Social visit visa inquiry', 'confirmed');

INSERT INTO `applications` (`id`, `application_ref`, `user_id`, `service_id`, `applicant_name`, `applicant_ic`, `applicant_phone`, `status`, `remarks`, `submitted_date`) VALUES
(1, 'IMP-20261121-1001', 4, 1, 'Ahmad bin Abdullah', '900101-14-5567', '+60 17-123 4567', 'approved', 'Documents verified online. Proceed to counter with appointment.', DATE_SUB(CURDATE(), INTERVAL 3 DAY)),
(2, 'IMP-20261121-1002', 4, 2, 'Siti Sarah binti Kassim', '950612-10-8890', '+60 19-876 5432', 'submitted', 'Awaiting counter verification.', DATE_SUB(CURDATE(), INTERVAL 2 DAY)),
(3, 'IMP-20261119-1003', 4, 3, 'John Tan Wei Lun', '881123-08-3345', '+60 16-555 7788', 'approved', 'Visa entry documents confirmed.', DATE_SUB(CURDATE(), INTERVAL 5 DAY));
