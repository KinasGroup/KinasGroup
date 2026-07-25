<?php
/**
 * Featured Listings Algorithm
 * Automatically selects the best listings to feature based on multiple criteria
 */

class FeaturedAlgorithm {
    private $db;
    private $criteria = [
        'views' => 30,          // Weight for views
        'recent' => 25,         // Weight for recent listings
        'price_value' => 20,    // Weight for best value
        'completeness' => 15,   // Weight for complete listings
        'engagement' => 10      // Weight for engagement (messages/inquiries)
    ];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Calculate a score for a listing
     */
    public function calculateScore($listing, $division) {
        $score = 0;
        
        // 1. Views Score (based on relative popularity)
        $views = $listing['views'] ?? 0;
        $maxViews = $this->getMaxViews($division);
        if ($maxViews > 0) {
            $score += ($views / $maxViews) * $this->criteria['views'];
        }
        
        // 2. Recent Score (newer listings get higher score)
        $created = strtotime($listing['created_at'] ?? 'now');
        $daysOld = (time() - $created) / (60 * 60 * 24);
        $recencyScore = max(0, (30 - $daysOld) / 30); // 0-30 days
        $score += $recencyScore * $this->criteria['recent'];
        
        // 3. Price Value Score (best value for money)
        if (isset($listing['price']) && $listing['price'] > 0) {
            // Compare to average price in this division
            $avgPrice = $this->getAveragePrice($division);
            if ($avgPrice > 0) {
                $priceRatio = $avgPrice / $listing['price'];
                $valueScore = min(1, $priceRatio / 2); // Cap at 1
                $score += $valueScore * $this->criteria['price_value'];
            }
        }
        
        // 4. Completeness Score (how complete is the listing)
        $completeness = $this->calculateCompleteness($listing);
        $score += $completeness * $this->criteria['completeness'];
        
        // 5. Engagement Score (messages, inquiries, saves)
        $engagement = $listing['engagement'] ?? 0;
        $maxEngagement = $this->getMaxEngagement($division);
        if ($maxEngagement > 0) {
            $score += ($engagement / $maxEngagement) * $this->criteria['engagement'];
        }
        
        return round($score, 2);
    }
    
    /**
     * Calculate listing completeness (0-1)
     */
    private function calculateCompleteness($listing) {
        $fields = ['title', 'price', 'description', 'brand', 'city', 'state'];
        $hasImage = $this->hasImage($listing['id'], $listing['division']);
        
        $complete = 0;
        foreach ($fields as $field) {
            if (!empty($listing[$field])) {
                $complete++;
            }
        }
        
        // Add bonus for having an image
        if ($hasImage) {
            $complete += 1;
        }
        
        return min(1, $complete / count($fields));
    }
    
