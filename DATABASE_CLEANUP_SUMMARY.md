# Database Cleanup Summary - Lunch Columns Removal

**Date**: April 6, 2026  
**Purpose**: Remove unused lunch_in/lunch_out columns and implement automatic 60-minute lunch deduction for shifts > 4 hours

---

## ✅ COMPLETED CHANGES

### 1. **Database Schema** (student_db.sql) - DONE
Removed `lunch_out` and `lunch_in` columns from the `attendance` table:
```sql
-- BEFORE:
`lunch_out` datetime DEFAULT NULL,
`lunch_in` datetime DEFAULT NULL,

-- AFTER: Columns removed
```

**Updated Attendance Table Structure:**
```sql
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `employer_id` int(11) DEFAULT NULL,
  `log_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `effective_start_time` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` enum('Present','Absent') DEFAULT 'Absent',
  `shift_status` enum('on_time','late_grace','adjusted_shift') DEFAULT 'on_time',
  `late_minutes` int(11) DEFAULT 0,
  `verified` tinyint(1) DEFAULT 0,
  `reason` text DEFAULT NULL,
  `daily_task` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

---

### 2. **Query Logic** (includes/supervisor_db.php) - DONE
✅ Updated all 4 query functions with new 60-minute auto-deduction logic:

**New Calculation Logic:**
```sql
CASE 
    WHEN TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out) > 240 THEN 
        TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out) - 60
    ELSE 
        TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
END
```

**Functions Updated:**
1. `get_attendance()` - All 4 branches (both employer scenarios)
2. `get_total_minutes()` - All 3 branches (null employer + 2 company scenarios)

**What Changed:**
- Removed: `a.lunch_out, a.lunch_in` from SELECT clauses
- Removed: `COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)` lunch calculations
- Added: Auto-deduction logic that subtracts 60 minutes when shift > 4 hours (240 minutes)

---

### 3. **Test Data Files** - NEEDS MANUAL FIX

The test data INSERT statements still reference lunch columns. You have two options:

#### **OPTION A: Clean Database & Re-import (RECOMMENDED)**
```sql
-- 1. Delete existing test data
DELETE FROM attendance WHERE log_date >= '2026-03-16' AND log_date <= '2026-03-20';

