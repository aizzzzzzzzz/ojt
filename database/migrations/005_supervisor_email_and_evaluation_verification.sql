ALTER TABLE `employers`
ADD COLUMN `email` VARCHAR(255) DEFAULT NULL AFTER `username`;

CREATE TABLE IF NOT EXISTS `evaluation_verification_codes` (
    `verification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `verification_key` VARCHAR(64) NOT NULL UNIQUE,
    `employer_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `sent_to_email` VARCHAR(255) NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `attempts` TINYINT NOT NULL DEFAULT 0,
    `max_attempts` TINYINT NOT NULL DEFAULT 5,
    `expires_at` DATETIME NOT NULL,
    `verified_at` DATETIME DEFAULT NULL,
    `used_at` DATETIME DEFAULT NULL,
    `evaluation_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_eval_verify_lookup` (`verification_key`),
    INDEX `idx_eval_verify_owner` (`employer_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
