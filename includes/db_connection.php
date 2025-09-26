<?php
// Database connection (supports Railway + local development)
function getDatabaseConnection() {
    // Railway: DATABASE_URL (Postgres or MySQL depending on setup)
    $database_url = getenv('DATABASE_URL');
    
    // Debug: Log what we're getting
    error_log("DATABASE_URL exists: " . ($database_url ? 'yes' : 'no'));
    if ($database_url) {
        error_log("DATABASE_URL starts with: " . substr($database_url, 0, 20));
    }
    
    if ($database_url) {
        $db_parts = parse_url($database_url);

        if (!$db_parts) {
            die("Invalid DATABASE_URL format");
        }

        $host = $db_parts['host'] ?? null;
        $port = $db_parts['port'] ?? null;
        $dbname = isset($db_parts['path']) ? ltrim($db_parts['path'], '/') : null;
        $username = $db_parts['user'] ?? null;
        $password = $db_parts['pass'] ?? null;

        if (!$host || !$dbname || !$username) {
            die("Missing required database connection parameters");
        }

        // Detect scheme: postgres or mysql
        if ($db_parts['scheme'] === 'postgres' || $db_parts['scheme'] === 'postgresql') {
            $dsn = "pgsql:host=$host;port=" . ($port ?? 5432) . ";dbname=$dbname";
        } elseif ($db_parts['scheme'] === 'mysql') {
            $dsn = "mysql:host=$host;port=" . ($port ?? 3306) . ";dbname=$dbname;charset=utf8mb4";
        } else {
            die("Unsupported database scheme: " . ($db_parts['scheme'] ?? 'unknown'));
        }
    } else {
        // Local development fallback (MySQL)
        $dsn = "mysql:host=localhost;dbname=evaluation_system;charset=utf8mb4";
        $username = "root";
        $password = "";
    }

    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 30
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Database connection failed. Please check your configuration.");
    }
}

$pdo = getDatabaseConnection();

// Database helper functions
function query($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return false;
    }
}

function fetch_assoc($result) {
    return $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
}

function fetch_all($result) {
    return $result ? $result->fetchAll(PDO::FETCH_ASSOC) : [];
}
?>
