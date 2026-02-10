<?php

function get_students_list($pdo) {
    $stmt = $pdo->query("SELECT student_id, username FROM students ORDER BY username");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_attendance_records($pdo) {
    $stmt = $pdo->prepare("
        SELECT
            s.student_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.school,
            s.required_hours,
            a.log_date,
            a.time_in,
            a.lunch_out,
            a.lunch_in,
            a.time_out,
            a.status,
            a.reason,
            a.verified,
            a.daily_task,
            CASE
                WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL THEN
                    CONCAT(
                        FLOOR((
                            TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                            - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                        ) / 60), 'h ',
                        MOD((
                            TIMESTAMPDIFF(MINUTE, a.time_in, a.time_out)
                            - COALESCE(TIMESTAMPDIFF(MINUTE, a.lunch_out, a.lunch_in), 0)
                        ), 60), 'm'
                    )
                ELSE '---'
            END AS daily_hours
        FROM students s
        LEFT JOIN attendance a ON s.student_id = a.student_id
        ORDER BY s.last_name, s.first_name, s.middle_name, a.log_date
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_total_minutes($pdo) {
    $acc_stmt = $pdo->prepare("
        SELECT student_id,
            SUM(
                CASE
                    WHEN time_in IS NOT NULL AND time_out IS NOT NULL AND verified = 1 THEN
                        TIMESTAMPDIFF(MINUTE, time_in, time_out)
                        - COALESCE(TIMESTAMPDIFF(MINUTE, lunch_out, lunch_in), 0)
                    ELSE 0
                END
            ) AS total_minutes
        FROM attendance
        GROUP BY student_id
    ");
    $acc_stmt->execute();
    $acc_rows = $acc_stmt->fetchAll(PDO::FETCH_ASSOC);
    $acc_map = [];
    foreach ($acc_rows as $ar) {
        $acc_map[$ar['student_id']] = (int) ($ar['total_minutes'] ?? 0);
    }
    return $acc_map;
}

function get_evaluated_students($pdo) {
    $eval_stmt = $pdo->prepare("
        SELECT DISTINCT student_id
        FROM evaluations
    ");
    $eval_stmt->execute();
    return array_flip($eval_stmt->fetchAll(PDO::FETCH_COLUMN));
}

function mark_student_absent($pdo, $student_id, $date, $reason) {
    $checkStmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ?");
    $checkStmt->execute([$student_id, $date]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        $updateStmt = $pdo->prepare("
            UPDATE attendance
            SET status = 'Absent', reason = ?, verified = 0
            WHERE student_id = ? AND log_date = ?
        ");
        $updateStmt->execute([$reason, $student_id, $date]);
        return "Attendance updated to Absent";
    } else {
        $insertStmt = $pdo->prepare("
            INSERT INTO attendance (student_id, log_date, status, reason, verified)
            VALUES (?, ?, 'Absent', ?, 0)
        ");
        $insertStmt->execute([$student_id, $date, $reason]);
        return "Student marked as Absent";
    }
}
?>
