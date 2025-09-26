<?php
// database_setup.php - Updated with Google Integration tables

require_once 'includes/db_connection.php';

try {
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

    // Create activity_log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            status ENUM('success', 'error', 'warning') DEFAULT 'success',
            user_id INT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_user (user_id),
            INDEX idx_status (status)
        )
    ");
    echo "✓ Activity log table created/updated\n";

    // Update students table to add section relationship
    $pdo->exec("
        ALTER TABLE students 
        ADD COLUMN IF NOT EXISTS section_id INT,
        ADD COLUMN IF NOT EXISTS student_id VARCHAR(20) UNIQUE,
        ADD INDEX IF NOT EXISTS idx_section (section_id)
    ");
    echo "✓ Students table updated with section relationship\n";

    // Update users table for better student linking
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS student_table_id INT,
        ADD COLUMN IF NOT EXISTS password_changed BOOLEAN DEFAULT FALSE,
        ADD INDEX IF NOT EXISTS idx_student_table (student_table_id)
    ");
    echo "✓ Users table updated with student linking\n";

    // Create teachers table if it doesn't exist
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

    // Create evaluations table if it doesn't exist
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
            FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Evaluations table created/updated\n";

    // Add foreign key constraints if they don't exist
    try {
        $pdo->exec("
            ALTER TABLE students 
            ADD CONSTRAINT fk_students_section 
            FOREIGN KEY (section_id) REFERENCES sections(id) 
            ON DELETE SET NULL
        ");
    } catch (PDOException $e) {
        // Constraint might already exist
        if (strpos($e->getMessage(), 'Duplicate foreign key constraint') === false) {
            echo "Note: Could not add section foreign key - " . $e->getMessage() . "\n";
        }
    }

    try {
        $pdo->exec("
            ALTER TABLE users 
            ADD CONSTRAINT fk_users_student 
            FOREIGN KEY (student_table_id) REFERENCES students(id) 
            ON DELETE SET NULL
        ");
    } catch (PDOException $e) {
        // Constraint might already exist
        if (strpos($e->getMessage(), 'Duplicate foreign key constraint') === false) {
            echo "Note: Could not add user-student foreign key - " . $e->getMessage() . "\n";
        }
    }

    // Create admin user if it doesn't exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, 'admin', 'System Administrator')")
            ->execute(['admin', $adminPassword]);
        echo "✓ Admin user created (username: admin, password: admin123)\n";
    }

    echo "\n=== Database setup completed successfully! ===\n";
    echo "Next steps:\n";
    echo "1. Create credentials/ directory\n";
    echo "2. Add your Google service account JSON file\n";
    echo "3. Create your Google Sheets with student/teacher data\n";
    echo "4. Run composer install to get dependencies\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
