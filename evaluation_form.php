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
    // Get student's section information to determine if they're College or SHS
    $student_section_stmt = query("
        SELECT s.program, s.section_code, s.year_level 
        FROM users u 
        JOIN students st ON u.student_table_id = st.id 
        JOIN sections s ON st.section_id = s.id 
        WHERE u.id = ?", [$_SESSION['user_id']]);
    
    $student_section = fetch_assoc($student_section_stmt);
    $student_department = $student_section['program'] ?? 'COLLEGE'; // Default to COLLEGE if not found
    
    // Store student program info in session if not already there
    if (!isset($_SESSION['program'])) {
        $_SESSION['program'] = $student_section['program'] ?? 'Not Specified';
        $_SESSION['section'] = $student_section['section_code'] ?? 'Not Specified';
    }

    // Get teacher information - match teacher's department with student's level
    $teacher_stmt = query("
        SELECT t.id, t.name, t.department, 
               CASE 
                   WHEN t.department = 'COLLEGE' THEN 'College Department'
                   WHEN t.department = 'SHS' THEN 'Senior High School Department'
                   ELSE t.department 
               END as display_department
        FROM teachers t 
        WHERE t.id = ? AND t.department = ?", 
        [$teacher_id, $student_department]);
    
    $teacher_info = fetch_assoc($teacher_stmt);

    if (!$teacher_info) {
        // If no match found, try to get teacher info without department restriction for error message
        $teacher_fallback_stmt = query("SELECT name, department FROM teachers WHERE id = ?", [$teacher_id]);
        $teacher_fallback = fetch_assoc($teacher_fallback_stmt);
        
        if ($teacher_fallback) {
            throw new Exception("This teacher (" . $teacher_fallback['name'] . ") is assigned to " . 
                              $teacher_fallback['department'] . " department, but you are a " . 
                              $student_department . " student. You can only evaluate teachers from your department.");
        } else {
            throw new Exception("Teacher not found.");
        }
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

        // FIXED INSERT STATEMENT - Match exact column count (27 columns + auto-increment id)
        $insert_sql = "INSERT INTO evaluations (
            user_id, student_id, student_name, section, program, teacher_id, 
            q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
            q2_1, q2_2, q2_3, q2_4,
            q3_1, q3_2, q3_3, q3_4,
            q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
            comments
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $_SESSION['user_id'], 
            $_SESSION['student_id'] ?? '', 
            $_SESSION['full_name'] ?? '', 
            $_SESSION['section'] ?? '', 
            $_SESSION['program'] ?? '', 
            $teacher_id,
            $ratings['q1_1'], $ratings['q1_2'], $ratings['q1_3'], $ratings['q1_4'], $ratings['q1_5'], $ratings['q1_6'],
            $ratings['q2_1'], $ratings['q2_2'], $ratings['q2_3'], $ratings['q2_4'],
            $ratings['q3_1'], $ratings['q3_2'], $ratings['q3_3'], $ratings['q3_4'],
            $ratings['q4_1'], $ratings['q4_2'], $ratings['q4_3'], $ratings['q4_4'], $ratings['q4_5'], $ratings['q4_6'],
            $comments
        ];

        // Debug: Check counts match
        $placeholders = substr_count($insert_sql, '?');
        $param_count = count($params);
        
        if ($placeholders !== $param_count) {
            throw new Exception("Parameter count mismatch: $placeholders placeholders vs $param_count parameters");
        }

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
        $error = "Error: " . $e->getMessage();
    }
}

// ... REST OF THE CODE REMAINS THE SAME ...

// Helper function to safely display values
function safe_display($value, $default = 'Not Available') {
    return !empty($value) && $value !== null ? htmlspecialchars($value) : $default;
}

// Define questions for both languages (keeping your existing arrays)
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

