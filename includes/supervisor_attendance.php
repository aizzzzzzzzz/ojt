<?php

function handle_mark_absent($pdo, $student_id, $date, $reason) {
    if (!$student_id || !$reason) {
        return "Student ID and reason are required.";
    }

    try {
        $pdo->beginTransaction();
        $message = mark_student_absent($pdo, $student_id, $date, $reason);
        $pdo->commit();
        write_audit_log('Mark Absent', "Student ID: $student_id, Date: $date");

        $student_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM students WHERE student_id = ?");
        $student_stmt->execute([$student_id]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

        if ($student && !empty($student['email'])) {
            $student_name = $student['first_name'] . ' ' . $student['last_name'];
            $email_result = send_attendance_notification($student['email'], $student_name, $date, 'absent');
            if ($email_result !== true) {
                error_log("Failed to send attendance notification: " . $email_result);
            }
        }

        return $message;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return "Database error: " . $e->getMessage();
    }
}

function handle_verify_attendance($pdo, $student_id, $date) {
    try {
        $updateStmt = $pdo->prepare("
            UPDATE attendance
            SET verified = 1, verified_at = NOW()
            WHERE student_id = ? AND log_date = ?
        ");
        $updateStmt->execute([$student_id, $date]);

        $student_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM students WHERE student_id = ?");
        $student_stmt->execute([$student_id]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);

        if ($student && !empty($student['email'])) {
            $student_name = $student['first_name'] . ' ' . $student['last_name'];
            $email_result = send_attendance_notification($student['email'], $student_name, $date, 'present');
            if ($email_result !== true) {
                error_log("Failed to send attendance verification notification: " . $email_result);
            }
        }

        write_audit_log('Verify Attendance', "Student ID: $student_id, Date: $date");
        return "Attendance verified successfully!";
} catch (PDOException $e) {
    return "Database error: " . $e->getMessage();
}
}
?>
