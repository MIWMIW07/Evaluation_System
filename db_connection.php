<?php
// db_connection.php - PostgreSQL version
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getDatabaseConnection() {
    $database_url = getenv('DATABASE_URL');
    
    if (!$database_url) {
        die("Database connection string not found. Please check your Railway environment variables.");
    }
    
    try {
        $url = parse_url($database_url);
        
        $host = $url['host'];
        $port = $url['port'];
        $dbname = ltrim($url['path'], '/');
        $username = $url['user'];
        $password = $url['pass'];
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Helper function to execute queries
function query($sql, $params = []) {
    $pdo = getDatabaseConnection();
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
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

// Initialize connection
try {
    $conn = getDatabaseConnection();
} catch (Exception $e) {
    $conn = null;
}
?>


