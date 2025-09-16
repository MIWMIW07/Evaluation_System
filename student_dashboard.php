<?php
// student_dashboard.php - Student Dashboard
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Include database connection
require_once 'db_connection.php';

$success = '';
$error = '';

// Handle program/section update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_info'])) {
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
$current_program = $_SESSION['program'];
$current_section = $_SESSION['section'];

// Get teachers based on student's program
$teachers_result = [];
$evaluated_teachers = [];

// In student_dashboard.php, modify the teachers query
if (!empty($current_program)) {
    try {
        // Get teachers for student's program
        $teachers_stmt = query("SELECT id, name, subject FROM teachers WHERE program = ? ORDER BY name", 
                              [$current_program]);
        $teachers_result = fetch_all($teachers_stmt);
        // ... rest of the code
    } catch (Exception $e) {
        $error = "❌ Could not load teachers list: " . $e->getMessage();
        // Set empty results to prevent further errors
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 3px solid #4CAF50;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2.2em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .user-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #2196F3;
        }
        
        .user-info h3 {
            color: #1976D2;
            margin-bottom: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .info-item label {
            font-weight: 600;
            color: #666;
            font-size: 0.9em;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-item span {
            color: #2c3e50;
            font-weight: bold;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #4CAF50;
        }
        
        .stat-card h3 {
            color: #4CAF50;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .progress-card {
            border-left-color: #2196F3;
        }
        
        .progress-card h3 {
            color: #2196F3;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 10px;
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .program-section-form {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #ffc107;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.3);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }
        
        .btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #dc3545 100%);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
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
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 5px solid #4CAF50;
            transition: all 0.3s ease;
        }
        
        .teacher-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .teacher-card.evaluated {
            border-left-color: #28a745;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        }
        
        .teacher-card h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .teacher-card p {
            color: #666;
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
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
            color: #155724;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-left: 5px solid #28a745;
        }
        
        .alert-error {
            color: #721c24;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border-left: 5px solid #dc3545;
        }
        
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #dc3545;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .no-program-message {
            text-align: center;
            padding: 40px;
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-radius: 10px;
            margin-top: 30px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state h3 {
            margin-bottom: 15px;
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .teachers-grid {
                grid-template-columns: 1fr;
            }
            
            .logout-btn {
                position: relative;
                display: block;
                width: fit-content;
                margin: 10px auto 20px;
            }
            
            .header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <a href="logout.php" class="logout-btn">🚪 Logout</a>
    
    <div class="container">
        <div class="header">
            <h1>🎓 Student Dashboard</h1>
            <p>Teacher Evaluation System</p>
        </div>
        
        <div class="user-info">
            <h3>👤 Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Student ID:</label>
                    <span><?php echo htmlspecialchars($_SESSION['student_id']); ?></span>
                </div>
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
                            <!-- SHS Sections -->
                            <option value="Grade 11-A" <?php echo ($current_section === 'Grade 11-A') ? 'selected' : ''; ?>>Grade 11-A</option>
                            <option value="Grade 11-B" <?php echo ($current_section === 'Grade 11-B') ? 'selected' : ''; ?>>Grade 11-B</option>
                            <option value="Grade 12-A" <?php echo ($current_section === 'Grade 12-A') ? 'selected' : ''; ?>>Grade 12-A</option>
                            <option value="Grade 12-B" <?php echo ($current_section === 'Grade 12-B') ? 'selected' : ''; ?>>Grade 12-B</option>
                            <!-- College Sections -->
                            <option value="BSIT-1A" <?php echo ($current_section === 'BSIT-1A') ? 'selected' : ''; ?>>BSIT-1A</option>
                            <option value="BSIT-2A" <?php echo ($current_section === 'BSIT-2A') ? 'selected' : ''; ?>>BSIT-2A</option>
                            <option value="BSCS-1A" <?php echo ($current_section === 'BSCS-1A') ? 'selected' : ''; ?>>BSCS-1A</option>
                            <option value="BSCS-2A" <?php echo ($current_section === 'BSCS-2A') ? 'selected' : ''; ?>>BSCS-2A</option>
                            <option value="BSBA-1A" <?php echo ($current_section === 'BSBA-1A') ? 'selected' : ''; ?>>BSBA-1A</option>
                            <option value="BSBA-2A" <?php echo ($current_section === 'BSBA-2A') ? 'selected' : ''; ?>>BSBA-2A</option>
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
                <p style="color: #666; margin-bottom: 20px;">
                    Click "Evaluate Teacher" to start evaluating a teacher. Already evaluated teachers are marked as completed.
                </p>
                
                <?php if (!empty($teachers_result)): ?>
                    <div class="teachers-grid">
                        <?php foreach($teachers_result as $teacher): ?>
                            <?php $is_evaluated = in_array($teacher['id'], $evaluated_teachers); ?>
                            <div class="teacher-card <?php echo $is_evaluated ? 'evaluated' : ''; ?>">
                                <h4><?php echo htmlspecialchars($teacher['name']); ?></h4>
                                <p><strong>Subject:</strong> <?php echo htmlspecialchars($teacher['subject']); ?></p>
                                <p><strong>Program:</strong> <?php echo htmlspecialchars($current_program); ?></p>
                                
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
                        <p>No teachers are available for your selected program.</p>
                        <p>Please contact your administrator if this seems incorrect.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="no-program-message">
                <h3>🔧 Setup Required</h3>
                <p>Please select your program and section above to view available teachers for evaluation.</p>
            </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 40px; padding-top: 25px; border-top: 2px solid #e9ecef; color: #6c757d;">
            <p><strong>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>Teacher Evaluation System - Student Dashboard</p>
            <p style="margin-top: 10px;">
                Last updated: <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
        </div>
    </div>

    <script>
        // Dynamic section options based on program
        document.getElementById('program').addEventListener('change', function() {
            const sectionSelect = document.getElementById('section');
            const selectedProgram = this.value;
            
            // Clear current options except the first one
            while (sectionSelect.children.length > 1) {
                sectionSelect.removeChild(sectionSelect.lastChild);
            }
            
            if (selectedProgram === 'SHS') {
                const sections = [
                    'Grade 11-A', 'Grade 11-B', 'Grade 12-A', 'Grade 12-B'
                ];
                
                sections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section;
                    option.textContent = section;
                    sectionSelect.appendChild(option);
                });
            } else if (selectedProgram === 'COLLEGE') {
                const sections = [
                    'BSIT-1A', 'BSIT-1B', 'BSIT-2A', 'BSIT-2B',
                    'BSCS-1A', 'BSCS-1B', 'BSCS-2A', 'BSCS-2B',
                    'BSBA-1A', 'BSBA-1B', 'BSBA-2A', 'BSBA-2B',
                    'BSE-1A', 'BSE-2A', 'BSHM-1A', 'BSHM-2A'
                ];
                
                sections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section;
                    option.textContent = section;
                    sectionSelect.appendChild(option);
                });
            }
        });
        
        // Trigger the change event on page load to set correct sections
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('program').dispatchEvent(new Event('change'));
            
            // Animate stat cards
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
        });
        
        // Add confirmation for logout
        document.querySelector('.logout-btn').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
