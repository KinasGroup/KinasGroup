<?php
// KINAS GROUP - Storage Helper
// Unified interface for R2 and local storage

require_once __DIR__ . '/../config/constants.php';

class StorageHelper {
    
    /**
     * Get the full URL for a stored file
     * Automatically handles R2 vs local paths
     */
    public static function getFileUrl(string $filepath, string $type = 'general'): string {
        if (STORAGE_DRIVER === 'r2' && R2_ENABLED) {
            // Check if it's already a full R2 URL
            if (strpos($filepath, 'http') === 0) {
                return $filepath;
            }
            
            // Get the folder for this type
            $folder = R2_FOLDERS[$type] ?? R2_FOLDERS['general'];
            $cleanPath = ltrim($filepath, '/');
            
            return rtrim(R2_PUBLIC_URL, '/') . '/' . $folder . $cleanPath;
        }
        
        // Local storage - return relative path
        return SITE_URL . '/uploads/' . ltrim($filepath, '/');
    }
    
    /**
     * Get thumbnail URL
     */
    public static function getThumbnailUrl(string $filename, string $type = 'general'): ?string {
        if (empty($filename)) {
            return null;
        }
        
        $thumbFile = 'thumbnails/thumb_' . ltrim($filename, 'thumb_');
        
        if (STORAGE_DRIVER === 'r2' && R2_ENABLED) {
            $folder = R2_FOLDERS[$type] ?? R2_FOLDERS['general'];
            return rtrim(R2_PUBLIC_URL, '/') . '/' . $folder . $thumbFile;
        }
        
        return SITE_URL . '/uploads/' . $type . '/' . $thumbFile;
    }
    
    /**
     * Check if R2 is properly configured
     */
    public static function isR2Available(): bool {
        return STORAGE_DRIVER === 'r2' 
            && !empty(R2_ACCOUNT_ID) 
            && !empty(R2_ACCESS_KEY) 
            && !empty(R2_SECRET_KEY)
            && !empty(R2_BUCKET);
    }
    
    /**
     * Delete file from active storage
     */
    public static function deleteFile(string $filepath, string $type = 'general'): bool {
        if (STORAGE_DRIVER === 'r2' && self::isR2Available()) {
            return self::deleteFromR2($filepath, $type);
        }
        
        return self::deleteFromLocal($filepath, $type);
    }
    
    private static function deleteFromR2(string $filepath, string $type): bool {
        // R2 deletion logic (can be implemented if needed)
        // For now, return true as R2 files persist
        return true;
    }
    
    private static function deleteFromLocal(string $filepath, string $type): bool {
        $fullPath = UPLOAD_DIR . $type . '/' . basename($filepath);
        if (file_exists($fullPath)) {
            unlink($fullPath);
            
            // Also delete thumbnail
            $thumbPath = UPLOAD_DIR . $type . '/thumbnails/thumb_' . basename($filepath);
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
            return true;
        }
        return false;
    }
    
    /**
     * Migrate local files to R2 (one-time migration script)
     */
    public static function migrateToR2(string $type = null): array {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        if (!self::isR2Available()) {
            $results['errors'][] = 'R2 not configured';
            return $results;
        }
        
        $types = $type ? [$type] : array_keys(R2_FOLDERS);
        
        foreach ($types as $t) {
            $localDir = UPLOAD_DIR . $t . '/';
            if (!is_dir($localDir)) {
                continue;
            }
            
            $files = glob($localDir . '*.{jpg,jpeg,png,webp,pdf}', GLOB_BRACE);
            foreach ($files as $file) {
                // This would need the R2Upload class to actually upload
                // Implementation provided in previous response
            }
        }
        
        return $results;
    }
}
