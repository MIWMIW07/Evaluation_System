<?php
// logout.php - Handle user logout
session_start();

// Store user info for goodbye message
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// Store message in session before destroying
$_SESSION['logout_message'] = "👋 Goodbye, " . htmlspecialchars($user_name) . "! You have been logged out successfully.";
$_SESSION['user_type_was'] = $user_type;

// Destroy all session data
session_unset();
session_destroy();

// Redirect to login page with message
header('Location: login.php');
exit;
?>
