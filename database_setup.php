<?php
// database_setup.php
// Hybrid setup: PostgreSQL for teachers/evaluations/admin, Google Sheets for students

require_once 'includes/db_connection.php';

// ✅ If database is not available, show info page
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
                    <li><strong>Students Data:</strong> Stored in Google Sheets</li>
                    <li><strong>Teachers & Evaluations:</strong> Stored in PostgreSQL</li>
                </ul>
                
                <p><strong>Next Steps:</strong></p>
                <ol>
                    <li>Set up your PostgreSQL database on Railway</li>
                    <li>Ensure <code>DATABASE_URL</code> environment variable is set</li>
                    <li>Reload this setup to create teacher/evaluation tables</li>
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
    echo "🔧 Setting up hybrid database system...\n\n";

    // ==============================
    // TABLE CREATION (Postgres syntax)
    // ==============================

    // Sections
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sections (
            id SERIAL PRIMARY KEY,
            section_code VARCHAR(20) NOT NULL UNIQUE,
            section_name VARCHAR(100) NOT NULL,
            program VARCHAR(10) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
            year_level VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_section_code ON sections(section_code);
        CREATE INDEX IF NOT EXISTS idx_program ON sections(program);
    ");
    echo "✓ Sections table ready\n";

    // Teachers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teachers (
            id SERIAL PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            department VARCHAR(10) NOT NULL CHECK (department IN ('SHS', 'COLLEGE', 'BOTH')),
            subject VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_name ON teachers(name);
        CREATE INDEX IF NOT EXISTS idx_department ON teachers(department);
    ");
    echo "✓ Teachers table ready\n";

    // Teacher-Section assignments
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_sections (
            id SERIAL PRIMARY KEY,
            teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
            section_id INT NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
            subject VARCHAR(100),
            school_year VARCHAR(20) DEFAULT '2025-2026',
            semester VARCHAR(10) DEFAULT '1st' CHECK (semester IN ('1st','2nd')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (teacher_id, section_id, subject, school_year, semester)
        );
        CREATE INDEX IF NOT EXISTS idx_teacher ON teacher_sections(teacher_id);
        CREATE INDEX IF NOT EXISTS idx_section ON teacher_sections(section_id);
    ");
    echo "✓ Teacher-Sections table ready\n";

    // Evaluations
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS evaluations (
            id SERIAL PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            student_name VARCHAR(100) NOT NULL,
            teacher_id INT NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
            section VARCHAR(50) NOT NULL,
            program VARCHAR(10) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
            subject VARCHAR(100),

            -- Teaching Ability (6 questions)
            q1_1 SMALLINT NOT NULL CHECK (q1_1 BETWEEN 1 AND 5),
            q1_2 SMALLINT NOT NULL CHECK (q1_2 BETWEEN 1 AND 5),
            q1_3 SMALLINT NOT NULL CHECK (q1_3 BETWEEN 1 AND 5),
            q1_4 SMALLINT NOT NULL CHECK (q1_4 BETWEEN 1 AND 5),
            q1_5 SMALLINT NOT NULL CHECK (q1_5 BETWEEN 1 AND 5),
            q1_6 SMALLINT NOT NULL CHECK (q1_6 BETWEEN 1 AND 5),

            -- Management Skills (4 questions)
            q2_1 SMALLINT NOT NULL CHECK (q2_1 BETWEEN 1 AND 5),
            q2_2 SMALLINT NOT NULL CHECK (q2_2 BETWEEN 1 AND 5),
            q2_3 SMALLINT NOT NULL CHECK (q2_3 BETWEEN 1 AND 5),
            q2_4 SMALLINT NOT NULL CHECK (q2_4 BETWEEN 1 AND 5),

            -- Guidance Skills (4 questions)
            q3_1 SMALLINT NOT NULL CHECK (q3_1 BETWEEN 1 AND 5),
            q3_2 SMALLINT NOT NULL CHECK (q3_2 BETWEEN 1 AND 5),
            q3_3 SMALLINT NOT NULL CHECK (q3_3 BETWEEN 1 AND 5),
            q3_4 SMALLINT NOT NULL CHECK (q3_4 BETWEEN 1 AND 5),

            -- Personal & Social (6 questions)
            q4_1 SMALLINT NOT NULL CHECK (q4_1 BETWEEN 1 AND 5),
            q4_2 SMALLINT NOT NULL CHECK (q4_2 BETWEEN 1 AND 5),
            q4_3 SMALLINT NOT NULL CHECK (q4_3 BETWEEN 1 AND 5),
            q4_4 SMALLINT NOT NULL CHECK (q4_4 BETWEEN 1 AND 5),
            q4_5 SMALLINT NOT NULL CHECK (q4_5 BETWEEN 1 AND 5),
            q4_6 SMALLINT NOT NULL CHECK (q4_6 BETWEEN 1 AND 5),

            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL,
            UNIQUE (student_id, teacher_id)
        );
        CREATE INDEX IF NOT EXISTS idx_student ON evaluations(student_id);
        CREATE INDEX IF NOT EXISTS idx_teacher_eval ON evaluations(teacher_id);
        CREATE INDEX IF NOT EXISTS idx_section_eval ON evaluations(section);
        CREATE INDEX IF NOT EXISTS idx_program_eval ON evaluations(program);
        CREATE INDEX IF NOT EXISTS idx_created_eval ON evaluations(created_at);
    ");
    echo "✓ Evaluations table ready\n";

    // Admin Users
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id SERIAL PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            user_type VARCHAR(20) DEFAULT 'admin' CHECK (user_type IN ('admin')),
            full_name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        );
        CREATE INDEX IF NOT EXISTS idx_username ON users(username);
        CREATE INDEX IF NOT EXISTS idx_user_type ON users(user_type);
    ");
    echo "✓ Admin users table ready\n";

    // Activity Log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_log (
            id SERIAL PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            status VARCHAR(10) DEFAULT 'success' CHECK (status IN ('success','error','warning')),
            user_id VARCHAR(50),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_timestamp ON activity_log(timestamp);
        CREATE INDEX IF NOT EXISTS idx_user ON activity_log(user_id);
        CREATE INDEX IF NOT EXISTS idx_status ON activity_log(status);
    ");
    echo "✓ Activity log table ready\n";

    // ==============================
    // DEFAULT DATA
    // ==============================

    // Default Admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, 'admin', 'System Administrator')")
            ->execute(['admin', $adminPass]);
        echo "✓ Default admin created (username: admin, password: admin123)\n";
    }

    // Default Sections
    $stmt = $pdo->query("SELECT COUNT(*) FROM sections");
    if ($stmt->fetchColumn() == 0) {
        $sections = [
            ['SHS-11A', 'Grade 11 Section A', 'SHS', 'Grade 11'],
            ['SHS-12A', 'Grade 12 Section A', 'SHS', 'Grade 12'],
            ['COL-IT1A', 'IT 1st Year A', 'COLLEGE', '1st Year'],
            ['COL-IT2A', 'IT 2nd Year A', 'COLLEGE', '2nd Year']
        ];
        foreach ($sections as $s) {
            $pdo->prepare("INSERT INTO sections (section_code, section_name, program, year_level) VALUES (?, ?, ?, ?)")
                ->execute($s);
        }
        echo "✓ Sample sections created\n";
    }

    // Default Teachers
    $stmt = $pdo->query("SELECT COUNT(*) FROM teachers");
    if ($stmt->fetchColumn() == 0) {
        $teachers = [
            ['Ms. Maria Santos', 'SHS', 'Mathematics'],
            ['Mr. Juan Dela Cruz', 'SHS', 'English'],
            ['Dr. Ana Rodriguez', 'COLLEGE', 'Programming'],
            ['Prof. Carlos Mendoza', 'COLLEGE', 'Database Systems']
        ];
        foreach ($teachers as $t) {
            $pdo->prepare("INSERT INTO teachers (name, department, subject) VALUES (?, ?, ?)")
                ->execute($t);
        }
        echo "✓ Sample teachers created\n";
    }

    // ==============================
    // SUMMARY
    // ==============================

    echo "\n=== ✅ Hybrid Database Setup Complete ===\n\n";
    echo "📊 PostgreSQL holds: Teachers, Sections, Evaluations, Admins\n";
    echo "📑 Google Sheets holds: Student authentication & enrollment\n\n";
    echo "📝 Student Login (via Google Sheets):\n";
    echo "• Username: LASTNAMEFIRSTNAME (uppercase, no spaces)\n";
    echo "• Password: pass123\n\n";

    // Log completion
    $pdo->prepare("INSERT INTO activity_log (action, description, status, user_id) VALUES (?, ?, ?, ?)")
        ->execute(['setup', 'Hybrid DB setup completed', 'success', 'system']);

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
    exit(1);
}
?>
