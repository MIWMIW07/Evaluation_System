<?php
// database_setup.php
// Updated to include all real teacher assignments from bulk_insert_teachers.php
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

// Function to clean teacher names
function cleanTeacherName($name) {
    $name = trim($name);
    $name = str_replace(['**', '*'], '', $name); // Remove markdown formatting
    $name = preg_replace('/\s+/', ' ', $name); // Replace multiple spaces with single space
    return trim($name);
}

// Function to insert all real teacher assignments
function insertAllTeacherAssignments($pdo) {
    echo "📝 Inserting all teacher assignments...<br>";
    
    $inserted = 0;
    $stmt = $pdo->prepare("
        INSERT INTO teacher_assignments (teacher_name, section, program, school_year, semester, is_active)
        VALUES (?, ?, ?, '2025-2026', '1st', true)
        ON CONFLICT (teacher_name, section, school_year, semester) DO NOTHING
    ");
    
    // COLLEGE TEACHERS - All your real data
    echo "&nbsp;&nbsp;📚 Adding College Teachers...<br>";
    
    $collegeTeachers = [
        // BSCS Programs
        'BSCS-1M1' => ['MR. VELE', 'MR. RODRIGUEZ', 'MR. JIMENEZ', 'MS. RENDORA', 'MR. LACERNA', 'MR. ATIENZA'],
        'BSCS-2N1' => ['MR. RODRIGUEZ', 'MR. ICABANDE', 'MR. PATIAM', 'MS. VELE', 'MR. JIMENEZ', 'MS. RENDORA', 'MR. GORDON'],
        'BSCS-3M1' => ['MR. PATALEN', 'MS. DIMAPILIS', 'MR. V. GORDON', 'MR. JIMENEZ'],
        'BSCS-4N1' => ['MS. DIMAPILIS', 'MR. ELLO', 'MR. V. GORDON', 'MR. PATALEN'],
        'BSCS-1SC' => ['MR. VELE', 'MR. RODRIGUEZ', 'MR. ESPEÑA'],
        'BSCS-2SC' => ['MR. ICABANDE', 'MR. PATIAM', 'MR. ESPEÑA'],
        
        // BSOA Programs  
        'BSOA-1M1' => ['MR. VELE', 'MR. LACERNA', 'MR. RODRIGUEZ', 'MS. IGHARAS', 'MS. OCTAVO', 'MS. RENDORA', 'MR. ATIENZA'],
        'BSOA-2N1' => ['MR. LACERNA', 'MS. RENDORA', 'MS. VELE', 'MR. CALCEÑA', 'MS. CARMONA', 'MS. IGHARAS'],
        'BSOA-3M1' => ['MR. MATILA', 'MR. ELLO', 'MS. IGHARAS', 'MR. CALCEÑA', 'MR. V. GORDON'],
        'BSOA-4N1' => ['MR. CALCEÑA', 'MS. IGHARAS'],
        'BSOA-1SC' => ['MR. VELE', 'MS. DIMAPILIS', 'MR. RODRIGUEZ', 'MS. IGHARAS', 'MS. GENTEROY'],
        'BSOA-2SC' => ['MR. ICABANDE', 'MS. GENTEROY', 'MS. DIMAPILIS', 'MS. OCTAVO'],
        
        // EDUC Programs
        'EDUC-1M1' => ['MR. VELE', 'MR. MATILA', 'MR. V. GORDON', 'MS. TESORO', 'MR. LACERNA', 'MR. RODRIGUEZ', 'MS. RENDORA', 'MR. ATIENZA'],
        'EDUC-2N1' => ['MS. VELE', 'MR. VALENZUELA', 'MR. ICABANDE', 'MR. ELLO', 'MR. ORNACHO', 'MR. MATILA', 'MS. OCTAVO', 'MS. RENDORA'],
        'EDUC-3M1' => ['MS. OCTAVO', 'MR. VALENZUELA', 'MR. MATILA', 'MR. CALCEÑA', 'MS. MAGNO', 'MS. TESORO', 'MS. CARMONA'],
        'EDUC-4M1' => ['MR. ELLO', 'MS. TESORO'],
        'EDUC-4N1' => ['MS. TESORO'],
        'EDUC-1SC' => ['MR. ICABANDE', 'MR. LACERNA', 'MR. ORNACHO', 'MR. MATILA', 'MR. VELE', 'MS. DIMAPILIS'],
        'EDUC-2SC' => ['MR. LACERNA', 'MR. VELE', 'MR. PATIAM', 'MS. OCTAVO', 'MR. ORNACHO'],
    ];
    
    foreach ($collegeTeachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'COLLEGE']);
                $inserted++;
            }
        }
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✓ Added " . count($teachers) . " teachers to {$section}<br>";
    }
    
    // SHS GRADE 11 TEACHERS
    echo "&nbsp;&nbsp;🎓 Adding SHS Grade 11 Teachers...<br>";
    
    $shs11Teachers = [
        // HE Sections
        'HE1M1' => ['MS. TINGSON', 'MS. LAGUADOR', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. ROQUIOS', 'MRS. TESORO', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        'HE1M2' => ['MS. TINGSON', 'MRS. YABUT', 'MR. SANTOS', 'MR. ORNACHO', 'MR. ALCEDO', 'MRS. TESORO', 'MS. RENDORA', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        'HE1M3' => ['MS. TINGSON', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. ROQUIOS', 'MR. ALCEDO', 'MS. RENDORA', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        'HE1M4' => ['MS. TINGSON', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. ROQUIOS', 'MRS. TESORO', 'MS. RENDORA', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        'HE1N1' => ['MS. TINGSON', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. ROQUIOS', 'MRS. TESORO', 'MS. RENDORA', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        'HE1N2' => ['MS. TINGSON', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. ROQUIOS', 'MRS. TESORO', 'MS. RENDORA', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        'HE1N3' => ['MS. TINGSON', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MR. LACERNA', 'MRS. TESORO', 'MS. RENDORA', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        'HE1N4' => ['MS. TINGSON', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MR. LACERNA', 'MRS. TESORO', 'MS. RENDORA', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
        
        // ICT Sections
        'ICT1M1' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MR. R. GORDON', 'MS. RENDORA', 'MR. UMALI', 'MR. JIMENEZ', 'MR. V. GORDON'],
        'ICT1M2' => ['MS. ROQUIOS', 'MS. LAGUADOR', 'MR. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MRS. TESORO', 'MS. RENDORA', 'MR. UMALI', 'MR. JIMENEZ', 'MR. V. GORDON'],
        'ICT1N1' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. RODRIGUEZ', 'MS. ANGELES', 'MS. TINGSON', 'MR. R. GORDON', 'MR. UMALI', 'MR. JIMENEZ', 'MR. V. GORDON'],
        'ICT1N2' => ['MS. ROQUIOS', 'MS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MR. ALCEDO', 'MS. RENDORA', 'MR. UMALI', 'MR. JIMENEZ', 'MR. V. GORDON'],
        
        // HUMSS Sections
        'HUMSS1M1' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. LAGUADOR', 'MS. RENDORA', 'MR. ALCEDO'],
        'HUMSS1M2' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MR. R. GORDON', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1M3' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. TESORO', 'MR. UMALI', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1M4' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. LAGUADOR', 'MR. UMALI', 'MR. ALCEDO'],
        'HUMSS1M5' => ['MS. ROQUIOS', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. TINGSON', 'MR. R. GORDON', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1N1' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. LAGUADOR', 'MS. RENDORA', 'MR. ALCEDO'],
        'HUMSS1N2' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. LAGUADOR', 'MS. RENDORA', 'MR. ALCEDO'],
        'HUMSS1N3' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MR. R. GORDON', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        
        // ABM Sections
        'ABM1M1' => ['MS. TINGSON', 'MS. RIVERA', 'MS. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MS. TESORO', 'MR. UMALI', 'MS. LAGUADOR', 'MR. CALCEÑA', 'MS. GAJIRAN'],
        'ABM1M2' => ['MS. TINGSON', 'MS. LAGUADOR', 'MR. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MS. TESORO', 'MR. UMALI', 'MR. CALCEÑA', 'MS. GAJIRAN'],
        'ABM1N1' => ['MS. TINGSON', 'MS. LAGUADOR', 'MR. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MS. TESORO', 'MS. RENDORA', 'MR. CALCEÑA', 'MS. GAJIRAN'],
    ];
    
    foreach ($shs11Teachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'SHS']);
                $inserted++;
            }
        }
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✓ Added " . count($teachers) . " teachers to {$section}<br>";
    }
    
    // SHS GRADE 12 TEACHERS
    echo "&nbsp;&nbsp;🎓 Adding SHS Grade 12 Teachers...<br>";
    
    $shs12Teachers = [
        // HE3 Sections (Grade 12)
        'HE3M1' => ['MS. CARMONA', 'MR. BATILES', 'MR. ICABANDE', 'MR. PATIAM', 'MR. UMALI', 'MS. MAGNO'],
        'HE3M2' => ['MS. CARMONA', 'MR. BATILES', 'MS. RIVERA', 'MR. PATIAM', 'MR. UMALI', 'MS. MAGNO'],
        'HE3M3' => ['MS. CARMONA', 'MR. BATILES', 'MR. ICABANDE', 'MR. PATIAM', 'MS. RENDORA', 'MS. MAGNO'],
        'HE3M4' => ['MS. CARMONA', 'MR. BATILES', 'MR. ICABANDE', 'MR. PATIAM', 'MS. RENDORA', 'MS. MAGNO'],
        'HE3N1' => ['MS. CARMONA', 'MR. BATILES', 'MR. ICABANDE', 'MR. PATIAM', 'MS. RENDORA', 'MS. MAGNO'],
        'HE3N2' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MR. UMALI', 'MS. MAGNO'],
        'HE3N3' => ['MS. CARMONA', 'MR. BATILES', 'MR. ICABANDE', 'MR. PATIAM', 'MR. UMALI', 'MS. MAGNO'],
        'HE3N4' => ['MS. CARMONA', 'MR. BATILES', 'MR. ICABANDE', 'MR. PATIAM', 'MS. RENDORA', 'MS. MAGNO'],
        
        // ICT3 Sections (Grade 12)
        'ICT3M1' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. RENDORA', 'MR. V. GORDON'],
        'ICT3M2' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        'ICT3N1' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        'ICT3N2' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        
        // HUMSS3 Sections (Grade 12)
        'HUMSS3M1' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. GARCIA', 'MR. BATILES'],
        'HUMSS3M2' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. GARCIA', 'MR. BATILES'],
        'HUMSS3M3' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. GARCIA', 'MR. BATILES'],
        'HUMSS3M4' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. GARCIA', 'MR. BATILES'],
        'HUMSS3N1' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. GARCIA'],
        'HUMSS3N2' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MR. UMALI', 'MR. GARCIA'],
        'HUMSS3N3' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. GARCIA'],
        'HUMSS3N4' => ['MS. CARMONA', 'MR. LACERNA', 'MS. LIBRES', 'MR. PATIAM', 'MR. UMALI', 'MR. GARCIA'],
        
        // ABM3 Sections (Grade 12)
        'ABM3M1' => ['MS. CARMONA', 'MR. BATILES', 'MS. RIVERA', 'MR. PATIAM', 'MR. UMALI', 'MR. CALCEÑA'],
        'ABM3M2' => ['MS. CARMONA', 'MR. BATILES', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. CALCEÑA'],
        'ABM3N1' => ['MS. CARMONA', 'MR. BATILES', 'MS. LIBRES', 'MR. PATIAM', 'MR. UMALI', 'MR. CALCEÑA'],
    ];
    
    foreach ($shs12Teachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'SHS']);
                $inserted++;
            }
        }
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✓ Added " . count($teachers) . " teachers to {$section}<br>";
    }
    
    // SHS SC (Special Classes) TEACHERS
    echo "&nbsp;&nbsp;🌟 Adding SHS Special Classes Teachers...<br>";
    
    $shsSCTeachers = [
        // Grade 11 SC
        'HE-11SC' => ['MR. LACERNA', 'MR. RODRIGUEZ', 'MR. VALENZUELA', 'MR. MATILA', 'MR. UMALI', 'MS. GENTEROY'],
        'ICT-11SC' => ['MR. LACERNA', 'MR. RODRIGUEZ', 'MR. VALENZUELA', 'MR. MATILA', 'MR. JIMENEZ'],
        'HUMSS-11SC' => ['MR. ICABANDE', 'MR. PATIAM', 'MS. VELE', 'MR. MATILA'],
        'ABM-11SC' => ['MR. ICABANDE', 'MR. PATIAM', 'MS. VELE', 'MR. VALENZUELA', 'MR. RODRIGUEZ'],
        
        // Grade 12 SC
        'HE-12SC' => ['MR. VELE', 'MR. ICABANDE', 'MR. PATIAM', 'MS. GENTEROY'],
        'ICT-12SC' => ['MR. VELE', 'MR. ICABANDE', 'MR. PATIAM', 'MR. JIMENEZ'],
        'HUMSS-12SC' => ['MR. LACERNA', 'MR. UMALI', 'MR. PATIAM', 'MR. ICABANDE', 'MR. VELE'],
        'ABM-12SC' => ['MR. LACERNA', 'MR. UMALI', 'MR. PATIAM', 'MS. IGHARAS'],
    ];
    
    foreach ($shsSCTeachers as $section => $teachers) {
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $stmt->execute([$teacher, $section, 'SHS']);
                $inserted++;
            }
        }
        echo "&nbsp;&nbsp;&nbsp;&nbsp;✓ Added " . count($teachers) . " teachers to {$section}<br>";
    }
    
    return $inserted;
}

