<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher's Performance Evaluation - <?php echo safe_display($teacher_info['name'] ?? ''); ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --maroon: #800000;
            --dark-maroon: #5E0C0C;
            --gold: #DAA520;
            --light-gold: #FFD700;
            --pale-gold: #fff9e6;
            --cream: #fffaf0;
            --white: #ffffff;
            --light-gray: #f5f5f5;
            --shadow: rgba(94, 12, 12, 0.1);
        }

        body {
            background-color: var(--cream);
            color: var(--dark-maroon);
            line-height: 1.6;
            padding: 20px;
            padding-top: 70px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: var(--white);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px var(--shadow);
            position: relative;
            border: 1px solid var(--gold);
        }

        /* Skeleton Loading Styles */
        #skeleton-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--cream);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            transition: opacity 0.5s ease;
        }

        .skeleton-logo {
            width: 300px;
            height: 40px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 5px;
            margin-bottom: 30px;
            animation: loading 1.5s infinite;
        }

        .skeleton-header {
            width: 80%;
            height: 30px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 5px;
            margin-bottom: 15px;
            animation: loading 1.5s infinite;
        }

        .skeleton-subheader {
            width: 60%;
            height: 20px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 5px;
            margin-bottom: 40px;
            animation: loading 1.5s infinite;
        }

        .skeleton-card {
            width: 90%;
            height: 100px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: loading 1.5s infinite;
        }

        .skeleton-row {
            width: 95%;
            height: 15px;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            border-radius: 3px;
            margin-bottom: 10px;
            animation: loading 1.5s infinite;
        }

        .skeleton-row.short {
            width: 70%;
        }

        .skeleton-row.medium {
            width: 85%;
        }

        .skeleton-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f0f0f0;
            border-top: 5px solid var(--gold);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-top: 20px;
        }

        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .skeleton-hidden {
            display: none;
        }

        .fade-out {
            opacity: 0;
        }

        /* Header and Institution Styles */
        header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--gold);
            background: linear-gradient(135deg, var(--maroon) 0%, var(--dark-maroon) 100%);
            color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px var(--shadow);
        }

        h1 {
            color: var(--gold);
            margin-bottom: 10px;
            font-size: 1.8em;
        }

        h2 {
            color: var(--maroon);
            margin: 25px 0 15px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--gold);
            font-size: 1.4em;
        }

        h3 {
            color: var(--maroon);
            margin: 15px 0 10px;
            font-size: 1.2em;
        }

        .institution-name {
            font-weight: bold;
            font-size: 1.4em;
            color: var(--gold);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }

        .address {
            font-style: italic;
            color: var(--pale-gold);
            margin-bottom: 15px;
        }

        .teacher-info {
            background: linear-gradient(135deg, var(--pale-gold) 0%, var(--cream) 100%);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid var(--gold);
            box-shadow: 0 2px 5px var(--shadow);
        }

        .teacher-info h3 {
            color: var(--maroon);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }

        .teacher-info h3:before {
            content: "👨‍🏫";
            margin-right: 10px;
        }

        .language-toggle {
            display: flex;
            justify-content: center;
            margin: 15px 0;
        }

        .language-toggle button {
            padding: 10px 20px;
            margin: 0 5px;
            background-color: var(--pale-gold);
            border: 2px solid var(--gold);
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            color: var(--maroon);
        }

        .language-toggle button.active {
            background-color: var(--maroon);
            color: var(--gold);
            border-color: var(--maroon);
        }

        .language-toggle button:hover:not(.active) {
            background-color: var(--gold);
            color: var(--white);
        }

        .progress-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: var(--maroon);
            padding: 10px 20px;
            box-shadow: 0 2px 5px var(--shadow);
            z-index: 2000;
        }

        .progress-bar {
            height: 8px;
            background-color: var(--pale-gold);
            border-radius: 4px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: linear-gradient(90deg, var(--gold) 0%, var(--light-gold) 100%);
            width: 0%;
            transition: width 0.5s ease;
            border-radius: 4px;
        }

        .progress-text {
            text-align: center;
            font-weight: bold;
            color: var(--gold);
            margin-bottom: 5px;
        }

        .instructions {
            background-color: var(--pale-gold);
            padding: 15px;
            border-left: 4px solid var(--gold);
            margin-bottom: 25px;
            border-radius: 0 5px 5px 0;
            box-shadow: 0 2px 4px var(--shadow);
        }

        .rating-scale {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(to right, var(--dark-maroon), var(--maroon), var(--gold), var(--light-gold), var(--pale-gold));
            border-radius: 5px;
            color: white;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 2px 4px var(--shadow);
        }

        .rating-item {
            text-align: center;
            flex: 1;
            font-size: 16px;
            text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
            box-shadow: 0 2px 5px var(--shadow);
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--pale-gold);
        }

        th {
            background: linear-gradient(135deg, var(--maroon) 0%, var(--dark-maroon) 100%);
            font-weight: 600;
            color: var(--gold);
        }

        tr:nth-child(even) {
            background-color: var(--light-gray);
        }

        tr:hover {
            background-color: var(--pale-gold);
        }

        .rating-options {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }

        .rating-options label {
            display: inline-block;
            text-align: center;
            width: 18%;
            cursor: pointer;
            color: var(--dark-maroon);
            font-weight: 500;
            transition: all 0.2s;
            padding: 5px;
            border-radius: 4px;
        }

        .rating-options label:hover {
            background-color: var(--pale-gold);
        }

        /* Custom Radio Button Styling */
        input[type="radio"] {
            /* Hide default radio */
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--maroon);
            border-radius: 50%;
            outline: none;
            cursor: pointer;
            position: relative;
            margin: 8px 0;
            display: inline-block;
            vertical-align: middle;
        }

        input[type="radio"]:checked {
            background-color: var(--maroon);
            border-color: var(--maroon);
        }

        input[type="radio"]:checked::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background-color: var(--gold);
            border-radius: 50%;
        }

        input[type="radio"]:focus {
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.3);
        }

        textarea {
            width: 100%;
            height: 120px;
            padding: 15px;
            border: 2px solid var(--pale-gold);
            border-radius: 5px;
            resize: vertical;
            font-size: 16px;
            background-color: var(--white);
            color: var(--dark-maroon);
            transition: border 0.3s;
        }

        textarea:focus {
            border-color: var(--gold);
            outline: none;
            box-shadow: 0 0 0 3px rgba(218, 165, 32, 0.2);
        }

        .submit-btn {
            display: block;
            width: 220px;
            margin: 30px auto 10px;
            padding: 14px;
            background: linear-gradient(135deg, var(--maroon) 0%, var(--dark-maroon) 100%);
            color: var(--gold);
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 6px var(--shadow);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, var(--dark-maroon) 0%, var(--maroon) 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 8px var(--shadow);
        }

        .submit-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px var(--shadow);
        }

        .submit-btn:disabled {
            background: #cccccc;
            color: #666666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .success-message {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #28a745;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid #dc3545;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .evaluation-status {
            background: linear-gradient(135deg, var(--pale-gold) 0%, var(--cream) 100%);
            color: var(--dark-maroon);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border-left: 5px solid var(--gold);
            box-shadow: 0 2px 5px var(--shadow);
        }

        .tagalog {
            display: none;
        }

        footer {
            text-align: center;
            margin-top: 20px;
            color: var(--maroon);
            font-size: 0.9em;
            padding-top: 20px;
            border-top: 1px solid var(--pale-gold);
        }

        .back-link {
            background: var(--maroon);
            color: var(--gold);
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 15px;
            font-weight: bold;
            transition: all 0.3s;
            box-shadow: 0 2px 4px var(--shadow);
        }

        .back-link:hover {
            background: var(--dark-maroon);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px var(--shadow);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .rating-options {
                flex-direction: column;
            }

            .rating-options label {
                width: 100%;
                text-align: left;
                margin-bottom: 5px;
                padding: 8px;
            }

            th, td {
                padding: 8px 5px;
                font-size: 14px;
            }

            .rating-scale {
                flex-direction: column;
                align-items: center;
                padding: 10px;
            }
            
            .rating-item {
                width: 100%;
                font-size: 14px;
                margin: 5px 0;
            }

            /* Skeleton responsive */
            .skeleton-header {
                width: 90%;
            }
            
            .skeleton-subheader {
                width: 80%;
            }
            
            .skeleton-card {
                width: 95%;
            }

            .submit-btn {
                width: 100%;
                max-width: 300px;
            }
        }

        /* Additional decorative elements */
        .section-divider {
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 30px 0;
            border: none;
        }

        .decorative-corner {
            position: absolute;
            width: 30px;
            height: 30px;
        }

        .corner-tl {
            top: 0;
            left: 0;
            border-top: 3px solid var(--gold);
            border-left: 3px solid var(--gold);
            border-top-left-radius: 10px;
        }

        .corner-tr {
            top: 0;
            right: 0;
            border-top: 3px solid var(--gold);
            border-right: 3px solid var(--gold);
            border-top-right-radius: 10px;
        }

        .corner-bl {
            bottom: 0;
            left: 0;
            border-bottom: 3px solid var(--gold);
            border-left: 3px solid var(--gold);
            border-bottom-left-radius: 10px;
        }

        .corner-br {
            bottom: 0;
            right: 0;
            border-bottom: 3px solid var(--gold);
            border-right: 3px solid var(--gold);
            border-bottom-right-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Skeleton Loading Screen -->
    <div id="skeleton-loader">
        <div class="skeleton-logo"></div>
        <div class="skeleton-header"></div>
        <div class="skeleton-subheader"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-row"></div>
        <div class="skeleton-row medium"></div>
        <div class="skeleton-row short"></div>
        <div class="skeleton-row"></div>
        <div class="skeleton-row medium"></div>
        <div class="skeleton-spinner"></div>
    </div>

    <div class="progress-container">
        <div class="progress-text" id="progress-text">Completion: 0%</div>
        <div class="progress-bar">
            <div class="progress" id="progress-bar"></div>
        </div>
    </div>

    <div class="container">
        <!-- Decorative corners -->
        <div class="decorative-corner corner-tl"></div>
        <div class="decorative-corner corner-tr"></div>
        <div class="decorative-corner corner-bl"></div>
        <div class="decorative-corner corner-br"></div>
        
        <header>
            <div class="institution-name">PHILTECH GMA</div>
            <div class="institution-name">PHILIPPINE TECHNOLOGICAL INSTITUTE OF SCIENCE ARTS AND TRADE CENTRAL INC.</div>
            <div class="address">2nd Floor CRDM BLDG. Governor's Drive Brgy G. Maderan GMA, Cavite</div>

            <h1><?php echo $is_view_mode ? 'View Evaluation' : 'TEACHER\'S PERFORMANCE EVALUATION BY THE STUDENTS'; ?></h1>

            <?php if ($teacher_info): ?>
            <div class="teacher-info">
                <h3>Evaluating: <?php echo safe_display($teacher_info['name']); ?></h3>
                <p style="margin: 5px 0;"><strong>Department:</strong> <?php echo safe_display($teacher_info['display_department']); ?></p>
                <p style="margin: 5px 0;"><strong>Student:</strong> <?php echo safe_display($_SESSION['full_name'] ?? ''); ?> (<?php echo safe_display($_SESSION['student_id'] ?? $_SESSION['username']); ?>)</p>
                <p style="margin: 5px 0;"><strong>Student Program:</strong> <?php echo safe_display($_SESSION['program'] ?? ''); ?></p>
                <p style="margin: 5px 0;"><strong>Student Section:</strong> <?php echo safe_display($_SESSION['section'] ?? ''); ?></p>
            </div>
            <?php endif; ?>

            <?php if (!$is_view_mode): ?>
            <div class="language-toggle">
                <button id="english-btn" class="active">English</button>
                <button id="tagalog-btn">Tagalog</button>
            </div>
            <?php endif; ?>
        </header>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <h3>✅ Success!</h3>
                <p><?php echo safe_display($success); ?></p>
                <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <h3>❌ Error</h3>
                <p><?php echo safe_display($error); ?></p>
                <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if ($is_view_mode && $existing_evaluation): ?>
            <div class="evaluation-status">
                <h3>✅ Evaluation Already Submitted</h3>
                <p>You have already evaluated this teacher on <?php echo date('F j, Y \a\t g:i A', strtotime($existing_evaluation['created_at'] ?? 'now')); ?>.</p>
                <?php if ($average_rating > 0): ?>
                <p>Average Rating: <strong><?php echo $average_rating; ?>/5.0</strong> (<?php echo $performance_level; ?>)</p>
                <?php endif; ?>
                <a href="student_dashboard.php" class="back-link">← Back to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if (!$is_view_mode && $teacher_info && empty($error)): ?>
        <div class="instructions english">
            <p>Instructions: The following items describe the aspects of teacher's behavior in and out the classroom. Please choose the number that indicates the degree to which you feel each item is descriptive of him/her. Your rating will be the reference that may lead to the improvement of instructor, so kindly rate each item as thoughtfully and carefully as possible. This will be kept confidentially.</p>
        </div>
        
        <div class="instructions tagalog">
            <p>Mga Panuto: Ang mga sumusunod na aytem ay naglalarawan sa mga aspeto ng pag-uugali ng guro sa loob at labas ng silid-aralan. Paki piliin ang numero na nagpapakita ng antas kung saan naramdaman mo ang bawat aytem na naglalarawan sa kanya. Ang inyong rating ay magiging sanggunian na maaaring humantong sa pagpapabuti ng tagapagturo, kaya mangyaring i-rate ang bawat aytem nang maingat at maayos. Ito ay itatago nang kumpidensyal.</p>
        </div>
        
        <div class="rating-scale">
            <div class="rating-item">5 - Outstanding</div>
            <div class="rating-item">4 - Very Satisfactory</div>
            <div class="rating-item">3 - Good/Satisfactory</div>
            <div class="rating-item">2 - Fair</div>
            <div class="rating-item">1 - Unsatisfactory</div>
        </div>

        <form id="evaluation-form" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            
            <!-- English Content -->
            <div class="english">
                <h2>1. Teaching Competence</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section1_questions as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" required> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <h2>2. Management Skills</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section2_questions as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" required> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <h2>3. Guidance Skills</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section3_questions as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" required> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <h2>4. Personal and Social Qualities/Skills</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Statement</th>
                            <th width="30%">Rating</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section4_questions as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>" value="<?php echo $i; ?>" required> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <div class="comments-section">
                    <h2>5. Comments</h2>
                    <h3>Positive Comments</h3>
                    <textarea name="q5-positive-en" placeholder="What are the positive aspects about this teacher's performance?" required></textarea>
                    
                    <h3>Negative Comments / Areas for Improvement</h3>
                    <textarea name="q5-negative-en" placeholder="What areas could this teacher improve on?" required></textarea>
                </div>
            </div>
            
            <!-- Tagalog Content -->
            <div class="tagalog">
                <h2>1. Kakayahan sa Pagtuturo</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section1_tagalog as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>_tl" value="<?php echo $i; ?>"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <h2>2. Kasanayan sa pamamahala</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section2_tagalog as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>_tl" value="<?php echo $i; ?>"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <h2>3. Mga kasanayan sa Paggabay</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section3_tagalog as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>_tl" value="<?php echo $i; ?>"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <h2>4. Personal at panlipunang katangian</h2>
                <table>
                    <thead>
                        <tr>
                            <th width="70%">Pahayag</th>
                            <th width="30%">Marka</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section4_tagalog as $key => $question): ?>
                        <tr>
                            <td><?php echo $key . ' ' . htmlspecialchars($question); ?></td>
                            <td>
                                <div class="rating-options">
                                    <?php 
                                    $name = "rating" . str_replace('.', '_', $key);
                                    for ($i = 5; $i >= 1; $i--): ?>
                                        <label><input type="radio" name="<?php echo $name; ?>_tl" value="<?php echo $i; ?>"> <?php echo $i; ?></label>
                                    <?php endfor; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <hr class="section-divider">
                
                <div class="comments-section">
                    <h2>5. Komento</h2>
                    <h3>Positibong Komento</h3>
                    <textarea name="q5-positive-tl" placeholder="Ano ang mga positibong aspeto tungkol sa pagganap ng guro na ito?"></textarea>
                    
                    <h3>Negatibong Komento / Mga Lugar na Pagbubutihin</h3>
                    <textarea name="q5-negative-tl" placeholder="Anong mga lugar ang maaaring pagbutihin ng guro na ito?"></textarea>
                </div>
            </div>
            
            <button type="submit" class="submit-btn" id="submit-btn">Submit Evaluation</button>
        </form>
        <?php endif; ?>

        <footer>
            <p>This evaluation will be kept confidential.</p>
            <p>© PHILTECH GMA</p>
        </footer>
    </div>

    <script>
        // Skeleton loading functionality
        document.addEventListener('DOMContentLoaded', () => {
            const skeletonLoader = document.getElementById('skeleton-loader');
            
            // Show skeleton for 3 seconds then fade out
            setTimeout(() => {
                skeletonLoader.classList.add('fade-out');
                
                // Remove skeleton from DOM after fade out completes
                setTimeout(() => {
                    skeletonLoader.style.display = 'none';
                }, 500); // Match the CSS transition duration
            }, 3000); // 3 seconds loading time

            // Rest of your existing JavaScript code...
            const englishBtn = document.getElementById('english-btn');
            const tagalogBtn = document.getElementById('tagalog-btn');
            const englishContent = document.querySelectorAll('.english');
            const tagalogContent = document.querySelectorAll('.tagalog');
            const form = document.getElementById('evaluation-form');
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            const submitBtn = document.getElementById('submit-btn');

            // Helper: sync radio selection and comments from English to Tagalog
            function syncEnglishToTagalog() {
                // Sync radio buttons
                const englishRadios = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
                englishRadios.forEach(radio => {
                    if (radio.checked) {
                        const tagalogName = radio.name + '_tl';
                        const tagalogRadio = form.querySelector(`input[name="${tagalogName}"][value="${radio.value}"]`);
                        if (tagalogRadio) tagalogRadio.checked = true;
                    }
                });
                
                // Sync comments
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                
                if (positiveEn && positiveTl) positiveTl.value = positiveEn.value;
                if (negativeEn && negativeTl) negativeTl.value = negativeEn.value;
            }

            // Helper: sync radio selection and comments from Tagalog to English
            function syncTagalogToEnglish() {
                // Sync radio buttons
                const tagalogRadios = form.querySelectorAll('input[type="radio"][name*="_tl"]');
                tagalogRadios.forEach(radio => {
                    if (radio.checked) {
                        const englishName = radio.name.replace('_tl', '');
                        const englishRadio = form.querySelector(`input[name="${englishName}"][value="${radio.value}"]`);
                        if (englishRadio) englishRadio.checked = true;
                    }
                });
                
                // Sync comments
                const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                
                if (positiveTl && positiveEn) positiveEn.value = positiveTl.value;
                if (negativeTl && negativeEn) negativeEn.value = negativeTl.value;
            }

            // Language toggle
            if (englishBtn) {
                englishBtn.addEventListener('click', () => {
                    syncTagalogToEnglish();
                    englishContent.forEach(el => el.style.display = 'block');
                    tagalogContent.forEach(el => el.style.display = 'none');
                    englishBtn.classList.add('active');
                    tagalogBtn.classList.remove('active');
                    updateProgress();
                });
            }

            if (tagalogBtn) {
                tagalogBtn.addEventListener('click', () => {
                    syncEnglishToTagalog();
                    englishContent.forEach(el => el.style.display = 'none');
                    tagalogContent.forEach(el => el.style.display = 'block');
                    tagalogBtn.classList.add('active');
                    englishBtn.classList.remove('active');
                    updateProgress();
                });
            }

            // Initialize display
            if (englishContent.length > 0) {
                englishContent.forEach(el => el.style.display = 'block');
            }
            if (tagalogContent.length > 0) {
                tagalogContent.forEach(el => el.style.display = 'none');
            }

            // Update progress bar - FIXED VERSION
            function updateProgress() {
                if (!form) return;
                
                let totalFields = 0;
                let completedFields = 0;

                // Check which language is active
                const isEnglishActive = englishBtn && englishBtn.classList.contains('active');
                
                if (isEnglishActive) {
                    // Check English radio buttons
                    const englishRadios = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
                    const englishRadioGroups = new Set();
                    englishRadios.forEach(radio => englishRadioGroups.add(radio.name));
                    
                    totalFields += englishRadioGroups.size;
                    englishRadioGroups.forEach(name => {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (checked) completedFields++;
                    });

                    // Check English comments
                    const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                    const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                    
                    totalFields += 2;
                    if (positiveEn && positiveEn.value.trim()) completedFields++;
                    if (negativeEn && negativeEn.value.trim()) completedFields++;
                } else {
                    // Check Tagalog radio buttons
                    const tagalogRadios = form.querySelectorAll('input[type="radio"][name*="_tl"]');
                    const tagalogRadioGroups = new Set();
                    tagalogRadios.forEach(radio => tagalogRadioGroups.add(radio.name));
                    
                    totalFields += tagalogRadioGroups.size;
                    tagalogRadioGroups.forEach(name => {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (checked) completedFields++;
                    });

                    // Check Tagalog comments
                    const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
                    const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
                    
                    totalFields += 2;
                    if (positiveTl && positiveTl.value.trim()) completedFields++;
                    if (negativeTl && negativeTl.value.trim()) completedFields++;
                }

                const progress = totalFields > 0 ? Math.round((completedFields / totalFields) * 100) : 0;
                
                if (progressBar) progressBar.style.width = progress + '%';
                if (progressText) progressText.textContent = `Completion: ${progress}%`;
                if (submitBtn) submitBtn.disabled = progress < 100;
            }

            // Real-time sync for Tagalog radio buttons to English ones
            function setupRealTimeSync() {
                if (!form) return;

                // When Tagalog radio is clicked, sync to English
                form.addEventListener('change', (e) => {
                    if (e.target.type === 'radio' && e.target.name.includes('_tl')) {
                        const englishName = e.target.name.replace('_tl', '');
                        const englishRadio = form.querySelector(`input[name="${englishName}"][value="${e.target.value}"]`);
                        if (englishRadio) {
                            englishRadio.checked = true;
                        }
                        updateProgress();
                    }
                });

                // When Tagalog textarea is typed, sync to English
                form.addEventListener('input', (e) => {
                    if (e.target.name === 'q5-positive-tl') {
                        const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                        if (positiveEn) positiveEn.value = e.target.value;
                        updateProgress();
                    } else if (e.target.name === 'q5-negative-tl') {
                        const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                        if (negativeEn) negativeEn.value = e.target.value;
                        updateProgress();
                    }
                });

                // When English textarea is typed, update progress
                form.addEventListener('input', (e) => {
                    if (e.target.name === 'q5-positive-en' || e.target.name === 'q5-negative-en') {
                        updateProgress();
                    }
                });
            }

            // Listen to changes in both languages
            if (form) {
                setupRealTimeSync();
                
                form.addEventListener('change', updateProgress);
                form.addEventListener('input', updateProgress);

                // Form submit validation
                form.addEventListener('submit', (e) => {
                    let valid = true;
                    const errors = [];

                    // Check all radio groups (English version for submission)
                    const radioNames = new Set();
                    const radioInputs = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
                    radioInputs.forEach(input => radioNames.add(input.name));

                    radioNames.forEach(name => {
                        const checked = form.querySelector(`input[name="${name}"]:checked`);
                        if (!checked) {
                            valid = false;
                            errors.push(`Please provide a rating for question ${name}`);
                        }
                    });

                    // Check comments (English version for submission)
                    const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                    const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                    
                    if (!positiveEn || !positiveEn.value.trim()) {
                        valid = false;
                        errors.push('Please provide positive comments');
                        if (positiveEn) positiveEn.style.border = '2px solid red';
                    } else if (positiveEn) {
                        positiveEn.style.border = '';
                    }
                    
                    if (!negativeEn || !negativeEn.value.trim()) {
                        valid = false;
                        errors.push('Please provide areas for improvement');
                        if (negativeEn) negativeEn.style.border = '2px solid red';
                    } else if (negativeEn) {
                        negativeEn.style.border = '';
                    }

                    if (!valid) {
                        e.preventDefault();
                        alert('Please complete all required fields:\n\n' + errors.join('\n'));
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.textContent = 'Submitting...';
                    submitBtn.disabled = true;
                });
            }

            // Initial progress update
            setTimeout(updateProgress, 100);
        });

        // Set progress to 100% in view mode
        const isViewMode = <?php echo $is_view_mode ? 'true' : 'false'; ?>;
        if (isViewMode) {
            const progressBar = document.getElementById('progress-bar');
            const progressText = document.getElementById('progress-text');
            if (progressBar) progressBar.style.width = '100%';
            if (progressText) progressText.textContent = 'Completion: 100%';
        }
    </script>
</body>
</html>
