#!/bin/bash

# Enable PHP error logging
echo "display_errors = On" >> /usr/local/etc/php/conf.d/errors.ini
echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/errors.ini
echo "log_errors = On" >> /usr/local/etc/php/conf.d/errors.ini
echo "error_log = /var/www/html/logs/php_errors.log" >> /usr/local/etc/php/conf.d/errors.ini

# Fix MPM
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork

# Set ServerName
echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Configure port 8080
sed -i 's/Listen 80/Listen 8080/g' /etc/apache2/ports.conf

# Configure VirtualHost
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

# Check if config exists and log it
echo "=== Checking config ===" >> /var/www/html/logs/debug.log
ls -la /var/www/html/config/ >> /var/www/html/logs/debug.log 2>&1
cat /var/www/html/config/config.inc.php >> /var/www/html/logs/debug.log 2>&1

# Start Apache
exec apache2-foreground
