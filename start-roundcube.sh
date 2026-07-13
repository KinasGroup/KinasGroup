#!/bin/bash

# Enable MPM
a2dismod mpm_event mpm_worker 2>/dev/null
a2enmod mpm_prefork

# Set ServerName
echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Use port 8080
echo "Listen 8080" > /etc/apache2/ports.conf

# Create directories
mkdir -p /var/www/html/config /var/www/html/logs /var/www/html/temp

# Create config file
cat > /var/www/html/config/config.inc.php << 'CONFIG'
<?php
$config = array();
$config["db_dsnw"] = "mysql://root:xFpLpHtZgWqiNPVGBGJGYPexiLCznkft@mysql-geov.railway.internal/railway";
$config["default_host"] = "imaps://imap.gmail.com:993";
$config["smtp_server"] = "tls://smtp.resend.com";
$config["smtp_port"] = 587;
$config["smtp_user"] = "%u";
$config["smtp_pass"] = "%p";
$config["product_name"] = "KINAS GROUP Webmail";
$config["skin"] = "elastic";
$config["des_key"] = "lVRPVvUd3l5iYlmO1y8xGpuP";
$config["enable_installer"] = false;
$config["log_driver"] = "file";
$config["log_dir"] = "/var/www/html/logs/";
$config["auto_create_user"] = true;
$config["sent_mbox"] = "Sent";
$config["trash_mbox"] = "Trash";
$config["drafts_mbox"] = "Drafts";
$config["junk_mbox"] = "Junk";
$config["language"] = "en_US";
$config["timezone"] = "Africa/Lagos";
$config["enable_spellcheck"] = true;
$config["spellcheck_engine"] = "pspell";
?>
CONFIG

chown -R www-data:www-data /var/www/html/config /var/www/html/logs /var/www/html/temp

# Configure VirtualHost
rm -f /etc/apache2/sites-enabled/*
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

a2ensite roundcube

# Start Apache
apache2-foreground
