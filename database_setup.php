<?php

require_once 'security.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}
// Enhanced database setup for Teacher Evaluation System with Login
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Enhanced Database Setup for Teacher Evaluation System</h2>";

// Use the same connection logic as db_connection.php
require_once 'db_connection.php';

if (!isset($conn)) {
    die("<p style='color:red;'>❌ Could not establish database connection. Please check your Railway PostgreSQL service.</p>");
}

echo "<p style='color:green;'>✅ Connected to PostgreSQL database successfully!</p>";

try {
    // Create users table for login system
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        user_type VARCHAR(20) NOT NULL CHECK (user_type IN ('student', 'admin')),
        full_name VARCHAR(100) NOT NULL,
        student_id VARCHAR(50), -- Only for students
        program VARCHAR(50), -- Only for students (SHS or COLLEGE)
        section VARCHAR(50), -- Only for students
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP
    )";

    // Add this code to database_setup.php after table creation
try {
    // Check if program column exists in teachers table
    $check_column = query("SELECT column_name FROM information_schema.columns WHERE table_name='teachers' AND column_name='program'");
    $column_exists = fetch_assoc($check_column);
    
    if (!$column_exists) {
        // Add the program column to teachers table
        query("ALTER TABLE teachers ADD COLUMN program VARCHAR(20) NOT NULL DEFAULT 'SHS' CHECK (program IN ('SHS', 'COLLEGE'))");
        echo "<p style='color:green;'>✅ Added 'program' column to teachers table</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:orange;'>⚠️ Could not check/add 'program' column: " . $e->getMessage() . "</p>";
}

    if (query($sql)) {
        echo "<p style='color:green;'>✅ Users table created successfully</p>";
    } else {
        echo "<p style='color:red;'>❌ Error creating users table</p>";
    }


// Add this to your database setup script
$sql = "CREATE TABLE IF NOT EXISTS login_attempts (
    id SERIAL PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(255) NOT NULL,
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP NULL
)";

if (query($sql)) {
    echo "<p style='color:green;'>✅ Login attempts table created successfully</p>";
} else {
    echo "<p style='color:red;'>❌ Error creating login attempts table</p>";
}
    
    // Enhanced teachers table with program field
    $sql = "CREATE TABLE IF NOT EXISTS teachers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject VARCHAR(100) NOT NULL,
        program VARCHAR(20) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if (query($sql)) {
        echo "<p style='color:green;'>✅ Teachers table created/updated successfully</p>";
    } else {
        echo "<p style='color:red;'>❌ Error creating teachers table</p>";
    }

    // Enhanced evaluations table
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

    // Add user_id column to evaluations table if it doesn't exist
