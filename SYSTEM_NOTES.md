# OJT System Notes

Last updated: 2026-03-01
Project path: `c:\xampp\htdocs\ojt`

## 1) System Overview

This is a PHP/MySQL OJT management system with three user roles:
- Student
- Supervisor/Employer
- Admin

Core capabilities:
- Role-based login and dashboard access
- Daily attendance logging and supervisor verification
- Project creation, student submissions, grading, and approval/rejection
- Final student evaluation with rating criteria and comments
- Certificate generation/download and certificate hash verification
- Attendance export (Excel) and evaluation export (PDF)
- Optional websocket notification broadcaster (Ratchet)

Primary stack:
- PHP 8+
- MySQL/MariaDB
- Bootstrap (CDN)
- PhpSpreadsheet (Excel)
- FPDF/mPDF (PDF generation)
- PHPMailer (email)
- Ratchet (websocket server)

## 2) Entry Points and Routing

- `index.php` is the public landing page and role selector.
- Login pages:
  - `public/student_login.php`
  - `public/supervisor_login.php`
  - `public/admin_login.php`
- Main dashboards:
  - `public/student_dashboard.php`
  - `public/supervisor_dashboard.php`
  - `public/admin_dashboard.php`

Access control is session-based and enforced by shared helpers in `includes/` and `private/config.php`.

## 3) Role Workflows

### Student Flow
1. Login (`public/student_login.php`)
2. First login requires password change (`public/change_password.php`)
3. Use dashboard (`public/student_dashboard.php`) to:
- log attendance actions (time in/out, lunch out/in)
- save daily tasks
- view attendance history and status
- export attendance history to Excel
- view projects and submit project files/code
4. Download generated documents when available:
- evaluation PDF (`public/download_evaluation.php`)
- certificate PDF (`public/download_certificate.php`)

### Supervisor/Employer Flow
1. Login (`public/supervisor_login.php`)
2. First login requires password change (`public/change_password.php`)
3. Use dashboard (`public/supervisor_dashboard.php`) to:
- monitor students
- mark absences and verify attendance
- evaluate students
- add signature and generate certificates
- create/manage projects and review submissions
- upload/manage shared documents

### Admin Flow
1. Login (`public/admin_login.php`)
2. Use dashboard (`public/admin_dashboard.php`) to:
- manage employers/supervisors
- monitor high-level counts
- access admin templates/views
- write audit logs

## 4) Core Modules

### Security and Configuration
- `private/config.php`
  - DB connection and timezone setup
  - security headers (CSP, HSTS when HTTPS, X-Frame-Options, etc.)
  - session security hardening
  - CSRF token generation/validation
  - sanitization utilities
  - password policy helper
  - login attempt tracking helpers

### Authentication and Middleware
- `includes/auth.php`: student session guard
- `includes/supervisor_auth.php`: supervisor session guard + profile fetch
- `includes/admin_auth.php`: admin session guard + profile fetch
- `includes/auth_check.php`: generic role gate (`require_role`)
- `includes/middleware.php`: role guards, CSRF check, audit-log writers

### Data and Business Logic
- `includes/db.php`: student/attendance/project DB helpers
- `includes/supervisor_db.php`: supervisor-side listing/aggregation logic
- `includes/attendance.php`: student attendance action handling
- `includes/supervisor_attendance.php`: absence/verification handlers
- `includes/projects.php`: project submission handler
- `includes/export.php`: Excel export helper
- `includes/admin_db.php`: admin CRUD/count helpers
- `includes/audit.php`: audit/activity logging helpers
- `includes/email.php`: email notifications and test email sender
- `includes/Security.php`, `includes/FormHandler.php`, `includes/Database.php`: utility classes/helpers

## 5) Database Notes (`student_db.sql`)

Main tables:
- `students`
- `employers`
- `admins`
- `companies`
- `attendance`
- `projects`
- `project_submissions`
- `evaluations`
- `certificates`
- `certificate_hashes`
- `uploaded_files`
- `audit_logs`
- `activity_logs`

Important constraints:
- unique student username, employer username, admin username
- one evaluation per student/employer pair (`unique_evaluation`)
- one submission per project/student pair (`unique_submission`)
- attendance uniqueness per student/date/employer (`unique_attendance`)
- FK cascades for most student-linked records

## 6) Real-Time/Notification Behavior

Polling APIs (used by dashboards):
- `public/api/check_attendance.php`
- `public/api/check_updates.php`

Dashboard JS polling:
- student and supervisor dashboards periodically call APIs and trigger auto-refresh notices.


## 7) File Storage Notes

Used directories:
- `storage/uploads/` for project submissions and generated files
- `uploads/` for uploaded asset files (for example logos)
- `assets/` and `public/assets/` for static files

PDF/certificate generation touches:
- `lib/fpdf.php`
- `lib/mpdf-*` (or mPDF autoload path in code)
- certificate files referenced by DB `certificates.file_path`

## 8) File-by-File Notes (App-Owned)

### Root
- `index.php`: landing page, HTTPS redirect logic, role portal links.
- `student_db.sql`: schema, indexes, auto-increment rules, FK constraints.
- `composer.json`: dependencies (`phpoffice/phpspreadsheet`, `cboden/ratchet`, dev `phpunit`).

### Private
- `private/config.php`: global config, DB, security, helpers.

