<?php
// KINAS GROUP - Application Constants

// Site Configuration
define('SITE_NAME', getenv('APP_NAME') ?: 'KINAS GROUP');
define('SITE_URL',  rtrim(getenv('APP_URL') ?: 'https://kinasgroup.com', '/'));
define('ADMIN_EMAIL',   getenv('ADMIN_EMAIL')   ?: 'admin@kinasgroup.com');
define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'support@kinasgroup.com');

// Division Configuration
define('DIVISION_AUTOMOBILE', 'kinas-automobile');
define('DIVISION_REAL_ESTATE', 'williams-connect-home');
define('DIVISION_SOLAR', 'kinas-volt');
define('DIVISION_MARKETPLACE', 'kinas-marketplace');

// Division Names
define('DIVISIONS', [
    'kinas-automobile' => 'KINAS AUTOMOBILE LIMITED',
    'williams-connect-home' => 'WILLIAMS CONNECT HOME',
    'kinas-volt' => 'KINAS VOLT',
    'kinas-marketplace' => 'KINAS MARKETPLACE'
]);

// Division Accent Colors
define('DIVISION_COLORS', [
    'kinas-automobile'      => '#006c75',
    'williams-connect-home' => '#1A5276',
    'kinas-volt'            => '#27AE60',
    'kinas-marketplace'     => '#8E44AD'
]);

// =======================
// Upload Paths (Local - used only when R2 is disabled or as fallback)
// =======================

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('CAR_UPLOAD_DIR', UPLOAD_DIR . 'cars/');
define('PROPERTY_UPLOAD_DIR', UPLOAD_DIR . 'properties/');
define('PRODUCT_UPLOAD_DIR', UPLOAD_DIR . 'products/');
define('KYC_UPLOAD_DIR', UPLOAD_DIR . 'kyc-documents/');
define('BLOG_UPLOAD_DIR', UPLOAD_DIR . 'blog/');

// =======================
// Cloudflare R2 Storage Configuration
// =======================

// R2 Enable/Disable (reads from environment)
define('R2_ENABLED', getenv('R2_ENABLED') !== 'false' && !empty(getenv('R2_BUCKET')));

// R2 Credentials
define('R2_ACCOUNT_ID', getenv('R2_ACCOUNT_ID') ?: '');
define('R2_ACCESS_KEY', getenv('R2_ACCESS_KEY_ID') ?: '');
define('R2_SECRET_KEY', getenv('R2_SECRET_ACCESS_KEY') ?: '');
define('R2_BUCKET',     getenv('R2_BUCKET') ?: 'kinas-group-uploads');
define('R2_PUBLIC_URL', getenv('R2_PUBLIC_URL')
    ?: (R2_ACCOUNT_ID ? 'https://pub-' . R2_ACCOUNT_ID . '.r2.dev/' . (getenv('R2_BUCKET') ?: 'kinas-group-uploads') : ''));

// R2 Folder Structure (mirrors local structure)
define('R2_FOLDERS', [
    'cars' => 'cars/',
    'properties' => 'properties/',
    'products' => 'products/',
    'kyc-documents' => 'kyc-documents/',
    'blog' => 'blog/',
    'general' => 'general/'
]);

// R2 Upload Configuration
define('R2_MAX_UPLOAD_SIZE', (int)(getenv('R2_MAX_UPLOAD_SIZE') ?: 10 * 1024 * 1024)); // 10MB default
define('R2_IMAGE_QUALITY',   (int)(getenv('R2_IMAGE_QUALITY')   ?: 85));
define('R2_THUMBNAIL_WIDTH', (int)(getenv('R2_THUMBNAIL_WIDTH') ?: 400));
define('R2_MAX_IMAGE_WIDTH', (int)(getenv('R2_MAX_IMAGE_WIDTH') ?: 1920));
define('R2_MAX_IMAGE_HEIGHT',(int)(getenv('R2_MAX_IMAGE_HEIGHT') ?: 1080));

// Fallback to local storage if R2 fails
define('R2_FALLBACK_TO_LOCAL', getenv('R2_FALLBACK_TO_LOCAL') !== 'false');

