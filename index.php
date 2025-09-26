<?php
// Absolutely minimal index.php - no includes, no complex logic
echo "<!DOCTYPE html>";
echo "<html><head><title>Test</title></head><body>";
echo "<h1>SUCCESS! Container is working!</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' . "</p>";
echo "</body></html>";
?>
