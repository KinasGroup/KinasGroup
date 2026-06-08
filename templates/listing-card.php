<?php
// Reusable Listing Card Template - LUXURY STYLED
function renderListingCard($listing, $type = 'car') {
    $url = getListingUrl($listing, $type);
    $image = getListingImage($listing, $type);
    $price = formatPriceNgn($listing['price']); // Updated for NGN
    $featured = !empty($listing['featured']);
    $verified = !empty($listing['agent_verified']);
    $isNew = strtotime($listing['created_at']) > strtotime('-7 days');
    
    ob_start();
?>
    <div class="luxury-card" data-id="<?php echo $listing['id']; ?>" data-type="<?php echo $type; ?>">
        <a href="<?php echo $url; ?>" style="text-decoration: none; color: inherit;">
            <div class="luxury-card-image" style="position: relative; height: 260px; overflow: hidden; background: #f0f0f0;">
                <img src="<?php echo $image; ?>" 
                     alt="<?php echo htmlspecialchars($listing['title']); ?>"
                     loading="lazy"
                     style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;">
                
                <?php if ($featured): ?>
                    <span style="position: absolute; top: 15px; left: 15px; background: var(--je-gold, #C6A43F); color: var(--je-black, #0A0A0A); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                        Featured
                    </span>
                <?php endif; ?>
                
                <?php if ($isNew): ?>
                    <span style="position: absolute; top: 15px; right: 55px; background: #2E7D32; color: white; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 600;">
                        New
                    </span>
                <?php endif; ?>
                
                <button class="favorite-btn" 
                        onclick="event.preventDefault(); toggleFavorite(<?php echo $listing['id']; ?>, '<?php echo $type; ?>')"
                        aria-label="Save to favorites"
                        style="position: absolute; top: 15px; right: 15px; background: white; border: none; border-radius: 50%; width: 36px; height: 36px; cursor: pointer; font-size: 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.3s; display: flex; align-items: center; justify-content: center;">
                    ♡
                </button>
            </div>
            
            <div class="luxury-card-content" style="padding: 20px;">
                <p style="font-family: 'Inter', sans-serif; font-size: 12px; color: var(--je-gold, #C6A43F); text-transform: uppercase; letter-spacing: 1px; font-weight: 600; margin-bottom: 8px;">
                    <?php echo htmlspecialchars($listing['division_name'] ?? getDivisionName($type)); ?>
                </p>
                
                <h3 style="font-family: 'Prata', serif; font-size: 20px; font-weight: 400; margin: 10px 0; color: var(--je-black, #0A0A0A); line-height: 1.3;">
                    <?php echo htmlspecialchars($listing['title']); ?>
                </h3>
                
                <?php
                // Type-specific details
                switch($type) {
                    case 'car':
                        echo '<p style="font-family: "Inter", sans-serif; font-size: 13px; color: var(--je-gray-dark, #666666); margin: 8px 0;">';
                        echo htmlspecialchars($listing['year'] . ' · ' . number_format($listing['mileage']) . ' km · ' . $listing['transmission']);
                        echo '</p>';
                        break;
                    case 'property':
                        echo '<p style="font-family: "Inter", sans-serif; font-size: 13px; color: var(--je-gray-dark, #666666); margin: 8px 0;">';
                        echo htmlspecialchars($listing['beds'] . ' Beds · ' . $listing['baths'] . ' Baths · ' . number_format($listing['sqft']) . ' sqft');
                        echo '</p>';
                        break;
                }
                ?>
                
                <p class="luxury-card-price" style="font-family: 'Inter', sans-serif; font-size: 28px; font-weight: 700; color: var(--je-black, #0A0A0A); margin: 15px 0 10px;">
                    <?php echo $price; ?>
                </p>
                
                <div style="margin-top: 8px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <?php if ($verified): ?>
                        <span style="display: inline-block; background: #E8F5E9; color: #2E7D32; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: 'Inter', sans-serif;">
                            <i class="fas fa-check-circle"></i> Verified Agent
                        </span>
                    <?php endif; ?>
                    
                    <span style="font-size: 12px; color: var(--je-gray-dark, #666666); font-family: 'Inter', sans-serif;">
                        <i class="far fa-clock"></i> <?php echo timeAgo($listing['created_at']); ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    
    <style>
        .luxury-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #E0E0E0;
        }
        
        .luxury-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border-color: var(--je-gold, #C6A43F);
        }
        
        .luxury-card:hover .luxury-card-image img {
            transform: scale(1.05);
        }
        
        .favorite-btn:hover {
            background: var(--je-gold, #C6A43F);
            color: white;
            transform: scale(1.1);
        }
    </style>
<?php
    return ob_get_clean();
}

// Helper function for Nigerian Naira formatting
function formatPriceNgn($price) {
    if (!$price) return '₦0';
    return '₦' . number_format($price, 0, '.', ',');
}

function getListingUrl($listing, $type) {
    $baseUrls = [
        'car' => '/divisions/kinas-automobile/detail.php?id=',
        'property' => '/divisions/williams-connect-home/detail.php?id=',
        'solar' => '/divisions/kinas-volt/services.php?id=',
        'marketplace' => '/divisions/kinas-marketplace/detail.php?id='
    ];
    
    return ($baseUrls[$type] ?? '#') . $listing['id'];
}

function getListingImage($listing, $type) {
    $placeholders = [
        'car' => '/assets/images/placeholder/car-placeholder.jpg',
        'property' => '/assets/images/placeholder/property-placeholder.jpg',
        'solar' => '/assets/images/placeholder/solar-placeholder.jpg',
        'marketplace' => '/assets/images/placeholder/product-placeholder.jpg'
    ];
    
    return $listing['thumbnail'] ?? $listing['featured_image'] ?? $placeholders[$type] ?? '/assets/images/placeholder/generic-placeholder.jpg';
}

function getDivisionName($type) {
    $names = [
        'car' => 'KINAS Automobile',
        'property' => 'Williams Connect Home',
        'solar' => 'KINAS Volt',
        'marketplace' => 'KINAS Marketplace'
    ];
    
    return $names[$type] ?? 'KINAS GROUP';
}

function timeAgo($timestamp) {
    if (!$timestamp) return 'Recently';
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff/60) . ' min ago';
    if ($diff < 86400) return floor($diff/3600) . ' hours ago';
    if ($diff < 604800) return floor($diff/86400) . ' days ago';
    return date('M j, Y', $time);
}
?>
