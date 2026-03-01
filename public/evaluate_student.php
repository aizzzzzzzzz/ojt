<?php
session_start();
require_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: employer_login.php");
    exit;
}

$employer_id = $_SESSION['employer_id'];

$students = $pdo->query("SELECT student_id, CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) AS name FROM students ORDER BY name")
               ->fetchAll(PDO::FETCH_ASSOC);

$success = "";
$error = "";

$selected_student_id = $_GET['student_id'] ?? '';
$selected_student_name = null;

if ($selected_student_id !== '') {
    foreach ($students as $student) {
        if ((string) $student['student_id'] === (string) $selected_student_id) {
            $selected_student_name = $student['name'];
            break;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = $_POST['student_id'] ?? null;

    if (!$student_id) {
        $error = "Please select a student.";
    } else {

        $check = $pdo->prepare("SELECT evaluation_id FROM evaluations WHERE student_id = ?");
        $check->execute([$student_id]);

        if ($check->rowCount() > 0) {
            $error = "This student already has a final evaluation.";
        } else {

            $sql = "INSERT INTO evaluations (
                        student_id, employer_id, evaluation_date,
                        attendance_rating, work_quality_rating, initiative_rating,
                        communication_rating, teamwork_rating, adaptability_rating,
                        professionalism_rating, problem_solving_rating, technical_skills_rating,
                        comments
                    )
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $student_id,
                $employer_id,
                $_POST['attendance_rating'],
                $_POST['work_quality_rating'],
                $_POST['initiative_rating'],
                $_POST['communication_rating'],
                $_POST['teamwork_rating'],
                $_POST['adaptability_rating'],
                $_POST['professionalism_rating'],
                $_POST['problem_solving_rating'],
                $_POST['technical_skills_rating'],
                $_POST['comments']
            ]);

            $success = "Evaluation submitted successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Evaluation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #2c3e50;
            line-height: 1.5;
        }

        .evaluation-page {
            padding: 20px;
        }

        .evaluation-card {
            width: 100%;
            max-width: 980px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
            padding: 28px;
        }

        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .page-title {
            margin: 0;
            font-size: 1.9rem;
            font-weight: 700;
            color: #1f3b57;
        }

        .page-subtitle {
            margin: 6px 0 0;
            color: #5f7488;
            font-size: 0.98rem;
        }

        .student-block {
            background: #f8f9fa;
            border: 1px solid #dfe6ee;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 22px;
        }

        .student-name {
            margin: 0;
            font-size: 1rem;
            color: #1f3b57;
        }

        .section-card {
            background: #f8f9fa;
            border: 1px solid #dfe6ee;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 22px;
        }

        .section-title {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1f3b57;
        }

        .section-help {
            margin: 6px 0 0;
            color: #5f7488;
            font-size: 0.92rem;
        }

        .ratings-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
            margin-top: 16px;
        }

        .rating-item label,
        .form-label {
            display: inline-block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #274b6d;
        }

        .form-select,
        .form-control {
            border-color: #c7d5e4;
            min-height: 46px;
        }

        .form-select:focus,
        .form-control:focus {
            border-color: #7ab4f8;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }

        .actions-row {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 4px;
        }

        @media (max-width: 768px) {
            .evaluation-page {
                padding: 14px;
            }

            .evaluation-card {
                padding: 18px;
                border-radius: 12px;
            }

            .top-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .ratings-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .actions-row {
                flex-direction: column-reverse;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<div class="evaluation-page">
    <div class="evaluation-card">
        <div class="top-actions">
            <div>
                <h1 class="page-title">Final Evaluation Form</h1>
                <p class="page-subtitle">Rate student performance across core OJT criteria.</p>
            </div>
            <a href="supervisor_dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($selected_student_id !== '' && $selected_student_name === null): ?>
            <div class="alert alert-warning">Selected student was not found. Please choose a valid student.</div>
        <?php endif; ?>

        <form method="POST">
            <div class="student-block">
                <label class="form-label" for="student_id">Select Student</label>

                <?php if ($selected_student_name === null): ?>
                    <select id="student_id" name="student_id" class="form-select" required>
                        <option value="">-- Choose Student --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?= $s['student_id'] ?>">
                                <?= htmlspecialchars($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="student_id" value="<?= htmlspecialchars((string) $selected_student_id) ?>">
                    <p class="student-name"><strong>Student:</strong> <?= htmlspecialchars($selected_student_name) ?></p>
                <?php endif; ?>
            </div>

            <div class="section-card">
                <h2 class="section-title">Ratings</h2>
                <p class="section-help">Use the scale: 1 = Poor, 5 = Excellent.</p>

                <?php
                $criteria = [
                    "attendance_rating" => "Attendance",
                    "work_quality_rating" => "Quality of Work",
                    "initiative_rating" => "Initiative",
                    "communication_rating" => "Communication",
                    "teamwork_rating" => "Teamwork",
                    "adaptability_rating" => "Adaptability",
                    "professionalism_rating" => "Professionalism",
                    "problem_solving_rating" => "Problem Solving",
                    "technical_skills_rating" => "Technical Skills"
                ];
                ?>

                <div class="ratings-grid">
                    <?php foreach ($criteria as $field => $label): ?>
                        <div class="rating-item">
                            <label for="<?= $field ?>"><?= $label ?></label>
                            <select id="<?= $field ?>" name="<?= $field ?>" class="form-select" required>
                                <option value="">Select Rating</option>
                                <option value="1">1 - Poor</option>
                                <option value="2">2 - Fair</option>
                                <option value="3">3 - Good</option>
                                <option value="4">4 - Very Good</option>
                                <option value="5">5 - Excellent</option>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="section-card">
                <label class="form-label" for="comments">Comments (optional)</label>
                <textarea id="comments" name="comments" class="form-control" rows="4"></textarea>
            </div>

            <div class="actions-row">
                <button type="submit" class="btn btn-primary">Submit Evaluation</button>
            </div>
        </form>
    </div>
</div>

</body>
</html>
