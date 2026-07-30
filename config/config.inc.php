<?php

// ========================
// KINAS GROUP - Roundcube Configuration (FIXED)
// ========================

// Database - USING YOUR ACTUAL RAILWAY DATABASE
$config['db_dsnw'] = 'mysql://root:xFpLpHtZgWqiNPVGBGJGYPexiLCznkft@mysql-geov.railway.internal/railway';

// IMAP (Incoming Mail) - Using your email provider
$config['default_host'] = 'imaps://imap.gmail.com:993';
$config['imap_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
];

// SMTP (Sending) - Using Resend
$config['smtp_server'] = 'tls://smtp.resend.com';
$config['smtp_port'] = 587;
$config['smtp_user'] = '%u';
$config['smtp_pass'] = '%p';
$config['smtp_conn_options'] = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
];

// General Settings
$config['product_name'] = 'KINAS GROUP Mail';
$config['support_url'] = 'https://kinas-group.com/support';
$config['skin'] = 'elastic';
$config['language'] = 'en_US';
$config['timezone'] = 'Africa/Lagos';

// Security
$config['des_key'] = 'qaorx6ok43z57CKVbYtWGwMW';
$config['cipher_method'] = 'AES-256-CBC';

// Features
$config['enable_spellcheck'] = true;
$config['plugins'] = [
    'archive',
    'zipdownload',
    'managesieve',
    'password',
    'markasjunk'
];

// Logging - USING THE CORRECT DIRECTORY
$config['log_driver'] = 'file';
$config['log_dir'] = '/var/www/html/logs/';

// Session & Security
$config['session_lifetime'] = 60;
$config['ip_check'] = true;
$config['double_auth'] = false;

// Mime & Attachments
$config['max_message_size'] = '50M';
$config['mime_types'] = true;

// Branding
$config['logo_display'] = 'always';
$config['create_default_folders'] = true;

// Performance
$config['enable_caching'] = true;
$config['messages_sort_col'] = 'date';
$config['messages_sort_order'] = 'DESC';

// Disable installer
$config['enable_installer'] = false;
