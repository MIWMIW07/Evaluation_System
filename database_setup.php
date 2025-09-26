<?php
// database_setup.php - Hybrid setup (DB for teachers/evaluations, Google Sheets for students)

require_once 'includes/db_connection.php';

if (!isDatabaseAvailable()) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Setup - No Connection</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f0f0f0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
            .error { color: #e74c3c; }
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
                    <li><strong>Students Data:</strong> Stored in Google Sheets</li>
                    <li><strong>Teachers & Evaluations:</strong> Stored in Database</li>
                </ul>
                
                <p><strong>Next Steps:</strong></p>
                <ol>
                    <li>Set up your database service on Railway (PostgreSQL or MySQL)</li>
                    <li>Ensure DATABASE_URL environment variable is set</li>
                    <li>Run this setup again to create teacher/evaluation tables</li>
                </ol>
            </div>
            
            <div>
                <a href="index.php" style="background: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Home</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
    echo "🔧 Setting up hybrid database system...\n\n";

    // Create sections table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            section_code VARCHAR(20) NOT NULL UNIQUE,
            section_name VARCHAR(100) NOT NULL,
            program ENUM('SHS', 'COLLEGE') NOT NULL,
            year_level VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_section_code (section_code),
            INDEX idx_program (program)
        )
    ");
    echo "✓ Sections table created/updated\n";

    // Create teachers table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teachers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            department ENUM('SHS', 'COLLEGE') NOT NULL,
            subject VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_department (department)
        )
    ");
    echo "✓ Teachers table created/updated\n";

    // Create teacher_sections table (many-to-many relationship)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            section_id INT NOT NULL,
            subject VARCHAR(100),
            school_year VARCHAR(20) DEFAULT '2025-2026',
            semester ENUM('1st', '2nd') DEFAULT '1st',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
            
            UNIQUE KEY unique_assignment (teacher_id, section_id, subject, school_year, semester),
            INDEX idx_teacher (teacher_id),
            INDEX idx_section (section_id)
        )
    ");
    echo "✓ Teacher-Section assignments table created/updated\n";

    // Create evaluations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS evaluations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            student_name VARCHAR(100) NOT NULL,
            teacher_id INT NOT NULL,
            section VARCHAR(50) NOT NULL,
            program ENUM('SHS', 'COLLEGE') NOT NULL,
            subject VARCHAR(100),
            
            -- Section 1: Teaching Ability (6 questions)
            q1_1 TINYINT NOT NULL CHECK (q1_1 BETWEEN 1 AND 5),
            q1_2 TINYINT NOT NULL CHECK (q1_2 BETWEEN 1 AND 5),
            q1_3 TINYINT NOT NULL CHECK (q1_3 BETWEEN 1 AND 5),
            q1_4 TINYINT NOT NULL CHECK (q1_4 BETWEEN 1 AND 5),
            q1_5 TINYINT NOT NULL CHECK (q1_5 BETWEEN 1 AND 5),
            q1_6 TINYINT NOT NULL CHECK (q1_6 BETWEEN 1 AND 5),
            
            -- Section 2: Management Skills (4 questions)
            q2_1 TINYINT NOT NULL CHECK (q2_1 BETWEEN 1 AND 5),
            q2_2 TINYINT NOT NULL CHECK (q2_2 BETWEEN 1 AND 5),
            q2_3 TINYINT NOT NULL CHECK (q2_3 BETWEEN 1 AND 5),
            q2_4 TINYINT NOT NULL CHECK (q2_4 BETWEEN 1 AND 5),
            
            -- Section 3: Guidance Skills (4 questions)
            q3_1 TINYINT NOT NULL CHECK (q3_1 BETWEEN 1 AND 5),
            q3_2 TINYINT NOT NULL CHECK (q3_2 BETWEEN 1 AND 5),
            q3_3 TINYINT NOT NULL CHECK (q3_3 BETWEEN 1 AND 5),
            q3_4 TINYINT NOT NULL CHECK (q3_4 BETWEEN 1 AND 5),
            
            -- Section 4: Personal and Social Characteristics (6 questions)
            q4_1 TINYINT NOT NULL CHECK (q4_1 BETWEEN 1 AND 5),
            q4_2 TINYINT NOT NULL CHECK (q4_2 BETWEEN 1 AND 5),
            q4_3 TINYINT NOT NULL CHECK (q4_3 BETWEEN 1 AND 5),
            q4_4 TINYINT NOT NULL CHECK (q4_4 BETWEEN 1 AND 5),
            q4_5 TINYINT NOT NULL CHECK (q4_5 BETWEEN 1 AND 5),
            q4_6 TINYINT NOT NULL CHECK (q4_6 BETWEEN 1 AND 5),
            
            -- Comments
            comments TEXT,
            
            -- Timestamps
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL,
            
            -- Indexes
            INDEX idx_student (student_id),
            INDEX idx_teacher (teacher_id),
            INDEX idx_section (section),
            INDEX idx_program (program),
            INDEX idx_created (created_at),
            
            -- Foreign key
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
            
            -- Prevent duplicate evaluations
            UNIQUE KEY unique_evaluation (student_id, teacher_id)
        )
    ");
    echo "✓ Evaluations table created/updated\n";

    // Create admin users table (minimal, just for admin access)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            user_type ENUM('admin') DEFAULT 'admin',
            full_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            
            INDEX idx_username (username),
            INDEX idx_user_type (user_type)
        )
    ");
    echo "✓ Admin users table created/updated\n";

    // Create activity log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            status ENUM('success', 'error', 'warning') DEFAULT 'success',
            user_id VARCHAR(50),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        )
    ");
    echo "✓ Activity log table created/updated\n";

    // Insert sample data if empty
    
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, 'admin', 'System Administrator')")
            ->execute(['admin', $adminPassword]);
        echo "✓ Admin user created (username: admin, password: admin123)\n";
    } else {
        echo "✓ Admin user already exists\n";
    }

    // Insert sample sections if empty
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $sampleSections = [
            ['SHS-11A', 'Grade 11 Section A', 'SHS', 'Grade 11'],
            ['SHS-11B', 'Grade 11 Section B', 'SHS', 'Grade 11'],
            ['SHS-12A', 'Grade 12 Section A', 'SHS', 'Grade 12'],
            ['COL-IT1A', 'IT 1st Year Section A', 'COLLEGE', '1st Year'],
            ['COL-IT2A', 'IT 2nd Year Section A', 'COLLEGE', '2nd Year']
        ];
        
        foreach ($sampleSections as $section) {
            $pdo->prepare("INSERT INTO sections (section_code, section_name, program, year_level) VALUES (?, ?, ?, ?)")
                ->execute($section);
        }
        echo "✓ Sample sections created\n";
    } else {
        echo "✓ Sections already exist\n";
    }

    // Insert sample teachers if empty
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM teachers");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $sampleTeachers = [
            ['Ms. Maria Santos', 'SHS', 'Mathematics'],
            ['Mr. Juan Dela Cruz', 'SHS', 'English'],
            ['Dr. Ana Rodriguez', 'COLLEGE', 'Computer Programming'],
            ['Prof. Carlos Mendoza', 'COLLEGE', 'Database Systems'],
            ['Ms. Linda Garcia', 'SHS', 'Science']
        ];
        
        foreach ($sampleTeachers as $teacher) {
            $pdo->prepare("INSERT INTO teachers (name, department, subject) VALUES (?, ?, ?)")
                ->execute($teacher);
        }
        echo "✓ Sample teachers created\n";
    } else {
        echo "✓ Teachers already exist\n";
    }

    echo "\n=== Hybrid Database Setup Complete! ===\n\n";
    echo "📊 System Configuration:\n";
    echo "✓ Database: Teachers, Sections, Evaluations\n";
    echo "✓ Google Sheets: Student authentication & enrollment data\n\n";
    
    echo "📋 Next Steps:\n";
    echo "1. Set up your Google Sheet with student data\n";
    echo "2. Configure teacher-section assignments\n";
    echo "3. Test student login with Google Sheets data\n\n";
    
    echo "📝 Google Sheets Format (Students sheet):\n";
    echo "Column A: Student_ID\n";
    echo "Column B: Full_Name\n";
    echo "Column C: Section\n";
    echo "Column D: Program (SHS/COLLEGE)\n";
    echo "Column E: Username\n";
    echo "Column F: Password (hashed)\n\n";

    // Log setup completion
    $pdo->prepare("INSERT INTO activity_log (action, description, status, user_id) VALUES (?, ?, ?, ?)")
        ->execute(['database_setup', 'Hybrid database setup completed successfully', 'success', 'system']);

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
