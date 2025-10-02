<?php
// admin_download_reports.php
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Handle AJAX refresh request
if (isset($_GET['action']) && $_GET['action'] === 'refresh') {
    header('Content-Type: application/json');
    echo json_encode(getReportsData());
    exit;
}

function getReportsData() {
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
    
    // Count total PDFs
    $totalPDFs = 0;
    foreach ($teachers as $programs) {
        foreach ($programs as $pdfs) {
            $totalPDFs += count($pdfs);
        }
    }
    
    return [
        'teachers' => $teachers,
        'totalPDFs' => $totalPDFs,
        'teacherCount' => count($teachers),
        'timestamp' => time()
    ];
}

$data = getReportsData();
$teachers = $data['teachers'];
$totalPDFs = $data['totalPDFs'];

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
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: #667eea;
        }

        .btn-primary:hover:not(:disabled) {
            background: #5568d3;
        }

        .btn-refresh {
            background: #28a745;
        }

        .btn-refresh:hover:not(:disabled) {
            background: #218838;
        }

        .btn-refresh.loading {
            background: #6c757d;
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card.updating {
            animation: pulse 0.5s ease-in-out;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            margin-top: 5px;
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

        .teacher-section {
            margin-bottom: 20px;
            border: 2px solid #667eea;
            border-radius: 8px;
            overflow: hidden;
            transition: opacity 0.3s;
        }

        .teacher-section.removing {
            opacity: 0.5;
            pointer-events: none;
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
            display: flex;
            align-items: center;
            gap: 8px;
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

        .download-btn {
            padding: 8px 16px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            display: inline-block;
            font-size: 0.9em;
        }

        .download-btn:hover {
            background: #218838;
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
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
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

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: bold;
            margin-left: 5px;
        }

        .badge-blue {
            background: #007bff;
            color: white;
        }

        .last-updated {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }

        #notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
        }

        .notification {
            background: white;
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s ease-out;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100%);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .notification.success {
            border-left: 4px solid #28a745;
        }

        .notification.error {
            border-left: 4px solid #dc3545;
        }

        .notification.info {
            border-left: 4px solid #17a2b8;
        }
    </style>
</head>
<body>
    <div id="notification-container"></div>

    <div class="container">
        <div class="header">
            <h1>📥 Download Evaluation Reports</h1>
            <div class="header-actions">
                <a href="admin.php" class="btn btn-primary">← Back to Dashboard</a>
                <button id="refreshBtn" class="btn btn-refresh" onclick="refreshReports()">
                    <span id="refreshIcon">🔄</span>
                    <span id="refreshText">Refresh Reports</span>
                </button>
            </div>
            <div class="last-updated" id="lastUpdated">
                Last updated: Just now
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card" id="teacherStat">
                <div class="stat-number" id="teacherCount"><?php echo count($teachers); ?></div>
                <div class="stat-label">Teachers</div>
            </div>
            <div class="stat-card" id="pdfStat">
                <div class="stat-number" id="pdfCount"><?php echo $totalPDFs; ?></div>
                <div class="stat-label">Total PDF Reports</div>
            </div>
        </div>

        <div id="reportsContainer">
            <?php if (empty($teachers)): ?>
            <div class="section">
                <div class="empty-state">
                    <h3>📄 No Reports Available</h3>
                    <p>Generate reports first from the admin dashboard.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="section">
                <h2>👥 Teacher Evaluation Reports</h2>
                <div class="alert alert-info">
                    <strong>ℹ️ Note:</strong> Click on a teacher's name to view and download their reports by program. Use the refresh button to check for changes.
                </div>
                
                <div id="teacherList">
                    <?php foreach ($teachers as $teacherName => $programs): ?>
                    <div class="teacher-section" data-teacher="<?php echo htmlspecialchars($teacherName); ?>">
                        <div class="teacher-header" onclick="toggleTeacher(this)">
                            <span>📚 <?php echo htmlspecialchars($teacherName); ?></span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        <div class="teacher-content hidden">
                            <?php foreach ($programs as $programName => $pdfs): ?>
                            <div class="program-section">
                                <div class="program-title">
                                    📖 <?php echo htmlspecialchars($programName); ?>
                                    <span class="badge badge-blue"><?php echo count($pdfs); ?> file<?php echo count($pdfs) > 1 ? 's' : ''; ?></span>
                                </div>
                                <ul class="pdf-list">
                                    <?php foreach ($pdfs as $pdf): ?>
                                    <li class="pdf-item">
                                        <div class="pdf-info">
                                            <div class="pdf-name">📄 <?php echo htmlspecialchars($pdf['name']); ?></div>
                                            <div class="pdf-size"><?php echo formatBytes($pdf['size']); ?></div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($pdf['path']); ?>" class="download-btn" download>
                                            ⬇ Download PDF
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
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    let isRefreshing = false;
    let expandedTeachers = new Set();

    function toggleTeacher(element) {
        const content = element.nextElementSibling;
        const section = element.parentElement;
        const teacherName = section.getAttribute('data-teacher');
        
        content.classList.toggle('hidden');
        section.classList.toggle('collapsed');
        
        // Track expanded state
        if (content.classList.contains('hidden')) {
            expandedTeachers.delete(teacherName);
        } else {
            expandedTeachers.add(teacherName);
        }
    }

    function showNotification(message, type = 'info') {
        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        
        const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
        notification.innerHTML = `
            <span style="font-size: 1.2em;">${icon}</span>
            <span>${message}</span>
        `;
        
        container.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideInRight 0.3s ease-out reverse';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    function formatBytes(bytes) {
        if (bytes >= 1073741824) {
            return (bytes / 1073741824).toFixed(2) + ' GB';
        } else if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(2) + ' MB';
        } else if (bytes >= 1024) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return bytes + ' bytes';
        }
    }

    async function refreshReports() {
        if (isRefreshing) return;
        
        isRefreshing = true;
        const refreshBtn = document.getElementById('refreshBtn');
        const refreshIcon = document.getElementById('refreshIcon');
        const refreshText = document.getElementById('refreshText');
        
        // Update button state
        refreshBtn.disabled = true;
        refreshBtn.classList.add('loading');
        refreshIcon.innerHTML = '<span class="spinner"></span>';
        refreshText.textContent = 'Refreshing...';
        
        try {
            const response = await fetch('?action=refresh&t=' + Date.now());
            
            if (!response.ok) {
                throw new Error('Failed to fetch reports');
            }
            
            const data = await response.json();
            
            // Check if there are changes
            const currentTeacherCount = parseInt(document.getElementById('teacherCount').textContent);
            const currentPdfCount = parseInt(document.getElementById('pdfCount').textContent);
            
            const hasChanges = currentTeacherCount !== data.teacherCount || 
                              currentPdfCount !== data.totalPDFs;
            
            // Update statistics with animation
            if (hasChanges) {
                document.getElementById('teacherStat').classList.add('updating');
                document.getElementById('pdfStat').classList.add('updating');
                
                setTimeout(() => {
                    document.getElementById('teacherCount').textContent = data.teacherCount;
                    document.getElementById('pdfCount').textContent = data.totalPDFs;
                    
                    document.getElementById('teacherStat').classList.remove('updating');
                    document.getElementById('pdfStat').classList.remove('updating');
                }, 250);
            }
            
            // Update teacher list
            updateTeacherList(data.teachers);
            
            // Update last updated time
            const now = new Date();
            document.getElementById('lastUpdated').textContent = 
                `Last updated: ${now.toLocaleTimeString()}`;
            
            // Show notification
            if (hasChanges) {
                showNotification('Reports refreshed successfully! Changes detected.', 'success');
            } else {
                showNotification('Reports are up to date. No changes found.', 'info');
            }
            
        } catch (error) {
            console.error('Refresh error:', error);
            showNotification('Failed to refresh reports. Please try again.', 'error');
        } finally {
            // Reset button state
            isRefreshing = false;
            refreshBtn.disabled = false;
            refreshBtn.classList.remove('loading');
            refreshIcon.textContent = '🔄';
            refreshText.textContent = 'Refresh Reports';
        }
    }

    function updateTeacherList(teachers) {
        const container = document.getElementById('reportsContainer');
        
        if (Object.keys(teachers).length === 0) {
            container.innerHTML = `
                <div class="section">
                    <div class="empty-state">
                        <h3>📄 No Reports Available</h3>
                        <p>Generate reports first from the admin dashboard.</p>
                    </div>
                </div>
            `;
            return;
        }
        
        let html = `
            <div class="section">
                <h2>👥 Teacher Evaluation Reports</h2>
                <div class="alert alert-info">
                    <strong>ℹ️ Note:</strong> Click on a teacher's name to view and download their reports by program. Use the refresh button to check for changes.
                </div>
                <div id="teacherList">
        `;
        
        for (const [teacherName, programs] of Object.entries(teachers)) {
            const isExpanded = expandedTeachers.has(teacherName);
            const collapsedClass = isExpanded ? '' : 'collapsed';
            const hiddenClass = isExpanded ? '' : 'hidden';
            
            html += `
                <div class="teacher-section ${collapsedClass}" data-teacher="${escapeHtml(teacherName)}">
                    <div class="teacher-header" onclick="toggleTeacher(this)">
                        <span>📚 ${escapeHtml(teacherName)}</span>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="teacher-content ${hiddenClass}">
            `;
            
            for (const [programName, pdfs] of Object.entries(programs)) {
                html += `
                    <div class="program-section">
                        <div class="program-title">
                            📖 ${escapeHtml(programName)}
                            <span class="badge badge-blue">${pdfs.length} file${pdfs.length > 1 ? 's' : ''}</span>
                        </div>
                        <ul class="pdf-list">
                `;
                
                pdfs.forEach(pdf => {
                    html += `
                        <li class="pdf-item">
                            <div class="pdf-info">
                                <div class="pdf-name">📄 ${escapeHtml(pdf.name)}</div>
                                <div class="pdf-size">${formatBytes(pdf.size)}</div>
                            </div>
                            <a href="${escapeHtml(pdf.path)}" class="download-btn" download>
                                ⬇ Download PDF
                            </a>
                        </li>
                    `;
                });
                
                html += `
                        </ul>
                    </div>
                `;
            }
            
            html += `
                    </div>
                </div>
            `;
        }
        
        html += `
                </div>
            </div>
        `;
        
        container.innerHTML = html;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Optional: Auto-refresh every 30 seconds
    // Uncomment the lines below if you want automatic refresh
    /*
    setInterval(() => {
        refreshReports();
    }, 30000); // 30 seconds
    */
    </script>
</body>
</html>
