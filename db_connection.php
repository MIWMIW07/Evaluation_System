<?php
// db_connection.php - Fixed for Railway PostgreSQL
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getDatabaseConnection() {
    // Try DATABASE_URL first (Railway's format)
    $database_url = getenv('DATABASE_URL');
    
    if ($database_url) {
        try {
            $url = parse_url($database_url);
            
            $host = $url['host'];
            $port = $url['port'] ?? 5432;
            $dbname = ltrim($url['path'], '/');
            $username = $url['user'];
            $password = $url['pass'];
            
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
            
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection with DATABASE_URL failed: " . $e->getMessage());
        }
    }
    
    // Fallback to individual environment variables
    $host = getenv('PGHOST') ?: 'localhost';
    $port = getenv('PGPORT') ?: '5432';
    $dbname = getenv('PGDATABASE') ?: 'railway';
    $username = getenv('PGUSER') ?: 'postgres';
    $password = getenv('PGPASSWORD') ?: '';
    
    if (empty($host) || empty($dbname)) {
        throw new Exception("Database configuration not found. Please check your Railway environment variables.");
    }
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Global PDO instance
$pdo = null;

// Helper function to execute queries
function query($sql, $params = []) {
    global $pdo;
    
    if ($pdo === null) {
        $pdo = getDatabaseConnection();
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage());
        throw new Exception("Database query failed: " . $e->getMessage());
    }
}

// Helper function to fetch a row
function fetch_assoc($stmt) {
    return $stmt->fetch();
}

// Helper function to fetch all rows
function fetch_all($stmt) {
    return $stmt->fetchAll();
}

// Initialize connection for backward compatibility
try {
    $conn = getDatabaseConnection();
} catch (Exception $e) {
    $conn = null;
    error_log("Database initialization failed: " . $e->getMessage());
}
?>
