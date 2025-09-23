<?php
// student_dashboard.php - Student Dashboard
session_start();

require_once 'includes/security.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'includes/db_connection.php';

$success = '';
$error = '';

// Handle program/section update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_info'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    try {
        $program = trim($_POST['program']);
        $section = trim($_POST['section']);
        
        if (empty($program) || empty($section)) {
            throw new Exception("Program and section are required.");
        }
        
        // Update user information
        query("UPDATE users SET program = ?, section = ? WHERE id = ?", 
              [$program, $section, $_SESSION['user_id']]);
        
        // Update session variables
        $_SESSION['program'] = $program;
        $_SESSION['section'] = $section;
        
        $success = "✅ Your program and section have been updated successfully!";
        
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}

// Get student's current program and section
$current_section = $_SESSION['section'] ?? '';
$current_program = $_SESSION['program'] ?? '';

// ==================================================================
// NEW CODE #1: Fetch all sections and group them by program for the dynamic dropdown
// ==================================================================
try {
    $all_sections_stmt = query("SELECT section_code, program FROM sections WHERE is_active = true ORDER BY section_code");
    $all_sections = fetch_all($all_sections_stmt);
    
    $sections_by_program = [];
    foreach ($all_sections as $section) {
        // Group sections under their program ('COLLEGE' or 'SHS')
        $sections_by_program[$section['program']][] = $section['section_code'];
    }
} catch (Exception $e) {
    $error = "Could not load section list: " . $e->getMessage();
    $sections_by_program = [];
}
// ==================================================================

// Get teachers based on student's program
$teachers_result = [];
$evaluated_teachers = [];

// Get evaluated teachers for this student
try {
    $evaluated_stmt = query("SELECT teacher_id FROM evaluations WHERE user_id = ?", 
                            [$_SESSION['user_id']]);
    $evaluated_teachers_result = fetch_all($evaluated_stmt);
    $evaluated_teachers = array_column($evaluated_teachers_result, 'teacher_id');
} catch (Exception $e) {
    $error = "Could not load evaluation data: " . $e->getMessage();
}

// Get teachers for student's section using the new structure
if (!empty($current_section)) {
    try {
        // ==================================================================
        // MODIFIED SQL QUERY #2: Added "AND t.department = sec.program"
        // This ensures the teacher's department matches the section's program.
        // ==================================================================
        $teachers_stmt = query("
            SELECT DISTINCT
                t.id, 
                t.name, 
                t.department
            FROM teachers t
            JOIN section_teachers st ON t.id = st.teacher_id
            JOIN sections sec ON st.section_id = sec.id
            WHERE sec.section_code = ?
              AND t.department = sec.program
              AND st.is_active = true
              AND t.is_active = true
            ORDER BY t.name", 
            [$current_section, $current_program]
        );
        $teachers_result = fetch_all($teachers_stmt);
        
        if (empty($teachers_result)) {
            // Fallback: This query is already correct as it uses the program.
            $teachers_stmt = query("
                SELECT id, name, department 
                FROM teachers 
                WHERE department = ? AND is_active = true 
                ORDER BY name", 
                [$current_program]
            );
            $teachers_result = fetch_all($teachers_stmt);
        }
        
    } catch (Exception $e) {
        $error = "Could not load teachers list: " . $e->getMessage();
        $teachers_result = [];
    }
} else {
    $teachers_result = [];
}