    /**
     * Check if listing has an image
     */
    private function hasImage($listingId, $division) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM listing_images 
                WHERE listing_id = ? AND listing_type = ? 
            ");
            $stmt->execute([$listingId, $division]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get max views for a division
     */
    private function getMaxViews($division) {
        $tableMap = [
            'car' => 'car_listings',
            'solar' => 'solar_listings',
            'property' => 'property_listings',
            'marketplace' => 'marketplace_listings'
        ];
        $table = $tableMap[$division] ?? null;
        if (!$table) return 1;
        
        try {
            $stmt = $this->db->query("SELECT MAX(views) FROM $table WHERE status = 'active'");
            return max(1, $stmt->fetchColumn());
        } catch (Exception $e) {
            return 1;
        }
    }
    
    /**
     * Get max engagement for a division
     */
    private function getMaxEngagement($division) {
        // This would query messages/inquiries count
        // For now, return 10 as a default
        return 10;
    }
    
    /**
     * Get average price for a division
     */
    private function getAveragePrice($division) {
        $tableMap = [
            'car' => 'car_listings',
            'solar' => 'solar_listings',
            'property' => 'property_listings',
            'marketplace' => 'marketplace_listings'
        ];
        $table = $tableMap[$division] ?? null;
        if (!$table) return 1;
        
        try {
            $stmt = $this->db->query("SELECT AVG(price) FROM $table WHERE status = 'active' AND price > 0");
            return max(1, $stmt->fetchColumn());
        } catch (Exception $e) {
            return 1;
        }
    }
    
    /**
     * Get featured listings across all divisions
     */
    public function getFeaturedListings($limit = 8) {
        $divisions = ['car', 'solar', 'property', 'marketplace'];
        $tableMap = [
            'car' => 'car_listings',
            'solar' => 'solar_listings',
            'property' => 'property_listings',
            'marketplace' => 'marketplace_listings'
        ];

        // Score every division's own pool separately, so a division that
        // scores lower overall (e.g. fewer views platform-wide) still
        // gets its own top performers featured — previously all four
        // divisions were pooled together and ranked as one list, so a
        // division that scored well across the board could sweep every
        // slot, leaving other divisions with nothing featured on their
        // own index page at all.
        $perDivision = [];
        foreach ($divisions as $division) {
            $table = $tableMap[$division];
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        id, title, price, views, created_at, 
                        brand, city, state, description,
                        '$division' as division,
                        '$table' as table_name
                    FROM $table 
                    WHERE status = 'active'
                    ORDER BY created_at DESC
                    LIMIT 50
                ");
                $stmt->execute();
                $listings = $stmt->fetchAll();

                foreach ($listings as &$listing) {
                    $listing['score'] = $this->calculateScore($listing, $division);
                }
                unset($listing);

                usort($listings, function($a, $b) { return $b['score'] - $a['score']; });
                $perDivision[$division] = $listings;
            } catch (Exception $e) {
                // Table might not exist or have no data
                $perDivision[$division] = [];
            }
        }

        // Fair allocation: each division gets an equal base share of the
        // slots (rounded down), then any leftover slots (from the limit
        // not dividing evenly, or a division not having enough listings
        // to fill its share) are handed out to the next-highest-scoring
        // listings across all divisions, so the total still adds up to
        // $limit when enough listings exist overall.
        $activeDivisions = array_filter($divisions, fn($d) => !empty($perDivision[$d]));
        $divisionCount = count($activeDivisions);
        $baseShare = $divisionCount > 0 ? intdiv($limit, $divisionCount) : 0;

        $result = [];
        $leftoverPool = [];
        foreach ($activeDivisions as $division) {
            $take = array_slice($perDivision[$division], 0, $baseShare);
            $result = array_merge($result, $take);
            $leftoverPool = array_merge($leftoverPool, array_slice($perDivision[$division], $baseShare));
        }

        $remaining = $limit - count($result);
        if ($remaining > 0 && !empty($leftoverPool)) {
            usort($leftoverPool, function($a, $b) { return $b['score'] - $a['score']; });
            $result = array_merge($result, array_slice($leftoverPool, 0, $remaining));
        }

        return $result;
    }
    
    /**
     * Update featured flags in the database
     */
    public function updateFeaturedListings($limit = 8) {
        // First, reset all featured flags
        $tables = ['car_listings', 'solar_listings', 'property_listings', 'marketplace_listings'];
        foreach ($tables as $table) {
            try {
                $this->db->exec("UPDATE $table SET featured = 0 WHERE status = 'active'");
            } catch (Exception $e) {
                // Table might not exist
            }
        }
        
        // Get the top listings
        $featured = $this->getFeaturedListings($limit);
        
        // Mark them as featured
        $updated = 0;
        foreach ($featured as $listing) {
            try {
                $stmt = $this->db->prepare("UPDATE {$listing['table_name']} SET featured = 1 WHERE id = ?");
                $stmt->execute([$listing['id']]);
                $updated++;
            } catch (Exception $e) {
                // Skip if error
            }
        }
        
        return $updated;
    }
}
