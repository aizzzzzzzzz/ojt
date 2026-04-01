-- =====================================================
-- SHIFT CHANGE REQUESTS - COMPLETE SETUP
-- =====================================================
-- Run this in phpMyAdmin to set up approved shift changes
-- =====================================================

-- Step 1: Add missing columns to shift_change_requests
ALTER TABLE `shift_change_requests`
ADD COLUMN `used` TINYINT(1) DEFAULT 0 AFTER `status`,
ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `reviewed_at`,
ADD INDEX `idx_used` (`used`);

-- Step 2: Update approved_at for existing approved requests
UPDATE `shift_change_requests`
SET `approved_at` = `reviewed_at`
WHERE `status` = 'approved' AND `reviewed_at` IS NOT NULL;

-- =====================================================
-- VERIFICATION
-- =====================================================
-- Run this to check if columns were added:
-- DESCRIBE shift_change_requests;

-- =====================================================
-- HOW IT WORKS
-- =====================================================
/*
1. Student requests shift change for a specific date
2. Supervisor approves → status='approved', approved_at=NOW()
3. On the request date, student dashboard shows:
   - Green banner with approved shift times
   - Time In button enabled at NEW start time
4. Student clicks Time In → used=1 (can't reuse)
5. Next day → back to normal schedule
*/
-- =====================================================
