<?php
// student_dashboard.php
session_start();
require_once __DIR__ . '/includes/db_connection.php';

// Check if user is student and logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'student') {
    header('Location: index.php');
    exit;
}

// Check if required student session data exists
$required_fields = ['student_id', 'username', 'first_name', 'last_name', 'section', 'program'];
foreach ($required_fields as $field) {
    if (!isset($_SESSION[$field])) {
        header('Location: index.php');
        exit;
    }
}

// Get student data from session
$student_id = $_SESSION['student_id'];
$username = $_SESSION['username'];
$first_name = $_SESSION['first_name'];
$last_name = $_SESSION['last_name'];
$section = $_SESSION['section'];
$program = $_SESSION['program'];

// Handle section/program change if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_section'])) {
    $new_section = $_POST['section'];
    $new_program = $_POST['program'];
    
    // Validate the section exists for this student
    // (In a real scenario, you'd check against available sections for this student)
    $_SESSION['section'] = $new_section;
    $_SESSION['program'] = $new_program;
    $section = $new_section;
    $program = $new_program;
    
    // Show success message
    $_SESSION['success_message'] = "Section updated successfully to {$new_section}";
    header('Location: student_dashboard.php');
    exit;
}

// Get teachers for current section
$pdo = getPDO();
$stmt = $pdo->prepare("
    SELECT teacher_name, subject 
    FROM teacher_assignments 
    WHERE section = ? AND program = ? AND is_active = true
    ORDER BY subject
");
$stmt->execute([$section, $program]);
$teachers = $stmt->fetchAll();

// Get evaluation status for each teacher
$evaluation_status = [];
foreach ($teachers as $teacher) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as evaluated 
        FROM evaluations 
        WHERE student_username = ? AND teacher_name = ? AND section = ?
    ");
    $stmt->execute([$username, $teacher['teacher_name'], $section]);
    $result = $stmt->fetch();
    $evaluation_status[$teacher['teacher_name']] = $result['evaluated'] > 0;
}

