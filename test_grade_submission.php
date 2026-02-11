<?php
require 'private/config.php';

echo "=== TESTING GRADE SUBMISSION CHANGES ===\n";

// Assume some test data
$submission_id = 22; // Use existing submission from check
$employer_id = 34; // From query, creator of project 2

// Simulate session
$_SESSION['employer_id'] = $employer_id;

// Test 1: Try to grade a rejected submission
echo "Test 1: Grading a rejected submission\n";
try {
    // First, set status to Rejected
    $pdo->exec("UPDATE project_submissions SET status = 'Rejected' WHERE submission_id = $submission_id");

    // Simulate POST data
    $_POST['submission_id'] = $submission_id;
    $_POST['status'] = 'Approved';
    $_POST['remarks'] = 'Test approve';

    // Include the script (but it has session_start, so can't include)
    // Instead, copy the logic here for testing

    $submission_id_test = (int)($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (!$submission_id_test || !in_array($status, ['Pending', 'Approved', 'Rejected'])) {
        echo "Invalid data\n";
    } else {
        // Check if submission belongs to a project created by this employer (IDOR prevention)
        $stmt = $pdo->prepare("
            SELECT ps.status
            FROM project_submissions ps
            INNER JOIN projects p ON ps.project_id = p.project_id
            WHERE ps.submission_id = ? AND p.created_by = ?
        ");
        $stmt->execute([$submission_id_test, $_SESSION['employer_id']]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            echo "Access denied or submission not found.\n";
        } elseif ($submission['status'] === 'Rejected') {
            echo "Cannot grade a rejected submission.\n";
        } else {
            echo "Would proceed with grading to $status\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 2: Grade a pending submission
echo "\nTest 2: Grading a pending submission\n";
try {
    // Set status to Pending
    $pdo->exec("UPDATE project_submissions SET status = 'Pending' WHERE submission_id = $submission_id");

    $_POST['submission_id'] = $submission_id;
    $_POST['status'] = 'Approved';
    $_POST['remarks'] = 'Test approve';

    $submission_id_test = (int)($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    if (!$submission_id_test || !in_array($status, ['Pending', 'Approved', 'Rejected'])) {
        echo "Invalid data\n";
    } else {
        $stmt = $pdo->prepare("
            SELECT ps.status
            FROM project_submissions ps
            INNER JOIN projects p ON ps.project_id = p.project_id
            WHERE ps.submission_id = ? AND p.created_by = ?
        ");
        $stmt->execute([$submission_id_test, $_SESSION['employer_id']]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$submission) {
            echo "Access denied or submission not found.\n";
        } elseif ($submission['status'] === 'Rejected') {
            echo "Cannot grade a rejected submission.\n";
        } else {
            echo "Would proceed with grading to $status\n";
            // Actually do it for test
            $remarks_escaped = $pdo->quote($remarks);
            $status_escaped = $pdo->quote($status);
            $sql = "UPDATE project_submissions SET status = $status_escaped, remarks = $remarks_escaped, graded_at = NOW() WHERE submission_id = $submission_id_test";
            $result = $pdo->exec($sql);
            echo "Graded successfully, rows affected: $result\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Test 3: Unauthorized employer
echo "\nTest 3: Unauthorized employer\n";
try {
    $_SESSION['employer_id'] = 999; // Invalid employer

    $_POST['submission_id'] = $submission_id;
    $_POST['status'] = 'Approved';

    $submission_id_test = (int)($_POST['submission_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    $stmt = $pdo->prepare("
        SELECT ps.status
        FROM project_submissions ps
        INNER JOIN projects p ON ps.project_id = p.project_id
        WHERE ps.submission_id = ? AND p.created_by = ?
    ");
    $stmt->execute([$submission_id_test, $_SESSION['employer_id']]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$submission) {
        echo "Access denied or submission not found.\n";
    } else {
        echo "Unexpected: access granted\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== TESTS COMPLETED ===\n";
?>
