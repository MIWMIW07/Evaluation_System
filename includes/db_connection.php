<?php
// Database connection (supports Railway + local development)
function getDatabaseConnection() {
    // Railway: DATABASE_URL (Postgres or MySQL depending on setup)
    if (isset($_ENV['DATABASE_URL'])) {
        $database_url = $_ENV['DATABASE_URL'];
        $db_parts = parse_url($database_url);

        $host = $db_parts['host'];
        $port = $db_parts['port'] ?? null;
        $dbname = ltrim($db_parts['path'], '/');
        $username = $db_parts['user'];
        $password = $db_parts['pass'];

        // Detect scheme: postgres or mysql
        if ($db_parts['scheme'] === 'postgres') {
            $dsn = "pgsql:host=$host;port=" . ($port ?? 5432) . ";dbname=$dbname";
        } elseif ($db_parts['scheme'] === 'mysql') {
            $dsn = "mysql:host=$host;port=" . ($port ?? 3306) . ";dbname=$dbname;charset=utf8mb4";
        } else {
            die("Unsupported database scheme: " . $db_parts['scheme']);
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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
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

