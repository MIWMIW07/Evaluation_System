<?php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    try {
        $manager = getDataManager();
        $user = $manager->authenticateUser($username, $password);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['type'];

            if ($user['type'] === 'admin') {
                header("Location: admin.php");
                exit;
            } elseif ($user['type'] === 'teacher') {
                header("Location: teacher_dashboard.php");
                exit;
            } elseif ($user['type'] === 'student') {
                header("Location: student_dashboard.php");
                exit;
            }
        } else {
            $error = "Invalid username or password.";
        }
    } catch (Exception $e) {
        $error = "Login failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login - Evaluation System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h2>Login</h2>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
    <form method="POST">
        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>
        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
