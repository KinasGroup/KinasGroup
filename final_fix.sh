#!/bin/bash

echo "🔧 Applying final fixes to all files..."

# Fix 1: Rename generateCSRFToken to generate_csrf_token
find . -name "*.php" -type f -exec sed -i 's/generateCSRFToken()/generate_csrf_token()/g' {} \;

# Fix 2: Remove duplicate session_start from login.php
sed -i '/^session_start();/d' auth/login.php
# Add session_start at the top properly
sed -i '1i<?php\nsession_start();' auth/login.php

# Fix 3: Ensure csrf.php has the right function name
cat > includes/csrf.php << 'CSRF_EOF'
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
?>
CSRF_EOF

echo "✅ All fixes applied"
echo ""
echo "Now commit and push:"
echo "git add . && git commit -m 'Fix CSRF function name and session issues' && git push origin main"
