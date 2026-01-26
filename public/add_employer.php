<?php
session_start();
require "../private/config.php";
require_once __DIR__ . '/../includes/middleware.php';
require_admin();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST["name"]);
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (!empty($name) && !empty($username) && !empty($password)) {
        // Check for duplicate username (case-insensitive)
        $stmt = $pdo->prepare("SELECT 1 FROM employers WHERE LOWER(username) = LOWER(?)");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username already exists.";
            header("Location: add_employer.php");
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO employers (name, username, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$name, $username, $password])) {
                $_SESSION['success'] = "Employer account created successfully!";
                header("Location: employer_login.php");
                exit;
            } else {
                $error = "Failed to add employer. Please try again.";
            }
        }
    } else {
        $error = "All fields are required!";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Employer</title>
    <style>
        .success-msg {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            text-align: left;
            font-weight: 500;
        }

        .error-msg {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            text-align: left;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <h2>Add Employer</h2>

    <?php if (!empty($error)) echo "<div class='error-msg'>$error</div>"; ?>
    <?php if (!empty($_SESSION['error'])) { echo "<div class='error-msg'>".$_SESSION['error']."</div>"; unset($_SESSION['error']); } ?>
    <?php if (!empty($_SESSION['success'])) { echo "<div class='success-msg'>".$_SESSION['success']."</div>"; unset($_SESSION['success']); } ?>

    <form method="post">
        <label>Name:</label><br>
        <input type="text" name="name" required><br><br>

        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Add Employer</button>
    </form>

    <br>
    <a href="index.php">Back to Home</a>
</body>
</html>
