<?php

// ========================
// KINAS GROUP - Roundcube Configuration
// ========================

// Database
$config['db_dsnw'] = 'mysql://roundcube:YOUR_DB_PASSWORD_HERE@localhost/roundcube_db';

// IMAP (Incoming Mail)
$config['default_host'] = 'ssl://imap.gmail.com';        // Change to your IMAP server
$config['default_port'] = 993;
$config['imap_conn_options'] = [
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false
    ]
];

// SMTP (Sending) - Using Resend Relay / API-friendly
$config['smtp_server'] = 'smtp.resend.com';   // Or your provider
$config['smtp_port'] = 587;
$config['smtp_user'] = '%u';                  // Use full email address
$config['smtp_pass'] = '%p';
$config['smtp_conn_options'] = [
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true,
        'allow_self_signed' => false
    ]
];

// General Settings
$config['product_name'] = 'KINAS GROUP Mail';
$config['support_url'] = 'https://kinas-group.com/support';
$config['skin'] = 'elastic';                    // Modern responsive skin
$config['language'] = 'en_US';
$config['timezone'] = 'Africa/Lagos';           // Nigeria timezone

// Security
$config['des_key'] = 'REPLACE_WITH_A_RANDOM_24_CHAR_STRING_!'; // Generate a strong one
$config['cipher_method'] = 'AES-256-CBC';

// Features
$config['enable_spellcheck'] = true;
$config['plugins'] = [
    'archive',
    'zipdownload',
    'managesieve',     // For email filters
    'password',        // Optional: allow password change
    'markasjunk'
];

// Logging
$config['log_driver'] = 'file';
$config['log_dir'] = '/var/log/roundcube/';   // Ensure this folder exists and is writable

// Session & Security
$config['session_lifetime'] = 60;             // minutes
$config['ip_check'] = true;
$config['double_auth'] = false;               // Set true if using 2FA

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

// Prevent installer access after setup
$config['enable_installer'] = false;          // IMPORTANT: Set to false after installation
