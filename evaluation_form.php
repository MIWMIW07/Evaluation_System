<?php
// evaluation_form.php - Enhanced evaluation form for logged in students
session_start();

// Include security functions for CSRF protection
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
$teacher_info = null;
$is_view_mode = false;
$existing_evaluation = null;

// Get teacher ID from URL
$teacher_id = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;

if ($teacher_id <= 0) {
    header('Location: student_dashboard.php');
    exit;
}

try {
    // Get teacher information
    $teacher_stmt = query("SELECT id, name, subject, program FROM teachers WHERE id = ?", [$teacher_id]);
    $teacher_info = fetch_assoc($teacher_stmt);
    
    if (!$teacher_info) {
        throw new Exception("Teacher not found.");
    }
    
    // Check if student has already evaluated this teacher
    $check_stmt = query("SELECT * FROM evaluations WHERE user_id = ? AND teacher_id = ?", 
                       [$_SESSION['user_id'], $teacher_id]);
    $existing_evaluation = fetch_assoc($check_stmt);
    
    if ($existing_evaluation) {
        $is_view_mode = true;
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Handle form submission (only if not in view mode)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_view_mode) {
    try {
        // Validate CSRF token first
        if (!validate_csrf_token($_POST['csrf_token'])) {
            die('CSRF token validation failed');
        }
        
        // Validate all required fields
        $ratings = [];
        
        // Section 1: Teaching Competence (6 questions)
        for ($i = 1; $i <= 6; $i++) {
            $rating = intval($_POST["q1_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 1.$i");
            }
            $ratings["q1_$i"] = $rating;
        }
        
        // Section 2: Management Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["q2_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 2.$i");
            }
            $ratings["q2_$i"] = $rating;
        }
        
        // Section 3: Guidance Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["q3_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 3.$i");
            }
            $ratings["q3_$i"] = $rating;
        }
        
        // Section 4: Personal and Social Characteristics (6 questions)
        for ($i = 1; $i <= 6; $i++) {
            $rating = intval($_POST["q4_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 4.$i");
            }
            $ratings["q4_$i"] = $rating;
        }
        
        $comments = trim($_POST['comments'] ?? '');
        
        // Insert evaluation using PostgreSQL syntax
        $insert_sql = "INSERT INTO evaluations (user_id, student_id, student_name, section, program, teacher_id, subject, 
                      q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
                      q2_1, q2_2, q2_3, q2_4,
                      q3_1, q3_2, q3_3, q3_4,
                      q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
                      comments) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $_SESSION['user_id'], $_SESSION['student_id'], $_SESSION['full_name'], 
            $_SESSION['section'], $_SESSION['program'], $teacher_id, $teacher_info['subject'],
            $ratings['q1_1'], $ratings['q1_2'], $ratings['q1_3'], $ratings['q1_4'], $ratings['q1_5'], $ratings['q1_6'],
            $ratings['q2_1'], $ratings['q2_2'], $ratings['q2_3'], $ratings['q2_4'],
            $ratings['q3_1'], $ratings['q3_2'], $ratings['q3_3'], $ratings['q3_4'],
            $ratings['q4_1'], $ratings['q4_2'], $ratings['q4_3'], $ratings['q4_4'], $ratings['q4_5'], $ratings['q4_6'],
            $comments
        ];
        
        $stmt = query($insert_sql, $params);
        
        if ($stmt) {
            $success = "✅ Evaluation submitted successfully! Thank you for your feedback.";
            // Reload to show in view mode
            $check_stmt = query("SELECT * FROM evaluations WHERE user_id = ? AND teacher_id = ?", 
                               [$_SESSION['user_id'], $teacher_id]);
            $existing_evaluation = fetch_assoc($check_stmt);
            $is_view_mode = true;
        } else {
            throw new Exception("Database error occurred while saving your evaluation.");
        }
        
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_view_mode ? 'View' : 'Submit'; ?> Evaluation - Teacher Evaluation System</title>
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
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4CAF50;
        }
        
        header h1 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 1.8em;
        }
        
        header p {
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .teacher-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #2196F3;
        }
        
        .teacher-info h3 {
            color: #1976D2;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
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
        
        .evaluation-status {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
            text-align: center;
        }
        
        .evaluation-status h3 {
            color: #155724;
            margin-bottom: 10px;
        }
        
        .rating-scale {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 25px;
            background: linear-gradient(135deg, #f9f9f9 0%, #e8f5e8 100%);
            padding: 15px;
            border-radius: 8px;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .scale-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .scale-item span {
            font-weight: bold;
            display: block;
            color: #4CAF50;
            font-size: 1.2em;
            margin-bottom: 5px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background: #fafafa;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .section-title {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .question {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #4CAF50;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .question-text {
            margin-bottom: 15px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95em;
        }
        
        .rating-options {
            display: flex;
            justify-content: space-between;
            max-width: 400px;
        }
        
        .rating-options label {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 10px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .rating-options label:hover {
            background-color: #e8f5e8;
            transform: translateY(-2px);
        }
        
        .rating-options input[type="radio"] {
            margin-top: 8px;
            transform: scale(1.2);
        }
        
        .rating-display {
            display: inline-block;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            min-width: 40px;
            text-align: center;
        }
        
        .comments-section textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .comments-section textarea:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.3);
        }
        
        .comments-display {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #6c757d;
            font-style: italic;
            color: #495057;
        }
        
        .btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #6c757d 100%);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #1976D2 0%, #2196F3 100'#');
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }
        
        .button-group {
            text-align: center;
            margin-top: 30px;
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
        
        .back-link {
            position: fixed;
            top: 20px;
            left: 20px;
            background: #2196F3;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .back-link:hover {
            background: #1976D2;
            transform: translateY(-2px);
        }
        
        .average-rating {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-left: 5px solid #ffc107;
            text-align: center;
        }
        
        .average-rating h4 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .average-score {
            font-size: 2em;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 15px;
            }
            
            .rating-scale {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .rating-options {
                flex-direction: column;
                max-width: 100%;
            }
            
            .rating-options label {
                flex-direction: row;
                justify-content: space-between;
                margin-bottom: 8px;
                padding: 8px 12px;
            }
            
            .back-link {
                position: relative;
                display: block;
                width: fit-content;
                margin: 10px auto;
            }
            
            .button-group {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
    
    <div class="container">
        <header>
            <h1>Philippine Technological Institute of Science Arts and Trade, Inc.</h1>
            <p>GMA-BRANCH (2nd Semester 2024-2025)</p>
            <h2><?php echo $is_view_mode ? '👁️ View Evaluation' : '📝 Teacher Evaluation'; ?></h2>
        </header>
        
        <?php if ($teacher_info): ?>
            <div class="teacher-info">
                <h3>👨‍🏫 Teacher Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Teacher Name:</label>
                        <span><?php echo htmlspecialchars($teacher_info['name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Subject:</label>
                        <span><?php echo htmlspecialchars($teacher_info['subject']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Program:</label>
                        <span><?php echo htmlspecialchars($teacher_info['program']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Your Section:</label>
                        <span><?php echo htmlspecialchars($_SESSION['section']); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($is_view_mode && $existing_evaluation): ?>
            <div class="evaluation-status">
                <h3>✅ Evaluation Already Submitted</h3>
                <p>You have already evaluated this teacher on <?php echo date('F j, Y \a\t g:i A', strtotime($existing_evaluation['evaluation_date'])); ?>.</p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!$is_view_mode): ?>
            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100'); border-left: 6px solid #2196F3; padding: 20px; margin-bottom: 25px; border-radius: 5px;">
                <p><strong>📋 Directions:</strong> The following items describe aspects of the teacher's characteristics inside and outside the classroom. 
                Choose the appropriate number that fits your observation. Your score will help the teacher further develop their dedication to the field of teaching.</p>
            </div>
        <?php endif; ?>
        
        <div class="rating-scale">
            <div class="scale-item"><span>5</span> Outstanding</div>
            <div class="scale-item"><span>4</span> Very Satisfactory</div>
            <div class="scale-item"><span>3</span> Good/Satisfactory</div>
            <div class="scale-item"><span>2</span> Fair</div>
            <div class="scale-item"><span>1</span> Unsatisfactory</div>
        </div>
        
        <?php if (!$is_view_mode): ?>
            <form method="POST" action="" id="evaluationForm">
                <!-- CSRF Token Field -->
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <?php endif; ?>
        
        <!-- Section 1: Teaching Competence -->
        <div class="form-section">
            <h3 class="section-title">1. Teaching Competence</h3>
            
            <?php 
            $section1_questions = [
                '1.1' => 'Analyzes and explains lessons without reading from the book in class',
                '1.2' => 'Uses audio-visual and devices to support and facilitate teaching',
                '1.3' => 'Presents ideas/concepts clearly and convincingly from related fields and incorporates subject matter into actual experience',
                '1.4' => 'Allows students to use concepts to demonstrate understanding of lessons',
                '1.5' => 'Gives fair tests and evaluations and returns test results within a reasonable time',
                '1.6' => 'Commands orderly teaching using proper speech'
            ];
            
            foreach ($section1_questions as $key => $question):
                $field_name = 'q1_' . substr($key, -1);
                $current_value = $is_view_mode ? $existing_evaluation[$field_name] : '';
            ?>
                <div class="question">
                    <p class="question-text"><?php echo $key; ?> <?php echo $question; ?></p>
                    <?php if ($is_view_mode): ?>
                        <div class="rating-display"><?php echo $current_value; ?></div>
                    <?php else: ?>
                        <div class="rating-options">
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="1" required> 1</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="5"> 5</label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Section 2: Management Skills -->
        <div class="form-section">
            <h3 class="section-title">2. Management Skills</h3>
            
            <?php 
            $section2_questions = [
                '2.1' => 'Maintains an orderly, disciplined and safe classroom to ensure proper and conducive learning',
                '2.2' => 'Follows a systematic schedule of classes and other daily activities',
                '2.3' => 'Develops in students respect and respect for teachers',
                '2.4' => 'Allows students to express their opinions and views'
            ];
            
            foreach ($section2_questions as $key => $question):
                $field_name = 'q2_' . substr($key, -1);
                $current_value = $is_view_mode ? $existing_evaluation[$field_name] : '';
            ?>
                <div class="question">
                    <p class="question-text"><?php echo $key; ?> <?php echo $question; ?></p>
                    <?php if ($is_view_mode): ?>
                        <div class="rating-display"><?php echo $current_value; ?></div>
                    <?php else: ?>
                        <div class="rating-options">
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="1" required> 1</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="5"> 5</label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Section 3: Guidance Skills -->
        <div class="form-section">
            <h3 class="section-title">3. Guidance Skills</h3>
            
            <?php 
            $section3_questions = [
                '3.1' => 'Accepts students as individuals with strengths and weaknesses',
                '3.2' => 'Shows confidence and self-organization',
                '3.3' => 'Manages class and student problems with fairness and understanding',
                '3.4' => 'Shows genuine concern for personal and other problems shown by students outside of classroom activities'
            ];
            
            foreach ($section3_questions as $key => $question):
                $field_name = 'q3_' . substr($key, -1);
                $current_value = $is_view_mode ? $existing_evaluation[$field_name] : '';
            ?>
                <div class="question">
                    <p class="question-text"><?php echo $key; ?> <?php echo $question; ?></p>
                    <?php if ($is_view_mode): ?>
                        <div class="rating-display"><?php echo $current_value; ?></div>
                    <?php else: ?>
                        <div class="rating-options">
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="1" required> 1</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="5"> 5</label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Section 4: Personal and Social Characteristics -->
        <div class="form-section">
            <h3 class="section-title">4. Personal and Social Characteristics</h3>
            
            <?php 
            $section4_questions = [
                '4.1' => 'Maintains emotional balance: not too critical or overly sensitive',
                '4.2' => 'Free from habitual movements that interfere with the teaching and learning process',
                '4.3' => 'Neat and presentable; Clean and tidy clothes',
                '4.4' => 'Does not show favoritism',
                '4.5' => 'Has a good sense of humor and shows enthusiasm in teaching',
                '4.6' => 'Has good diction, clear and proper voice modulation'
            ];
            
            foreach ($section4_questions as $key => $question):
                $field_name = 'q4_' . substr($key, -1);
                $current_value = $is_view_mode ? $existing_evaluation[$field_name] : '';
            ?>
                <div class="question">
                    <p class="question-text"><?php echo $key; ?> <?php echo $question; ?></p>
                    <?php if ($is_view_mode): ?>
                        <div class="rating-display"><?php echo $current_value; ?></div>
                    <?php else: ?>
                        <div class="rating-options">
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="1" required> 1</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                            <label><input type="radio" name="<?php echo $field_name; ?>" value="5"> 5</label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Comments Section -->
        <div class="form-section comments-section">
            <h3 class="section-title">5. Comments/Suggestions</h3>
            <?php if ($is_view_mode): ?>
                <?php if (!empty($existing_evaluation['comments'])): ?>
                    <div class="comments-display">
                        <?php echo htmlspecialchars($existing_evaluation['comments']); ?>
                    </div>
                <?php else: ?>
                    <div class="comments-display" style="color: #6c757d; font-style: italic;">
                        No comments provided.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <textarea name="comments" placeholder="Please provide any comments or suggestions about the teacher..."></textarea>
            <?php endif; ?>
        </div>
        
        <?php if ($is_view_mode && $existing_evaluation): ?>
            <?php
            // Calculate average rating
            $total_rating = $existing_evaluation['q1_1'] + $existing_evaluation['q1_2'] + $existing_evaluation['q1_3'] + 
                           $existing_evaluation['q1_4'] + $existing_evaluation['q1_5'] + $existing_evaluation['q1_6'] +
                           $existing_evaluation['q2_1'] + $existing_evaluation['q2_2'] + $existing_evaluation['q2_3'] + 
                           $existing_evaluation['q2_4'] + $existing_evaluation['q3_1'] + $existing_evaluation['q3_2'] + 
                           $existing_evaluation['q3_3'] + $existing_evaluation['q3_4'] + $existing_evaluation['q4_1'] + 
                           $existing_evaluation['q4_2'] + $existing_evaluation['q4_3'] + $existing_evaluation['q4_4'] + 
                           $existing_evaluation['q4_5'] + $existing_evaluation['q4_6'];
            $average_rating = round($total_rating / 20, 2);
            
            $performance_level = '';
            if ($average_rating >= 4.5) {
                $performance_level = 'Outstanding';
            } else if ($average_rating >= 4.0) {
                $performance_level = 'Very Satisfactory';
            } else if ($average_rating >= 3.5) {
                $performance_level = 'Good/Satisfactory';
            } else if ($average_rating >= 2.5) {
                $performance_level = 'Fair';
            } else {
                $performance_level = 'Needs Improvement';
            }
            ?>
            <div class="average-rating">
                <h4>📊 Your Overall Rating</h4>
                <div class="average-score"><?php echo $average_rating; ?>/5.0</div>
                <p><strong><?php echo $performance_level; ?></strong></p>
            </div>
        <?php endif; ?>
        
        <div class="button-group">
            <?php if ($is_view_mode): ?>
                <a href="student_dashboard.php" class="btn btn-primary">🏠 Back to Dashboard</a>
                <a href="evaluation_form.php?teacher_id=<?php echo $teacher_id; ?>&print=1" class="btn btn-secondary" target="_blank">🖨️ Print Evaluation</a>
            <?php else: ?>
                <button type="submit" class="btn" id="submitBtn">✅ Submit Evaluation</button>
                <a href="student_dashboard.php" class="btn btn-secondary">❌ Cancel</a>
            <?php endif; ?>
        </div>
        
        <?php if (!$is_view_mode): ?>
            </form>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 40px; padding-top: 25px; border-top: 2px solid #e9ecef; color: #6c757d;">
            <p><strong>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>Teacher Evaluation System</p>
            <p style="margin-top: 10px;">
                Last updated: <?php echo date('F j, Y \a\t g:i A'); ?>
            </p>
        </div>
    </div>

    <script>
        <?php if (!$is_view_mode): ?>
        // Form validation enhancement
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            const requiredRadioGroups = [
                'q1_1', 'q1_2', 'q1_3', 'q1_4', 'q1_5', 'q1_6',
                'q2_1', 'q2_2', 'q2_3', 'q2_4',
                'q3_1', 'q3_2', 'q3_3', 'q3_4',
                'q4_1', 'q4_2', 'q4_3', 'q4_4', 'q4_5', 'q4_6'
            ];
            
            let allAnswered = true;
            let firstUnanswered = null;
            
            for (let group of requiredRadioGroups) {
                const radios = document.getElementsByName(group);
                const checked = Array.from(radios).some(radio => radio.checked);
                if (!checked) {
                    allAnswered = false;
                    if (!firstUnanswered) {
                        firstUnanswered = group;
                    }
                }
            }
            
            if (!allAnswered) {
                e.preventDefault();
                alert(`Please answer all questions. Missing: Question ${firstUnanswered.replace('_', '.')}`);
                
                // Scroll to first unanswered question
                const firstRadio = document.getElementsByName(firstUnanswered)[0];
                if (firstRadio) {
                    firstRadio.closest('.question').scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    firstRadio.closest('.question').style.borderLeft = '4px solid #dc3545';
                    setTimeout(() => {
                        firstRadio.closest('.question').style.borderLeft = '4px solid #4CAF50';
                    }, 3000);
                }
                return;
            }
            
            // Show confirmation dialog
            if (!confirm('Are you sure you want to submit this evaluation? You cannot change it after submission.')) {
                e.preventDefault();
                return;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '⏳ Submitting...';
            submitBtn.disabled = true;
        });
        
        // Add visual feedback for radio selections
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Remove previous selections styling in this group
                const groupName = this.name;
                document.querySelectorAll(`input[name="${groupName}"]`).forEach(r => {
                    r.closest('label').style.backgroundColor = '';
                    r.closest('label').style.transform = '';
                });
                
                // Style the selected option
                this.closest('label').style.backgroundColor = '#e8f5e8';
                this.closest('label').style.transform = 'scale(1.05)';
                
                // Mark question as completed
                const question = this.closest('.question');
                question.style.borderLeft = '4px solid #28a745';
                question.style.backgroundColor = '#f8fff8';
            });
        });
        <?php endif; ?>
        
        // Animate elements on page load
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('.form-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                section.style.transition = 'all 0.5s ease';
                
                setTimeout(() => {
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
        
        // Add smooth scrolling to questions
        document.querySelectorAll('.question').forEach(question => {
            question.addEventListener('click', function() {
                this.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            });
        });
    </script>
</body>
</html>
