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
    // Get teacher information with proper department/program mapping
    // Check if student is college level to get correct teacher program
    $student_level = $_SESSION['program'] ?? '';
    
    // Modified query to get correct teacher program based on student level
    if (strpos(strtolower($student_level), 'bs') !== false || strpos(strtolower($student_level), 'bachelor') !== false) {
        // College student - get teacher's college program, not SHS program
        $teacher_stmt = query("
            SELECT t.id, t.name, 
                   COALESCE(t.college_program, t.program, 'Not Specified') as program,
                   t.department
            FROM teachers t 
            WHERE t.id = ?", [$teacher_id]);
    } else {
        // SHS student - get regular program
        $teacher_stmt = query("SELECT id, name, program, department FROM teachers WHERE id = ?", [$teacher_id]);
    }
    
    $teacher_info = fetch_assoc($teacher_stmt);

    if (!$teacher_info) {
        throw new Exception("Teacher not found.");
    }

    // Ensure we have non-null values for display
    $teacher_info['name'] = $teacher_info['name'] ?? 'Unknown Teacher';
    $teacher_info['program'] = $teacher_info['program'] ?? 'Not Specified';
    $teacher_info['department'] = $teacher_info['department'] ?? 'Not Specified';

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
        $insert_sql = "INSERT INTO evaluations (user_id, student_id, student_name, section, program, teacher_id, 
                      q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
                      q2_1, q2_2, q2_3, q2_4,
                      q3_1, q3_2, q3_3, q3_4,
                      q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
                      comments) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

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

// Helper function to safely display values
function safe_display($value, $default = 'Not Available') {
    return !empty($value) ? htmlspecialchars($value) : $default;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher's Performance Evaluation - <?php echo safe_display($teacher_info['name'] ?? ''); ?></title>
    <!-- Rest of your HTML head content remains the same -->
</head>
<body>
    <!-- Your existing styles remain the same -->
    <style>
        /* Your existing CSS styles remain unchanged */
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

        /* Add all your other existing CSS styles here - they remain the same */
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

        /* Add the rest of your CSS styles - they remain unchanged */
    </style>

    <div class="container">
        <header>
            <div class="institution-name">PHILTECH GMA</div>
            <div class="institution-name">PHILIPPINE TECHNOLOGICAL INSTITUTE OF SCIENCE ARTS AND TRADE CENTRAL INC.</div>
            <div class="address">2nd Floor CRDM BLDG. Governor's Drive Brgy G. Maderan GMA, Cavite</div>

            <h1><?php echo $is_view_mode ? 'View Evaluation' : 'TEACHER\'S PERFORMANCE EVALUATION BY THE STUDENTS'; ?></h1>

            <div style="background: linear-gradient(135deg, #fff9e6 0%, #f9eeca 100%); padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #DAA520;">
                <h3 style="color: #800000; margin-bottom: 10px;">👨‍🏫 Evaluating: <?php echo safe_display($teacher_info['name'] ?? ''); ?></h3>
                <p style="margin: 5px 0;"><strong>Program:</strong> <?php echo safe_display($teacher_info['program'] ?? ''); ?></p>
                <p style="margin: 5px 0;"><strong>Department:</strong> <?php echo safe_display($teacher_info['department'] ?? ''); ?></p>
                <p style="margin: 5px 0;"><strong>Student:</strong> <?php echo safe_display($_SESSION['full_name'] ?? ''); ?> (<?php echo safe_display($_SESSION['student_id'] ?? ''); ?>)</p>
                <p style="margin: 5px 0;"><strong>Student Program:</strong> <?php echo safe_display($_SESSION['program'] ?? ''); ?></p>
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
                        <span><?php echo safe_display($teacher_info['name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Program/Subject:</label>
                        <span><?php echo safe_display($teacher_info['program']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Department:</label>
                        <span><?php echo safe_display($teacher_info['department']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Your Section:</label>
                        <span><?php echo safe_display($_SESSION['section'] ?? ''); ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($is_view_mode && $existing_evaluation): ?>
            <div class="evaluation-status">
                <h3>Evaluation Already Submitted</h3>
                <p>You have already evaluated this teacher on <?php echo date('F j, Y \a\t g:i A', strtotime($existing_evaluation['evaluation_date'] ?? 'now')); ?>.</p>
            </div>
        <?php endif; ?>

        <!-- Rest of your HTML content remains the same, just replace htmlspecialchars() calls with safe_display() -->
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <h3>✅ Success!</h3>
                <p><?php echo safe_display($success); ?></p>
                <p style="margin-top: 15px;"><a href="student_dashboard.php" style="background: #DAA520; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Dashboard</a></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div style="background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); color: #721c24; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid #dc3545;">
                <h3>❌ Error</h3>
                <p><?php echo safe_display($error); ?></p>
            </div>
        <?php endif; ?>

        <!-- Continue with the rest of your form HTML... -->
        
    </div>

    <!-- Your JavaScript remains the same -->
    <script>
        // Your existing JavaScript code remains unchanged
        document.addEventListener('DOMContentLoaded', () => {
            // ... your existing JavaScript code
        });
    </script>
</body>
</html>
