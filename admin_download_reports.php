<?php
// admin_download_reports.php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle direct file download
if (isset($_GET['download'])) {
    $file = basename($_GET['download']); // Security: only filename, no paths
    $filePath = __DIR__ . '/reports/' . $file;
    
    if (file_exists($filePath) && pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        $error = "File not found or invalid file type.";
    }
}

// Handle PDF download from nested folders
if (isset($_GET['pdf'])) {
    $relativePath = $_GET['pdf'];
    // Security: prevent directory traversal
    $relativePath = str_replace(['../', '..\\'], '', $relativePath);
    $filePath = __DIR__ . '/reports/Teacher Evaluation Reports/Reports/' . $relativePath;
    
    if (file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    } else {
        $error = "PDF file not found.";
    }
}

$reportsDir = __DIR__ . '/reports/';
$zipFiles = [];
$teacherReports = [];

// Get all ZIP files
if (is_dir($reportsDir)) {
    $files = scandir($reportsDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip') {
            $zipFiles[] = [
                'name' => $file,
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

// Browse teacher folders and their PDF files
$teacherReportsPath = $reportsDir . 'Teacher Evaluation Reports/Reports/';
if (is_dir($teacherReportsPath)) {
    $teachers = scandir($teacherReportsPath);
    foreach ($teachers as $teacher) {
        if ($teacher !== '.' && $teacher !== '..' && is_dir($teacherReportsPath . $teacher)) {
            $teacherReports[$teacher] = [];
            
            // Get programs for this teacher
            $teacherDir = $teacherReportsPath . $teacher . '/';
            $programs = scandir($teacherDir);
            
            foreach ($programs as $program) {
                if ($program !== '.' && $program !== '..' && is_dir($teacherDir . $program)) {
                    $programDir = $teacherDir . $program . '/';
                    $pdfFiles = array_diff(scandir($programDir), ['.', '..']);
                    
                    foreach ($pdfFiles as $pdf) {
                        if (pathinfo($pdf, PATHINFO_EXTENSION) === 'pdf') {
                            $teacherReports[$teacher][] = [
                                'name' => $pdf,
                                'program' => $program,
                                'path' => $teacher . '/' . $program . '/' . $pdf,
                                'size' => filesize($programDir . $pdf)
                            ];
                        }
                    }
                }
            }
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
            font-weight: 500;
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

        .zip-item, .pdf-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .zip-item:hover, .pdf-item:hover {
            background: #f8f9ff;
            border-color: #667eea;
            transform: translateX(5px);
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .file-meta {
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
            font-weight: 500;
        }

        .download-btn:hover {
            background: #218838;
        }

        .teacher-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9ff;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .teacher-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 15px;
        }

        .program-badge {
            display: inline-block;
            padding: 3px 10px;
            background: #667eea;
            color: white;
            border-radius: 12px;
            font-size: 0.85em;
            margin-right: 10px;
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

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f8f9ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            text-align: center;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Download Evaluation Reports</h1>
            <a href="admin.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <strong>❌ Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($zipFiles); ?></div>
                <div class="stat-label">ZIP Files Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($teacherReports); ?></div>
                <div class="stat-label">Teachers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $totalPDFs = 0;
                    foreach ($teacherReports as $reports) {
                        $totalPDFs += count($reports);
                    }
                    echo $totalPDFs;
                    ?>
                </div>
                <div class="stat-label">Total PDF Reports</div>
            </div>
        </div>

        <?php if (empty($zipFiles) && empty($teacherReports)): ?>
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
                <strong>💡 Recommended:</strong> Download the ZIP file to get all reports in one package!
            </div>
            <ul class="zip-list">
                <?php foreach ($zipFiles as $zip): ?>
                <li class="zip-item">
                    <div class="file-info">
                        <div class="file-name">📦 <?php echo htmlspecialchars($zip['name']); ?></div>
                        <div class="file-meta">
                            Size: <?php echo formatBytes($zip['size']); ?> | 
                            Created: <?php echo date('F j, Y g:i A', $zip['date']); ?>
                        </div>
                    </div>
                    <a href="?download=<?php echo urlencode($zip['name']); ?>" class="download-btn">
                        ⬇ Download ZIP
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!empty($teacherReports)): ?>
        <div class="section">
            <h2>👥 Individual PDF Reports by Teacher</h2>
            <div class="alert alert-warning">
                <strong>ℹ️ Note:</strong> You can download individual PDF files below, or use the ZIP file above for all reports at once.
            </div>
            
            <?php foreach ($teacherReports as $teacher => $reports): ?>
                <?php if (!empty($reports)): ?>
                <div class="teacher-section">
                    <div class="teacher-name">👨‍🏫 <?php echo htmlspecialchars($teacher); ?></div>
                    <ul class="zip-list">
                        <?php foreach ($reports as $report): ?>
                        <li class="pdf-item">
                            <div class="file-info">
                                <div class="file-name">
                                    📄 <?php echo htmlspecialchars($report['name']); ?>
                                </div>
                                <div class="file-meta">
                                    <span class="program-badge"><?php echo htmlspecialchars($report['program']); ?></span>
                                    Size: <?php echo formatBytes($report['size']); ?>
                                </div>
                            </div>
                            <a href="?pdf=<?php echo urlencode($report['path']); ?>" class="download-btn">
                                ⬇ Download PDF
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
