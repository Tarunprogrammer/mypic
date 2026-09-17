-- ===================================================
-- MYPIC - AI Face Recognition Photo Portal Database Schema
-- Database: if0_41878641_mypic_db
-- ===================================================

-- ---------------------------------------------------
-- 1. Admins Table
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) DEFAULT 'Administrator',
    `email` VARCHAR(100) DEFAULT NULL,
    `role` VARCHAR(20) DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 2. Events / Albums Table
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(160) NOT NULL UNIQUE,
    `description` TEXT DEFAULT NULL,
    `event_date` DATE DEFAULT NULL,
    `location` VARCHAR(150) DEFAULT NULL,
    `cover_image` VARCHAR(255) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `photo_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 3. Photos Table
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `photos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_id` INT DEFAULT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(255) NOT NULL,
    `thumbnail_path` VARCHAR(255) NOT NULL,
    `file_size` BIGINT DEFAULT 0,
    `width` INT DEFAULT 0,
    `height` INT DEFAULT 0,
    `face_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_event_id` (`event_id`),
    CONSTRAINT `fk_photos_event` FOREIGN KEY (`event_id`) 
        REFERENCES `events`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 4. Photo Faces (128-d Vector Embeddings) Table
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `photo_faces` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `photo_id` INT NOT NULL,
    `face_index` INT DEFAULT 0,
    `box_x` FLOAT DEFAULT 0,
    `box_y` FLOAT DEFAULT 0,
    `box_w` FLOAT DEFAULT 0,
    `box_h` FLOAT DEFAULT 0,
    `score` FLOAT DEFAULT 0,
    `descriptor` MEDIUMTEXT NOT NULL COMMENT 'JSON array of 128 float numbers',
    `face_thumbnail` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_photo_id` (`photo_id`),
    CONSTRAINT `fk_faces_photo` FOREIGN KEY (`photo_id`) 
        REFERENCES `photos`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- 5. System Settings Table
-- ---------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
    `key_name` VARCHAR(50) PRIMARY KEY,
    `key_value` TEXT NOT NULL,
    `description` VARCHAR(255) DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------
-- Seed Default Admin & Settings
-- ---------------------------------------------------
-- Default admin credentials: tarun / tarun@7648
INSERT INTO `admins` (`username`, `password`, `name`, `email`)
VALUES ('tarun', '$2y$10$yz4F2RoxMvkpYCwLgPaPT.gSHC1PErq6i1FhTlbRYVjO.eCumdDY.', 'Eppe Tarun', 'eppetarun@gmail.com')
ON DUPLICATE KEY UPDATE `password`=VALUES(`password`), `name`=VALUES(`name`);

INSERT INTO `settings` (`key_name`, `key_value`, `description`) VALUES
('site_name', 'MYPIC Face Recognition Portal', 'Application Title'),
('match_threshold', '0.55', 'Face recognition distance threshold (0.45=strict, 0.60=lenient)'),
('max_upload_size_mb', '25', 'Max photo upload file size in Megabytes'),
('allow_public_search', '1', 'Allow guest users to scan faces without login'),
('gemini_api_key', 'AQ.Ab8RN6JxLJvDNhOdzs-VQgsAPQCS3iHuiu8MqKqw6Gi4MjyvHg', 'Google Gemini AI API Key for face & photo analysis')
ON DUPLICATE KEY UPDATE `key_name`=`key_name`;

INSERT INTO `events` (`title`, `slug`, `description`, `event_date`, `location`, `is_active`)
VALUES ('General Album', 'general', 'Default album for indexed photos', CURDATE(), 'Main Hall', 1)
ON DUPLICATE KEY UPDATE `slug`=`slug`;