try {
    $pdo = getPDO();
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Database Setup</title>";
    echo "<style>body{font-family:Arial,sans-serif;margin:20px;line-height:1.6;}h2{color:#800000;}h3{color:#A52A2A;}p{margin:5px 0;}.summary{background:#f0f0f0;padding:15px;border-radius:5px;margin:20px 0;}.success{color:green;}.error{color:red;}.highlight{background:#ffffcc;padding:10px;border-radius:5px;border-left:5px solid #ffcc00;}</style>";
    echo "</head><body>";
    
    echo "<h2>🔧 Setting up database system with all real teacher assignments...</h2><br>";

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
            program VARCHAR(10) NOT NULL CHECK (program IN ('SHS', 'COLLEGE')),
            school_year VARCHAR(20) DEFAULT '2025-2026',
            semester VARCHAR(10) DEFAULT '1st' CHECK (semester IN ('1st','2nd')),
            is_active BOOLEAN DEFAULT true,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (teacher_name, section, school_year, semester)
        );
        
        CREATE INDEX idx_teacher_name ON teacher_assignments(teacher_name);
        CREATE INDEX idx_section ON teacher_assignments(section);
        CREATE INDEX idx_program ON teacher_assignments(program);
        CREATE INDEX idx_active ON teacher_assignments(is_active);
    ");
    echo "✓ Teacher assignments table created<br><br>";

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
            UNIQUE (student_username, teacher_name, section)
        );
        
        CREATE INDEX idx_student_username ON evaluations(student_username);
        CREATE INDEX idx_teacher_eval ON evaluations(teacher_name);
        CREATE INDEX idx_section_eval ON evaluations(section);
        CREATE INDEX idx_program_eval ON evaluations(program);
    ");
    echo "✓ Evaluations table created<br><br>";

    // ==============================
    // Insert ALL Real Teacher Assignments
    // ==============================
    $totalInserted = insertAllTeacherAssignments($pdo);

    // ==============================
    // Summary and Statistics
    // ==============================
    echo "<div class='summary'>";
    echo "<h3>✅ Database Setup Complete!</h3>";
    echo "<p class='success'><strong>Total teacher assignments inserted: {$totalInserted}</strong></p>";
    echo "</div>";
    
    // Show detailed statistics
    echo "<h3>📈 Assignment Statistics by Program</h3>";
    $stmt = $pdo->query("SELECT program, COUNT(*) as count FROM teacher_assignments GROUP BY program ORDER BY program");
    $stats = $stmt->fetchAll();
    
    foreach ($stats as $stat) {
        echo "<p>• <strong>{$stat['program']}:</strong> {$stat['count']} assignments</p>";
    }
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT teacher_name) as unique_teachers FROM teacher_assignments");
    $uniqueTeachers = $stmt->fetchColumn();
    echo "<p><strong>Unique teachers:</strong> {$uniqueTeachers}</p>";
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT section) as unique_sections FROM teacher_assignments");
    $uniqueSections = $stmt->fetchColumn();
    echo "<p><strong>Sections covered:</strong> {$uniqueSections}</p>";
    
    // Show specific BSCS3M1 assignments (your section)
    echo "<div class='highlight'>";
    echo "<h3>🎯 Your Section (BSCS3M1) Teachers:</h3>";
    $stmt = $pdo->prepare("SELECT teacher_name FROM teacher_assignments WHERE section = ? AND program = ? ORDER BY teacher_name");
    $stmt->execute(['BSCS3M1', 'COLLEGE']);
    $bscs3m1_teachers = $stmt->fetchAll();
    
    if ($bscs3m1_teachers) {
        foreach ($bscs3m1_teachers as $teacher) {
            echo "<p>• <strong>{$teacher['teacher_name']}</strong></p>";
        }
        echo "<p style='color:green;'><strong>✅ Found " . count($bscs3m1_teachers) . " teachers for your section!</strong></p>";
    } else {
        echo "<p style='color:red;'><strong>❌ No teachers found for BSCS3M1</strong></p>";
    }
    echo "</div>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0;'>";
    echo "<h3>🏫 System Information</h3>";
    echo "<p><strong>Tables Created:</strong></p>";
    echo "<ul>";
    echo "<li>📋 <code>teacher_assignments</code> - All real teacher-section assignments</li>";
    echo "<li>📊 <code>evaluations</code> - Student evaluation responses storage</li>";
    echo "</ul>";
    
    echo "<p><strong>Data Sources:</strong></p>";
    echo "<ul>";
    echo "<li>📑 <strong>Students:</strong> Google Sheets (Student_ID, Last_Name, First_Name, Section, Program, Username, Password)</li>";
    echo "<li>👨‍🏫 <strong>Teachers:</strong> Database teacher_assignments table (now populated with all real data)</li>";
    echo "<li>📊 <strong>Evaluations:</strong> Database evaluations table</li>";
    echo "</ul>";
    
    echo "<p><strong>Ready to use:</strong></p>";
    echo "<ol>";
    echo "<li>✅ All teacher assignments are loaded</li>";
    echo "<li>✅ Your BSCS3M1 section should now show teachers</li>";
    echo "<li>✅ Students can now evaluate their assigned teachers</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<br><p><a href='index.php' style='background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold;'>🏠 Go to Login Page</a></p>";
    echo "<p><a href='student_dashboard.php' style='background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin-left: 10px;'>📱 Test Student Dashboard</a></p>";
    
    echo "</body></html>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 8px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
    echo "<h3>❌ Database Setup Failed</h3>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
    echo "</div>";
    echo "</body></html>";
    exit(1);
}
?>




