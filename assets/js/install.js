// VidPowr Installer JavaScript

class VidpowrInstaller {
    constructor() {
        this.currentStep = this.getCurrentStep();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.initializeStep();
        this.setupFormValidation();
        this.setupProgressTracking();
    }

    getCurrentStep() {
        const urlParams = new URLSearchParams(window.location.search);
        return parseInt(urlParams.get('step')) || 1;
    }

    setupEventListeners() {
        // Step navigation
        document.querySelectorAll('[data-step-nav]').forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                this.navigateToStep(parseInt(button.dataset.stepNav));
            });
        });

        // Form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                this.handleFormSubmit(form, e);
            });
        });

        // License key formatting
        const licenseInput = document.getElementById('license_key');
        if (licenseInput) {
            licenseInput.addEventListener('input', this.formatLicenseKey.bind(this));
            licenseInput.addEventListener('paste', this.handleLicensePaste.bind(this));
        }

        // Database setup toggles
        const createDbCheckbox = document.getElementById('create_database');
        const createUserCheckbox = document.getElementById('create_user');
        
        if (createDbCheckbox) {
            createDbCheckbox.addEventListener('change', this.toggleDatabaseFields.bind(this));
        }
        
        if (createUserCheckbox) {
            createUserCheckbox.addEventListener('change', this.toggleUserFields.bind(this));
        }

        // Password generation
        const generateBtn = document.querySelector('[data-action="generate-password"]');
        if (generateBtn) {
            generateBtn.addEventListener('click', this.generatePassword.bind(this));
        }

        // Connection testing
        const testBtn = document.querySelector('[data-action="test-connection"]');
        if (testBtn) {
            testBtn.addEventListener('click', this.testDatabaseConnection.bind(this));
        }
    }

    initializeStep() {
        // Step-specific initialization
        switch (this.currentStep) {
            case 1:
                this.initializeSystemCheck();
                break;
            case 2:
                this.initializeLicenseValidation();
                break;
            case 3:
                this.initializeDatabaseSetup();
                break;
            case 4:
                this.initializeInstallation();
                break;
        }
    }

    setupFormValidation() {
        // Add real-time validation
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('blur', () => {
                this.validateField(input);
            });

            input.addEventListener('input', () => {
                if (input.classList.contains('is-invalid')) {
                    this.validateField(input);
                }
            });
        });
    }

    setupProgressTracking() {
        // Track installation progress
        this.progressInterval = null;
        this.installationSteps = [
            'Creating environment file',
            'Installing dependencies',
            'Generating application key',
            'Running database migrations',
            'Seeding database',
            'Creating admin user',
            'Setting file permissions',
            'Creating storage links',
            'Optimizing application',
            'Finalizing installation'
        ];
    }

    // System Check Methods
    initializeSystemCheck() {
        this.checkSystemRequirements();
    }

    async checkSystemRequirements() {
        try {
            const response = await fetch('install.php?step=1&check=1', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (response.ok) {
                const data = await response.json();
                this.updateRequirementStatus(data.requirements);
            }
        } catch (error) {
            console.error('System check failed:', error);
        }
    }

    updateRequirementStatus(requirements) {
        Object.keys(requirements).forEach(key => {
            const element = document.querySelector(`[data-requirement="${key}"]`);
            if (element) {
                const requirement = requirements[key];
                const statusClass = requirement.result.status ? 'passed' : 
                                  (requirement.result.optional ? 'optional' : 'failed');
                
                element.className = `requirement-item ${statusClass}`;
                
                const icon = element.querySelector('.requirement-icon');
                if (icon) {
                    icon.textContent = requirement.result.status ? '✅' : 
                                     (requirement.result.optional ? '⚠️' : '❌');
                }
            }
        });
    }

    // License Validation Methods
    initializeLicenseValidation() {
        const storedLicense = document.querySelector('[data-stored-license]');
        if (storedLicense) {
            this.showStoredLicenseInfo(storedLicense.dataset);
        }
    }

    formatLicenseKey(e) {
        let value = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        
        // Add dashes at correct positions
        if (value.length > 7) value = value.slice(0, 7) + '-' + value.slice(7);
        if (value.length > 13) value = value.slice(0, 13) + '-' + value.slice(13);
        if (value.length > 19) value = value.slice(0, 19) + '-' + value.slice(19);
        
        e.target.value = value;
    }

    handleLicensePaste(e) {
        e.preventDefault();
        const pastedData = (e.clipboardData || window.clipboardData).getData('text');
        const formatted = pastedData.toUpperCase().replace(/[^A-Z0-9]/g, '');
        e.target.value = formatted;
        this.formatLicenseKey({ target: e.target });
    }

    // Database Setup Methods
    initializeDatabaseSetup() {
        this.toggleDatabaseFields();
        this.toggleUserFields();
    }

    toggleDatabaseFields() {
        const createDb = document.getElementById('create_database');
        const dbNameGroup = document.getElementById('db_name_group');
        
        if (createDb && dbNameGroup) {
            dbNameGroup.style.display = createDb.checked ? 'block' : 'none';
        }
    }

    toggleUserFields() {
        const createUser = document.getElementById('create_user');
        const userFields = document.getElementById('new_user_fields');
        
        if (createUser && userFields) {
            userFields.style.display = createUser.checked ? 'block' : 'none';
        }
    }

    generatePassword() {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < 16; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        const passwordInput = document.getElementById('new_password');
        if (passwordInput) {
            passwordInput.value = password;
            this.showPasswordGenerated(password);
        }
    }

    showPasswordGenerated(password) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-success';
        alert.innerHTML = `<strong>✅ Password Generated:</strong> <code>${password}</code>`;
        
        const form = document.querySelector('form');
        form.insertBefore(alert, form.firstChild);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }

    async testDatabaseConnection() {
        const testBtn = document.querySelector('[data-action="test-connection"]');
        const originalText = testBtn.textContent;
        
        testBtn.textContent = 'Testing...';
        testBtn.disabled = true;

        try {
            const formData = new FormData(document.querySelector('form'));
            const response = await fetch('install.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();
            this.showConnectionResult(result);

        } catch (error) {
            this.showConnectionResult({ success: false, error: error.message });
        } finally {
            testBtn.textContent = originalText;
            testBtn.disabled = false;
        }
    }

    showConnectionResult(result) {
        const resultDiv = document.getElementById('connection_result');
        if (!resultDiv) return;

        if (result.success) {
            resultDiv.innerHTML = `
                <div class="alert alert-success">
                    <strong>✅ Connection Successful!</strong><br>
                    Database connection is working properly.
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <strong>❌ Connection Failed</strong><br>
                    ${result.error}
                </div>
            `;
        }
    }

    // Installation Methods
    initializeInstallation() {
        // Auto-start installation after a short delay
        setTimeout(() => {
            this.startInstallation();
        }, 1000);
    }

    startInstallation() {
        const form = document.getElementById('install-form');
        if (form) {
            form.submit();
            this.trackInstallationProgress();
        }
    }

    trackInstallationProgress() {
        let currentStep = 0;
        
        this.progressInterval = setInterval(() => {
            if (currentStep < this.installationSteps.length) {
                this.updateProgressStep(currentStep);
                currentStep++;
            } else {
                clearInterval(this.progressInterval);
                this.redirectToComplete();
            }
        }, 3000);
    }

    updateProgressStep(stepIndex) {
        const steps = document.querySelectorAll('.progress-item');
        
        // Mark previous step as completed
        if (stepIndex > 0) {
            steps[stepIndex - 1].classList.remove('active');
            steps[stepIndex - 1].classList.add('completed');
            steps[stepIndex - 1].querySelector('.step-icon').textContent = '✅';
        }
        
        // Mark current step as active
        if (stepIndex < steps.length) {
            steps[stepIndex].classList.add('active');
            steps[stepIndex].querySelector('.step-icon').textContent = '⏳';
            
            // Update status text
            const statusText = document.getElementById('status-text');
            if (statusText) {
                statusText.textContent = this.installationSteps[stepIndex] + '...';
            }
        }
    }

    redirectToComplete() {
        window.location.href = 'install.php?step=complete';
    }

    // Form Handling
    handleFormSubmit(form, event) {
        // Add client-side validation
        if (!this.validateForm(form)) {
            event.preventDefault();
            return;
        }

        // Show loading state
        this.showFormLoading(form);
    }

    validateForm(form) {
        let isValid = true;
        
        form.querySelectorAll('.form-control[required]').forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }

    validateField(input) {
        const value = input.value.trim();
        let isValid = true;
        let message = '';

        // Basic validation
        if (input.hasAttribute('required') && !value) {
            isValid = false;
            message = 'This field is required';
        }

        // Email validation
        if (input.type === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                message = 'Please enter a valid email address';
            }
        }

        // License key validation
        if (input.id === 'license_key' && value) {
            const licenseRegex = /^VIDPOWR-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/;
            if (!licenseRegex.test(value)) {
                isValid = false;
                message = 'License key format is invalid';
            }
        }

        this.updateFieldValidation(input, isValid, message);
        return isValid;
    }

    updateFieldValidation(input, isValid, message) {
        const formGroup = input.closest('.form-group');
        if (!formGroup) return;

        // Remove existing validation classes
        input.classList.remove('is-valid', 'is-invalid');
        
        // Remove existing feedback
        const existingFeedback = formGroup.querySelector('.invalid-feedback, .valid-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        if (isValid) {
            input.classList.add('is-valid');
        } else {
            input.classList.add('is-invalid');
            
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = message;
            formGroup.appendChild(feedback);
        }
    }

    showFormLoading(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
        }
    }

    // Navigation
    navigateToStep(step) {
        window.location.href = `install.php?step=${step}`;
    }

    // Utility Methods
    showStoredLicenseInfo(licenseData) {
        // Display stored license information
        console.log('Stored license:', licenseData);
    }

    showError(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.innerHTML = `<strong>❌ Error:</strong> ${message}`;
        
        const content = document.querySelector('.content');
        content.insertBefore(alert, content.firstChild);
        
        setTimeout(() => {
            alert.remove();
        }, 10000);
    }

    showSuccess(message) {
        const alert = document.createElement('div');
        alert.className = 'alert alert-success';
        alert.innerHTML = `<strong>✅ Success:</strong> ${message}`;
        
        const content = document.querySelector('.content');
        content.insertBefore(alert, content.firstChild);
        
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

// Initialize installer when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new VidpowrInstaller();
});

// Global utility functions
function generatePassword() {
    const installer = new VidpowrInstaller();
    installer.generatePassword();
}

function testConnection() {
    const installer = new VidpowrInstaller();
    installer.testDatabaseConnection();
}
