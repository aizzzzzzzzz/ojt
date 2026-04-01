# Approved Shift Changes - Complete Feature

## Overview
Students can now request shift changes, and once approved by their supervisor, the system automatically:
1. Shows a visual indicator on their dashboard
2. Allows Time In at the **new** start time (not the original)
3. Handles overnight shifts (e.g., 10PM → 7AM next day)
4. Marks the shift as "used" after Time In

---

## Features Implemented

### ✅ 1. Most Recent Approval Used
If multiple approved shifts exist for the same date, the **most recently approved** one is used.

**Query:** `ORDER BY approved_at DESC LIMIT 1`

### ✅ 2. Visual Indicator
A green banner shows at the top of the Attendance tab:
```
┌──────────────────────────────────────────────────┐
│ ✅ Approved Shift Change Active Today            │
│                                                  │
│ Your shift today: 12:00 PM - 09:00 PM           │
│ (Overnight shift - ends next day)               │
│                                                  │
│ Normal schedule: 08:00 AM - 05:00 PM            │
└──────────────────────────────────────────────────┘
```

### ✅ 3. Mark as Used
After the student times in:
- `used` flag is set to `1`
- Shift change cannot be reused
- Next day returns to normal schedule

### ✅ 4. Overnight Shifts Supported
Handles shifts that span midnight:
```
Example: 10:00 PM → 7:00 AM (next day)
- System detects: shift_start > shift_end
- Applies the approved times correctly
- Shows "(Overnight shift - ends next day)" badge
```

---

## Database Changes

### New Columns Added:
```sql
ALTER TABLE shift_change_requests ADD COLUMN:
- `used` TINYINT(1) DEFAULT 0       -- Track if shift was used
- `approved_at` TIMESTAMP NULL      -- When supervisor approved
- INDEX `idx_used` (`used`)         -- For performance
```

---

## How It Works (Flow)

### Student Side:
```
1. Student requests shift change
   ↓
2. Waits for supervisor approval
   ↓
3. On approved date:
   - Sees green banner with new times
   - Time In button works at NEW start time
   - Other buttons unlock after Time In
   ↓
4. After Time In:
   - Shift marked as "used"
   - Can use Lunch Out, Lunch In, Time Out normally
```

### Supervisor Side:
```
1. Opens "Manage Shift Change Requests"
   ↓
2. Sees pending requests
   ↓
3. Clicks "Approve" or "Reject"
   ↓
4. If approved:
   - status = 'approved'
   - approved_at = NOW()
   - Student sees new times on dashboard
```

---

## Files Modified

### Backend:
- ✅ `public/student_dashboard.php`
  - Added `get_approved_shift_change()` function
  - Checks for approved shifts on load
  - Overrides work_start/work_end if approved
  - Marks shift as used after Time In

### Frontend:
- ✅ `templates/attendance_tab.php`
  - Shows green banner if approved shift active
  - Displays both new and original times
  - Shows overnight shift indicator

### Database:
- ✅ `public/manage_shift_requests.php`
  - Sets `approved_at` when approving
  - Sets `approved_at = NULL` when rejecting

---

## Setup Instructions

### Step 1: Run Database Migration
1. Open phpMyAdmin: http://localhost/phpmyadmin
2. Select database: `student_db`
3. Click "SQL" tab
4. Paste and run:
```sql
-- From: database/setup_approved_shifts.sql
ALTER TABLE `shift_change_requests`
ADD COLUMN `used` TINYINT(1) DEFAULT 0 AFTER `status`,
ADD COLUMN `approved_at` TIMESTAMP NULL DEFAULT NULL AFTER `reviewed_at`,
ADD INDEX `idx_used` (`used`);

UPDATE `shift_change_requests`
SET `approved_at` = `reviewed_at`
WHERE `status` = 'approved' AND `reviewed_at` IS NOT NULL;
```

### Step 2: Upload Modified Files
- `public/student_dashboard.php`
- `templates/attendance_tab.php`
- `public/manage_shift_requests.php`

### Step 3: Test the Feature

#### Test as Student:
1. Login as student
2. Request a shift change for **today**
3. Wait for supervisor approval
4. After approval:
   - See green banner
   - Time In should work at new time
5. After Time In:
   - Check database: `used = 1`

#### Test as Supervisor:
1. Login as supervisor
2. Go to "Manage Shift Change Requests"
3. Approve a pending request
4. Check if student's dashboard shows new times

---

## Testing Scenarios

### Scenario 1: Normal Shift Change
```
Original: 8:00 AM - 5:00 PM
Requested: 12:00 PM - 9:00 PM
Result: ✅ Student can time in at 12 PM
```

### Scenario 2: Overnight Shift
```
Original: 8:00 AM - 5:00 PM
Requested: 10:00 PM - 7:00 AM (next day)
Result: ✅ Shows overnight badge, works correctly
```

### Scenario 3: Multiple Approvals (Same Day)
```
Request #1: 8AM → 10AM (approved at 9:00 AM)
Request #2: 8AM → 12PM (approved at 10:30 AM) ← Used
Result: ✅ Most recent approval (12 PM) is used
```

### Scenario 4: Already Used Shift
```
Student used approved shift yesterday
Today: Back to normal schedule
Result: ✅ No banner, original times apply
```

---

## Edge Cases Handled

| Case | Handling |
|------|----------|
| Multiple approved shifts | Uses most recent (`approved_at DESC`) |
| Shift spans midnight | Detects `shift_start > shift_end`, shows badge |
| Already used shift | `used = 1` prevents reuse |
| Pending shift | Ignored, uses original schedule |
| Rejected shift | Ignored, uses original schedule |
| No approved shifts | Uses default schedule |

---

## Database Queries

### Check for Today's Approved Shift:
```sql
SELECT * FROM shift_change_requests
WHERE student_id = ?
  AND request_date = CURDATE()
  AND status = 'approved'
  AND used = 0
ORDER BY approved_at DESC
LIMIT 1;
```

### Mark as Used:
```sql
UPDATE shift_change_requests
SET used = 1
WHERE id = ?;
```

### View All Used Shifts:
```sql
SELECT scr.*, s.first_name, s.last_name
FROM shift_change_requests scr
JOIN students s ON scr.student_id = s.student_id
WHERE scr.used = 1
ORDER BY scr.requested_at DESC;
```

---

## Troubleshooting

### Problem: Banner doesn't show
**Check:**
1. Shift is approved (status = 'approved')
2. Request date is today
3. Shift is not yet used (used = 0)
4. Files uploaded correctly

### Problem: Time In still disabled
**Check:**
1. Current time is within grace period of NEW start time
2. Approved shift is for TODAY (not future date)
3. Browser cache cleared

### Problem: Shift not marked as used
**Check:**
1. Student actually clicked Time In
2. Database column `used` exists
3. No SQL errors in logs

---

## Future Enhancements

Potential improvements:
- [ ] Email notification when shift is approved
- [ ] SMS reminder before shift starts
- [ ] Recurring shift changes (every Monday, etc.)
- [ ] Shift change history view for students
- [ ] Bulk approve for supervisors
- [ ] Calendar view of approved shifts

---

## Summary

✅ **Feature Complete!**

Students can now:
- Request shift changes
- See approved changes on dashboard
- Time in at NEW start time
- Use overnight shifts
- One-time use per approval

Supervisors can:
- Approve/reject requests
- See most recent approval used
- Track which shifts were used

System automatically:
- Uses most recent approval
- Handles overnight shifts
- Marks as used after Time In
- Returns to normal next day
