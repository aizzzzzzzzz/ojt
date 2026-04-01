# 📋 InfinityFree Setup Guide - Auto-Mark Absent

## ⚠️ Important: InfinityFree Limitations

InfinityFree **does not support cron jobs**, so the automatic 7 PM scheduling requires an external service.

---

## ✅ Option 1: Manual Execution (Easiest)

Just click the button in your supervisor dashboard daily:

1. Login to supervisor dashboard
2. Scroll to **"⚠️ Auto-Mark Absent"** section
3. Click **"Run Auto-Mark Now"**
4. View results page

**Pros:** No setup required
**Cons:** Must remember to do it daily

---

## ✅ Option 2: External Cron Service (Recommended)

### Step-by-Step: Cron-Job.org Setup

#### 1️⃣ Get Your Token
The cron token is already generated: `auto_absent_a7f3c9e2b1d4f8a6`

If you need to check it:
- File location: `/htdocs/private/auto_absent_token.php`

#### 2️⃣ Sign Up for Cron-Job.org
1. Go to: https://cron-job.org
2. Click **"Sign Up"** (it's free)
3. Verify your email

#### 3️⃣ Create Cron Job
1. Login to Cron-Job.org
2. Click **"Create cronjob"**
3. Fill in the form:

| Field | Value |
|-------|-------|
| **Title** | `Auto-Mark Absent - OJT System` |
| **URL** | `https://your-domain.000webhostapp.com/public/auto_mark_absent_cron.php?token=auto_absent_a7f3c9e2b1d4f8a6` |
| **Minute** | `0` |
| **Hour** | `19` |
| **Day** | `*` |
| **Month** | `*` |
| **Weekday** | `*` |
| **Timezone** | `Asia/Manila` (UTC+8) |

4. Click **"Create cronjob"**

#### 4️⃣ Test It
1. In Cron-Job.org dashboard, find your cron job
2. Click **"Run now"** button
3. Check your `attendance` table in phpMyAdmin
4. You should see new absent records

#### 5️⃣ Monitor
- Cron-Job.org will email you if the cron fails
- Check execution history in your Cron-Job.org dashboard
- Verify attendance records daily

---

## 🔧 Replace With Your Domain

**Important:** Replace `your-domain.000webhostapp.com` with your actual InfinityFree domain!

Example:
```
❌ https://your-domain.000webhostapp.com/...
✅ https://ojt-system.000webhostapp.com/...
```

---

## 📊 What Happens at 7 PM?

The cron job will:
1. ✅ Fetch all students from database
2. ✅ Check each student's attendance for today
3. ✅ Mark as **Absent** if:
   - No attendance record exists, OR
   - Present but not verified by supervisor
4. ✅ Log all actions in audit trail
5. ✅ Send results back to Cron-Job.org

---

## ️ Troubleshooting

### Cron job fails with "403 Forbidden"
- **Cause:** Token is missing or wrong
- **Fix:** Check token in URL matches `private/auto_absent_token.php`

### Cron job fails with "404 Not Found"
- **Cause:** Wrong URL path
- **Fix:** Verify your domain name and path are correct

### Students not being marked absent
- **Cause:** Already have attendance or verified
- **Fix:** Check `attendance` table - they may already be marked

### Time is wrong (not 7 PM Manila time)
- **Cause:** Wrong timezone setting
- **Fix:** In Cron-Job.org, select `Asia/Manila` timezone

---

## 📞 Support

If you need help:
1. Check `docs/auto_mark_absent.md` for full documentation
2. Review Cron-Job.org FAQ: https://cron-job.org/en/faq/
3. Check your database `audit_logs` table for execution history

---

## 🎯 Quick Reference

**Manual URL:**
```
https://your-domain.000webhostapp.com/public/auto_mark_absent.php
```

**Cron URL:**
```
https://your-domain.000webhostapp.com/public/auto_mark_absent_cron.php?token=auto_absent_a7f3c9e2b1d4f8a6
```

**Cron Schedule:**
```
Minute: 0
Hour: 19 (7 PM)
Day: *
Month: *
Weekday: *
Timezone: Asia/Manila
```

**Token:**
```
auto_absent_a7f3c9e2b1d4f8a6
```
