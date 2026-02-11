<?php
session_start();
require_once __DIR__ . '/../private/config.php';

if (!isset($_SESSION['employer_id']) || $_SESSION['role'] !== 'employer') {
    header("Location: employer_login.php");
    exit;
}

$employer_id = $_SESSION['employer_id'];

$students = $pdo->query("SELECT student_id, CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) AS name FROM students ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$success = "";
$error = "";

$selected_student_id = $_GET['student_id'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $student_id = $_POST['student_id'] ?? null;

    if (!$student_id) {
        $error = "Please select a student.";
    } else {

        $check = $pdo->prepare("SELECT evaluation_id FROM evaluation WHERE student_id = ?");
        $check->execute([$student_id]);

        if ($check->rowCount() > 0) {
            $error = "This student already has a final evaluation.";
        } else {

            $sql = "INSERT INTO evaluation (
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
<html>
<head>
<title>Final Evaluation</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background:#e9f5ff; }
.container { background:white; padding:25px; border-radius:12px; margin-top:30px; box-shadow:0 4px 12px rgba(0,0,0,0.1); }
.rating-label { font-weight:bold; }
</style>
</head>
<body>

<div class="container">
    <h2 class="mb-4">Final Evaluation Form</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">

        <div class="mb-3">
            <label class="form-label">Select Student:</label>

            <?php if (!$selected_student_id): ?>
                <select name="student_id" class="form-control" required>
                    <option value="">-- Choose Student --</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?= $s['student_id'] ?>">
                            <?= htmlspecialchars($s['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input type="hidden" name="student_id" value="<?= $selected_student_id ?>">
                <p><strong>Student:</strong> <?= htmlspecialchars($students[array_search($selected_student_id, array_column($students,'student_id'))]['name']) ?></p>
            <?php endif; ?>
        </div>

        <h5 class="mt-4">Ratings (1 = Poor, 5 = Excellent)</h5>

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

        foreach ($criteria as $field => $label):
        ?>
        <div class="mb-3">
            <label class="rating-label"><?= $label ?></label>
            <select name="<?= $field ?>" class="form-control" required>
                <option value="">Select Rating</option>
                <option value="1">1 - Poor</option>
                <option value="2">2 - Fair</option>
                <option value="3">3 - Good</option>
                <option value="4">4 - Very Good</option>
                <option value="5">5 - Excellent</option>
            </select>
        </div>
        <?php endforeach; ?>

        <div class="mb-3">
            <label class="form-label">Comments (optional)</label>
            <textarea name="comments" class="form-control" rows="4"></textarea>
        </div>

        <button class="btn btn-primary">Submit Evaluation</button>

    </form>
</div>

</body>
</html>
