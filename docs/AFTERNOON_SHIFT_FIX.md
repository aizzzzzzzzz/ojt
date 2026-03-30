# 🕐 AFTERNOON SHIFT / HALF-DAY STUDENT SUPPORT

## Problem Solved

**Before:** Students with afternoon shifts (e.g., 1:00 PM start time) couldn't Time In because the system only allowed Time In within the grace period of the default morning work start time (e.g., 8:00 AM + 10 min grace = until 8:10 AM).

**After:** The system now **automatically detects afternoon shifts** and applies the grace period from the student's actual work start time.

---

## How It Works

### Automatic Detection
The system checks if a student's work start time is **12:00 PM (noon) or later**:

```php
$is_afternoon_shift = $work_start_dt->format('H') >= 12;
```

### Grace Period Calculation
For afternoon shift students:
- **Work Start:** 1:00 PM (13:00)
- **Grace Period:** 10 minutes (configurable by supervisor)
- **Time In Window:** 1:00 PM - 1:10 PM

For morning shift students:
- **Work Start:** 8:00 AM
- **Grace Period:** 10 minutes (configurable by supervisor)
- **Time In Window:** 8:00 AM - 8:10 AM

---

## What Changed

### Files Modified
1. **`public/student_dashboard.php`**
   - Added afternoon shift detection logic (2 locations)
   - Variables: `$is_afternoon_shift`

2. **`templates/attendance_tab.php`**
   - Added visual indicator for afternoon shift students
   - Shows: "(Afternoon shift detected - work starts at 13:00)"

---

## Configuration

### For Supervisors
You can configure work hours per company/employer:

1. Go to **Supervisor Dashboard**
2. Find **Grace Period Configuration**
3. Set:
   - **Work Start Time** (e.g., 08:00 for morning, 13:00 for afternoon)
   - **Work End Time**
   - **Late Grace Period** (1-30 minutes)
   - **EOD Grace Period** (1-6 hours)

### Student Schedule Assignment
Students inherit their work schedule from:
- **Direct assignment** (created_by employer)
- **Company assignment** (company_id)

---

## Examples

### Example 1: Morning Shift Student
- **Work Start:** 08:00
- **Grace Period:** 10 minutes
- **Can Time In:** 08:00 - 08:10
- **Message:** "Time In: within 10 minutes of work start"

### Example 2: Afternoon Shift Student
- **Work Start:** 13:00 (1:00 PM)
- **Grace Period:** 10 minutes
- **Can Time In:** 13:00 - 13:10
- **Message:** "Time In: within 10 minutes of work start (Afternoon shift detected - work starts at 13:00)"

### Example 3: Half-Day Student (1:00 PM - 5:00 PM)
- **Work Start:** 13:00
- **Work End:** 17:00
- **Grace Period:** 10 minutes
- **EOD Grace:** 3 hours
- **Can Time In:** 13:00 - 13:10
- **Can do other actions:** until 20:00 (8 PM)

---

## Error Messages

### If Student Tries to Time In Too Early
```
Time In is not allowed yet. Work starts at 13:00.
```

### If Student Misses Grace Period
```
Time In is no longer allowed. The 10-minute grace period ended at 13:10.
```

### If Student Tries Other Actions After Cutoff
```
Attendance actions are no longer available for today. Cutoff was 20:00.
```

---

## Special Cases

### Demo Mode
When `DEMO_DISABLE_ATTENDANCE_GRACE_PERIOD` is enabled:
- All time restrictions are disabled
- Students can Time In/Out at any time
- Message shown: "Demo mode: attendance time limits are temporarily disabled."

### Weekend/Holiday
The system doesn't automatically block weekends/holidays. Supervisors must:
- Mark absences manually, or
- Configure attendance verification rules

---

## Testing

### Test Afternoon Shift Student
1. Create/edit student with work_start = '13:00'
2. Login as student
3. Check attendance page shows: "(Afternoon shift detected - work starts at 13:00)"
4. Try to Time In at 12:50 PM → Should fail (too early)
5. Try to Time In at 1:05 PM → Should succeed
6. Try to Time In at 1:15 PM → Should fail (grace period ended)

### Test Morning Shift Student
1. Create/edit student with work_start = '08:00'
2. Login as student
3. Check attendance page shows normal message (no afternoon shift note)
4. Time In behavior unchanged

---

## Database Fields

Work schedule is stored in `employers` table:
```sql
work_start TIME          -- e.g., '08:00:00' or '13:00:00'
work_end TIME            -- e.g., '17:00:00' or '21:00:00'
late_grace_minutes INT   -- e.g., 10
eod_grace_hours INT      -- e.g., 3
```

Students inherit via:
- `students.created_by` → employer_id
- `students.company_id` → company_id

---

## Future Enhancements

Consider adding:
- [ ] Multiple shift support (student can switch between morning/afternoon)
- [ ] Holiday schedule configuration
- [ ] Overtime tracking (hours beyond work_end)
- [ ] Break time configuration (lunch duration)
- [ ] Flexible schedule (different work hours per day)
