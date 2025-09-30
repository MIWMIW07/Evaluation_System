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
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 25px;
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border-left: 5px solid #667eea;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 1.1em;
        }
        
        .card {
            background: #f8f9fa;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            border-left: 5px solid #667eea;
        }
        
        .card h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn {
            background: #667eea;
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
        }
        
        .btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }
        
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        
        .btn-info { background: #17a2b8; }
        .btn-info:hover { background: #138496; }
        
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .evaluation-table th,
        .evaluation-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        
        .evaluation-table th {
            background: #34495e;
            color: white;
        }
        
        .evaluation-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .loading {
            color: #6c757d;
            font-style: italic;
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
        }
        
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin: 10px 0;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🏫 Teacher Evaluation System - Admin Dashboard</h1>
            <p>Welcome, Administrator! Manage evaluations and generate reports.</p>
        </div>

        <!-- Debug Information -->
        <div class="debug-info">
            <strong>🔍 Debug Info:</strong> 
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
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <button class="btn btn-success" onclick="generateLocalReports()">
                📊 Generate Evaluation Reports
            </button>
            <button class="btn btn-info" onclick="refreshEvaluations()">
                🔄 Refresh Evaluations
            </button>
            <a href="maintenance.php" class="btn btn-warning">🔧 Maintenance</a>
            <a href="logout.php" class="btn btn-danger">🚪 Logout</a>
        </div>

        <!-- Recent Evaluations -->
        <div class="card">
            <h3>📋 Recent Student Evaluations</h3>
            <div class="action-buttons">
                <button class="btn btn-info" onclick="refreshEvaluations()">🔄 Refresh List</button>
            </div>
            
            <?php if (empty($recentEvals)): ?>
                <p class="loading">No evaluations found.</p>
                <?php if ($totalEvals > 0): ?>
                    <div class="debug-info">
                        <strong>⚠️ Data Mismatch:</strong> 
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
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($eval['student_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($eval['teacher_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($eval['program'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($eval['section'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M j, g:i A', strtotime($eval['submitted_at'] ?? 'now')); ?></td>
                            <td><strong><?php echo number_format($avgScore, 1); ?>/5.0</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Teacher Statistics -->
        <div class="card">
            <h3>👨‍🏫 Teacher Performance Overview</h3>
            <?php if (empty($teacherStats)): ?>
                <p class="loading">No teacher statistics available.</p>
            <?php else: ?>
                <div class="teacher-stats">
                    <?php foreach ($teacherStats as $stat): ?>
                        <div class="teacher-stat-card">
                            <h4><?php echo htmlspecialchars($stat['teacher_name']); ?></h4>
                            <p><strong>Program:</strong> <?php echo htmlspecialchars($stat['program']); ?></p>
                            <p><strong>Evaluations:</strong> <?php echo $stat['eval_count']; ?></p>
                            <p><strong>Average Score:</strong> <?php echo number_format($stat['avg_score'], 1); ?>%</p>
                            <p><strong>Rating:</strong> 
                                <?php
                                $rating = '';
                                if ($stat['avg_score'] >= 90) $rating = 'Excellent';
                                elseif ($stat['avg_score'] >= 80) $rating = 'Very Good';
                                elseif ($stat['avg_score'] >= 70) $rating = 'Good';
                                elseif ($stat['avg_score'] >= 60) $rating = 'Satisfactory';
                                else $rating = 'Needs Improvement';
                                echo $rating;
                                ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
        
        resultDiv.innerHTML = '<p class="loading">🔄 Generating PDF reports... This may take a few minutes for large datasets.</p>';
        
        try {
            const response = await fetch('local_reports_generator.php');
            const data = await response.json();
            
            if (data.success) {
                let html = `<div class="result-box result-success">`;
                html += `<p>✅ ${data.message}</p>`;
                html += `<p><strong>Teachers Processed:</strong> ${data.teachers_processed}</p>`;
                html += `<p><strong>Individual Reports:</strong> ${data.individual_reports}</p>`;
                html += `<p><strong>Summary Reports:</strong> ${data.summary_reports}</p>`;
                html += `<p><strong>Total Files:</strong> ${data.total_files}</p>`;
                
                if (data.zip_file) {
                    html += `<p><a href="${data.zip_file}" class="btn btn-success" download>📥 Download All Reports (ZIP)</a></p>`;
                    html += `<p><small>Save this file to your Desktop and extract to get the folder structure.</small></p>`;
                }
                
                html += `</div>`;
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = `<div class="result-box result-error">Error: ${data.error}</div>`;
            }
        } catch (error) {
            resultDiv.innerHTML = `<div class="result-box result-error">Network error: ${error.message}</div>`;
        }
    }

    // Refresh evaluations
    function refreshEvaluations() {
        location.reload();
    }

    // Auto-refresh every 30 seconds to show new evaluations
    setInterval(() => {
        refreshEvaluations();
    }, 30000);
</script>
</body>
</html>