// Get evaluation statistics
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
        font-size: 2.2em;
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
        height: 50px;
        width: auto;
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

    .btn {
        display: inline-block;
        padding: 10px 20px;
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
    .program-section-form {
        background: linear-gradient(135deg, #F5F5DC 0%, #FFEC8B 100%);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        border-left: 5px solid #D4AF37;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .program-section-form h3 {
        color: #800000;
        margin-bottom: 10px;
        font-size: 1.4em;
        border-bottom: 2px solid #D4AF37;
        padding-bottom: 10px;
    }

    .form-description {
        color: #500000;
        margin-bottom: 20px;
        line-height: 1.5;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        color: #800000;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.95em;
    }

    .form-group select {
        padding: 12px 15px;
        border: 2px solid #D4AF37;
        border-radius: 8px;
        background-color: #fff;
        color: #500000;
        font-size: 1em;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    .form-group select:focus {
        outline: none;
        border-color: #800000;
        box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.2);
    }

    .form-button-container {
        display: flex;
        align-items: center;
        height: 100%;
        padding-bottom: 5px;
    }

    .form-btn {
        width: 100%;
        padding: 12px;
        font-size: 1em;
    }
    
    @media (max-width: 768px) {
        .container {
            margin: 10px;
            padding: 20px;
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

        .form-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }
    
        .form-button-container {
            padding-bottom: 0;
            margin-top: 5px;
        }
    
        .program-section-form {
            padding: 20px;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <img src="logo.png" alt="School Logo" class="logo">
                <div>
                    <h1>Student Dashboard</h1>
                    <p>Teacher Evaluation System</p>
                </div>
            </div>
        </div>
        
        <div class="user-info">
            <h3>👤 Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Username:</label>
                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <div class="info-item">
                    <label>Current Program:</label>
                    <span><?php echo htmlspecialchars($current_program ?: 'Not Set'); ?></span>
                </div>
                <div class="info-item">
                    <label>Current Section:</label>
                    <span><?php echo htmlspecialchars($current_section ?: 'Not Set'); ?></span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="program-section-form">
            <h3>📚 Update Your Program & Section</h3>
            <p style="margin-bottom: 20px; color: #666;">Please select your program and section to view available teachers for evaluation.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="update_info" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="program">Program *</label>
                        <select id="program" name="program" required>
                            <option value="">Select Program</option>
                            <option value="SHS" <?php echo ($current_program === 'SHS') ? 'selected' : ''; ?>>
                                Senior High School (SHS)
                            </option>
                            <option value="COLLEGE" <?php echo ($current_program === 'COLLEGE') ? 'selected' : ''; ?>>
                                College
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="section">Section *</label>
                        <select id="section" name="section" required>
                            <option value="">Select Section</option>
                            <?php if (!empty($current_program) && isset($sections_by_program[$current_program])): ?>
                                <?php foreach ($sections_by_program[$current_program] as $section_code): ?>
                                    <option value="<?php echo htmlspecialchars($section_code); ?>" 
                                        <?php echo ($current_section === $section_code) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section_code); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">🔄 Update Info</button>
                </div>
            </form>
        </div>
        
        <?php if (!empty($current_program) && !empty($current_section)): ?>
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
                            <?php $is_evaluated = in_array($teacher['id'], $evaluated_teachers); ?>
                            <div class="teacher-card <?php echo $is_evaluated ? 'evaluated' : ''; ?>">
                                <h4><?php echo htmlspecialchars($teacher['name']); ?></h4>
                                <p><strong>Department:</strong> <?php echo htmlspecialchars($teacher['department']); ?></p>
                                
                                <div class="evaluation-status">
                                    <?php if ($is_evaluated): ?>
                                        <span class="status-badge status-completed">✅ Evaluated</span>
                                        <a href="evaluation_form.php?teacher_id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-secondary" style="padding: 8px 15px; font-size: 0.9em;">
                                            👁️ View Evaluation
                                        </a>
                                    <?php else: ?>
                                        <span class="status-badge status-pending">⏳ Pending</span>
                                        <a href="evaluation_form.php?teacher_id=<?php echo $teacher['id']; ?>" 
                                           class="btn" style="padding: 8px 15px; font-size: 0.9em;">
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
                        <p>No teachers are assigned to your selected section.</p>
                        <p>Please contact your administrator if this seems incorrect.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-program-message">
                <h3>🔧 Setup Required</h3>
                <p>Please select your program and section above to see your teachers.</p>
            </div>
        <?php endif; ?>
        
        <div class="logout-container">
            <p><strong>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>Teacher Evaluation System - Student Dashboard</p>
            <p style="margin-top: 10px;">
                Last updated: <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
            <a href="logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </div>

    <script>
        // Animate stat cards
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 150);
            });
            
            // Add confirmation for logout
            document.querySelector('.logout-btn').addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
