<?php
// database_setup.php
// Minimal database setup: Only evaluations and teacher_assignments tables
// Students and teachers data stored in Google Sheets

require_once __DIR__ . '/includes/db_connection.php';

// Check if database is available
if (!isDatabaseAvailable()) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Setup - No Connection</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
            .error { color: #e74c3c; font-weight: bold; }
            .info { background: #e8f4fd; padding: 15px; border-radius: 5px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Database Setup</h1>
            <div class="error">❌ Database connection not available</div>
            
            <div class="info">
                <h3>Hybrid System Information:</h3>
                <ul>
                    <li><strong>Students Data:</strong> Stored in Google Sheets (Student_ID, Last_Name, First_Name, Section, Program, Username, Password)</li>
                    <li><strong>Teachers List:</strong> Stored in Google Sheets</li>
                    <li><strong>Evaluations & Teacher Assignments:</strong> Stored in PostgreSQL</li>
                </ul>
                
                <p><strong>Next Steps:</strong></p>
                <ol>
                    <li>Set up your PostgreSQL database on Railway</li>
                    <li>Ensure <code>DATABASE_URL</code> environment variable is set</li>
                    <li>Reload this setup to create the necessary tables</li>
                </ol>
            </div>
            
            <p><a href="index.php" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Home</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    $pdo = getPDO();
    echo "<h2>🔧 Setting up minimal database system...</h2><br>";

    // ==============================
    // Drop ALL old tables (clean slate)
    // ==============================
    echo "🗑️ Cleaning up old tables...<br>";
    $pdo->exec("
        DROP TABLE IF EXISTS activity_logs CASCADE;
        DROP TABLE IF EXISTS evaluations CASCADE;
        DROP TABLE IF EXISTS teacher_assignments CASCADE;
        DROP TABLE IF EXISTS sections CASCADE;
        DROP TABLE IF EXISTS users CASCADE;
        DROP TABLE IF EXISTS students CASCADE;
        DROP TABLE IF EXISTS teachers CASCADE;
        DROP TABLE IF EXISTS login_attempts CASCADE;
        DROP TABLE IF EXISTS section_teachers CASCADE;
    ");
    echo "✓ Old tables removed<br><br>";

    // ==============================
    // Teacher Assignments Table
    // ==============================
    echo "📋 Creating teacher_assignments table...<br>";
    $pdo->exec("
        CREATE TABLE teacher_assignments (
            id SERIAL PRIMARY KEY,
            teacher_name VARCHAR(100) NOT NULL,
            section VARCHAR(50) NOT NULL,
            subject VARCHAR(100) NOT NULL,
            program VARCHAR(10) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
            school_year VARCHAR(20) DEFAULT '2025-2026',
            semester VARCHAR(10) DEFAULT '1st' CHECK (semester IN ('1st','2nd')),
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (teacher_name, section, subject, school_year, semester)
        );
        
        CREATE INDEX idx_teacher_name ON teacher_assignments(teacher_name);
        CREATE INDEX idx_section ON teacher_assignments(section);
        CREATE INDEX idx_program ON teacher_assignments(program);
        CREATE INDEX idx_active ON teacher_assignments(is_active);
    ");
    echo "✓ Teacher assignments table created<br>";

    // ==============================
    // Evaluations Table
    // ==============================
    echo "📊 Creating evaluations table...<br>";
    $pdo->exec("
        CREATE TABLE evaluations (
            id SERIAL PRIMARY KEY,
            student_username VARCHAR(50) NOT NULL,
            student_name VARCHAR(100) NOT NULL,
            teacher_name VARCHAR(100) NOT NULL,
            section VARCHAR(50) NOT NULL,
            program VARCHAR(10) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
            subject VARCHAR(100),
            
            -- Section 1: Teaching Ability (6 questions)
            q1_1 SMALLINT CHECK (q1_1 BETWEEN 1 AND 5),
            q1_2 SMALLINT CHECK (q1_2 BETWEEN 1 AND 5),
            q1_3 SMALLINT CHECK (q1_3 BETWEEN 1 AND 5),
            q1_4 SMALLINT CHECK (q1_4 BETWEEN 1 AND 5),
            q1_5 SMALLINT CHECK (q1_5 BETWEEN 1 AND 5),
            q1_6 SMALLINT CHECK (q1_6 BETWEEN 1 AND 5),
            
            -- Section 2: Management Skills (4 questions)
            q2_1 SMALLINT CHECK (q2_1 BETWEEN 1 AND 5),
            q2_2 SMALLINT CHECK (q2_2 BETWEEN 1 AND 5),
            q2_3 SMALLINT CHECK (q2_3 BETWEEN 1 AND 5),
            q2_4 SMALLINT CHECK (q2_4 BETWEEN 1 AND 5),
            
            -- Section 3: Guidance Skills (4 questions)
            q3_1 SMALLINT CHECK (q3_1 BETWEEN 1 AND 5),
            q3_2 SMALLINT CHECK (q3_2 BETWEEN 1 AND 5),
            q3_3 SMALLINT CHECK (q3_3 BETWEEN 1 AND 5),
            q3_4 SMALLINT CHECK (q3_4 BETWEEN 1 AND 5),
            
            -- Section 4: Personal and Social Characteristics (6 questions)
            q4_1 SMALLINT CHECK (q4_1 BETWEEN 1 AND 5),
            q4_2 SMALLINT CHECK (q4_2 BETWEEN 1 AND 5),
            q4_3 SMALLINT CHECK (q4_3 BETWEEN 1 AND 5),
            q4_4 SMALLINT CHECK (q4_4 BETWEEN 1 AND 5),
            q4_5 SMALLINT CHECK (q4_5 BETWEEN 1 AND 5),
            q4_6 SMALLINT CHECK (q4_6 BETWEEN 1 AND 5),
            
            -- Comments and timestamps
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            -- Prevent duplicate evaluations
            UNIQUE (student_username, teacher_name, subject, section)
        );
        
        CREATE INDEX idx_student_username ON evaluations(student_username);
        CREATE INDEX idx_teacher_eval ON evaluations(teacher_name);
        CREATE INDEX idx_section_eval ON evaluations(section);
        CREATE INDEX idx_program_eval ON evaluations(program);
    ");
    echo "✓ Evaluations table created<br>";

    // ==============================
    // Insert Sample Teacher Assignments
    // ==============================
    echo "<br>📝 Adding sample teacher assignments...<br>";
    $sample_assignments = [
        // SHS Teachers
        ['Ms. Rodriguez', 'SHS-11A', 'Mathematics', 'SHS', '2025-2026', '1st'],
        ['Mr. Santos', 'SHS-11A', 'English', 'SHS', '2025-2026', '1st'],
        ['Mrs. Cruz', 'SHS-11A', 'Science', 'SHS', '2025-2026', '1st'],
        ['Ms. Garcia', 'SHS-12A', 'Mathematics', 'SHS', '2025-2026', '1st'],
        ['Mr. Lopez', 'SHS-12A', 'Filipino', 'SHS', '2025-2026', '1st'],
        
        // College Teachers
        ['Prof. Johnson', 'BSCS-1A', 'Programming Fundamentals', 'COLLEGE', '2025-2026', '1st'],
        ['Dr. Williams', 'BSCS-1A', 'Computer Science Fundamentals', 'COLLEGE', '2025-2026', '1st'],
        ['Prof. Brown', 'BSCS-2A', 'Data Structures', 'COLLEGE', '2025-2026', '1st'],
        ['Dr. Davis', 'BSCS-2A', 'Database Systems', 'COLLEGE', '2025-2026', '1st'],
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO teacher_assignments (teacher_name, section, subject, program, school_year, semester) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT (teacher_name, section, subject, school_year, semester) DO NOTHING
    ");
    
    $added_count = 0;
    foreach ($sample_assignments as $assignment) {
        $stmt->execute($assignment);
        if ($stmt->rowCount() > 0) {
            $added_count++;
        }
    }
    echo "✓ Added {$added_count} teacher assignments<br>";

    // ==============================
    // Summary
    // ==============================
    echo "<br><div style='background: #d4edda; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745;'>";
    echo "<h3>✅ Database Setup Complete!</h3>";
    echo "<p><strong>Tables Created:</strong></p>";
    echo "<ul>";
    echo "<li>📋 <code>teacher_assignments</code> - Links teachers to sections and subjects</li>";
    echo "<li>📊 <code>evaluations</code> - Stores student evaluation responses</li>";
    echo "</ul>";
    
    echo "<p><strong>Data Source:</strong></p>";
    echo "<ul>";
    echo "<li>📑 <strong>Students:</strong> Google Sheets (Columns: Student_ID, Last_Name, First_Name, Section, Program, Username, Password)</li>";
    echo "<li>👨‍🏫 <strong>Teachers:</strong> Database teacher_assignments table</li>";
    echo "</ul>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Ensure your Google Sheets has the correct student data</li>";
    echo "<li>Students will login using their Username from Google Sheets</li>";
    echo "<li>Student info will be automatically displayed from Google Sheets</li>";
    echo "<li>Add more teacher assignments as needed</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<br><p><a href='index.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>← Go to Login Page</a></p>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
    echo "<h3>❌ Database Setup Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
    echo "</div>";
    exit(1);
}
?>
