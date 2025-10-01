<?php
// create_zip.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

function createZipFromFolder($source, $destination) {
    if (!extension_loaded('zip')) {
        return false;
    }

    if (!file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return false;
    }

    $source = str_replace('\\', '/', realpath($source));

    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $file = str_replace('\\', '/', $file);

            // Skip . and ..
            if (in_array(substr($file, strrpos($file, '/') + 1), ['.', '..'])) {
                continue;
            }

            $file = realpath($file);

            if (is_dir($file)) {
                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
            } else if (is_file($file)) {
                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
            }
        }
    }

    return $zip->close();
}

try {
    $reportsPath = __DIR__ . '/reports/Teacher Evaluation Reports/Reports/';
    
    if (!is_dir($reportsPath)) {
        echo json_encode([
            'success' => false,
            'error' => 'No reports folder found. Please generate reports first.'
        ]);
        exit;
    }

    // Check if there are any PDF files
    $hasFiles = false;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($reportsPath),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'pdf') {
            $hasFiles = true;
            break;
        }
    }

    if (!$hasFiles) {
        echo json_encode([
            'success' => false,
            'error' => 'No PDF reports found to zip.'
        ]);
        exit;
    }

    $zipPath = __DIR__ . '/reports/All_Reports_' . date('Y-m-d_H-i-s') . '.zip';
    
    if (createZipFromFolder($reportsPath, $zipPath)) {
        echo json_encode([
            'success' => true,
            'message' => 'ZIP package created successfully!',
            'zip_file' => 'reports/' . basename($zipPath),
            'zip_size' => filesize($zipPath)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create ZIP file. Please check file permissions.'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
