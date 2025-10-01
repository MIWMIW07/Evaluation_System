<?php
// quick_logo_fix.php - Place this in your root directory and run it once

$inputFile = __DIR__ . '/logo.png';
$backupFile = __DIR__ . '/logo_original.png';
$outputFile = __DIR__ . '/logo.png';

if (!file_exists($inputFile)) {
    die("Error: logo.png not found in root directory\n");
}

// Create backup first
copy($inputFile, $backupFile);
echo "✓ Backup created: logo_original.png\n";

// Check if GD extension is loaded
if (!extension_loaded('gd')) {
    die("Error: GD extension not enabled. Enable it in php.ini or use online converter.\n");
}

// Load the image
$img = @imagecreatefrompng($inputFile);
if (!$img) {
    die("Error: Could not load PNG image\n");
}

// Get dimensions
$width = imagesx($img);
$height = imagesy($img);

// Create new image with white background
$newImg = imagecreatetruecolor($width, $height);
$white = imagecolorallocate($newImg, 255, 255, 255);
imagefill($newImg, 0, 0, $white);

// Copy original image onto white background (removes transparency)
imagecopy($newImg, $img, 0, 0, 0, 0, $width, $height);

// Save without alpha channel
imagepng($newImg, $outputFile, 9);

// Cleanup
imagedestroy($img);
imagedestroy($newImg);

echo "✓ Logo converted successfully!\n";
echo "✓ Original saved as: logo_original.png\n";
echo "✓ New logo (no alpha): logo.png\n";
echo "\nYou can now delete this script and run your report generator.\n";
?>
