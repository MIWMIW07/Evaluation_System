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
        
        $positive_comments = trim($_POST['positive_comments'] ?? '');
        $negative_comments = trim($_POST['negative_comments'] ?? '');
        $comments = "Positive: " . $positive_comments . "\nNegative: " . $negative_comments;
        
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

// Parse existing comments for view mode
$positive_comments_view = "";
$negative_comments_view = "";
if ($is_view_mode && !empty($existing_evaluation['comments'])) {
    $comments = $existing_evaluation['comments'];
    if (preg_match('/Positive: (.*?)(?:\nNegative:|$)/s', $comments, $positive_match)) {
        $positive_comments_view = trim($positive_match[1]);
    }
    if (preg_match('/Negative: (.*)$/s', $comments, $negative_match)) {
        $negative_comments_view = trim($negative_match[1]);
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
    background-color: #f5f7f9;
    color: #333;
    line-height: 1.6;
    padding: 20px;
    padding-top: 70px; /* Prevents overlap with fixed progress bar */
}

.container {
    max-width: 1100px;
    margin: 0 auto;
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    position: relative;
}

header {
    text-align: center;
    margin-bottom: 30px;
    border-bottom: 2px solid #2c3e50;
    padding-bottom: 20px;
}

h1 {
    color: #2c3e50;
    margin-bottom: 10px;
}

h2 {
    color: #3498db;
    margin: 25px 0 15px;
    padding-bottom: 5px;
    border-bottom: 1px solid #eaeaea;
}

h3 {
    color: #2c3e50;
    margin: 20px 0 10px;
}

.language-toggle {
    display: flex;
    justify-content: center;
    margin: 15px 0;
}

.language-toggle button {
    padding: 8px 15px;
    margin: 0 5px;
    background-color: #e7e7e7;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s;
}

.language-toggle button.active {
    background-color: #3498db;
    color: white;
}

.institution-name {
    font-weight: bold;
    font-size: 1.4em;
    color: #2c3e50;
}

.address {
    font-style: italic;
    color: #7f8c8d;
    margin-bottom: 15px;
}

.instructions {
    background-color: #f8f9fa;
    padding: 15px;
    border-left: 4px solid #3498db;
    margin-bottom: 25px;
    border-radius: 0 5px 5px 0;
}

/* Rating Scale */
.rating-scale {
    display: flex;
    justify-content: space-between;
    margin: 20px 0;
    padding: 15px;
    background: linear-gradient(to right, #e74c3c, #f1c40f, #2ecc71);
    border-radius: 5px;
    color: white;
    font-weight: bold;
    text-align: center;
}

.rating-item {
    text-align: center;
    flex: 1;
    font-size: 16px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 25px;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eaeaea;
}

th {
    background-color: #f2f6fc;
    font-weight: 600;
}

tr:hover {
    background-color: #f9f9f9;
}

.rating-options {
    display: flex;
    justify-content: space-between;
    width: 100%;
}

.rating-options label {
    display: inline-block;
    text-align: center;
    width: 18%;
    cursor: pointer;
}

input[type="radio"] {
    transform: scale(1.2);
    margin: 8px 0;
}

.comments-section {
    margin-top: 30px;
}

textarea {
    width: 100%;
    height: 120px;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    resize: vertical;
    font-size: 16px;
    margin-bottom: 15px;
}

.submit-btn {
    display: block;
    width: 200px;
    margin: 30px auto 10px;
    padding: 12px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 5px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
}

.submit-btn:hover {
    background-color: #2980b9;
}

.submit-btn:disabled {
    background-color: #95a5a6;
    cursor: not-allowed;
}

footer {
    text-align: center;
    margin-top: 20px;
    color: #7f8c8d;
    font-size: 0.9em;
}

.tagalog {
    display: none;
}

/* Incomplete question styling */
.incomplete {
    background-color: #ffebee;
    border-left: 4px solid #e74c3c;
}

.incomplete-notice {
    color: #e74c3c;
    font-size: 14px;
    margin-top: 5px;
    display: none;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.7);
    z-index: 1000;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background-color: white;
    padding: 30px;
    border-radius: 10px;
    max-width: 500px;
    text-align: center;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
}

.modal h2 {
    color: #e74c3c;
    margin-bottom: 15px;
}

.modal p {
    margin-bottom: 20px;
    line-height: 1.5;
}

.modal-btn {
    padding: 10px 20px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
}

/* Progress Bar - Fixed at Top */
.progress-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    background-color: #fff;
    padding: 10px 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    z-index: 2000;
}

