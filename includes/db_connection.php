<?php
// db_connection.php - Improved for Railway PostgreSQL
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getDatabaseConnection() {
    static $pdo = null;
    
    // Return existing connection if available
    if ($pdo !== null) {
        return $pdo;
    }
    
    // Railway provides DATABASE_URL in this format:
    // postgresql://username:password@host:port/database
    $database_url = getenv('DATABASE_URL');
    
    if ($database_url) {
        try {
            // Parse the DATABASE_URL
            $url = parse_url($database_url);
            
            if (!$url || !isset($url['host'], $url['user'], $url['pass'], $url['path'])) {
                throw new Exception("Invalid DATABASE_URL format");
            }
            
            $host = $url['host'];
            $port = $url['port'] ?? 5432;
            $dbname = ltrim($url['path'], '/');
            $username = $url['user'];
            $password = $url['pass'];
            
            // Build DSN
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
            
            // Create PDO connection with Railway-optimized settings
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false, // Don't use persistent connections on Railway
                PDO::ATTR_TIMEOUT => 30 // Longer timeout for Railway
            ]);
            
            // Test the connection
            $pdo->query("SELECT 1");
            
            return $pdo;
            
        } catch (PDOException $e) {
            error_log("Railway DATABASE_URL connection failed: " . $e->getMessage());
            // Don't throw here, try fallback method
        }
    }
    
    // Fallback to individual environment variables (for local development)
    $host = getenv('PGHOST');
    $port = getenv('PGPORT') ?: 5432;
    $dbname = getenv('PGDATABASE');
    $username = getenv('PGUSER');
    $password = getenv('PGPASSWORD');
    
    if (!$host || !$dbname || !$username) {
        throw new Exception("Database connection failed: No valid connection parameters found. Make sure Railway PostgreSQL service is properly configured.");
    }
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
        
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 30
        ]);
        
        // Test the connection
        $pdo->query("SELECT 1");
        
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage() . ". Please check your Railway PostgreSQL service configuration.");
    }
}

// Global PDO instance
$pdo = null;

// Helper function to get PDO instance
function getPDO() {
    global $pdo;
    if ($pdo === null) {
        $pdo = getDatabaseConnection();
    }
    return $pdo;
}

// Helper function to execute queries with better error handling
function query($sql, $params = []) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare($sql);
        
        // Execute with parameters
        $success = $stmt->execute($params);
        
        if (!$success) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Query execution failed: " . $errorInfo[2]);
        }
        
        return $stmt;
        
    } catch (PDOException $e) {
        error_log("Database query failed: " . $e->getMessage());
        error_log("SQL: " . $sql);
        error_log("Params: " . json_encode($params));
        
        // Check if it's a connection issue
        if (strpos($e->getMessage(), 'connection') !== false || 
            strpos($e->getMessage(), 'server') !== false) {
            // Reset the global PDO to force reconnection
            global $pdo;
            $pdo = null;
            
            throw new Exception("Database connection lost. Please refresh the page and try again.");
        }
        
        throw new Exception("Database query failed: " . $e->getMessage());
    } catch (Exception $e) {
        error_log("Query error: " . $e->getMessage());
        throw $e;
    }
}

// Helper function to fetch a row
function fetch_assoc($stmt) {
    if (!$stmt) {
        throw new Exception("Invalid statement object");
    }
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Helper function to fetch all rows
function fetch_all($stmt) {
    if (!$stmt) {
        throw new Exception("Invalid statement object");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Helper function to get the last inserted ID
function get_last_insert_id($sequence_name = null) {
    $pdo = getPDO();
    return $pdo->lastInsertId($sequence_name);
}

// Helper function to check if table exists
function table_exists($table_name) {
    try {
        $stmt = query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = ?", [$table_name]);
        $result = fetch_assoc($stmt);
        return $result['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking if table exists: " . $e->getMessage());
        return false;
    }
}

// Helper function to execute multiple queries (for setup)
function execute_multiple_queries($queries) {
    $pdo = getPDO();
    $results = [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($queries as $query) {
            if (trim($query)) {
                $stmt = $pdo->prepare($query);
                $results[] = $stmt->execute();
            }
        }
        
        $pdo->commit();
        return $results;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Initialize connection test on include
try {
    // Test connection when this file is included
    $test_pdo = getDatabaseConnection();
    // Store in global for backward compatibility
    $pdo = $test_pdo;
} catch (Exception $e) {
    // Log the error but don't throw it immediately
    error_log("Database connection initialization failed: " . $e->getMessage());
    // The error will be caught when actually trying to use the connection
}
?>
