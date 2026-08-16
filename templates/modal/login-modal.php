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
        if (!input) return;

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

    function updateModalCsrfToken(token) {
        if (!token) return;

        const field = document.querySelector('#modal-login-form input[name="csrf_token"]');

        if (field) {
            field.value = token;
        }
    }

    // Handle login form submission
    document.getElementById('modal-login-form')?.addEventListener('submit', async function(e) {
        e.preventDefault();

        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const errorDiv = document.getElementById('modal-login-error');
        const originalText = submitBtn.innerHTML;

        submitBtn.innerHTML = 'Signing in...';
        submitBtn.disabled = true;
        errorDiv.style.display = 'none';
        errorDiv.textContent = '';

        const formData = new FormData(form);
        const csrfToken = formData.get('csrf_token') || '';

        try {
            const response = await fetch('/api/auth/login.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({
                    email: formData.get('email'),
                    password: formData.get('password'),
                    remember_me: formData.get('remember_me') === '1',
                    csrf_token: csrfToken
                })
            });

            const rawText = await response.text();

            let data = {};

            try {
                data = JSON.parse(rawText);
            } catch (parseError) {
                console.error('Modal login response was not valid JSON:', rawText);
                throw new Error('Invalid login response');
            }

            if (data.csrf_token) {
                updateModalCsrfToken(data.csrf_token);
            }

            if (data.success) {
                if (data.token) {
                    localStorage.setItem('kinas_token', data.token);
                }

                if (data.user) {
                    localStorage.setItem('kinas_user', JSON.stringify(data.user));
                }

                closeLoginModal();

                const role = data?.user?.role || 'user';

                if (role === 'admin') {
                    window.location.href = '/admin/dashboard.php';
                } else if (role === 'agent') {
                    window.location.href = '/agent/dashboard.php';
                } else {
                    window.location.reload();
                }

                return;
            }

            errorDiv.textContent = data.error || 'Login failed. Please check your credentials.';
            errorDiv.style.display = 'block';
        } catch (error) {
            console.error('Modal login request failed:', error);
            errorDiv.textContent = 'Unable to complete login. Please check your connection and try again.';
            errorDiv.style.display = 'block';
        } finally {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    });
</script>
