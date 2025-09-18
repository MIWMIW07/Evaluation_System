<?php
// index.php - Fixed for Railway deployment (no redirect loops)
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect based on user type
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
        exit;
    } elseif ($_SESSION['user_type'] === 'student') {
        header('Location: student_dashboard.php');
        exit;
    }
}

// If not logged in, show the evaluation form or redirect to login
// For now, redirect to login
header('Location: login.php');
exit;
?>
