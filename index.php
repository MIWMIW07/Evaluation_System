<?php
// Start session to track if a student has already submitted an evaluation
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "evaluation_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect form data
    $student_id = $_POST['student_id'];
    $student_name = $_POST['student_name'];
    $section = $_POST['section'];
    $program = $_POST['program'];
    $teacher_id = $_POST['teacher'];
    $subject = $_POST['subject'];
    
    // Initialize an array to store all question ratings
    $ratings = [];
    
    // Section 1: Teaching Competence (6 questions)
    for ($i = 1; $i <= 6; $i++) {
        $ratings["q1_$i"] = $_POST["q1_$i"];
    }
    
    // Section 2: Management Skills (4 questions)
    for ($i = 1; $i <= 4; $i++) {
        $ratings["q2_$i"] = $_POST["q2_$i"];
    }
    
    // Section 3: Guidance Skills (4 questions)
    for ($i = 1; $i <= 4; $i++) {
        $ratings["q3_$i"] = $_POST["q3_$i"];
    }
    
    // Section 4: Personal and Social Characteristics (6 questions)
    for ($i = 1; $i <= 6; $i++) {
        $ratings["q4_$i"] = $_POST["q4_$i"];
    }
    
    $comments = $_POST['comments'];
    
    // Check if student has already evaluated this teacher
    $check_sql = "SELECT id FROM evaluations WHERE student_id = '$student_id' AND teacher_id = $teacher_id";
    $result = $conn->query($check_sql);
    
    if ($result->num_rows > 0) {
        $error = "You have already evaluated this teacher.";
    } else {
        // Prepare and bind
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
            $success = "Evaluation submitted successfully!";
            // Set session variable to prevent multiple submissions
            $_SESSION['evaluated_'.$teacher_id] = true;
        } else {
            $error = "Error: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Fetch teachers for dropdown
$teachers_sql = "SELECT id, name FROM teachers";
$teachers_result = $conn->query($teachers_sql);

$conn->close();
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
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #4CAF50;
        }
        
        header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        header p {
            color: #7f8c8d;
        }
        
        .instruction-box {
            background-color: #e7f3fe;
            border-left: 6px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .rating-scale {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
        }
        
        .scale-item {
            text-align: center;
            flex: 1;
        }
        
        .scale-item span {
            font-weight: bold;
            display: block;
        }
        
        .evaluation-form {
            margin-top: 20px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .section-title {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            margin: -15px -15px 15px -15px;
            border-radius: 3px 3px 0 0;
        }
        
        .question {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #eee;
        }
        
        .question:last-child {
            border-bottom: none;
        }
        
        .question-text {
            margin-bottom: 10px;
            font-weight: bold;
        }
        
        .rating-options {
            display: flex;
            justify-content: space-between;
        }
        
        .rating-options label {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
        }
        
        .rating-options input[type="radio"] {
            margin-top: 5px;
        }
        
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .submit-btn {
            background-color: #4CAF50;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            margin: 30px auto;
            width: 200px;
        }
        
        .submit-btn:hover {
            background-color: #45a049;
        }
        
        .required {
            color: red;
        }
        
        footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #7f8c8d;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        
        .alert-error {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        
        @media (max-width: 768px) {
            .rating-options {
                flex-direction: column;
            }
            
            .rating-options label {
                flex-direction: row;
                justify-content: space-between;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Philippine Technological Institute of Science Arts and Trade, Inc.</h1>
            <p>GMA-BRANCH (2nd Semester 2024-2025)</p>
            <h2>Teacher Evaluation System</h2>
        </header>
        
        <div class="instruction-box">
            <p><strong>Directions:</strong> The following items describe aspects of the teacher's characteristics inside and outside the classroom. 
            Choose the appropriate number that fits your observation. Your score will help the teacher further develop their dedication to the field of teaching.</p>
        </div>
        
        <div class="rating-scale">
            <div class="scale-item"><span>5</span> Outstanding</div>
            <div class="scale-item"><span>4</span> Very Satisfactory</div>
            <div class="scale-item"><span>3</span> Good/Satisfactory</div>
            <div class="scale-item"><span>2</span> Fair</div>
            <div class="scale-item"><span>1</span> Unsatisfactory</div>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" class="evaluation-form">
            <div class="form-group">
                <label for="student_id">Student ID <span class="required">*</span></label>
                <input type="text" id="student_id" name="student_id" required>
            </div>
            
            <div class="form-group">
                <label for="student_name">Student Name <span class="required">*</span></label>
                <input type="text" id="student_name" name="student_name" required>
            </div>
            
            <div class="form-group">
                <label for="section">Section <span class="required">*</span></label>
                <input type="text" id="section" name="section" required>
            </div>
            
            <div class="form-group">
                <label for="program">Program/Strand <span class="required">*</span></label>
                <select id="program" name="program" required>
                    <option value="">Select Program/Strand</option>
                    <option value="STEM">STEM (Science, Technology, Engineering, and Mathematics)</option>
                    <option value="HUMSS">HUMSS (Humanities and Social Sciences)</option>
                    <option value="ABM">ABM (Accountancy, Business, and Management)</option>
                    <option value="GAS">GAS (General Academic Strand)</option>
                    <option value="TVL">TVL (Technical-Vocational-Livelihood)</option>
                    <option value="BSIT">BS Information Technology</option>
                    <option value="BSCS">BS Computer Science</option>
                    <option value="BSBA">BS Business Administration</option>
                    <option value="BSE">BS Education</option>
                    <option value="BSHM">BS Hospitality Management</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="teacher">Teacher <span class="required">*</span></label>
                <select id="teacher" name="teacher" required>
                    <option value="">Select Teacher</option>
                    <?php while($row = $teachers_result->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>"><?php echo $row['name']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="subject">Subject <span class="required">*</span></label>
                <input type="text" id="subject" name="subject" required>
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
                <textarea id="comments" name="comments" placeholder="Please provide any comments or suggestions about the teacher..."></textarea>
            </div>
            
            <button type="submit" class="submit-btn">Submit Evaluation</button>
        </form>
        
        <footer>
            <p>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc. - Evaluation System</p>
        </footer>
    </div>
</body>
</html>
