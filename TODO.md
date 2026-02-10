# TODO: Implement Evaluate Student Visibility Based on Required Hours

## Steps to Complete
- [x] Modify `includes/supervisor_db.php` to include `required_hours` in the `get_attendance_records` query.
- [x] Update `public/supervisor_dashboard.php` to calculate `required_minutes` from the student's `required_hours` (convert hours to minutes) and use it for eligibility check.
- [x] Test the changes to ensure the "Evaluate Student" button is hidden until required hours are met.

## Notes
- Assumes `required_hours` is stored in hours in the database.
- For demo purposes, to "cheat" and make the button always visible, temporarily set `$required_minutes = 0;` in `supervisor_dashboard.php` or modify the eligibility condition.
