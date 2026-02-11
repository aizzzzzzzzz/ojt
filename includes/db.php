<?php

function get_student_info($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ? LIMIT 1");
    $stmt->execute([$student_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function get_attendance_history($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY log_date DESC");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_today_attendance($pdo, $student_id, $today) {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1");
    $stmt->execute([$student_id, $today]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculate_total_minutes($attendance) {
    $total_minutes = 0;
    foreach ($attendance as $row) {
        if ($row['verified'] == 1 && !empty($row['time_in']) && !empty($row['time_out'])) {
            $time_in = strtotime($row['time_in']);
            $time_out = strtotime($row['time_out']);
            $minutesWorked = max(0, ($time_out - $time_in) / 60);

            if (!empty($row['lunch_out']) && !empty($row['lunch_in'])) {
                $lunch_out = strtotime($row['lunch_out']);
                $lunch_in = strtotime($row['lunch_in']);
                $minutesWorked -= max(0, ($lunch_in - $lunch_out) / 60);
            }

            $total_minutes += max(0, $minutesWorked);
        }
    }
    return $total_minutes;
}

function get_projects($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM projects ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_student_submissions($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT ps.*, p.project_name FROM project_submissions ps JOIN projects p ON ps.project_id = p.project_id WHERE ps.student_id = ? ORDER BY ps.submission_date DESC");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function save_daily_task($pdo, $student_id, $today, $task) {
    $check = $pdo->prepare("SELECT id FROM attendance WHERE student_id = ? AND log_date = ? LIMIT 1");
    $check->execute([$student_id, $today]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $upd = $pdo->prepare("UPDATE attendance SET daily_task = ? WHERE id = ?");
        $upd->execute([$task, $existing['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO attendance (student_id, employer_id, log_date, daily_task, status) VALUES (?, NULL, ?, ?, 'present')");
        $ins->execute([$student_id, $today, $task]);
    }
}

function submit_project($pdo, $project_id, $student_id, $file_path, $remarks) {
    $stmt = $pdo->prepare("
        INSERT INTO project_submissions
        (project_id, student_id, file_path, status, submission_date, remarks, submission_status)
        VALUES (?, ?, ?, 'Pending', NOW(), ?, 'On Time')
        ON DUPLICATE KEY UPDATE
        file_path = VALUES(file_path),
        status = 'Pending',
        submission_date = NOW(),
        remarks = VALUES(remarks),
        submission_status = 'On Time',
        graded_at = NULL
    ");
    $stmt->execute([$project_id, $student_id, $file_path, $remarks]);
}
?>
