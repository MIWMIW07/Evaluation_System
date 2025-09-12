<?php
// Start session to track if a student has already submitted an evaluation
session_start();

// Include database connection
require_once 'db_connection.php';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Collect and sanitize form data
        $student_id = trim($_POST['student_id']);
        $student_name = trim($_POST['student_name']);
        $section = trim($_POST['section']);
        $program = trim($_POST['program']);
        $teacher_id = intval($_POST['teacher']);
        $subject = trim($_POST['subject']);
        
        // Validate required fields
        if (empty($student_id) || empty($student_name) || empty($section) || 
            empty($program) || empty($teacher_id) || empty($subject)) {
            throw new Exception("All fields are required.");
        }
        
        // Initialize an array to store all question ratings
        $ratings = [];
        
        // Section 1: Teaching Competence (6 questions)
        for ($i = 1; $i <= 6; $i++) {
            $rating = intval($_POST["q1_$i"]);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 1.$i");
            }
            $ratings["q1_$i"] = $rating;
        }
        
        // Section 2: Management Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["q2_$i"]);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 2.$i");
            }
            $ratings["q2_$i"] = $rating;
        }
        
        // Section 3: Guidance Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["q3_$i"]);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 3.$i");
            }
            $ratings["q3_$i"] = $rating;
        }
        
        // Section 4: Personal and Social Characteristics (6 questions)
        for ($i = 1; $i <= 6; $i++) {
            $rating = intval($_POST["q4_$i"]);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 4.$i");
            }
            $ratings["q4_$i"] = $rating;
        }
        
        $comments = trim($_POST['comments'] ?? '');
        
        // Check if student has already evaluated this teacher
        $check_stmt = $conn->prepare("SELECT id FROM evaluations WHERE student_id = ? AND teacher_id = ?");
        $check_stmt->bind_param("si", $student_id, $teacher_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows > 0) {
            throw new Exception("You have already evaluated this teacher.");
        }
        
        // Insert evaluation
        $stmt = $conn->prepare("INSERT INTO evaluations (student_id, student_name, section, program, teacher_id, subject, 
                                q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
                                q2_1, q2_2, q2_3, q2_4,
                                q3_1, q3_2, q3_3, q3_4,
                                q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
                                comments) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("ssssisiiiiiiiiiiiiiiiiiiis", 
            $student_id, $student_name, $section, $program, $teacher_id, $subject,
            $ratings['q1_1'], $ratings['q1_2'], $ratings['q1_3'], $ratings['q1_4'], $ratings['q1_5'], $ratings['q1_6'],
            $ratings['q2_1'], $ratings['q2_2'], $ratings['q2_3'], $ratings['q2_4'],
            $ratings['q3_1'], $ratings['q3_2'], $ratings['q3_3'], $ratings['q3_4'],
            $ratings['q4_1'], $ratings['q4_2'], $ratings['q4_3'], $ratings['q4_4'], $ratings['q4_5'], $ratings['q4_6'],
            $comments);
        
        if ($stmt->execute()) {
            $success = "✅ Evaluation submitted successfully! Thank you for your feedback.";
            // Clear form data after successful submission
            $_POST = [];
        } else {
            throw new Exception("Database error occurred while saving your evaluation.");
        }
        
        $stmt->close();
        $check_stmt->close();
        
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}

// Fetch teachers for dropdown
$teachers_sql = "SELECT id, name, subject FROM teachers ORDER BY name";
$teachers_result = $conn->query($teachers_sql);

