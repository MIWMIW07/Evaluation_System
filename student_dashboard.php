<?php
// student_dashboard.php - Updated to work with Google Sheets data
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once 'includes/db_connection.php';

$success = '';
$error = '';

// Get database connection
try {
    $pdo = getPDO();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get student information from session (already loaded from Google Sheets during login)
$student_username = $_SESSION['username'];
$student_full_name = $_SESSION['full_name'];
$student_program = $_SESSION['program'] ?? 'Not Set';
$student_section = $_SESSION['section'] ?? 'Not Set';
$student_id = $_SESSION['student_id'];

// Handle section change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_section'])) {
    $new_section = trim($_POST['new_section']);
    
    if (!empty($new_section)) {
        // Validate that the section exists for this student's program
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT section 
                FROM teacher_assignments 
                WHERE program = ? AND section = ? AND is_active = true
                LIMIT 1
            ");
            $stmt->execute([$student_program, $new_section]);
            $valid_section = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($valid_section) {
                $_SESSION['section'] = $new_section;
                $student_section = $new_section;
                $success = "Section successfully changed to $new_section";
                
                // Clear evaluated teachers cache since section changed
                $evaluated_teachers = [];
            } else {
                $error = "Section '$new_section' is not available for your program ($student_program)";
            }
        } catch (Exception $e) {
            $error = "Error validating section: " . $e->getMessage();
        }
    } else {
        $error = "Please enter a section";
    }
}

// Get available sections for this student's program
$available_sections = [];
if ($student_program !== 'Not Set') {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT section 
            FROM teacher_assignments 
            WHERE program = ? AND is_active = true 
            ORDER BY section
        ");
        $stmt->execute([$student_program]);
        $available_sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        // Table might not exist yet or other error
        $available_sections = [];
    }
}

// Get teachers assigned to this student's section from database
$teachers_result = [];
$evaluated_teachers = [];

