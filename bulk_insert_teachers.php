<?php
// bulk_insert_teachers.php - Insert all teacher assignments from your documents
require_once 'includes/db_connection.php';

// Function to clean teacher names
function cleanTeacherName($name) {
    $name = trim($name);
    $name = str_replace(['**', '*'], '', $name); // Remove markdown formatting
    $name = preg_replace('/\s+/', ' ', $name); // Replace multiple spaces with single space
    return trim($name);
}

// Function to insert teacher assignments
function insertTeacherAssignments($pdo) {
    echo "<h2>🔧 Inserting Teacher Assignments</h2>";
    
    // Clear existing assignments first
    echo "<p>Clearing existing teacher assignments...</p>";
    $pdo->exec("DELETE FROM teacher_assignments");
    
    $inserted = 0;
    $stmt = $pdo->prepare("
        INSERT INTO teacher_assignments (teacher_name, section, subject, program, school_year, semester, is_active)
        VALUES (?, ?, ?, ?, '2025-2026', '1st', true)
        ON CONFLICT (teacher_name, section, subject, school_year, semester) DO NOTHING
    ");
    
    // COLLEGE TEACHERS
    echo "<h3>📚 Inserting College Teachers</h3>";
    
    $collegeTeachers = [
        // BSCS Programs
        'BSCS-1M1' => ['MR. VELE', 'MR. RODRIGUEZ', 'MR. JIMENEZ', 'MR. JIMENEZ', 'MS. RENDORA', 'MR. LACERNA', 'MS. RENDORA', 'MR. ATIENZA'],
        'BSCS-2N1' => ['MR. RODRIGUEZ', 'MR. ICABANDE', 'MR. PATIAM', 'MS. VELE', 'MR. JIMENEZ', 'MR. JIMENEZ', 'MS. RENDORA', 'MR. GORDON'],
        'BSCS-3M1' => ['MR. PATALEN', 'MS. DIMAPILIS', 'MR. V. GORDON', 'MS. DIMAPILIS', 'MS. DIMAPILIS', 'MR. JIMENEZ'],
        'BSCS-4N1' => ['MS. DIMAPILIS', 'MS. DIMAPILIS', 'MS. DIMAPILIS', 'MR. ELLO', 'MR. V. GORDON', 'MR. PATALEN'],
        
        // BSOA Programs  
        'BSOA-1M1' => ['MR. VELE', 'MR. LACERNA', 'MR. RODRIGUEZ', 'MS. IGHARAS', 'MS. OCTAVO', 'MS. RENDORA', 'MS. RENDORA', 'MR. ATIENZA'],
        'BSOA-2N1' => ['MR. LACERNA', 'MS. RENDORA', 'MS. VELE', 'MR. CALCEÑA', 'MS. CARMONA', 'MS. IGHARAS', 'MS. RENDORA', 'MS. RENDORA'],
        'BSOA-3M1' => ['MR. MATILA', 'MR. ELLO', 'MS. IGHARAS', 'MR. CALCEÑA', 'MR. V. GORDON'],
        'BSOA-4N1' => ['MR. CALCEÑA', 'MS. IGHARAS', 'MS. IGHARAS', 'MR. CALCEÑA'],
        
        // EDUC Programs
        'EDUC-1M1' => ['MR. VELE', 'MR. MATILA', 'MR. V. GORDON', 'MS. TESORO', 'MR. LACERNA', 'MR. LACERNA', 'MR. RODRIGUEZ', 'MS. RENDORA', 'MS. RENDORA', 'MR. ATIENZA'],
        'EDUC-2N1' => ['MS. VELE', 'MR. VALENZUELA', 'MR. ICABANDE', 'MR. ELLO', 'MR. VALENZUELA', 'MR. ORNACHO', 'MR. MATILA', 'MS. OCTAVO', 'MS. RENDORA'],
        'EDUC-3M1' => ['MS. OCTAVO', 'MR. VALENZUELA', 'MR. MATILA', 'MR. CALCEÑA', 'MS. MAGNO', 'MS. MAGNO', 'MS. TESORO', 'MS. CARMONA'],
        'EDUC-4M1' => ['MS. TESORO', 'MR. ELLO', 'MS. TESORO', 'MS. TESORO'],
        'EDUC-4N1' => ['MS. TESORO'],
    ];
    
    foreach ($collegeTeachers as $section => $teachers) {
        $subjectCounter = 1;
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $subject = "Subject " . $subjectCounter;
                $stmt->execute([$teacher, $section, $subject, 'COLLEGE']);
                $inserted++;
                $subjectCounter++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }
    
    // SHS GRADE 11 TEACHERS
    echo "<h3>🎓 Inserting SHS Grade 11 Teachers</h3>";
    
    $shs11Teachers = [
        // HE Sections
        'HE1M1' => ['MS. TINGSON', 'MS. LAGUADOR', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. ROQUIOS', 'MRS. TESORO', 'MR. UMALI', 'MR. UMALI', 'MR. R. GORDON', 'MR. GARCIA'],
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
        'ICT1N1' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. RODRIGUEZ', 'MS. ANGELES', 'MS. TINGSON', 'MR. R. GORDON', 'MR. UMALI', 'MR. UMALI', 'MR. JIMENEZ', 'MR. V. GORDON'],
        'ICT1N2' => ['MS. ROQUIOS', 'MS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MR. ALCEDO', 'MS. RENDORA', 'MR. UMALI', 'MR. JIMENEZ', 'MR. V. GORDON'],
        
        // HUMSS Sections
        'HUMSS1M1' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. ROQUIOS', 'MS. LAGUADOR', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1M2' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. ROQUIOS', 'MR. R. GORDON', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1M3' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. TESORO', 'MR. UMALI', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1M4' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. LAGUADOR', 'MR. UMALI', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1M5' => ['MS. ROQUIOS', 'MRS. YABUT', 'MS. GAJIRAN', 'MS. ANGELES', 'MS. TINGSON', 'MR. R. GORDON', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1N1' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. LAGUADOR', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1N2' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MS. LAGUADOR', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        'HUMSS1N3' => ['MS. ROQUIOS', 'MRS. YABUT', 'MR. SANTOS', 'MS. ANGELES', 'MS. TINGSON', 'MR. R. GORDON', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. ALCEDO'],
        
        // ABM Sections
        'ABM1M1' => ['MS. TINGSON', 'MS. RIVERA', 'MS. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MS. TESORO', 'MR. UMALI', 'MS. LAGUADOR', 'MR. CALCEÑA', 'MS. GAJIRAN'],
        'ABM1M2' => ['MS. TINGSON', 'MS. LAGUADOR', 'MR. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MS. TESORO', 'MR. UMALI', 'MS. LAGUADOR', 'MR. CALCEÑA', 'MS. GAJIRAN'],
        'ABM1N1' => ['MS. TINGSON', 'MS. LAGUADOR', 'MR. SANTOS', 'MS. ANGELES', 'MR. ALCEDO', 'MS. TESORO', 'MS. RENDORA', 'MS. LAGUADOR', 'MR. CALCEÑA', 'MS. GAJIRAN'],
    ];
    
    foreach ($shs11Teachers as $section => $teachers) {
        $subjectCounter = 1;
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $subject = "Subject " . $subjectCounter;
                $stmt->execute([$teacher, $section, $subject, 'SHS']);
                $inserted++;
                $subjectCounter++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }
    
    // SHS GRADE 12 TEACHERS
    echo "<h3>🎓 Inserting SHS Grade 12 Teachers</h3>";
    
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
        'ICT3M1' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. ICABANDE', 'MR. RENDORA', 'MR. V. GORDON'],
        'ICT3M2' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        'ICT3N1' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        'ICT3N2' => ['MS. LIBRES', 'MR. LACERNA', 'MR. ICABANDE', 'MR. ICABANDE', 'MR. UMALI', 'MR. V. GORDON'],
        
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
        'ABM3M1' => ['MS. CARMONA', 'MR. BATILES', 'MS. RIVERA', 'MR. PATIAM', 'MR. UMALI', 'MR. CALCEÑA', 'MR. CALCEÑA'],
        'ABM3M2' => ['MS. CARMONA', 'MR. BATILES', 'MS. LIBRES', 'MR. PATIAM', 'MS. RENDORA', 'MR. CALCEÑA', 'MR. CALCEÑA'],
        'ABM3N1' => ['MS. CARMONA', 'MR. BATILES', 'MS. LIBRES', 'MR. PATIAM', 'MR. UMALI', 'MR. CALCEÑA'],
    ];
    
    foreach ($shs12Teachers as $section => $teachers) {
        $subjectCounter = 1;
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $subject = "Subject " . $subjectCounter;
                $stmt->execute([$teacher, $section, $subject, 'SHS']);
                $inserted++;
                $subjectCounter++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }
    
    // SHS SC (Special Classes) TEACHERS
    echo "<h3>🌟 Inserting SHS Special Classes Teachers</h3>";
    
    $shsSCTeachers = [
        // Grade 11 SC
        'HE-11SC' => ['MR. LACERNA', 'MR. RODRIGUEZ', 'MR. VALENZUELA', 'MR. MATILA', 'MR. UMALI', 'MS. GENTEROY'],
        'ICT-11SC' => ['MR. LACERNA', 'MR. RODRIGUEZ', 'MR. VALENZUELA', 'MR. MATILA', 'MR. JIMENEZ', 'MR. JIMENEZ'],
        'HUMSS-11SC' => ['MR. ICABANDE', 'MR. PATIAM', 'MS. VELE', 'MS. VELE', 'MR. MATILA'],
        'ABM-11SC' => ['MR. ICABANDE', 'MR. PATIAM', 'MS. VELE', 'MS. VELE', 'MR. VALENZUELA', 'MR. RODRIGUEZ'],
        
        // Grade 12 SC
        'HE-12SC' => ['MR. VELE', 'MR. ICABANDE', 'MR. PATIAM', 'MS. GENTEROY'],
        'ICT-12SC' => ['MR. VELE', 'MR. ICABANDE', 'MR. PATIAM', 'MR. JIMENEZ', 'MR. JIMENEZ'],
        'HUMSS-12SC' => ['MR. LACERNA', 'MR. UMALI', 'MR. PATIAM', 'MR. ICABANDE', 'MR. VELE'],
        'ABM-12SC' => ['MR. LACERNA', 'MR. UMALI', 'MR. PATIAM', 'MS. IGHARAS', 'MS. IGHARAS'],
    ];
    
    foreach ($shsSCTeachers as $section => $teachers) {
        $subjectCounter = 1;
        foreach ($teachers as $teacher) {
            $teacher = cleanTeacherName($teacher);
            if (!empty($teacher)) {
                $subject = "Subject " . $subjectCounter;
                $stmt->execute([$teacher, $section, $subject, 'SHS']);
                $inserted++;
                $subjectCounter++;
            }
        }
        echo "<p>✓ Added " . count($teachers) . " teachers to {$section}</p>";
    }
    
    return $inserted;
}

try {
    $pdo = getPDO();
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Bulk Insert Teachers</title>";
    echo "<style>body{font-family:Arial,sans-serif;margin:20px;line-height:1.6;}h2{color:#800000;}h3{color:#A52A2A;}p{margin:5px 0;}.summary{background:#f0f0f0;padding:15px;border-radius:5px;margin:20px 0;}.success{color:green;}.error{color:red;}</style>";
    echo "</head><body>";
    
    echo "<h1>🏫 Bulk Teacher Assignment Import</h1>";
    echo "<p>Importing all teacher assignments from your documents...</p>";
    
    $totalInserted = insertTeacherAssignments($pdo);
    
    echo "<div class='summary'>";
    echo "<h3>📊 Import Summary</h3>";
    echo "<p class='success'><strong>Total teachers assigned: {$totalInserted}</strong></p>";
    echo "<p>All teacher assignments have been successfully imported!</p>";
    echo "</div>";
    
    // Show some statistics
    $stmt = $pdo->query("SELECT program, COUNT(*) as count FROM teacher_assignments GROUP BY program ORDER BY program");
    $stats = $stmt->fetchAll();
    
    echo "<h3>📈 Assignment Statistics by Program</h3>";
    foreach ($stats as $stat) {
        echo "<p>• {$stat['program']}: {$stat['count']} assignments</p>";
    }
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT teacher_name) as unique_teachers FROM teacher_assignments");
    $uniqueTeachers = $stmt->fetchColumn();
    echo "<p><strong>Unique teachers: {$uniqueTeachers}</strong></p>";
    
    $stmt = $pdo->query("SELECT COUNT(DISTINCT section) as unique_sections FROM teacher_assignments");
    $uniqueSections = $stmt->fetchColumn();
    echo "<p><strong>Sections covered: {$uniqueSections}</strong></p>";
    
    echo "<br><p><a href='index.php' style='background:#007bff;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;'>← Back to Login</a></p>";
    echo "<p><a href='admin.php' style='background:#28a745;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;margin-left:10px;'>Go to Admin Panel</a></p>";
    
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>Failed to insert teacher assignments: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