// Tagalog questions (keeping your existing arrays)
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

    // Sum all ratings safely
    for ($i = 1; $i <= 6; $i++) {
        $total_rating += intval($existing_evaluation["q1_$i"] ?? 0);
        $total_questions++;
    }
    for ($i = 1; $i <= 4; $i++) {
        $total_rating += intval($existing_evaluation["q2_$i"] ?? 0);
        $total_questions++;
    }
    for ($i = 1; $i <= 4; $i++) {
        $total_rating += intval($existing_evaluation["q3_$i"] ?? 0);
        $total_questions++;
    }
    for ($i = 1; $i <= 6; $i++) {
        $total_rating += intval($existing_evaluation["q4_$i"] ?? 0);
        $total_questions++;
    }

    if ($total_questions > 0) {
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher's Performance Evaluation - <?php echo safe_display($teacher_info['name'] ?? ''); ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f9f5eb;
            color: #5E0C0C;
            line-height: 1.6;
            padding: 20px;
            padding-top: 70px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fffaf0;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(94, 12, 12, 0.1);
            position: relative;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #DAA520;
            padding-bottom: 20px;
        }

        h1 {
            color: #800000;
            margin-bottom: 10px;
        }

        h2 {
            color: #DAA520;
            margin: 25px 0 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #f1dca0;
        }

        .institution-name {
            font-weight: bold;
            font-size: 1.4em;
            color: #800000;
        }

        .address {
            font-style: italic;
            color: #9c6c6c;
            margin-bottom: 15px;
        }

        .teacher-info {
            background: linear-gradient(135deg, #fff9e6 0%, #f9eeca 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #DAA520;
        }
        
        .teacher-info h3 {
            color: #800000;
            margin-bottom: 15px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            background: #fffaf0;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(94, 12, 12, 0.1);
        }
        
        .info-item label {
            font-weight: 600;
            color: #9c6c6c;
            font-size: 0.9em;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-item span {
            color: #5E0C0C;
            font-weight: bold;
        }

        .language-toggle {
            display: flex;
            justify-content: center;
            margin: 15px 0;
        }

        .language-toggle button {
            padding: 8px 15px;
            margin: 0 5px;
            background-color: #f1dca0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            color: #5E0C0C;
        }

        .language-toggle button.active {
            background-color: #DAA520;
            color: white;
        }

        .progress-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #fffaf0;
            padding: 10px 20px;
            box-shadow: 0 2px 5px rgba(94, 12, 12, 0.1);
            z-index: 2000;
        }

        .progress-bar {
            height: 8px;
            background-color: #f1dca0;
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background-color: #DAA520;
            width: 0%;
            transition: width 0.5s ease;
        }

        .progress-text {
            text-align: center;
            font-weight: bold;
            color: #5E0C0C;
            margin-bottom: 5px;
        }

        .instructions {
            background-color: #f9f5eb;
            padding: 15px;
            border-left: 4px solid #DAA520;
            margin-bottom: 25px;
            border-radius: 0 5px 5px 0;
        }

        .rating-scale {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(to right, #5E0C0C, #800000, #DAA520, #FFD700, #f1dca0);
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
            border-bottom: 1px solid #f1dca0;
        }

        th {
            background-color: #f9f5eb;
            font-weight: 600;
            color: #5E0C0C;
        }

        tr:hover {
            background-color: #fff9e6;
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
            color: #5E0C0C;
        }

        input[type="radio"] {
            transform: scale(1.2);
            margin: 8px 0;
        }

        textarea {
            width: 100%;
            height: 120px;
            padding: 15px;
            border: 1px solid #f1dca0;
            border-radius: 5px;
            resize: vertical;
            font-size: 16px;
            background-color: #fffaf0;
            color: #5E0C0C;
        }

        .submit-btn {
            display: block;
            width: 200px;
            margin: 30px auto 10px;
            padding: 12px;
            background-color: #DAA520;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background-color: #b8860b;
        }

        .submit-btn:disabled {
            background-color: #9c6c6c;
            cursor: not-allowed;
        }

        .success-message {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
        }

        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #dc3545;
        }

        .tagalog {
            display: none;
        }

        footer {
            text-align: center;
            margin-top: 20px;
            color: #9c6c6c;
            font-size: 0.9em;
        }

        /* Responsive design */
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
    </style>
</head>
<body>
    <div class="progress-container">
        <div class="progress-text" id="progress-text">Completion: 0%</div>
        <div class="progress-bar">
            <div class="progress" id="progress-bar"></div>
        </div>
    </div>

    <div class="container">
        <header>
            <div class="institution-name">PHILTECH GMA</div>
            <div class="institution-name">PHILIPPINE TECHNOLOGICAL INSTITUTE OF SCIENCE ARTS AND TRADE CENTRAL INC.</div>
            <div class="address">2nd Floor CRDM BLDG. Governor's Drive Brgy G. Maderan GMA, Cavite</div>

            <h1><?php echo $is_view_mode ? 'View Evaluation' : 'TEACHER\'S PERFORMANCE EVALUATION BY THE STUDENTS'; ?></h1>

            <?php if ($teacher_info): ?>
            <div style="background: linear-gradient(135deg, #fff9e6 0%, #f9eeca 100%); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #DAA520;">
                <h3 style="color: #800000; margin-bottom: 10px;">👨‍🏫 Evaluating: <?php echo safe_display($teacher_info['name']); ?></h3>
                <p style="margin: 5px 0;"><strong>Department:</strong> <?php echo safe_display($teacher_info['display_department']); ?></p>
                <p style="margin: 5px 0;"><strong>Student:</strong> <?php echo safe_display($_SESSION['full_name'] ?? ''); ?> (<?php echo safe_display($_SESSION['student_id'] ?? ''); ?>)</p>
                <p style="margin: 5px 0;"><strong>Student Program:</strong> <?php echo safe_display($_SESSION['program'] ?? ''); ?></p>
                <p style="margin: 5px 0;"><strong>Student Section:</strong> <?php echo safe_display($_SESSION['section'] ?? ''); ?></p>
            </div>
            <?php endif; ?>

            <div class="language-toggle">
                <button id="english-btn" class="active">English</button>
                <button id="tagalog-btn">Tagalog</button>
            </div>
        </header>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <h3>✅ Success!</h3>
                <p><?php echo safe_display($success); ?></p>
                <p style="margin-top: 15px;"><a href="student_dashboard.php" style="background: #DAA520; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <h3>❌ Error</h3>
                <p><?php echo safe_display($error); ?></p>
                <?php if (strpos($error, 'department') !== false): ?>
                    <p style="margin-top: 15px;"><a href="student_dashboard.php" style="background: #DAA520; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($is_view_mode && $existing_evaluation): ?>
            <div class="evaluation-status">
                <h3>✅ Evaluation Already Submitted</h3>
                <p>You have already evaluated this teacher on <?php echo date('F j, Y \a\t g:i A', strtotime($existing_evaluation['evaluation_date'] ?? 'now')); ?>.</p>
                <p>Average Rating: <strong><?php echo $average_rating; ?>/5.0</strong> (<?php echo $performance_level; ?>)</p>
                <p style="margin-top: 15px;"><a href="student_dashboard.php" style="background: #4caf50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a></p>
            </div>
        <?php endif; ?>

        <?php if (!$is_view_mode && $teacher_info && empty($error)): ?>
        <div class="instructions english">
            <p>Instructions: The following items describe the aspects of teacher's behavior in and out the classroom. Please choose the number that indicates the degree to which you feel each item is descriptive of him/her. Your rating will be the reference that may lead to the improvement of instructor, so kindly rate each item as thoughtfully and carefully as possible. This will be kept confidentially.</p>
        </div>
        
        <div class="instructions tagalog">
            <p>Mga Panuto: Ang mga sumusunod na aytem ay naglalarawan sa mga aspeto ng pag-uugali ng guro sa loob at labas ng silid-aralan. Paki piliin ang numero na nagpapakita ng antas kung saan naramdaman mo ang bawat aytem na naglalarawan sa kanya. Ang iyong rating ay magiging sanggunian na maaaring humantong sa pagpapabuti ng tagapagturo, kaya mangyaring i-rate ang bawat aytem nang maingat at maayos. Ito ay itatago nang kumpidensyal.</p>
        </div>
        
        <div class="rating-scale">
            <div class="rating-item">5 - Outstanding</div>
            <div class="rating-item">4 - Very Satisfactory</div>
            <div class="rating-item">3 - Good/Satisfactory</div>
            <div class="rating-item">2 - Fair</div>
            <div class="rating-item">1 - Unsatisfactory</div>
        </div>

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
                        <?php foreach ($section1_questions as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        <?php foreach ($section2_questions as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        <?php foreach ($section3_questions as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        <?php foreach ($section4_questions as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="comments-section">
                    <h2>5. Comments</h2>
                    <h3>Positive Comments</h3>
                    <textarea name="q5-positive-en" class="required-comment" placeholder="What are the positive aspects about this teacher's performance?"></textarea>
                    
                    <h3>Negative Comments / Areas for Improvement</h3>
                    <textarea name="q5-negative-en" class="required-comment" placeholder="What areas could this teacher improve on?"></textarea>
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
                        <?php foreach ($section1_tagalog as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>-t">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_t";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        <?php foreach ($section2_tagalog as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>-t">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_t";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        <?php foreach ($section3_tagalog as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>-t">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_t";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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
                        <?php foreach ($section4_tagalog as $key => $question): ?>
                        <tr id="question-<?php echo str_replace('.', '-', $key); ?>-t">
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_t";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" class="required-rating"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="comments-section">
                    <h2>5. Komento</h2>
                    <h3>Positibong Komento</h3>
                    <textarea name="q5-positive-tl" class="required-comment" placeholder="Ano ang mga positibong aspeto tungkol sa pagganap ng guro na ito?"></textarea>
                    
                    <h3>Negatibong Komento / Mga Lugar na Pagbubutihin</h3>
                    <textarea name="q5-negative-tl" class="required-comment" placeholder="Anong mga lugar ang maaaring pagbutihin ng guro na ito?"></textarea>
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
                // Sync radio buttons
                const englishRadios = form.querySelectorAll('input[type="radio"]:not([name*="_t"])');
                englishRadios.forEach(radio => {
                    if (radio.checked) {
                        const tagalogName = radio.name + '_t';
                        const tagalogRadio = form.querySelector(`input[name="${tagalogName}"][value="${radio.value}"]`);
                        if (tagalogRadio) tagalogRadio.checked = true;
                    }
                });
                
                // Sync comments
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                
                if (positiveEn && positiveTl) positiveTl.value = positiveEn.value;
                if (negativeEn && negativeTl) negativeTl.value = negativeEn.value;
            }

            // Helper: sync radio selection and comments from Tagalog to English
            function syncTagalogToEnglish() {
                // Sync radio buttons
                const tagalogRadios = form.querySelectorAll('input[type="radio"][name*="_t"]');
                tagalogRadios.forEach(radio => {
                    if (radio.checked) {
                        const englishName = radio.name.replace('_t', '');
                        const englishRadio = form.querySelector(`input[name="${englishName}"][value="${radio.value}"]`);
                        if (englishRadio) englishRadio.checked = true;
                    }
                });
                
                // Sync comments
                const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                
                if (positiveTl && positiveEn) positiveEn.value = positiveTl.value;
                if (negativeTl && negativeEn) negativeEn.value = negativeTl.value;
            }

            // Language toggle
            if (englishBtn) {
                englishBtn.addEventListener('click', () => {
                    syncTagalogToEnglish();
                    englishContent.forEach(el => el.style.display = 'block');
                    tagalogContent.forEach(el => el.style.display = 'none');
                    englishBtn.classList.add('active');
                    tagalogBtn.classList.remove('active');
                    updateProgress();
                });
            }

            if (tagalogBtn) {
                tagalogBtn.addEventListener('click', () => {
                    syncEnglishToTagalog();
                    englishContent.forEach(el => el.style.display = 'none');
                    tagalogContent.forEach(el => el.style.display = 'block');
                    tagalogBtn.classList.add('active');
                    englishBtn.classList.remove('active');
                    updateProgress();
                });
            }

            // Initialize display
            englishContent.forEach(el => el.style.display = 'block');
            tagalogContent.forEach(el => el.style.display = 'none');

            // Update progress bar
            function updateProgress() {
                if (!form) return;
                
                // Get all unique radio group names (English only, since we sync them)
                const radioNames = new Set();
                const radioInputs = form.querySelectorAll('input[type="radio"]:not([name*="_t"])');
                radioInputs.forEach(input => radioNames.add(input.name));
                
                const totalRatings = radioNames.size;
                let answeredRatings = 0;
                
                // Check each radio group
                radioNames.forEach(name => {
                    const checked = form.querySelector(`input[name="${name}"]:checked`);
                    if (checked) answeredRatings++;
                });

                // Comments: check if both positive and negative are filled
                const totalComments = 2;
                let answeredComments = 0;
                
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                
                if (positiveEn && positiveEn.value.trim()) answeredComments++;
                if (negativeEn && negativeEn.value.trim()) answeredComments++;

                const progress = ((answeredRatings / totalRatings + answeredComments / totalComments) / 2) * 100;
                
                if (progressBar) progressBar.style.width = progress + '%';
                if (progressText) progressText.textContent = `Completion: ${Math.round(progress)}%`;
                if (submitBtn) submitBtn.disabled = progress < 100;
            }

            // Listen to changes
            if (form) {
                form.addEventListener('change', updateProgress);
                form.addEventListener('input', updateProgress);

                // Form submit validation
                form.addEventListener('submit', (e) => {
                    let valid = true;
                    const errors = [];

                    // Check all radio groups
                    const radioNames = new Set();
                    const radioInputs = form.querySelectorAll('input[type="radio"]:not([name*="_t"])');
                    radioInputs.forEach(input => radioNames.add(input.name));

                    radioNames.forEach(name => {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (!checked) {
                            valid = false;
                            errors.push(`Please provide a rating for question ${name}`);
                        }
                    });

                    // Check comments
                    const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                    const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                    
                    if (!positiveEn || !positiveEn.value.trim()) {
                        valid = false;
                        errors.push('Please provide positive comments');
                        if (positiveEn) positiveEn.style.border = '2px solid red';
                    } else if (positiveEn) {
                        positiveEn.style.border = '';
                    }
                    
                    if (!negativeEn || !negativeEn.value.trim()) {
                        valid = false;
                        errors.push('Please provide areas for improvement');
                        if (negativeEn) negativeEn.style.border = '2px solid red';
                    } else if (negativeEn) {
                        negativeEn.style.border = '';
                    }

                    if (!valid) {
                        e.preventDefault();
                        alert('Please complete all required fields:\n\n' + errors.join('\n'));
                        return false;
                    }
                });
            }

            // Initial progress update
            updateProgress();
        });

        // Set progress to 100% in view mode
        const isViewMode = <?php echo $is_view_mode ? 'true' : 'false'; ?>;
        if (isViewMode) {
            document.getElementById('progress-bar').style.width = '100%';
            document.getElementById('progress-text').textContent = 'Completion: 100%';
        }
    </script>
</body>
</html>
