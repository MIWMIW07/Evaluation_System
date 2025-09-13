<?php
// PostgreSQL database connection for Railway

function getEnvVar($keys, $default = null) {
    foreach ($keys as $key) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
}

// Railway PostgreSQL provides DATABASE_URL
$database_url = getEnvVar(['DATABASE_URL', 'POSTGRES_URL']);

$is_production = !empty(getEnvVar(['RAILWAY_ENVIRONMENT']));

if (!$database_url) {
    $error_msg = "No DATABASE_URL found. Make sure PostgreSQL service is connected.";
    error_log($error_msg);
    throw new Exception($error_msg);
}

try {
    // Parse the DATABASE_URL
    $url_parts = parse_url($database_url);
    
    if (!$url_parts) {
        throw new Exception("Invalid DATABASE_URL format");
    }
    
    $db_host = $url_parts['host'];
    $db_user = $url_parts['user'];
    $db_pass = $url_parts['pass'];
    $db_name = ltrim($url_parts['path'], '/');
    $db_port = $url_parts['port'] ?? 5432;
    
    // Create PDO connection
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;sslmode=require";
    $conn = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10
    ]);
    
    // Test connection
    $test = $conn->query("SELECT 1 as test");
    if (!$test) {
        throw new Exception("Connection test failed");
    }
    
    if (!$is_production) {
        error_log("PostgreSQL connection successful!");
        error_log("Host: $db_host, Database: $db_name, Port: $db_port");
    }
    
} catch (Exception $e) {
    error_log("PostgreSQL Connection Error: " . $e->getMessage());
    
    if ($is_production) {
        throw new Exception("Database connection unavailable. Please try again later.");
    } else {
        $error_details = "PostgreSQL Connection Failed!\n\n";
        $error_details .= "Error: " . $e->getMessage() . "\n\n";
        $error_details .= "DATABASE_URL: " . ($database_url ? 'SET' : 'NOT SET') . "\n";
        if ($database_url) {
            $safe_url = preg_replace('/:[^:@]*@/', ':***@', $database_url);
            $error_details .= "URL (safe): " . $safe_url . "\n";
        }
        
        throw new Exception($error_details);
    }
}

// Helper function for PostgreSQL queries
function query($sql, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
        throw $e;
    }
}

// Helper function to get results as associative array (like mysqli)
function fetch_assoc($stmt) {
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetch_all($stmt) {
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
