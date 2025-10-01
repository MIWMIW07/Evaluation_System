<?php
// admin_download_reports.php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$reportsDir = __DIR__ . '/reports/';
$teachers = [];

// Get teacher folders with their PDFs
$teacherReportsPath = $reportsDir . 'Teacher Evaluation Reports/Reports/';
if (is_dir($teacherReportsPath)) {
    $teacherFolders = scandir($teacherReportsPath);
    foreach ($teacherFolders as $teacherName) {
        if ($teacherName === '.' || $teacherName === '..') continue;
        
        $teacherPath = $teacherReportsPath . $teacherName . '/';
        if (!is_dir($teacherPath)) continue;
        
        $programs = [];
        $programFolders = scandir($teacherPath);
        
        foreach ($programFolders as $program) {
            if ($program === '.' || $program === '..') continue;
            
            $programPath = $teacherPath . $program . '/';
            if (!is_dir($programPath)) continue;
            
            $pdfs = [];
            $pdfFiles = scandir($programPath);
            
            foreach ($pdfFiles as $pdf) {
                if (pathinfo($pdf, PATHINFO_EXTENSION) === 'pdf') {
                    $pdfs[] = [
                        'name' => $pdf,
                        'path' => 'reports/Teacher Evaluation Reports/Reports/' . $teacherName . '/' . $program . '/' . $pdf,
                        'size' => filesize($programPath . $pdf)
                    ];
                }
            }
            
            if (!empty($pdfs)) {
                $programs[$program] = $pdfs;
            }
        }
        
        if (!empty($programs)) {
            $teachers[$teacherName] = $programs;
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

        .header-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #667eea;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
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

        .teacher-section {
            margin-bottom: 20px;
            border: 2px solid #667eea;
            border-radius: 8px;
            overflow: hidden;
        }

        .teacher-header {
            background: #667eea;
            color: white;
            padding: 15px;
            font-weight: bold;
            font-size: 1.1em;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .teacher-header:hover {
            background: #5568d3;
        }

        .teacher-content {
            padding: 15px;
            background: #f8f9ff;
        }

        .program-section {
            margin-bottom: 15px;
            background: white;
            padding: 15px;
            border-radius: 5px;
        }

        .program-title {
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.05em;
        }

        .pdf-list {
            list-style: none;
        }

        .pdf-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }

        .pdf-item:last-child {
            border-bottom: none;
        }

        .pdf-info {
            flex: 1;
        }

        .pdf-name {
            color: #333;
            font-weight: 500;
            margin-bottom: 3px;
        }

        .pdf-size {
            font-size: 0.85em;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 10px;
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

        .toggle-icon {
            transition: transform 0.3s;
        }

        .collapsed .toggle-icon {
            transform: rotate(-90deg);
        }

        .teacher-content.hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📥 Download Evaluation Reports</h1>
            <div class="header-actions">
                <a href="admin_dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <div style="background: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div style="font-size: 2.5em; font-weight: bold; color: #667eea;">
                    <?php echo count($teachers); ?>
                </div>
                <div style="color: #666; margin-top: 5px;">Teachers</div>
            </div>
            <div style="background: white; padding: 25px; border-radius: 10px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div style="font-size: 2.5em; font-weight: bold; color: #667eea;">
                    <?php 
                    $totalPDFs = 0;
                    foreach ($teachers as $programs) {
                        foreach ($programs as $pdfs) {
                            $totalPDFs += count($pdfs);
                        }
                    }
                    echo $totalPDFs;
                    ?>
                </div>
                <div style="color: #666; margin-top: 5px;">Total PDF Reports</div>
            </div>
        </div>

        <?php if (!empty($teachers)): ?>
        <div class="section">
            <h2>👥 Individual PDF Reports by Teacher</h2>
            <div class="alert alert-info">
                <strong>ℹ️ Note:</strong> Click on a teacher's name to view and download their individual reports.
            </div>
            
            <?php foreach ($teachers as $teacherName => $programs): ?>
            <div class="teacher-section">
                <div class="teacher-header" onclick="toggleTeacher(this)">
                    <span>📚 <?php echo htmlspecialchars($teacherName); ?></span>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="teacher-content hidden">
                    <?php foreach ($programs as $programName => $pdfs): ?>
                    <div class="program-section">
                        <div class="program-title">📖 <?php echo htmlspecialchars($programName); ?></div>
                        <ul class="pdf-list">
                            <?php foreach ($pdfs as $pdf): ?>
                            <li class="pdf-item">
                                <div class="pdf-info">
                                    <div class="pdf-name">📄 <?php echo htmlspecialchars($pdf['name']); ?></div>
                                    <div class="pdf-size"><?php echo formatBytes($pdf['size']); ?></div>
                                </div>
                                <a href="<?php echo htmlspecialchars($pdf['path']); ?>" class="download-btn" download>
                                    ⬇ Download
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function toggleTeacher(element) {
        const content = element.nextElementSibling;
        const section = element.parentElement;
        
        content.classList.toggle('hidden');
        section.classList.toggle('collapsed');
    }
    </script>
</body>
</html>
