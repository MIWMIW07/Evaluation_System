<?php
// database_setup.php - Database initialization and table creation
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Security check - Only allow access during setup phase
// You can comment out these lines during initial setup, then uncomment for security
/*
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(403);
    die('Access Denied: Admin access required for database setup.');
}
*/

// Include database connection with correct path
require_once 'includes/db_connection.php';

$setup_messages = [];
$errors = [];

try {
    $pdo = getDatabaseConnection();
    $setup_messages[] = "✅ Database connection successful!";
    
    // Create users table
    $create_users_table = "CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        user_type VARCHAR(20) NOT NULL DEFAULT 'student',
        full_name VARCHAR(100) NOT NULL,
        student_id VARCHAR(20),
        program VARCHAR(50),
        section VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL
    )";
    
    $pdo->exec($create_users_table);
    $setup_messages[] = "✅ Users table created/verified";
    
    // Create teachers table
    $create_teachers_table = "CREATE TABLE IF NOT EXISTS teachers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject VARCHAR(100) NOT NULL,
        program VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($create_teachers_table);
    $setup_messages[] = "✅ Teachers table created/verified";
    
    // Create evaluations table
    $create_evaluations_table = "CREATE TABLE IF NOT EXISTS evaluations (
        id SERIAL PRIMARY KEY,
        user_id INTEGER REFERENCES users(id),
        student_id VARCHAR(20) NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        section VARCHAR(50) NOT NULL,
        program VARCHAR(50) NOT NULL,
        teacher_id INTEGER REFERENCES teachers(id),
        subject VARCHAR(100) NOT NULL,
        q1_1 INTEGER NOT NULL CHECK (q1_1 >= 1 AND q1_1 <= 5),
        q1_2 INTEGER NOT NULL CHECK (q1_2 >= 1 AND q1_2 <= 5),
        q1_3 INTEGER NOT NULL CHECK (q1_3 >= 1 AND q1_3 <= 5),
        q1_4 INTEGER NOT NULL CHECK (q1_4 >= 1 AND q1_4 <= 5),
        q1_5 INTEGER NOT NULL CHECK (q1_5 >= 1 AND q1_5 <= 5),
        q1_6 INTEGER NOT NULL CHECK (q1_6 >= 1 AND q1_6 <= 5),
        q2_1 INTEGER NOT NULL CHECK (q2_1 >= 1 AND q2_1 <= 5),
        q2_2 INTEGER NOT NULL CHECK (q2_2 >= 1 AND q2_2 <= 5),
        q2_3 INTEGER NOT NULL CHECK (q2_3 >= 1 AND q2_3 <= 5),
        q2_4 INTEGER NOT NULL CHECK (q2_4 >= 1 AND q2_4 <= 5),
        q3_1 INTEGER NOT NULL CHECK (q3_1 >= 1 AND q3_1 <= 5),
        q3_2 INTEGER NOT NULL CHECK (q3_2 >= 1 AND q3_2 <= 5),
        q3_3 INTEGER NOT NULL CHECK (q3_3 >= 1 AND q3_3 <= 5),
        q3_4 INTEGER NOT NULL CHECK (q3_4 >= 1 AND q3_4 <= 5),
        q4_1 INTEGER NOT NULL CHECK (q4_1 >= 1 AND q4_1 <= 5),
        q4_2 INTEGER NOT NULL CHECK (q4_2 >= 1 AND q4_2 <= 5),
        q4_3 INTEGER NOT NULL CHECK (q4_3 >= 1 AND q4_3 <= 5),
        q4_4 INTEGER NOT NULL CHECK (q4_4 >= 1 AND q4_4 <= 5),
        q4_5 INTEGER NOT NULL CHECK (q4_5 >= 1 AND q4_5 <= 5),
        q4_6 INTEGER NOT NULL CHECK (q4_6 >= 1 AND q4_6 <= 5),
        comments TEXT,
        evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, teacher_id)
    )";
    
    $pdo->exec($create_evaluations_table);
    $setup_messages[] = "✅ Evaluations table created/verified";
    
    // Insert sample admin user (only if doesn't exist)
    $check_admin = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check_admin->execute(['admin']);
    
    if ($check_admin->fetchColumn() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $insert_admin = $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, ?, ?)");
        $insert_admin->execute(['admin', $admin_password, 'admin', 'System Administrator']);
        $setup_messages[] = "✅ Admin user created (username: admin, password: admin123)";
    } else {
        $setup_messages[] = "ℹ️ Admin user already exists";
    }
    
    // Insert sample student users
    $sample_students = [
        ['student1', 'pass123', 'Juan Dela Cruz', 'STU001', 'SHS', 'Grade 11-A'],
        ['student2', 'pass123', 'Maria Santos', 'STU002', 'SHS', 'Grade 12-A'],
        ['student3', 'pass123', 'Pedro Garcia', 'STU003', 'COLLEGE', 'BSIT-1A'],
        ['student4', 'pass123', 'Ana Reyes', 'STU004', 'COLLEGE', 'BSCS-1A']
    ];
    
    foreach ($sample_students as $student) {
        $check_student = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check_student->execute([$student[0]]);
        
        if ($check_student->fetchColumn() == 0) {
            $student_password = password_hash($student[1], PASSWORD_DEFAULT);
            $insert_student = $pdo->prepare("INSERT INTO users (username, password, user_type, full_name, student_id, program, section) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert_student->execute([
                $student[0], $student_password, 'student', 
                $student[2], $student[3], $student[4], $student[5]
            ]);
            $setup_messages[] = "✅ Student user created: {$student[0]} ({$student[2]})";
        }
    }
    
    // Insert sample teachers
    $sample_teachers = [
        ['Prof. Roberto Martinez', 'Mathematics', 'SHS'],
        ['Prof. Elena Rodriguez', 'English', 'SHS'],
        ['Prof. Michael Chen', 'Science', 'SHS'],
        ['Dr. Sarah Johnson', 'Programming Fundamentals', 'COLLEGE'],
        ['Prof. David Lopez', 'Database Systems', 'COLLEGE'],
        ['Dr. Lisa Wang', 'Web Development', 'COLLEGE'],
        ['Prof. Carlos Mendoza', 'Business Management', 'COLLEGE']
    ];
    
    foreach ($sample_teachers as $teacher) {
        $check_teacher = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE name = ? AND subject = ?");
        $check_teacher->execute([$teacher[0], $teacher[1]]);
        
        if ($check_teacher->fetchColumn() == 0) {
            $insert_teacher = $pdo->prepare("INSERT INTO teachers (name, subject, program) VALUES (?, ?, ?)");
            $insert_teacher->execute($teacher);
            $setup_messages[] = "✅ Teacher created: {$teacher[0]} - {$teacher[1]}";
        }
    }
    
    $setup_messages[] = "🎉 Database setup completed successfully!";
    
} catch (Exception $e) {
    $errors[] = "❌ Database setup failed: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Teacher Evaluation System</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-radius: 15px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 3px solid #4CAF50;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2.2em;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .setup-results {
            margin-bottom: 30px;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            font-weight: 500;
        }
        
        .message.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 5px solid #28a745;
        }
        
        .message.error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 5px solid #dc3545;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            margin: 10px 5px;
        }
        
        .btn:hover {
            background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(76, 175, 80, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            box-shadow: 0 4px 15px rgba(33, 150, 243, 0.3);
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #1976D2 0%, #2196F3 100%);
            box-shadow: 0 8px 25px rgba(33, 150, 243, 0.4);
        }
        
        .action-buttons {
            text-align: center;
            margin-top: 30px;
        }
        
        .demo-accounts {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            padding: 25px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 5px solid #ffc107;
        }
        
        .demo-accounts h3 {
            color: #856404;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .account-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .account-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .account-type {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .credentials {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 8px;
        }
        
        .warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            border-left: 5px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Database Setup</h1>
            <p>Teacher Evaluation System</p>
            <p>Setting up database for Railway PostgreSQL deployment...</p>
        </div>
        
        <div class="setup-results">
            <?php if (!empty($setup_messages)): ?>
                <?php foreach ($setup_messages as $message): ?>
                    <div class="message success"><?php echo htmlspecialchars($message); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (empty($errors)): ?>
            <div class="demo-accounts">
                <h3>📋 Demo Accounts Created</h3>
                <div class="account-grid">
                    <div class="account-card">
                        <div class="account-type">🔑 Admin Account</div>
                        <div class="credentials">Username: admin<br>Password: admin123</div>
                        <small>System Administrator Access</small>
                    </div>
                    
                    <div class="account-card">
                        <div class="account-type">🎓 Student Accounts</div>
                        <div class="credentials">
                            student1 / pass123 (SHS)<br>
                            student2 / pass123 (SHS)<br>
                            student3 / pass123 (College)<br>
                            student4 / pass123 (College)
                        </div>
                        <small>Sample student accounts</small>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="login.php" class="btn">🚀 Go to Login</a>
            <a href="test_connection.php" class="btn btn-secondary">🔍 Test Database Connection</a>
            <?php if (!empty($errors)): ?>
                <a href="database_setup.php" class="btn" onclick="location.reload();">🔄 Retry Setup</a>
            <?php endif; ?>
        </div>
        
        <div class="warning">
            <h3>⚠️ Security Notice</h3>
            <p><strong>Important:</strong> After completing the initial setup, you should restrict access to this database setup page by uncommenting the security check code in the file, or remove this file from production.</p>
            <p>This page should only be accessible during initial deployment setup.</p>
        </div>
        
        <div style="text-align: center; margin-top: 40px; padding-top: 25px; border-top: 2px solid #e9ecef; color: #6c757d;">
            <p><strong>© 2025 Philippine Technological Institute of Science Arts and Trade, Inc.</strong></p>
            <p>Teacher Evaluation System - Database Setup</p>
        </div>
    </div>
</body>
</html>
