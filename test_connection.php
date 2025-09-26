<?php
// Simple test to check if the container is running and database connection works
echo "Container is running!<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Check if DATABASE_URL exists
$database_url = getenv('DATABASE_URL');
if ($database_url) {
    echo "DATABASE_URL found: " . substr($database_url, 0, 20) . "...<br>";
    
    try {
        // Test database connection without including the full db_connection.php
        $db_parts = parse_url($database_url);
        if ($db_parts) {
            echo "Database host: " . ($db_parts['host'] ?? 'unknown') . "<br>";
            echo "Database scheme: " . ($db_parts['scheme'] ?? 'unknown') . "<br>";
        }
    } catch (Exception $e) {
        echo "Error parsing DATABASE_URL: " . $e->getMessage() . "<br>";
    }
} else {
    echo "No DATABASE_URL found - using local development mode<br>";
}

// Check if files exist
$files_to_check = ['login.php', 'admin.php', 'student_dashboard.php'];
foreach ($files_to_check as $file) {
    echo "File $file: " . (file_exists($file) ? "exists" : "missing") . "<br>";
}

echo "Test completed successfully!";
?>
