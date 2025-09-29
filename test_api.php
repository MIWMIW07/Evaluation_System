<?php
// test_api.php - Simple test file
session_start();
$_SESSION['user_id'] = 'admin';
$_SESSION['user_type'] = 'admin';

require_once 'google_integration_api.php';
?>
