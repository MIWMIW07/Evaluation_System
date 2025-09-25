<?php
// database_setup.php - FIXED VERSION FOR RAILWAY DEPLOYMENT
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection function
function getDatabaseConnection() {
    // Check if we're on Railway (environment variables will be set)
    if (isset($_ENV['DATABASE_URL']) || isset($_SERVER['DATABASE_URL'])) {
        $database_url = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'];
        $db_parts = parse_url($database_url);
        
        $host = $db_parts['host'];
        $port = $db_parts['port'] ?? 5432;
        $dbname = ltrim($db_parts['path'], '/');
        $username = $db_parts['user'];
        $password = $db_parts['pass'];
        
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    } else {
        // Local development fallback
        $dsn = "mysql:host=localhost;dbname=evaluation_system;charset=utf8mb4";
        $username = "root";
        $password = "";
    }
    
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

$setup_messages = [];
$errors = [];

try {
    $pdo = getDatabaseConnection();
    $setup_messages[] = "✅ Database connection successful!";
    
    // Detect database type
    $is_postgres = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $auto_increment = $is_postgres ? 'SERIAL PRIMARY KEY' : 'INT AUTO_INCREMENT PRIMARY KEY';
    $text_type = $is_postgres ? 'TEXT' : 'TEXT';
    $timestamp_default = $is_postgres ? 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';

    // --- 1. TABLE CREATION (CORRECT ORDER) ---

    $create_sections_table = "CREATE TABLE IF NOT EXISTS sections (
        id $auto_increment,
        section_code VARCHAR(20) UNIQUE NOT NULL,
        section_name VARCHAR(100) NOT NULL,
        program VARCHAR(50) NOT NULL,
        year_level VARCHAR(20),
        is_active BOOLEAN DEFAULT true,
        created_at $timestamp_default
    )";
    $pdo->exec($create_sections_table);
    $setup_messages[] = "✅ Sections table created/verified";

    // Create students table first because the users table references it
    $create_students_table = "CREATE TABLE IF NOT EXISTS students (
        id $auto_increment,
        student_id VARCHAR(30) UNIQUE,
        last_name VARCHAR(50) NOT NULL,
        first_name VARCHAR(50) NOT NULL,
        middle_name VARCHAR(50),
        full_name VARCHAR(150) NOT NULL,
        section_id INTEGER" . ($is_postgres ? " REFERENCES sections(id)" : "") . ",
        is_active BOOLEAN DEFAULT true,
        enrolled_date $timestamp_default
    )";
    $pdo->exec($create_students_table);
    $setup_messages[] = "✅ Students table created/verified";

    $create_users_table = "CREATE TABLE IF NOT EXISTS users (
        id $auto_increment,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        user_type VARCHAR(20) NOT NULL DEFAULT 'student',
        full_name VARCHAR(100) NOT NULL,
        student_id VARCHAR(20),
        program VARCHAR(50),
        section VARCHAR(50),
        created_at $timestamp_default,
        last_login TIMESTAMP NULL,
        student_table_id INTEGER" . ($is_postgres ? " REFERENCES students(id)" : "") . "
    )";
    $pdo->exec($create_users_table);
    $setup_messages[] = "✅ Users table created/verified";

    $create_teachers_table = "CREATE TABLE IF NOT EXISTS teachers (
        id $auto_increment,
        name VARCHAR(100) NOT NULL,
        department VARCHAR(50) NOT NULL,
        is_active BOOLEAN DEFAULT true,
        created_at $timestamp_default,
        UNIQUE(name, department)
    )";
    $pdo->exec($create_teachers_table);
    $setup_messages[] = "✅ Teachers table created/verified.";

    $create_section_teachers_table = "CREATE TABLE IF NOT EXISTS section_teachers (
        id $auto_increment,
        section_id INTEGER" . ($is_postgres ? " REFERENCES sections(id)" : "") . ",
        teacher_id INTEGER" . ($is_postgres ? " REFERENCES teachers(id)" : "") . ",
        is_active BOOLEAN DEFAULT true,
        assigned_date $timestamp_default,
        UNIQUE(section_id, teacher_id)
    )";
    $pdo->exec($create_section_teachers_table);
    $setup_messages[] = "✅ Section_Teachers table created/verified.";

    $create_evaluations_table = "CREATE TABLE IF NOT EXISTS evaluations (
        id $auto_increment,
        user_id INTEGER" . ($is_postgres ? " REFERENCES users(id)" : "") . ",
        student_id VARCHAR(20) NOT NULL,
        student_name VARCHAR(100) NOT NULL,
        section VARCHAR(50) NOT NULL,
        program VARCHAR(50) NOT NULL,
        teacher_id INTEGER" . ($is_postgres ? " REFERENCES teachers(id)" : "") . ",
        q1_1 INTEGER NOT NULL,
        q1_2 INTEGER NOT NULL,
        q1_3 INTEGER NOT NULL,
        q1_4 INTEGER NOT NULL,
        q1_5 INTEGER NOT NULL,
        q1_6 INTEGER NOT NULL,
        q2_1 INTEGER NOT NULL,
        q2_2 INTEGER NOT NULL,
        q2_3 INTEGER NOT NULL,
        q2_4 INTEGER NOT NULL,
        q3_1 INTEGER NOT NULL,
        q3_2 INTEGER NOT NULL,
        q3_3 INTEGER NOT NULL,
        q3_4 INTEGER NOT NULL,
        q4_1 INTEGER NOT NULL,
        q4_2 INTEGER NOT NULL,
        q4_3 INTEGER NOT NULL,
        q4_4 INTEGER NOT NULL,
        q4_5 INTEGER NOT NULL,
        q4_6 INTEGER NOT NULL,
        comments $text_type,
        evaluation_date $timestamp_default,
        UNIQUE(user_id, teacher_id)
    )";
    $pdo->exec($create_evaluations_table);
    $setup_messages[] = "✅ Evaluations table created/verified";
    
    // --- 2. DATA INSERTION ---

    // ADMIN USER
    $check_admin = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check_admin->execute(['admin']);
    if ($check_admin->fetchColumn() == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username, password, user_type, full_name) VALUES (?, ?, ?, ?)")->execute(['admin', $admin_password, 'admin', 'System Administrator']);
        $setup_messages[] = "✅ Admin user created (username: admin, password: admin123)";
    }

    // SECTIONS DATA
    $sections = [
        ['BSCS-1M1', 'BS Computer Science 1st Year Morning Section 1', 'COLLEGE', '1st Year'], ['BSCS-2N1', 'BS Computer Science 2nd Year Night Section 1', 'COLLEGE', '2nd Year'], ['BSCS-3M1', 'BS Computer Science 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'], ['BSCS-4N1', 'BS Computer Science 4th Year Night Section 1', 'COLLEGE', '4th Year'], ['BSOA-1M1', 'BS Office Administration 1st Year Morning Section 1', 'COLLEGE', '1st Year'], ['BSOA-2N1', 'BS Office Administration 2nd Year Night Section 1', 'COLLEGE', '2nd Year'], ['BSOA-3M1', 'BS Office Administration 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'], ['BSOA-4N1', 'BS Office Administration 4th Year Night Section 1', 'COLLEGE', '4th Year'], ['EDUC-1M1', 'Bachelor of Elementary Education 1st Year Morning Section 1', 'COLLEGE', '1st Year'], ['EDUC-2N1', 'Bachelor of Elementary Education 2nd Year Night Section 1', 'COLLEGE', '2nd Year'], ['EDUC-3M1', 'Bachelor of Elementary Education 3rd Year Morning Section 1', 'COLLEGE', '3rd Year'], ['EDUC-4M1', 'Bachelor of Elementary Education 4th Year Morning Section 1', 'COLLEGE', '4th Year'], ['EDUC-4N1', 'Bachelor of Elementary Education 4th Year Night Section 1', 'COLLEGE', '4th Year'], ['BSCS-1SC', 'BS Computer Science 1st Year Sunday Class', 'COLLEGE', '1st Year'], ['BSCS-2SC', 'BS Computer Science 2nd Year Sunday Class', 'COLLEGE', '2nd Year'], ['BSOA-1SC', 'BS Office Administration 1st Year Sunday Class', 'COLLEGE', '1st Year'], ['BSOA-2SC', 'BS Office Administration 2nd Year Sunday Class', 'COLLEGE', '2nd Year'], ['EDUC-1SC', 'Bachelor of Technical Vocational Teacher Education 1st Year Sunday Class', 'COLLEGE', '1st Year'], ['EDUC-2SC', 'Bachelor of Technical Vocational Teacher Education 2nd Year Sunday Class', 'COLLEGE', '2nd Year'],
        ['ABM-1M1', 'Accountancy Business Management Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['ABM-1M2', 'Accountancy Business Management Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['ABM-1N1', 'Accountancy Business Management Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['HUMSS-1M1', 'Humanities and Social Sciences Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['HUMSS-1M2', 'Humanities and Social Sciences Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['HUMSS-1M3', 'Humanities and Social Sciences Grade 11 Morning Section 3', 'SHS', 'Grade 11'], ['HUMSS-1M4', 'Humanities and Social Sciences Grade 11 Morning Section 4', 'SHS', 'Grade 11'], ['HUMSS-1M5', 'Humanities and Social Sciences Grade 11 Morning Section 5', 'SHS', 'Grade 11'], ['HUMSS-1N1', 'Humanities and Social Sciences Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['HUMSS-1N2', 'Humanities and Social Sciences Grade 11 Night Section 2', 'SHS', 'Grade 11'], ['HUMSS-1N3', 'Humanities and Social Sciences Grade 11 Night Section 3', 'SHS', 'Grade 11'], ['HE-1M1', 'Home Economics Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['HE-1M2', 'Home Economics Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['HE-1M3', 'Home Economics Grade 11 Morning Section 3', 'SHS', 'Grade 11'], ['HE-1M4', 'Home Economics Grade 11 Morning Section 4', 'SHS', 'Grade 11'], ['HE-1N1', 'Home Economics Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['HE-1N2', 'Home Economics Grade 11 Night Section 2', 'SHS', 'Grade 11'], ['ICT-1M1', 'Information and Communication Technology Grade 11 Morning Section 1', 'SHS', 'Grade 11'], ['ICT-1M2', 'Information and Communication Technology Grade 11 Morning Section 2', 'SHS', 'Grade 11'], ['ICT-1N1', 'Information and Communication Technology Grade 11 Night Section 1', 'SHS', 'Grade 11'], ['ICT-1N2', 'Information and Communication Technology Grade 11 Night Section 2', 'SHS', 'Grade 11'],
        ['HUMSS-3M1', 'Humanities and Social Sciences Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['HUMSS-3M2', 'Humanities and Social Sciences Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['HUMSS-3M3', 'Humanities and Social Sciences Grade 12 Morning Section 3', 'SHS', 'Grade 12'], ['HUMSS-3M4', 'Humanities and Social Sciences Grade 12 Morning Section 4', 'SHS', 'Grade 12'], ['HUMSS-3N1', 'Humanities and Social Sciences Grade 12 Night Section 1', 'SHS', 'Grade 12'], ['HUMSS-3N2', 'Humanities and Social Sciences Grade 12 Night Section 2', 'SHS', 'Grade 12'], ['HUMSS-3N3', 'Humanities and Social Sciences Grade 12 Night Section 3', 'SHS', 'Grade 12'], ['HUMSS-3N4', 'Humanities and Social Sciences Grade 12 Night Section 4', 'SHS', 'Grade 12'], ['HE-3M1', 'Home Economics Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['HE-3M2', 'Home Economics Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['HE-3M3', 'Home Economics Grade 12 Morning Section 3', 'SHS', 'Grade 12'], ['HE-3M4', 'Home Economics Grade 12 Morning Section 4', 'SHS', 'Grade 12'], ['HE-3N1', 'Home Economics Grade 12 Night Section 1', 'SHS', 'Grade 12'], ['HE-3N2', 'Home Economics Grade 12 Night Section 2', 'SHS', 'Grade 12'], ['HE-3N3', 'Home Economics Grade 12 Night Section 3', 'SHS', 'Grade 12'], ['HE-3N4', 'Home Economics Grade 12 Night Section 4', 'SHS', 'Grade 12'], ['ICT-3M1', 'Information and Communication Technology Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['ICT-3M2', 'Information and Communication Technology Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['ICT-3N1', 'Information and Communication Technology Grade 12 Night Section 1', 'SHS', 'Grade 12'], ['ICT-3N2', 'Information and Communication Technology Grade 12 Night Section 2', 'SHS', 'Grade 12'], ['ABM-3M1', 'Accountancy Business Management Grade 12 Morning Section 1', 'SHS', 'Grade 12'], ['ABM-3M2', 'Accountancy Business Management Grade 12 Morning Section 2', 'SHS', 'Grade 12'], ['ABM-3N1', 'Accountancy Business Management Grade 12 Night Section 1', 'SHS', 'Grade 12'],
        ['HE-11SC', 'Home Economics Grade 11 Sunday Class', 'SHS', 'Grade 11'], ['ICT-11SC', 'Information and Communication Technology Grade 11 Sunday Class', 'SHS', 'Grade 11'], ['HUMSS-11SC', 'Humanities and Social Sciences Grade 11 Sunday Class', 'SHS', 'Grade 11'], ['ABM-11SC', 'Accountancy Business Management Grade 11 Sunday Class', 'SHS', 'Grade 11'],
        ['HE-12SC', 'Home Economics Grade 12 Sunday Class', 'SHS', 'Grade 12'], ['ICT-12SC', 'Information and Communication Technology Grade 12 Sunday Class', 'SHS', 'Grade 12'], ['HUMSS-12SC', 'Humanities and Social Sciences Grade 12 Sunday Class', 'SHS', 'Grade 12'], ['ABM-12SC', 'Accountancy Business Management Grade 12 Sunday Class', 'SHS', 'Grade 12']
    ];
    $sections_created = 0;
    foreach ($sections as $section) {
        $check_section = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE section_code = ?");
        $check_section->execute([$section[0]]);
        if ($check_section->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO sections (section_code, section_name, program, year_level) VALUES (?, ?, ?, ?)")->execute($section);
            $sections_created++;
        }
    }
    $setup_messages[] = "✅ {$sections_created} new sections created/verified";

    // --- 3. STUDENT DATA INSERTION ---
    $all_students_by_section = [
        'BSCS-1M1' => [['ABAÑO','SHAWN ROVIC','PARREÑO'],['ANDRES','ALLYSA','LIBRANDO'],['CARDIENTE','SHIENA','AMANTE'],['CERNA','SHAYRA','OMILIG'],['CORTES','MARRY JOY','LUVIDECES'],['DALUZ','MA. KATE JASMIN','PORTACIO'],['ESTOCADO','LEOVY JOY','CARBONELLO'],['GARCERA','ANGELA',''],['JACINTO','MA. CLARISSA','BAUTISTA'],['LABAGALA','TRISHA MAE','CADILE'],['MUSCA','ROSALINDA',''],['RAFANAN','ROSELYN','LANORIA'],['SAYNO','JANILLE','BASINANG'],['ZARSUELO','JENNICA ROSE','BAMBIBO'],['ABIGUELA','RAYBAN','NAVEA'],['ADONIS','JARED','DICHUPA'],['AMABAO','EZEKIEL JEAN',''],['BULADO','JOHN PAUL','VALEROSO'],['CADILE','BENCH JOSH','MENDOZA'],['CUEVAS','CYRUS','MARTINEZ'],['DIOQUINO','FRANCIS','RECTO'],['FALCON','KARL ANTHONY','VERSOZA'],['KEMPIS','BRIAN JOSH','ACIDO'],['LACUARIN','CYRUS','MARMETO'],['LLEGANIA','LANCE ALLANDY','MONTILLA'],['LOREDO','CLARENCE','CASTUCIANO'],['LORICA','JOSEPH','PAMIN'],['MEDROSO','VICENTE','VENCIO'],['NOVICIO','ACESON','LAWA'],['QUIRANTE','IZEUS JAKE','E'],['ROA','MICHAEL','RAMALLOSA'],['SAMILLANO','EDRIAN','PESTAÑO'],['VALLEJO','JOHN KENNETH ADRIAN','EMBESTRO'],['VERDOQUILLO','LEMUEL','HITALIA'],['VILLANUEVA','ROWIE','PADEL']],
        'BSOA-1M1' => [['ABRAGAN','CHERRY','SARMIENTO'],['ADRAO','SHIELA MAY','RIBAYA'],['ALMANZA','RASHEL','ALBITO'],['ARRIOLA','CHRISTEL GENESIS','DELOS REYES'],['CAINTIC','JAMAICA MAE','VALERIO'],['CANELA','RAZEL MAICCA','G'],['CELIS','IZZY','TADEO'],['DACLES','KHATE','AGRAVANTE'],['DACUYA','IVY','BACULINAO'],['DARDAGAN','JOHANNA','GUINAL'],['DEGAYO','PRINCESS','BACALSO'],['DELA CRUZ','JANEYA CASSANDRA','SOBREMONTE'],['DOMECILLO','JULY-ANN','RAMIREZ'],['DOMONDON','ABIGAIL','TUTAAN'],['EVANGELISTA','KEYCEE','MIRANDILLA'],['FORTALEZA','ABEGAIL','COLICO'],['FRIAS','JENNY VHABE',''],['FUENTES','LEONORA ANNA','HISONA'],['GABORNE','PRECIOUS JOSEPHINE','ABANDO'],['GARCIA','KRISTINE JOY','ECHON'],['JAGOLINA','TANYA','FAJARDO'],['LARIOQUE','MICHELLE JOY','SOLAYAO'],['LUCHANA','GABBY ANN','GARCERA'],['MACALINO','MICHELLE','IRINCO'],['MASUNGSONG','ROSE ANN','CAMINO'],['MOJICA','MICHAELA VENICE GAIL','BUSTAMANTE'],['PULLEDA','WINA',''],['RAFOLS','DIANNA ROME','POLLANTE'],['REMOTO','MARY DIVINE GRACE','BALGOS'],['RENDON','ELAIJAH MAE','LAURINA'],['SAMSON','ANGELINE','HERNANDEZ'],['SUMORIA','ALMIRA','CELIS'],['TAMBUNGAN','WENLIAN-MISIA','DAEF'],['TESADO','ARLENE',''],['VANZUELA','RHIANNA MAE','OSARES'],['ALBEZA','ROMEO','ESCUADRO'],['BADO','GILBERT','ALCANTARA'],['DELA PAZ','JUDEL','DEL SOCORRO'],['DURANTE','MARK LORENZ','HERNANDEZ'],['ESGUERRA','JENMORE','BACALSO'],['FAMILIAR','MARC JERIC','RODRIGUEZ'],['HISONA','CHRISTIAN',''],['MERO','NOLIE',''],['PACIS','JERALD','SIERRA'],['PAVIA','JASTIN','NIEPAS'],['PEREZ','REGGIE','ALIMANIA'],['RODRIGUEZ','ROILAND','GRUTAS'],['VICTORIA','MARJOHN','N/I']],
        'EDUC-1M1' => [['AMARANTO','JOANA MARIE','BIQUILLO'],['ANTIPOLO','CANDIES DALE',''],['ARSOLON','REBECCA AIRA','CIRUELA'],['ASANZA','CLARIZ','LAUROSA'],['ASUCENAS','APPLE MAE','JUNSY'],['BAUNTO','SAIMAH','PANGADAPEN'],['BOMBASE','ANGEL','VILLARENO'],['DAYANDAYAN','RODELYN',''],['DELOS SANTOS','NORLY','JAGOLINA'],['FERNANDES','FIRILYN','TABOTABO'],['GABAYNO','BERNADETTED','GADO'],['GABRIEL','AYESHA NICOLE','ESLAO'],['GARILAO','CRISJAELLE','SALVADOR'],['GRAFIL','JOSIE','DINGAL'],['LABASBAS','MAYCIE','PASCUAL'],['LICMUAN','KAYE EUNICE','NARAG'],['LINGHAP','REGIELYN','ADAMOS'],['LOBERISCO','MYRA',''],['MARTICIO','ANNA MARIE','CAMACHO'],['MATCHINA','SHAINE','SUELO'],['OMEDES','MIRAFE','SOMALO'],['PAGAL','PELJOY','SERON'],['RABE','CHRISTINE GRACE','TAGRIPIS'],['RAMOS','CLARISS','LAXA'],['REFAMA','JASMINE','MANALILI'],['RODRIGUEZ','WINNIECHA','DAVID'],['TANDAS','ANGELICA NICOLE','CATHEDRAL'],['TESADO','MILENA','BOHOLANO'],['ABBANG','JOHN ERIC PAUL','LINGAO LINGAO'],['ALBAO','MARK LESTER','ALEJO'],['ARGOTA','RYAN','BALINGASA'],['BALUCAS','JHANMER','GENEROSO'],['CUYOS','MHIKE ANDREI','DUMA'],['DABLO','JOMEL','ALEGADO'],['GANDEZA','STEVEN','MORENO'],['GUZMAN','MARK LUIS','RAMOS'],['JACER','ANTONIO','CAYOBIT'],['LIABRES','GEMMAR','O'],['PLOPINIO','GIAN KENNETH','CLACIO'],['ROTONI','CHRISTIAN','USERO'],['SALAC','ANGELO','CLACIO'],['SAMONTE','CRIZ GHABRIEL','DAPIOSEM'],['TORREON','DENVER','TAGCIP'],['VELASCO','KURT JON LOUIE','OFENDA']],
        'BSCS-2N1' => [['BALBIDO','FATIMA','MARAS'],['DIAZ','LYNETH','ALMARIO'],['FAJARDO','MARY GRACE','GARCIA'],['GENODEPA','ROSELLE','CORECES'],['MERCADO','MEAGAN','ESCALANTE'],['PANTI','ANGEL',''],['TULIN','THARQ JELO','PAGARAO'],['ALCOBER','EMMANUEL','BARBOSA'],['ANGCANAN','EZEKIEL','LIM'],['BAGHARI','CHRIS THIEGO','T'],['BUENAVENTURA','HARLEY','BILUAL'],['CABALLERO','JHAVIE JYBE','TACADAO'],['CORNEJO','MARLON','DUTIG'],['LAYNES','CHRISTIAN EDMAR','LUCIDO'],['MAGPAYO','ARWIN','LAURORA'],['PAYUMO','KITH NATHAN LEI','PABLO'],['QUITILEN','JOEY','DIGOS'],['ROSAL','CHRISTIAN','VILLACARLOS'],['ROSAL','CHRIZYRUZE','VILLACARLOS'],['SAÑO','NATHAN CARL','ROYO'],['TUYOR','ALDRIN','SERTIMO'],['VALENCIA','YURI','RUIZ'],['YABUT','MARI ANTHONY LORENZ','QUIRANTE']],
        'BSOA-2N1' => [['ADRIAS','ELIZA','MABBORANG'],['AMULAR','GERNITTE JARED','CACAO'],['AQUINO','ALEX DHAWN','MABINI'],['ARCIAGA','PATRICIA','MALANA'],['BASA','PRINCESS MONICA','CATAPAN'],['BASIHAN','SHEANA','GILBER'],['BELAZA','FLORENDA','CASTIDADES'],['BOÑON','VALERIE ANN',''],['CLAVERIA','ARLISH JUNE','-'],['COLARINA','MA. KARLA FE','BALATON'],['DAEF','AMIHAN MAY',''],['DE LEON','PAULINE KHIM','SANCHEZ'],['DUMAGAT','SHAINA','JERUSALEM'],['ESTOLANO','REGINE','LEGASPINA'],['GABIN','JENNILYN','CANANAO'],['GURO','AMOR CHARITY','DELA CHINA'],['HERNANDEZ','MICAELLA','ACEDERA'],['HOMBRIA','MA. FE','TABON'],['JOSUE','ANGELA','DATUL'],['LABAGALA','JOANA MAE','CADILE'],['LEUTERIO','ROVELYN','MORENO'],['LORIA','AMY GRACE','CATILO'],['MADRIAGA','MHARIS EMMNUELLE','MACHATE'],['MANAOG','AGNES DAYAH','ARINES'],['MARIANO','KIMBERLY','CABAHUG'],['NAVA','KATHERINE JOY M','MANILINGAN'],['PELOJERO','PRENCESS','CANIMO'],['PRECONCILLO','MARICAR','TAGUIAM'],['RICO','CECILIA','S'],['RIVERO','CHARISH','N/I'],['SALCEDO','GRACEL','VALENZUELA'],['SULA','MARY ANN','MARANAN'],['TAYCO','JOLINA','MARTINEZ'],['ZURITA','JOANA MAY','BAUSAMO'],['AGOS','JANIEL AXEL','FORTUNO'],['ALVAREZ','R-IAN SZHERWIN','VILLA SERAN'],['BONSUCAN','JILBER KIM','SIALONGO'],['CALIGAGAN','DAVE','CALOSA'],['CORNEJO','ALFRED','SALVACION'],['DE GALA','JERICHO','-'],['ENDRIGA','JOHN MARK','VILLA'],['GAMBOA','MARC EPHRAEM','FUENTES'],['GOMEZ','RICHARD','ABIAD'],['IBAY','WINSTON','VILLAR'],['REMOTO','JUSTIN','BALGOS'],['SALE','KENNEDY','SONA'],['SITOY','VINCENT',''],['TABARES','IAN','LIMON']],
        'EDUC-2N1' => [['AGUSTIN','ATASHA','MILCA'],['ANTAZO','JOHN REY','ABAD'],['BALEROS','KARLA SHANEA','FERNANDEZ'],['BARLAS','AIRAMIE',''],['BRUTAS','JAMILLE','SALAMANQUE'],['CHUA','BENJO','AGBUYA'],['COYNO','ROY','JUMAWAN'],['Encarquez','Mary Jane','Bautista'],['FORTALEZA','JOYCE ANN','COLICO'],['FRANCISCO','SAMANTHA KOLHYN','M'],['GONZALO','GIZELLE','MARJES'],['HERNANDEZ','SAMANTHA','DUCANTE'],['JOVES','PRINCESS JOIZA','DESPUES'],['KING','ANGELINE','BENGUET'],['KIUNESALA','CHICQUI','BAUTISTA'],['LANZUELA','ROMNICK','GAHIS'],['LEONIDA','CHESKA','CEZAR'],['LOPERA','ARJEAN','DELIMA'],['LOYOLA','SHIELA MAE','BUENAFE'],['MANGINAO','MARK ANTHONY','ANDONG'],['MARFIL','GERALDINE','ABAD'],['MENDOZA','CLARK JAYSON',''],['NAVARES','ANDREA AMOR','TRINIDAD'],['PANER','RHEA JANE','PENETRADO'],['PISTON','SHEENA MAE','FRANCISCO'],['POLENDEY','JUSTEEN','SURBONA'],['QUIZON','JAN MARK','P'],['RAMOS','FELICITY FAITH','DULOT'],['REQUIPO','FATIMA','ABIDAÑia'],['RODIL','JOHN GABRIEL','PESTAÑO'],['RULLODA','GILLEINNE JORJE','CABANG'],['SALAZAR','HANIE FRIELYN','COSME'],['SUBALISID','ALTHEA','N/I'],['VERAÑA','JUVELLE','LUCERO'],['VICENTE','RUSTY JAY','DONGON']],
        'BSCS-3M1' => [['AMADO','MYRLINE','CASUPANG'],['AWAT','ARIANNE LEIH','AZUPARDO'],['DALUZ','LORRAINE','PORTACIO'],['MARTINEZ','ANGELICA ANNE','CAJEPE'],['TRINIDAD','JESLYN','SURUIZ'],['ADORNA','KENNETH','WAGA'],['CABRITIT','JAYNIEL','SANTIAGO'],['CELMAR','PETER PAUL',''],['CORDIAL','LOUIS ALFRED','GUTIERREZ'],['DACULA','CHRISTIAN','PASCO'],['DE RAMOS','JOHN VINCENT','PAILONA'],['DELA CRUZ','PRINCE WILLIAM VINCENT','ESCARDA'],['ELIJORDE','DANILO','LAVILLA'],['ISRAEL','GABRIEL','VARGAS'],['LEVARDO','RIQUIE LARREY','MANGILIT'],['MANGILIT','MARK LANDER','GARCIA'],['MICOSA','MERVIN','D'],['MONTESCLAROS','MICO','C'],['NAVARRO','CHRISTIAN SHUCKS','CABANLIT'],['OMAYAN','KING NATHANIEL','A'],['RAÑESES','MHAYTHAN JAMES','APOSTOL'],['RESTRIVERA','ZAIMON JAMES','SEGUILLA'],['TOQUE','CHRISTOPHER GLEN','RESURRECCION']],
        'BSOA-3M1' => [['ACUÑA','STEFFHANIE','MAGPAYO'],['AURELLANO','NICOLE ANNE',''],['BALBUENA','MARVIE','PADUA'],['BAUTISTA','MA.ELAINE','VALES'],['BUENAVENTURA','MARIZ','BIMAL'],['CABALTERA','ROY ANN','BANZON'],['CAHILIG','JENYLYN','LOBEDERIO'],['CALAPARAN','MAEANN','BRIONGOS'],['DELOS SANTOS','HONEYLYN','TORFILO'],['DUMAGAN','FRITCHELE','OSAREZ'],['EJERCITO','RACHEL','BALANGUIT'],['FRANCISCO','KATE NICOLE','MASUJER'],['GINOO','ANGEL','GASPAN'],['LAS MARIAS','DEANJIE','DIANA'],['MARTIN','EUNICE','MOSQUEDA'],['MYDIN','MYBEB','RAMOS'],['OSARES','ANGELICA','MATURAN'],['RAMOS','NIELA BELLE','DELADIA'],['SABENORIO','BERYLL JOY','DELMONTE'],['TAPION','CARRIL ANGEL','SILANG'],['TEPAIT','ROMA','JAVIER'],['TORRES','JOANA FRANCIA','N/I'],['ZERRUDO','DENNISE ANNE','ZAMORA'],['APLACADOR','JOHN MEG','CERVANTES'],['MONTIERDE','JHON TROY','DEL MUNDO']],
        'EDUC-3M1' => [['ABAÑO','SHANE MARIE','PARREÑO'],['AMARAÑO','NICOLE','ROQUE'],['BANATE','VENUS LYZA','SASE'],['BARDELAS','APRIL NEL','MIRANDA'],['CUSAY','ASHLEY','DESISTO'],['DE PAZ','JOANNE','QUINIQUITO'],['GARCIA','RAZEL','APIPI'],['GARCIA','ROSALIE','APIPI'],['OLAIVAR','JOY JESUSA','ROSCO'],['RODERNO','JULIA JONIAH',''],['CASICA','ROLANDO','OLEMBERIO'],['PAGHARION','MARK JOEL','-']],
        'BSCS-4N1' => [['ALLADA','REALYN','CALWIT'],['AMBAY','LOVELY JANE','VALENTIN'],['BALDOVINO','KAYE','CARRANZA'],['LEVISTE','VEINA IRENE','DE LUNA'],['SUMATRA','MARINELLA','AMOROSO'],['AÑASCO','JULIUS','ALONZO'],['BALANA','RALVIN','A'],['CARRASCAL','CHARLSON','FERNANDEZ'],['CERBITO','MIGUEL','LOMOCSO'],['CHUA','JOHN REINER','ANAS'],['FERMAN','ADRIAN','NIEVA'],['HAYAO','KURT','SARENO'],['JAVIER','KENNETH','ARQUITA'],['LAÑOHAN','NIÑO','ROMERO'],['LOZANO','ANGELO','OCBA'],['MISLANG','ROYLAN','BUMANLAG'],['MORENO','RENZ RYAN','YTURALDE'],['SANDIG','MARK JOVEL','SERVANDE']],
        'BSOA-4N1' => [['BELETA','NICOLE','COBILLA'],['BERMILLO','SUNSHINE','LOVENDINO'],['CARIÑO','JOYCE ANNE','RIMANDO'],['ESTOLLOSO','CRIZEAL','BAGALLON'],['KARUNUNGAN','JEWEL','RAMOS'],['LAPASTORA','JEMIE JOY',''],['MANIEGO','SAMANTHA','FLORES'],['NEGRITE','SHAIRA MAE','LACHICA'],['PEREZ','CYRIL','VIOLA'],['PEROCILLO','MA.MAAN GRACE',''],['SILVERIO','STEPHANIE','M'],['TARROBAL','JANNAH MARIE','MARCELO'],['VELASQUEZ','REA MAE','ALIPAN'],['YOSORES','PEACH AVA','OLFATO'],['CRUZ','KYLE DANIELLE CESAR','GABALES'],['GARCIA','KEITH BRYAN','INFANTE'],['LAGO','EMER','OLARTE'],['MAGNAYE','DON','-'],['MALINAO','JOHN FORD','BLASABAS'],['ORDOÑA','AYAN FRANCE','ROSAROSO'],['POLO','AHLF CEDRIC','LANCETA'],['QUIANE','RYVIN','MACALLAN']],
        'EDUC-4M1' => [['GALIT','PATRICK JOHN','SUNDO'],['MIRANDA','NELSON','ALBINDO'],['OLAVIAGA','LANCE PHILIP','MONTILLA'],['PERMIJO','MARK ANTHONY','LEANA'],['ALONG','ALICIA','TABLASON'],['VALGUNA','JENNY','MAGARZO'],['ANG','RHEMYLYN','PERITO'],['DEL CASTILLO','JASMIN FAITH','ENRIQUEZ'],['DEL CASTILLO','JEMIMAH','D'],['GELBOLINGO','REGINA YSOBEL','JURIDICO'],['TAYOTO','MARJORIE','T'],['MIRANDA','CRESCHILLE','ROSENDAL'],['MONTEREY','RONALYNE','L'],['NAVEA','JOANA MAE','TEJAS'],['OLAVIAGA','REI CAMILLE','BERMAS'],['PAJE','ROSELLE','JEBULAN'],['REPOLLO','BLESSED NICOLE','GUALBERTO'],['SOLO','JERICHA','GARCIA']],
        'EDUC-4N1' => [['ALVAREZ','CRESHELLE','MARQUESES'],['IDE','ERIKA','S']],
        'ABM-1M1' => [['ADVINCULA','LEBRON JAMES','B.'],['ARTIOLA','ALBERT','N.'],['BRAGAT','JOHN MICO','L.'],['CLAVO','JOSHUA','C.'],['HUIT','ADRIAN DAVE','G.'],['JORGE','ROGERICK','L.'],['ORGAS','JOHN','J.'],['OTACAN','DARYL','C.'],['ALANO','JHILIAN','G.'],['ARLANTE','ARLIE MAY','R.'],['AUDENCIAL','MARY ELAINE','A.'],['BERNARDINO','RAZHEN','M.'],['CERTIZA','ALMERA JOY','B.'],['CLARIDAD','JESSA','E.'],['CRUZ','CHRISTINE','C.'],['DABODA','ROXANNE','B.'],['DACULA','KAREN CLAIRE','P.'],['ESTRADA','MARIVIC','M.'],['INABANG','NORJANNAH','D.'],['ONGCAL','CLARISSE ANNE','B.'],['ORILLONEDA','JURISH MAE','M.'],['PABLO','LOVELY','P.'],['PAPNA','HELEN','Q.'],['PERA','CLYDEL ROSE','I.'],['PIMENTEL','MARIANNE RAIN','A.'],['RAYSES','JACKY LOU','S.'],['SALTING','JAMAICA','M.'],['TABURNAL','SHIZKA','R.'],['TARROBAL','LYKA REEZE','M.'],['TEOXON','ABIGAIL','S.'],['TOLENTINO','LYDEL','B.'],['Asegurado','E-jay','M.'],['Caimoy','MJ','M.'],['Espino','Daniel Lawrence','-.'],['Lacdao','Christian Paul','D.'],['Ceballos','Mayca Grace','C.'],['Desquitado','Ziethly Kate','B.'],['Donato','Bhaby angel','A.'],['Filosopo','Lucille','P.'],['Geslani','Ronagrace','G.'],['Guillepa','Arlene','A.'],['Laguardia','Kimberly','D.'],['Manalang','Mica Jane','L.'],['Nablo','Rikki Mae','A.'],['Nolasco','Mary Ann','R.'],['Olitoquit','Ella Mae','V.'],['Peregrino','Aivy','S.'],['RAMOS','MARRY YVONE','C.'],['Sebial','Angel Royen','M.'],['Tulipas','Abegail Lian','R.'],['Villanueva','Lindsay Nicole','Y.'],['Pucio','Bernadette','V.'],['Solis','Precious Ann','D.'],['Togonon','Shyra','D.'],['Ababa','GemarK','C.'],['Balitustos','John loyd','A.']],
        'ABM-1M2' => [['ARCIAGA','KING LAWRENCE','B.'],['CAMBA','RESTY','A.'],['CRUZ','KENSHIN','G.'],['GATCHALIAN','KENGIE','L.'],['ORCALES','YUAN DOMINGO','P.'],['RIÑON','JAN VINCE','A.'],['AGNES','NADYN','R.'],['AMULAR','MELJANE','A.'],['BIÑAS','PRINCESS ZABNEL','A.'],['CABANTING','MILLICENT AMAYLA','S.'],['CAMIA','KRISTINE GIA','E.'],['CEDRON','PRINCESS ZAIRA','A.'],['DE LEON','LEAN NICOLE','L.'],['DESALES','KEZIAH LYN','P.'],['DIMAANO','HANNA JOYCE','N.'],['DIONEDA','RHEA MAE','P.'],['ETIC','MARY VINE','B.'],['FADULLO','JELIAN ROSE','V.'],['GARCIA','ALEXIE LEI','.'],['LUY','SHAIRA','R.'],['MISALUCHA','KRISTHEL FRANCE','C.'],['PEÑARANDA','NORLYNLUZ','M.'],['RAMOS','DENICE MARIE','G.'],['RAZONADO','BABY LOVE','C.'],['ROJAS','YHANNA','M.'],['SARMIENTO','IRISH JOY','T.'],['SICAT','RYZAMAE','V.'],['TAN','AIREN CASSANDRA','M.'],['TORRES','SKYLEHR SAVANNAH','U.'],['TUBIS','RHAINNIEL','L.'],['Agustino','Deseree','B.'],['Barbosa','Nathaly','L.'],['LOBREN','ANDREA','M.'],['MAGPANTAY','NICHOLE','H.'],['Matula','Lhara Ayesha','C.'],['Navarro','Justine Mae','D.'],['Surdilla','Elline Christine','B.'],['Manimtim','James Edward','B.'],['ALBESA','CYRIL ANN','S.'],['BINGAYAN','MARIAN','A.'],['Delos Reyes','Jonalyn','E.'],['DUCALING','ZOILA','E.'],['Ford','Sheena Mae','M.'],['Tañega','Jessel Mae','S.'],['Aguilar','Symon','S.'],['Cadao','John Jeremy','J.']],
        'ABM-1N1' => [['BACHICHA','NOVIEM VER','R.'],['BIOJON','JHON HAROLD','C.'],['DELOS REYES','JOEL','R.'],['JAEN','JASON','T.'],['LIZADA','BENCH JOSHUA','S.'],['MADRIDANO','JOHN DAVE','M.'],['MARTINEZ','ALEXIS','G.'],['MATIENZO','DAVE','.'],['PUNONGBAYAN','ARJEL','M.'],['RODRIGUEZ','XHALEEWELL','C.'],['SANQUILLOS','JAO LAWRENCE','E.'],['ANDOR','CATHERINE','L.'],['BENAVIDEZ','HANNAH','P.'],['BERANGBERANG','JHUSMINKIETH','P.'],['BORDONA','JINKY','V.'],['BRIONES','ALLYS JUANE','A.'],['DEL PILAR','CHRISTINE SHANE','F.'],['DEMETRIO','NEIRIZ JANNAIZA','N.'],['DOMING','IRISH MAE','M.'],['FARAON','ANGELICA','L.'],['FLORES','SAMANTA','E.'],['GATCHALIAN','DANICA MAE','M.'],['GUEVARRA','ALYSSA','D.'],['JARIOL','RICHELYN','M.'],['PALMES','RHEN MAE','N.'],['ROBLES','MERCEL','A.'],['TAÑON','MERLIE JOY','A.'],['TARRIELA','CHRISTINE','G.'],['VENCIO','VALERIE ANNE','L.'],['YONSON','MARY CLAIRE','A.'],['BORDIOS','CARLA MAE','B.'],['MANALO','JEAN NICOLE','B.'],['ABORQUE','RHYZEL MAE','C.'],['CAÑETE','MARIANNEL','H.'],['Corona','Ahrian Joyce','O.'],['Dematera','Kristelle','P.'],['Glifonea','Jade Ann','B.'],['GUANSING','ANDREA NICHOLE','S.'],['HERRERA','JHANNA','R.'],['Olayta','Lovely Mae','D.'],['PEDERE','ASHANTA NICOLE','P.'],['QUINIANO','GWYNETH SHANE','C.'],['ROMANO','JISELLE','B.'],['Valencia','Hanna Lheighven','-.']],
        'ABM-11SC' => [['DOYOLA','CHRISTOPER','V.'],['MARILAO','SHIELLO','G.'],['MENDIOLA','JUDY ANN','P.'],['ADUCA','MYRA','A.'],['Bautista','Mary Denz','-.'],['CELLS','MIRUMEL','L.'],['MANABAT','RACHEL ANN','H.']],
        'HUMSS-1M1' => [['BAYBAY','MIKE JARED','M.'],['CANLAS','JOHN AXEL','U.'],['DOLLOPAC','JHASTIN DAVID','D.'],['GARCIA','JEROME','S.'],['MARIN','ANGELO JAMES','S.'],['OLIQUINO','ERL COBI','A.'],['RAYNANCIA','JAY LAURENCE','N.'],['ALEJO','LHIANNE','C.'],['ALOSA','RECCA','M.'],['ASTORGA','NICOLE','A.'],['BALQUIN','ANGEL','A.'],['CABATUANDO','ALEIRA CHLOE','B.'],['DELA CRUZ','ASHLEY YVONNE','C.'],['HIPOLITO','YUNELLA','R.'],['MANUNGAS','VENIZE KRISHA','P.'],['MORATALLA','MAJAH ZHAINEDYLLE','E.'],['PAGADOR','JANEUELLE','B.'],['PEÑAFLOR','ISABEL','M.'],['REVIRO','RANA LEA','T.'],['ROBANTES','CHANEL ALLURE','C.'],['SOLLEGUE','KELLY CAZANDRA','P.'],['SUNAJO','ANGELYN','P.'],['YUBOC','SHANEEN KHRYSS','L.']],
        'HE-1M1' => [['BACLAO','JOHN DANIEL','A.'],['DIWATA','JOSHUA','C.'],['FRISCO','AUDREY','A.'],['GONZALES','AR-JAY','Q.'],['GRATUITO','DOMINIC','B.'],['HERMANOS','ARGYNE JAY','C.'],['LIBRES','MARIANO','T.'],['MONTOYA','IVAN JAMES','M.'],['SALUMBIDES','ALEXANDER JAMES','F.'],['SANZ','KIRBY','G.'],['TANDAYAG','ROLAND JUSTIN','M.'],['UMBAY','JOHN LORENZ','Q.'],['ACEBUECHE','JHENY MHAE','N.'],['AMATOSA','CHRISTINE MAY','F.'],['BETITA','MARIA CAMILLA','V.'],['CAREON','JEARLENE','V.'],['DELA ROSA','SOFIA CASSANDRA','M.'],['DERLA','AYESHA','D.'],['EDLOY','ANGEL ANN','A.'],['ESCOBER','RUTH ARIADNE','V.'],['ESPINA','ANGEL MAE','S.'],['EVANGELISTA','EDRALYN','T.'],['LEMON','KHAILYCATE YHUNICE','R.'],['MAGDAMIT','GENIEVIEVE','P.'],['MAMBA','ERA GRACE','I.'],['MINGUIN','MARIE ANGELA','D.'],['PESCANTE','ELIJAH','V.']],
        'ICT-1M1' => [['AGUIRRE','GENESIS','B.'],['ARPILLEDA','JUSTIN JAY','P.'],['BULAC','GIAN CARLO','D.'],['CORAJE','REYMOND','C.'],['DEL ROSARIO','ROWJOHN LORENCE','M.'],['DELGADO','JOHN JOSHUA','P.'],['ESPIRITU','HERO','S.'],['GAUT','ELLIANDREI JADE','Y.'],['LAGAHIT','MARK JOHNNEL','M.'],['LAS MARIAS','IANJIE','P.'],['MAMARIL','JERSON','C.'],['MARTINEZ','JAEDAN AHNIELL','E.'],['SOLIS','MARK ANTHONY','S.'],['ZURITA','GABRIEL','A.'],['CORDOVA','SHIELA MAE','A.'],['ERGUIZA','KATRINA','A.'],['GUARIN','KIMBERLYN','O.'],['LEPALAM','ANGEL KIM','J.'],['MILLANO','MARIAN','V.'],['ONDANGAN','HANNAH ALLEYAH','U.'],['PITOS','NIÑA CHARISH','M.'],['RIBERAL','ALILIA CAMILLE','F.'],['SOLIS','DIANNE KATE','A.']],
        'HUMSS-3M1' => [['ARNALDO','MARK JASTINE','M.'],['Caballero','Paulo','C.'],['Callueng','Vaughn Louelle','B.'],['Carlos','Prince Vernie','I.'],['Delos Reyes','Jhervin Grhei','T.'],['Fuertes','Benjie Jr.','M.'],['JAVELO','JOHN ALFRED','E.'],['Manaig','John Louie','F.'],['Menguito','Prince Kyle','I.'],['NEGOLO','JOHN MARKY','C.'],['Noblefranca','Laurence James','D.'],['PAGCALIWAGAN','AERHON','D.'],['RESTRIVERA','MARK JERICK','A.'],['Talimay','John Angel','C.'],['Valles','Jay Iverson','B.'],['Bayson','Lejana','S.'],['FARO','JEAN KATHLEEN','D.'],['Fuentes','Kyle','B.'],['Garcia','Alyza Chinee','V.'],['Geniblazo','Rhizza','D.'],['Jubay','Janlex Trish','M.'],['MACAPAGAL','ANNA MARIE','R.'],['Mañosa','Diana Grace','B.'],['Modesto','Mharky','C.'],['Mustaza','Aira Camille','P.'],['Quijano','Nadine Alyssa','P.'],['Regondola','Neisha Leiyhan','D.'],['Sagang','larahvelle','H.']],
        'HE-3M1' => [['BANDONG','JHON JHASPER','C.'],['Carlos','John Paul','T.'],['Cubelo','Ydran-Rick','S.'],['Dispabeladeras','Jerimiah','M.'],['Dominguez','Raja Cebastian','G.'],['Harina','Karl Daniel','D.'],['Maigue','John Harold','G.'],['Melodias','Kurt Lorens','R.'],['ODON','ALDRIN','D.'],['SANGLAY','JOHN WESLEY','G.'],['Secula','Arjay','.'],['Sengco','Mcnesse Jaerjarvi','D.'],['Valenzuela','John Miguel','E.'],['Abejo','Sheena Anne','S.'],['Adonis','Mary Grace','M.'],['Alolor','Shekainah','C.'],['Aspi','Angela','L.'],['AVENDAÑO','Princess Angel','C.'],['BAGUAL','LHAICA','B.'],['Belandres','Charlene','L.'],['Cortez','Ma. Jinky','F.'],['Dela Cruz','Hecy Jya','M.'],['Dimaano','Daphne Vhenice','V.'],['Lacdao','Angel Mae','D.'],['Mero','Lenie Ann','Y.'],['Sale','Carrie Lei','L.'],['STARLING','NATASHA','.']],
        'ICT-3M1' => [['AGUILO JR.','RAMEL','R.'],['BALAORO','JOSEPH','C.'],['Baluca','Lowel Ezekiel','T.'],['BARBA','FRANZ RUSSEL','D.'],['CAÑETE','RALPH KENNETH','P.'],['DINGLASAN','JARETT ADHAN','T.'],['EBEN','JAMIR ANGELO','A.'],['ELARDO','ELIZSAR','S.'],['ENRIQUEZ','KIAN GREY','.'],['Lutiva','Ezekiel Carl','.'],['POLENDEY','JHUDIEL','S.'],['RICAFORT','ANDRES PIO','B.'],['SANTOS','RHAINE','B.'],['SANTOS','KARL ALDRIN','M.'],['Tarriela','Justine','G.'],['Tayo','Stanley Emmanuel','D.'],['Tiangson','Lormin Jr','B.'],['Turirit','Justine carl','G.'],['VALDEZ JR.','Charlie','L.'],['Balagat','Michaela Rose','D.'],['Flores','Aryan Rose','R.'],['Maglalang','Jamaica Rain','F.'],['OCAMPO','JAZMINE','P.'],['Paderes','Andrea Nicole','P.'],['PUZON','LYN ANDRAE','A.'],['Tupaz','Sihinity Vaine','N.']]
    ];

    $total_students_created = 0;
    foreach ($all_students_by_section as $section_code => $students) {
        $section_stmt = $pdo->prepare("SELECT id FROM sections WHERE section_code = ?");
        $section_stmt->execute([$section_code]);
        $section_id = $section_stmt->fetchColumn();

        if ($section_id) {
            foreach ($students as $student_data) {
                $last_name = $student_data[0];
                $first_name = $student_data[1];
                $middle_name = $student_data[2] ?? '';
                $full_name = trim($first_name . ' ' . $middle_name . ' ' . $last_name);
                
                $check_student = $pdo->prepare("SELECT COUNT(*) FROM students WHERE full_name = ? AND section_id = ?");
                $check_student->execute([$full_name, $section_id]);

                if ($check_student->fetchColumn() == 0) {
                    $insert_student = $pdo->prepare("INSERT INTO students (last_name, first_name, middle_name, full_name, section_id) VALUES (?, ?, ?, ?, ?)");
                    $insert_student->execute([$last_name, $first_name, $middle_name, $full_name, $section_id]);
                    $total_students_created++;
                }
            }
        } else {
            $errors[] = "❌ Section code '{$section_code}' not found. Students for this section were not added.";
        }
    }
    $setup_messages[] = "✅ Inserted/verified {$total_students_created} students into the 'students' table.";

    // --- 4. STUDENT ACCOUNT CREATION (CORRECT ORDER) ---
$setup_messages[] = "⏳ Creating student login accounts...";
$students_stmt = $pdo->query("SELECT id, full_name, first_name, last_name FROM students");
$all_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

$student_users_created = 0;
$default_password_hashed = password_hash('pass123', PASSWORD_DEFAULT);

foreach ($all_students as $student) {
    // Use full first name instead of just initial to avoid duplicates
    $clean_lastname = preg_replace('/[^a-zA-Z0-9]/', '', $student['last_name']);
    $clean_firstname = preg_replace('/[^a-zA-Z0-9]/', '', $student['first_name']);
    $username = strtoupper($clean_lastname . $clean_firstname);

    $check_user = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check_user->execute([$username]);

    if ($check_user->fetchColumn() == 0) {
        $insert_user = $pdo->prepare("INSERT INTO users (username, password, user_type, full_name, student_table_id) VALUES (?, ?, 'student', ?, ?)");
        $insert_user->execute([$username, $default_password_hashed, $student['full_name'], $student['id']]);
        $student_users_created++;
    }
}
$setup_messages[] = "✅ Created {$student_users_created} new student login accounts. The password for all students is 'pass123'.";

    // --- 5. TEACHER DATA (RESET AND CORRECT STRUCTURE) ---
// First, reset the teachers table to start fresh
$pdo->exec("DELETE FROM section_teachers"); // Remove assignments first
$pdo->exec("DELETE FROM teachers"); // Remove all teachers

// Reset auto-increment based on database type
if ($is_postgres) {
    $pdo->exec("ALTER SEQUENCE teachers_id_seq RESTART WITH 1");
} else {
    $pdo->exec("ALTER TABLE teachers AUTO_INCREMENT = 1");
}

// Define teachers by department
$college_teachers = [
    'ATIENZA, MICHAEL G.',
    'ELLO, GERALD',
    'ESPEÑA, RENE EMMANUEL',
    'OCTAVO, APRIL JOY',
    'PATALEN, FRANCIS',
    'DIMAPILIS, NERLIE'
];

$shs_teachers = [
    'ALCEDO, LANCE',
    'ANGELES, AIRA',
    'BATILES, EDWIN',
    'GAJIRAN, CLAIRE',
    'GARCIA, ROWELL',
    'GORDON, RAINIEL',
    'LIBRES, AJ',
    'LAGUADOR, RIKKA',
    'RIVERA, GRACE',
    'ROQUIOS, JERALINE',
    'SANTOS, PRINCE LOUIE',
    'TINGSON, ARJILEN',
    'UMALI, JOSHUA',
    'YABUT, EDITH'
];

$both_teachers = [
    'CALCEÑA, MICHAEL ANGELO',
    'CARMONA, JENNYVEY',
    'GENTEROY, JENYCA',
    'GORDON, RAIVEN',
    'ICABANDE, EPHRAIM',
    'IGHARAS, ROWENA A.',
    'JIMENEZ, CARL JOSEPH',
    'LACERNA, NORI',
    'MAGNO, JOY',
    'MATILA, MAR JAY',
    'ORNACHO, DOMINIC',
    'PATIAM, FRANCIS',
    'RENDORA, HASEL',
    'RODRIGUEZ, JUDELITO',
    'TESORO, MARITES',
    'VALENZUELA, AZRIEL',
    'VELE, ALLAN',
    'VELE, SHEIN'
];

$teachers_created = 0;

// Insert COLLEGE teachers
foreach ($college_teachers as $teacher_name) {
    $check_teacher = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE name = ? AND department = ?");
    $check_teacher->execute([$teacher_name, 'COLLEGE']);
    
    if ($check_teacher->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO teachers (name, department) VALUES (?, ?)")->execute([$teacher_name, 'COLLEGE']);
        $teachers_created++;
    }
}

// Insert SHS teachers
foreach ($shs_teachers as $teacher_name) {
    $check_teacher = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE name = ? AND department = ?");
    $check_teacher->execute([$teacher_name, 'SHS']);
    
    if ($check_teacher->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO teachers (name, department) VALUES (?, ?)")->execute([$teacher_name, 'SHS']);
        $teachers_created++;
    }
}

// Insert BOTH teachers (create two entries - one for each department)
foreach ($both_teachers as $teacher_name) {
    // Insert for COLLEGE
    $check_college = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE name = ? AND department = ?");
    $check_college->execute([$teacher_name, 'COLLEGE']);
    
    if ($check_college->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO teachers (name, department) VALUES (?, ?)")->execute([$teacher_name, 'COLLEGE']);
        $teachers_created++;
    }
    
    // Insert for SHS
    $check_shs = $pdo->prepare("SELECT COUNT(*) FROM teachers WHERE name = ? AND department = ?");
    $check_shs->execute([$teacher_name, 'SHS']);
    
    if ($check_shs->fetchColumn() == 0) {
        $pdo->prepare("INSERT INTO teachers (name, department) VALUES (?, ?)")->execute([$teacher_name, 'SHS']);
        $teachers_created++;
    }
}

$setup_messages[] = "✅ Created/verified {$teachers_created} teacher entries (COLLEGE: " . count($college_teachers) . ", SHS: " . count($shs_teachers) . ", BOTH: " . (count($both_teachers) * 2) . ")";
    
    // --- 6. TEACHER ASSIGNMENTS (CORRECTED NAMES) ---
$all_assignments = [
    'BSCS-1M1' => ['VELE, ALLAN', 'RODRIGUEZ, JUDELITO', 'JIMENEZ, CARL JOSEPH', 'RENDORA, HASEL', 'LACERNA, NORI', 'ATIENZA, MICHAEL G.'],
    'BSCS-2N1' => ['RODRIGUEZ, JUDELITO', 'ICABANDE, EPHRAIM', 'RENDORA, HASEL', 'GORDON, RAIVEN'],
    'EDUC-4M1' => ['TESORO, MARITES', 'ELLO, GERALD'],
    'ICT-3M1' => ['LIBRES, AJ', 'LACERNA, NORI', 'ICABANDE, EPHRAIM', 'UMALI, JOSHUA', 'GORDON, RAIVEN'],
    'HUMSS-3M1' => ['CARMONA, JENNYVEY', 'LACERNA, NORI', 'LIBRES, AJ', 'PATIAM, FRANCIS', 'RENDORA, HASEL', 'GARCIA, ROWELL', 'BATILES, EDWIN'],
    'HE-3M1' => ['CARMONA, JENNYVEY', 'BATILES, EDWIN', 'RIVERA, GRACE', 'PATIAM, FRANCIS', 'UMALI, JOSHUA', 'CALCEÑA, MICHAEL ANGELO'],
    'HE-1M1' => ['LACERNA, NORI', 'RODRIGUEZ, JUDELITO', 'VALENZUELA, AZRIEL', 'MATILA, MAR JAY', 'UMALI, JOSHUA', 'GENTEROY, JENYCA'],
    'ICT-1M1' => ['LACERNA, NORI', 'RODRIGUEZ, JUDELITO', 'VALENZUELA, AZRIEL', 'MATILA, MAR JAY', 'JIMENEZ, CARL JOSEPH']
];

function assignTeachersToSection($pdo, $section_code, $teacher_names, &$total_assignments) {
    $section_stmt = $pdo->prepare("SELECT id, program FROM sections WHERE section_code = ?");
    $section_stmt->execute([$section_code]);
    $section_info = $section_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section_info) {
        global $errors; $errors[] = "Section {$section_code} not found"; return;
    }
    
    $section_id = $section_info['id'];
    $department = $section_info['program'];
    
    foreach ($teacher_names as $teacher_name) {
        $teacher_stmt = $pdo->prepare("SELECT id FROM teachers WHERE name = ? AND department = ?");
        $teacher_stmt->execute([$teacher_name, $department]);
        $teacher_id = $teacher_stmt->fetchColumn();
        
        if ($teacher_id) {
            $check_assignment = $pdo->prepare("SELECT COUNT(*) FROM section_teachers WHERE section_id = ? AND teacher_id = ?");
            $check_assignment->execute([$section_id, $teacher_id]);
            
            if ($check_assignment->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO section_teachers (section_id, teacher_id) VALUES (?, ?)")->execute([$section_id, $teacher_id]);
                $total_assignments++;
            }
        } else {
            global $errors;
            $errors[] = "⚠️ Teacher '{$teacher_name}' not found for department '{$department}'. Assignment to '{$section_code}' skipped.";
        }
    }
}

$total_section_assignments = 0;
foreach ($all_assignments as $section_code => $teachers) {
    assignTeachersToSection($pdo, $section_code, $teachers, $total_section_assignments);
}
$setup_messages[] = "✅ Processed teacher assignments. Total new assignments: {$total_section_assignments}.";

} catch (PDOException $e) {
    $errors[] = "❌ Database Error: " . $e->getMessage();
} catch (Exception $e) {
    $errors[] = "❌ General Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Database Setup Results</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px; 
            margin: 0;
            min-height: 100vh;
        }
        .container { 
            max-width: 900px; 
            margin: auto; 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.2); 
        }
        h1 { 
            text-align: center; 
            color: #333;
            margin-bottom: 30px;
            font-size: 2.5em;
        }
        .message { 
            padding: 15px; 
            margin: 10px 0; 
            border-radius: 8px; 
            border-left: 5px solid; 
            font-size: 16px;
            line-height: 1.5;
        }
        .success { 
            background-color: #d4edda; 
            border-color: #28a745; 
            color: #155724; 
        }
        .error { 
            background-color: #f8d7da; 
            border-color: #dc3545; 
            color: #721c24; 
        }
        .info { 
            background-color: #d1ecf1; 
            border-color: #17a2b8; 
            color: #0c5460; 
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5a6fd8;
        }
        .center {
            text-align: center;
        }
        .login-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #dee2e6;
        }
        .login-info h3 {
            color: #495057;
            margin-top: 0;
        }
        .credentials {
            background: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
        }
        .examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .example-card {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Database Setup Results</h1>
        
        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($setup_messages)): ?>
            <?php foreach ($setup_messages as $message): ?>
                <div class="message success"><?php echo htmlspecialchars($message); ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (empty($errors)): ?>
        <div class="login-info">
            <h3>📝 Login Credentials Created:</h3>
            <p><strong>Admin Account:</strong></p>
            <div class="credentials">
                Username: admin<br>
                Password: admin123
            </div>
            
            <p><strong>Student Account Examples:</strong></p>
            <div class="examples">
                <div class="example-card">
                    <strong>ABAÑO, SHAWN ROVIC</strong><br>
                    Username: abanos<br>
                    Password: pass123
                </div>
                <div class="example-card">
                    <strong>ANDRES, ALLYSA</strong><br>
                    Username: andresa<br>
                    Password: pass123
                </div>
                <div class="example-card">
                    <strong>DELA CRUZ, MARIA</strong><br>
                    Username: delacruzm<br>
                    Password: pass123
                </div>
                <div class="example-card">
                    <strong>GARCIA, JEROME</strong><br>
                    Username: garciaj<br>
                    Password: pass123
                </div>
            </div>
            
            <p><em><strong>Username Pattern:</strong> lastname + first letter of firstname (all lowercase, special characters removed)</em></p>
            <p><em>All student passwords are: <strong>pass123</strong></em></p>
        </div>
        <?php endif; ?>
        
        <div class="center">
            <a href="index.php" class="btn">🏠 Return to Login</a>
            <?php if (!empty($errors)): ?>
                <a href="database_setup.php" class="btn" style="background: #dc3545;">🔄 Retry Setup</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>




