<?php
// test_connection.php - Railway Database Connection Tester
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Railway Database Connection Test</h2>";
echo "<hr>";

// Function to safely display environment variables (hiding sensitive parts)
function maskSensitiveData($value, $showChars = 4) {
    if (empty($value)) return 'Not Set';
    if (strlen($value) <= $showChars) return str_repeat('*', strlen($value));
    return substr($value, 0, $showChars) . str_repeat('*', strlen($value) - $showChars);
}

echo "<h3>1. Environment Variables Check</h3>";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
echo "<tr><th style='padding: 8px;'>Variable</th><th style='padding: 8px;'>Status</th><th style='padding: 8px;'>Value (masked)</th></tr>";

$env_vars = [
    'DATABASE_URL' => getenv('DATABASE_URL'),
    'PGHOST' => getenv('PGHOST'),
    'PGPORT' => getenv('PGPORT'),
    'PGDATABASE' => getenv('PGDATABASE'),
    'PGUSER' => getenv('PGUSER'),
    'PGPASSWORD' => getenv('PGPASSWORD')
];

foreach ($env_vars as $var => $value) {
    $status = $value ? '✅ Set' : '❌ Not Set';
    $maskedValue = $var === 'PGPASSWORD' || $var === 'DATABASE_URL' 
                   ? maskSensitiveData($value, 3) 
                   : ($value ?: 'Not Set');
    
    echo "<tr><td style='padding: 8px;'>$var</td><td style='padding: 8px;'>$status</td><td style='padding: 8px;'>$maskedValue</td></tr>";
}
echo "</table>";

echo "<h3>2. DATABASE_URL Parsing Test</h3>";
$database_url = getenv('DATABASE_URL');
if ($database_url) {
    echo "✅ DATABASE_URL is set<br>";
    
    $url = parse_url($database_url);
    if ($url) {
        echo "✅ DATABASE_URL parsing successful<br>";
        echo "Host: " . ($url['host'] ?? 'Not found') . "<br>";
        echo "Port: " . ($url['port'] ?? 'Not found') . "<br>";
        echo "Database: " . (isset($url['path']) ? ltrim($url['path'], '/') : 'Not found') . "<br>";
        echo "User: " . ($url['user'] ?? 'Not found') . "<br>";
        echo "Password: " . (isset($url['pass']) ? maskSensitiveData($url['pass'], 2) : 'Not found') . "<br>";
    } else {
        echo "❌ Failed to parse DATABASE_URL<br>";
    }
} else {
    echo "❌ DATABASE_URL is not set<br>";
}

echo "<hr>";

echo "<h3>3. Connection Test #1: Using DATABASE_URL</h3>";
if ($database_url) {
    try {
        $url = parse_url($database_url);
        
        if (!$url) {
            throw new Exception("Invalid DATABASE_URL format");
        }
        
        $host = $url['host'] ?? null;
        $port = $url['port'] ?? 5432;
        $dbname = isset($url['path']) ? ltrim($url['path'], '/') : null;
        $username = $url['user'] ?? null;
        $password = $url['pass'] ?? null;
        
        if (!$host || !$dbname || !$username) {
            throw new Exception("Missing required connection parameters");
        }
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        echo "DSN: $dsn<br>";
        echo "Username: $username<br>";
        
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 10
        ]);
        
        echo "✅ Connection successful using DATABASE_URL!<br>";
        
        // Test a simple query
        $stmt = $pdo->query("SELECT version() as version, current_database() as database, current_user as user");
        $result = $stmt->fetch();
        
        echo "Database version: " . substr($result['version'], 0, 50) . "...<br>";
        echo "Connected to database: " . $result['database'] . "<br>";
        echo "Connected as user: " . $result['user'] . "<br>";
        
    } catch (Exception $e) {
        echo "❌ Connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Skipped - DATABASE_URL not available<br>";
}

echo "<hr>";

echo "<h3>4. Connection Test #2: Using Individual Variables</h3>";
try {
    $host = getenv('PGHOST') ?: 'localhost';
    $port = getenv('PGPORT') ?: '5432';
    $dbname = getenv('PGDATABASE') ?: 'railway';
    $username = getenv('PGUSER') ?: 'postgres';
    $password = getenv('PGPASSWORD') ?: '';
    
    echo "Host: $host<br>";
    echo "Port: $port<br>";
    echo "Database: $dbname<br>";
    echo "Username: $username<br>";
    echo "Password: " . maskSensitiveData($password, 2) . "<br>";
    
    if (empty($host) || empty($dbname)) {
        throw new Exception("Missing required environment variables");
    }
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10
    ]);
    
    echo "✅ Connection successful using individual variables!<br>";
    
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";

echo "<h3>5. Testing Your db_connection.php</h3>";
try {
    require_once 'includes/db_connection.php';
    $pdo = getDatabaseConnection();
    echo "✅ Your db_connection.php works correctly!<br>";
    
    // Test the query helper function
    $result = query("SELECT 'Hello World' as message");
    $row = fetch_assoc($result);
    echo "✅ Query helper functions work: " . $row['message'] . "<br>";
    
} catch (Exception $e) {
    echo "❌ Your db_connection.php failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";

echo "<h3>6. Tables Check</h3>";
try {
    if (isset($pdo)) {
        $tables = ['users', 'teachers', 'evaluations'];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM information_schema.tables WHERE table_name = ?");
            $stmt->execute([$table]);
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                // Table exists, check row count
                $countStmt = $pdo->query("SELECT COUNT(*) as rows FROM $table");
                $countResult = $countStmt->fetch();
                echo "✅ Table '$table' exists with {$countResult['rows']} rows<br>";
            } else {
                echo "❌ Table '$table' does not exist<br>";
            }
        }
    } else {
        echo "❌ No database connection available for table check<br>";
    }
} catch (Exception $e) {
    echo "❌ Table check failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";

echo "<h3>7. Recommendations</h3>";

if (!getenv('DATABASE_URL') && !getenv('PGHOST')) {
    echo "<div style='background: #ffe6e6; padding: 15px; border: 1px solid #ff9999; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>❌ Critical Issue:</strong> No database environment variables found.<br>";
    echo "Make sure you have deployed your application to Railway and added a PostgreSQL database service.<br>";
    echo "Railway should automatically set the DATABASE_URL environment variable.";
    echo "</div>";
}

if (getenv('DATABASE_URL')) {
    echo "<div style='background: #e6ffe6; padding: 15px; border: 1px solid #99ff99; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>✅ Good:</strong> DATABASE_URL is available (Railway standard).<br>";
    echo "This is the preferred method for Railway deployments.";
    echo "</div>";
}

echo "<div style='background: #e6f3ff; padding: 15px; border: 1px solid #99ccff; border-radius: 5px; margin: 10px 0;'>";
echo "<strong>💡 Next Steps:</strong><br>";
echo "1. If no environment variables are found, make sure your Railway deployment includes a PostgreSQL service<br>";
echo "2. If connection fails, try running database_setup.php to create tables<br>";
echo "3. If tables don't exist, definitely run database_setup.php<br>";
echo "4. Check Railway logs for any deployment errors";
echo "</div>";

echo "<hr>";
echo "<p><a href='database_setup.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Run Database Setup</a></p>";
echo "<p><a href='admin.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔙 Back to Admin</a></p>";
?>
