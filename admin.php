<?php
// Fix for header warning - add output buffering
ob_start();
session_start();
require_once 'includes/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get evaluation statistics
try {
    $pdo = getPDO();
    
    // Total evaluations count
    $totalEvals = $pdo->query("SELECT COUNT(*) FROM evaluations")->fetchColumn();
    
    // Recent evaluations (last 10) - FIXED QUERY
    $recentEvals = $pdo->query("
        SELECT * 
        FROM evaluations 
        ORDER BY submitted_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Teacher statistics - FIXED QUERY
    $teacherStats = $pdo->query("
        SELECT 
            teacher_name, 
            program,
            COUNT(*) as eval_count,
            AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                 q2_1 + q2_2 + q2_3 + q2_4 + 
                 q3_1 + q3_2 + q3_3 + q3_4 + 
                 q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20) * 100 as avg_score
        FROM evaluations 
        GROUP BY teacher_name, program 
        ORDER BY eval_count DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $totalEvals = 0;
    $recentEvals = [];
    $teacherStats = [];
}
ob_end_clean(); // Clean the output buffer
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Teacher Evaluation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #8B0000 0%, #5a0000 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fefefe;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #D4AF37;
            padding-bottom: 25px;
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 25%;
            width: 50%;
            height: 2px;
            background: #8B0000;
        }
        
        .header h1 {
            color: #8B0000;
            margin-bottom: 10px;
            font-size: 2.5em;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
        }
        
        .header p {
            color: #5a5a5a;
            font-size: 1.1em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            border-left: 5px solid #D4AF37;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #8B0000;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #5a5a5a;
            font-size: 1.1em;
        }
        
        .card {
            background: #fff;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            border-left: 5px solid #D4AF37;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .card h3 {
            color: #8B0000;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5em;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .btn {
            background: #8B0000;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            margin: 5px;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .btn:hover {
            background: #6d0000;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        }
        
        .btn-gold { 
            background: #D4AF37; 
            color: #333;
        }
        
        .btn-gold:hover { 
            background: #b8941f; 
            color: white;
        }
        
        .btn-warning { 
            background: #ffc107; 
            color: #333; 
        }
        
        .btn-warning:hover { 
            background: #e0a800; 
        }
        
        .btn-danger { 
            background: #dc3545; 
        }
        
        .btn-danger:hover { 
            background: #c82333; 
        }
        
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .evaluation-table th,
        .evaluation-table td {
            border: 1px solid #e0e0e0;
            padding: 12px;
            text-align: left;
        }
        
        .evaluation-table th {
            background: #8B0000;
            color: white;
            font-weight: 600;
        }
        
        .evaluation-table tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .evaluation-table tr:hover {
            background: #f0f0f0;
        }
        
        .loading {
            color: #6c757d;
            font-style: italic;
            padding: 15px;
            text-align: center;
        }
        
        .result-box {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        
        .result-success { 
            background: #d4edda; 
            border-left-color: #28a745; 
            color: #155724;
        }
        
        .result-error { 
            background: #f8d7da; 
            border-left-color: #dc3545; 
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        
        .teacher-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .teacher-stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }
        
        .teacher-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .teacher-stat-card h4 {
            color: #8B0000;
            margin-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 8px;
        }
        
        .debug-info {
            background: #fff9e6;
            border: 1px solid #D4AF37;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 0.9em;
            color: #8B0000;
        }
        
        .score-bar {
            height: 10px;
            background: #f0f0f0;
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }
        
        .score-fill {
            height: 100%;
            background: #D4AF37;
            border-radius: 5px;
        }
        
        .rating-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 5px;
        }
        
        .rating-excellent { background: #28a745; color: white; }
        .rating-verygood { background: #17a2b8; color: white; }
        .rating-good { background: #ffc107; color: #333; }
        .rating-satisfactory { background: #fd7e14; color: white; }
        .rating-needsimprovement { background: #dc3545; color: white; }
        
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 250px;
            background: #8B0000;
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        .admin-sidebar.active {
            transform: translateX(0);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: block;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            transition: background 0.3s ease;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar-menu a.active {
            background: #D4AF37;
            color: #333;
        }
        
        .menu-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #8B0000;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px 15px;
            cursor: pointer;
            z-index: 1001;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        .main-content.shifted {
            margin-left: 250px;
        }
        
        @media (max-width: 768px) {
            .admin-sidebar {
                width: 200px;
            }
            
            .main-content.shifted {
                margin-left: 200px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar Navigation -->
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-user-shield"></i> Admin Panel</h2>
        </div>
        <ul class="sidebar-menu">
            <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#"><i class="fas fa-chart-bar"></i> Analytics</a></li>
            <li><a href="#"><i class="fas fa-users"></i> Teachers</a></li>
            <li><a href="#"><i class="fas fa-user-graduate"></i> Students</a></li>
            <li><a href="#"><i class="fas fa-file-pdf"></i> Reports</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content" id="mainContent">
        <div class="container">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-university"></i> Teacher Evaluation System - Admin Dashboard</h1>
                <p>Welcome, Administrator! Manage evaluations and generate reports.</p>
            </div>

            <!-- Debug Information -->
            <div class="debug-info">
                <strong><i class="fas fa-info-circle"></i> Debug Info:</strong> 
                Total evaluations in database: <?php echo $totalEvals; ?> | 
                Recent evaluations found: <?php echo count($recentEvals); ?>
            </div>

            <!-- Quick Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalEvals; ?></div>
                    <div class="stat-label">Total Evaluations</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($teacherStats); ?></div>
                    <div class="stat-label">Teachers Evaluated</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php 
                        $programs = array_unique(array_column($teacherStats, 'program'));
                        echo count($programs);
                        ?>
                    </div>
                    <div class="stat-label">Programs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <?php 
                        $avgScore = 0;
                        if (!empty($teacherStats)) {
                            $totalAvg = 0;
                            foreach ($teacherStats as $stat) {
                                $totalAvg += $stat['avg_score'];
                            }
                            $avgScore = $totalAvg / count($teacherStats);
                        }
                        echo number_format($avgScore, 1);
                        ?>%
                    </div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-gold" onclick="generateLocalReports()">
                    <i class="fas fa-file-pdf"></i> Generate Evaluation Reports
                </button>
                <button class="btn" onclick="refreshEvaluations()">
                    <i class="fas fa-sync-alt"></i> Refresh Evaluations
                </button>
                <a href="maintenance.php" class="btn btn-warning">
                    <i class="fas fa-tools"></i> Maintenance
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Recent Evaluations -->
            <div class="card">
                <h3><i class="fas fa-list-alt"></i> Recent Student Evaluations</h3>
                <div class="action-buttons">
                    <button class="btn" onclick="refreshEvaluations()">
                        <i class="fas fa-sync-alt"></i> Refresh List
                    </button>
                </div>
                
                <?php if (empty($recentEvals)): ?>
                    <p class="loading">No evaluations found.</p>
                    <?php if ($totalEvals > 0): ?>
                        <div class="debug-info">
                            <strong><i class="fas fa-exclamation-triangle"></i> Data Mismatch:</strong> 
                            Database shows <?php echo $totalEvals; ?> evaluations but query returned 0.
                            This might indicate a query issue.
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <table class="evaluation-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Teacher</th>
                                <th>Program</th>
                                <th>Section</th>
                                <th>Date</th>
                                <th>Avg Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEvals as $eval): 
                                $totalScore = $eval['q1_1'] + $eval['q1_2'] + $eval['q1_3'] + $eval['q1_4'] + $eval['q1_5'] + $eval['q1_6'] +
                                            $eval['q2_1'] + $eval['q2_2'] + $eval['q2_3'] + $eval['q2_4'] +
                                            $eval['q3_1'] + $eval['q3_2'] + $eval['q3_3'] + $eval['q3_4'] +
                                            $eval['q4_1'] + $eval['q4_2'] + $eval['q4_3'] + $eval['q4_4'] + $eval['q4_5'] + $eval['q4_6'];
                                $avgScore = $totalScore / 20;
                                $percentage = ($avgScore / 5) * 100;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($eval['student_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($eval['teacher_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($eval['program'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($eval['section'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($eval['submitted_at'] ?? 'now')); ?></td>
                                <td>
                                    <strong><?php echo number_format($avgScore, 1); ?>/5.0</strong>
                                    <div class="score-bar">
                                        <div class="score-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Teacher Statistics -->
            <div class="card">
                <h3><i class="fas fa-chalkboard-teacher"></i> Teacher Performance Overview</h3>
                <?php if (empty($teacherStats)): ?>
                    <p class="loading">No teacher statistics available.</p>
                <?php else: ?>
                    <div class="teacher-stats">
                        <?php foreach ($teacherStats as $stat): 
                            $rating = '';
                            $ratingClass = '';
                            if ($stat['avg_score'] >= 90) {
                                $rating = 'Excellent';
                                $ratingClass = 'rating-excellent';
                            } elseif ($stat['avg_score'] >= 80) {
                                $rating = 'Very Good';
                                $ratingClass = 'rating-verygood';
                            } elseif ($stat['avg_score'] >= 70) {
                                $rating = 'Good';
                                $ratingClass = 'rating-good';
                            } elseif ($stat['avg_score'] >= 60) {
                                $rating = 'Satisfactory';
                                $ratingClass = 'rating-satisfactory';
                            } else {
                                $rating = 'Needs Improvement';
                                $ratingClass = 'rating-needsimprovement';
                            }
                        ?>
                        <div class="teacher-stat-card">
                            <h4><?php echo htmlspecialchars($stat['teacher_name']); ?></h4>
                            <p><strong>Program:</strong> <?php echo htmlspecialchars($stat['program']); ?></p>
                            <p><strong>Evaluations:</strong> <?php echo $stat['eval_count']; ?></p>
                            <p><strong>Average Score:</strong> <?php echo number_format($stat['avg_score'], 1); ?>%</p>
                            <div class="score-bar">
                                <div class="score-fill" style="width: <?php echo $stat['avg_score']; ?>%"></div>
                            </div>
                            <p><strong>Rating:</strong> 
                                <span class="rating-badge <?php echo $ratingClass; ?>"><?php echo $rating; ?></span>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Generate Local PDF Reports
    async function generateLocalReports() {
        let resultDiv = document.getElementById('reportResult');
        if (!resultDiv) {
            // Create result div if it doesn't exist
            const reportCard = document.querySelector('.card');
            const newResultDiv = document.createElement('div');
            newResultDiv.id = 'reportResult';
            reportCard.appendChild(newResultDiv);
            resultDiv = newResultDiv;
        }
        
        resultDiv.innerHTML = '<p class="loading"><i class="fas fa-spinner fa-spin"></i> Generating PDF reports... This may take a few minutes for large datasets.</p>';
        
        try {
            const response = await fetch('local_reports_generator.php');
            const data = await response.json();
            
            if (data.success) {
                let html = `<div class="result-box result-success">`;
                html += `<p><i class="fas fa-check-circle"></i> ${data.message}</p>`;
                html += `<p><strong>Teachers Processed:</strong> ${data.teachers_processed}</p>`;
                html += `<p><strong>Individual Reports:</strong> ${data.individual_reports}</p>`;
                html += `<p><strong>Summary Reports:</strong> ${data.summary_reports}</p>`;
                html += `<p><strong>Total Files:</strong> ${data.total_files}</p>`;
                
                if (data.zip_file) {
                    html += `<p><a href="${data.zip_file}" class="btn btn-gold" download><i class="fas fa-download"></i> Download All Reports (ZIP)</a></p>`;
                    html += `<p><small>Save this file to your Desktop and extract to get the folder structure.</small></p>`;
                }
                
                html += `</div>`;
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = `<div class="result-box result-error"><i class="fas fa-exclamation-triangle"></i> Error: ${data.error}</div>`;
            }
        } catch (error) {
            resultDiv.innerHTML = `<div class="result-box result-error"><i class="fas fa-exclamation-triangle"></i> Network error: ${error.message}</div>`;
        }
    }

    // Refresh evaluations
    function refreshEvaluations() {
        location.reload();
    }

    // Toggle sidebar
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('adminSidebar').classList.toggle('active');
        document.getElementById('mainContent').classList.toggle('shifted');
    });

    // Auto-refresh every 30 seconds to show new evaluations
    setInterval(() => {
        refreshEvaluations();
    }, 30000);
</script>
</body>
</html>
