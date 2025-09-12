<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Process the form submission
    include 'db_connection.php';

    // Collect all form data
    $studentId = $_POST['studentId'];
    $studentName = $_POST['studentName'];
    $section = $_POST['section'];
    $program = $_POST['program'];
    $teacherId = $_POST['teacher'];
    $subject = $_POST['subject'];
    $comments = $_POST['comments'];

    // Collect all the question ratings
    $ratings = [];
    for ($i=1; $i<=4; $i++) {
        for ($j=1; $j<=6; $j++) {
            $qName = "q{$i}_{$j}";
            if (isset($_POST[$qName])) {
                $ratings[$qName] = $_POST[$qName];
            }
        }
    }

    // Prepare SQL statement
    // We are going to insert into evaluations table
    // Note: Adjust the SQL according to your table structure

    $sql = "INSERT INTO evaluations (teacher_id, student_id, student_name, section, program, subject, comments, 
             q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
             q2_1, q2_2, q2_3, q2_4,
             q3_1, q3_2, q3_3, q3_4,
             q4_1, q4_2, q4_3, q4_4, q4_5, q4_6)
            VALUES (?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Error preparing statement: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("issssssiiiiiiiiiiiiiiiiiii", 
        $teacherId, $studentId, $studentName, $section, $program, $subject, $comments,
        $ratings['q1_1'], $ratings['q1_2'], $ratings['q1_3'], $ratings['q1_4'], $ratings['q1_5'], $ratings['q1_6'],
        $ratings['q2_1'], $ratings['q2_2'], $ratings['q2_3'], $ratings['q2_4'],
        $ratings['q3_1'], $ratings['q3_2'], $ratings['q3_3'], $ratings['q3_4'],
        $ratings['q4_1'], $ratings['q4_2'], $ratings['q4_3'], $ratings['q4_4'], $ratings['q4_5'], $ratings['q4_6']
    );

    if ($stmt->execute()) {
        echo "<h2>Thank you for your evaluation!</h2>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
    exit; // Stop rendering the form
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
        
        <form id="evaluationForm" class="evaluation-form">
            <div class="form-group">
                <label for="studentId">Student ID <span class="required">*</span></label>
                <input type="text" id="studentId" name="studentId" required>
            </div>
            
            <div class="form-group">
                <label for="studentName">Student Name <span class="required">*</span></label>
                <input type="text" id="studentName" name="studentName" required>
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
                    <!-- Options will be populated from database later -->
                    <option value="1">ABSALON, JIMMY P.</option>
                    <!-- Add more teachers as needed -->
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
                
                <!-- Questions 2.1 to 2.4 would go here following the same pattern -->
                <!-- For brevity, I'm showing just the structure for one question -->
                
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
                
                <!-- Additional questions for section 2 would be here -->
            </div>
            
            <div class="form-section">
                <h3 class="section-title">3. Guidance Skills</h3>
                
                <!-- Questions 3.1 to 3.4 would go here -->
            </div>
            
            <div class="form-section">
                <h3 class="section-title">4. Personal and Social Characteristics</h3>
                
                <!-- Questions 4.1 to 4.6 would go here -->
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

    <script>
        document.getElementById('evaluationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Basic validation
            let allQuestionsAnswered = true;
            const radioGroups = document.querySelectorAll('input[type="radio"]');
            const groups = {};
            
            // Group radio buttons by name
            radioGroups.forEach(radio => {
                if (!groups[radio.name]) {
                    groups[radio.name] = [];
                }
                groups[radio.name].push(radio);
            });
            
            // Check if any group has no selection
            for (const groupName in groups) {
                const isChecked = groups[groupName].some(radio => radio.checked);
                if (!isChecked) {
                    allQuestionsAnswered = false;
                    break;
                }
            }
            
            if (!allQuestionsAnswered) {
                alert('Please answer all evaluation questions before submitting.');
                return;
            }
            
            // If validation passes, prepare data for submission
            const formData = new FormData(this);
            
            // For now, just show an alert. Later, this will be sent to PHP.
            alert('Evaluation submitted successfully! (This will be connected to PHP later)');
            
            // In the final version, you'll send this data to your PHP script
            // fetch('submit_evaluation.php', {
            //     method: 'POST',
            //     body: formData
            // })
            // .then(response => response.json())
            // .then(data => {
            //     if (data.success) {
            //         alert('Evaluation submitted successfully!');
            //         this.reset();
            //     } else {
            //         alert('Error: ' + data.message);
            //     }
            // })
            // .catch(error => {
            //     console.error('Error:', error);
            //     alert('An error occurred while submitting the evaluation.');
            // });
        });
    </script>
</body>
</html>
