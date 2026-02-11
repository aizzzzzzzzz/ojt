# Security Enforcement TODO

## SQL Injection Prevention
- [ ] Review all $pdo->query() calls and replace with prepared statements if user input is involved
- [ ] Ensure all database queries use parameterized queries
- [ ] Check files: admin_dashboard.php, evaluation.php, evaluate_student.php, etc.

## IDOR (Insecure Direct Object Reference) Prevention
- [x] view_evaluation.php: Added check that student belongs to current employer
- [x] view_submission.php: Added authorization checks for students and employers
- [x] approve_submission.php: Added check that submission belongs to employer's project
- [x] reject_submission.php: Added check that submission belongs to employer's project
- [ ] Add authorization checks for remaining endpoints using IDs from URL/params
- [ ] Files to check:
  - view_pdf.php: Already has some checks, verify complete
  - generate_certificate.php: Already has employer_id check, but verify student ownership
  - add_signature.php: Already has employer_id check, but verify student ownership
  - attendance_action.php: Check student belongs to employer
  - download.php: Check file access permissions
  - delete_document.php: Already has checks, verify complete

## Missing Authorization on Protected Routes
- [ ] Ensure all admin routes have require_admin() or similar
- [ ] Ensure supervisor routes have require_supervisor()
- [ ] Check middleware usage in all public files
- [ ] Add role checks where missing

## Summary of Security Fixes Applied
- [x] Database configuration updated to use environment variables for portability
- [x] IDOR fixes applied to multiple endpoints:
  - view_evaluation.php: Student ownership check
  - view_submission.php: Submission ownership checks for students and employers
  - approve_submission.php: Project ownership check
  - reject_submission.php: Project ownership check
  - attendance_action.php: Student-employer relationship check
- [ ] Remaining tasks: SQL injection review, additional IDOR checks, authorization middleware

## General
- [ ] Test all changes to ensure system still works
- [ ] Update any hardcoded queries to use Database class if applicable
