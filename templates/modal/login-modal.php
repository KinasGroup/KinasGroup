<!-- Login Modal -->
<div id="login-modal" class="admin-modal" style="display: none;">
    <div class="admin-modal-content" style="max-width: 440px;">
        <div class="admin-modal-header">
            <h3>Welcome Back</h3>
            <button onclick="closeLoginModal()" class="modal-close">✕</button>
        </div>
        
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="/assets/images/logos/kinas-group-logo.jpg" alt="KINAS GROUP" style="height: 30px; margin-bottom: 10px;">
            <p style="color: var(--tertiary); font-size: 14px;">Sign in to your KINAS account</p>
        </div>
        
        <form id="modal-login-form">
            <?php echo Security::csrfField(); ?>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="your@email.com" required autocomplete="email">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <div style="position: relative;">
                    <input type="password" name="password" id="modal-password" placeholder="Your password" required autocomplete="current-password" style="padding-right: 40px;">
                    <button type="button" 
                            onclick="togglePasswordVisibility('modal-password')" 
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--tertiary);">
                        👁️
                    </button>
                </div>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; color: var(--tertiary);">
                    <input type="checkbox" name="remember_me" value="1">
                    Remember me
                </label>
                <a href="/auth/forgot-password.php" style="font-size: 13px; color: var(--accent); text-decoration: none;">Forgot password?</a>
            </div>
            
            <button type="submit" class="je2-button black" style="width: 100%; padding: 14px; font-size: 16px;">
                Sign In
            </button>
            
            <div id="modal-login-error" class="alert alert-danger" style="display: none; margin-top: 15px;"></div>
        </form>
        
        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-card);">
            <p style="color: var(--tertiary); font-size: 14px;">
                Don't have an account? 
                <a href="/auth/register.php" style="color: var(--accent); text-decoration: none; font-weight: 600;">Register as Agent</a>
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 15px;">
            <p style="font-size: 12px; color: var(--tertiary);">
                By signing in, you agree to our 
                <a href="/pages/terms-of-use.php" style="color: var(--accent);">Terms</a> and 
                <a href="/pages/privacy-policy.php" style="color: var(--accent);">Privacy Policy</a>
            </p>
        </div>
    </div>
</div>

<script>
function openLoginModal() {
    document.getElementById('login-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeLoginModal() {
    document.getElementById('login-modal').style.display = 'none';
    document.body.style.overflow = '';
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    input.type = input.type === 'password' ? 'text' : 'password';
}

// Close modal when clicking overlay
document.getElementById('login-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeLoginModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLoginModal();
    }
});

// Handle login form submission
document.getElementById('modal-login-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const errorDiv = document.getElementById('modal-login-error');
    const originalText = submitBtn.textContent;
    
    // Show loading state
    submitBtn.textContent = 'Signing in...';
    submitBtn.disabled = true;
    errorDiv.style.display = 'none';
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('/api/auth/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': formData.get('csrf_token')
            },
            body: JSON.stringify({
                email: formData.get('email'),
                password: formData.get('password'),
                remember_me: formData.get('remember_me') === '1'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Store token
            localStorage.setItem('kinas_token', data.token);
            localStorage.setItem('kinas_user', JSON.stringify(data.user));
            
            // Redirect based on role
            closeLoginModal();
            
            if (data.user.role === 'admin') {
                window.location.href = '/admin/dashboard.php';
            } else if (data.user.role === 'agent') {
                window.location.href = '/agent/dashboard.php';
            } else {
                window.location.reload();
            }
        } else {
            errorDiv.textContent = data.error || 'Login failed. Please check your credentials.';
            errorDiv.style.display = 'block';
        }
    } catch (error) {
        errorDiv.textContent = 'Network error. Please try again.';
        errorDiv.style.display = 'block';
    } finally {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
    }
});
</script>