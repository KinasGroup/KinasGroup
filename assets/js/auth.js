// KINAS GROUP - Authentication Handler
class AuthHandler {
    constructor() {
        this.currentUser = JSON.parse(localStorage.getItem('kinas_user') || 'null');
        this.init();
    }
    
    init() {
        this.updateUI();
        this.setupEventListeners();
    }
    
    updateUI() {
        const authLinks = document.querySelectorAll('[data-auth]');
        authLinks.forEach(link => {
            const action = link.dataset.auth;
            if (this.currentUser) {
                if (action === 'show-logged-in') link.style.display = '';
                if (action === 'show-logged-out') link.style.display = 'none';
                if (action === 'username') link.textContent = this.currentUser.name;
            } else {
                if (action === 'show-logged-in') link.style.display = 'none';
                if (action === 'show-logged-out') link.style.display = '';
            }
        });
    }
    
    setupEventListeners() {
        // Login Form
        const loginForm = document.getElementById('login-form');
        if (loginForm) {
            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!validateForm(loginForm)) return;
                
                const email = loginForm.querySelector('[name="email"]').value;
                const password = loginForm.querySelector('[name="password"]').value;
                
                try {
                    const result = await api.login(email, password);
                    this.currentUser = result.user;
                    localStorage.setItem('kinas_user', JSON.stringify(result.user));
                    window.location.href = result.user.role === 'admin' ? '/admin/dashboard.php' : '/agent/dashboard.php';
                } catch (error) {
                    this.showError(loginForm, error.message);
                }
            });
        }
        
        // Registration Form
        const registerForm = document.getElementById('register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!validateForm(registerForm)) return;
                
                const formData = new FormData(registerForm);
                const userData = Object.fromEntries(formData.entries());
                
                try {
                    await api.register(userData);
                    window.location.href = '/auth/verify-email.php';
                } catch (error) {
                    this.showError(registerForm, error.message);
                }
            });
        }
        
        // OTP Verification
        const otpForm = document.getElementById('otp-form');
        if (otpForm) {
            otpForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const otp = otpForm.querySelector('[name="otp"]').value;
                
                try {
                    await api.request('auth/verify-otp.php', {
                        method: 'POST',
                        body: JSON.stringify({ otp })
                    });
                    window.location.href = '/agent/verification.php';
                } catch (error) {
                    this.showError(otpForm, error.message);
                }
            });
        }
    }
    
    showError(form, message) {
        let errorDiv = form.querySelector('.form-error');
        if (!errorDiv) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-danger form-error';
            form.insertBefore(errorDiv, form.firstChild);
        }
        errorDiv.textContent = message;
        setTimeout(() => errorDiv.remove(), 5000);
    }
    
    logout() {
        api.logout();
        this.currentUser = null;
        localStorage.removeItem('kinas_user');
    }
}

// Initialize auth handler
const auth = new AuthHandler();