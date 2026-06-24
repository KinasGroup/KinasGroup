<?php
/**
 * KINAS GROUP - Main Header Template
 * Used across all pages
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'KINAS GROUP - Luxury Marketplace'; ?></title>
    <meta name="description" content="<?php echo $pageDescription ?? 'Discover luxury automobiles, properties, solar solutions and more at KINAS GROUP.'; ?>">
    
    <!-- ============================================================ -->
    <!-- USER DATA - Pass session data to JavaScript (ADDED FOR FIX) -->
    <!-- ============================================================ -->
    <meta name="user-data" content='<?php 
        $userData = [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'phone' => $_SESSION['user_phone'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
            'loggedIn' => isset($_SESSION['user_id'])
        ];
        echo json_encode($userData);
    ?>'>
    <meta name="user-id" content="<?php echo $_SESSION['user_id'] ?? ''; ?>">
    
    <!-- ============================================================ -->
    <!-- STYLES -->
    <!-- ============================================================ -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Prata&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/responsive.css">
    <link rel="stylesheet" href="/assets/css/luxury-marketplace.css">
    <link rel="stylesheet" href="/assets/css/james-edition.css">
    <link rel="stylesheet" href="/assets/css/footer-social.css">
    
    <?php if (isset($isAdminPage) && $isAdminPage): ?>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <?php endif; ?>
    
    <!-- ============================================================ -->
    <!-- FAVICON -->
    <!-- ============================================================ -->
    <link rel="icon" href="/assets/images/logos/kinas-group-logo.png" type="image/png">
    
    <!-- ============================================================ -->
    <!-- FLASH MESSAGES STYLES (from functions.php) -->
    <!-- ============================================================ -->
    <?php if (function_exists('get_flash_styles')): ?>
        <?php echo get_flash_styles(); ?>
    <?php endif; ?>
    
    <!-- ============================================================ -->
    <!-- CUSTOM PAGE STYLES -->
    <!-- ============================================================ -->
    <?php if (isset($customStyles)): ?>
        <style><?php echo $customStyles; ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- ============================================================ -->
    <!-- FLASH MESSAGES -->
    <!-- ============================================================ -->
    <?php if (function_exists('display_flash_messages')): ?>
        <?php echo display_flash_messages(); ?>
    <?php endif; ?>

    <!-- ============================================================ -->
    <!-- HEADER -->
    <!-- ============================================================ -->
    <header id="header" class="je3-header <?php echo $headerClass ?? 'solid'; ?>">
        <div class="je3-container">
            <!-- Logo -->
            <a href="/" class="je3-logo">
                <img src="/assets/images/logos/kinas-group-logo.png" alt="KINAS GROUP" height="50">
                <span class="je3-logo-text">KINAS <span class="gold">GROUP</span></span>
            </a>

            <!-- Navigation -->
            <nav class="je3-nav" id="mainNav">
                <ul class="je3-nav-list">
                    <li class="je3-nav-item has-dropdown">
                        <a href="#" class="je3-nav-link">Divisions <i class="fas fa-chevron-down"></i></a>
                        <div class="je3-dropdown">
                            <a href="/divisions/kinas-automobile/" class="je3-dropdown-item">
                                <i class="fas fa-car"></i> KINAS Automobile
                            </a>
                            <a href="/divisions/kinas-marketplace/" class="je3-dropdown-item">
                                <i class="fas fa-store"></i> KINAS Marketplace
                            </a>
                            <a href="/divisions/kinas-volt/" class="je3-dropdown-item">
                                <i class="fas fa-solar-panel"></i> KINAS Volt
                            </a>
                            <a href="/divisions/williams-connect-home/" class="je3-dropdown-item">
                                <i class="fas fa-home"></i> Williams Connect Home
                            </a>
                        </div>
                    </li>
                    <li class="je3-nav-item"><a href="/about.php" class="je3-nav-link">About</a></li>
                    <li class="je3-nav-item"><a href="/blog/" class="je3-nav-link">Blog</a></li>
                    <li class="je3-nav-item"><a href="/contact.php" class="je3-nav-link">Contact</a></li>
                    
                    <?php if (SessionManager::isLoggedIn()): ?>
                        <li class="je3-nav-item has-dropdown">
                            <a href="#" class="je3-nav-link">
                                <i class="fas fa-user-circle"></i> 
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Account'); ?>
                                <i class="fas fa-chevron-down"></i>
                            </a>
                            <div class="je3-dropdown je3-dropdown-right">
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <a href="/admin/dashboard.php" class="je3-dropdown-item">
                                        <i class="fas fa-crown"></i> Admin Dashboard
                                    </a>
                                <?php elseif ($_SESSION['user_role'] === 'agent'): ?>
                                    <a href="/agent/dashboard.php" class="je3-dropdown-item">
                                        <i class="fas fa-user-tie"></i> Agent Dashboard
                                    </a>
                                <?php else: ?>
                                    <a href="/user/dashboard.php" class="je3-dropdown-item">
                                        <i class="fas fa-user"></i> My Dashboard
                                    </a>
                                <?php endif; ?>
                                <a href="/user/profile.php" class="je3-dropdown-item">
                                    <i class="fas fa-cog"></i> Profile Settings
                                </a>
                                <a href="/user/saved-listings.php" class="je3-dropdown-item">
                                    <i class="fas fa-heart"></i> Saved Listings
                                </a>
                                <hr class="je3-dropdown-divider">
                                <a href="/auth/logout.php" class="je3-dropdown-item je3-dropdown-danger">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="je3-nav-item">
                            <a href="/auth/login.php" class="je3-nav-link je3-nav-cta">Login</a>
                        </li>
                        <li class="je3-nav-item">
                            <a href="/auth/register.php" class="je3-nav-link je3-nav-cta je3-nav-cta-gold">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Mobile Menu Toggle -->
            <button class="je3-mobile-toggle" id="mobileToggle" aria-label="Toggle menu">
                <span class="je3-mobile-bar"></span>
                <span class="je3-mobile-bar"></span>
                <span class="je3-mobile-bar"></span>
            </button>
        </div>
    </header>

    <!-- ============================================================ -->
    <!-- MAIN CONTENT WRAPPER -->
    <!-- ============================================================ -->
    <main id="main-content">

<?php
// ============================================================
// FLASH MESSAGE STYLES (inline - already output above)
// ============================================================
// The flash styles are output via get_flash_styles() above
// which is defined in functions.php
// ============================================================
?>
