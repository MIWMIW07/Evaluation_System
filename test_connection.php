<?php
// test_connection.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Use the same connection logic as db_connection.php
require_once 'db_connection.php';

if (!isset($conn)) {
    die("<p style='color:red;'>❌ Could not establish database connection.</p>");
}

echo "<p style='color:green;'>✅ Connected to PostgreSQL database successfully!</p>";

// Try a simple query
try {
    $result = query("SELECT version()");
    $version = fetch_assoc($result);
    echo "<p>PostgreSQL Version: " . $version['version'] . "</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Query failed: " . $e->getMessage() . "</p>";
}
?>
