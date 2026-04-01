# Shift Change Request Feature

## Overview
Students can request shift changes, and supervisors can approve or reject these requests through the dashboard.

---

## For Students

### How to Request a Shift Change:

1. **Go to Student Dashboard**
2. **Click "📅 Request Shift Change"** button in the Attendance Actions section
3. **Fill out the form:**
   - **Request Type:** One-time or Recurring
   - **Date:** When the shift change applies
   - **Shift Start Time:** New start time
   - **Shift End Time:** New end time
   - **Reason:** Why you need the shift change
4. **Submit** the request
5. **Wait for supervisor approval**

### Status Meanings:
- **Pending** - Waiting for supervisor review
- **Approved** - Supervisor approved the change
- **Rejected** - Supervisor rejected the request (check review notes)

---

## For Supervisors

### How to Manage Shift Requests:

1. **Go to Supervisor Dashboard**
2. **Scroll to "Attendance Records" section**
3. **Click "📅 Manage Shift Change Requests"** button
4. **Review pending requests:**
   - Student name
   - Requested date
   - Current shift vs Requested shift
   - Reason for change
5. **Approve or Reject:**
   - Click **"Approve"** ✅ to accept the request
   - Click **"Reject"** ❌ to deny the request
   - Add review notes (optional but recommended)
6. **View history** of processed requests

### Where to Find the Button:

In **Supervisor Dashboard** → **Attendance Records** section:

```
┌─────────────────────────────────────────────────┐
│ ⚠️ Auto-Mark Absent                             │
│                                                  │
│ [Run Auto-Mark Now] [Manually Mark Absent]     │
│ [📅 Manage Shift Change Requests] ← NEW!        │
└─────────────────────────────────────────────────┘
```

---

## Features

### Student Side:
- ✅ Request one-time or recurring shift changes
- ✅ View status of all requests
- ✅ See approval/rejection with supervisor notes
- ✅ Cannot edit/delete pending requests

### Supervisor Side:
- ✅ View all pending requests from their students
- ✅ Approve or reject requests
- ✅ Add review notes
- ✅ View history of processed requests
- ✅ Audit log of all actions

---

## Database Table

Uses `shift_change_requests` table:

```sql
CREATE TABLE shift_change_requests (
    id INT PRIMARY KEY,
    student_id INT,
    request_date DATE,
    requested_shift_start TIME,
    requested_shift_end TIME,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected'),
    requested_at TIMESTAMP,
    reviewed_by INT,
    reviewed_at TIMESTAMP,
    review_notes TEXT
);
```

---

## Files

### Student:
- `public/request_shift_change.php` - Shift change request form
- `templates/attendance_tab.php` - Added "Request Shift Change" button

### Supervisor:
- `public/manage_shift_requests.php` - Manage and approve/reject requests
- `public/supervisor_dashboard.php` - Added "Manage Shift Change Requests" button

---

## Workflow

```
Student                          Supervisor
  │                                  │
  ├─→ Request Shift Change           │
  │                                  │
  │                                  ├─→ See Pending Request
  │                                  │
  │                                  ├─→ Review Details
  │                                  │
  │                                  ├─→ Approve/Reject
  │                                  │
  ├─← Status Updated                 │
  │                                  │
```

---

## Notifications

Currently, there are no automatic notifications. Students should:
- Check their request status regularly
- Look for status changes in the dashboard

Supervisors should:
- Check pending requests daily
- Review and respond promptly

---

## Best Practices

### For Students:
1. ✅ Request shift changes in advance (not last minute)
2. ✅ Provide clear, specific reasons
3. ✅ Check request status regularly
4. ✅ Don't submit multiple requests for the same date

### For Supervisors:
1. ✅ Review pending requests daily
2. ✅ Add review notes when rejecting
3. ✅ Communicate with students about shift changes
4. ✅ Keep track of approved changes

---

## Testing

### Test as Student:
1. Login as student
2. Click "📅 Request Shift Change"
3. Fill form and submit
4. Check request appears in history

### Test as Supervisor:
1. Login as supervisor
2. Click "📅 Manage Shift Change Requests"
3. See pending request from test student
4. Approve or reject with notes
5. Check request moves to processed section

---

## Troubleshooting

### Student can't see the button:
- Clear browser cache
- Make sure you're logged in as student
- Check if attendance tab is selected

### Supervisor can't see requests:
- Make sure student is linked to your account (created_by or company_id)
- Check if requests are already processed
- Refresh the page

### Request submission fails:
- Check all fields are filled
- Ensure shift start < shift end
- Verify reason is provided
- Check database connection

---

## Future Enhancements

Potential improvements:
- [ ] Email notifications for status changes
- [ ] SMS notifications
- [ ] Bulk approve/reject
- [ ] Calendar view of approved changes
- [ ] Automatic shift update after approval
- [ ] Request cancellation by student (if pending)
