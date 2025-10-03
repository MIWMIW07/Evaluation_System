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
    
    // Recent evaluations (last 10) - WITH COMMENTS
    $recentEvals = $pdo->query("
        SELECT * 
        FROM evaluations 
        ORDER BY submitted_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Teacher statistics - FIXED QUERY with proper percentage calculation
    $teacherStats = $pdo->query("
        SELECT 
            teacher_name, 
            program,
            COUNT(*) as eval_count,
            -- Correct calculation: average of 20 questions (each 1-5) converted to percentage
            (AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                 q2_1 + q2_2 + q2_3 + q2_4 + 
                 q3_1 + q3_2 + q3_3 + q3_4 + 
                 q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20) / 5) * 100 as avg_score
        FROM evaluations 
        GROUP BY teacher_name, program 
        ORDER BY teacher_name, program
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get data for the average rate line graph - FIXED QUERY
    $graphData = $pdo->query("
        SELECT 
            teacher_name,
            -- Correct calculation: average of 20 questions (each 1-5) converted to percentage
            (AVG((q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
                 q2_1 + q2_2 + q2_3 + q2_4 + 
                 q3_1 + q3_2 + q3_3 + q3_4 + 
                 q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20) / 5) * 100 as avg_score,
            COUNT(*) as eval_count
        FROM evaluations 
        GROUP BY teacher_name
        ORDER BY avg_score DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get student names for each teacher and program - UPDATED TO INCLUDE COMMENTS
    $teacherStudents = $pdo->query("
        SELECT 
            teacher_name,
            program,
            student_name,
            section,
            submitted_at,
            positive_comments,
            negative_comments,
            (q1_1 + q1_2 + q1_3 + q1_4 + q1_5 + q1_6 + 
             q2_1 + q2_2 + q2_3 + q2_4 + 
             q3_1 + q3_2 + q3_3 + q3_4 + 
             q4_1 + q4_2 + q4_3 + q4_4 + q4_5 + q4_6) / 20 as avg_score
        FROM evaluations 
        ORDER BY teacher_name, program, submitted_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Admin Dashboard Error: " . $e->getMessage());
    $totalEvals = 0;
    $recentEvals = [];
    $teacherStats = [];
    $graphData = [];
    $teacherStudents = [];
}

// Group teacher stats by teacher name for folder-style display
$groupedTeacherStats = [];
foreach ($teacherStats as $stat) {
    $teacherName = $stat['teacher_name'];
    if (!isset($groupedTeacherStats[$teacherName])) {
        $groupedTeacherStats[$teacherName] = [];
    }
    $groupedTeacherStats[$teacherName][] = $stat;
}

// Group students by teacher and program
$groupedStudents = [];
foreach ($teacherStudents as $student) {
    $teacherName = $student['teacher_name'];
    $program = $student['program'];
    
    if (!isset($groupedStudents[$teacherName])) {
        $groupedStudents[$teacherName] = [];
    }
    if (!isset($groupedStudents[$teacherName][$program])) {
        $groupedStudents[$teacherName][$program] = [];
    }
    $groupedStudents[$teacherName][$program][] = $student;
}

// Calculate overall average rating for quick stats - FIXED CALCULATION
$overallAvgRating = 0;
if (!empty($teacherStats)) {
    $totalScore = 0;
    $totalEvaluations = 0;
    foreach ($teacherStats as $stat) {
        $totalScore += $stat['avg_score'] * $stat['eval_count'];
        $totalEvaluations += $stat['eval_count'];
    }
    $overallAvgRating = $totalEvaluations > 0 ? $totalScore / $totalEvaluations : 0;
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary-maroon: #800020;
            --dark-maroon: #5a0018;
            --light-maroon: #a8324a;
            --primary-gold: #d4af37;
            --light-gold: #f0e6d2;
            --dark-gold: #b8941f;
            --neutral-light: #f8f5f0;
            --neutral-dark: #2c1810;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary-maroon) 0%, var(--dark-maroon) 100%);
            color: var(--neutral-dark);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: var(--neutral-light);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
            border: 1px solid var(--primary-gold);
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 3px solid var(--primary-gold);
            padding-bottom: 25px;
            position: relative;
        }
        
        .header h1 {
            color: var(--primary-maroon);
            margin-bottom: 10px;
            font-size: 2.5rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.1);
        }
        
        .header p {
            color: var(--dark-maroon);
            font-size: 1.2rem;
        }
        
        .header::after {
            content: "";
            position: absolute;
            bottom: -3px;
            left: 25%;
            width: 50%;
            height: 1px;
            background: linear-gradient(to right, transparent, var(--primary-gold), transparent);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-top: 5px solid var(--primary-maroon);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary-maroon), var(--primary-gold));
        }
        
        .stat-icon {
            font-size: 2.5rem;
            color: var(--primary-gold);
            margin-bottom: 15px;
        }
        
        .stat-number {
            font-size: 2.8em;
            font-weight: bold;
            color: var(--primary-maroon);
            margin-bottom: 10px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            color: var(--dark-maroon);
            font-size: 1.1em;
            font-weight: 500;
        }
        
        .card {
            background: white;
            padding: 25px;
            margin-bottom: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 5px solid var(--primary-maroon);
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        
        .card h3 {
            color: var(--primary-maroon);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            border-bottom: 1px solid var(--light-gold);
            padding-bottom: 10px;
        }
        
        .card h3 i {
            color: var(--primary-gold);
        }
        
        .btn {
            background: var(--primary-maroon);
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
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            background: var(--dark-maroon);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-success { 
            background: var(--success); 
        }
        .btn-success:hover { 
            background: #218838; 
        }
        
        .btn-warning { 
            background: var(--warning); 
            color: var(--neutral-dark); 
        }
        .btn-warning:hover { 
            background: #e0a800; 
        }
        
        .btn-danger { 
            background: var(--danger); 
        }
        .btn-danger:hover { 
            background: #c82333; 
        }
        
        .btn-info { 
            background: var(--info); 
        }
        .btn-info:hover { 
            background: #138496; 
        }
        
        .btn-gold {
            background: var(--primary-gold);
            color: var(--neutral-dark);
        }
        
        .btn-gold:hover {
            background: var(--dark-gold);
        }
        
        .evaluation-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.9em;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .evaluation-table th,
        .evaluation-table td {
            border: 1px solid #e0d6c8;
            padding: 12px;
            text-align: left;
        }
        
        .evaluation-table th {
            background: var(--primary-maroon);
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
        }
        
        .evaluation-table tr:nth-child(even) {
            background: var(--light-gold);
        }
        
        .evaluation-table tr:hover {
            background: #f5e8c8;
        }
        
        .comment-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 0.85em;
        }
        
        .comment-cell:hover {
            white-space: normal;
            overflow: visible;
            background: white;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            z-index: 10;
            position: relative;
        }
        
        .positive-comment {
            color: var(--success);
        }
        
        .negative-comment {
            color: var(--danger);
        }
        
        .loading {
            color: #6c757d;
            font-style: italic;
            padding: 20px;
            text-align: center;
            background: var(--light-gold);
            border-radius: 8px;
        }
        
        .result-box {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        
        .result-success { 
            background: #d4edda; 
            border-left-color: var(--success); 
            color: #155724;
        }
        
        .result-error { 
            background: #f8d7da; 
            border-left-color: var(--danger); 
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
            gap: 20px;
            margin-top: 15px;
        }
        
        .teacher-stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            border-top: 4px solid var(--primary-gold);
        }
        
        .teacher-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .teacher-stat-card h4 {
            color: var(--primary-maroon);
            margin-bottom: 10px;
            font-size: 1.2rem;
            border-bottom: 1px solid var(--light-gold);
            padding-bottom: 8px;
        }
        
        .teacher-stat-card p {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        
        .teacher-stat-card p strong {
            color: var(--dark-maroon);
        }
        
        .debug-info {
            background: var(--light-gold);
            border: 1px solid var(--primary-gold);
            border-radius: 8px;
            padding: 12px 15px;
            margin: 10px 0;
            font-size: 0.9em;
            color: var(--dark-maroon);
        }

        .table-wrapper {
            overflow-x: auto;
            margin-top: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .rating-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            color: white;
        }
        
        .rating-excellent {
            background: var(--success);
        }
        
        .rating-good {
            background: var(--info);
        }
        
        .rating-satisfactory {
            background: var(--warning);
            color: var(--neutral-dark);
        }
        
        .rating-needs-improvement {
            background: var(--danger);
        }
        
        .admin-welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(to right, var(--primary-maroon), var(--light-maroon));
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-maroon);
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
            margin-top: 20px;
        }
        
        .graph-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .graph-controls select, .graph-controls input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            color: var(--neutral-dark);
        }
        
        .graph-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .graph-summary-card {
            background: var(--light-gold);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid var(--primary-gold);
        }
        
        .graph-summary-card h4 {
            color: var(--dark-maroon);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
        
        .graph-summary-card p {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-maroon);
        }
        
        /* Loading Overlay Styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(128, 0, 32, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            color: white;
        }
        
        .loading-spinner {
            width: 80px;
            height: 80px;
            border: 8px solid rgba(212, 175, 55, 0.3);
            border-top: 8px solid var(--primary-gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }
        
        .loading-text {
            font-size: 1.5rem;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .loading-progress {
            width: 300px;
            height: 20px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .loading-progress-bar {
            height: 100%;
            background: var(--primary-gold);
            width: 0%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }
        
        .loading-details {
            margin-top: 15px;
            font-size: 1rem;
            text-align: center;
            max-width: 500px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn-loading {
            position: relative;
            overflow: hidden;
        }
        
        .btn-loading::after {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        /* Folder Style for Teacher Performance */
        .teacher-folder {
            background: white;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #e9ecef;
        }
        
        .folder-header {
            background: var(--light-gold);
            padding: 15px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s ease;
        }
        
        .folder-header:hover {
            background: #f5e8c8;
        }
        
        .folder-header h4 {
            color: var(--primary-maroon);
            margin: 0;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .folder-icon {
            color: var(--primary-gold);
            transition: transform 0.3s ease;
        }
        
        .folder-header.active .folder-icon {
            transform: rotate(90deg);
        }
        
        .folder-badge {
            background: var(--primary-maroon);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .folder-content {
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .folder-content.active {
            padding: 20px;
            max-height: 1000px;
        }
        
        .program-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .program-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .program-card h5 {
            color: var(--primary-maroon);
            margin-bottom: 10px;
            font-size: 1.1rem;
            border-bottom: 1px solid var(--light-gold);
            padding-bottom: 5px;
        }
        
        .program-card p {
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        
        .program-card p strong {
            color: var(--dark-maroon);
        }
        
        /* Student List Styles */
        .student-list {
            margin-top: 15px;
        }
        
        .student-list h6 {
            color: var(--primary-maroon);
            margin-bottom: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .student-list-container {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            background: var(--light-gold);
        }
        
        .student-item {
            display: flex;
            flex-direction: column;
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.85rem;
        }
        
        .student-item:last-child {
            border-bottom: none;
        }
        
        .student-name {
            font-weight: 500;
            color: var(--neutral-dark);
            margin-bottom: 5px;
        }
        
        .student-details {
            display: flex;
            gap: 10px;
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 8px;
        }
        
        .student-score {
            font-weight: bold;
            color: var(--primary-maroon);
        }
        
        .student-section {
            background: var(--primary-gold);
            color: var(--neutral-dark);
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.75rem;
        }
        
        .student-date {
            color: #6c757d;
            font-size: 0.75rem;
        }
        
        .student-comments {
            width: 100%;
            margin-top: 5px;
            font-size: 0.75rem;
            border-top: 1px dashed #e9ecef;
            padding-top: 8px;
        }
        
        .positive-comment {
            color: var(--success);
            margin-bottom: 3px;
        }
        
        .negative-comment {
            color: var(--danger);
        }
        
        .student-comments strong {
            font-weight: 600;
        }
        
        .no-students {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 10px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
            
            .admin-welcome {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .loading-progress {
                width: 250px;
            }
            
            .loading-text {
                font-size: 1.2rem;
            }
            
            .program-grid {
                grid-template-columns: 1fr;
            }
            
            .student-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .student-details {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Admin Welcome Bar -->
        <div class="admin-welcome">
            <div class="admin-info">
                <div class="admin-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div>
                    <h2>Welcome, Administrator!</h2>
                    <p>Current Time: <span id="currentTime"></span></p>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-university"></i> Teacher Evaluation System - Admin Dashboard</h1>
            <p>Comprehensive management and reporting for faculty evaluations</p>
        </div>

        <!-- Debug Information -->
        <div class="debug-info">
            <strong><i class="fas fa-info-circle"></i> System Status:</strong> 
            Total evaluations in database: <?php echo $totalEvals; ?> | 
            Recent evaluations found: <?php echo count($recentEvals); ?> |
            Teachers in graph: <?php echo count($graphData); ?>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-number"><?php echo $totalEvals; ?></div>
                <div class="stat-label">Total Evaluations</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-number"><?php echo count($groupedTeacherStats); ?></div>
                <div class="stat-label">Teachers Evaluated</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $programs = array_unique(array_column($teacherStats, 'program'));
                    echo count($programs);
                    ?>
                </div>
                <div class="stat-label">Programs</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number">
                    <?php echo number_format($overallAvgRating, 1); ?>%
                </div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="card">
            <h3><i class="fas fa-tasks"></i> Quick Actions</h3>
            <div class="action-buttons">
                <button class="btn btn-success" onclick="generateLocalReports()">
                    <i class="fas fa-chart-bar"></i> Generate Evaluation Reports
                </button>
                <button class="btn btn-info" onclick="refreshEvaluations()">
                    <i class="fas fa-sync-alt"></i> Refresh Evaluations
                </button>
                <a href="maintenance.php" class="btn btn-warning">
                    <i class="fas fa-tools"></i> System Maintenance
                </a>
                <a href="admin_download_reports.php" class="btn btn-gold">
                    <i class="fas fa-download"></i> Download Reports
                </a>
            </div>
        </div>

        <!-- Teacher Average Ratings Graph -->
        <div class="card">
            <h3><i class="fas fa-chart-line"></i> Teacher Average Ratings</h3>
            
            <div class="graph-controls">
                <select id="sortOrder" onchange="updateChart()">
                    <option value="desc">Highest to Lowest</option>
                    <option value="asc">Lowest to Highest</option>
                </select>
                <select id="minEvaluations" onchange="updateChart()">
                    <option value="0">All Teachers</option>
                    <option value="5">5+ Evaluations</option>
                    <option value="10">10+ Evaluations</option>
                    <option value="20">20+ Evaluations</option>
                </select>
                <input type="text" id="searchTeacher" placeholder="Search teacher..." onkeyup="updateChart()">
            </div>
            
            <div class="chart-container">
                <canvas id="teacherRatingsChart"></canvas>
            </div>
            
            <div class="graph-summary">
                <div class="graph-summary-card">
                    <h4>Highest Rating</h4>
                    <p id="highestRating"><?php 
                        if (!empty($graphData)) {
                            $maxRating = max(array_column($graphData, 'avg_score'));
                            echo number_format($maxRating, 1) . '%';
                        } else {
                            echo 'N/A';
                        }
                    ?></p>
                </div>
                <div class="graph-summary-card">
                    <h4>Lowest Rating</h4>
                    <p id="lowestRating"><?php 
                        if (!empty($graphData)) {
                            $minRating = min(array_column($graphData, 'avg_score'));
                            echo number_format($minRating, 1) . '%';
                        } else {
                            echo 'N/A';
                        }
                    ?></p>
                </div>
                <div class="graph-summary-card">
                    <h4>Teachers Shown</h4>
                    <p id="teachersShown"><?php echo count($graphData); ?></p>
                </div>
            </div>
        </div>

        <!-- Recent Evaluations -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Recent Student Evaluations</h3>
            <div class="action-buttons">
                <button class="btn btn-info" id="refreshListBtn" onclick="showRefreshLoading()">
                    <i class="fas fa-sync-alt"></i> Refresh List
                </button>
            </div>
            
            <?php if (empty($recentEvals)): ?>
                <p class="loading">No evaluations found in the system.</p>
                <?php if ($totalEvals > 0): ?>
                    <div class="debug-info">
                        <strong><i class="fas fa-exclamation-triangle"></i> Data Mismatch:</strong> 
                        Database shows <?php echo $totalEvals; ?> evaluations but query returned 0.
                        This might indicate a query issue.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="table-wrapper">
                    <table class="evaluation-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Teacher</th>
                                <th>Program</th>
                                <th>Section</th>
                                <th>Positive Comment</th>
                                <th>Negative Comment</th>
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
                                $scorePercentage = ($avgScore / 5) * 100;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($eval['student_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($eval['teacher_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($eval['program'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($eval['section'] ?? 'N/A'); ?></td>
                                <td class="comment-cell positive-comment" title="<?php echo htmlspecialchars($eval['positive_comments'] ?? ''); ?>">
                                    <?php 
                                    $positiveComment = $eval['positive_comments'] ?? '';
                                    echo !empty($positiveComment) ? htmlspecialchars($positiveComment) : '-';
                                    ?>
                                </td>
                                <td class="comment-cell negative-comment" title="<?php echo htmlspecialchars($eval['negative_comments'] ?? ''); ?>">
                                    <?php 
                                    $negativeComment = $eval['negative_comments'] ?? '';
                                    echo !empty($negativeComment) ? htmlspecialchars($negativeComment) : '-';
                                    ?>
                                </td>
                                <td><?php echo date('M j, g:i A', strtotime($eval['submitted_at'] ?? 'now')); ?></td>
                                <td>
                                    <strong><?php echo number_format($avgScore, 1); ?>/5.0</strong>
                                    <?php if ($scorePercentage >= 80): ?>
                                        <span class="rating-badge rating-excellent">Excellent</span>
                                    <?php elseif ($scorePercentage >= 70): ?>
                                        <span class="rating-badge rating-good">Good</span>
                                    <?php elseif ($scorePercentage >= 60): ?>
                                        <span class="rating-badge rating-satisfactory">Satisfactory</span>
                                    <?php else: ?>
                                        <span class="rating-badge rating-needs-improvement">Needs Improvement</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Teacher Performance Overview -->
        <div class="card">
            <h3><i class="fas fa-trophy"></i> Teacher Performance Overview</h3>
            <?php if (empty($groupedTeacherStats)): ?>
                <p class="loading">No teacher statistics available at this time.</p>
            <?php else: ?>
                <div class="teacher-folders">
                    <?php foreach ($groupedTeacherStats as $teacherName => $programs): 
                        $totalEvalsForTeacher = 0;
                        $totalScoreForTeacher = 0;
                        foreach ($programs as $program) {
                            $totalEvalsForTeacher += $program['eval_count'];
                            $totalScoreForTeacher += $program['avg_score'] * $program['eval_count'];
                        }
                        $overallAverage = $totalEvalsForTeacher > 0 ? $totalScoreForTeacher / $totalEvalsForTeacher : 0;
                    ?>
                        <div class="teacher-folder">
                            <div class="folder-header" onclick="toggleFolder(this)">
                                <h4>
                                    <i class="fas fa-folder folder-icon"></i>
                                    <?php echo htmlspecialchars($teacherName); ?>
                                </h4>
                                <div class="folder-badge">
                                    <?php echo count($programs); ?> Program(s) | 
                                    Overall: <?php echo number_format($overallAverage, 1); ?>%
                                </div>
                            </div>
                            <div class="folder-content">
                                <div class="program-grid">
                                    <?php foreach ($programs as $program): 
                                        $programScore = $program['avg_score'];
                                        
                                        $ratingClass = '';
                                        if ($programScore >= 80) {
                                            $ratingClass = 'rating-excellent';
                                            $ratingText = 'Excellent';
                                        } elseif ($programScore >= 70) {
                                            $ratingClass = 'rating-good';
                                            $ratingText = 'Very Good';
                                        } elseif ($programScore >= 60) {
                                            $ratingClass = 'rating-satisfactory';
                                            $ratingText = 'Good';
                                        } elseif ($programScore >= 50) {
                                            $ratingClass = 'rating-satisfactory';
                                            $ratingText = 'Satisfactory';
                                        } else {
                                            $ratingClass = 'rating-needs-improvement';
                                            $ratingText = 'Needs Improvement';
                                        }
                                    ?>
                                        <div class="program-card">
                                            <h5><?php echo htmlspecialchars($program['program']); ?></h5>
                                            <p><strong>Evaluations:</strong> <?php echo $program['eval_count']; ?></p>
                                            <p><strong>Average Score:</strong> <?php echo number_format($programScore, 1); ?>%</p>
                                            <p><strong>Rating:</strong> 
                                                <span class="rating-badge <?php echo $ratingClass; ?>"><?php echo $ratingText; ?></span>
                                            </p>
                                            
                                            <!-- Student List for this program -->
                                            <div class="student-list">
                                                <h6><i class="fas fa-users"></i> Students (<?php echo $program['eval_count']; ?>)</h6>
                                                <div class="student-list-container">
                                                    <?php 
                                                    $studentsInProgram = $groupedStudents[$teacherName][$program['program']] ?? [];
                                                    if (!empty($studentsInProgram)): 
                                                        foreach ($studentsInProgram as $student): 
                                                            $studentAvgScore = number_format($student['avg_score'], 1);
                                                    ?>
                                                            <div class="student-item">
                                                                <div class="student-name"><?php echo htmlspecialchars($student['student_name']); ?></div>
                                                                <div class="student-details">
                                                                    <span class="student-section"><?php echo htmlspecialchars($student['section']); ?></span>
                                                                    <span class="student-score"><?php echo $studentAvgScore; ?>/5.0</span>
                                                                    <span class="student-date"><?php echo date('M j', strtotime($student['submitted_at'])); ?></span>
                                                                </div>
                                                                <!-- Comments Section -->
                                                                <div class="student-comments">
                                                                    <div class="positive-comment">
                                                                        <strong>Positive:</strong> 
                                                                        <?php 
                                                                        $positiveComment = $student['positive_comments'] ?? '';
                                                                        echo !empty($positiveComment) ? htmlspecialchars(substr($positiveComment, 0, 50) . (strlen($positiveComment) > 50 ? '...' : '')) : '-';
                                                                        ?>
                                                                    </div>
                                                                    <div class="negative-comment">
                                                                        <strong>To Improve:</strong> 
                                                                        <?php 
                                                                        $negativeComment = $student['negative_comments'] ?? '';
                                                                        echo !empty($negativeComment) ? htmlspecialchars(substr($negativeComment, 0, 50) . (strlen($negativeComment) > 50 ? '...' : '')) : '-';
                                                                        ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                    <?php 
                                                        endforeach;
                                                    else: 
                                                    ?>
                                                        <div class="no-students">No students found for this program</div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay" style="display: none;">
        <div class="loading-spinner"></div>
        <div class="loading-text">Refreshing Evaluation Data</div>
        <div class="loading-progress">
            <div class="loading-progress-bar" id="loadingProgressBar"></div>
        </div>
        <div class="loading-details">
            <p>Please wait while we fetch the latest evaluation data...</p>
            <p><small>This will only take a moment</small></p>
        </div>
    </div>

    <script>
    // Prepare data for the chart
    const graphData = <?php echo json_encode($graphData); ?>;
    
    // Initialize the chart
    let teacherRatingsChart = null;
    
    function initializeChart() {
        const ctx = document.getElementById('teacherRatingsChart').getContext('2d');
        
        // Process data based on current filters
        const filteredData = filterData();
        
        // Extract labels and data
        const labels = filteredData.map(item => {
            const name = item.teacher_name.length > 20 
                ? item.teacher_name.substring(0, 20) + '...' 
                : item.teacher_name;
            return `${name} (${item.eval_count})`;
        });
        
        const data = filteredData.map(item => item.avg_score);
        
        // Create gradient for the line
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(212, 175, 55, 0.8)');
        gradient.addColorStop(1, 'rgba(212, 175, 55, 0.1)');
        
        // Create the chart
        teacherRatingsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Average Rating (%)',
                    data: data,
                    backgroundColor: gradient,
                    borderColor: 'rgba(212, 175, 55, 1)',
                    borderWidth: 3,
                    pointBackgroundColor: 'rgba(128, 0, 32, 1)',
                    pointBorderColor: 'rgba(255, 255, 255, 1)',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const teacherName = filteredData[context.dataIndex].teacher_name;
                                const evalCount = filteredData[context.dataIndex].eval_count;
                                return `${teacherName}: ${context.parsed.y.toFixed(1)}% (${evalCount} evaluations)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Average Rating (%)'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Teachers (Number of Evaluations)'
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
        
        updateSummaryStats(filteredData);
    }
    
    function filterData() {
        const sortOrder = document.getElementById('sortOrder').value;
        const minEvaluations = parseInt(document.getElementById('minEvaluations').value);
        const searchTerm = document.getElementById('searchTeacher').value.toLowerCase();
        
        let filtered = [...graphData];
        
        // Filter by minimum evaluations
        if (minEvaluations > 0) {
            filtered = filtered.filter(item => item.eval_count >= minEvaluations);
        }
        
        // Filter by search term
        if (searchTerm) {
            filtered = filtered.filter(item => 
                item.teacher_name.toLowerCase().includes(searchTerm)
            );
        }
        
        // Sort data
        filtered.sort((a, b) => {
            if (sortOrder === 'desc') {
                return b.avg_score - a.avg_score;
            } else {
                return a.avg_score - b.avg_score;
            }
        });
        
        return filtered;
    }
    
    function updateChart() {
        if (teacherRatingsChart) {
            teacherRatingsChart.destroy();
        }
        initializeChart();
    }
    
    function updateSummaryStats(data) {
        if (data.length === 0) {
            document.getElementById('highestRating').textContent = 'N/A';
            document.getElementById('lowestRating').textContent = 'N/A';
            document.getElementById('teachersShown').textContent = '0';
            return;
        }
        
        const scores = data.map(item => item.avg_score);
        const highest = Math.max(...scores);
        const lowest = Math.min(...scores);
        
        document.getElementById('highestRating').textContent = highest.toFixed(1) + '%';
        document.getElementById('lowestRating').textContent = lowest.toFixed(1) + '%';
        document.getElementById('teachersShown').textContent = data.length;
    }
    
    // Toggle folder open/close
    function toggleFolder(header) {
        header.classList.toggle('active');
        const content = header.nextElementSibling;
        content.classList.toggle('active');
    }
    
    // Show loading overlay for refresh
    function showRefreshLoading() {
        const overlay = document.getElementById('loadingOverlay');
        const progressBar = document.getElementById('loadingProgressBar');
        const refreshBtn = document.getElementById('refreshListBtn');
        
        // Show overlay
        overlay.style.display = 'flex';
        
        // Add loading class to button
        refreshBtn.classList.add('btn-loading');
        refreshBtn.disabled = true;
        
        // Animate progress bar over 3 seconds
        let progress = 0;
        const interval = setInterval(() => {
            progress += 1;
            progressBar.style.width = progress + '%';
            
            if (progress >= 100) {
                clearInterval(interval);
                // Reload the page after progress completes
                setTimeout(() => {
                    location.reload();
                }, 300);
            }
        }, 30); // 30ms * 100 = 3000ms (3 seconds)
    }
    
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
                html += `<p><strong>Reports Location:</strong> ${data.reports_location}</p>`;
                html += `<p><a href="admin_download_reports.php" class="btn btn-success"><i class="fas fa-download"></i> View & Download Reports</a></p>`;
                html += `</div>`;
                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = `<div class="result-box result-error"><i class="fas fa-exclamation-triangle"></i> Error: ${data.error}</div>`;
            }
        } catch (error) {
            resultDiv.innerHTML = `<div class="result-box result-error"><i class="fas fa-exclamation-triangle"></i> Network error: ${error.message}</div>`;
        }
    }

    // Update current time in real-time
    function updateCurrentTime() {
        const now = new Date();
        const options = { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true 
        };
        const timeString = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = timeString;
    }

    // Update time immediately and then every second
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);

    // Refresh evaluations (regular refresh without loading)
    function refreshEvaluations() {
        location.reload();
    }

    // Initialize the chart when the page loads
    document.addEventListener('DOMContentLoaded', function() {
        initializeChart();
        
        // Open the first folder by default
        const firstFolder = document.querySelector('.folder-header');
        if (firstFolder) {
            toggleFolder(firstFolder);
        }
    });

    // Auto-refresh every 30 seconds to show new evaluations
    setInterval(() => {
        refreshEvaluations();
    }, 30000);
    </script>
</body>
</html>
