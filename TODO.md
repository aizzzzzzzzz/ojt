# TODO: Move Upload Feature to Employer Dashboard and Rename to OJT Supervisor

## Step 1: Rename Employer Dashboard
- [x] Change title from "Employer Dashboard" to "OJT Supervisor Dashboard"
- [x] Update welcome message from "You are logged in as Employer." to "You are logged in as OJT Supervisor."

## Step 2: Move Upload Feature to Employer Dashboard
- [x] Add necessary includes (middleware.php) to supervisor_dashboard.php
 for CSRF and audit functions
- [x] Add upload handling logic at the top of supervisor_dashboard.php
 (adapt from admin_dashboard.php, changing admin_id to employer_id)
- [x] Add the "OJT Documents" collapsible section to supervisor_dashboard.php
 after the Quick Actions
- [x] Update the table header from "Uploaded By (Admin ID)" to "Uploaded By (Employer ID)"

## Step 3: Remove Upload Section from Admin Dashboard
- [x] Remove the upload section from admin_dashboard.php

## Followup Steps
- [ ] Test upload functionality in employer dashboard
- [ ] Verify downloads work for employer-uploaded files
- [ ] Ensure no other references to admin upload feature
