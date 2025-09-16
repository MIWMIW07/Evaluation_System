<?php
// security.php - Security utility functions

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection (adjust path as needed)
require_once 'db_connection.php'; // or wherever your database connection is defined

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
    return preg_match('/^[a-zA-Z\s\.\-\']+$/', $name); // Added apostrophe for names like O'Brien
}

function validate_password_strength($password) {
    // At least 8 characters, with uppercase, lowercase, number
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// Rate limiting for login attempts
function check_login_rate_limit($username) {
    global $pdo; // Assuming you're using PDO, adjust as needed
    
    $max_attempts = 5;
    $lockout_time = 15; // 15 minutes
    
    try {
        // Create rate limit table if it doesn't exist (MySQL compatible)
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            username VARCHAR(255) NOT NULL,
            attempts INT DEFAULT 1,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            locked_until TIMESTAMP NULL,
            INDEX idx_ip_username (ip_address, username),
            INDEX idx_last_attempt (last_attempt)
        )");
        
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Clean up old records (older than 24 hours)
        $pdo->prepare("DELETE FROM login_attempts WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->execute();
        
        // Check if currently locked out
        $stmt = $pdo->prepare("SELECT locked_until FROM login_attempts 
                              WHERE (ip_address = ? OR username = ?) 
                              AND locked_until > NOW() 
                              ORDER BY last_attempt DESC LIMIT 1");
        $stmt->execute([$ip, $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && $row['locked_until']) {
            $locked_until = new DateTime($row['locked_until']);
            $now = new DateTime();
            $remaining = $locked_until->getTimestamp() - $now->getTimestamp();
            
            if ($remaining > 0) {
                $minutes = ceil($remaining / 60);
                return [
                    'success' => false,
                    'message' => "Too many login attempts. Please try again in $minutes minutes.",
                    'locked_until' => $locked_until->format('Y-m-d H:i:s')
                ];
            }
        }
        
        // Get current attempt count for this IP/username in the last hour
        $stmt = $pdo->prepare("SELECT id, attempts FROM login_attempts 
                              WHERE (ip_address = ? OR username = ?) 
                              AND last_attempt > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                              AND (locked_until IS NULL OR locked_until <= NOW())
                              ORDER BY last_attempt DESC LIMIT 1");
        $stmt->execute([$ip, $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $new_attempts = $row['attempts'] + 1;
            if ($new_attempts >= $max_attempts) {
                // Lock the account
                $stmt = $pdo->prepare("UPDATE login_attempts 
                                     SET attempts = ?, last_attempt = NOW(), locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) 
                                     WHERE id = ?");
                $stmt->execute([$new_attempts, $lockout_time, $row['id']]);
                
                return [
                    'success' => false,
                    'message' => "Too many login attempts. Account locked for $lockout_time minutes.",
                    'attempts' => $new_attempts
                ];
            } else {
                // Update attempt count
                $stmt = $pdo->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = NOW() WHERE id = ?");
                $stmt->execute([$new_attempts, $row['id']]);
                
                $remaining_attempts = $max_attempts - $new_attempts;
                return [
                    'success' => true,
                    'message' => "Login attempt recorded. $remaining_attempts attempts remaining.",
                    'attempts' => $new_attempts
                ];
            }
        } else {
            // First attempt, create new record
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip_address, username) VALUES (?, ?)");
            $stmt->execute([$ip, $username]);
            
            return [
                'success' => true,
                'message' => 'First login attempt recorded.',
                'attempts' => 1
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Rate limiting error: " . $e->getMessage());
        // Don't block login if rate limiting fails
        return ['success' => true, 'message' => 'Rate limiting temporarily unavailable.'];
    }
}

// Reset login attempts after successful login
function reset_login_attempts($username) {
    global $pdo;
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ? OR username = ?");
        $stmt->execute([$ip, $username]);
    } catch (PDOException $e) {
        error_log("Error resetting login attempts: " . $e->getMessage());
    }
}

// Log security events
function log_security_event($event_type, $details = '') {
    global $pdo;
    
    try {
        // Create security log table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS security_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_type (event_type),
            INDEX idx_created_at (created_at)
        )");
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO security_log (event_type, ip_address, user_agent, details) VALUES (?, ?, ?, ?)");
        $stmt->execute([$event_type, $ip, $user_agent, $details]);
        
    } catch (PDOException $e) {
        error_log("Security logging error: " . $e->getMessage());
    }
}

// Check for suspicious activity patterns
function detect_suspicious_activity() {
    global $pdo;
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Check for rapid requests (more than 50 requests in 5 minutes)
        $stmt = $pdo->prepare("SELECT COUNT(*) as request_count FROM security_log 
                              WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $stmt->execute([$ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['request_count'] > 50) {
            log_security_event('SUSPICIOUS_ACTIVITY', "Rapid requests detected: {$row['request_count']} requests in 5 minutes");
            return true;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Suspicious activity detection error: " . $e->getMessage());
        return false;
    }
}

// Generate secure session ID
function generate_secure_session_id() {
    session_regenerate_id(true);
    return session_id();
}

// Set secure session parameters
function set_secure_session_params() {
    // Set session cookie parameters for security
    session_set_cookie_params([
        'lifetime' => 3600, // 1 hour
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']), // Only send over HTTPS
        'httponly' => true, // Prevent JavaScript access
        'samesite' => 'Strict' // CSRF protection
    ]);
}

// Initialize secure session
function init_secure_session() {
    set_secure_session_params();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
?>