// Storage driver preference ('r2' or 'local')
define('STORAGE_DRIVER', (R2_ENABLED && !empty(R2_ACCOUNT_ID) && !empty(R2_ACCESS_KEY)) ? 'r2' : 'local');

// File Upload Limits
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);
define('MAX_IMAGES_PER_LISTING', 20);

// Pagination
define('ITEMS_PER_PAGE', 12);
define('BLOG_ITEMS_PER_PAGE', 9);
define('ADMIN_ITEMS_PER_PAGE', 25);

// Security
define('PASSWORD_MIN_LENGTH', 8);
define('SESSION_LIFETIME', 86400); // 24 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes
define('OTP_LENGTH', 6);
define('OTP_EXPIRY', 600); // 10 minutes
define('CSRF_TOKEN_LENGTH', 32);

// Commission
define('COMMISSION_RATE', 5); // 5%
define('FEATURED_LISTING_PRICE', 49.99);
define('LISTING_DURATION_DAYS', 90);

// Currency
define('CURRENCY', 'NGN');
define('CURRENCY_SYMBOL', '₦');

// API
define('API_RATE_LIMIT', 100); // requests per minute
define('API_RATE_WINDOW', 60); // seconds

// Email Templates
define('EMAIL_TEMPLATES', [
    'welcome' => 'Welcome to KINAS GROUP',
    'verification' => 'Verify Your Email',
    'password_reset' => 'Password Reset Request',
    'agent_approved' => 'Agent Account Approved',
    'agent_rejected' => 'Agent Application Status',
    'new_inquiry' => 'New Listing Inquiry',
    'listing_approved' => 'Listing Approved',
    'listing_flagged' => 'Listing Flagged for Review'
]);

// Listing Statuses
define('LISTING_STATUSES', ['active', 'sold', 'pending', 'flagged', 'removed', 'expired', 'draft']);

// Agent Statuses
define('AGENT_STATUSES', ['pending', 'approved', 'rejected', 'suspended']);

// User Roles
define('USER_ROLES', ['user', 'agent', 'admin']);

// Property Types
define('PROPERTY_TYPES', [
    'house' => 'House',
    'apartment' => 'Apartment',
    'condo' => 'Condo',
    'villa' => 'Villa',
    'townhouse' => 'Townhouse',
    'land' => 'Land',
    'commercial' => 'Commercial'
]);

// Car Conditions
define('CAR_CONDITIONS', ['new', 'used', 'certified-pre-owned']);

// Fuel Types
define('FUEL_TYPES', ['petrol', 'diesel', 'electric', 'hybrid', 'plugin-hybrid']);

// Transmission Types
define('TRANSMISSION_TYPES', ['automatic', 'manual', 'semi-automatic', 'cvt']);

// Solar Service Types
define('SOLAR_SERVICES', [
    'residential' => 'Residential Installation',
    'commercial' => 'Commercial Solutions',
    'industrial' => 'Industrial Scale',
    'maintenance' => 'Maintenance',
    'financing' => 'Financing',
    'battery' => 'Battery Storage'
]);

// Marketplace Categories
define('MARKETPLACE_CATEGORIES', [
    'electronics' => 'Electronics',
    'fashion' => 'Fashion',
    'home-garden' => 'Home & Garden',
    'sports' => 'Sports & Outdoors',
    'collectibles' => 'Collectibles',
    'art' => 'Art',
    'jewelry' => 'Jewelry & Watches',
    'vehicles' => 'Vehicles',
    'other' => 'Other'
]);

// Social Media Links
define('SOCIAL_MEDIA', [
    'facebook' => 'https://facebook.com/kinasgroup',
    'twitter' => 'https://x.com/kinasgroup',      // Updated to x.com
    'instagram' => 'https://instagram.com/kinasgroup',
    'linkedin' => 'https://linkedin.com/company/kinasgroup',
    'youtube' => 'https://youtube.com/@kinasgroup'
]);

// Timezone — must match database.php; use Africa/Lagos for Nigeria operations
date_default_timezone_set(getenv('TIMEZONE') ?: 'Africa/Lagos');
?>
