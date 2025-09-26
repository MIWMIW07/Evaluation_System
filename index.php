<?php
// Minimal index.php for debugging - bypasses all complex logic
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html><head><title>Debug</title></head><body>";
echo "<h1>Container is running!</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Test if we can start a session
try {
    session_start();
    echo "<p>✅ Session started successfully</p>";
} catch (Exception $e) {
    echo "<p>❌ Session error: " . $e->getMessage() . "</p>";
}

// Test environment variables
$database_url = getenv('DATABASE_URL');
if ($database_url) {
    echo "<p>✅ DATABASE_URL found</p>";
    try {
        $db_parts = parse_url($database_url);
        if ($db_parts && isset($db_parts['scheme'])) {
            echo "<p>Database type: " . $db_parts['scheme'] . "</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Error parsing DATABASE_URL: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>⚠️ No DATABASE_URL - local mode</p>";
}

// Test if we can connect to database WITHOUT including db_connection.php
echo "<p><strong>Testing database connection separately...</strong></p>";

try {
    if ($database_url) {
        $db_parts = parse_url($database_url);
        $host = $db_parts['host'];
        $port = $db_parts['port'] ?? null;
        $dbname = ltrim($db_parts['path'], '/');
        $username = $db_parts['user'];
        $password = $db_parts['pass'];

        if ($db_parts['scheme'] === 'postgres' || $db_parts['scheme'] === 'postgresql') {
            $dsn = "pgsql:host=$host;port=" . ($port ?? 5432) . ";dbname=$dbname";
        } elseif ($db_parts['scheme'] === 'mysql') {
            $dsn = "mysql:host=$host;port=" . ($port ?? 3306) . ";dbname=$dbname;charset=utf8mb4";
        } else {
            throw new Exception("Unsupported database scheme: " . $db_parts['scheme']);
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]);
        
        echo "<p>✅ Database connection successful!</p>";
    } else {
        echo "<p>⚠️ Skipping database test (no DATABASE_URL)</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<p><a href='test_connection.php'>Test Connection Page</a></p>";
echo "<p>Files in directory:</p><ul>";
foreach (glob("*.php") as $file) {
    echo "<li>$file</li>";
}
echo "</ul>";

echo "</body></html>";
?>
