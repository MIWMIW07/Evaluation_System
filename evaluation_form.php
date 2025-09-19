<?php
// evaluation_form.php - Enhanced bilingual evaluation form for logged in students
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
            $rating = intval($_POST["rating1_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 1.$i");
            }
            $ratings["q1_$i"] = $rating;
        }

        // Section 2: Management Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["rating2_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 2.$i");
            }
            $ratings["q2_$i"] = $rating;
        }

        // Section 3: Guidance Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["rating3_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 3.$i");
            }
            $ratings["q3_$i"] = $rating;
        }

        // Section 4: Personal and Social Characteristics (6 questions)
        for ($i = 1; $i <= 6; $i++) {
            $rating = intval($_POST["rating4_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 4.$i");
            }
            $ratings["q4_$i"] = $rating;
        }

        $positive = trim($_POST['q5-positive-en'] ?? $_POST['q5-positive-tl'] ?? '');
        $negative = trim($_POST['q5-negative-en'] ?? $_POST['q5-negative-tl'] ?? '');
        $comments = "Positive: $positive\nNegative: $negative";

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
            $success = "Evaluation submitted successfully! Thank you for your feedback.";
            // Reload to show in view mode
            $check_stmt = query("SELECT * FROM evaluations WHERE user_id = ? AND teacher_id = ?",
                [$_SESSION['user_id'], $teacher_id]);
            $existing_evaluation = fetch_assoc($check_stmt);
            $is_view_mode = true;
        } else {
            throw new Exception("Database error occurred while saving your evaluation.");
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Define questions for both languages
$section1_questions = [
    '1.1' => 'Analyses and elaborates lesson without reading textbook in class.',
    '1.2' => 'Uses audio visual and devices to support and facilitate instructions.',
    '1.3' => 'Present ideas/ concepts clearly and convincingly from related fields and integrate subject matter with actual experience.',
    '1.4' => 'Make the students apply concepts to demonstrate understanding of the lesson.',
    '1.5' => 'Gives fair test and examination and return test results within the reasonable period.',
    '1.6' => 'Shows good command of the language of instruction.'
];

$section2_questions = [
    '2.1' => 'Maintains responsive, disciplined and safe classroom atmosphere that is conducive to learning.',
    '2.2' => 'Follow the schematic way.',
    '2.3' => 'Stimulate students respect regard for the teacher.',
    '2.4' => 'Allow the students to express their opinions and views.'
];

$section3_questions = [
    '3.1' => 'Accepts students as they are by recognizing their strength and weakness as individuals.',
    '3.2' => 'Inspires students to be self-reliant and self- disciplined.',
    '3.3' => 'Handles class and student\'s problem with fairness and understanding.',
    '3.4' => 'Shows genuine concern for the personal and other problems presented by the students outside classroom activities.'
];

$section4_questions = [
    '4.1' => 'Maintains emotional balance; neither over critical nor over-sensitive.',
    '4.2' => 'Is free from mannerisms that distract the teaching and learning process.',
    '4.3' => 'Is well groomed; clothes are clean and neat (Uses appropriate clothes that are becoming of a teacher).',
    '4.4' => 'Shows no favoritism.',
    '4.5' => 'Has good sense of humor and shows enthusiasm in teaching.',
    '4.6' => 'Has good diction, clear and modulated voice.'
];

$section1_tagalog = [
    '1.1' => 'Nasuri at-naipaliwanag ang araling nang hindi binabasa ang aklat sa klase.',
    '1.2' => 'Gumugamit ng audio-visual at mga device upang suportahan at mapadali ang pagtuturo',
    '1.3' => 'Nagpapakita ng mga ideya/konsepto nang malinaw at nakukumbinsi mula sa mga kaugnay na larangan at isama ang subject matter sa aktwal na karanasan.',
    '1.4' => 'Hinahayaan ang mga mag-aaral na gumamit ng mga konsepto upang ipakita ang pag-unawa sa mga aralin',
    '1.5' => 'Nagbibigay ng patas na pagsusulit at pagsusuri at ibalik ang mga result ang pagsusulit sa loob ng makatawirang panahon.',
    '1.6' => 'Naguutos nang maayos sa pagtuturo gamit ang maayos na pananalita.'
];

$section2_tagalog = [
    '2.1' => 'Pinapanatiling maayos, disiplinado at ligtas ang silid-aralan upang magkaraon ng maayos na pagaaral.',
    '2.2' => 'Sumusunod sa sistematikong iskedyul ng mga klase at iba pang pangaraw-araw na gawain.',
    '2.3' => 'Hinuhubog sa mga mag-aaral ang respeto at paggalang sa mga guro.',
    '2.4' => 'Pinahihinlulutan ang mga mag-aaral na ipahayag ang kanilang mga opinyon at mga pananaw.'
];

$section3_tagalog = [
    '3.1' => 'Pagtanggap sa mga mag-aaral bilang indibidwal na may kalakasan at kahinaan.',
    '3.2' => 'Pagpapakita ng tiwala at kaayusan sa sarili',
    '3.3' => 'Pinangangasiwaan ang problema ng klase at mga mag-aaral nang may patas at pang-unawa.',
    '3.4' => 'Nagpapakita ng tunay na pagmamalasakit sa mga personal at iba pang problemang ipinakita ng mga mag-aaral.'
];

$section4_tagalog = [
    '4.1' => 'Nagpapanatili ng emosyonal na balanse: hindi masyadong kritikal o sobrang sensitibo.',
    '4.2' => 'Malaya sa nakasanayang galaw na nakakagambala sa proseso ng pagtuturo at pagkatuto.',
    '4.3' => 'Maayos at presentable; Malinis at maayos ang mga damit.',
    '4.4' => 'Hindi nagpapakita ng paboritismo',
    '4.5' => 'May magandang sense of humor at nagpapakita ng sigla sa pagtuturo.',
    '4.6' => 'May magandang diction, malinaw at maayos na timpla ng boses.'
];

// Calculate average rating if in view mode
$average_rating = 0;
$performance_level = '';
if ($is_view_mode && $existing_evaluation) {
    $total_rating = 0;
    $total_questions = 0;

    // Sum all ratings
    for ($i = 1; $i <= 6; $i++) {
        $total_rating += $existing_evaluation["q1_$i"];
        $total_questions++;
    }
    for ($i = 1; $i <= 4; $i++) {
        $total_rating += $existing_evaluation["q2_$i"];
        $total_questions++;
    }
    for ($i = 1; $i <= 4; $i++) {
        $total_rating += $existing_evaluation["q3_$i"];
        $total_questions++;
    }
    for ($i = 1; $i <= 6; $i++) {
        $total_rating += $existing_evaluation["q4_$i"];
        $total_questions++;
    }

    $average_rating = round($total_rating / $total_questions, 2);

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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher's Performance Evaluation - <?php echo htmlspecialchars($teacher_info['name']); ?></title>
</head>
<body>
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
}

/* Small phones */
@media (max-width: 480px) {
    .rating-scale {
        padding: 8px;
    }
    .rating-item {
        font-size: 13px;
    }
}

    </style>

    <div class="container">
        <header>
            <div class="institution-name">PHILTECH GMA</div>
            <div class="institution-name">PHILIPPINE TECHNOLOGICAL INSTITUTE OF SCIENCE ARTS AND TRADE CENTRAL INC.</div>
            <div class="address">2nd Floor CRDM BLDG. Governor's Drive Brgy G. Maderan GMA, Cavite</div>

            <h1><?php echo $is_view_mode ? 'View Evaluation' : 'TEACHER\'S PERFORMANCE EVALUATION BY THE STUDENTS'; ?></h1>

            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #2196F3;">
                <h3 style="color: #1976D2; margin-bottom: 10px;">👨‍🏫 Evaluating: <?php echo htmlspecialchars($teacher_info['name']); ?></h3>
                <p style="margin: 5px 0;"><strong>Subject:</strong> <?php echo htmlspecialchars($teacher_info['subject']); ?></p>
                <p style="margin: 5px 0;"><strong>Program:</strong> <?php echo htmlspecialchars($teacher_info['program']); ?></p>
                <p style="margin: 5px 0;"><strong>Student:</strong> <?php echo htmlspecialchars($_SESSION['full_name']); ?> (<?php echo htmlspecialchars($_SESSION['student_id']); ?>)</p>
            </div>

            <div class="language-toggle">
                <button id="english-btn" class="active">English</button>
                <button id="tagalog-btn">Tagalog</button>
            </div>

            <div class="progress-container">
    <div class="progress-text" id="progress-text">Completion: 0%</div>
    <div class="progress-bar">
        <div class="progress" id="progress-bar"></div>
    </div>
</div>
    </header>
        <?php if ($teacher_info): ?>
            <div class="teacher-info">
                <h3>Teacher Information</h3>
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
                <h3>Evaluation Already Submitted</h3>
                <p>You have already evaluated this teacher on <?php echo date('F j, Y \a\t g:i A', strtotime($existing_evaluation['evaluation_date'])); ?>.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); color: #155724; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #28a745;">
                <h3>✅ Success!</h3>
                <p><?php echo htmlspecialchars($success); ?></p>
                <p style="margin-top: 15px;"><a href="student_dashboard.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Dashb[...]
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #dc3545;">
                <h3>❌ Error</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($is_view_mode && $existing_evaluation): ?>
            <div class="evaluation-results" style="background: linear-gradient(135deg, #e8f5e8 0%, #f1f8e9 100%); padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #4caf50;">
                <h3>📊 Your Evaluation Details</h3>
                <?php
                // Calculate section averages
                $section1_avg = ($existing_evaluation['q1_1'] + $existing_evaluation['q1_2'] + $existing_evaluation['q1_3'] + $existing_evaluation['q1_4'] + $existing_evaluation['q1_5'] + $existing_evaluation['q1_6']) / 6;
                $section2_avg = ($existing_evaluation['q2_1'] + $existing_evaluation['q2_2'] + $existing_evaluation['q2_3'] + $existing_evaluation['q2_4']) / 4;
                $section3_avg = ($existing_evaluation['q3_1'] + $existing_evaluation['q3_2'] + $existing_evaluation['q3_3'] + $existing_evaluation['q3_4']) / 4;
                $section4_avg = ($existing_evaluation['q4_1'] + $existing_evaluation['q4_2'] + $existing_evaluation['q4_3'] + $existing_evaluation['q4_4'] + $existing_evaluation['q4_5'] + $existing_evaluation['q4_6']) / 6;

                // Function to display bar chart for section
                function display_section_bar($section_name, $avg) {
                    $percentage = ($avg / 5) * 100;
                    echo '<div style="margin-bottom: 20px; padding: 15px; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">';
                    echo '<p style="font-weight: 600; color: #2c3e50; margin-bottom: 10px;">' . htmlspecialchars($section_name) . ' - ' . number_format($avg, 1) . '/5</p>';
                    echo '<div style="background: #ecf0f1; height: 25px; border-radius: 12px; overflow: hidden;">';
                    echo '<div style="background: linear-gradient(90deg, #3498db, #2ecc71); height: 100%; width: ' . $percentage . '%; border-radius: 12px; transition: width 1s ease;"></div>';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
                <h4 style="color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 30px;">Ratings Overview</h4>
                <div style="margin-bottom: 30px; padding: 20px; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
                    <h5 style="color: #2c3e50; margin-bottom: 15px;">Average Ratings Trend</h5>
                    <svg id="ratingLineGraph" width="100%" height="220" viewBox="0 0 400 220" style="border: 1px solid #ecf0f1; border-radius: 8px; background: white; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                        <defs>
                            <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:gold;stop-opacity:1" />
                                <stop offset="25%" style="stop-color:goldenrod;stop-opacity:1" />
                                <stop offset="50%" style="stop-color:lightgoldenrodyellow;stop-opacity:1" />
                                <stop offset="75%" style="stop-color:maroon;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:darkred;stop-opacity:1" />
                            </linearGradient>
                            <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
                                <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="maroon" flood-opacity="0.4"/>
                            </filter>
                        </defs>
                        <?php
                        $sections = [$section1_avg, $section2_avg, $section3_avg, $section4_avg];
                        $labels = ['Teaching', 'Management', 'Guidance', 'Personal'];
                        $points = '';
                        for ($i = 0; $i < count($sections); $i++) {
                            $x = 50 + $i * 100;
                            $y = 170 - ($sections[$i] / 5) * 100;
                            $points .= ($i > 0 ? ' ' : '') . "$x,$y";
                            // Draw vertical grid lines
                            echo "<line x1='$x' y1='70' x2='$x' y2='170' stroke='#ecf0f1' stroke-width='1' />";
                            // Draw labels
                            echo "<text x='$x' y='190' text-anchor='middle' font-size='12' fill='#7f8c8d'>{$labels[$i]}</text>";
                        }
                        // Draw horizontal grid lines and labels
                        for ($j = 0; $j <= 5; $j++) {
                            $y = 170 - $j * 20;
                            echo "<line x1='40' y1='$y' x2='350' y2='$y' stroke='#ecf0f1' stroke-width='1' />";
                            echo "<text x='30' y='" . ($y + 5) . "' text-anchor='end' font-size='10' fill='#7f8c8d'>" . $j . "</text>";
                        }
                        // Draw polyline with shadow filter
                        echo "<polyline points='$points' fill='none' stroke='url(#lineGradient)' stroke-width='4' filter='url(#shadow)' stroke-linejoin='round' stroke-linecap='round' />";
                        // Draw points with hover circles
                        $points_array = explode(' ', $points);
                        foreach ($points_array as $index => $point) {
                            list($x, $y) = explode(',', $point);
                            echo "<circle class='hover-point' cx='$x' cy='$y' r='7' fill='gold' style='cursor:pointer;' data-label='{$labels[$index]}' data-value='" . number_format($sections[$index], 2) . "' />";
                            echo "<circle cx='$x' cy='$y' r='10' fill='none' stroke='goldenrod' stroke-width='2' opacity='0.3' />";
                        }
                        ?>
                        <style>
                            .tooltip {
                                position: absolute;
                                background: maroon;
                                color: lightgoldenrodyellow;
                                padding: 6px 12px;
                                border-radius: 6px;
                                font-size: 14px;
                                pointer-events: none;
                                opacity: 0;
                                transition: opacity 0.3s ease;
                                white-space: nowrap;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                                z-index: 1000;
                            }
                        </style>
                    </svg>
                    <div id="tooltip" class="tooltip"></div>
                    <script>
                        const svg = document.getElementById('ratingLineGraph');
                        const tooltip = document.getElementById('tooltip');
                        const points = svg.querySelectorAll('.hover-point');

                        points.forEach(point => {
                            point.addEventListener('mouseenter', (e) => {
                                const label = e.target.getAttribute('data-label');
                                const value = e.target.getAttribute('data-value');
                                tooltip.textContent = label + ': ' + value + ' / 5';
                                tooltip.style.opacity = 1;
                                const rect = svg.getBoundingClientRect();
                                const cx = parseFloat(e.target.getAttribute('cx'));
                                const cy = parseFloat(e.target.getAttribute('cy'));
                                tooltip.style.left = (rect.left + cx + 10) + 'px';
                                tooltip.style.top = (rect.top + cy - 30) + 'px';
                            });
                            point.addEventListener('mouseleave', () => {
                                tooltip.style.opacity = 0;
                            });
                        });
                    </script>
                </div>
                <?php
                display_section_bar('1. Teaching Competence', $section1_avg);
                display_section_bar('2. Management Skills', $section2_avg);
                display_section_bar('3. Guidance Skills', $section3_avg);
                display_section_bar('4. Personal and Social Qualities/Skills', $section4_avg);
                ?>

                <?php
                // Function to display question and selected rating with professional design
                function display_question_rating($question, $rating) {
                    echo '<div style="margin-bottom: 15px; padding: 15px; background: #ffffff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">';
                    echo '<p style="font-weight: 600; color: #2c3e50; margin-bottom: 8px;">' . htmlspecialchars($question) . '</p>';
                    echo '<div style="display: inline-block; background: #2ecc71; color: white; padding: 5px 12px; border-radius: 20px; font-weight: bold; font-size: 14px;">';
                    echo htmlspecialchars($rating) . ' / 5';
                    echo '</div>';
                    echo '</div>';
                }
                ?>
                <h4 style="color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 30px;">1. Teaching Competence</h4>
                <?php
                foreach ($section1_questions as $key => $question) {
                    $rating = $existing_evaluation["q1_" . substr($key, 2)] ?? 'N/A';
                    display_question_rating($question, $rating);
                }
                ?>
                <h4 style="color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 30px;">2. Management Skills</h4>
                <?php
                foreach ($section2_questions as $key => $question) {
                    $rating = $existing_evaluation["q2_" . substr($key, 2)] ?? 'N/A';
                    display_question_rating($question, $rating);
                }
                ?>
                <h4 style="color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 30px;">3. Guidance Skills</h4>
                <?php
                foreach ($section3_questions as $key => $question) {
                    $rating = $existing_evaluation["q3_" . substr($key, 2)] ?? 'N/A';
                    display_question_rating($question, $rating);
                }
                ?>
                <h4 style="color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 30px;">4. Personal and Social Qualities/Skills</h4>
                <?php
                foreach ($section4_questions as $key => $question) {
                    $rating = $existing_evaluation["q4_" . substr($key, 2)] ?? 'N/A';
                    display_question_rating($question, $rating);
                }
                ?>
                <h4 style="color: #3498db; border-bottom: 2px solid #3498db; padding-bottom: 5px; margin-top: 30px;">Comments</h4>
                <?php
                $comments = $existing_evaluation['comments'];
                $parts = explode("\n", $comments);
                $positive = str_replace("Positive: ", "", $parts[0] ?? '');
                $negative = str_replace("Negative: ", "", $parts[1] ?? '');
                ?>
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1; background: #f9f9f9; padding: 15px; border-radius: 8px; box-shadow: inset 0 0 5px rgba(0,0,0,0.05);">
                        <h5 style="color: #27ae60; margin-bottom: 10px;">Positive Comments</h5>
                        <p style="font-size: 15px; color: #2c3e50;"><?php echo nl2br(htmlspecialchars($positive)); ?></p>
                    </div>
                    <div style="flex: 1; background: #f9f9f9; padding: 15px; border-radius: 8px; box-shadow: inset 0 0 5px rgba(0,0,0,0.05);">
                        <h5 style="color: #e74c3c; margin-bottom: 10px;">Negative Comments</h5>
                        <p style="font-size: 15px; color: #2c3e50;"><?php echo nl2br(htmlspecialchars($negative)); ?></p>
                    </div>
                </div>
                <p style="margin-top: 15px;"><a href="student_dashboard.php" style="background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a></p>
            </div>
        <?php endif; ?>

        <div class="instructions english">
            <p>Instructions: The following items describe the aspects of teacher's behavior in and out the classroom. Please choose the number that indicates the degree to which you feel each item is descriptive of him/her. Your rating will be the reference that may lead to the improvement of instructor, so kindly rate each item as thoughtfully and carefully as possible. This will be kept confidentially.</p>
            <p class="incomplete-notice" id="english-incomplete-notice">Please answer all questions before submitting. Incomplete sections are highlighted in red.</p>
        </div>
        
        <div class="instructions tagalog">
            <p>Mga Panuto: Ang mga sumusunod na aytem ay naglalarawan sa mga aspeto ng pag-uugali ng guro sa loob at labas ng silid-aralan. Paki piliin ang numero na nagpapakita ng antas kung saan naramdaman mo ang bawat aytem na naglalarawan sa kanya. Ang iyong rating ay magiging sanggunian na maaaring humantong sa pagpapabuti ng tagapagturo, kaya mangyaring i-rate ang bawat aytem nang maingat at maayos. Ito ay itatago nang kumpidensyal.</p>
            <p class="incomplete-notice" id="tagalog-incomplete-notice">Mangyaring sagutin ang lahat ng mga katanungan bago ipasa. Ang mga hindi kumpletong seksyon ay naka-highlight sa pula.</p>
        </div>
        
        <div class="rating-scale">
            <div class="rating-item">5 - Outstanding</div>
            <div class="rating-item">4 - Very Satisfactory</div>
            <div class="rating-item">3 - Good/Satisfactory</div>
            <div class="rating-item">2 - Fair</div>
            <div class="rating-item">1 - Unsatisfactory</div>
        </div>
        
        <?php if (!$is_view_mode): ?>
        <form id="evaluation-form" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <!-- English Content -->
            <div class="english">
                <h2>1. Teaching Competence</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-1-1">
                            <td>1.1 Analyses and elaborates lesson without reading textbook in class.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_1" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_1" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_1" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_1" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_1" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-2">
                            <td>1.2 Uses audio visual and devices to support and facilitate instructions.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_2" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_2" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_2" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_2" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_2" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-3">
                            <td>1.3 Present ideas/ concepts clearly and convincingly from related fields and integrate subject matter with actual experience.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_3" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_3" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_3" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_3" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_3" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-4">
                            <td>1.4 Make the students apply concepts to demonstrate understanding of the lesson.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_4" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_4" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_4" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_4" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_4" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-5">
                            <td>1.5 Gives fair test and examination and return test results within the reasonable period.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_5" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_5" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_5" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_5" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_5" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-6">
                            <td>1.6 Shows good command of the language of instruction.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_6" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_6" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_6" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_6" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_6" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2>2. Management Skills</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-2-1">
                            <td>2.1 Maintains responsive, disciplined and safe classroom atmosphere that is conducive to learning.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_1" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_1" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_1" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_1" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_1" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-2-2">
                            <td>2.2 Follow the schematic way.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_2" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_2" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_2" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_2" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_2" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-2-3">
                            <td>2.3 Stimulate students respect regard for the teacher.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_3" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_3" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_3" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_3" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_3" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-2-4">
                            <td>2.4 Allow the students to express their opinions and views.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_4" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_4" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_4" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_4" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_4" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2>3. Guidance Skills</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-3-1">
                            <td>3.1 Accepts students as they are by recognizing their strength and weakness as individuals.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_1" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_1" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_1" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_1" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_1" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-3-2">
                            <td>3.2 Inspires students to be self-reliant and self- disciplined.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_2" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_2" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_2" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_2" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_2" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-3-3">
                            <td>3.3 Handles class and student's problem with fairness and understanding.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_3" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_3" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_3" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_3" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_3" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-3-4">
                            <td>3.4 Shows genuine concern for the personal and other problems presented by the students outside classroom activities.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_4" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_4" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_4" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_4" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_4" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2>4. Personal and Social Qualities/Skills</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-4-1">
                            <td>4.1 Maintains emotional balance; neither over critical nor over-sensitive.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_1" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_1" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_1" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_1" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_1" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-2">
                            <td>4.2 Is free from mannerisms that distract the teaching and learning process.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_2" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_2" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_2" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_2" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_2" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-3">
                            <td>4.3 Is well groomed; clothes are clean and neat (Uses appropriate clothes that are becoming of a teacher).</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_3" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_3" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_3" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_3" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_3" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-4">
                            <td>4.4 Shows no favoritism.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_4" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_4" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_4" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_4" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_4" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-5">
                            <td>4.5 Has good sense of humor and shows enthusiasm in teaching.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_5" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_5" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_5" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_5" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_5" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-6">
                            <td>4.6 Has good diction, clear and modulated voice.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_6" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_6" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_6" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_6" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_6" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="comments-section">
                    <h2>5. Comments</h2>
                    <tr class="english">
  
  <td>
     <!-- Positive Comment -->
    <textarea name="q5-positive-en" class="required-comment" placeholder="Positive comment about the instructor..."></textarea>
    <!-- Negative Comment -->
    <textarea name="q5-negative-en" class="required-comment" placeholder="Negative comment about the instructor..."></textarea>
  </td>
</tr>
                </div>
            </div>
            
            <!-- Tagalog Content -->
            <div class="tagalog">
                <h2>1. Kakayahan sa Pagtuturo</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-1-1-t">
                            <td>1.1 Nasuri at-naipaliwanag ang aralin nang hindi binabasa ang aklat sa klase.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_1_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_1_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_1_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_1_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_1_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-2-t">
                            <td>1.2 Gumugamit ng audio-visual at mga device upang suportahan at mapadali ang pagtuturo</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_2_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_2_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_2_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_2_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_2_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-3-t">
                            <td>1.3 Nagpapakita ng mga ideya/konsepto nang malinaw at nakukumbinsi mula sa mga kaugnay na larangan at isama ang subject matter sa aktwal na karanasan.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_3_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_3_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_3_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_3_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_3_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-4-t">
                            <td>1.4 Hinahayaan ang mga mag-aaral na gumamit ng mga konsepto upang ipakita ang pag-unawa sa mga aralin</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_4_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_4_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_4_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_4_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_4_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-5-t">
                            <td>1.5 Nagbibigay ng patas na pagsusulit at pagsusuri at ibalik ang mga resulta ng pagsusulit sa loob ng makatawirang panahon.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_5_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_5_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_5_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_5_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_5_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-1-6-t">
                            <td>1.6 Naguutos nang maayos sa pagtuturo gamit ang maayos na pananalita.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating1_6_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating1_6_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating1_6_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating1_6_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating1_6_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2>2. Kasanayan sa pamamahala</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-2-1-t">
                            <td>2.1 Pinapanatiling maayos, disiplinado at ligtas ang silid-aralan upang magkaraoon ng maayos at maaliwalas na pagaaral.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_1_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_1_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_1_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_1_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_1_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-2-2-t">
                            <td>2.2 Sumusunod sa sistematikong iskedyul ng mga klase at iba pang pangaraw-araw na gawain.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_2_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_2_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_2_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_2_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_2_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-2-3-t">
                            <td>2.3 Hinuhubog sa mga mag-aaral ang respeto at paggalang sa mga guro.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_3_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_3_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_3_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_3_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_3_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-2-4-t">
                            <td>2.4 Pinahihinlulutan ang mga mag-aaral na ipahayag ang kanilang mga opinyon at mga pananaw.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating2_4_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating2_4_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating2_4_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating2_4_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating2_4_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2>3. Mga kasanayan sa Paggabay</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-3-1-t">
                            <td>3.1 Pagtanggap sa mga mag-aaral bilang indibidwal na may kalakasan at kahinaan.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_1_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_1_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_1_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_1_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_1_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-3-2-t">
                            <td>3.2 Pagpapakita ng tiwala and kaayusan sa sarili</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_2_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_2_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_2_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_2_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_2_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-3-3-t">
                            <td>3.3 Pinangangasiwaan ang problema ng klase at mga mag-aaral nang may patas at pang-unawa.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_3_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_3_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_3_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_3_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_3_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-3-4-t">
                            <td>3.4 Nagpapakita ng tunay na pagmamalasakit sa mga personal at iba pang problemang ipinakita ng mga mag-aaral sa labas ng mga aktibidad sa silid-aralan.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating3_4_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating3_4_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating3_4_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating3_4_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating3_4_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <h2>4. Personal at panlipunang katangian</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr id="question-4-1-t">
                            <td>4.1 Nagpapanatili ng emosyonal na balanse: hindi masyadong kritikal o sobrang sensitibo.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_1_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_1_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_1_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_1_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_1_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-2-t">
                            <td>4.2 Malaya sa nakasanayang galaw na nakakagambala sa proseso ng pagtuturo at pagkatuto.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_2_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_2_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_2_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_2_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_2_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-3-t">
                            <td>4.3 Maayos at presentable; Malinis at maayos ang mga damit.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_3_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_3_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_3_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_3_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_3_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-4-t">
                            <td>4.4 Hindi nagpapakita ng paboritismo</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_4_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_4_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_4_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_4_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_4_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-5-t">
                            <td>4.5 May magandang sense of humor at nagpapakita ng sigla sa pagtuturo.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_5_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_5_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_5_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_5_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_5_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                        <tr id="question-4-6-t">
                            <td>4.6 May magandang diction, malinaw at maayos na timpla ng boses.</td>
                            <td>
                                <div class="rating-options">
                                    <label><input type="radio" name="rating4_6_t" value="5" class="required-rating"> 5</label>
                                    <label><input type="radio" name="rating4_6_t" value="4"> 4</label>
                                    <label><input type="radio" name="rating4_6_t" value="3"> 3</label>
                                    <label><input type="radio" name="rating4_6_t" value="2"> 2</label>
                                    <label><input type="radio" name="rating4_6_t" value="1"> 1</label>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="comments-section">
                    <h2>5.Komento</h2>
                   <!-- Positive Comment -->
    <textarea name="q5-positive-tl" class="required-comment" placeholder="Positibong komento tungkol sa guro..."></textarea>
    <!-- Negative Comment -->
    <textarea name="q5-negative-tl" class="required-comment" placeholder="Negatibong komento tungkol sa guro..."></textarea>
                </div>
            </div>
            
            <button type="submit" class="submit-btn" id="submit-btn" disabled>Submit Evaluation</button>
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
    const englishBtn = document.getElementById('english-btn');
    const tagalogBtn = document.getElementById('tagalog-btn');
    const englishContent = document.querySelectorAll('.english');
    const tagalogContent = document.querySelectorAll('.tagalog');
    const form = document.getElementById('evaluation-form');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const submitBtn = document.getElementById('submit-btn');

    // Helper: sync radio selection and comments from English to Tagalog
    function syncEnglishToTagalog() {
        for (let i = 1; i <= 4; i++) {
            let max = i === 1 ? 6 : (i === 2 || i === 3 ? 4 : 6);
            for (let j = 1; j <= max; j++) {
                let nameEn = `rating${i}_${j}`;
                let nameTl = `rating${i}_${j}_t`;
                let checkedRadio = form.querySelector(`input[name="${nameEn}"]:checked`);
                if (checkedRadio) {
                    let value = checkedRadio.value;
                    let toRadio = form.querySelector(`input[name="${nameTl}"][value="${value}"]`);
                    if (toRadio) toRadio.checked = true;
                } else {
                    form.querySelectorAll(`input[name="${nameTl}"]`).forEach(r => r.checked = false);
                }
            }
        }
        // Sync comments
        let positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
        let positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
        let negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
        let negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
        if (positiveEn && positiveTl) positiveTl.value = positiveEn.value;
        if (negativeEn && negativeTl) negativeTl.value = negativeEn.value;
    }

    // Helper: sync radio selection and comments from Tagalog to English
    function syncTagalogToEnglish() {
        for (let i = 1; i <= 4; i++) {
            let max = i === 1 ? 6 : (i === 2 || i === 3 ? 4 : 6);
            for (let j = 1; j <= max; j++) {
                let nameTl = `rating${i}_${j}_t`;
                let nameEn = `rating${i}_${j}`;
                let checkedRadio = form.querySelector(`input[name="${nameTl}"]:checked`);
                if (checkedRadio) {
                    let value = checkedRadio.value;
                    let toRadio = form.querySelector(`input[name="${nameEn}"][value="${value}"]`);
                    if (toRadio) toRadio.checked = true;
                } else {
                    form.querySelectorAll(`input[name="${nameEn}"]`).forEach(r => r.checked = false);
                }
            }
        }
        // Sync comments
        let positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
        let positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
        let negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
        let negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
        if (positiveTl && positiveEn) positiveEn.value = positiveTl.value;
        if (negativeTl && negativeEn) negativeEn.value = negativeTl.value;
    }

    // Language toggle
    englishBtn.addEventListener('click', () => {
        // Sync Tagalog answers to English before showing English
        syncTagalogToEnglish();
        englishContent.forEach(el => el.style.display = 'block');
        tagalogContent.forEach(el => el.style.display = 'none');
        englishBtn.classList.add('active');
        tagalogBtn.classList.remove('active');
        updateProgress();
    });

    tagalogBtn.addEventListener('click', () => {
        // Sync English answers to Tagalog before showing Tagalog
        syncEnglishToTagalog();
        englishContent.forEach(el => el.style.display = 'none');
        tagalogContent.forEach(el => el.style.display = 'block');
        tagalogBtn.classList.add('active');
        englishBtn.classList.remove('active');
        updateProgress();
    });

    // Initialize display
    englishContent.forEach(el => el.style.display = 'block');
    tagalogContent.forEach(el => el.style.display = 'none');

    // Update progress bar
    function updateProgress() {
        // Get all unique radio group names (both languages)
        const radioInputs = form.querySelectorAll('input[type="radio"]');
        const ratingNames = Array.from(radioInputs)
            .map(input => input.name)
            .filter((v, i, a) => a.indexOf(v) === i && !v.endsWith('_t') && !v.endsWith('_en')); // Only base names

        // For each base name, check if either language has a checked radio
        let totalRatings = ratingNames.length;
        let answeredRatings = 0;
        ratingNames.forEach(name => {
            let checked = form.querySelector(`input[name="${name}"]:checked`)
                || form.querySelector(`input[name="${name}_t"]:checked`)
                || form.querySelector(`input[name="${name}_en"]:checked`);
            if (checked) answeredRatings++;
        });

        // Comments: check both languages
        const totalComments = 2; // Only need one positive and one negative
        let answeredComments = 0;
        if (
            form.querySelector('textarea[name="q5-positive-en"]').value.trim() ||
            form.querySelector('textarea[name="q5-positive-tl"]').value.trim()
        ) answeredComments++;
        if (
            form.querySelector('textarea[name="q5-negative-en"]').value.trim() ||
            form.querySelector('textarea[name="q5-negative-tl"]').value.trim()
        ) answeredComments++;

        const progress = ((answeredRatings / totalRatings + answeredComments / totalComments) / 2) * 100;
        progressBar.style.width = progress + '%';
        progressText.textContent = `Completion: ${Math.round(progress)}%`;

        submitBtn.disabled = progress < 100;
    }

    // Listen to changes
    form.addEventListener('change', updateProgress);
    form.addEventListener('input', updateProgress);

    // Form submit validation
    form.addEventListener('submit', (e) => {
        let valid = true;

        // Check radio groups (base names only)
        for (let i = 1; i <= 4; i++) {
            let max = i === 1 ? 6 : (i === 2 || i === 3 ? 4 : 6);
            for (let j = 1; j <= max; j++) {
                let name = `rating${i}_${j}`;
                let checked = form.querySelector(`input[name="${name}"]:checked`)
                    || form.querySelector(`input[name="${name}_t"]:checked`)
                    || form.querySelector(`input[name="${name}_en"]:checked`);
                let row = document.getElementById(`question-${i}-${j}`) || document.getElementById(`question-${i}-${j}-t`);
                if (!checked && row) {
                    valid = false;
                    row.style.backgroundColor = '#f8d7da';
                } else if (row) {
                    row.style.backgroundColor = '';
                }
            }
        }

        // Check comments (either language)
        let posEn = form.querySelector('textarea[name="q5-positive-en"]');
        let posTl = form.querySelector('textarea[name="q5-positive-tl"]');
        let negEn = form.querySelector('textarea[name="q5-negative-en"]');
        let negTl = form.querySelector('textarea[name="q5-negative-tl"]');
        if (
            (!posEn.value.trim() && !posTl.value.trim())
        ) {
            valid = false;
            if (posEn) posEn.style.border = '2px solid red';
            if (posTl) posTl.style.border = '2px solid red';
        } else {
            if (posEn) posEn.style.border = '';
            if (posTl) posTl.style.border = '';
        }
        if (
            (!negEn.value.trim() && !negTl.value.trim())
        ) {
            valid = false;
            if (negEn) negEn.style.border = '2px solid red';
            if (negTl) negTl.style.border = '2px solid red';
        } else {
            if (negEn) negEn.style.border = '';
            if (negTl) negTl.style.border = '';
        }

        if (!valid) {
            e.preventDefault();
            alert('Please complete all required fields before submitting.');
        }
    });
});

// Set progress to 100% in view mode
if (<?php echo $is_view_mode ? 'true' : 'false'; ?>) {
    document.getElementById('progress-bar').style.width = '100%';
    document.getElementById('progress-text').textContent = 'Completion: 100%';
}
</script>
</body>
</html>
