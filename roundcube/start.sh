#!/bin/bash

echo "=== Starting Roundcube Setup ==="

# Fix MPM
echo "Fixing MPM modules..."
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork

# Set ServerName
echo "Setting ServerName..."
echo "ServerName localhost" >> /etc/apache2/apache2.conf

# FIX: Completely rewrite ports.conf
echo "Fixing ports.conf..."
cat > /etc/apache2/ports.conf << 'PORTS'
Listen 8080
<IfModule ssl_module>
    Listen 443
</IfModule>
<IfModule mod_gnutls.c>
    Listen 443
</IfModule>
PORTS

# Configure VirtualHost
echo "Configuring VirtualHost..."
rm -f /etc/apache2/sites-enabled/* 2>/dev/null
cat > /etc/apache2/sites-available/roundcube.conf << 'VHOST'
<VirtualHost *:8080>
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
VHOST

a2ensite roundcube 2>/dev/null || true

# Verify Roundcube files exist
echo "=== Checking Roundcube files ==="
if [ -f /var/www/html/index.php ]; then
    echo "✅ index.php exists"
else
    echo "❌ index.php NOT FOUND!"
fi

# Enable PHP error logging
echo "=== Enabling PHP error logging ==="
echo "display_errors = On" >> /usr/local/etc/php/conf.d/errors.ini
echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini
echo "log_errors = On" >> /usr/local/etc/php/conf.d/errors.ini
echo "error_log = /var/www/html/logs/php_errors.log" >> /usr/local/etc/php/conf.d/errors.ini

# ============================================
# DATABASE CONNECTION TEST
# ============================================
echo "=== Testing Database Connection ==="
php -r "
try {
    \$pdo = new PDO('mysql:host=mysql-geov.railway.internal;dbname=railway', 'root', 'xFpLpHtZgWqiNPVGBGJGYPexiLCznkft');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo '✅ Database connection successful!\\n';
    
    // Check if tables exist
    \$tables = \$pdo->query('SHOW TABLES');
    \$tableCount = \$tables->rowCount();
    echo \"✅ Found \$tableCount tables in database.\\n\";
    
    // Check rc_users table
    \$result = \$pdo->query(\"SELECT COUNT(*) FROM rc_users\");
    \$userCount = \$result->fetchColumn();
    echo \"✅ rc_users table has \$userCount users.\\n\";
    
} catch (Exception \$e) {
    echo '❌ Database connection failed: ' . \$e->getMessage() . \"\\n\";
}
"

# ============================================
# ENSURE ADMIN USER EXISTS (NEW)
# ============================================
echo "=== Ensuring admin user exists ==="
php -r "
try {
    \$pdo = new PDO('mysql:host=mysql-geov.railway.internal;dbname=railway', 'root', 'xFpLpHtZgWqiNPVGBGJGYPexiLCznkft');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if admin user exists
    \$stmt = \$pdo->query(\"SELECT COUNT(*) FROM rc_users WHERE username = 'admin@kinas-group.com'\");
    \$count = \$stmt->fetchColumn();
    
    if (\$count == 0) {
        echo 'Admin user not found. Creating...\\n';
        \$pdo->exec(\"INSERT INTO rc_users (username, mail_host, created, last_login, language) VALUES ('admin@kinas-group.com', 'imaps://imappro.zoho.com:993', NOW(), NOW(), 'en_US')\");
        \$pdo->exec(\"INSERT INTO rc_identities (user_id, name, email, standard, organization) SELECT user_id, 'Administrator', 'admin@kinas-group.com', 1, 'KINAS GROUP' FROM rc_users WHERE username = 'admin@kinas-group.com'\");
        echo '✅ Admin user created successfully!\\n';
    } else {
        echo '✅ Admin user already exists.\\n';
    }
    
    // Verify the user exists
    \$stmt = \$pdo->query(\"SELECT COUNT(*) FROM rc_users\");
    \$userCount = \$stmt->fetchColumn();
    echo \"✅ rc_users now has \$userCount user(s).\\n\";
    
} catch (Exception \$e) {
    echo '❌ Error ensuring admin user: ' . \$e->getMessage() . \"\\n\";
}
"

# ============================================
# CONFIG FILE CHECK
# ============================================
echo "=== Checking Config File ==="
if [ -f /var/www/html/config/config.inc.php ]; then
    echo "✅ config.inc.php exists"
    echo "=== Config contents (database line) ==="
    grep -i "db_dsnw" /var/www/html/config/config.inc.php
else
    echo "❌ config.inc.php NOT FOUND!"
fi

# ============================================
# IMAP/SMTP CONNECTION TEST
# ============================================
echo "=== Testing IMAP Connection ==="
php -r "
try {
    \$imap = imap_open('{imappro.zoho.com:993/imap/ssl/novalidate-cert}', 'admin@kinas-group.com', 'Company@4421');
    if (\$imap) {
        echo '✅ IMAP connection successful!\\n';
        imap_close(\$imap);
    } else {
        echo '❌ IMAP connection failed: ' . imap_last_error() . \"\\n\";
    }
} catch (Exception \$e) {
    echo '❌ IMAP test error: ' . \$e->getMessage() . \"\\n\";
}
"

# ============================================
# START APACHE
# ============================================
echo "=== Setup Complete. Starting Apache ==="
exec apache2-foreground
