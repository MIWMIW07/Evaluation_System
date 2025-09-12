<?php
// Database connection using Railway's environment variables

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

// Try Railway's common MySQL environment variable patterns
$db_host = getEnvVar(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], 'localhost');
$db_name = getEnvVar(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], 'railway');
$db_user = getEnvVar(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], 'root');
$db_pass = getEnvVar(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS'], '');
$db_port = getEnvVar(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], '3306');

// Also try Railway's DATABASE_URL format (mysql://user:pass@host:port/db)
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

// Ensure port is integer
$db_port = (int)$db_port;

// Log connection attempt for debugging (only in development)
if (!getEnvVar(['RAILWAY_ENVIRONMENT', 'RAILWAY_ENVIRONMENT_NAME'])) {
    error_log("MySQL Connection Attempt: Host=$db_host, Database=$db_name, User=$db_user, Port=$db_port");
}

try {
    // Create connection with error reporting
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset for proper Unicode support
    if (!$conn->set_charset("utf8mb4")) {
        error_log("MySQL charset error: " . $conn->error);
    }
    
    // Log successful connection (only in development)
    if (!getEnvVar(['RAILWAY_ENVIRONMENT', 'RAILWAY_ENVIRONMENT_NAME'])) {
        error_log("MySQL connection successful to database: $db_name");
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("MySQL Connection Error: " . $e->getMessage());
    
    // Don't expose sensitive info in production
    if (getEnvVar(['RAILWAY_ENVIRONMENT', 'RAILWAY_ENVIRONMENT_NAME'])) {
        die("Database connection failed. Please check configuration.");
    } else {
        die("Connection failed: " . $e->getMessage() . 
            "<br><br>Connection Details:<br>Host: $db_host<br>Database: $db_name<br>User: $db_user<br>Port: $db_port<br><br>" .
            "Available Environment Variables:<br>" . 
            "DATABASE_URL: " . (getEnvVar(['DATABASE_URL']) ? 'Set' : 'Not set') . "<br>" .
            "MYSQLHOST: " . (getEnvVar(['MYSQLHOST']) ? getEnvVar(['MYSQLHOST']) : 'Not set') . "<br>" .
            "MYSQL_HOST: " . (getEnvVar(['MYSQL_HOST']) ? getEnvVar(['MYSQL_HOST']) : 'Not set') . "<br>" .
            "RAILWAY_ENVIRONMENT: " . (getEnvVar(['RAILWAY_ENVIRONMENT']) ? getEnvVar(['RAILWAY_ENVIRONMENT']) : 'Not set'));
    }
}
?>
