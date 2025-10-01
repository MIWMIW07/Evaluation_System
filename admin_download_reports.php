<?php
// admin_download_reports.php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$reportsDir = __DIR__ . '/reports/';
$zipFiles = [];
$reportFolders = [];

// Get all ZIP files
if (is_dir($reportsDir)) {
    $files = scandir($reportsDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $zipFiles[] = [
                'name' => $file,
                'path' => 'reports/' . $file,
                'size' => filesize($reportsDir . $file),
                'date' => filemtime($reportsDir . $file)
            ];
        }
    }
    
    // Sort by date, newest first
    usort($zipFiles, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Check if report folders exist
$teacherReportsPath = $reportsDir . 'Teacher Evaluation Reports/Reports/';
if (is_dir($teacherReportsPath)) {
    $teachers = scandir($teacherReportsPath);
    foreach ($teachers as $teacher) {
        if ($teacher !== '.' && $teacher !== '..' && is_dir($teacherReportsPath . $teacher)) {
            $reportFolders[] = $teacher;
        }
    }
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Reports - Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: #5568d3;
        }

        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section h2 {
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .zip-list {
            list-style: none;
        }

        .zip-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .zip-item:hover {
            background: #f8f9ff;
            border-color: #667eea;
            transform: translateX(5px);
        }

        .zip-info {
            flex: 1;
        }

        .zip-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .zip-meta {
            font-size: 0.9em;
            color: #666;
        }

        .download-btn {
            padding: 10px 20px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            display: inline-block;
        }

        .download-btn:hover {
            background: #218838;
        }

        .folder-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .folder-item {
            padding: 20px;
            background: #f8f9ff;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-align: center;
            color: #333;
            font-weight: 500;
        }

        .folder-item::before {
            content: "📁";
            display: block;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state svg {
            width: 100px;
            height: 100px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Download Evaluation Reports</h1>
            <a href="admin.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <?php if (empty($zipFiles) && empty($reportFolders)): ?>
        <div class="section">
            <div class="empty-state">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <h3>No Reports Available</h3>
                <p>Generate reports first from the admin dashboard.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($zipFiles)): ?>
        <div class="section">
            <h2>📦 ZIP Files (All Reports Bundled)</h2>
            <div class="alert alert-info">
                <strong>💡 Tip:</strong> Download the ZIP file for all reports in one package!
            </div>
            <ul class="zip-list">
                <?php foreach ($zipFiles as $zip): ?>
                <li class="zip-item">
                    <div class="zip-info">
                        <div class="zip-name"><?php echo htmlspecialchars($zip['name']); ?></div>
                        <div class="zip-meta">
                            Size: <?php echo formatBytes($zip['size']); ?> | 
                            Created: <?php echo date('F j, Y g:i A', $zip['date']); ?>
                        </div>
                    </div>
                    <a href="<?php echo htmlspecialchars($zip['path']); ?>" class="download-btn" download>
                        ⬇ Download
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($reportFolders)): ?>
        <div class="section">
            <h2>👥 Individual Teacher Reports</h2>
            <div class="alert alert-warning">
                <strong>⚠️ Note:</strong> Individual files are organized by teacher. Use ZIP download for convenience.
            </div>
            <div class="folder-list">
                <?php foreach ($reportFolders as $teacher): ?>
                <div class="folder-item">
                    <?php echo htmlspecialchars($teacher); ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
