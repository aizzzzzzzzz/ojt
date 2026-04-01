-- =====================================================
-- SHIFT CHANGE REQUESTS - ADDITIONAL COLUMNS
-- =====================================================
-- Adds:
-- - `used` flag to track if approved shift was used
-- - `approved_at` timestamp for sorting by approval time
-- =====================================================

-- Add used column
    ALTER TABLE `shift_change_requests`
    ADD COLUMN `used` TINYINT(1) DEFAULT 0 AFTER `status`;

    -- Add approved_at column (for sorting by most recent approval)
    ALTER TABLE `shift_change_requests`
    ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `reviewed_at`;

    -- Add index for used column
    ALTER TABLE `shift_change_requests`
    ADD INDEX `idx_used` (`used`);

    -- Update approved_at for existing approved requests
    UPDATE `shift_change_requests`
    SET `approved_at` = `reviewed_at`
    WHERE `status` = 'approved' AND `reviewed_at` IS NOT NULL;

-- =====================================================
-- ROLLBACK (If you need to undo)
-- =====================================================
-- ALTER TABLE `shift_change_requests` DROP COLUMN `used`;
-- ALTER TABLE `shift_change_requests` DROP COLUMN `approved_at`;
