<?php
// index.php - Main entry point (simplified)
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin.php');
        exit;
    } else {
        header('Location: student_dashboard.php');
        exit;
    }
}

// If not logged in, redirect to login page
header('Location: login.php');
exit;
?>
