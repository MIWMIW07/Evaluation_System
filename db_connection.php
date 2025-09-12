<?php
// Database connection using environment variables for Railway deployment

// Try Railway's common MySQL environment variable patterns
$db_host = $_ENV['MYSQL_HOST'] ?? $_ENV['DB_HOST'] ?? getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$db_name = $_ENV['MYSQL_DATABASE'] ?? $_ENV['DB_NAME'] ?? getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'railway';
$db_user = $_ENV['MYSQL_USER'] ?? $_ENV['DB_USER'] ?? getenv('MYSQL_USER') ?: getenv('DB_USER') ?: 'root';
$db_pass = $_ENV['MYSQL_PASSWORD'] ?? $_ENV['DB_PASS'] ?? getenv('MYSQL_PASSWORD') ?: getenv('DB_PASS') ?: '';
$db_port = $_ENV['MYSQL_PORT'] ?? $_ENV['DB_PORT'] ?? getenv('MYSQL_PORT') ?: getenv('DB_PORT') ?: 3306;

// Also try Railway's DATABASE_URL format
$database_url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
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

// Log connection attempt for debugging
error_log("Attempting to connect to MySQL: Host=$db_host, Database=$db_name, User=$db_user, Port=$db_port");

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);

// Check connection
if ($conn->connect_error) {
    error_log("MySQL Connection failed: " . $conn->connect_error);
    
    // Don't expose sensitive info in production
    if (getenv('RAILWAY_ENVIRONMENT') === 'production' || !empty($_ENV['RAILWAY_ENVIRONMENT'])) {
        die("Database connection failed. Please check configuration.");
    } else {
        die("Connection failed: " . $conn->connect_error . 
            "<br>Host: $db_host<br>Database: $db_name<br>User: $db_user<br>Port: $db_port");
    }
}

// Set charset
$conn->set_charset("utf8mb4");

// Log successful connection
error_log("MySQL connection successful to database: $db_name");
?>
