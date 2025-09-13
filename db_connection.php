<?php
// Database connection for Railway deployment

// Function to get environment variable with multiple fallbacks
function getEnvVar($keys, $default = null) {
    foreach ($keys as $key) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
}

// Railway MySQL service provides these environment variables
$db_host = getEnvVar(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], 'localhost');
$db_name = getEnvVar(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], 'railway');
$db_user = getEnvVar(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], 'root');
$db_pass = getEnvVar(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD', 'DB_PASS'], '');
$db_port = getEnvVar(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], '3306');

// Also try Railway's DATABASE_URL format
$database_url = getEnvVar(['DATABASE_URL', 'MYSQL_URL']);
if ($database_url) {
    $url_parts = parse_url($database_url);
    if ($url_parts) {
        $db_host = $url_parts['host'] ?? $db_host;
        $db_user = $url_parts['user'] ?? $db_user;
        $db_pass = $url_parts['pass'] ?? $db_pass;
        $db_name = ltrim($url_parts['path'] ?? '', '/') ?: $db_name;
        $db_port = $url_parts['port'] ?? $db_port;
    }
}

// Convert port to integer
$db_port = (int)$db_port;

// Check if we're in Railway environment
$is_production = !empty(getEnvVar(['RAILWAY_ENVIRONMENT', 'RAILWAY_PROJECT_ID']));

// Log connection details for debugging (only in development)
if (!$is_production) {
    error_log("Database connection attempt:");
    error_log("Host: $db_host");
    error_log("Database: $db_name");
    error_log("User: $db_user");
    error_log("Port: $db_port");
    error_log("Password: " . (empty($db_pass) ? 'EMPTY' : 'SET'));
}

try {
    // Create connection
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("MySQL connection failed: " . $conn->connect_error);
    }
    
    // Set charset for proper Unicode support
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Warning: Could not set MySQL charset: " . $conn->error);
    }
    
    // Test connection with a simple query
    $test_result = $conn->query("SELECT 1 as test");
    if (!$test_result) {
        throw new Exception("MySQL connection test failed: " . $conn->error);
    }
    
    // Log success (only in development)
    if (!$is_production) {
        error_log("MySQL connection successful!");
    }
    
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    
    if ($is_production) {
        // In production, show generic error
        throw new Exception("Database connection unavailable. Please try again later.");
    } else {
        // In development, show detailed error
        $error_details = "Database connection failed!\n\n";
        $error_details .= "Error: " . $e->getMessage() . "\n\n";
        $error_details .= "Connection Details:\n";
        $error_details .= "- Host: $db_host\n";
        $error_details .= "- Database: $db_name\n";
        $error_details .= "- User: $db_user\n";
        $error_details .= "- Port: $db_port\n";
        $error_details .= "- Password: " . (empty($db_pass) ? 'NOT SET' : 'SET') . "\n\n";
        $error_details .= "Environment Variables:\n";
        $error_details .= "- MYSQLHOST: " . getEnvVar(['MYSQLHOST']) . "\n";
        $error_details .= "- MYSQLDATABASE: " . getEnvVar(['MYSQLDATABASE']) . "\n";
        $error_details .= "- MYSQLUSER: " . getEnvVar(['MYSQLUSER']) . "\n";
        $error_details .= "- MYSQLPASSWORD: " . (getEnvVar(['MYSQLPASSWORD']) ? 'SET' : 'NOT SET') . "\n";
        $error_details .= "- DATABASE_URL: " . (getEnvVar(['DATABASE_URL']) ? 'SET' : 'NOT SET') . "\n";
        
        throw new Exception($error_details);
    }
}
?>