// Get already evaluated teachers for this student
try {
    $stmt = $pdo->prepare("
        SELECT teacher_name
        FROM evaluations 
        WHERE student_username = ? AND section = ?
    ");
    $stmt->execute([$student_username, $student_section]);
    $evaluated_teachers = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Table might not exist yet or other error
    $evaluated_teachers = [];
}

// Get available teachers from teacher_assignments table
if ($student_section !== 'Not Set' && $student_program !== 'Not Set') {
    try {
        $stmt = $pdo->prepare("
            SELECT teacher_name, program, section
            FROM teacher_assignments 
            WHERE section = ? AND program = ? AND is_active = true
            ORDER BY teacher_name
        ");
        $stmt->execute([$student_section, $student_program]);
        $teachers_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Could not load teachers: " . $e->getMessage();
        $teachers_result = [];
    }
}

// Calculate statistics
$total_teachers = count($teachers_result);
$completed_evaluations = count($evaluated_teachers);
$remaining_evaluations = $total_teachers - $completed_evaluations;
$completion_percentage = $total_teachers > 0 ? round(($completed_evaluations / $total_teachers) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Teacher Evaluation System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #800000 0%, #500000 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
            position: relative;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 3px solid #D4AF37;
        }
        
        .header h1 {
            color: #800000;
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            margin-bottom: 10px;
            background: linear-gradient(135deg, #800000, #A52A2A);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .logo {
            max-height: 65px;
            width: auto;
            height: auto;
        }
        
        .user-info {
            background: linear-gradient(135deg, #F5F5DC 0%, #FFD700 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #D4AF37;
        }
        
        .user-info h3 {
            color: #800000;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            background: #F5F5DC;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #D4AF37;
        }
        
        .info-item label {
            font-weight: 600;
            color: #800000;
            font-size: 0.9em;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-item span {
            color: #500000;
            font-weight: bold;
        }
        
        /* Change Section Styles */
        .change-section {
            background: linear-gradient(135deg, #F5F5DC 0%, #FFEC8B 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #800000;
        }
        
        .change-section h3 {
            color: #800000;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .change-section-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #800000;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #D4AF37;
            border-radius: 8px;
            background: #fff;
            color: #500000;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #800000 0%, #A52A2A 100%);
            color: #FFD700;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            text-align: center;
            font-size: 0.95em;
            white-space: nowrap;
        }

        .btn:hover {
            background: linear-gradient(135deg, #A52A2A 0%, #800000 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            color: #FFEC8B;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #D4AF37 0%, #FFD700 100%);
            color: #800000;
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #FFD700 0%, #D4AF37 100%);
            color: #500000;
        }
        
        .btn-small {
            padding: 8px 15px;
            font-size: 0.9em;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #F5F5DC 0%, #FFEC8B 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #800000;
        }
        
        .stat-card h3 {
            color: #800000;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #500000;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .progress-card {
            border-left-color: #D4AF37;
        }
        
        .progress-card h3 {
            color: #800000;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #F5F5DC;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
            border: 1px solid #D4AF37;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #800000, #A52A2A);
            border-radius: 10px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFD700;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .teachers-section {
            margin-top: 30px;
        }
        
        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .teacher-card {
            background: #F5F5DC;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #800000;
            transition: all 0.3s ease;
        }
        
        .teacher-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .teacher-card.evaluated {
            border-left-color: #D4AF37;
            background: linear-gradient(135deg, #FFEC8B 0%, #FFD700 100%);
        }
        
        .teacher-card h4 {
            color: #800000;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .teacher-card p {
            color: #500000;
            margin-bottom: 15px;
        }
        
        .evaluation-status {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #FFEC8B;
            color: #800000;
            border: 1px solid #D4AF37;
        }
        
        .status-completed {
            background: #D4AF37;
            color: #800000;
            border: 1px solid #FFD700;
        }
        
        .alert {
            padding: 20px;
            margin-bottom: 25px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .alert-success {
            color: #800000;
            background: linear-gradient(135deg, #FFEC8B 0%, #FFD700 100%);
            border-left: 5px solid #D4AF37;
        }
        
        .alert-error {
            color: #800000;
            background: linear-gradient(135deg, #FFEC8B 0%, #F5F5DC 100%);
            border-left: 5px solid #800000;
        }

        .logout-container {
            text-align: center;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 2px solid #D4AF37;
        }
        
        .logout-btn {
            display: inline-block;
            background: linear-gradient(135deg, #800000 0%, #500000 100%);
            color: #FFD700;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        
        .logout-btn:hover {
            background: linear-gradient(135deg, #500000 0%, #800000 100%);
            transform: translateY(-2px);
            color: #FFEC8B;
        }
        
        .no-program-message {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, #FFEC8B 0%, #F5F5DC 100%);
            border-radius: 10px;
            margin-top: 30px;
            border: 1px solid #D4AF37;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #500000;
            background: #F5F5DC;
            border-radius: 10px;
            border: 1px solid #D4AF37;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: #800000;
        }
        
        /* Skeleton Loading Styles */
        .skeleton-loading {
            display: block;
        }
        
        .content-loaded {
            display: none;
        }
        
        .skeleton-header {
            height: 80px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 8px;
            margin-bottom: 30px;
            animation: loading 1.5s infinite;
        }
        
        .skeleton-user-info {
            height: 120px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 8px;
            margin-bottom: 30px;
            animation: loading 1.5s infinite;
        }
        
        .skeleton-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .skeleton-stat-card {
            height: 120px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 15px;
            animation: loading 1.5s infinite;
        }
        
        .skeleton-section {
            height: 30px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 8px;
            margin-bottom: 20px;
            animation: loading 1.5s infinite;
        }
        
        .skeleton-teachers {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .skeleton-teacher-card {
            height: 180px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 12px;
            animation: loading 1.5s infinite;
        }
        
        .skeleton-footer {
            height: 80px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 8px;
            margin-top: 40px;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            
            .change-section-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .btn {
                width: 100%;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .teachers-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 1.8em;
            }

            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .skeleton-stats {
                grid-template-columns: 1fr;
            }
            
            .skeleton-teachers {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 1.5em;
            }

            .info-grid,
            .stats-container,
            .teachers-grid {
                grid-template-columns: 1fr;
            }

            .btn, .logout-btn {
                width: 100%;
                font-size: 0.9em;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Skeleton Loading Structure -->
    <div id="skeleton-loading" class="skeleton-loading">
        <div class="container">
            <div class="skeleton-header"></div>
            <div class="skeleton-user-info"></div>
            <div class="skeleton-stats">
                <div class="skeleton-stat-card"></div>
                <div class="skeleton-stat-card"></div>
                <div class="skeleton-stat-card"></div>
                <div class="skeleton-stat-card"></div>
            </div>
            <div class="skeleton-section"></div>
            <div class="skeleton-section" style="width: 70%;"></div>
            <div class="skeleton-teachers">
                <div class="skeleton-teacher-card"></div>
                <div class="skeleton-teacher-card"></div>
                <div class="skeleton-teacher-card"></div>
            </div>
            <div class="skeleton-footer"></div>
        </div>
    </div>
    
    <!-- Actual Content -->
    <div id="main-content" class="content-loaded">
        <div class="container">
            <div class="header">
                <div class="header-content">
                    <img src="logo.png" alt="School Logo" class="logo" onerror="this.style.display='none'">
                    <div>
                        <h1>Student Dashboard</h1>
                        <p>Teacher Evaluation System</p>
                    </div>
                </div>
            </div>
            
            <div class="user-info">
                <h3>👤 Welcome, <?php echo htmlspecialchars($student_full_name); ?>!</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Username:</label>
                        <span><?php echo htmlspecialchars($student_username); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Student ID:</label>
                        <span><?php echo htmlspecialchars($student_id); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Current Program:</label>
                        <span><?php echo htmlspecialchars($student_program); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Current Section:</label>
                        <span><?php echo htmlspecialchars($student_section); ?></span>
                    </div>
                </div>
            </div>

            <!-- Change Section Feature -->
            <div class="change-section">
                <h3>🔄 Change Section</h3>
                <p style="color: #800000; margin-bottom: 15px;">
                    Select a different section for back subject to evaluate the teachers assigned to that subject.
                </p>
                
                <form method="post" action="" class="change-section-form">
                    <div class="form-group">
                        <label for="new_section">Select New Section:</label>
                        <select name="new_section" id="new_section" class="form-control" required>
                            <option value="">-- Select Section --</option>
                            <?php foreach($available_sections as $section): ?>
                                <option value="<?php echo htmlspecialchars($section); ?>" 
                                    <?php echo $section === $student_section ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($section); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="change_section" class="btn">
                        🔄 Change Section
                    </button>
                </form>
                
                <?php if (!empty($available_sections)): ?>
                    <div style="margin-top: 15px; font-size: 0.9em; color: #500000;">
                        
                    </div>
                <?php else: ?>
                    <div style="margin-top: 15px; color: #800000;">
                        No sections available for your program. Please contact administrator.
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($student_program !== 'Not Set' && $student_section !== 'Not Set'): ?>
                <div class="stats-container">
                    <div class="stat-card">
                        <h3><?php echo $total_teachers; ?></h3>
                        <p>Total Teachers</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $completed_evaluations; ?></h3>
                        <p>Completed Evaluations</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo $remaining_evaluations; ?></h3>
                        <p>Remaining Evaluations</p>
                    </div>
                    <div class="stat-card progress-card">
                        <h3><?php echo $completion_percentage; ?>%</h3>
                        <p>Completion Progress</p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $completion_percentage; ?>%;">
                                <?php if ($completion_percentage > 20): ?>
                                    <?php echo $completion_percentage; ?>%
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="teachers-section">
                    <h2>👨‍🏫 Teachers Available for Evaluation</h2>
                    <p style="color: #800000; margin-bottom: 20px;">
                        Click "Evaluate Teacher" to start evaluating a teacher. Already evaluated teachers are marked as completed.
                    </p>
                    
                    <?php if (!empty($teachers_result)): ?>
                        <div class="teachers-grid">
                            <?php foreach($teachers_result as $teacher): ?>
                                <?php 
                                    $is_evaluated = in_array($teacher['teacher_name'], $evaluated_teachers); 
                                ?>
                                <div class="teacher-card <?php echo $is_evaluated ? 'evaluated' : ''; ?>">
                                    <h4><?php echo htmlspecialchars($teacher['teacher_name']); ?></h4>
                                    <p><strong>Section:</strong> <?php echo htmlspecialchars($teacher['section']); ?></p>
                                    <p><strong>Program:</strong> <?php echo htmlspecialchars($teacher['program']); ?></p>
                                    
                                    <div class="evaluation-status">
                                        <?php if ($is_evaluated): ?>
                                            <span class="status-badge status-completed">✅ Evaluated</span>
                                            <a href="evaluation_form.php?teacher=<?php echo urlencode($teacher['teacher_name']); ?>" 
                                               class="btn btn-secondary btn-small">
                                                👁️ View Evaluation
                                            </a>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">⏳ Pending</span>
                                            <a href="evaluation_form.php?teacher=<?php echo urlencode($teacher['teacher_name']); ?>" 
                                               class="btn btn-small">
                                                📝 Evaluate Teacher
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <h3>📭 No Teachers Found</h3>
                            <p>No teachers are assigned to your section (<?php echo htmlspecialchars($student_section); ?>).</p>
                            <p>Please contact your administrator if this seems incorrect.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-program-message">
                    <h3>⚠️ Incomplete Student Information</h3>
                    <p>Your program or section information is missing from the system.</p>
                    <p>Please contact your administrator to update your information in Google Sheets.</p>
                    <p><strong>Current Info:</strong></p>
                    <p>Program: <?php echo htmlspecialchars($student_program); ?></p>
                    <p>Section: <?php echo htmlspecialchars($student_section); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="logout-container">
                <p><strong>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
                <p>Teacher Evaluation System - Student Dashboard</p>
                <p style="margin-top: 10px;">
                    Last updated: <?php echo date('F j, Y \a\t g:i A'); ?>
                    Developer: ISRAEL GABRIEL, TOQUE CHRISTOPHER GLEN, MERVIN LEO MICOSA
                </p>
                <a href="logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show skeleton loading for 1 seconds
            setTimeout(function() {
                document.getElementById('skeleton-loading').style.display = 'none';
                document.getElementById('main-content').style.display = 'block';
            }, 1000);

            // Add confirmation for logout
            document.querySelector('.logout-btn').addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });

            // Add confirmation for section change
            const sectionForm = document.querySelector('.change-section-form');
            if (sectionForm) {
                sectionForm.addEventListener('submit', function(e) {
                    const newSection = document.getElementById('new_section').value;
                    const currentSection = '<?php echo $student_section; ?>';
                    
                    if (newSection === currentSection) {
                        e.preventDefault();
                        alert('You are already in this section.');
                        return false;
                    }
                    
                    if (!confirm(`Are you sure you want to change to section ${newSection}? This will update the teachers list.`)) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Add loading state for evaluation buttons
            const evalButtons = document.querySelectorAll('.btn');
            evalButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const originalText = this.textContent;
                    this.textContent = 'Loading...';
                    this.style.pointerEvents = 'none';
                    
                    // Reset after 1 seconds if page doesn't navigate
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.pointerEvents = 'auto';
                    }, 1000);
                });
            });
        });
    </script>
</body>
</html>