### Includes
- `includes/admin_auth.php`: admin auth guard and admin info retrieval.
- `includes/admin_db.php`: admin counts, employer add/delete, file upload helper.
- `includes/attendance.php`: attendance action logic and task handling.
- `includes/audit.php`: audit and activity logging functions.
- `includes/auth.php`: student auth guard.
- `includes/auth_check.php`: role checks and user-session resolver.
- `includes/Database.php`: DB utility wrapper class.
- `includes/db.php`: student-centric data access helpers.
- `includes/email.php`: PHPMailer setup and notification senders.
- `includes/export.php`: Excel export helper flow.
- `includes/FormHandler.php`: form validation and sanitization support.
- `includes/middleware.php`: admin/employer guards, CSRF check, audit log writes.
- `includes/projects.php`: project submission processing.
- `includes/Security.php`: additional security helper logic.
- `includes/supervisor_attendance.php`: supervisor attendance handlers.
- `includes/supervisor_auth.php`: supervisor auth guard and profile getter.
- `includes/supervisor_db.php`: supervisor data queries and aggregations.

### Public - Auth and Core Pages
- `public/student_login.php`: student login flow.
- `public/supervisor_login.php`: supervisor/admin login into supervisor context.
- `public/admin_login.php`: admin-only login.
- `public/change_password.php`: first-login and password update flow.
- `public/logout.php`: session logout and redirect.

### Public - Dashboards and Main UX
- `public/student_dashboard.php`: student main app (attendance, export, projects, polling).
- `public/supervisor_dashboard.php`: supervisor main app (attendance, evaluation/certificate actions).
- `public/admin_dashboard.php`: admin portal and management actions.

### Public - Student/Attendance Actions
- `public/mark_absent.php`: mark student absent workflow form.
- `public/absences.php`: absence management view/form.
- `public/verify_attendance.php`: mark attendance record as verified.
- `public/student_attendance_content.php`: attendance UI content fragment.

### Public - Student/Employer Management
- `public/add_student.php`: add student record.
- `public/delete_student.php`: delete student record.
- `public/add_employer.php`: add employer/supervisor.
- `public/add_first_admin.php`: bootstrap first admin account.

### Public - Projects and Submissions
- `public/create_project.php`: create project task.
- `public/manage_projects.php`: list/manage projects.
- `public/delete_project.php`: remove project.
- `public/project_submissions.php`: view submissions by project.
- `public/student_submit_form.php`: student submission form.
- `public/submit_project.php`: submission upload/validation handler.
- `public/approve_submission.php`: approve a submission + notify student.
- `public/reject_submission.php`: reject a submission + notify student.
- `public/grade_submission.php`: grading endpoint for submissions.
- `public/preview_code.php`: execute/preview code output.
- `public/preview_functions.js`: front-end helper for preview behavior.
- `public/view_submission.php`: secure submission viewer.
- `public/view_pdf.php`: secure file/PDF viewer for role owners.
- `public/view_output.php`: student-only output viewer.
- `public/generate_pdf.php`: generate PDF from output HTML and submit.

### Public - Evaluation and Certificates
- `public/evaluate_student.php`: final rating form and evaluation save.
- `public/evaluation.php`: alternate evaluation form page.
- `public/view_evaluation.php`: supervisor-side evaluation view.
- `public/download_evaluation.php`: student evaluation PDF export.
- `public/add_signature.php`: supervisor signature capture/use.
- `public/generate_certificate.php`: certificate creation flow.
- `public/download_certificate.php`: student certificate download.
- `public/verify_blockchain.php`: certificate hash verification page.

### Public - Documents and Files
- `public/upload_documents.php`: upload/list/delete shared documents.
- `public/download.php`: secured download for uploaded documents.
- `public/delete_document.php`: remove uploaded document record/file.

### Public - Export and Utility
- `public/export_attendance.php`: attendance Excel export endpoint.
- `public/test_email.php`: email testing utility endpoint/page.
- `public/employer_sidebar.php`: shared sidebar fragment for employer pages.

### Public - API Endpoints
- `public/api/check_attendance.php`: attendance update polling JSON endpoint.
- `public/api/check_updates.php`: project/certificate update polling JSON endpoint.

### Templates
- `templates/header.php`: shared student UI/header structure.
- `templates/footer.php`: shared student footer/scripts.
- `templates/attendance_tab.php`: student attendance tab markup.
- `templates/export_tab.php`: student export tab markup.
- `templates/projects_tab.php`: student projects tab markup.
- `templates/supervisor_header.php`: supervisor template header.
- `templates/supervisor_footer.php`: supervisor template footer/scripts.
- `templates/supervisor_attendance.php`: supervisor attendance table/layout.
- `templates/admin_header.php`: admin header/navigation template.
- `templates/admin_main.php`: admin main content template.

## 9) Important Integration Notes

- Attendance and updates are primarily near-real-time via periodic polling, not strict push-only websocket.
- Certificate generation depends on evaluation completion and signature workflow.
- Many workflows rely on session role keys (`student_id`, `employer_id`, `admin_id`, `role`).
- Several pages still use mixed naming (`employer` vs `supervisor`) but serve the same role context.

## 10) Suggested Next Documentation Additions

- Add sequence diagrams for each role flow.
- Add endpoint-level request/response contracts for `public/api/*`.
- Add deployment/config docs for production email + websocket server process.
- Add permission matrix (role x page/action) as a separate markdown file.
