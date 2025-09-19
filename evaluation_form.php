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
        }
        
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
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
            transition: all 0.3s ease;
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
        
        .instructions {
            background-color: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin-bottom: 25px;
            border-radius: 0 5px 5px 0;
        }
        
        .rating-scale {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(to right, #e74c3c, #f39c12, #f1c40f, #27ae60, #2ecc71);
            border-radius: 8px;
            color: white;
            font-weight: bold;
        }
        
        .rating-item {
            text-align: center;
            flex: 1;
            padding: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }
        
        th {
            background-color: #f2f6fc;
            font-weight: 600;
            color: #2c3e50;
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
            padding: 8px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .rating-options label:hover {
            background-color: #e8f5e8;
            transform: translateY(-1px);
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
        
        input[type="radio"] {
            transform: scale(1.2);
            margin: 8px 0;
        }
        
        .comments-section {
            margin-top: 30px;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #6c757d;
        }
        
        textarea {
            width: 100%;
            height: 120px;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            resize: vertical;
            font-size: 16px;
            font-family: inherit;
            transition: border-color 0.3s ease;
        }
        
        textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
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
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #2980b9 0%, #3498db 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #6c757d 100%);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.4);
        }
        
        .button-group {
            text-align: center;
            margin-top: 30px;
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
            z-index: 1000;
        }
        
        .back-link:hover {
            background: #1976D2;
            transform: translateY(-2px);
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
        
        footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .tagalog {
            display: none;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 15px;
            }
            
            .rating-scale {
                flex-direction: column;
                gap: 5px;
            }
            
            .rating-options {
                flex-direction: column;
            }
            
            .rating-options label {
                width: 100%;
                text-align: left;
                margin-bottom: 5px;
            }
            
            .back-link {
                position: relative;
                display: block;
                width: fit-content;
                margin: 10px auto;
            }
            
            th, td {
                padding: 8px 5px;
                font-size: 14px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                margin: 5px 0;
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
            
            <h1><?php echo $is_view_mode ? 'View Evaluation' : 'TEACHER\'S PERFORMANCE EVALUATION BY THE STUDENTS'; ?></h1>
            
            <div class="language-toggle">
                <button id="english-btn" class="active">English</button>
                <button id="tagalog-btn">Tagalog</button>
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
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!$is_view_mode): ?>
            <div class="instructions english">
                <p><strong>Instructions:</strong> The following items describe the aspects of teacher's behavior in and out the classroom. Please choose the number that indicates the degree to which you feel each item is descriptive of him/her. Your rating will be the reference that may lead to the improvement of instructor, so kindly rate each item as thoughtfully and carefully as possible. This will be kept confidentially.</p>
            </div>
            
            <div class="instructions tagalog">
                <p><strong>Mga Panuto:</strong> Ang mga sumusunod na aytem ay naglalarawan sa mga aspeto ng pag-uugali ng guro sa loob at labas ng silid-aralan. Paki piliin ang numero na nagpapakita ng antas kung saan naramdaman mo ang bawat aytem na naglalarawan sa kanya. Ang iyong rating ay magiging sanggunian na maaaring humantong sa pagpapabuti ng tagapagturo, kaya mangyaring i-rate ang bawat aytem nang maingat at maayos. Ito ay itatago nang kumpidensyal.</p>
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
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <?php endif; ?>
        
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
                    <?php foreach ($section1_questions as $key => $question): 
                        $field_name = 'rating1_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q1_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
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
                    <?php foreach ($section2_questions as $key => $question): 
                        $field_name = 'rating2_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q2_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
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
                    <?php foreach ($section3_questions as $key => $question): 
                        $field_name = 'rating3_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q3_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
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
                    <?php foreach ($section4_questions as $key => $question): 
                        $field_name = 'rating4_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q4_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                    <?php foreach ($section1_tagalog as $key => $question): 
                        $field_name = 'rating1_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q1_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>2. Kasanayan sa Pamamahala</h2>
            <table>
                <thead>
                    <tr>
                        <th width="70%">Pahayag</th>
                        <th width="30%">Marka</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($section2_tagalog as $key => $question): 
                        $field_name = 'rating2_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q2_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>3. Kasanayan sa Paggabay</h2>
            <table>
                <thead>
                    <tr>
                        <th width="70%">Pahayag</th>
                        <th width="30%">Marka</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($section3_tagalog as $key => $question): 
                        $field_name = 'rating3_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q3_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <h2>4. Personal at Panlipunang Katangian/Kasanayan</h2>
            <table>
                <thead>
                    <tr>
                        <th width="70%">Pahayag</th>
                        <th width="30%">Marka</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($section4_tagalog as $key => $question): 
                        $field_name = 'rating4_' . substr($key, -1);
                        $current_value = $is_view_mode ? $existing_evaluation['q4_' . substr($key, -1)] : '';
                    ?>
                        <tr>
                            <td><?php echo $key; ?> <?php echo $question; ?></td>
                            <td>
                                <?php if ($is_view_mode): ?>
                                    <div class="rating-display"><?php echo $current_value; ?></div>
                                <?php else: ?>
                                    <div class="rating-options">
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="5" required> 5</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="4"> 4</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="3"> 3</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="2"> 2</label>
                                        <label><input type="radio" name="<?php echo $field_name; ?>" value="1"> 1</label>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($is_view_mode && $existing_evaluation): ?>
            <div class="comments-section">
                <h3>Your Comments</h3>
                <div class="comments-display">
                    <?php echo !empty($existing_evaluation['comments']) ? nl2br(htmlspecialchars($existing_evaluation['comments'])) : 'No comments provided.'; ?>
                </div>
            </div>
            
            <div class="average-rating">
                <h4>Overall Performance Rating</h4>
                <div class="average-score"><?php echo $average_rating; ?></div>
                <div class="performance-level"><?php echo $performance_level; ?></div>
            </div>
        <?php else: ?>
            <div class="comments-section">
                <h3>Comments/Suggestions (Optional)</h3>
                <textarea name="comments" placeholder="Please provide any additional comments or suggestions for improvement..."></textarea>
            </div>
        <?php endif; ?>
        
        <?php if (!$is_view_mode): ?>
            </form>
        <?php endif; ?>
        
        <div class="button-group">
            <?php if (!$is_view_mode): ?>
                <button type="submit" form="evaluationForm" class="btn">Submit Evaluation</button>
            <?php endif; ?>
            <a href="student_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        
        <footer>
            <p>© <?php echo date('Y'); ?> PHILTECH GMA - All rights reserved</p>
            <p>This evaluation system is designed to improve teaching quality and student learning experience.</p>
        </footer>
    </div>
    
    <script>
        // Language toggle functionality
        document.getElementById('english-btn').addEventListener('click', function() {
            document.querySelectorAll('.english').forEach(el => el.style.display = 'block');
            document.querySelectorAll('.tagalog').forEach(el => el.style.display = 'none');
            document.getElementById('english-btn').classList.add('active');
            document.getElementById('tagalog-btn').classList.remove('active');
        });
        
        document.getElementById('tagalog-btn').addEventListener('click', function() {
            document.querySelectorAll('.english').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tagalog').forEach(el => el.style.display = 'block');
            document.getElementById('english-btn').classList.remove('active');
            document.getElementById('tagalog-btn').classList.add('active');
        });
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('evaluationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    const radioGroups = {};
                    
                    // Collect all radio buttons
                    document.querySelectorAll('input[type="radio"]').forEach(radio => {
                        if (!radioGroups[radio.name]) {
                            radioGroups[radio.name] = [];
                        }
                        radioGroups[radio.name].push(radio);
                    });
                    
                    // Check if all required radio groups have a selection
                    for (const groupName in radioGroups) {
                        const groupSelected = radioGroups[groupName].some(radio => radio.checked);
                        if (!groupSelected) {
                            valid = false;
                            // Highlight the first unselected group
                            if (!radioGroups[groupName][0].closest('tr').classList.contains('missing')) {
                                radioGroups[groupName][0].closest('tr').classList.add('missing');
                                setTimeout(() => {
                                    radioGroups[groupName][0].closest('tr').scrollIntoView({behavior: 'smooth', block: 'center'});
                                }, 100);
                            }
                            break;
                        }
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Please complete all rating fields before submitting.');
                    }
                });
            }
        });
    </script>
</body>
</html>
