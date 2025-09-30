<?php
// student_dashboard.php - Student Dashboard
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: login.php');
    exit;
}

// Include database connection and helper functions
require_once 'includes/db_connection.php';
require_once 'includes/security.php';

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
        
        // Update user information using PDO directly
        $pdo = getPDO();
        $stmt = $pdo->prepare("UPDATE users SET program = ?, section = ? WHERE id = ?");
        $stmt->execute([$program, $section, $_SESSION['user_id']]);
        
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
$student_username = $_SESSION['username'] ?? '';

// Get available sections for dropdown using PDO
$sections_by_program = [];
try {
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT DISTINCT section, program FROM teacher_assignments WHERE is_active = true ORDER BY section");
    $all_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_sections as $section) {
        $sections_by_program[$section['program']][] = $section['section'];
    }
} catch (Exception $e) {
    $error = "Could not load section list: " . $e->getMessage();
}

// Get teacher assignments and evaluations
$teachers_result = [];
$evaluated_teachers = [];

try {
    // Get teacher assignments for current section and program
    if (!empty($current_section) && !empty($current_program)) {
        $stmt = $pdo->prepare("
            SELECT teacher_name, section, program 
            FROM teacher_assignments 
            WHERE section = ? AND program = ? AND is_active = true
            ORDER BY teacher_name
        ");
        $stmt->execute([$current_section, $current_program]);
        $teachers_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get evaluated teachers for this student
    $evaluations = getStudentEvaluations($student_username);
    $evaluated_teachers = array_column($evaluations, 'teacher_name');
    
} catch (Exception $e) {
    $error = "Could not load teacher data: " . $e->getMessage();
}

// Get evaluation statistics
$total_teachers = count($teachers_result);
$completed_evaluations = 0;

foreach ($teachers_result as $teacher) {
    if (hasEvaluatedTeacher($student_username, $teacher['teacher_name'])) {
        $completed_evaluations++;
    }
}

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
        /* All your existing CSS styles remain exactly the same */
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
            cursor: pointer;
        }
        
        .logout-btn:hover {
            background: linear-gradient(135deg, #500000 0%, #800000 100%);
            transform: translateY(-2px);
            color: #FFEC8B;
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

        /* FORM STYLES */
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
            grid-template-columns: 1fr 1fr auto;
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
            height: 46px;
        }

        .form-group select:focus {
            outline: none;
            border-color: #800000;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.2);
        }

        .form-button-container {
            display: flex;
            align-items: end;
            min-width: 140px;
        }

        .form-btn {
            width: 100%;
            padding: 12px 16px;
            font-size: 0.95em;
            height: 46px;
            white-space: nowrap;
        }
        
        /* MOBILE RESPONSIVENESS */
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

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-button-container {
                margin-top: 10px;
                min-width: auto;
            }
            
            .program-section-form {
                padding: 20px;
            }
        }

        /* LOGOUT MODAL STYLES */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: linear-gradient(135deg, #fff 0%, #F5F5DC 100%);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            width: 90%;
            max-width: 400px;
            border: 3px solid #D4AF37;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
            position: relative;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #800000 0%, #D4AF37 100%);
            border-radius: 15px 15px 0 0;
        }

        .modal-content h2 {
            margin-bottom: 15px;
            color: #800000;
            font-size: 1.5em;
        }

        .modal-content p {
            margin-bottom: 25px;
            color: #500000;
            font-weight: 500;
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @media (max-width: 480px) {
            .modal-actions {
                flex-direction: column;
                gap: 10px;
            }
            
            .modal-actions .btn {
                width: 100%;
            }
        }

        body.modal-open {
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Student Dashboard</h1>
            <p>Teacher Evaluation System</p>
        </div>
        
        <div class="user-info">
            <h3>👤 Welcome, <?php echo htmlspecialchars($_SESSION['name'] ?? 'Student'); ?>!</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Username:</label>
                    <span><?php echo htmlspecialchars($student_username); ?></span>
                </div>
                <div class="info-item">
                    <label>Current Program:</label>
                    <span><?php echo htmlspecialchars($current_program ?: 'Not set'); ?></span>
                </div>
                <div class="info-item">
                    <label>Current Section:</label>
                    <span><?php echo htmlspecialchars($current_section ?: 'Not set'); ?></span>
                </div>
            </div>
        </div>

        <!-- PROGRAM/SECTION FORM -->
        <div class="program-section-form">
            <h3>📚 Update Your Program & Section</h3>
            <p class="form-description">Please select your program and section to view available teachers for evaluation.</p>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="update_info" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="program">Program *</label>
                        <select id="program" name="program" required>
                            <option value="">Select Program</option>
                            <option value="SHS" <?php echo ($current_program === 'SHS') ? 'selected' : ''; ?>>Senior High School (SHS)</option>
                            <option value="COLLEGE" <?php echo ($current_program === 'COLLEGE') ? 'selected' : ''; ?>>College</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="section">Section *</label>
                        <select id="section" name="section" required>
                            <option value="">Select Section</option>
                            <?php if (!empty($current_program) && isset($sections_by_program[$current_program])): ?>
                                <?php foreach ($sections_by_program[$current_program] as $section): ?>
                                    <option value="<?php echo htmlspecialchars($section); ?>" 
                                        <?php echo ($current_section === $section) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($section); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="form-button-container">
                        <button type="submit" class="btn form-btn">🔄 Update Info</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- STATS SECTION -->
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
                        <?php echo $completion_percentage; ?>%
                    </div>
                </div>
            </div>
        </div>
        
        <!-- TEACHERS SECTION -->
        <div class="teachers-section">
            <h2>👨‍🏫 Teachers Available for Evaluation</h2>
            <p style="color: #800000; margin-bottom: 20px;">
                Click "Evaluate Teacher" to start evaluating a teacher. Already evaluated teachers are marked as completed.
            </p>
            
            <?php if (empty($teachers_result)): ?>
                <div class="empty-state">
                    <h3>No Teachers Available</h3>
                    <p>Please update your program and section to see available teachers for evaluation.</p>
                </div>
            <?php else: ?>
                <div class="teachers-grid">
                    <?php foreach ($teachers_result as $teacher): ?>
                        <?php 
                            $teacherName = $teacher['teacher_name'];
                            $isEvaluated = hasEvaluatedTeacher($student_username, $teacherName);
                            $cardClass = $isEvaluated ? 'teacher-card evaluated' : 'teacher-card';
                            $statusClass = $isEvaluated ? 'status-completed' : 'status-pending';
                            $statusText = $isEvaluated ? '✅ Completed' : '⏳ Pending';
                        ?>
                        <div class="<?php echo $cardClass; ?>">
                            <h4><?php echo htmlspecialchars($teacherName); ?></h4>
                            <p><strong>Section:</strong> <?php echo htmlspecialchars($teacher['section']); ?></p>
                            <p><strong>Program:</strong> <?php echo htmlspecialchars($teacher['program']); ?></p>
                            
                            <div class="evaluation-status">
                                <span class="status-badge <?php echo $statusClass; ?>">
                                    <?php echo $statusText; ?>
                                </span>
                                <?php if (!$isEvaluated): ?>
                                    <a href="evaluate_teacher.php?teacher=<?php echo urlencode($teacherName); ?>" 
                                       class="btn" style="padding: 8px 15px; font-size: 0.9em;">
                                        📝 Evaluate Teacher
                                    </a>
                                <?php else: ?>
                                    <button class="btn" style="padding: 8px 15px; font-size: 0.9em;" disabled>
                                        ✅ Evaluated
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="logout-container">
            <p><strong>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>Teacher Evaluation System - Student Dashboard</p>
            <p style="margin-top: 10px;">
                Last updated: <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
            <a href="#" class="logout-btn">🚪 Logout</a>
        </div>
    </div>

    <!-- LOGOUT CONFIRMATION MODAL -->
    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <h2>🚪 Confirm Logout</h2>
            <p>Are you sure you want to log out of the Teacher Evaluation System?</p>
            <div class="modal-actions">
                <button id="cancelLogout" class="btn btn-secondary">❌ Cancel</button>
                <a href="logout.php" class="btn">✅ Yes, Logout</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const programSelect = document.getElementById('program');
            const sectionSelect = document.getElementById('section');
            
            // Dynamic section loading
            const sectionsByProgram = <?php echo json_encode($sections_by_program); ?>;
            
            programSelect.addEventListener('change', function() {
                const selectedProgram = this.value;
                sectionSelect.innerHTML = '<option value="">Select Section</option>';
                
                if (selectedProgram && sectionsByProgram[selectedProgram]) {
                    sectionsByProgram[selectedProgram].forEach(function(section) {
                        const option = document.createElement('option');
                        option.value = section;
                        option.textContent = section;
                        sectionSelect.appendChild(option);
                    });
                }
            });

            // Logout Modal Functionality
            const logoutBtn = document.querySelector('.logout-btn');
            const modal = document.getElementById('logoutModal');
            const cancelBtn = document.getElementById('cancelLogout');
            const body = document.body;

            function openModal() {
                modal.style.display = 'flex';
                body.classList.add('modal-open');
                document.addEventListener('keydown', handleEscapeKey);
            }

            function closeModal() {
                modal.style.display = 'none';
                body.classList.remove('modal-open');
                document.removeEventListener('keydown', handleEscapeKey);
            }

            function handleEscapeKey(event) {
                if (event.key === 'Escape') closeModal();
            }

            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                openModal();
            });

            cancelBtn.addEventListener('click', closeModal);
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
        });
    </script>
</body>
</html>
