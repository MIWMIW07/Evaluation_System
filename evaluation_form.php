<?php
// Add this validation in the form submission section (around line 88)
// Replace the existing comments validation with this:

// Get comments with validation
$positive = trim($_POST['q5-positive-en'] ?? $_POST['q5-positive-tl'] ?? '');
$negative = trim($_POST['q5-negative-en'] ?? $_POST['q5-negative-tl'] ?? '');

// Validate comment length (minimum 20 characters)
if (strlen($positive) < 20) {
    throw new Exception("Positive comments must be at least 20 characters long.");
}
if (strlen($negative) < 20) {
    throw new Exception("Negative comments / areas for improvement must be at least 20 characters long.");
}

$comments = "Positive: $positive\nNegative: $negative";
?>

<!-- Update the textarea sections (around line 580 and 660) -->
<!-- For English version: -->
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

<!-- For Tagalog version: -->
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

<script>
// Add this to your existing JavaScript (around line 750)
// Update the form submit validation section with enhanced comment validation

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

// Add real-time character counter
function addCharacterCounter() {
    const textareas = form.querySelectorAll('textarea');
    
    textareas.forEach(textarea => {
        // Create character counter element
        const counter = document.createElement('small');
        counter.style.color = '#800000';
        counter.style.display = 'block';
        counter.style.marginTop = '5px';
        counter.style.fontWeight = 'bold';
        
        // Insert counter after textarea
        textarea.parentNode.insertBefore(counter, textarea.nextSibling);
        
        // Update counter on input
        textarea.addEventListener('input', () => {
            const length = textarea.value.trim().length;
            const remaining = 20 - length;
            
            if (length < 20) {
                counter.textContent = `${remaining} more characters needed (${length}/20 minimum)`;
                counter.style.color = '#dc3545';
                textarea.style.border = '2px solid #dc3545';
            } else {
                counter.textContent = `✓ ${length} characters`;
                counter.style.color = '#28a745';
                textarea.style.border = '1px solid #28a745';
            }
            
            updateProgress();
        });
        
        // Initialize counter
        textarea.dispatchEvent(new Event('input'));
    });
}

// Call this after DOM is loaded
if (form && !isViewMode) {
    addCharacterCounter();
}
</script>

<style>
/* Add this to your existing styles */
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
}

.char-counter.incomplete {
    color: #dc3545;
}

.char-counter.complete {
    color: #28a745;
}
</style>
