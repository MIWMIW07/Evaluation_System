<?php
// database_setup.php
// Hybrid setup: PostgreSQL for teachers assignments, evaluations, admins
// Google Sheets for student + teacher lists

require_once 'includes/db_connection.php';

// Initialize PDO connection
$pdo = getPDO();


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
                    <li><strong>Teachers List:</strong> Stored in Google Sheets</li>
                    <li><strong>Teacher Assignments, Evaluations, Admins:</strong> Stored in PostgreSQL</li>
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
    echo "🔧 Setting up hybrid database system...\n\n";

    // ==============================
    // Sections
    // ==============================
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

    // ==============================
    // Teacher Assignments
    // ==============================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_assignments (
            id SERIAL PRIMARY KEY,
            teacher_name VARCHAR(100) NOT NULL,
            section_id INT NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
            subject VARCHAR(100) NOT NULL,
            program VARCHAR(10) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
            school_year VARCHAR(20) DEFAULT '2025-2026',
            semester VARCHAR(10) DEFAULT '1st' CHECK (semester IN ('1st','2nd')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (teacher_name, section_id, subject, school_year, semester)
        );
        CREATE INDEX IF NOT EXISTS idx_teacher_assign ON teacher_assignments(teacher_name);
        CREATE INDEX IF NOT EXISTS idx_section_assign ON teacher_assignments(section_id);
    ");
    echo "✓ Teacher assignments table ready\n";

    // ==============================
    // Evaluations
    // ==============================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS evaluations (
            id SERIAL PRIMARY KEY,
            student_id VARCHAR(20) NOT NULL,
            student_name VARCHAR(100) NOT NULL,
            teacher_name VARCHAR(100) NOT NULL,
            section VARCHAR(50) NOT NULL,
            program VARCHAR(10) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
            subject VARCHAR(100),
            q1_1 SMALLINT CHECK (q1_1 BETWEEN 1 AND 5),
            q1_2 SMALLINT CHECK (q1_2 BETWEEN 1 AND 5),
            q1_3 SMALLINT CHECK (q1_3 BETWEEN 1 AND 5),
            q1_4 SMALLINT CHECK (q1_4 BETWEEN 1 AND 5),
            q1_5 SMALLINT CHECK (q1_5 BETWEEN 1 AND 5),
            q1_6 SMALLINT CHECK (q1_6 BETWEEN 1 AND 5),
            q2_1 SMALLINT CHECK (q2_1 BETWEEN 1 AND 5),
            q2_2 SMALLINT CHECK (q2_2 BETWEEN 1 AND 5),
            q2_3 SMALLINT CHECK (q2_3 BETWEEN 1 AND 5),
            q2_4 SMALLINT CHECK (q2_4 BETWEEN 1 AND 5),
            q3_1 SMALLINT CHECK (q3_1 BETWEEN 1 AND 5),
            q3_2 SMALLINT CHECK (q3_2 BETWEEN 1 AND 5),
            q3_3 SMALLINT CHECK (q3_3 BETWEEN 1 AND 5),
            q3_4 SMALLINT CHECK (q3_4 BETWEEN 1 AND 5),
            q4_1 SMALLINT CHECK (q4_1 BETWEEN 1 AND 5),
            q4_2 SMALLINT CHECK (q4_2 BETWEEN 1 AND 5),
            q4_3 SMALLINT CHECK (q4_3 BETWEEN 1 AND 5),
            q4_4 SMALLINT CHECK (q4_4 BETWEEN 1 AND 5),
            q4_5 SMALLINT CHECK (q4_5 BETWEEN 1 AND 5),
            q4_6 SMALLINT CHECK (q4_6 BETWEEN 1 AND 5),
            comments TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            submitted_at TIMESTAMP NULL,
            UNIQUE (student_id, teacher_name, subject)
        );
        CREATE INDEX IF NOT EXISTS idx_student_eval ON evaluations(student_id);
        CREATE INDEX IF NOT EXISTS idx_teacher_eval ON evaluations(teacher_name);
        CREATE INDEX IF NOT EXISTS idx_section_eval ON evaluations(section);
    ");
    echo "✓ Evaluations table ready\n";

    // ==============================
    // Admin Users
    // ==============================
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
    ");
    echo "✓ Admin users table ready\n";

    // ==============================
    // Activity Logs
    // ==============================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id SERIAL PRIMARY KEY,
            user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            status VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    echo "✓ Activity logs table ready\n";

    // ==============================
    // Default Data
    // ==============================
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type = 'admin'");
    if ($stmt->fetchColumn() == 0) {
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, 'admin', 'System Administrator')")
            ->execute(['admin', $adminPass]);
        echo "✓ Default admin created (username: admin, password: admin123)\n";
    }

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

    // ==============================
    // Summary + Activity Log
    // ==============================
    echo "\n=== ✅ Hybrid Database Setup Complete ===\n\n";
    echo "📊 PostgreSQL holds: Sections, Teacher Assignments, Evaluations, Admins\n";
    echo "📑 Google Sheets holds: Student list + Teacher list\n\n";
    echo "📝 Student Login (via Google Sheets):\n";
    echo "• Username: LASTNAMEFIRSTNAME (uppercase, no spaces)\n";
    echo "• Password: pass123\n\n";

    // Log setup completion
    logActivity("setup", "Hybrid DB setup completed", "success", null);

} catch (PDOException $e) {
    logActivity("setup", "Database setup failed: " . $e->getMessage(), "error", null);
    echo "❌ Error: " . $e->getMessage();
    exit(1);
}
?>