// Count completed evaluations
$completed = array_sum($evaluation_status);
$total = count($teachers);
$progress = $total > 0 ? round(($completed / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard - Teacher Evaluation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
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
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .welcome-section {
            flex: 1;
            min-width: 300px;
        }

        .welcome-section h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.2em;
        }

        .welcome-section p {
            color: #666;
            font-size: 1.1em;
        }

        .info-boxes {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .info-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border-left: 5px solid #667eea;
            flex: 1;
            min-width: 200px;
            position: relative;
        }

        .info-box .label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .info-box .value {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .change-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.3s;
        }

        .change-btn:hover {
            background: #5a6fd8;
        }

        .progress-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .progress-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 20px;
        }

        .progress-header h2 {
            color: #333;
            font-size: 1.5em;
        }

        .progress-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 15px 0;
        }

        .progress-fill {
            background: linear-gradient(90deg, #4CAF50, #45a049);
            height: 100%;
            transition: width 0.5s ease;
        }

        .progress-text {
            text-align: center;
            color: #666;
            font-weight: bold;
        }

        .teachers-section {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .section-title {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .teacher-card {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .teacher-card:hover {
            border-color: #667eea;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.1);
        }

        .teacher-card.completed {
            border-color: #4CAF50;
            background: #f8fff8;
        }

        .teacher-name {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }

        .teacher-subject {
            color: #666;
            margin-bottom: 15px;
            font-size: 1em;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .evaluate-btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .evaluate-btn:hover {
            background: #5a6fd8;
        }

        .evaluate-btn.completed {
            background: #4CAF50;
        }

        .evaluate-btn.completed:hover {
            background: #45a049;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .modal h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .cancel-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }

        .save-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-boxes {
                flex-direction: column;
            }
            
            .teachers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($first_name . ' ' . $last_name); ?>!</h1>
                <p>Teacher Evaluation System - Academic Year 2025-2026</p>
                
                <div class="info-boxes">
                    <div class="info-box">
                        <div class="label">Current Section</div>
                        <div class="value">
                            <?php echo htmlspecialchars($section); ?>
                            <button class="change-btn" onclick="openChangeModal()">
                                <i class="fas fa-edit"></i> Change
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <div class="label">Current Program</div>
                        <div class="value">
                            <?php echo htmlspecialchars($program); ?>
                            <button class="change-btn" onclick="openChangeModal()">
                                <i class="fas fa-edit"></i> Change
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <button class="logout-btn" onclick="location.href='logout.php'">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </div>

        <!-- Progress Section -->
        <div class="progress-section">
            <div class="progress-header">
                <h2>Evaluation Progress</h2>
            </div>
            
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
            </div>
            
            <div class="progress-text">
                <?php echo $completed; ?> out of <?php echo $total; ?> evaluations completed (<?php echo $progress; ?>%)
            </div>
        </div>

        <!-- Teachers Section -->
        <div class="teachers-section">
            <h2 class="section-title">Your Teachers for Evaluation</h2>
            
            <?php if (empty($teachers)): ?>
                <div style="text-align: center; padding: 40px; color: #666;">
                    <i class="fas fa-info-circle" style="font-size: 3em; margin-bottom: 15px;"></i>
                    <p>No teachers assigned to your section yet.</p>
                    <p>Please check back later or contact administration.</p>
                </div>
            <?php else: ?>
                <div class="teachers-grid">
                    <?php foreach ($teachers as $teacher): ?>
                        <?php $isCompleted = $evaluation_status[$teacher['teacher_name']]; ?>
                        <div class="teacher-card <?php echo $isCompleted ? 'completed' : ''; ?>">
                            <div class="teacher-name"><?php echo htmlspecialchars($teacher['teacher_name']); ?></div>
                            <div class="teacher-subject"><?php echo htmlspecialchars($teacher['subject']); ?></div>
                            
                            <div class="status-badge <?php echo $isCompleted ? 'status-completed' : 'status-pending'; ?>">
                                <?php echo $isCompleted ? '✓ Completed' : '⏳ Pending'; ?>
                            </div>
                            
                            <button class="evaluate-btn <?php echo $isCompleted ? 'completed' : ''; ?>" 
                                    onclick="location.href='evaluation_form.php?teacher=<?php echo urlencode($teacher['teacher_name']); ?>&subject=<?php echo urlencode($teacher['subject']); ?>'">
                                <?php echo $isCompleted ? '✓ View Evaluation' : '📝 Evaluate Now'; ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Change Section/Program Modal -->
    <div class="modal" id="changeModal">
        <div class="modal-content">
            <h3>Change Section & Program</h3>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="section">Select Section:</label>
                    <select id="section" name="section" required>
                        <option value="">-- Select Section --</option>
                        <option value="BSCS3M1" <?php echo $section === 'BSCS3M1' ? 'selected' : ''; ?>>BSCS3M1</option>
                        <option value="BSCS3N1" <?php echo $section === 'BSCS3N1' ? 'selected' : ''; ?>>BSCS3N1</option>
                        <option value="BSOA3M1" <?php echo $section === 'BSOA3M1' ? 'selected' : ''; ?>>BSOA3M1</option>
                        <option value="EDUC3M1" <?php echo $section === 'EDUC3M1' ? 'selected' : ''; ?>>EDUC3M1</option>
                        <!-- Add more sections as needed -->
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="program">Select Program:</label>
                    <select id="program" name="program" required>
                        <option value="">-- Select Program --</option>
                        <option value="COLLEGE" <?php echo $program === 'COLLEGE' ? 'selected' : ''; ?>>COLLEGE</option>
                        <option value="SHS" <?php echo $program === 'SHS' ? 'selected' : ''; ?>>SENIOR HIGH SCHOOL</option>
                    </select>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn" onclick="closeChangeModal()">Cancel</button>
                    <button type="submit" class="save-btn" name="change_section">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openChangeModal() {
            document.getElementById('changeModal').style.display = 'flex';
        }

        function closeChangeModal() {
            document.getElementById('changeModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('changeModal');
            if (event.target === modal) {
                closeChangeModal();
            }
        }

        // Show success message if exists
        <?php if (isset($_SESSION['success_message'])): ?>
            alert('<?php echo $_SESSION['success_message']; ?>');
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
