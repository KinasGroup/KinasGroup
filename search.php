<?php
/**
 * Global Search Page - KINAS GROUP
 * Searches across all divisions
 */

require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'includes/helpers.php';
require_once 'api/config/database.php';
require_once 'includes/je-components.php';

$db = Database::getInstance()->getConnection();

// Get search parameters
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$division = isset($_GET['division']) ? $_GET['division'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'relevance';

$pageTitle = 'Search Results - KINAS GROUP';
include 'templates/header.php';
?>

<div style="max-width: 1400px; margin: 100px auto 40px; padding: 0 40px;">
    <h1 style="font-family: 'Prata', serif; font-size: 32px; margin-bottom: 20px;">
        <i class="fas fa-search" style="color: #C6A43F;"></i> 
        Search Results
    </h1>
    
    <form method="GET" action="search.php" style="display: flex; gap: 12px; margin-bottom: 30px; flex-wrap: wrap;">
        <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" 
               placeholder="Search for cars, properties, solar, products..." 
               style="flex: 1; min-width: 280px; padding: 14px 20px; border: 2px solid #E0E0E0; border-radius: 8px; font-size: 16px;">
        <select name="division" style="padding: 14px 20px; border: 2px solid #E0E0E0; border-radius: 8px; background: #fff; min-width: 150px;">
            <option value="all" <?php echo $division === 'all' ? 'selected' : ''; ?>>All Divisions</option>
            <option value="car" <?php echo $division === 'car' ? 'selected' : ''; ?>>🚗 Automobile</option>
            <option value="solar" <?php echo $division === 'solar' ? 'selected' : ''; ?>>☀️ Volt</option>
            <option value="property" <?php echo $division === 'property' ? 'selected' : ''; ?>>🏠 Homes</option>
            <option value="marketplace" <?php echo $division === 'marketplace' ? 'selected' : ''; ?>>🛍️ Marketplace</option>
        </select>
        <button type="submit" style="padding: 14px 32px; background: #C6A43F; color: #0A0A0A; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-search"></i> Search
        </button>
    </form>
    
    <?php if ($query): ?>
        <p style="color: #666; margin-bottom: 30px;">
            Showing results for: <strong>"<?php echo htmlspecialchars($query); ?>"</strong>
        </p>
        
        <div style="background: #F8F8F8; padding: 40px; text-align: center; border-radius: 12px;">
            <i class="fas fa-search" style="font-size: 48px; color: #C6A43F; margin-bottom: 16px; display: block;"></i>
            <h3>Search functionality is being integrated</h3>
            <p style="color: #666;">Please check back soon for full search results across all divisions.</p>
        </div>
    <?php else: ?>
        <div style="background: #F8F8F8; padding: 60px; text-align: center; border-radius: 12px;">
            <i class="fas fa-search" style="font-size: 48px; color: #C6A43F; margin-bottom: 16px; display: block;"></i>
            <h3>Search for anything on KINAS GROUP</h3>
            <p style="color: #666;">Enter keywords above to search across all divisions.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'templates/footer.php'; ?>
