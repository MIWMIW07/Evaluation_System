<?php
session_start();
require_once 'includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $manager = getDataManager();
    $auth = $manager->authenticateUser($username, $password);

    if ($auth) {
        $_SESSION['user_id']   = $auth['id'];
        $_SESSION['user_type'] = $auth['type'];

        logActivity("login", "User $username logged in", "success", $auth['id']);

        // ✅ Redirect based on role
        if ($auth['type'] === 'admin') {
            header("Location: admin/dashboard.php");
        } elseif ($auth['type'] === 'teacher') {
            header("Location: teacher/dashboard.php");
        } elseif ($auth['type'] === 'student') {
            header("Location: student/dashboard.php");
        } else {
            header("Location: index.php"); // fallback
        }
        exit;
    } else {
        logActivity("login_failed", "Invalid login attempt for $username", "error", null);
        $_SESSION['error'] = "Invalid username or password.";
        header("Location: index.php");
        exit;
    }
}
