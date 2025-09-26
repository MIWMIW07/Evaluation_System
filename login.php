<?php
// login.php – Handles authentication only
session_start();
require_once 'includes/security.php';
require_once 'includes/db_connection.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        header("Location: index.php?error=required");
        exit();
    }

    // Authenticate user (function from includes/security.php)
    $user = authenticateUser($username, $password);

    if ($user) {
        $_SESSION['user_id'] = $user['user_id'] ?? $user['student_id'] ?? $user['username'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['full_name'] = $user['full_name'];

        if ($user['user_type'] === 'student') {
            $_SESSION['student_id'] = $user['student_id'];
            $_SESSION['section'] = $user['section'];
            $_SESSION['program'] = $user['program'];
            header("Location: student_dashboard.php");
            exit();
        } else {
            header("Location: admin.php");
            exit();
        }
    } else {
        header("Location: index.php?error=invalid");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
