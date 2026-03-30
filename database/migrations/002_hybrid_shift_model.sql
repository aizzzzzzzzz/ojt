-- =====================================================
-- HYBRID SHIFT MODEL MIGRATION
-- =====================================================
-- Adds support for:
-- - Shift status tracking (on_time, late_grace, adjusted_shift)
-- - Effective start time for adjusted shifts
-- - Late minutes tracking
-- - Shift change request system
-- =====================================================

-- Step 1: Add columns to attendance table
-- These columns are NULL-able and have defaults for backwards compatibility
ALTER TABLE `attendance` 
ADD COLUMN `shift_status` ENUM('on_time', 'late_grace', 'adjusted_shift') DEFAULT 'on_time' AFTER `status`,
ADD COLUMN `effective_start_time` DATETIME DEFAULT NULL AFTER `time_in`,
ADD COLUMN `late_minutes` INT DEFAULT 0 AFTER `shift_status`;

-- Step 2: Create shift_change_requests table
CREATE TABLE IF NOT EXISTS `shift_change_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `request_date` DATE NOT NULL,
  `requested_shift_start` TIME NOT NULL,
  `requested_shift_end` TIME NOT NULL,
  `reason` TEXT,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` INT DEFAULT NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `review_notes` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES employers(employer_id) ON DELETE SET NULL,
  INDEX `idx_student_id` (`student_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_request_date` (`request_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Step 3: Add indexes for performance
ALTER TABLE `attendance` 
ADD INDEX `idx_shift_status` (`shift_status`),
ADD INDEX `idx_log_date_status` (`log_date`, `shift_status`);

-- Step 4: Update existing records (optional - sets default values)
-- This ensures all existing records have proper default values
UPDATE `attendance` 
SET `shift_status` = 'on_time', 
    `late_minutes` = 0 
WHERE `shift_status` IS NULL;

-- =====================================================
-- VERIFICATION QUERIES (Run these to confirm migration)
-- =====================================================

-- Check if columns were added:
-- DESCRIBE attendance;

-- Check if table was created:
-- SHOW TABLES LIKE 'shift_change_requests';

-- =====================================================
-- ROLLBACK (If you need to undo this migration)
-- =====================================================

-- ALTER TABLE `attendance` 
-- DROP COLUMN `shift_status`,
-- DROP COLUMN `effective_start_time`,
-- DROP COLUMN `late_minutes`;

-- DROP TABLE IF EXISTS `shift_change_requests`;
