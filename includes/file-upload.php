<?php
// KINAS GROUP - File Upload Handler
// SECURED: Added path traversal protection and directory whitelisting
// ENHANCED: Cloudflare R2 support with automatic fallback to local storage

require_once __DIR__ . '/r2-upload.php';

class FileUpload {
    private string $uploadDir;
    private array $allowedTypes;
    private int $maxSize;
    private bool $useR2;
    private ?R2Upload $r2Uploader;
    private bool $r2FallbackToLocal;

    /** @var array Whitelist of allowed upload subdirectories - prevents path traversal */
    private const ALLOWED_SUBDIRS = [
        'cars',
        'properties',
        'products',
        'blog',
        'kyc-documents',
        'general'
    ];

    /** @var array Whitelist of allowed MIME types */
    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf'
    ];

    /** @var array Mapping MIME types to file extensions */
    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf'
    ];

    public function __construct(string $subDir = 'general') {
        // Check if R2 should be used (environment variable override)
        $this->useR2 = R2Upload::isConfigured() && (getenv('R2_ENABLED') !== 'false');
        $this->r2FallbackToLocal = getenv('R2_FALLBACK_TO_LOCAL') !== 'false';
        
        if ($this->useR2) {
            try {
                $this->r2Uploader = new R2Upload($subDir);
                if ($this->r2Uploader->isEnabled()) {
                    $this->uploadDir = ''; // Not used for R2
                    error_log('FileUpload: Using R2 storage');
                } else {
                    $this->useR2 = false;
                    error_log('FileUpload: R2 not enabled, using local storage');
                }
            } catch (RuntimeException $e) {
                // Fall back to local storage if R2 fails to initialize
                $this->useR2 = false;
                error_log('FileUpload: R2 initialization failed, using local storage: ' . $e->getMessage());
            }
        }
        
        if (!$this->useR2) {
            $this->uploadDir = $this->sanitizeAndValidatePath($subDir);
            if (!file_exists($this->uploadDir)) {
                mkdir($this->uploadDir, 0755, true);
            }
            error_log('FileUpload: Using local storage at ' . $this->uploadDir);
        }
        
        $this->allowedTypes = self::MIME_TO_EXTENSION;
        $this->maxSize = 10 * 1024 * 1024; // 10MB
    }

    /**
     * Sanitize and validate upload subdirectory path
     * Prevents path traversal attacks (e.g., ../../../etc/passwd)
     */
    private function sanitizeAndValidatePath(string $subDir): string {
        // Remove any directory separators and null bytes
        $subDir = str_replace(['/', '\\', "\0"], '', $subDir);
        $subDir = basename($subDir);

        // Whitelist validation
        if (!in_array($subDir, self::ALLOWED_SUBDIRS, true)) {
            $subDir = 'general';
        }

        $basePath = __DIR__ . '/../uploads/' . $subDir;

        // Ensure the final path is within uploads directory (realpath check)
        $realBase = realpath(__DIR__ . '/../uploads');
        $realPath = realpath($basePath);

        if ($realPath === false) {
            // Directory doesn't exist yet, check parent exists
            if (!is_dir($realBase)) {
                // Create uploads directory if it doesn't exist
                mkdir($realBase, 0755, true);
            }
            return $basePath;
        }

        // Ensure path is within uploads directory
        if (strpos($realPath, $realBase) !== 0) {
            throw new \RuntimeException('Invalid upload path');
        }

        return $realPath;
    }

    public function upload(array $file, array $options = []): array {
        // Use R2 if configured and enabled
        if ($this->useR2 && $this->r2Uploader && $this->r2Uploader->isEnabled()) {
            error_log('FileUpload: Attempting R2 upload for ' . ($file['name'] ?? 'unknown'));
            $result = $this->r2Uploader->upload($file, $options);
            
            if ($result['success']) {
                error_log('FileUpload: R2 upload SUCCESS');
                return $result;
            }
            
            error_log('FileUpload: R2 upload FAILED: ' . ($result['error'] ?? 'unknown'));
            
            // If R2 fails and fallback is enabled, try local storage
            if ($this->r2FallbackToLocal && !empty($result['fallback'])) {
                error_log('FileUpload: Falling back to local storage');
                return $this->uploadLocal($file, $options);
            }
            
            // If no fallback, return the error
            return $result;
        }
        
        // Fall back to local storage
        return $this->uploadLocal($file, $options);
    }
    
    /**
     * Original local storage upload method (preserved for backward compatibility)
     */
    private function uploadLocal(array $file, array $options = []): array {
        // Validate file
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'error' => 'No file uploaded'];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadError($file['error'])];
        }

        // Check file size
        if ($file['size'] > $this->maxSize) {
            return ['success' => false, 'error' => 'File exceeds maximum size of 10MB'];
        }

        // Verify MIME type using finfo (more reliable than relying on client-provided type)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Normalize MIME type (handle jpg vs jpeg)
        $normalizedMime = $mimeType === 'image/jpg' ? 'image/jpeg' : $mimeType;
        
        if (!array_key_exists($normalizedMime, $this->allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }

        $extension = $this->allowedTypes[$normalizedMime];

        // Additional file content validation for images
        if (strpos($normalizedMime, 'image/') === 0) {
            $imageCheck = @getimagesize($file['tmp_name']);
            if ($imageCheck === false) {
                return ['success' => false, 'error' => 'Invalid image file'];
            }
        }

        // Generate unique filename
        $filename = $this->generateFilename($extension, $options);
        $filepath = $this->uploadDir . '/' . $filename;

        // Resize image if needed
        if (strpos($normalizedMime, 'image/') === 0 && isset($options['maxWidth'])) {
            $this->resizeImage($file['tmp_name'], $filepath, $options);
        } else {
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                return ['success' => false, 'error' => 'Failed to save file'];
            }
        }

        // Generate thumbnail for images
        $thumbnail = null;
        if (strpos($normalizedMime, 'image/') === 0) {
            $thumbnail = $this->generateThumbnail($filepath, $filename);
        }

        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => '/' . str_replace($_SERVER['DOCUMENT_ROOT'], '', $filepath),
            'thumbnail' => $thumbnail,
            'mime_type' => $normalizedMime,
            'size' => $file['size']
        ];
    }

    public function uploadMultiple(array $files, array $options = []): array {
        if ($this->useR2 && $this->r2Uploader && $this->r2Uploader->isEnabled()) {
            $result = $this->r2Uploader->uploadMultiple($files, $options);
            // Check if any R2 uploads failed and fallback is enabled
            if ($this->r2FallbackToLocal) {
                foreach ($result as &$res) {
                    if (!$res['success'] && !empty($res['fallback'])) {
                        // Need to get the original file data to retry locally
                        // This is simplified - in practice you'd want to track which file failed
                    }
                }
            }
            return $result;
        }
        
        $results = [];

        // Reorganize file array
        if (!isset($files['name']) || !is_array($files['name'])) {
            return $results;
        }
        
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];

                $results[] = $this->upload($file, $options);
            }
        }

        return $results;
    }

    private function generateFilename(string $extension, array $options = []): string {
        $prefix = $options['prefix'] ?? '';
        return $prefix . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }

    private function resizeImage(string $sourcePath, string $destinationPath, array $options): void {
        $maxWidth = (int)($options['maxWidth'] ?? 1920);
        $maxHeight = (int)($options['maxHeight'] ?? 1080);
        $quality = (int)($options['quality'] ?? 85);

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new \RuntimeException('Cannot read source image');
        }

        [$width, $height] = $imageInfo;

        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio >= 1) {
            // Image is smaller than max dimensions, just copy
            copy($sourcePath, $destinationPath);
            return;
        }

        $newWidth = (int)round($width * $ratio);
        $newHeight = (int)round($height * $ratio);

        // Create resized image
        $sourceImage = $this->createImageFromFile($sourcePath);
        $destinationImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ((@getimagesize($sourcePath))[2] === IMAGETYPE_PNG) {
            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);
        }

        imagecopyresampled($destinationImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save
        $this->saveImage($destinationImage, $destinationPath, $quality);

        imagedestroy($sourceImage);
        imagedestroy($destinationImage);
    }

    private function generateThumbnail(string $filepath, string $filename): ?string {
        $thumbDir = $this->uploadDir . '/thumbnails';
        if (!file_exists($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $thumbPath = $thumbDir . '/thumb_' . $filename;

        $imageInfo = @getimagesize($filepath);
        if ($imageInfo === false) {
            return null;
        }

        [$width, $height] = $imageInfo;
        $thumbWidth = 400;
        $thumbHeight = (int)round(($thumbWidth / $width) * $height);

        $sourceImage = $this->createImageFromFile($filepath);
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

        if ((@getimagesize($filepath))[2] === IMAGETYPE_PNG) {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
        }

        imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        $this->saveImage($thumbImage, $thumbPath, 80);

        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        return 'thumb_' . $filename;
    }

    private function createImageFromFile(string $filepath) {
        $imgInfo = @getimagesize($filepath);
        $type = $imgInfo !== false ? $imgInfo[2] : false;

        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($filepath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($filepath);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($filepath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($filepath);
            default:
                throw new \RuntimeException('Unsupported image type');
        }
    }

    private function saveImage($image, string $filepath, int $quality): void {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $filepath, $quality);
                break;
            case 'png':
                $pngQuality = (int)round(($quality / 100) * 9);
                imagepng($image, $filepath, $pngQuality);
                break;
            case 'webp':
                imagewebp($image, $filepath, $quality);
                break;
            case 'gif':
                imagegif($image, $filepath);
                break;
            default:
                throw new \RuntimeException('Unsupported image format');
        }
    }

    public function delete(string $filename): bool {
        if ($this->useR2 && $this->r2Uploader && $this->r2Uploader->isEnabled()) {
            return $this->r2Uploader->delete($filename);
        }
        
        // Sanitize filename to prevent directory traversal
        $filename = basename($filename);

        $filepath = $this->uploadDir . '/' . $filename;
        $thumbPath = $this->uploadDir . '/thumbnails/thumb_' . $filename;

        $deleted = false;

        if (file_exists($filepath)) {
            unlink($filepath);
            $deleted = true;
        }

        if (file_exists($thumbPath)) {
            unlink($thumbPath);
            $deleted = true;
        }

        return $deleted;
    }

    private function getUploadError(int $code): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => '
