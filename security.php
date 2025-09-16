<?php
// security.php - Security utility functions

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

// Validation functions
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_alphanumeric($input) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $input);
}

function validate_name($name) {
    return preg_match('/^[a-zA-Z\s\.\-]+$/', $name);
}

// Rate limiting for login attempts
function check_login_rate_limit($username) {
    $max_attempts = 5;
    $lockout_time = 15 * 60; // 15 minutes in seconds
    
    // Create rate limit table if it doesn't exist
    query("CREATE TABLE IF NOT EXISTS login_attempts (
        id SERIAL PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(255) NOT NULL,
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        locked_until TIMESTAMP NULL
    )");
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Check if currently locked out
    $result = query("SELECT locked_until FROM login_attempts WHERE ip_address = ? OR username = ? ORDER BY last_attempt DESC LIMIT 1", [$ip, $username]);
    $row = fetch_assoc($result);
    
    if ($row && $row['locked_until'] && strtotime($row['locked_until']) > time()) {
        $remaining = strtotime($row['locked_until']) - time();
        die("Too many login attempts. Please try again in " . ceil($remaining / 60) . " minutes.");
    }
    
    // Update or create attempt record
    $result = query("SELECT id, attempts FROM login_attempts WHERE (ip_address = ? OR username = ?) AND last_attempt > NOW() - INTERVAL '1 hour'", [$ip, $username]);
    $row = fetch_assoc($result);
    
    if ($row) {
        $new_attempts = $row['attempts'] + 1;
        if ($new_attempts >= $max_attempts) {
            // Lock the account
            query("UPDATE login_attempts SET attempts = ?, last_attempt = NOW(), locked_until = NOW() + INTERVAL '$lockout_time seconds' WHERE id = ?", [$new_attempts, $row['id']]);
            die("Too many login attempts. Account locked for 15 minutes.");
        } else {
            query("UPDATE login_attempts SET attempts = ?, last_attempt = NOW() WHERE id = ?", [$new_attempts, $row['id']]);
        }
    } else {
        query("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)", [$ip, $username]);
    }
    
    return true;
}
?>
