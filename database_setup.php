<?php
// Enhanced database setup for Railway deployment with PostgreSQL
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Setup for Teacher Evaluation System</h2>";

// Use the same connection logic as db_connection.php
require_once 'db_connection.php';

if (!isset($conn)) {
    die("<p style='color:red;'>❌ Could not establish database connection. Please check your Railway PostgreSQL service.</p>");
}

echo "<p style='color:green;'>✅ Connected to PostgreSQL database successfully!</p>";

try {
    // Create teachers table
    $sql = "CREATE TABLE IF NOT EXISTS teachers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        subject VARCHAR(100) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if (query($sql)) {
        echo "<p style='color:green;'>✅ Teachers table created successfully</p>";
    } else {
        echo "<p style='color:red;'>❌ Error creating teachers table</p>";
    }

    // Create evaluations table
    $sql = "CREATE TABLE IF NOT EXISTS evaluations (
        id SERIAL PRIMARY KEY,
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
        UNIQUE(student_id, teacher_id)
    )";

    if (query($sql)) {
        echo "<p style='color:green;'>✅ Evaluations table created successfully</p>";
    } else {
        echo "<p style='color:red;'>❌ Error creating evaluations table</p>";
    }

    // Check if teachers already exist
    $check_stmt = query("SELECT COUNT(*) as count FROM teachers");
    $row = fetch_assoc($check_stmt);

    if ($row['count'] == 0) {
        // Insert sample teachers
        $teachers = [
            ['ABSALON, JIMMY P.', 'Computer Science'],
            ['CRUZ, MARIA SANTOS', 'Mathematics'],
            ['REYES, JUAN DELA', 'English Literature'],
            ['SANTOS, ANNA LOPEZ', 'General Science'],
            ['GARCIA, PEDRO MANUEL', 'Physics'],
            ['TORRES, ELENA ROSE', 'Chemistry'],
            ['VALDEZ, ROBERTO CARLOS', 'History'],
            ['MENDOZA, SARAH JANE', 'Filipino']
        ];
        
        $inserted = 0;
        foreach ($teachers as $teacher) {
            try {
                query("INSERT INTO teachers (name, subject) VALUES (?, ?)", [$teacher[0], $teacher[1]]);
                $inserted++;
            } catch (Exception $e) {
                echo "<p style='color:orange;'>⚠️ Error inserting {$teacher[0]}: " . $e->getMessage() . "</p>";
            }
        }
        
        echo "<p style='color:green;'>✅ $inserted sample teachers inserted successfully</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ Teachers already exist ({$row['count']} found), skipping insertion</p>";
    }

    // Show current teachers
    echo "<h3>Current Teachers in Database:</h3>";
    $teachers_stmt = query("SELECT id, name, subject FROM teachers ORDER BY name");
    $teachers = fetch_all($teachers_stmt);

    if (count($teachers) > 0) {
        echo "<table border='1' style='border-collapse:collapse; width:100%; margin:10px 0;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Subject</th></tr>";
        foreach($teachers as $row) {
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['subject']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No teachers found in database.</p>";
    }

    echo "<p style='color:green; font-weight:bold; margin:20px 0;'>🎉 Database setup completed successfully!</p>";

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error during setup: " . $e->getMessage() . "</p>";
}

echo "<p><a href='index.php' style='background:#4CAF50; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Go to Evaluation Form</a></p>";
echo "<p><a href='admin.php' style='background:#2196F3; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; margin-left:10px;'>Go to Admin Dashboard</a></p>";
?>
