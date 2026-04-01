# Auto-Mark Absent Feature

## Overview
This feature automatically marks students as absent at 7:00 PM if:
1. They have **no attendance record** for the day
2. OR their attendance is marked as "Present" but **not verified** by the supervisor

## ⚠️ InfinityFree Hosting Limitations

**Important:** InfinityFree does **NOT support cron jobs**. The automatic 7 PM scheduling will NOT work on InfinityFree.

### What Works on InfinityFree:
- ✅ **Manual execution** - Click "Run Auto-Mark Now" button
- ✅ **All features** - Marking absent, audit logging, etc.

### What Doesn't Work:
- ❌ **Automatic 7 PM scheduling** - No cron support
- ❌ **CLI execution** - No command line access

### Solution for InfinityFree:
Use an **external cron service** like [Cron-Job.org](https://cron-job.org) (free) to call your script daily.

---

## Files Created

### 1. `public/auto_mark_absent.php`
- **Manual trigger** - Can be accessed via browser by admin
- Shows results page with statistics
- Can be forced to run before 7 PM using `?force=1` parameter
- **Access**: Only admins and employers can run this

### 2. `public/auto_mark_absent_cron.php`
- **Automated cron job** - Runs daily at 7:00 PM
- Returns JSON response
- Requires security token for web access
- Can run via CLI without token

## Setup Instructions

### For InfinityFree Hosting

#### Option 1: External Cron Service (Recommended)

**Step 1: Get Your Cron Token**
1. In your hosting file manager, navigate to: `/htdocs/private/auto_absent_token.php`
2. Note the token value (e.g., `auto_absent_a7f3c9e2b1d4f8a6`)

**Step 2: Setup Cron-Job.org**
1. Go to [https://cron-job.org](https://cron-job.org) and sign up (free)
2. Click "Create cronjob"
3. Configure:
   - **Title**: Auto-Mark Absent
   - **URL**: `https://your-domain.000webhostapp.com/public/auto_mark_absent_cron.php?token=YOUR_TOKEN_HERE`
   - **Execution schedule**: 
     - Minute: `0`
     - Hour: `19` (7 PM)
     - Day: `*`
     - Month: `*`
     - Weekday: `*`
   - **Timezone**: Select `Asia/Manila` (Philippines)
4. Click "Create cronjob"

**Step 3: Test**
- Wait for scheduled time OR
- Click "Run now" in Cron-Job.org dashboard
- Check results in your database `attendance` table

#### Option 2: Manual Daily Execution
Simply visit daily at 7 PM:
```
https://your-domain.000webhostapp.com/public/auto_mark_absent.php
```

---

### For Local Development (XAMPP)

#### Option 1: Manual Execution
Simply visit in your browser:
```
http://localhost/ojt/public/auto_mark_absent.php
```

#### Option 2: Windows Task Scheduler
1. Open **Task Scheduler** in Windows
2. Create a new task:
   - **Name**: Auto-Mark Absent
   - **Trigger**: Daily at 7:00 PM
   - **Action**: Start a program
     - Program: `C:\xampp\php\php.exe`
     - Arguments: `c:\xampp\htdocs\ojt\public\auto_mark_absent_cron.php`
     - Start in: `c:\xampp\htdocs\ojt\public`

### For Production (Other Hosting Providers)

If your hosting supports cron jobs (cPanel, VPS, etc.):

#### cPanel Cron Jobs
1. Login to cPanel
2. Go to **Cron Jobs**
3. Add new cron job:
   ```
   Command: php /home/username/public_html/public/auto_mark_absent_cron.php
   Schedule: 0 19 * * *
   ```

#### VPS/Dedicated Server
```bash
crontab -e
# Add this line:
0 19 * * * php /var/www/html/public/auto_mark_absent_cron.php
```

---

## Cron Token Setup

1. The token file `private/auto_absent_token.php` is auto-generated
2. If it doesn't exist, create it with a secure token:
   ```php
   <?php
   return 'your_secure_random_token_here';
   ?>
   ```

3. Update `private/config.php` environment variable:
   ```
   AUTO_ABSENT_CRON_TOKEN=your_secure_random_token_here
   ```

## How It Works

### At 7:00 PM Daily:

1. **Fetches all students** from the database
2. **Checks each student's attendance** for the current day
3. **Marks as Absent** if:
   - No attendance record exists → Status: `Absent`, Reason: "Auto-marked: No attendance by 7:00 PM"
   - Present but unverified → Status changed to `Absent`, Reason: "Auto-marked: Unverified by 7:00 PM"
4. **Skips** students who:
   - Already have verified attendance
   - Were already marked absent manually

### Results Logged:
- Total students checked
- Number marked absent (no attendance)
- Number already present (verified)
- Number already absent
- Number changed from unverified to absent
- All actions logged in `audit_logs` table

## Testing

### Test Before 7 PM
Force the script to run immediately:
```
http://localhost/ojt/public/auto_mark_absent.php?force=1
```

### Test Cron Job
```bash
# Via CLI
php c:\xampp\htdocs\ojt\public\auto_mark_absent_cron.php

# Or with force parameter
php c:\xampp\htdocs\ojt\public\auto_mark_absent_cron.php?force=1
```

## Admin Dashboard Integration

A card has been added to the admin dashboard:
- **⚠ Auto-Mark Absent** - Click to run manually
- Opens in new tab to show results
- Red button to indicate critical action

## Database Changes

No new tables required. The script uses the existing `attendance` table:
- Updates `status` to 'Absent'
- Adds `reason` field with auto-mark explanation
- `verified` remains 0 (unverified) for auto-marked records

## Audit Trail

All auto-mark actions are logged in `audit_logs`:
- Action: "Auto Mark Absent"
- Target: Student ID and name
- Timestamp: When the action was performed

## Best Practices

1. **Supervisors should verify attendance daily** before 7 PM
2. **Review auto-marked absences** the next day
3. **Manual overrides** are still possible via `mark_absent.php`
4. **Check logs regularly** in admin dashboard

## Troubleshooting

### Script doesn't run
- Check if token is set in `private/auto_absent_token.php`
- Verify `private/config.php` has `$auto_absent_cron_token`
- Check PHP error logs

### Students not being marked
- Ensure student records exist in `students` table
- Check if attendance already exists for the day
- Verify timezone is set to 'Asia/Manila'

### Time issues
- Server time might differ from local time
- Script uses 'Asia/Manila' timezone
- Adjust cron schedule accordingly

## Security

- ✅ Token-based authentication for cron jobs
- ✅ Admin/employer access only for manual execution
- ✅ Audit logging for all actions
- ✅ SQL injection protection via prepared statements
- ✅ CSRF protection on manual execution
