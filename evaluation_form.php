<?php
// evaluation_form.php - Updated version with separate positive/negative comments
session_start();

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'student') {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once 'includes/db_connection.php';

$success = '';
$error = '';
$teacher_info = null;
$is_view_mode = false;
$existing_evaluation = null;

// Get teacher name and subject from URL parameters
$teacher_name = isset($_GET['teacher']) ? trim($_GET['teacher']) : '';

// Validate parameters
if (empty($teacher_name)) {
    $error = "Missing teacher information. Please select a teacher from your dashboard.";
} else {
    try {
        $pdo = getPDO();
        
        // Get student information from session
        $student_username = $_SESSION['username'];
        $student_program = $_SESSION['program'] ?? 'COLLEGE';
        $student_section = $_SESSION['section'] ?? '';

        // Verify this teacher assignment exists for this student's section
        $stmt = $pdo->prepare("
            SELECT teacher_name, section, program 
            FROM teacher_assignments 
            WHERE teacher_name = ? AND section = ? AND program = ? AND is_active = true
        ");
        $stmt->execute([$teacher_name, $student_section, $student_program]);
        $teacher_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$teacher_assignment) {
            $error = "This teacher assignment was not found for your section. Please contact your administrator.";
        } else {
            // Create teacher_info structure for compatibility with existing code
            $teacher_info = [
                'name' => $teacher_name,
                'department' => $student_program,
                'display_department' => $student_program === 'COLLEGE' ? 'College Department' : 'Senior High School Department'
            ];

            // Check if student has already evaluated this teacher for this subject
            $check_stmt = $pdo->prepare("
                SELECT * FROM evaluations 
                WHERE student_username = ? AND teacher_name = ? AND section = ?
            ");
            $check_stmt->execute([$student_username, $teacher_name, $student_section]);
            $existing_evaluation = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_evaluation) {
                $is_view_mode = true;
            }
        }

    } catch (Exception $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Simple CSRF token generation
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Handle form submission (only if not in view mode)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_view_mode && $teacher_info) {
    try {
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

        // Get comments with character validation - SEPARATE FIELDS
        $positive_comments = trim($_POST['q5-positive-en'] ?? $_POST['q5-positive-tl'] ?? '');
        $negative_comments = trim($_POST['q5-negative-en'] ?? $_POST['q5-negative-tl'] ?? '');
        
        // Validate comment length
        if (strlen($positive_comments) < 30) {
            throw new Exception("Positive comments must be at least 30 characters long.");
        }
        
        if (strlen($negative_comments) < 30) {
            throw new Exception("Negative comments/areas for improvement must be at least 30 characters long.");
        }

        // Use the updated saveEvaluation function with separate comment fields
        $evaluationData = [
            'student_username' => $_SESSION['username'],
            'student_name' => $_SESSION['full_name'] ?? '',
            'teacher_name' => $teacher_name,
            'section' => $_SESSION['section'] ?? '',
            'program' => $_SESSION['program'] ?? '',
            'q1_1' => $ratings['q1_1'], 'q1_2' => $ratings['q1_2'], 'q1_3' => $ratings['q1_3'],
            'q1_4' => $ratings['q1_4'], 'q1_5' => $ratings['q1_5'], 'q1_6' => $ratings['q1_6'],
            'q2_1' => $ratings['q2_1'], 'q2_2' => $ratings['q2_2'], 'q2_3' => $ratings['q2_3'], 'q2_4' => $ratings['q2_4'],
            'q3_1' => $ratings['q3_1'], 'q3_2' => $ratings['q3_2'], 'q3_3' => $ratings['q3_3'], 'q3_4' => $ratings['q3_4'],
            'q4_1' => $ratings['q4_1'], 'q4_2' => $ratings['q4_2'], 'q4_3' => $ratings['q4_3'],
            'q4_4' => $ratings['q4_4'], 'q4_5' => $ratings['q4_5'], 'q4_6' => $ratings['q4_6'],
            'positive_comments' => $positive_comments,
            'negative_comments' => $negative_comments
        ];

        $result = saveEvaluation($evaluationData);

        if ($result) {
            $success = "Evaluation submitted successfully! Thank you for your feedback.";
            // Reload to show in view mode
            $check_stmt = $pdo->prepare("
                SELECT * FROM evaluations 
                WHERE student_username = ? AND teacher_name = ? AND section = ?
            ");
            $check_stmt->execute([$student_username, $teacher_name, $student_section]);
            $existing_evaluation = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $is_view_mode = true;
        } else {
            throw new Exception("Database error occurred while saving your evaluation.");
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Helper function to safely display values
function safe_display($value, $default = 'Not Available') {
    return !empty($value) && $value !== null ? htmlspecialchars($value) : $default;
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

// Tagalog questions
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

        /* Skeleton Loading Styles */
        #skeleton-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f9f5eb;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease;
        }

        .skeleton-logo {
            width: 300px;
            height: 40px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 5px;
            margin-bottom: 30px;
            animation: loading 1.5s infinite;
        }

        .skeleton-header {
            width: 80%;
            height: 30px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 5px;
            margin-bottom: 15px;
            animation: loading 1.5s infinite;
        }

        .skeleton-subheader {
            width: 60%;
            height: 20px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 5px;
            margin-bottom: 40px;
            animation: loading 1.5s infinite;
        }

        .skeleton-card {
            width: 90%;
            height: 100px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: loading 1.5s infinite;
        }

        .skeleton-row {
            width: 95%;
            height: 15px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 3px;
            margin-bottom: 10px;
            animation: loading 1.5s infinite;
        }

        .skeleton-row.short {
            width: 70%;
        }

        .skeleton-row.medium {
            width: 85%;
        }

        .skeleton-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f0f0f0;
            border-top: 5px solid #800000;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-top: 20px;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .skeleton-hidden {
            display: none;
        }

        .fade-out {
            opacity: 0;
        }

        /* Rest of your existing styles remain the same */
        header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #800000;
            padding-bottom: 20px;
        }

        h1 {
            color: #800000;
            margin-bottom: 10px;
        }

        h2 {
            color: #800000;
            margin: 25px 0 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #d4af37;
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
            border-left: 5px solid #800000;
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
            background-color: #800000;
            color: white;
        }

        .language-toggle button:hover {
            background-color: #5E0C0C;
            color: white;
        }

        /* MAROON PROGRESS BAR */
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
            background: linear-gradient(90deg, #5E0C0C, #800000, #A52A2A);
            width: 0%;
            transition: width 0.5s ease;
            border-radius: 4px;
        }

        .progress-text {
            text-align: center;
            font-weight: bold;
            color: #800000;
            margin-bottom: 5px;
        }

        .instructions {
            background-color: #f9f5eb;
            padding: 15px;
            border-left: 4px solid #800000;
            margin-bottom: 25px;
            border-radius: 0 5px 5px 0;
        }

        .rating-scale {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(to right, #5E0C0C, #800000, #A52A2A, #DAA520, #f1dca0);
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

        /* UPDATED RADIO BUTTONS - Numbers beside radio buttons */
        .rating-options {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }

        .rating-options label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 18%;
            cursor: pointer;
            color: #5E0C0C;
            position: relative;
            padding: 5px 0;
            transition: all 0.3s ease;
            border-radius: 4px;
            font-size: 14px; /* Smaller font size */
        }

        .rating-options label:hover {
            background-color: #f9f5eb;
        }

        /* Hide default radio buttons */
        .rating-options input[type="radio"] {
            display: none;
        }

        /* Custom radio buttons - smaller size */
        .rating-options label::before {
            content: '';
            display: inline-block;
            width: 16px; /* Smaller size */
            height: 16px; /* Smaller size */
            border: 2px solid #800000;
            border-radius: 50%;
            background-color: white;
            margin-right: 8px; /* Space between radio and number */
            transition: all 0.3s ease;
        }

        /* Checked state */
        .rating-options input[type="radio"]:checked + label::before {
            background-color: #800000;
            box-shadow: inset 0 0 0 3px white;
        }

        /* Selected label style */
        .rating-options input[type="radio"]:checked + label {
            color: #800000;
            font-weight: bold;
            background-color: #f9f5eb;
        }

        /* Focus state for accessibility */
        .rating-options input[type="radio"]:focus + label::before {
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.3);
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
            transition: border 0.3s ease;
        }

        textarea:focus {
            border-color: #800000;
            outline: none;
            box-shadow: 0 0 0 2px rgba(128, 0, 0, 0.1);
        }

        .character-count {
            text-align: right;
            font-size: 14px;
            margin-top: 5px;
            color: #9c6c6c;
        }

        .character-count.error {
            color: #dc3545;
            font-weight: bold;
        }

        .character-count.success {
            color: #28a745;
            font-weight: bold;
        }

        .submit-btn {
            display: block;
            width: 200px;
            margin: 30px auto 10px;
            padding: 12px;
            background: linear-gradient(135deg, #800000, #5E0C0C);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(128, 0, 0, 0.2);
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #5E0C0C, #800000);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(128, 0, 0, 0.3);
        }

        .submit-btn:disabled {
            background: #9c6c6c;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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

        .evaluation-status {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
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

        .back-link {
            background: #800000;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 15px;
            transition: background 0.3s ease;
        }

        .back-link:hover {
            background: #5E0C0C;
        }

        /* View mode styles for separate comments */
        .comments-display {
            margin: 20px 0;
            padding: 15px;
            background: #f9f5eb;
            border-radius: 8px;
        }

        .positive-comments {
            border-left: 4px solid #28a745;
            padding-left: 15px;
            margin-bottom: 20px;
        }

        .negative-comments {
            border-left: 4px solid #dc3545;
            padding-left: 15px;
        }

        .comments-display h3 {
            color: #800000;
            margin-bottom: 10px;
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
                padding: 8px 10px;
                justify-content: flex-start;
            }

            .rating-options label::before {
                margin-right: 10px;
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

            /* Skeleton responsive */
            .skeleton-header {
                width: 90%;
            }
            
            .skeleton-subheader {
                width: 80%;
            }
            
            .skeleton-card {
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <!-- Skeleton Loading Screen -->
    <div id="skeleton-loader">
        <div class="skeleton-logo"></div>
        <div class="skeleton-header"></div>
        <div class="skeleton-subheader"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-row"></div>
        <div class="skeleton-row medium"></div>
        <div class="skeleton-row short"></div>
        <div class="skeleton-row"></div>
        <div class="skeleton-row medium"></div>
        <div class="skeleton-spinner"></div>
    </div>

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
            <div class="teacher-info">
                <h3 style="color: #800000; margin-bottom: 10px;">👨‍🏫 Evaluating: <?php echo safe_display($teacher_info['name']); ?></h3>
                <p style="margin: 5px 0;"><strong>Department:</strong> <?php echo safe_display($teacher_info['display_department']); ?></p>
                <p style="margin: 5px 0;"><strong>Student:</strong> <?php echo safe_display($_SESSION['full_name'] ?? ''); ?> (<?php echo safe_display($_SESSION['student_id'] ?? $_SESSION['username']); ?>)</p>
                <p style="margin: 5px 0;"><strong>Student Program:</strong> <?php echo safe_display($_SESSION['program'] ?? ''); ?></p>
                <p style="margin: 5px 0;"><strong>Student Section:</strong> <?php echo safe_display($_SESSION['section'] ?? ''); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!$is_view_mode): ?>
            <div class="language-toggle">
                <button id="english-btn" class="active">English</button>
                <button id="tagalog-btn">Tagalog</button>
            </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <h3>✅ Success!</h3>
                <p><?php echo safe_display($success); ?></p>
                <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <h3>❌ Error</h3>
                <p><?php echo safe_display($error); ?></p>
                <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if ($is_view_mode && $existing_evaluation): ?>
            <div class="evaluation-status">
                <h3>✅ Evaluation Already Submitted</h3>
                <p>You have already evaluated this teacher on <?php echo date('F j, Y \a\t g:i A', strtotime($existing_evaluation['created_at'] ?? 'now')); ?>.</p>
                <?php if ($average_rating > 0): ?>
                <p>Average Rating: <strong><?php echo $average_rating; ?>/5.0</strong> (<?php echo $performance_level; ?>)</p>
                <?php endif; ?>
                
                <!-- Display separate comments in view mode -->
                <div class="comments-display">
                    <div class="positive-comments">
                        <h3>📝 Positive Comments</h3>
                        <p><?php echo safe_display($existing_evaluation['positive_comments'] ?? 'No positive comments provided.'); ?></p>
                    </div>
                    
                    <div class="negative-comments">
                        <h3>💡 Areas for Improvement</h3>
                        <p><?php echo safe_display($existing_evaluation['negative_comments'] ?? 'No improvement suggestions provided.'); ?></p>
                    </div>
                </div>
                
                <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if (!$is_view_mode && $teacher_info && empty($error)): ?>
        <div class="instructions english">
            <p>Instructions: The following items describe the aspects of teacher's behavior in and out the classroom. Please choose the number that indicates the degree to which you feel each item is descriptive of him/her. Your rating will be the reference that may lead to the improvement of instructor, so kindly rate each item as thoughtfully and carefully as possible. This will be kept confidentially.</p>
            <p style="margin-top: 10px; font-weight: bold; color: #800000;">Note: Positive and negative comments must be at least 30 characters long.</p>
        </div>
        
        <div class="instructions tagalog">
            <p>Mga Panuto: Ang mga sumusunod na aytem ay naglalarawan sa mga aspeto ng pag-uugali ng guro sa loob at labas ng silid-aralan. Paki piliin ang numero na nagpapakita ng antas kung saan naramdaman mo ang bawat aytem na naglalarawan sa kanya. Ang inyong rating ay magiging sanggunian na maaaring humantong sa pagpapabuti ng tagapagturo, kaya mangyaring i-rate ang bawat aytem nang maingat at maayos. Ito ay itatago nang kumpidensyal.</p>
            <p style="margin-top: 10px; font-weight: bold; color: #800000;">Paalala: Ang positibo at negatibong komento ay dapat na hindi bababa sa 30 na karakter ang haba.</p>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>" required>
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>" required>
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>" required>
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>" required>
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                    <textarea name="q5-positive-en" placeholder="What are the positive aspects about this teacher's performance? (Minimum 30 characters)" required></textarea>
                    <div class="character-count" id="positive-en-count">0/30 characters</div>
                    
                    <h3>Negative Comments / Areas for Improvement</h3>
                    <textarea name="q5-negative-en" placeholder="What areas could this teacher improve on? (Minimum 30 characters)" required></textarea>
                    <div class="character-count" id="negative-en-count">0/30 characters</div>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_tl";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>">
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_tl";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>">
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_tl";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>">
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key) . "_tl";
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" id="<?php echo $name.'_'.$i; ?>">
                                        <label for="<?php echo $name.'_'.$i; ?>"><?php echo $i; ?></label>
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
                    <textarea name="q5-positive-tl" placeholder="Ano ang mga positibong aspeto tungkol sa pagganap ng guro na ito? (Hindi bababa sa 30 na karakter)"></textarea>
                    <div class="character-count" id="positive-tl-count">0/30 characters</div>
                    
                    <h3>Negatibong Komento / Mga Lugar na Pagbubutihin</h3>
                    <textarea name="q5-negative-tl" placeholder="Anong mga lugar ang maaaring pagbutihin ng guro na ito? (Hindi bababa sa 30 na karakter)"></textarea>
                    <div class="character-count" id="negative-tl-count">0/30 characters</div>
                </div>
            </div>
            
            <button type="submit" class="submit-btn" id="submit-btn">Submit Evaluation</button>
        </form>
        <?php endif; ?>

        <footer>
            <p>This evaluation will be kept confidential.</p>
            <p>© PHILTECH GMA</p>
        </footer>
    </div>

    <script>
        // Skeleton loading functionality
        document.addEventListener('DOMContentLoaded', () => {
            const skeletonLoader = document.getElementById('skeleton-loader');
            
            // Show skeleton for 3 seconds then fade out
            setTimeout(() => {
                skeletonLoader.classList.add('fade-out');
                
                // Remove skeleton from DOM after fade out completes
                setTimeout(() => {
                    skeletonLoader.style.display = 'none';
                }, 500); // Match the CSS transition duration
            }, 3000); // 3 seconds loading time

            // Rest of your existing JavaScript code...
            const englishBtn = document.getElementById('english-btn');
            const tagalogBtn = document.getElementById('tagalog-btn');
            const englishContent = document.querySelectorAll('.english');
            const tagalogContent = document.querySelectorAll('.tagalog');
            const form = document.getElementById('evaluation-form');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const submitBtn = document.getElementById('submit-btn');

            // Character count elements
            const positiveEnCount = document.getElementById('positive-en-count');
            const negativeEnCount = document.getElementById('negative-en-count');
            const positiveTlCount = document.getElementById('positive-tl-count');
            const negativeTlCount = document.getElementById('negative-tl-count');

            // Helper: sync radio selection and comments from English to Tagalog
            function syncEnglishToTagalog() {
                // Sync radio buttons
                const englishRadios = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
                englishRadios.forEach(radio => {
                    if (radio.checked) {
                        const tagalogName = radio.name + '_tl';
                        const tagalogRadio = form.querySelector(`input[name="${tagalogName}"][value="${radio.value}"]`);
                        if (tagalogRadio) tagalogRadio.checked = true;
                    }
                });
                
                // Sync comments
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                
                if (positiveEn && positiveTl) {
                    positiveTl.value = positiveEn.value;
                    updateCharacterCount(positiveTl, positiveTlCount);
                }
                if (negativeEn && negativeTl) {
                    negativeTl.value = negativeEn.value;
                    updateCharacterCount(negativeTl, negativeTlCount);
                }
            }

            // Helper: sync radio selection and comments from Tagalog to English
            function syncTagalogToEnglish() {
                // Sync radio buttons
                const tagalogRadios = form.querySelectorAll('input[type="radio"][name*="_tl"]');
                tagalogRadios.forEach(radio => {
                    if (radio.checked) {
                        const englishName = radio.name.replace('_tl', '');
                        const englishRadio = form.querySelector(`input[name="${englishName}"][value="${radio.value}"]`);
                        if (englishRadio) englishRadio.checked = true;
                    }
                });
                
                // Sync comments
                const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                
                if (positiveTl && positiveEn) {
                    positiveEn.value = positiveTl.value;
                    updateCharacterCount(positiveEn, positiveEnCount);
                }
                if (negativeTl && negativeEn) {
                    negativeEn.value = negativeTl.value;
                    updateCharacterCount(negativeEn, negativeEnCount);
                }
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
            if (englishContent.length > 0) {
                englishContent.forEach(el => el.style.display = 'block');
            }
            if (tagalogContent.length > 0) {
                tagalogContent.forEach(el => el.style.display = 'none');
            }

            // Update character count
            function updateCharacterCount(textarea, countElement) {
                const length = textarea.value.length;
                countElement.textContent = `${length}/30 characters`;
                
                if (length < 30) {
                    countElement.classList.remove('success');
                    countElement.classList.add('error');
                } else {
                    countElement.classList.remove('error');
                    countElement.classList.add('success');
                }
            }

            // Initialize character counts
            const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
            const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
            const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
            const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
            
            if (positiveEn) {
                updateCharacterCount(positiveEn, positiveEnCount);
                positiveEn.addEventListener('input', () => {
                    updateCharacterCount(positiveEn, positiveEnCount);
                    updateProgress();
                });
            }
            
            if (negativeEn) {
                updateCharacterCount(negativeEn, negativeEnCount);
                negativeEn.addEventListener('input', () => {
                    updateCharacterCount(negativeEn, negativeEnCount);
                    updateProgress();
                });
            }
            
            if (positiveTl) {
                updateCharacterCount(positiveTl, positiveTlCount);
                positiveTl.addEventListener('input', () => {
                    updateCharacterCount(positiveTl, positiveTlCount);
                    updateProgress();
                });
            }
            
            if (negativeTl) {
                updateCharacterCount(negativeTl, negativeTlCount);
                negativeTl.addEventListener('input', () => {
                    updateCharacterCount(negativeTl, negativeTlCount);
                    updateProgress();
                });
            }

            // Update progress bar - FIXED VERSION
            function updateProgress() {
                if (!form) return;
                
                let totalFields = 0;
                let completedFields = 0;

                // Check which language is active
                const isEnglishActive = englishBtn && englishBtn.classList.contains('active');
                
                if (isEnglishActive) {
                    // Check English radio buttons
                    const englishRadios = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
                    const englishRadioGroups = new Set();
                    englishRadios.forEach(radio => englishRadioGroups.add(radio.name));
                    
                    totalFields += englishRadioGroups.size;
                    englishRadioGroups.forEach(name => {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (checked) completedFields++;
                    });

                    // Check English comments
                    const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                    const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                    
                    totalFields += 2;
                    if (positiveEn && positiveEn.value.trim().length >= 30) completedFields++;
                    if (negativeEn && negativeEn.value.trim().length >= 30) completedFields++;
                } else {
                    // Check Tagalog radio buttons
                    const tagalogRadios = form.querySelectorAll('input[type="radio"][name*="_tl"]');
                    const tagalogRadioGroups = new Set();
                    tagalogRadios.forEach(radio => tagalogRadioGroups.add(radio.name));
                    
                    totalFields += tagalogRadioGroups.size;
                    tagalogRadioGroups.forEach(name => {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (checked) completedFields++;
                    });

                    // Check Tagalog comments
                    const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                    const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                    
                    totalFields += 2;
                    if (positiveTl && positiveTl.value.trim().length >= 30) completedFields++;
                    if (negativeTl && negativeTl.value.trim().length >= 30) completedFields++;
                }

                const progress = totalFields > 0 ? Math.round((completedFields / totalFields) * 100) : 0;
                
                if (progressBar) progressBar.style.width = progress + '%';
                if (progressText) progressText.textContent = `Completion: ${progress}%`;
                if (submitBtn) submitBtn.disabled = progress < 100;
            }

            // Real-time sync for Tagalog radio buttons to English ones
            function setupRealTimeSync() {
                if (!form) return;

                // When Tagalog radio is clicked, sync to English
                form.addEventListener('change', (e) => {
                    if (e.target.type === 'radio' && e.target.name.includes('_tl')) {
                        const englishName = e.target.name.replace('_tl', '');
                        const englishRadio = form.querySelector(`input[name="${englishName}"][value="${e.target.value}"]`);
                        if (englishRadio) {
                            englishRadio.checked = true;
                        }
                        updateProgress();
                    }
                });

                // When Tagalog textarea is typed, sync to English
                form.addEventListener('input', (e) => {
                    if (e.target.name === 'q5-positive-tl') {
                        const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                        if (positiveEn) positiveEn.value = e.target.value;
                        updateProgress();
                    } else if (e.target.name === 'q5-negative-tl') {
                        const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                        if (negativeEn) negativeEn.value = e.target.value;
                        updateProgress();
                    }
                });

                // When English textarea is typed, update progress
                form.addEventListener('input', (e) => {
                    if (e.target.name === 'q5-positive-en' || e.target.name === 'q5-negative-en') {
                        updateProgress();
                    }
                });
            }

            // Listen to changes in both languages
            if (form) {
                setupRealTimeSync();
                
                form.addEventListener('change', updateProgress);
                form.addEventListener('input', updateProgress);

                // Form submit validation
                form.addEventListener('submit', (e) => {
                    let valid = true;
                    const errors = [];

                    // Check all radio groups (English version for submission)
                    const radioNames = new Set();
                    const radioInputs = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
                    radioInputs.forEach(input => radioNames.add(input.name));

                    radioNames.forEach(name => {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (!checked) {
                            valid = false;
                            errors.push(`Please provide a rating for question ${name}`);
                        }
                    });

                    // Check comments (English version for submission)
                    const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                    const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                    
                    if (!positiveEn || positiveEn.value.trim().length < 30) {
                        valid = false;
                        errors.push('Positive comments must be at least 30 characters long');
                        if (positiveEn) positiveEn.style.border = '2px solid red';
                    } else if (positiveEn) {
                        positiveEn.style.border = '';
                    }
                    
                    if (!negativeEn || negativeEn.value.trim().length < 30) {
                        valid = false;
                        errors.push('Negative comments/areas for improvement must be at least 30 characters long');
                        if (negativeEn) negativeEn.style.border = '2px solid red';
                    } else if (negativeEn) {
                        negativeEn.style.border = '';
                    }

                    if (!valid) {
                        e.preventDefault();
                        alert('Please complete all required fields:\n\n' + errors.join('\n'));
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.textContent = 'Submitting...';
                    submitBtn.disabled = true;
                });
            }

            // Initial progress update
            setTimeout(updateProgress, 100);
        });

        // Set progress to 100% in view mode
        const isViewMode = <?php echo $is_view_mode ? 'true' : 'false'; ?>;
        if (isViewMode) {
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            if (progressBar) progressBar.style.width = '100%';
            if (progressText) progressText.textContent = 'Completion: 100%';
        }
    </script>
</body>
</html>
