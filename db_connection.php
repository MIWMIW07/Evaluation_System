<?php
// Flexible database connection for Railway (supports both MySQL and PostgreSQL)

function getEnvVar($keys, $default = null) {
    foreach ($keys as $key) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
    }
    return $default;
}

// Check if PostgreSQL is available (Railway often works better with PostgreSQL)
$postgres_url = getEnvVar(['DATABASE_URL', 'POSTGRES_URL']);
$mysql_host = getEnvVar(['MYSQLHOST', 'MYSQL_HOST']);

$conn = null;
$db_type = '';

try {
    // Try PostgreSQL first (if DATABASE_URL exists)
    if ($postgres_url) {
        $url_parts = parse_url($postgres_url);
        if ($url_parts && isset($url_parts['scheme']) && $url_parts['scheme'] === 'postgres') {
            $db_host = $url_parts['host'];
            $db_user = $url_parts['user'];
            $db_pass = $url_parts['pass'];
            $db_name = ltrim($url_parts['path'], '/');
            $db_port = $url_parts['port'] ?? 5432;
            
            $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name";
            $conn = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $db_type = 'postgresql';
            
            // Test connection
            $conn->query("SELECT 1");
            error_log("Connected to PostgreSQL successfully");
        }
    }
    
    // Fallback to MySQL if PostgreSQL not available
    if (!$conn && $mysql_host) {
        $db_host = getEnvVar(['MYSQLHOST', 'MYSQL_HOST'], 'localhost');
        $db_name = getEnvVar(['MYSQLDATABASE', 'MYSQL_DATABASE'], 'railway');
        $db_user = getEnvVar(['MYSQLUSER', 'MYSQL_USER'], 'root');
        $db_pass = getEnvVar(['MYSQLPASSWORD', 'MYSQL_PASSWORD'], '');
        $db_port = getEnvVar(['MYSQLPORT', 'MYSQL_PORT'], '3306');
        
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
        
        if ($conn->connect_error) {
            throw new Exception("MySQL connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        $db_type = 'mysql';
        error_log("Connected to MySQL successfully");
    }
    
    if (!$conn) {
        throw new Exception("No database configuration found");
    }
    
} catch (Exception $e) {
    $is_production = !empty(getEnvVar(['RAILWAY_ENVIRONMENT']));
    error_log("Database connection error: " . $e->getMessage());
    
    if ($is_production) {
        throw new Exception("Database service unavailable. Please try again later.");
    } else {
        $error_msg = "Database Connection Failed!\n\n";
        $error_msg .= "Error: " . $e->getMessage() . "\n\n";
        $error_msg .= "Available Environment Variables:\n";
        $error_msg .= "- DATABASE_URL: " . (getEnvVar(['DATABASE_URL']) ? 'SET' : 'NOT SET') . "\n";
        $error_msg .= "- POSTGRES_URL: " . (getEnvVar(['POSTGRES_URL']) ? 'SET' : 'NOT SET') . "\n";
        $error_msg .= "- MYSQLHOST: " . (getEnvVar(['MYSQLHOST']) ? getEnvVar(['MYSQLHOST']) : 'NOT SET') . "\n";
        $error_msg .= "- Current DB Type Attempt: " . ($postgres_url ? 'PostgreSQL' : 'MySQL') . "\n";
        
        throw new Exception($error_msg);
    }
}

// Helper function to execute queries (works with both MySQL and PostgreSQL)
function executeQuery($query, $params = []) {
    global $conn, $db_type;
    
    if ($db_type === 'postgresql') {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } else {
        // MySQL
        if (!empty($params)) {
            $stmt = $conn->prepare($query);
            $types = str_repeat('s', count($params)); // Assume all strings for simplicity
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        } else {
            return $conn->query($query);
        }
    }
}

error_log("Database connection established: $db_type");
?>
