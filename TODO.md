# TODO List - OJT Real-time Updates Replacement

## Tasks
[ ] 1. Enhance public/api/check_attendance.php to accept since parameter - Partially done
[ ] 2. Create public/api/check_updates.php for certificates and project submissions - Not done
[ ] 3. Update public/supervisor_dashboard.php - Replace WebSocket with polling - IN PROGRESS
[ ] 4. Update public/student_dashboard.php - Add polling for attendance and projects - PENDING
[ ] 5. Fix includes/attendance.php - Remove WebSocket notification call - PENDING

## Progress Log

### 1. check_attendance.php - Partially done
- Status: DONE (already implemented with `since` parameter)
- Notes: The code already supports the `since` parameter for polling

### 2. Create check_updates.php
- Status: DONE
- Created: public/api/check_updates.php
- Features: Checks for certificate and project submission updates with `since` parameter

### 3. Supervisor Dashboard Polling
- Status: IN PROGRESS
- Plan: Replace WebSocket with polling mechanism
- Steps:
  - [x] Read supervisor_dashboard.php to understand current implementation
  - [ ] Remove WebSocket code
  - [ ] Add polling mechanism using check_attendance.php and check_updates.php

### 4. Student Dashboard Polling
- Status: PENDING
- Plan: Add polling for attendance and project status
- Steps:
  - [ ] Read student_dashboard.php to understand current implementation
  - [ ] Add polling for attendance verification status
  - [ ] Add polling for project submission status

### 5. Remove WebSocket notifications
- Status: PENDING
- Plan: Remove notify_attendance_update calls from attendance.php
- Steps:
  - [ ] Read includes/attendance.php to find notify_attendance_update calls
  - [ ] Remove the notify_attendance_update function
  - [ ] Remove calls to this function
