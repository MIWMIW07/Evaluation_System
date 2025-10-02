<?php
// COMPLETE FORM SUBMISSION SECTION - Replace lines 70-130 in evaluation_form.php

// Handle form submission (only if not in view mode)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$is_view_mode && $teacher_info) {
    try {
        // Validate all required fields
        $ratings = [];

        // Section 1: Teaching Competence (6 questions)
        for ($i = 1; $i <= 6; $i++) {
            $rating = intval($_POST["rating1_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 1.$i");
            }
            $ratings["q1_$i"] = $rating;
        }

        // Section 2: Management Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["rating2_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 2.$i");
            }
            $ratings["q2_$i"] = $rating;
        }

        // Section 3: Guidance Skills (4 questions)
        for ($i = 1; $i <= 4; $i++) {
            $rating = intval($_POST["rating3_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 3.$i");
            }
            $ratings["q3_$i"] = $rating;
        }

        // Section 4: Personal and Social Characteristics (6 questions)
        for ($i = 1; $i <= 6; $i++) {
            $rating = intval($_POST["rating4_$i"] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new Exception("Invalid rating for question 4.$i");
            }
            $ratings["q4_$i"] = $rating;
        }

        // Get comments with validation (ONLY runs during POST submission)
        $positive = trim($_POST['q5-positive-en'] ?? $_POST['q5-positive-tl'] ?? '');
        $negative = trim($_POST['q5-negative-en'] ?? $_POST['q5-negative-tl'] ?? '');

        // Validate comment length (minimum 20 characters)
        if (strlen($positive) < 20) {
            throw new Exception("Positive comments must be at least 20 characters long. Current: " . strlen($positive) . " characters.");
        }
        if (strlen($negative) < 20) {
            throw new Exception("Negative comments / areas for improvement must be at least 20 characters long. Current: " . strlen($negative) . " characters.");
        }

        $comments = "Positive: $positive\nNegative: $negative";

        // Insert evaluation using the updated table structure
        $insert_sql = "INSERT INTO evaluations (
    student_username, student_name, teacher_name, section, program,
    q1_1, q1_2, q1_3, q1_4, q1_5, q1_6,
    q2_1, q2_2, q2_3, q2_4,
    q3_1, q3_2, q3_3, q3_4,
    q4_1, q4_2, q4_3, q4_4, q4_5, q4_6,
    comments
) VALUES (
    ?, ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?, ?, ?,
    ?, ?, ?, ?, ?, ?,
    ?
)";

        $params = [
            $_SESSION['username'],
            $_SESSION['full_name'] ?? '',
            $teacher_name,
            $_SESSION['section'] ?? '',
            $_SESSION['program'] ?? '',
            $ratings['q1_1'], $ratings['q1_2'], $ratings['q1_3'], $ratings['q1_4'], $ratings['q1_5'], $ratings['q1_6'],
            $ratings['q2_1'], $ratings['q2_2'], $ratings['q2_3'], $ratings['q2_4'],
            $ratings['q3_1'], $ratings['q3_2'], $ratings['q3_3'], $ratings['q3_4'],
            $ratings['q4_1'], $ratings['q4_2'], $ratings['q4_3'], $ratings['q4_4'], $ratings['q4_5'], $ratings['q4_6'],
            $comments
        ];

        $stmt = $pdo->prepare($insert_sql);
        $result = $stmt->execute($params);

        if ($result) {
            $success = "Evaluation submitted successfully! Thank you for your feedback.";
            // Reload to show in view mode
            $check_stmt = $pdo->prepare("
                SELECT * FROM evaluations 
                WHERE student_username = ? AND teacher_name = ? AND section = ?
            ");
            $check_stmt->execute([$student_username, $teacher_name, $student_section]);
            $existing_evaluation = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $is_view_mode = true;
        } else {
            throw new Exception("Database error occurred while saving your evaluation.");
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!-- UPDATE TEXTAREA SECTIONS - Replace around line 580-660 in evaluation_form.php -->

<!-- ENGLISH VERSION - Inside <div class="english"> -->
<div class="comments-section">
    <h2>5. Comments</h2>
    <h3>Positive Comments</h3>
    <textarea name="q5-positive-en" 
              placeholder="What are the positive aspects about this teacher's performance? (Minimum 20 characters)" 
              minlength="20"
              required></textarea>
    <small style="color: #800000; display: block; margin-top: 5px;">Minimum 20 characters required</small>
    
    <h3>Negative Comments / Areas for Improvement</h3>
    <textarea name="q5-negative-en" 
              placeholder="What areas could this teacher improve on? (Minimum 20 characters)" 
              minlength="20"
              required></textarea>
    <small style="color: #800000; display: block; margin-top: 5px;">Minimum 20 characters required</small>
</div>

<!-- TAGALOG VERSION - Inside <div class="tagalog"> -->
<div class="comments-section">
    <h2>5. Komento</h2>
    <h3>Positibong Komento</h3>
    <textarea name="q5-positive-tl" 
              placeholder="Ano ang mga positibong aspeto tungkol sa pagganap ng guro na ito? (Minimum 20 characters)"
              minlength="20"></textarea>
    <small style="color: #800000; display: block; margin-top: 5px;">Minimum 20 characters na kailangan</small>
    
    <h3>Negatibong Komento / Mga Lugar na Pagbubutihin</h3>
    <textarea name="q5-negative-tl" 
              placeholder="Anong mga lugar ang maaaring pagbutihin ng guro na ito? (Minimum 20 characters)"
              minlength="20"></textarea>
    <small style="color: #800000; display: block; margin-top: 5px;">Minimum 20 characters na kailangan</small>
</div>

<!-- ADD THIS CSS - Add to existing <style> section -->
<style>
/* Character counter and validation styles */
textarea.invalid {
    border: 2px solid #dc3545 !important;
    background-color: #fff5f5;
}

textarea.valid {
    border: 2px solid #28a745 !important;
}

.char-counter {
    font-size: 0.9em;
    margin-top: 5px;
    font-weight: 600;
    display: block;
}

.char-counter.incomplete {
    color: #dc3545;
}

.char-counter.complete {
    color: #28a745;
}
</style>

<!-- UPDATE JAVASCRIPT - Replace form validation section around line 750 -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const skeletonLoader = document.getElementById('skeleton-loader');
    
    // Show skeleton for 3 seconds then fade out
    setTimeout(() => {
        skeletonLoader.classList.add('fade-out');
        
        setTimeout(() => {
            skeletonLoader.style.display = 'none';
        }, 500);
    }, 3000);

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
        const englishRadios = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
        englishRadios.forEach(radio => {
            if (radio.checked) {
                const tagalogName = radio.name + '_tl';
                const tagalogRadio = form.querySelector(`input[name="${tagalogName}"][value="${radio.value}"]`);
                if (tagalogRadio) tagalogRadio.checked = true;
            }
        });
        
        const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
        const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
        const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
        const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
        
        if (positiveEn && positiveTl) positiveTl.value = positiveEn.value;
        if (negativeEn && negativeTl) negativeTl.value = negativeEn.value;
    }

    // Helper: sync radio selection and comments from Tagalog to English
    function syncTagalogToEnglish() {
        const tagalogRadios = form.querySelectorAll('input[type="radio"][name*="_tl"]');
        tagalogRadios.forEach(radio => {
            if (radio.checked) {
                const englishName = radio.name.replace('_tl', '');
                const englishRadio = form.querySelector(`input[name="${englishName}"][value="${radio.value}"]`);
                if (englishRadio) englishRadio.checked = true;
            }
        });
        
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

    // Update progress bar
    function updateProgress() {
        if (!form) return;
        
        let totalFields = 0;
        let completedFields = 0;

        const isEnglishActive = englishBtn && englishBtn.classList.contains('active');
        
        if (isEnglishActive) {
            const englishRadios = form.querySelectorAll('input[type="radio"]:not([name*="_tl"])');
            const englishRadioGroups = new Set();
            englishRadios.forEach(radio => englishRadioGroups.add(radio.name));
            
            totalFields += englishRadioGroups.size;
            englishRadioGroups.forEach(name => {
                const checked = form.querySelector(`input[name="${name}"]:checked`);
                if (checked) completedFields++;
            });

            const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
            const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
            
            totalFields += 2;
            if (positiveEn && positiveEn.value.trim().length >= 20) completedFields++;
            if (negativeEn && negativeEn.value.trim().length >= 20) completedFields++;
        } else {
            const tagalogRadios = form.querySelectorAll('input[type="radio"][name*="_tl"]');
            const tagalogRadioGroups = new Set();
            tagalogRadios.forEach(radio => tagalogRadioGroups.add(radio.name));
            
            totalFields += tagalogRadioGroups.size;
            tagalogRadioGroups.forEach(name => {
                const checked = form.querySelector(`input[name="${name}"]:checked`);
                if (checked) completedFields++;
            });

            const positiveTl = form.querySelector('textarea[name="q5-positive-tl"]');
            const negativeTl = form.querySelector('textarea[name="q5-negative-tl"]');
            
            totalFields += 2;
            if (positiveTl && positiveTl.value.trim().length >= 20) completedFields++;
            if (negativeTl && negativeTl.value.trim().length >= 20) completedFields++;
        }

        const progress = totalFields > 0 ? Math.round((completedFields / totalFields) * 100) : 0;
        
        if (progressBar) progressBar.style.width = progress + '%';
        if (progressText) progressText.textContent = `Completion: ${progress}%`;
        if (submitBtn) submitBtn.disabled = progress < 100;
    }

    // Real-time character counter
    function addCharacterCounter() {
        const textareas = form.querySelectorAll('textarea');
        
        textareas.forEach(textarea => {
            const existingCounter = textarea.nextElementSibling;
            if (existingCounter && existingCounter.classList.contains('char-counter')) {
                return; // Counter already exists
            }
            
            const counter = document.createElement('small');
            counter.className = 'char-counter';
            counter.style.color = '#800000';
            counter.style.display = 'block';
            counter.style.marginTop = '5px';
            counter.style.fontWeight = 'bold';
            
            textarea.parentNode.insertBefore(counter, textarea.nextSibling.nextSibling);
            
            textarea.addEventListener('input', () => {
                const length = textarea.value.trim().length;
                const remaining = 20 - length;
                
                if (length < 20) {
                    counter.textContent = `${remaining} more characters needed (${length}/20 minimum)`;
                    counter.className = 'char-counter incomplete';
                    textarea.style.border = '2px solid #dc3545';
                } else {
                    counter.textContent = `✓ ${length} characters`;
                    counter.className = 'char-counter complete';
                    textarea.style.border = '2px solid #28a745';
                }
                
                updateProgress();
            });
            
            textarea.dispatchEvent(new Event('input'));
        });
    }

    // Real-time sync for Tagalog to English
    function setupRealTimeSync() {
        if (!form) return;

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

        form.addEventListener('input', (e) => {
            if (e.target.name === 'q5-positive-tl') {
                const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
                if (positiveEn) positiveEn.value = e.target.value;
                updateProgress();
            } else if (e.target.name === 'q5-negative-tl') {
                const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
                if (negativeEn) negativeEn.value = e.target.value;
                updateProgress();
            } else if (e.target.name === 'q5-positive-en' || e.target.name === 'q5-negative-en') {
                updateProgress();
            }
        });
    }

    // Form submit validation with 20-character check
    if (form) {
        setupRealTimeSync();
        addCharacterCounter();
        
        form.addEventListener('change', updateProgress);
        form.addEventListener('input', updateProgress);

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

            // Enhanced comment validation with character count
            const positiveEn = form.querySelector('textarea[name="q5-positive-en"]');
            const negativeEn = form.querySelector('textarea[name="q5-negative-en"]');
            
            if (!positiveEn || !positiveEn.value.trim()) {
                valid = false;
                errors.push('Please provide positive comments');
                if (positiveEn) positiveEn.style.border = '2px solid red';
            } else if (positiveEn.value.trim().length < 20) {
                valid = false;
                errors.push(`Positive comments must be at least 20 characters (currently ${positiveEn.value.trim().length} characters)`);
                positiveEn.style.border = '2px solid red';
            } else if (positiveEn) {
                positiveEn.style.border = '';
            }
            
            if (!negativeEn || !negativeEn.value.trim()) {
                valid = false;
                errors.push('Please provide areas for improvement');
                if (negativeEn) negativeEn.style.border = '2px solid red';
            } else if (negativeEn.value.trim().length < 20) {
                valid = false;
                errors.push(`Negative comments must be at least 20 characters (currently ${negativeEn.value.trim().length} characters)`);
                negativeEn.style.border = '2px solid red';
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