-- 2. Run corrected SQL below (see "Corrected Test Data - INSERT Statements" section)
```

#### **OPTION B: Keep Existing & Use UPDATE**
```sql
-- This won't work anymore since lunch columns are deleted
-- The database schema change makes this infeasible
```

---

## 📋 CORRECTED TEST DATA - INSERT STATEMENTS

Remove the old INSERT statements from test data files. Use these corrected versions:

### Corrected Column List (NO LUNCH COLUMNS):
```sql
INSERT INTO `attendance` (
    `student_id`, 
    `employer_id`, 
    `log_date`, 
    `time_in`, 
    `effective_start_time`, 
    `time_out`,  -- Removed lunch_out and lunch_in
    `status`, 
    `shift_status`, 
    `late_minutes`, 
    `verified`, 
    `reason`, 
    `daily_task`
) VALUES
```

### Example Records (Cleaned):
```sql
-- BEFORE (with lunch times - DON'T USE):
(1, 1, '2026-03-16', '2026-03-16 08:10:00', '2026-03-16 08:10:00', 
 '2026-03-16 12:00:00', '2026-03-16 13:00:00',  -- LUNCH TIMES - REMOVE THESE
 '2026-03-16 17:05:00', 'Present', 'late_grace', 10, 1, 'Test UAT', 'Task 1'),

-- AFTER (corrected - USE THIS):
(1, 1, '2026-03-16', '2026-03-16 08:10:00', '2026-03-16 08:10:00', 
 '2026-03-16 17:05:00', 'Present', 'late_grace', 10, 1, 'Test UAT', 'Task 1'),
```

---

## 🔧 HOW LUNCH DEDUCTION NOW WORKS

### Before (Manual Entry):
- Supervisors entered lunch_out and lunch_in times
- System calculated: `total_time - (lunch_out - lunch_in)`

### After (Automatic):
- **If shift > 4 hours (240 minutes)**: Automatically deduct 60 minutes
- **If shift ≤ 4 hours**: No deduction
- **No manual entry needed**: lunch_in and lunch_out columns are gone

### Examples:
```
Shift 08:00 - 17:00 = 540 minutes (9 hours)
→ Deduct 60 min (lunch) = 480 minutes = 8 hours ✓

Shift 08:00 - 12:00 = 240 minutes exact (4 hours)  
→ No deduction (equals 4 hours) = 240 minutes = 4 hours ✓

Shift 08:00 - 12:05 = 245 minutes (4.08 hours)
→ Deduct 60 min (lunch) = 185 minutes = 3h 5m ✓
```

---

## 📝 CLEANUP SQL COMMANDS (Run in phpMyAdmin)

### 1. Delete Test Data with Lunch Columns:
```sql
DELETE FROM attendance WHERE log_date BETWEEN '2026-03-16' AND '2026-03-20';
```

### 2. Verify Column Removal:
```sql
DESCRIBE attendance;
-- Should NOT show lunch_out or lunch_in columns
```

### 3. Verify Query Logic Works:
```sql
-- Test the new auto-deduction
SELECT 
    student_id,
    log_date,
    time_in,
    time_out,
    TIMESTAMPDIFF(MINUTE, time_in, time_out) as gross_minutes,
    CASE 
        WHEN TIMESTAMPDIFF(MINUTE, time_in, time_out) > 240 THEN 
            TIMESTAMPDIFF(MINUTE, time_in, time_out) - 60
        ELSE 
            TIMESTAMPDIFF(MINUTE, time_in, time_out)
    END as net_minutes
FROM attendance 
WHERE time_in IS NOT NULL AND time_out IS NOT NULL
LIMIT 10;
```

---

## 📂 FILES MODIFIED

| File | Changes | Status |
|------|---------|--------|
| `database/student_db.sql` | Removed lunch_out, lunch_in columns from CREATE TABLE | ✅ Complete |
| `includes/supervisor_db.php` | Updated 4 query functions with auto-deduction logic | ✅ Complete |
| `database/test_data_march_16_20.sql` | **Column header fixed**, data still needs cleaning | ⚠️ Needs Update |
| `database/test_data_complete.sql` | **Column header fixed**, data still needs cleaning | ⚠️ Needs Update |

---

## 🚀 NEXT STEPS

1. **Backup current database** (if production)
   ```sql
   -- Export via phpMyAdmin or use backup table
   ```

2. **Import updated schema**
   - Make sure `lunch_out` and `lunch_in` columns are removed
   - Run: `ALTER TABLE attendance DROP COLUMN lunch_out, DROP COLUMN lunch_in;`

3. **Clean test data**
   - Delete old test records: `DELETE FROM attendance WHERE log_date >= '2026-03-16';`
   - Import corrected test data with lunch columns removed

4. **Verify system**
   - Check supervisor dashboard displays correct hours
   - Test shift > 4 hours automatically deducts 60 minutes
   - Check shift ≤ 4 hours has NO deduction

---

## ⚠️ IMPORTANT NOTES

- **No manual lunch entry UI needed** - lunch columns are completely removed
- **Automatic deduction rule**: 60 minutes when shift > 240 minutes
- **Data migration**: If you have attendance records with lunch times, they'll be orphaned (lunch_out/lunch_in columns gone)
- **Backward compatible**: Old records without lunch times work fine with new logic
- **All queries updated**: Both admin and supervisor views use new calculation

---

## 📞 VERIFICATION CHECKLIST

- [ ] Database schema updated (lunch columns removed)
- [ ] supervisor_db.php queries updated (auto-deduction logic in place)
- [ ] Old test data deleted
- [ ] New corrected test data imported (without lunch time values)
- [ ] Supervisor dashboard showing correct hours
- [ ] 4+ hour shifts showing 1 hour lunch deduction
- [ ] < 4 hour shifts showing NO deduction
- [ ] Admin can view attendance with new layout

---

**Last Updated**: April 6, 2026  
**Database Version**: 1.1 (Lunch Columns Removed)
