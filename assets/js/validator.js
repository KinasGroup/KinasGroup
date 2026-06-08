// KINAS GROUP - Form Validation Library
class Validator {
    constructor(formElement) {
        this.form = formElement;
        this.rules = {};
        this.errors = {};
        this.init();
    }
    
    init() {
        this.setupRules();
        this.bindRealTimeValidation();
        this.bindSubmit();
    }
    
    setupRules() {
        // Required fields
        this.form.querySelectorAll('[required]').forEach(field => {
            this.addRule(field.name, 'required', true);
        });
        
        // Email fields
        this.form.querySelectorAll('input[type="email"]').forEach(field => {
            this.addRule(field.name, 'email', true);
        });
        
        // Phone fields
        this.form.querySelectorAll('input[type="tel"]').forEach(field => {
            this.addRule(field.name, 'phone', true);
        });
        
        // Password fields
        this.form.querySelectorAll('input[type="password"][data-min-length]').forEach(field => {
            this.addRule(field.name, 'minLength', field.dataset.minLength);
        });
        
        // File uploads
        this.form.querySelectorAll('input[type="file"]').forEach(field => {
            if (field.dataset.maxSize) {
                this.addRule(field.name, 'fileSize', field.dataset.maxSize);
            }
            if (field.dataset.allowedTypes) {
                this.addRule(field.name, 'fileType', field.dataset.allowedTypes.split(','));
            }
        });
        
        // Number range
        this.form.querySelectorAll('input[type="number"][min]').forEach(field => {
            this.addRule(field.name, 'min', field.min);
        });
        this.form.querySelectorAll('input[type="number"][max]').forEach(field => {
            this.addRule(field.name, 'max', field.max);
        });
    }
    
    addRule(fieldName, rule, value) {
        if (!this.rules[fieldName]) {
            this.rules[fieldName] = [];
        }
        this.rules[fieldName].push({ rule, value });
    }
    
    bindRealTimeValidation() {
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            field.addEventListener('blur', () => {
                this.validateField(field);
            });
            
            field.addEventListener('input', () => {
                if (field.classList.contains('error')) {
                    this.validateField(field);
                }
            });
        });
    }
    
    bindSubmit() {
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (this.validateAll()) {
                this.form.submit();
            } else {
                this.showErrors();
            }
        });
    }
    
    validateField(field) {
        const fieldRules = this.rules[field.name] || [];
        const value = field.value;
        let isValid = true;
        let errorMessage = '';
        
        for (const { rule, value: ruleValue } of fieldRules) {
            switch (rule) {
                case 'required':
                    if (!value.trim()) {
                        isValid = false;
                        errorMessage = 'This field is required';
                    }
                    break;
                    
                case 'email':
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (value && !emailRegex.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid email address';
                    }
                    break;
                    
                case 'phone':
                    const phoneRegex = /^\+?[\d\s\-\(\)]{7,15}$/;
                    if (value && !phoneRegex.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid phone number';
                    }
                    break;
                    
                case 'minLength':
                    if (value.length < ruleValue) {
                        isValid = false;
                        errorMessage = `Must be at least ${ruleValue} characters`;
                    }
                    break;
                    
                case 'min':
                    if (parseFloat(value) < parseFloat(ruleValue)) {
                        isValid = false;
                        errorMessage = `Minimum value is ${ruleValue}`;
                    }
                    break;
                    
                case 'max':
                    if (parseFloat(value) > parseFloat(ruleValue)) {
                        isValid = false;
                        errorMessage = `Maximum value is ${ruleValue}`;
                    }
                    break;
                    
                case 'fileSize':
                    if (field.files[0]) {
                        const maxBytes = ruleValue * 1024 * 1024; // Convert MB to bytes
                        if (field.files[0].size > maxBytes) {
                            isValid = false;
                            errorMessage = `File must be less than ${ruleValue}MB`;
                        }
                    }
                    break;
                    
                case 'fileType':
                    if (field.files[0]) {
                        const allowedTypes = ruleValue;
                        const fileType = field.files[0].type;
                        if (!allowedTypes.some(type => fileType.match(type))) {
                            isValid = false;
                            errorMessage = `File type not allowed. Accepted: ${ruleValue.join(', ')}`;
                        }
                    }
                    break;
            }
            
            if (!isValid) break;
        }
        
        // Update field state
        if (isValid) {
            field.classList.remove('error');
            field.classList.add('valid');
            this.removeFieldError(field.name);
        } else {
            field.classList.remove('valid');
            field.classList.add('error');
            this.errors[field.name] = errorMessage;
        }
        
        this.updateFieldErrorDisplay(field, errorMessage);
        return isValid;
    }
    
    validateAll() {
        let allValid = true;
        this.errors = {};
        
        this.form.querySelectorAll('input, select, textarea').forEach(field => {
            if (this.rules[field.name]) {
                if (!this.validateField(field)) {
                    allValid = false;
                }
            }
        });
        
        return allValid;
    }
    
    updateFieldErrorDisplay(field, message) {
        let errorDiv = field.parentElement.querySelector('.field-error');
        
        if (message) {
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                field.parentElement.appendChild(errorDiv);
            }
            errorDiv.textContent = message;
        } else {
            if (errorDiv) {
                errorDiv.remove();
            }
        }
    }
    
    removeFieldError(fieldName) {
        delete this.errors[fieldName];
    }
    
    showErrors() {
        // Scroll to first error
        const firstError = this.form.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstError.focus();
        }
        
        // Show summary at top
        const errorCount = Object.keys(this.errors).length;
        let summaryDiv = this.form.querySelector('.error-summary');
        
        if (errorCount > 0) {
            if (!summaryDiv) {
                summaryDiv = document.createElement('div');
                summaryDiv.className = 'alert alert-danger error-summary';
                this.form.insertBefore(summaryDiv, this.form.firstChild);
            }
            
            summaryDiv.innerHTML = `
                <strong>Please fix the following errors (${errorCount}):</strong>
                <ul>
                    ${Object.entries(this.errors).map(([field, message]) => 
                        `<li><a href="#" onclick="document.getElementsByName('${field}')[0].focus(); return false;">${message}</a></li>`
                    ).join('')}
                </ul>
            `;
        } else if (summaryDiv) {
            summaryDiv.remove();
        }
    }
}

// Initialize validation on forms
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-validate]').forEach(form => {
        new Validator(form);
    });
});