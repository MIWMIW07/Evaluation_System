<?php
// login.php - Login Page

session_start();
require_once __DIR__ . '/includes/db_connection.php';

// If already logged in, redirect to correct dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header("Location: admin.php");
        exit;
    } elseif ($_SESSION['user_type'] === 'student') {
        header("Location: student_dashboard.php");
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $user = authenticateUser($username, $password);

        if ($user) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['user_type'] = $user['user_type'];

            // ✅ Log activity
            logActivity("login", "{$user['username']} logged in", "success", $user['id']);

            if ($user['user_type'] === 'admin') {
                header("Location: admin.php");
                exit;
            } else {
                header("Location: student_dashboard.php");
                exit;
            }
        } else {
            $error = "❌ Invalid username or password.";
            logActivity("login", "Failed login attempt for $username", "failed");
        }
    } else {
        $error = "⚠️ Please enter both username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Evaluation System</title>
    <link rel="stylesheet" href="styles.css"> <!-- Optional custom CSS -->
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>

        <?php if (!empty($error)): ?>
            <p style="color: red;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="username">Username</label>
            <input type="text" name="username" required>

            <label for="password">Password</label>
            <input type="password" name="password" required>

            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
