<?php
// check_gd.php
echo "<h1>PHP GD Extension Check</h1>";

if (extension_loaded('gd')) {
    $gd_info = gd_info();
    echo "<p style='color: green;'><strong>✓ GD Extension is loaded!</strong></p>";
    echo "<pre>";
    print_r($gd_info);
    echo "</pre>";
    
    // Test if we can create an image
    try {
        $im = imagecreate(100, 100);
        $background_color = imagecolorallocate($im, 255, 255, 255);
        $text_color = imagecolorallocate($im, 0, 0, 0);
        imagestring($im, 5, 10, 10, "GD Works!", $text_color);
        
        // Save to file to test
        imagepng($im, __DIR__ . '/test_gd.png');
        imagedestroy($im);
        
        if (file_exists(__DIR__ . '/test_gd.png')) {
            echo "<p style='color: green;'>✓ GD can create PNG images!</p>";
            unlink(__DIR__ . '/test_gd.png'); // Clean up
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ GD error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ GD Extension is NOT loaded!</strong></p>";
}

// Check TCPDF
if (class_exists('TCPDF')) {
    echo "<p style='color: green;'>✓ TCPDF is available!</p>";
} else {
    echo "<p style='color: red;'>✗ TCPDF is NOT available!</p>";
}