$check_column = query("SELECT column_name FROM information_schema.columns 
                      WHERE table_name='evaluations' AND column_name='user_id'");
if (count(fetch_all($check_column)) == 0) {
    query("ALTER TABLE evaluations ADD COLUMN user_id INTEGER REFERENCES users(id)");
    echo "✅ Added user_id column to evaluations table";
}
    if (query($sql)) {
        echo "<p style='color:green;'>✅ Evaluations table created/updated successfully</p>";
    } else {
        echo "<p style='color:red;'>❌ Error creating evaluations table</p>";
    }

    // Check if default admin exists
    $check_admin = query("SELECT COUNT(*) as count FROM users WHERE user_type = 'admin'");
    $admin_row = fetch_assoc($check_admin);

    if ($admin_row['count'] == 0) {
        // Create default admin account
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        query("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, 'admin', 'System Administrator')", 
              ['admin', $admin_password]);
        echo "<p style='color:green;'>✅ Default admin account created (Username: admin, Password: admin123)</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Admin account already exists</p>";
    }

    // Check if teachers already exist
    $check_stmt = query("SELECT COUNT(*) as count FROM teachers");
    $row = fetch_assoc($check_stmt);

    if ($row['count'] == 0) {
        // Insert sample teachers with programs
        $teachers = [
            // SHS Teachers
            ['ABSALON, JIMMY P.', 'Computer Science', 'SHS'],
            ['CRUZ, MARIA SANTOS', 'Mathematics', 'SHS'],
            ['REYES, JUAN DELA', 'English Literature', 'SHS'],
            ['SANTOS, ANNA LOPEZ', 'General Science', 'SHS'],
            ['GARCIA, PEDRO MANUEL', 'Physics', 'SHS'],
            ['TORRES, ELENA ROSE', 'Chemistry', 'SHS'],
            ['VALDEZ, ROBERTO CARLOS', 'History', 'SHS'],
            ['MENDOZA, SARAH JANE', 'Filipino', 'SHS'],
            
            // College Teachers
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
                echo "<p style='color:orange;'>⚠️ Error inserting {$teacher[0]}: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<p style='color:green;'>✅ $inserted sample teachers inserted successfully</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Teachers already exist ({$row['count']} found), skipping insertion</p>";
    }

    // Create some sample student accounts
    $check_students = query("SELECT COUNT(*) as count FROM users WHERE user_type = 'student'");
    $student_row = fetch_assoc($check_students);

    if ($student_row['count'] == 0) {
        $sample_students = [
            ['student1', 'pass123', 'Juan Dela Cruz', 'STU001', 'SHS', 'A'],
            ['student2', 'pass123', 'Maria Santos', 'STU002', 'SHS', 'B'],
            ['student3', 'pass123', 'Pedro Garcia', 'STU003', 'COLLEGE', 'BSIT-1A'],
            ['student4', 'pass123', 'Ana Reyes', 'STU004', 'COLLEGE', 'BSCS-2B']
        ];

        $inserted_students = 0;
        foreach ($sample_students as $student) {
            try {
                $hashed_password = password_hash($student[1], PASSWORD_DEFAULT);
                query("INSERT INTO users (username, password, user_type, full_name, student_id, program, section) VALUES (?, ?, 'student', ?, ?, ?, ?)", 
                      [$student[0], $hashed_password, $student[2], $student[3], $student[4], $student[5]]);
                $inserted_students++;
            } catch (Exception $e) {
                echo "<p style='color:orange;'>⚠️ Error inserting student {$student[0]}: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<p style='color:green;'>✅ $inserted_students sample student accounts created</p>";
        echo "<p style='color:blue;'>Sample Student Logins: student1/pass123, student2/pass123, student3/pass123, student4/pass123</p>";
    }

    // Show current data
    echo "<h3>Current Users in Database:</h3>";
    $users_stmt = query("SELECT id, username, user_type, full_name, program, section FROM users ORDER BY user_type, full_name");
    $users = fetch_all($users_stmt);

    if (count($users) > 0) {
        echo "<table border='1' style='border-collapse:collapse; width:100%; margin:10px 0;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Type</th><th>Full Name</th><th>Program</th><th>Section</th></tr>";
        foreach($users as $row) {
            echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td><td>{$row['user_type']}</td><td>{$row['full_name']}</td><td>" . ($row['program'] ?: '-') . "</td><td>" . ($row['section'] ?: '-') . "</td></tr>";
        }
        echo "</table>";
    }

    echo "<h3>Current Teachers by Program:</h3>";
    $teachers_stmt = query("SELECT id, name, subject, program FROM teachers ORDER BY program, name");
    $teachers = fetch_all($teachers_stmt);

    if (count($teachers) > 0) {
        echo "<table border='1' style='border-collapse:collapse; width:100%; margin:10px 0;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Subject</th><th>Program</th></tr>";
        foreach($teachers as $row) {
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['subject']}</td><td>{$row['program']}</td></tr>";
        }
        echo "</table>";
    }

    echo "<p style='color:green; font-weight:bold; margin:20px 0;'>🎉 Enhanced database setup completed successfully!</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error during setup: " . $e->getMessage() . "</p>";
}

echo "<p><a href='login.php' style='background:#4CAF50; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Go to Login Page</a></p>";
echo "<p><a href='admin.php' style='background:#2196F3; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; margin-left:10px;'>Go to Admin Dashboard</a></p>";
?>





