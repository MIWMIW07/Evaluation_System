<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS evaluation_db";
if ($conn->query($sql)) {
    echo "Database created successfully<br>";
} else {
    echo "Error creating database: " . $conn->error . "<br>";
}

// Select database
$conn->select_db("evaluation_db");

// Create teachers table
$sql = "CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(100) NOT NULL
)";

if ($conn->query($sql)) {
    echo "Teachers table created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Create evaluations table
$sql = "CREATE TABLE IF NOT EXISTS evaluations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(50) NOT NULL,
    student_name VARCHAR(100) NOT NULL,
    section VARCHAR(50) NOT NULL,
    program VARCHAR(100) NOT NULL,
    teacher_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    q1_1 INT NOT NULL,
    q1_2 INT NOT NULL,
    q1_3 INT NOT NULL,
    q1_4 INT NOT NULL,
    q1_5 INT NOT NULL,
    q1_6 INT NOT NULL,
    q2_1 INT NOT NULL,
    q2_2 INT NOT NULL,
    q2_3 INT NOT NULL,
    q2_4 INT NOT NULL,
    q3_1 INT NOT NULL,
    q3_2 INT NOT NULL,
    q3_3 INT NOT NULL,
    q3_4 INT NOT NULL,
    q4_1 INT NOT NULL,
    q4_2 INT NOT NULL,
    q4_3 INT NOT NULL,
    q4_4 INT NOT NULL,
    q4_5 INT NOT NULL,
    q4_6 INT NOT NULL,
    comments TEXT,
    evaluation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Evaluations table created successfully<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert sample teachers
$sql = "INSERT IGNORE INTO teachers (name, subject) VALUES
    ('ABSALON, JIMMY P.', 'Computer Science'),
    ('CRUZ, MARIA SANTOS', 'Mathematics'),
    ('REYES, JUAN DELA', 'English'),
    ('SANTOS, ANNA LOPEZ', 'Science')";

if ($conn->query($sql)) {
    echo "Sample teachers inserted successfully<br>";
} else {
    echo "Error inserting teachers: " . $conn->error . "<br>";
}

echo "Database setup completed!";

$conn->close();
?>