<?php
// database_setup.php - Fixed version (no admin check for initial setup)
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>Database Setup - Teacher Evaluation System</title>
<style>
body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f5f5; }
.container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
.success { color: #155724; background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0; border-radius: 5px; }
.error { color: #721c24; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0; border-radius: 5px; }
.warning { color: #856404; background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 5px; }
.info { color: #0c5460; background: #d1ecf1; border: 1px solid #bee5eb; padding: 10px; margin: 10px 0; border-radius: 5px; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f8f9fa; }
.btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
.btn:hover { background: #0056b3; }
.btn-success { background: #28a745; }
.btn-success:hover { background: #218838; }
</style></head><body><div class='container'>";

echo "<h1>🔧 Database Setup - Teacher Evaluation System</h1>";
echo "<p>Setting up database for Railway PostgreSQL deployment...</p>";

// Include database connection
require_once 'db_connection.php';

if (!isset($conn)) {
    echo "<div class='error'>❌ Could not establish database connection. Please check your Railway PostgreSQL service configuration.</div>";
    echo "<div class='info'><h4>Environment Variables Check:</h4>";
    echo "<ul>";
    echo "<li><strong>DATABASE_URL:</strong> " . (getenv('DATABASE_URL') ? 'Set' : 'Not Set') . "</li>";
    echo "<li><strong>PGHOST:</strong> " . (getenv('PGHOST') ? getenv('PGHOST') : 'Not Set') . "</li>";
    echo "<li><strong>PGDATABASE:</strong> " . (getenv('PGDATABASE') ? getenv('PGDATABASE') : 'Not Set') . "</li>";
    echo "<li><strong>PGUSER:</strong> " . (getenv('PGUSER') ? getenv('PGUSER') : 'Not Set') . "</li>";
    echo "<li><strong>PGPASSWORD:</strong> " . (getenv('PGPASSWORD') ? 'Set' : 'Not Set') . "</li>";
    echo "</ul></div>";
    echo "<p><a href='login.php' class='btn'>← Go to Login</a></p>";
    echo "</div></body></html>";
    exit;
}

echo "<div class='success'>✅ Connected to PostgreSQL database successfully!</div>";

try {
    // Create users table for login system
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        user_type VARCHAR(20) NOT NULL CHECK (user_type IN ('student', 'admin')),
        full_name VARCHAR(100) NOT NULL,
        student_id VARCHAR(50),
        program VARCHAR(50),
        section VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP
    )";

    if (query($sql)) {
        echo "<div class='success'>✅ Users table created successfully</div>";
    }

    // Create login attempts table for security
    $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
        id SERIAL PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        username VARCHAR(255) NOT NULL,
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        locked_until TIMESTAMP NULL
    )";

    if (query($sql)) {
        echo "<div class='success'>✅ Login attempts table created successfully</div>";
    }
    
    // Create teachers table with program field
    $sql = "CREATE TABLE IF NOT EXISTS teachers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject VARCHAR(100) NOT NULL,
        program VARCHAR(20) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if (query($sql)) {
        echo "<div class='success'>✅ Teachers table created successfully</div>";
    }

    // Create evaluations table
    $sql = "CREATE TABLE IF NOT EXISTS evaluations (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        student_id VARCHAR(50) NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        section VARCHAR(50) NOT NULL,
        program VARCHAR(100) NOT NULL,
        teacher_id INTEGER NOT NULL REFERENCES teachers(id) ON DELETE CASCADE,
        subject VARCHAR(100) NOT NULL,
        q1_1 INTEGER NOT NULL CHECK (q1_1 BETWEEN 1 AND 5),
        q1_2 INTEGER NOT NULL CHECK (q1_2 BETWEEN 1 AND 5),
        q1_3 INTEGER NOT NULL CHECK (q1_3 BETWEEN 1 AND 5),
        q1_4 INTEGER NOT NULL CHECK (q1_4 BETWEEN 1 AND 5),
        q1_5 INTEGER NOT NULL CHECK (q1_5 BETWEEN 1 AND 5),
        q1_6 INTEGER NOT NULL CHECK (q1_6 BETWEEN 1 AND 5),
        q2_1 INTEGER NOT NULL CHECK (q2_1 BETWEEN 1 AND 5),
        q2_2 INTEGER NOT NULL CHECK (q2_2 BETWEEN 1 AND 5),
        q2_3 INTEGER NOT NULL CHECK (q2_3 BETWEEN 1 AND 5),
        q2_4 INTEGER NOT NULL CHECK (q2_4 BETWEEN 1 AND 5),
        q3_1 INTEGER NOT NULL CHECK (q3_1 BETWEEN 1 AND 5),
        q3_2 INTEGER NOT NULL CHECK (q3_2 BETWEEN 1 AND 5),
        q3_3 INTEGER NOT NULL CHECK (q3_3 BETWEEN 1 AND 5),
        q3_4 INTEGER NOT NULL CHECK (q3_4 BETWEEN 1 AND 5),
        q4_1 INTEGER NOT NULL CHECK (q4_1 BETWEEN 1 AND 5),
        q4_2 INTEGER NOT NULL CHECK (q4_2 BETWEEN 1 AND 5),
        q4_3 INTEGER NOT NULL CHECK (q4_3 BETWEEN 1 AND 5),
        q4_4 INTEGER NOT NULL CHECK (q4_4 BETWEEN 1 AND 5),
        q4_5 INTEGER NOT NULL CHECK (q4_5 BETWEEN 1 AND 5),
        q4_6 INTEGER NOT NULL CHECK (q4_6 BETWEEN 1 AND 5),
        comments TEXT,
        evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, teacher_id)
    )";

    if (query($sql)) {
        echo "<div class='success'>✅ Evaluations table created successfully</div>";
    }

    // Check if default admin exists
    $check_admin = query("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
    $admin_row = fetch_assoc($check_admin);

    if ($admin_row['count'] == 0) {
        // Create default admin account
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        query("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, 'admin', 'System Administrator')", 
              ['admin', $admin_password]);
        echo "<div class='success'>✅ Default admin account created</div>";
        echo "<div class='info'><strong>Admin Login:</strong> Username: <code>admin</code> | Password: <code>admin123</code></div>";
    } else {
        echo "<div class='info'>ℹ️ Admin account already exists</div>";
    }

    // Check if teachers already exist
    $check_stmt = query("SELECT COUNT(*) as count FROM teachers");
    $row = fetch_assoc($check_stmt);

    if ($row['count'] == 0) {
        // Insert sample teachers with programs
        $teachers = [
            ['ABSALON, JIMMY P.', 'Computer Science', 'SHS'],
            ['CRUZ, MARIA SANTOS', 'Mathematics', 'SHS'],
            ['REYES, JUAN DELA', 'English Literature', 'SHS'],
            ['SANTOS, ANNA LOPEZ', 'General Science', 'SHS'],
            ['GARCIA, PEDRO MANUEL', 'Physics', 'SHS'],
            ['TORRES, ELENA ROSE', 'Chemistry', 'SHS'],
            ['DR. JOHNSON, MICHAEL', 'Data Structures', 'COLLEGE'],
            ['PROF. WILLIAMS, JENNIFER', 'Calculus', 'COLLEGE'],
            ['DR. BROWN, ROBERT', 'Database Systems', 'COLLEGE'],
            ['PROF. DAVIS, LISA', 'Business Management', 'COLLEGE'],
            ['DR. MILLER, DAVID', 'Software Engineering', 'COLLEGE'],
            ['PROF. WILSON, AMANDA', 'Statistics', 'COLLEGE']
        ];
        
        $inserted = 0;
        foreach ($teachers as $teacher) {
            try {
                query("INSERT INTO teachers (name, subject, program) VALUES (?, ?, ?)", 
                      [$teacher[0], $teacher[1], $teacher[2]]);
                $inserted++;
            } catch (Exception $e) {
                echo "<div class='warning'>⚠️ Error inserting {$teacher[0]}: " . $e->getMessage() . "</div>";
            }
        }
        
        echo "<div class='success'>✅ $inserted sample teachers inserted successfully</div>";
    } else {
        echo "<div class='info'>ℹ️ Teachers already exist ({$row['count']} found)</div>";
    }

    // Create sample student accounts
    $check_students = query("SELECT COUNT(*) as count FROM users WHERE user_type = 'student'");
    $student_row = fetch_assoc($check_students);

    if ($student_row['count'] == 0) {
        $sample_students = [
            ['student1', 'pass123', 'Juan Dela Cruz', 'STU001', 'SHS', 'Grade 11-A'],
            ['student2', 'pass123', 'Maria Santos', 'STU002', 'SHS', 'Grade 12-B'],
            ['student3', 'pass123', 'Pedro Garcia', 'STU003', 'COLLEGE', 'BSIT-1A'],
            ['student4', 'pass123', 'Ana Reyes', 'STU004', 'COLLEGE', 'BSCS-2A']
        ];

        $inserted_students = 0;
        foreach ($sample_students as $student) {
            try {
                $hashed_password = password_hash($student[1], PASSWORD_DEFAULT);
                query("INSERT INTO users (username, password, user_type, full_name, student_id, program, section) VALUES (?, ?, 'student', ?, ?, ?, ?)", 
                      [$student[0], $hashed_password, $student[2], $student[3], $student[4], $student[5]]);
                $inserted_students++;
            } catch (Exception $e) {
                echo "<div class='warning'>⚠️ Error inserting student {$student[0]}: " . $e->getMessage() . "</div>";
            }
        }
        
        echo "<div class='success'>✅ $inserted_students sample student accounts created</div>";
        echo "<div class='info'><strong>Sample Student Logins:</strong> student1/pass123, student2/pass123, student3/pass123, student4/pass123</div>";
    }

    // Show current data summary
    echo "<h3>📊 Database Summary</h3>";
    
    $users_stmt = query("SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type");
    $users_summary = fetch_all($users_stmt);
    
    echo "<table>";
    echo "<tr><th>User Type</th><th>Count</th></tr>";
    foreach($users_summary as $row) {
        echo "<tr><td>" . ucfirst($row['user_type']) . "</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";

    $teachers_stmt = query("SELECT program, COUNT(*) as count FROM teachers GROUP BY program");
    $teachers_summary = fetch_all($teachers_stmt);
    
    echo "<table>";
    echo "<tr><th>Program</th><th>Teachers Count</th></tr>";
    foreach($teachers_summary as $row) {
        echo "<tr><td>{$row['program']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";

    echo "<div class='success'><h3>🎉 Database setup completed successfully!</h3></div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Error during setup: " . $e->getMessage() . "</div>";
}

echo "<p style='margin-top: 30px;'>";
echo "<a href='login.php' class='btn btn-success'>🔑 Go to Login Page</a>";
echo "<a href='index.php' class='btn'>🏠 Go to Main Page</a>";
echo "</p>";

echo "<div class='info' style='margin-top: 20px;'>";
echo "<h4>🔧 Next Steps:</h4>";
echo "<ol>";
echo "<li>Click 'Go to Login Page' to test the system</li>";
echo "<li>Login with admin credentials to access admin dashboard</li>";
echo "<li>Login with student credentials to test student features</li>";
echo "<li>Check that all features work properly</li>";
echo "</ol>";
echo "</div>";

echo "</div></body></html>";
?>
