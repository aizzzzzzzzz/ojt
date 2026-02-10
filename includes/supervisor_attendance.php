<?php
// Attendance management module for supervisor dashboard

function handle_mark_absent($pdo, $student_id, $date, $reason) {
    if (!$student_id || !$reason) {
        return "Student ID and reason are required.";
    }

    try {
        $pdo->beginTransaction();
        $message = mark_student_absent($pdo, $student_id, $date, $reason);
        $pdo->commit();
        write_audit_log('Mark Absent', "Student ID: $student_id, Date: $date");
        return $message;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return "Database error: " . $e->getMessage();
    }
}
?>
