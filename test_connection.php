<?php
// test_connection.php - Debug tool to test database connection
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied.');
}

echo "<!DOCTYPE html>
<html><head><meta charset='UTF-8'><title>Connection Test</title>
<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
.success { color: green; background: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px; }
.error { color: red; background: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px; }
.info { color: blue; background: #d1ecf1; padding: 10px; margin: 10px 0; border-radius: 5px; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🔍 Database Connection Test</h1>";

// Test environment variables
echo "<h2>Environment Variables:</h2>";
echo "<div class='info'>";
echo "<strong>DATABASE_URL:</strong> " . (getenv('DATABASE_URL') ? 'Present' : 'Missing') . "<br>";
echo "<strong>PGHOST:</strong> " . (getenv('PGHOST') ?: 'Not Set') . "<br>";
echo "<strong>PGPORT:</strong> " . (getenv('PGPORT') ?: 'Not Set') . "<br>";
echo "<strong>PGDATABASE:</strong> " . (getenv('PGDATABASE') ?: 'Not Set') . "<br>";
echo "<strong>PGUSER:</strong> " . (getenv('PGUSER') ?: 'Not Set') . "<br>";
echo "<strong>PGPASSWORD:</strong> " . (getenv('PGPASSWORD') ? 'Set' : 'Not Set') . "<br>";
echo "</div>";

// Test PHP extensions
echo "<h2>PHP Extensions:</h2>";
echo "<div class='info'>";
echo "<strong>PDO:</strong> " . (extension_loaded('pdo') ? '✅ Available' : '❌ Missing') . "<br>";
echo "<strong>PDO_PGSQL:</strong> " . (extension_loaded('pdo_pgsql') ? '✅ Available' : '❌ Missing') . "<br>";
echo "<strong>PHP Version:</strong> " . phpversion() . "<br>";
echo "</div>";

// Test database connection
echo "<h2>Database Connection Test:</h2>";

try {
    require_once 'includes/db_connection.php';
    
    if (isset($pdo) && $pdo) {
        echo "<div class='success'>✅ Database connection successful!</div>";
        
        // Test a simple query
        try {
            $stmt = $pdo->query("SELECT version()");
            $version = $stmt->fetch();
            echo "<div class='success'>✅ PostgreSQL Version: " . $version['version'] . "</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Query test failed: " . $e->getMessage() . "</div>";
        }
        
        // Check if tables exist
        try {
            $tables = ['users', 'teachers', 'evaluations'];
            echo "<h3>Database Tables:</h3>";
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = ?)");
                $stmt->execute([$table]);
                $exists = $stmt->fetch()['exists'] ? '✅' : '❌';
                echo "<div>$exists Table '$table' " . ($stmt->fetch() ? 'exists' : 'missing') . "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Table check failed: " . $e->getMessage() . "</div>";
        }
        
    } else {
        echo "<div class='error'>❌ Database connection failed - connection object is null</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Connection failed: " . $e->getMessage() . "</div>";
    echo "<div class='info'><strong>Debug info:</strong><br>";
    echo "Error type: " . get_class($e) . "<br>";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "</div>";
}

echo "<p><a href='database_setup.php'>🔧 Run Database Setup</a> | <a href='login.php'>🔑 Go to Login</a></p>";
echo "</body></html>";
?>
