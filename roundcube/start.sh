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

# Configure port 8080
echo "Configuring port 8080..."
sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf

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

# Check if config exists
echo "=== Checking config file ==="
ls -la /var/www/html/config/
cat /var/www/html/config/config.inc.php | head -20

# Check if Roundcube files exist
echo "=== Checking Roundcube files ==="
ls -la /var/www/html/index.php

# Enable PHP error logging
echo "=== Enabling PHP error logging ==="
echo "display_errors = On" >> /usr/local/etc/php/conf.d/errors.ini
echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini
echo "log_errors = On" >> /usr/local/etc/php/conf.d/errors.ini
echo "error_log = /var/www/html/logs/php_errors.log" >> /usr/local/etc/php/conf.d/errors.ini

echo "=== Setup Complete. Starting Apache ==="
# Start Apache
exec apache2-foreground