if (!$teachers_result) {
    $error = "❌ Could not load teachers list. Please contact administrator.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Evaluation System</title>
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
        
        header h2 {
            color: #4CAF50;
            font-size: 1.5em;
        }
        
        .instruction-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
            border-left: 6px solid #2196F3;
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
        
        .evaluation-form {
            margin-top: 20px;
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
        
        .question:last-child {
            border-bottom: none;
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
        
        textarea {
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
        
        textarea:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.3);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-group select:focus, .form-group input:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.3);
        }
        
        .submit-btn {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            display: block;
            margin: 30px auto;
            width: 250px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .submit-btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .required {
            color: #e74c3c;
        }
        
        footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            color: #7f8c8d;
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
        
        .admin-link {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2196F3;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
        }
        
        .admin-link:hover {
            background: #1976D2;
            transform: translateY(-2px);
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
            
            .admin-link {
                position: relative;
                display: block;
                width: fit-content;
                margin: 10px auto;
            }
        }
    </style>
</head>
<body>
    <a href="admin.php" class="admin-link">📊 Admin Dashboard</a>
    
    <div class="container">
        <header>
            <h1>Philippine Technological Institute of Science Arts and Trade, Inc.</h1>
            <p>GMA-BRANCH (2nd Semester 2024-2025)</p>
            <h2>Teacher Evaluation System</h2>
        </header>
        
        <div class="instruction-box">
            <p><strong>📋 Directions:</strong> The following items describe aspects of the teacher's characteristics inside and outside the classroom. 
            Choose the appropriate number that fits your observation. Your score will help the teacher further develop their dedication to the field of teaching.</p>
        </div>
        
        <div class="rating-scale">
            <div class="scale-item"><span>5</span> Outstanding</div>
            <div class="scale-item"><span>4</span> Very Satisfactory</div>
            <div class="scale-item"><span>3</span> Good/Satisfactory</div>
            <div class="scale-item"><span>2</span> Fair</div>
            <div class="scale-item"><span>1</span> Unsatisfactory</div>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="evaluation-form">
            <div class="form-group">
                <label for="student_id">Student ID <span class="required">*</span></label>
                <input type="text" id="student_id" name="student_id" required 
                       value="<?php echo htmlspecialchars($_POST['student_id'] ?? ''); ?>"
                       placeholder="Enter your student ID">
            </div>
            
            <div class="form-group">
                <label for="student_name">Student Name <span class="required">*</span></label>
                <input type="text" id="student_name" name="student_name" required 
                       value="<?php echo htmlspecialchars($_POST['student_name'] ?? ''); ?>"
                       placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label for="section">Section <span class="required">*</span></label>
                <input type="text" id="section" name="section" required 
                       value="<?php echo htmlspecialchars($_POST['section'] ?? ''); ?>"
                       placeholder="Enter your section (e.g., A, B, C)">
            </div>
            
            <div class="form-group">
                <label for="program">Program/Strand <span class="required">*</span></label>
                <select id="program" name="program" required>
                    <option value="">Select Program/Strand</option>
                    <?php 
                    $programs = [
                        'STEM' => 'STEM (Science, Technology, Engineering, and Mathematics)',
                        'HUMSS' => 'HUMSS (Humanities and Social Sciences)',
                        'ABM' => 'ABM (Accountancy, Business, and Management)',
                        'GAS' => 'GAS (General Academic Strand)',
                        'TVL' => 'TVL (Technical-Vocational-Livelihood)',
                        'BSIT' => 'BS Information Technology',
                        'BSCS' => 'BS Computer Science',
                        'BSBA' => 'BS Business Administration',
                        'BSE' => 'BS Education',
                        'BSHM' => 'BS Hospitality Management'
                    ];
                    
                    foreach ($programs as $key => $value) {
                        $selected = (isset($_POST['program']) && $_POST['program'] === $key) ? 'selected' : '';
                        echo "<option value='$key' $selected>$value</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="teacher">Teacher <span class="required">*</span></label>
                <select id="teacher" name="teacher" required>
                    <option value="">Select Teacher</option>
                    <?php if ($teachers_result && $teachers_result->num_rows > 0): ?>
                        <?php while($row = $teachers_result->fetch_assoc()): ?>
                            <?php 
                            $selected = (isset($_POST['teacher']) && $_POST['teacher'] == $row['id']) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $row['id']; ?>" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($row['name']) . ' - ' . htmlspecialchars($row['subject']); ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject <span class="required">*</span></label>
                <input type="text" id="subject" name="subject" required 
                       value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>"
                       placeholder="Enter the subject name">
            </div>
            
            <div class="form-section">
                <h3 class="section-title">1. Teaching Competence</h3>
                
                <div class="question">
                    <p class="question-text">1.1 Analyzes and explains lessons without reading from the book in class</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q1_1" value="1" required> 1</label>
                        <label><input type="radio" name="q1_1" value="2"> 2</label>
                        <label><input type="radio" name="q1_1" value="3"> 3</label>
                        <label><input type="radio" name="q1_1" value="4"> 4</label>
                        <label><input type="radio" name="q1_1" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">1.2 Uses audio-visual and devices to support and facilitate teaching</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q1_2" value="1" required> 1</label>
                        <label><input type="radio" name="q1_2" value="2"> 2</label>
                        <label><input type="radio" name="q1_2" value="3"> 3</label>
                        <label><input type="radio" name="q1_2" value="4"> 4</label>
                        <label><input type="radio" name="q1_2" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">1.3 Presents ideas/concepts clearly and convincingly from related fields and incorporates subject matter into actual experience</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q1_3" value="1" required> 1</label>
                        <label><input type="radio" name="q1_3" value="2"> 2</label>
                        <label><input type="radio" name="q1_3" value="3"> 3</label>
                        <label><input type="radio" name="q1_3" value="4"> 4</label>
                        <label><input type="radio" name="q1_3" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">1.4 Allows students to use concepts to demonstrate understanding of lessons</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q1_4" value="1" required> 1</label>
                        <label><input type="radio" name="q1_4" value="2"> 2</label>
                        <label><input type="radio" name="q1_4" value="3"> 3</label>
                        <label><input type="radio" name="q1_4" value="4"> 4</label>
                        <label><input type="radio" name="q1_4" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">1.5 Gives fair tests and evaluations and returns test results within a reasonable time</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q1_5" value="1" required> 1</label>
                        <label><input type="radio" name="q1_5" value="2"> 2</label>
                        <label><input type="radio" name="q1_5" value="3"> 3</label>
                        <label><input type="radio" name="q1_5" value="4"> 4</label>
                        <label><input type="radio" name="q1_5" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">1.6 Commands orderly teaching using proper speech</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q1_6" value="1" required> 1</label>
                        <label><input type="radio" name="q1_6" value="2"> 2</label>
                        <label><input type="radio" name="q1_6" value="3"> 3</label>
                        <label><input type="radio" name="q1_6" value="4"> 4</label>
                        <label><input type="radio" name="q1_6" value="5"> 5</label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">2. Management Skills</h3>
                
                <div class="question">
                    <p class="question-text">2.1 Maintains an orderly, disciplined and safe classroom to ensure proper and conducive learning</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q2_1" value="1" required> 1</label>
                        <label><input type="radio" name="q2_1" value="2"> 2</label>
                        <label><input type="radio" name="q2_1" value="3"> 3</label>
                        <label><input type="radio" name="q2_1" value="4"> 4</label>
                        <label><input type="radio" name="q2_1" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">2.2 Follows a systematic schedule of classes and other daily activities</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q2_2" value="1" required> 1</label>
                        <label><input type="radio" name="q2_2" value="2"> 2</label>
                        <label><input type="radio" name="q2_2" value="3"> 3</label>
                        <label><input type="radio" name="q2_2" value="4"> 4</label>
                        <label><input type="radio" name="q2_2" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">2.3 Develops in students respect and respect for teachers</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q2_3" value="1" required> 1</label>
                        <label><input type="radio" name="q2_3" value="2"> 2</label>
                        <label><input type="radio" name="q2_3" value="3"> 3</label>
                        <label><input type="radio" name="q2_3" value="4"> 4</label>
                        <label><input type="radio" name="q2_3" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">2.4 Allows students to express their opinions and views</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q2_4" value="1" required> 1</label>
                        <label><input type="radio" name="q2_4" value="2"> 2</label>
                        <label><input type="radio" name="q2_4" value="3"> 3</label>
                        <label><input type="radio" name="q2_4" value="4"> 4</label>
                        <label><input type="radio" name="q2_4" value="5"> 5</label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">3. Guidance Skills</h3>
                
                <div class="question">
                    <p class="question-text">3.1 Accepts students as individuals with strengths and weaknesses</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q3_1" value="1" required> 1</label>
                        <label><input type="radio" name="q3_1" value="2"> 2</label>
                        <label><input type="radio" name="q3_1" value="3"> 3</label>
                        <label><input type="radio" name="q3_1" value="4"> 4</label>
                        <label><input type="radio" name="q3_1" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">3.2 Shows confidence and self-organization</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q3_2" value="1" required> 1</label>
                        <label><input type="radio" name="q3_2" value="2"> 2</label>
                        <label><input type="radio" name="q3_2" value="3"> 3</label>
                        <label><input type="radio" name="q3_2" value="4"> 4</label>
                        <label><input type="radio" name="q3_2" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">3.3 Manages class and student problems with fairness and understanding</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q3_3" value="1" required> 1</label>
                        <label><input type="radio" name="q3_3" value="2"> 2</label>
                        <label><input type="radio" name="q3_3" value="3"> 3</label>
                        <label><input type="radio" name="q3_3" value="4"> 4</label>
                        <label><input type="radio" name="q3_3" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">3.4 Shows genuine concern for personal and other problems shown by students outside of classroom activities</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q3_4" value="1" required> 1</label>
                        <label><input type="radio" name="q3_4" value="2"> 2</label>
                        <label><input type="radio" name="q3_4" value="3"> 3</label>
                        <label><input type="radio" name="q3_4" value="4"> 4</label>
                        <label><input type="radio" name="q3_4" value="5"> 5</label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">4. Personal and Social Characteristics</h3>
                
                <div class="question">
                    <p class="question-text">4.1 Maintains emotional balance: not too critical or overly sensitive</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q4_1" value="1" required> 1</label>
                        <label><input type="radio" name="q4_1" value="2"> 2</label>
                        <label><input type="radio" name="q4_1" value="3"> 3</label>
                        <label><input type="radio" name="q4_1" value="4"> 4</label>
                        <label><input type="radio" name="q4_1" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">4.2 Free from habitual movements that interfere with the teaching and learning process</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q4_2" value="1" required> 1</label>
                        <label><input type="radio" name="q4_2" value="2"> 2</label>
                        <label><input type="radio" name="q4_2" value="3"> 3</label>
                        <label><input type="radio" name="q4_2" value="4"> 4</label>
                        <label><input type="radio" name="q4_2" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">4.3 Neat and presentable; Clean and tidy clothes</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q4_3" value="1" required> 1</label>
                        <label><input type="radio" name="q4_3" value="2"> 2</label>
                        <label><input type="radio" name="q4_3" value="3"> 3</label>
                        <label><input type="radio" name="q4_3" value="4"> 4</label>
                        <label><input type="radio" name="q4_3" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">4.4 Does not show favoritism</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q4_4" value="1" required> 1</label>
                        <label><input type="radio" name="q4_4" value="2"> 2</label>
                        <label><input type="radio" name="q4_4" value="3"> 3</label>
                        <label><input type="radio" name="q4_4" value="4"> 4</label>
                        <label><input type="radio" name="q4_4" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">4.5 Has a good sense of humor and shows enthusiasm in teaching</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q4_5" value="1" required> 1</label>
                        <label><input type="radio" name="q4_5" value="2"> 2</label>
                        <label><input type="radio" name="q4_5" value="3"> 3</label>
                        <label><input type="radio" name="q4_5" value="4"> 4</label>
                        <label><input type="radio" name="q4_5" value="5"> 5</label>
                    </div>
                </div>
                
                <div class="question">
                    <p class="question-text">4.6 Has good diction, clear and proper voice modulation</p>
                    <div class="rating-options">
                        <label><input type="radio" name="q4_6" value="1" required> 1</label>
                        <label><input type="radio" name="q4_6" value="2"> 2</label>
                        <label><input type="radio" name="q4_6" value="3"> 3</label>
                        <label><input type="radio" name="q4_6" value="4"> 4</label>
                        <label><input type="radio" name="q4_6" value="5"> 5</label>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h3 class="section-title">5. Comments/Suggestions</h3>
                <textarea id="comments" name="comments" 
                          placeholder="Please provide any comments or suggestions about the teacher..."><?php echo htmlspecialchars($_POST['comments'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" class="submit-btn">✅ Submit Evaluation</button>
        </form>
        
        <footer>
            <p>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc. - Evaluation System</p>
        </footer>
    </div>

    <script>
        // Auto-populate subject when teacher is selected
        document.getElementById('teacher').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const teacherText = selectedOption.text;
                const subject = teacherText.split(' - ')[1];
                if (subject) {
                    document.getElementById('subject').value = subject;
                }
            }
        });
        
        // Form validation enhancement
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredRadioGroups = [
                'q1_1', 'q1_2', 'q1_3', 'q1_4', 'q1_5', 'q1_6',
                'q2_1', 'q2_2', 'q2_3', 'q2_4',
                'q3_1', 'q3_2', 'q3_3', 'q3_4',
                'q4_1', 'q4_2', 'q4_3', 'q4_4', 'q4_5', 'q4_6'
            ];
            
            for (let group of requiredRadioGroups) {
                const radios = document.getElementsByName(group);
                const checked = Array.from(radios).some(radio => radio.checked);
                if (!checked) {
                    e.preventDefault();
                    alert(`Please answer question ${group.replace('_', '.')}`);
                    return;
                }
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>
