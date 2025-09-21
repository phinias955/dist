// Validation functions for NIDA and Phone numbers
function validateNidaNumber(nidaNumber) {
    // Remove any spaces or dashes
    const cleaned = nidaNumber.replace(/[\s\-]/g, '');
    
    // Check if it's exactly 20 digits
    if (/^\d{20}$/.test(cleaned)) {
        return {
            valid: true,
            cleaned: cleaned,
            error: null
        };
    } else {
        return {
            valid: false,
            cleaned: cleaned,
            error: 'NIDA number must be exactly 20 digits'
        };
    }
}

function validatePhoneNumber(phoneNumber) {
    // Remove any spaces, dashes, or plus signs
    const cleaned = phoneNumber.replace(/[\s\-\+]/g, '');
    
    // Check if it's exactly 10 digits
    if (/^\d{10}$/.test(cleaned)) {
        return {
            valid: true,
            cleaned: cleaned,
            error: null
        };
    } else {
        return {
            valid: false,
            cleaned: cleaned,
            error: 'Phone number must be exactly 10 digits'
        };
    }
}

// Real-time validation for input fields
function setupValidation() {
    // NIDA number validation
    document.querySelectorAll('input[name="nida_number"]').forEach(input => {
        input.addEventListener('input', function() {
            validateNidaInput(this);
        });
        
        input.addEventListener('blur', function() {
            validateNidaInput(this);
        });
    });
    
    // Phone number validation
    document.querySelectorAll('input[name="phone"], input[type="tel"]').forEach(input => {
        input.addEventListener('input', function() {
            validatePhoneInput(this);
        });
        
        input.addEventListener('blur', function() {
            validatePhoneInput(this);
        });
    });
}

function validateNidaInput(input) {
    const validation = validateNidaNumber(input.value);
    const errorElement = getOrCreateErrorElement(input, 'nida-error');
    
    if (validation.valid) {
        input.classList.remove('border-red-500', 'border-orange-500');
        input.classList.add('border-green-500');
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    } else {
        input.classList.remove('border-green-500', 'border-orange-500');
        input.classList.add('border-red-500');
        errorElement.textContent = validation.error;
        errorElement.style.display = 'block';
    }
    
    // Update input value with cleaned version
    if (validation.cleaned !== input.value) {
        input.value = validation.cleaned;
    }
}

function validatePhoneInput(input) {
    const validation = validatePhoneNumber(input.value);
    const errorElement = getOrCreateErrorElement(input, 'phone-error');
    
    if (validation.valid) {
        input.classList.remove('border-red-500', 'border-orange-500');
        input.classList.add('border-green-500');
        errorElement.textContent = '';
        errorElement.style.display = 'none';
    } else {
        input.classList.remove('border-green-500', 'border-orange-500');
        input.classList.add('border-red-500');
        errorElement.textContent = validation.error;
        errorElement.style.display = 'block';
    }
    
    // Update input value with cleaned version
    if (validation.cleaned !== input.value) {
        input.value = validation.cleaned;
    }
}

function getOrCreateErrorElement(input, errorClass) {
    let errorElement = input.parentNode.querySelector('.' + errorClass);
    
    if (!errorElement) {
        errorElement = document.createElement('div');
        errorElement.className = errorClass + ' text-red-500 text-sm mt-1';
        errorElement.style.display = 'none';
        input.parentNode.appendChild(errorElement);
    }
    
    return errorElement;
}

// Form submission validation
function validateForm(form) {
    let isValid = true;
    const errors = [];
    
    // Validate NIDA numbers
    form.querySelectorAll('input[name="nida_number"]').forEach(input => {
        if (input.value.trim()) {
            const validation = validateNidaNumber(input.value);
            if (!validation.valid) {
                isValid = false;
                errors.push(validation.error);
                validateNidaInput(input);
            }
        }
    });
    
    // Validate phone numbers
    form.querySelectorAll('input[name="phone"], input[type="tel"]').forEach(input => {
        if (input.value.trim()) {
            const validation = validatePhoneNumber(input.value);
            if (!validation.valid) {
                isValid = false;
                errors.push(validation.error);
                validatePhoneInput(input);
            }
        }
    });
    
    if (!isValid) {
        alert('Please fix the following errors:\n' + errors.join('\n'));
    }
    
    return isValid;
}

// Auto-format inputs as user types
function setupAutoFormat() {
    // NIDA number formatting
    document.querySelectorAll('input[name="nida_number"]').forEach(input => {
        input.addEventListener('input', function() {
            // Remove non-digits
            let value = this.value.replace(/\D/g, '');
            
            // Limit to 20 digits
            if (value.length > 20) {
                value = value.substring(0, 20);
            }
            
            this.value = value;
        });
    });
    
    // Phone number formatting
    document.querySelectorAll('input[name="phone"], input[type="tel"]').forEach(input => {
        input.addEventListener('input', function() {
            // Remove non-digits
            let value = this.value.replace(/\D/g, '');
            
            // Limit to 10 digits
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            
            this.value = value;
        });
    });
}

// Initialize validation when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupValidation();
    setupAutoFormat();
    
    // Add validation to all forms
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
            }
        });
    });
});
