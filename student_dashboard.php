<?php
// student_dashboard.php - Student Dashboard
session_start();

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

// Handle program/section update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_info'])) {
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
    
    try {
        $program = trim($_POST['program']);
        $section = trim($_POST['section']);
        
        if (empty($program) || empty($section)) {
            throw new Exception("Program and section are required.");
        }
        
        // Update user information
        query("UPDATE users SET program = ?, section = ? WHERE id = ?", 
              [$program, $section, $_SESSION['user_id']]);
        
        // Update session variables
        $_SESSION['program'] = $program;
        $_SESSION['section'] = $section;
        
        $success = "✅ Your program and section have been updated successfully!";
        
    } catch (Exception $e) {
        $error = "❌ " . $e->getMessage();
    }
}

// Get student's current program and section
$current_section = $_SESSION['section'] ?? '';
$current_program = $_SESSION['program'] ?? '';

// ==================================================================
// NEW CODE #1: Fetch all sections and group them by program for the dynamic dropdown
// ==================================================================
try {
    $all_sections_stmt = query("SELECT section_code, program FROM sections WHERE is_active = true ORDER BY section_code");
    $all_sections = fetch_all($all_sections_stmt);
    
    $sections_by_program = [];
    foreach ($all_sections as $section) {
        // Group sections under their program ('COLLEGE' or 'SHS')
        $sections_by_program[$section['program']][] = $section['section_code'];
    }
} catch (Exception $e) {
    $error = "Could not load section list: " . $e->getMessage();
    $sections_by_program = [];
}
// ==================================================================

// Get teachers based on student's program
$teachers_result = [];
$evaluated_teachers = [];

// Get evaluated teachers for this student
try {
    $evaluated_stmt = query("SELECT teacher_id FROM evaluations WHERE user_id = ?", 
                            [$_SESSION['user_id']]);
    $evaluated_teachers_result = fetch_all($evaluated_stmt);
    $evaluated_teachers = array_column($evaluated_teachers_result, 'teacher_id');
} catch (Exception $e) {
    $error = "Could not load evaluation data: " . $e->getMessage();
}

// Get teachers for student's section using the new structure
if (!empty($current_section)) {
    try {
        // ==================================================================
        // MODIFIED SQL QUERY #2: Added "AND t.department = sec.program"
        // This ensures the teacher's department matches the section's program.
        // ==================================================================
        $teachers_stmt = query("
            SELECT DISTINCT
                t.id, 
                t.name, 
                t.department
            FROM teachers t
            JOIN section_teachers st ON t.id = st.teacher_id
            JOIN sections sec ON st.section_id = sec.id
            WHERE sec.section_code = ?
              AND t.department = sec.program
              AND st.is_active = true
              AND t.is_active = true
            ORDER BY t.name", 
            [$current_section]
        );
        $teachers_result = fetch_all($teachers_stmt);
        
        if (empty($teachers_result)) {
            // Fallback: This query is already correct as it uses the program.
            $teachers_stmt = query("
                SELECT id, name, department 
                FROM teachers 
                WHERE department = ? AND is_active = true 
                ORDER BY name", 
                [$current_program]
            );
            $teachers_result = fetch_all($teachers_stmt);
        }
        
    } catch (Exception $e) {
        $error = "Could not load teachers list: " . $e->getMessage();
        $teachers_result = [];
    }
} else {
    $teachers_result = [];
}

// Get evaluation statistics
$total_teachers = count($teachers_result);
$completed_evaluations = count($evaluated_teachers);
$remaining_evaluations = $total_teachers - $completed_evaluations;
$completion_percentage = $total_teachers > 0 ? round(($completed_evaluations / $total_teachers) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Teacher Evaluation System</title>
    <style>
        /* All your CSS styles go here */
    </style>
</head>
<body>
    <div class="container">
        <div class="program-section-form">
            <h3>📚 Update Your Program & Section</h3>
            <p style="margin-bottom: 20px; color: #666;">Please select your program and section to view available teachers for evaluation.</p>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="update_info" value="1">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="program">Program *</label>
                        <select id="program" name="program" required>
                            <option value="">Select Program</option>
                            <option value="SHS" <?php echo ($current_program === 'SHS') ? 'selected' : ''; ?>>
                                Senior High School (SHS)
                            </option>
                            <option value="COLLEGE" <?php echo ($current_program === 'COLLEGE') ? 'selected' : ''; ?>>
                                College
                            </option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="section">Section *</label>
                        <select id="section" name="section" required>
                            <option value="">Select a program first</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">🔄 Update Info</button>
                </div>
            </form>
        </div>
        
        </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Animate stat cards and logout confirmation logic remains the same ---
        
        // --- New logic for dynamic section dropdown ---
        const programSelect = document.getElementById('program');
        const sectionSelect = document.getElementById('section');
        
        // This PHP block safely embeds your section data into JavaScript
        const sectionsByProgram = <?php echo json_encode($sections_by_program); ?>;
        const currentSelectedSection = "<?php echo $current_section; ?>";

        function updateSectionDropdown() {
            const selectedProgram = programSelect.value;
            
            // Clear current options
            sectionSelect.innerHTML = ''; 
            
            if (selectedProgram && sectionsByProgram[selectedProgram]) {
                // Add a default option first
                let defaultOption = new Option('Select Section', '');
                sectionSelect.add(defaultOption);

                // Add sections for the selected program
                sectionsByProgram[selectedProgram].forEach(sectionCode => {
                    let option = new Option(sectionCode, sectionCode);
                    sectionSelect.add(option);
                });

                // If there's a previously selected section, try to re-select it
                if (currentSelectedSection) {
                    sectionSelect.value = currentSelectedSection;
                }
                
            } else {
                // If no program is selected
                let placeholderOption = new Option('Select a program first', '');
                sectionSelect.add(placeholderOption);
            }
        }

        // Add an event listener to the program dropdown
        programSelect.addEventListener('change', updateSectionDropdown);

        // Run the function once on page load to set the initial state
        updateSectionDropdown();
    });
    </script>
</body>
</html>