.progress-bar {
    height: 8px;
    background-color: #f0f0f0;
    border-radius: 4px;
    margin: 10px 0;
    overflow: hidden;
}

.progress {
    height: 100%;
    background-color: #2ecc71;
    width: 0%;
    transition: width 0.5s ease;
}

.progress-text {
    text-align: center;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}

/* View mode styling */
.view-mode-rating {
    display: inline-block;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
    min-width: 40px;
    text-align: center;
}

.view-comments {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid #6c757d;
    font-style: italic;
    color: #495057;
    margin-bottom: 15px;
}

/* Alert messages */
.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    font-weight: 500;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-left: 4px solid #28a745;
}

.alert-error {
    color: #721c24;
    background-color: #f8d7da;
    border-left: 4px solid #dc3545;
}

/* Back link */
.back-link {
    display: inline-block;
    background: #2196F3;
    color: white;
    padding: 10px 15px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: bold;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.back-link:hover {
    background: #1976D2;
    transform: translateY(-2px);
}

/* Teacher info */
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

/* Evaluation status */
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

/* Average rating */
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

/* ---------- RESPONSIVENESS ---------- */

/* Tablet adjustments */
@media (max-width: 1024px) {
    .rating-scale {
        padding: 12px;
    }
    .rating-item {
        font-size: 15px;
    }
}

/* Large phones */
@media (max-width: 768px) {
    .rating-options {
        flex-direction: column;
    }

    .rating-options label {
        width: 100%;
        text-align: left;
        margin-bottom: 5px;
    }

    th, td {
        padding: 8px 5px;
        font-size: 14px;
    }

    .modal-content {
        width: 90%;
        padding: 20px;
    }

    .rating-scale {
        flex-direction: column;
        align-items: center;
        padding: 10px;
    }
    .rating-item {
        width: 100%;
        font-size: 14px;
        margin: 5px 0;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

/* Small phones */
@media (max-width: 480px) {
    .rating-scale {
        padding: 8px;
    }
    .rating-item {
        font-size: 13px;
    }
    
    .container {
        padding: 15px;
    }
}
    </style>
</head>
<body>
    <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
    
    <div class="container">
        <header>
            <div class="institution-name">PHILTECH GMA</div>
            <div class="institution-name">PHILIPPINE TECHNOLOGICAL INSTITUTE OF SCIENCE ARTS AND TRADE CENTRAL INC.</div>
            <div class="address">2nd Floor CRDM BLDG. Governor's Drive Brgy G. Maderan GMA, Cavite</div>
            
            <h1>TEACHER'S PERFORMANCE EVALUATION BY THE STUDENTS</h1>
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
            <div class="progress-container">
                <div class="progress-text" id="progress-text">Completion: 0%</div>
                <div class="progress-bar">
                    <div class="progress" id="progress-bar"></div>
                </div>
            </div>
            
            <div class="instructions">
                <p>Instructions: The following items describe the aspects of teacher's behavior in and out the classroom. Please choose the number that indicates the degree to which you feel each item is descriptive of him/her. Your rating will be the reference that may lead to the improvement of instructor, so kindly rate each item as thoughtfully and carefully as possible. This will be kept confidentially.</p>
                <p class="incomplete-notice" id="incomplete-notice">Please answer all questions before submitting. Incomplete sections are highlighted in red.</p>
            </div>
        <?php endif; ?>
        
        <div class="rating-scale">
            <div class="rating-item">5 - Outstanding</div>
            <div class="rating-item">4 - Very Satisfactory</div>
            <div class="rating-item">3 - Good/Satisfactory</div>
            <div class="rating-item">2 - Fair</div>
            <div class="rating-item">1 - Unsatisfactory</div>
        </div>
        
        <?php if (!$is_view_mode): ?>
            <form method="POST" action="" id="evaluationForm">
                <!-- CSRF Token Field -->
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <?php endif; ?>
        
        <!-- Section 1: Teaching Competence -->
        <h2>1. Teaching Competence</h2>
        <table>
            <thead>
                <tr>
                    <th width="70%">Statement</th>
                    <th width="30%">Rating</th>
                </tr>
            </thead>
            <tbody>
                <tr id="question-1-1" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>1.1 Analyses and elaborates lesson without reading textbook in class.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q1_1']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q1_1" value="5" class="required-rating"> 5</label>
                                <label><input type="radio" name="q1_1" value="4"> 4</label>
                                <label><input type="radio" name="q1_1" value="3"> 3</label>
                                <label><input type="radio" name="q1_1" value="2"> 2</label>
                                <label><input type="radio" name="q1_1" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-1-2" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>1.2 Uses audio visual and devices to support and facilitate instructions.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q1_2']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q1_2" value="5"> 5</label>
                                <label><input type="radio" name="q1_2" value="4"> 4</label>
                                <label><input type="radio" name="q1_2" value="3"> 3</label>
                                <label><input type="radio" name="q1_2" value="2"> 2</label>
                                <label><input type="radio" name="q1_2" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-1-3" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>1.3 Present ideas/ concepts clearly and convincingly from related fields and integrate subject matter with actual experience.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q1_3']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q1_3" value="5"> 5</label>
                                <label><input type="radio" name="q1_3" value="4"> 4</label>
                                <label><input type="radio" name="q1_3" value="3"> 3</label>
                                <label><input type="radio" name="q1_3" value="2"> 2</label>
                                <label><input type="radio" name="q1_3" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-1-4" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>1.4 Make the students apply concepts to demonstrate understanding of the lesson.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q1_4']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q1_4" value="5"> 5</label>
                                <label><input type="radio" name="q1_4" value="4"> 4</label>
                                <label><input type="radio" name="q1_4" value="3"> 3</label>
                                <label><input type="radio" name="q1_4" value="2"> 2</label>
                                <label><input type="radio" name="q1_4" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-1-5" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>1.5 Gives fair test and examination and return test results within the reasonable period.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q1_5']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q1_5" value="5"> 5</label>
                                <label><input type="radio" name="q1_5" value="4"> 4</label>
                                <label><input type="radio" name="q1_5" value="3"> 3</label>
                                <label><input type="radio" name="q1_5" value="2"> 2</label>
                                <label><input type="radio" name="q1_5" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-1-6" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>1.6 Shows good command of the language of instruction.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q1_6']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q1_6" value="5"> 5</label>
                                <label><input type="radio" name="q1_6" value="4"> 4</label>
                                <label><input type="radio" name="q1_6" value="3"> 3</label>
                                <label><input type="radio" name="q1_6" value="2"> 2</label>
                                <label><input type="radio" name="q1_6" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <!-- Section 2: Management Skills -->
        <h2>2. Management Skills</h2>
        <table>
            <thead>
                <tr>
                    <th width="70%">Statement</th>
                    <th width="30%">Rating</th>
                </tr>
            </thead>
            <tbody>
                <tr id="question-2-1" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>2.1 Maintains responsive, disciplined and safe classroom atmosphere that is conducive to learning.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q2_1']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q2_1" value="5"> 5</label>
                                <label><input type="radio" name="q2_1" value="4"> 4</label>
                                <label><input type="radio" name="q2_1" value="3"> 3</label>
                                <label><input type="radio" name="q2_1" value="2"> 2</label>
                                <label><input type="radio" name="q2_1" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-2-2" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>2.2 Follow the schematic way.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q2_2']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q2_2" value="5"> 5</label>
                                <label><input type="radio" name="q2_2" value="4"> 4</label>
                                <label><input type="radio" name="q2_2" value="3"> 3</label>
                                <label><input type="radio" name="q2_2" value="2"> 2</label>
                                <label><input type="radio" name="q2_2" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-2-3" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>2.3 Stimulate students respect regard for the teacher.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q2_3']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q2_3" value="5"> 5</label>
                                <label><input type="radio" name="q2_3" value="4"> 4</label>
                                <label><input type="radio" name="q2_3" value="3"> 3</label>
                                <label><input type="radio" name="q2_3" value="2"> 2</label>
                                <label><input type="radio" name="q2_3" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-2-4" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>2.4 Allow the students to express their opinions and views.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q2_4']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q2_4" value="5"> 5</label>
                                <label><input type="radio" name="q2_4" value="4"> 4</label>
                                <label><input type="radio" name="q2_4" value="3"> 3</label>
                                <label><input type="radio" name="q2_4" value="2"> 2</label>
                                <label><input type="radio" name="q2_4" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <!-- Section 3: Guidance Skills -->
        <h2>3. Guidance Skills</h2>
        <table>
            <thead>
                <tr>
                    <th width="70%">Statement</th>
                    <th width="30%">Rating</th>
                </tr>
            </thead>
            <tbody>
                <tr id="question-3-1" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>3.1 Accepts students as they are by recognizing their strength and weakness as individuals.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q3_1']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q3_1" value="5"> 5</label>
                                <label><input type="radio" name="q3_1" value="4"> 4</label>
                                <label><input type="radio" name="q3_1" value="3"> 3</label>
                                <label><input type="radio" name="q3_1" value="2"> 2</label>
                                <label><input type="radio" name="q3_1" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-3-2" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>3.2 Inspires students to be self-reliant and self- disciplined.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q3_2']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q3_2" value="5"> 5</label>
                                <label><input type="radio" name="q3_2" value="4"> 4</label>
                                <label><input type="radio" name="q3_2" value="3"> 3</label>
                                <label><input type="radio" name="q3_2" value="2"> 2</label>
                                <label><input type="radio" name="q3_2" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-3-3" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>3.3 Handles class and student's problem with fairness and understanding.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q3_3']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q3_3" value="5"> 5</label>
                                <label><input type="radio" name="q3_3" value="4"> 4</label>
                                <label><input type="radio" name="q3_3" value="3"> 3</label>
                                <label><input type="radio" name="q3_3" value="2"> 2</label>
                                <label><input type="radio" name="q3_3" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-3-4" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>3.4 Shows genuine concern for the personal and other problems presented by the students outside classroom activities.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q3_4']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q3_4" value="5"> 5</label>
                                <label><input type="radio" name="q3_4" value="4"> 4</label>
                                <label><input type="radio" name="q3_4" value="3"> 3</label>
                                <label><input type="radio" name="q3_4" value="2"> 2</label>
                                <label><input type="radio" name="q3_4" value="1"> 极</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <!-- Section 4: Personal and Social Qualities/Skills -->
        <h2>4. Personal and Social Qualities/Skills</h2>
        <table>
            <thead>
                <tr>
                    <th width="70%">Statement</th>
                    <th width="30%">Rating</th>
                </tr>
            </thead>
            <tbody>
                <tr id="question-4-1" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>4.1 Maintains emotional balance; neither over critical nor over-sensitive.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q4_1']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q4_1" value="5"> 5</label>
                                <label><input type="radio" name="q4_1" value="4"> 4</label>
                                <label><input type="radio" name="q4_1" value="3"> 3</label>
                                <label><input type="radio" name="q4_1" value="2"> 2</label>
                                <label><input type="radio" name="q4_1" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-4-2" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>4.2 Is free from mannerisms that distract the teaching and learning process.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q4_2']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q4_2" value="5"> 5</label>
                                <label><input type="radio" name="q4_2" value="4"> 4</label>
                                <label><input type="radio" name="q4_2" value="3"> 3</label>
                                <label><input type="radio" name="q4_2" value="2"> 极</label>
                                <label><input type="radio" name="q4_2" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-4-3" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>4.3 Is well groomed; clothes are clean and neat (Uses appropriate clothes that are becoming of a teacher).</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q4_3']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q4_3" value="5"> 5</label>
                                <label><input type="radio" name="q4_3" value="4"> 4</label>
                                <label><input type="radio" name="q4_3" value="3"> 3</label>
                                <label><input type="radio" name="q4_3" value="2"> 2</label>
                                <label><input type="radio" name="q4_3"极="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-4-4" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>4.4 Shows no favoritism.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q4_4']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><极 type="radio" name="q4_4" value="5"> 5</label>
                                <label><input type="radio" name="q4_4" value="4"> 4</label>
                                <label><input type="radio" name="q4_4" value="3"> 3</label>
                                <label><input type="radio" name="q4_4" value="2"> 2</label>
                                <label><input type="radio" name="q4_4" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-4-5" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>4.5 Has good sense of humor and shows enthusiasm in teaching.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class="view-mode-rating"><?php echo $existing_evaluation['q4_5']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q4_5" value="5"> 5</label>
                                <label><input type="radio" name="q4_5" value="4"> 4</label>
                                <label><input type="radio" name="q4_5" value="3"> 3</label>
                                <label><input type="radio" name="q4_5" value="2"> 2</label>
                                <label><input type="radio" name="q4_5" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="question-4-6" class="<?php echo $is_view_mode ? '' : 'required-rating'; ?>">
                    <td>4.6 Has good diction, clear and modulated voice.</td>
                    <td>
                        <?php if ($is_view_mode): ?>
                            <div class极="view-mode-rating"><?php echo $existing_evaluation['q4_6']; ?></div>
                        <?php else: ?>
                            <div class="rating-options">
                                <label><input type="radio" name="q4_6" value="5"> 5</极>
                                <label><input type="radio" name="q4_6" value="4"> 4</label>
                                <label><input type="radio" name="q4_极" value="3"> 3</label>
                                <label><input type="radio" name="q4_6" value="2"> 2</label>
                                <label><input type="radio" name="q4_6" value="1"> 1</label>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <!-- Comments Section -->
        <div class="comments-section">
            <h2>5. Comments</h2>
            <?php if ($is_view_mode): ?>
                <?php if (!empty($positive_comments_view)): ?>
                    <div class="view-comments">
                        <strong>Positive:</strong><br>
                        <?php echo htmlspecialchars($positive_comments_view); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($negative_comments_view)): ?>
                    <div class="view-comments">
                        <strong>Negative:</strong><br>
                        <?php echo htmlspecialchars($negative_comments_view); ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($positive_comments_view) && empty($negative_comments_view)): ?>
                    <div class="view-comments" style="color: #6c757d; font-style: italic;">
                        No comments provided.
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p>Positive comment about the instructor:</p>
                <textarea name="positive_comments" id="positive-comment" class="required-comment" placeholder="Positive comment about the instructor..."></textarea>
                
                <p>Negative comment about the instructor:</p>
                <textarea name="negative_comments" id="negative-comment" class="required-comment" placeholder="Negative comment about the instructor..."></textarea>
            <?php endif; ?>
        </div>
        
        <?php if ($is_view_mode && $existing_evaluation): ?>
            <?php
            // Calculate average rating
            $total_rating = $existing_evaluation['q1_1'] + $existing_evaluation['q1_2'] + $existing_evaluation['q1_3'] + 
                           $existing_evaluation['极1_4'] + $existing_evaluation['q1_5'] + $existing_evaluation['q1_6'] +
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
                <a href="student_dashboard.php" class="submit-btn" style="text-decoration: none; text-align: center;">🏠 Back to Dashboard</a>
                <a href="evaluation_form.php?teacher_id=<?php echo $teacher_id; ?>&print=1" class="submit-btn" style="text-decoration: none; text-align: center; background-color: #6c757d;" target="_blank">🖨️ Print Evaluation</a>
            <?php else: ?>
                <button type="submit" class="submit-btn" id="submit-btn" disabled>Submit Evaluation</button>
            <?php endif; ?>
        </div>
        
        <?php if (!$is_view_mode): ?>
            </form>
        <?php endif; ?>
        
        <footer>
            <p>This evaluation will be kept confidential.</p>
            <p>© PHILTECH GMA</p>
        </footer>
    </div>

    <!-- Modal for incomplete form -->
    <div class="modal" id="incomplete-modal">
        <div class="modal-content">
            <h2>Incomplete Evaluation</h2>
            <p id="modal-message">Please answer all questions before submitting. The incomplete sections are highlighted in red.</p>
            <button class="modal-btn" id="modal-ok-btn">OK</button>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('evaluation-form');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const submitBtn = document.getElementById('submit-btn');
            const incompleteNotice = document.getElementById('incomplete-notice');
            const modal = document.getElementById('incomplete-modal');
            const modalMessage = document.getElementById('modal-message');
            const modalOkBtn = document.getElementById('modal-ok-btn');

            // Update progress bar
            function updateProgress() {
                const radioInputs = document.querySelectorAll('input[type="radio"]');
                const ratingNames = Array.from(radioInputs)
                    .map(input => input.name)
                    .filter((v, i, a) => a.indexOf(v) === i);

                let totalRatings = ratingNames.length;
                let answeredRatings = 0;
                ratingNames.forEach(name => {
                    if (document.querySelector(`input[name="${name}"]:checked`)) {
                        answeredRatings++;
                    }
                });

                // Comments: check both comment fields
                const totalComments = 2;
                let answeredComments = 0;
                if (document.getElementById('positive-comment') && document.getElementById('positive-comment').value.trim()) {
                    answeredComments++;
                }
                if (document.getElementById('negative-comment') && document.getElementById('negative-comment').value.trim()) {
                    answeredComments++;
                }

                const progress = ((answeredRatings / totalRatings) * 0.8 + (answeredComments / totalComments) * 0.2) * 100;
                progressBar.style.width = progress + '%';
                progressText.textContent = `Completion: ${Math.round(progress)}%`;

                if (submitBtn) {
                    submitBtn.disabled = progress < 100;
                }

                // Show/hide incomplete notice
                if (incompleteNotice) {
                    incompleteNotice.style.display = progress < 100 ? 'block' : 'none';
                }

                // Highlight incomplete questions
                ratingNames.forEach(name => {
                    const row = document.querySelector(`input[name="${name}"]`).closest('tr');
                    if (row && !document.querySelector(`input[name="${name}"]:checked`)) {
                        row.classList.add('incomplete');
                    } else if (row) {
                        row.classList.remove('incomplete');
                    }
                });

                // Highlight incomplete comments
                const positiveComment = document.getElementById('positive-comment');
                const negativeComment = document.getElementById('negative-comment');
                if (positiveComment && !positiveComment.value.trim()) {
                    positiveComment.style.borderColor = '#e74c3c';
                } else if (positiveComment) {
                    positiveComment.style.borderColor = '#ddd';
                }
                if (negativeComment && !negativeComment.value.trim()) {
                    negativeComment.style.borderColor = '#e74c3c';
                } else if (negativeComment) {
                    negativeComment.style.borderColor = '#ddd';
                }
            }

            // Listen to changes
            if (form) {
                form.addEventListener('change', updateProgress);
                form.addEventListener('input', updateProgress);
                
                // Initial update
                updateProgress();

                // Form submit validation
                form.addEventListener('submit', (e) => {
                    updateProgress();
                    
                    if (submitBtn.disabled) {
                        e.preventDefault();
                        modal.style.display = 'flex';
                    }
                });
            }

            // Modal button
            if (modalOkBtn) {
                modalOkBtn.addEventListener('click', () => {
                    modal.style.display = 'none';
                });
            }
        });
    </script>
</body>
</html>
