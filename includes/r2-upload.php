<?php
// KINAS GROUP - Cloudflare R2 Upload Handler
// Using AWS SDK for reliable S3-compatible uploads

// Check if AWS SDK is available
$awsSdkLoaded = false;
$vendorPaths = [
    __DIR__ . '/../vendor/autoload.php',
    '/var/www/html/vendor/autoload.php',
    '/app/vendor/autoload.php',
];

foreach ($vendorPaths as $vendorPath) {
    if (file_exists($vendorPath)) {
        require_once $vendorPath;
        if (class_exists('Aws\S3\S3Client')) {
            $awsSdkLoaded = true;
        }
        break;
    }
}

class R2Upload {
    private string $bucket;
    private string $accountId;
    private string $accessKey;
    private string $secretKey;
    private string $publicUrl;
    private array $allowedTypes;
    private int $maxSize;
    private $s3Client;
    private bool $useAwsSdk;
    
    /** @var array Whitelist of allowed upload subdirectories */
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
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf'
    ];
    
    /** @var array Mapping MIME types to file extensions */
    private const MIME_TO_EXTENSION = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf'
    ];
    
    public function __construct(string $subDir = 'general') {
        global $awsSdkLoaded;
        
        // Validate subdirectory
        if (!in_array($subDir, self::ALLOWED_SUBDIRS, true)) {
            $subDir = 'general';
        }
        
        // Load R2 configuration from environment
        $this->bucket = getenv('R2_BUCKET') ?: $_ENV['R2_BUCKET'] ?? '';
        $this->accountId = getenv('R2_ACCOUNT_ID') ?: $_ENV['R2_ACCOUNT_ID'] ?? '';
        $this->accessKey = getenv('R2_ACCESS_KEY_ID') ?: $_ENV['R2_ACCESS_KEY_ID'] ?? '';
        $this->secretKey = getenv('R2_SECRET_ACCESS_KEY') ?: $_ENV['R2_SECRET_ACCESS_KEY'] ?? '';
        $this->publicUrl = getenv('R2_PUBLIC_URL') ?: $_ENV['R2_PUBLIC_URL'] ?? '';
        
        // If no public URL is set, construct it from account ID and bucket
        if (empty($this->publicUrl) && !empty($this->accountId) && !empty($this->bucket)) {
            $this->publicUrl = "https://pub-{$this->accountId}.r2.dev/{$this->bucket}";
        }
        
        $this->allowedTypes = self::MIME_TO_EXTENSION;
        $this->maxSize = 10 * 1024 * 1024; // 10MB
        $this->useAwsSdk = $awsSdkLoaded && class_exists('Aws\S3\S3Client');
        
        // Initialize AWS SDK if available
        if ($this->useAwsSdk) {
            try {
                $this->s3Client = new Aws\S3\S3Client([
                    'version' => 'latest',
                    'region' => 'auto',
                    'endpoint' => "https://{$this->accountId}.r2.cloudflarestorage.com",
                    'credentials' => [
                        'key' => $this->accessKey,
                        'secret' => $this->secretKey,
                    ],
                    'use_path_style_endpoint' => false,
                ]);
                error_log('R2: AWS SDK initialized successfully');
            } catch (Exception $e) {
                $this->useAwsSdk = false;
                error_log('R2: AWS SDK init failed - ' . $e->getMessage());
            }
        } else {
            error_log('R2: AWS SDK not available, falling back to cURL');
        }
        
        // Validate configuration
        if (empty($this->bucket) || empty($this->accountId) || empty($this->accessKey) || empty($this->secretKey)) {
            throw new \RuntimeException('R2 configuration incomplete. Check environment variables.');
        }
    }
    
    /**
     * Upload file to R2
     */
    public function upload(array $file, array $options = []): array {
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
        
        // Verify MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!array_key_exists($mimeType, $this->allowedTypes)) {
            return ['success' => false, 'error' => 'Invalid file type'];
        }
        
        $extension = $this->allowedTypes[$mimeType];
        
        // Validate image if applicable
        if (strpos($mimeType, 'image/') === 0) {
            $imageCheck = @getimagesize($file['tmp_name']);
            if ($imageCheck === false) {
                return ['success' => false, 'error' => 'Invalid image file'];
            }
        }
        
        // Generate unique filename
        $subDir = $options['subDir'] ?? 'general';
        if (!in_array($subDir, self::ALLOWED_SUBDIRS, true)) {
            $subDir = 'general';
        }
        
        $filename = $this->generateFilename($extension, $options);
        $r2Key = $subDir . '/' . $filename;
        
        // Process image (resize/create thumbnail before upload)
        $processedFile = $this->processImageIfNeeded($file['tmp_name'], $options, $extension);
        
        // Upload to R2 using AWS SDK or cURL fallback
        if ($this->useAwsSdk) {
            $uploadResult = $this->uploadWithSdk($processedFile, $r2Key, $mimeType);
        } else {
            $uploadResult = $this->uploadWithCurl($processedFile, $r2Key, $mimeType);
        }
        
        if (!$uploadResult['success']) {
            return $uploadResult;
        }
        
        // Generate thumbnail for images
        $thumbnail = null;
        if (strpos($mimeType, 'image/') === 0) {
            $thumbnail = $this->generateAndUploadThumbnail($file['tmp_name'], $subDir, $filename, $options);
        }
        
        // Clean up temporary processed file if created
        if ($processedFile !== $file['tmp_name'] && file_exists($processedFile)) {
            unlink($processedFile);
        }
        
        return [
            'success' => true,
            'filename' => $filename,
            'filepath' => $uploadResult['url'],
            'thumbnail' => $thumbnail,
            'mime_type' => $mimeType,
            'size' => $file['size'],
            'key' => $r2Key
        ];
    }
    
    /**
     * Upload using AWS SDK (most reliable)
     */
    private function uploadWithSdk(string $filePath, string $key, string $mimeType): array {
        try {
            $result = $this->s3Client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => fopen($filePath, 'r'),
                'ContentType' => $mimeType,
                'ACL' => 'public-read',
            ]);
            
            // ============================================================
            // FIX: Use CDN URL (https://cdn.kinas-group.com) instead of R2 internal URL
            // ============================================================
            $publicUrlBase = rtrim($this->publicUrl, '/');
            
            // If publicUrl is empty or still the internal R2 URL, construct it
            if (empty($publicUrlBase) || strpos($publicUrlBase, 'r2.cloudflarestorage.com') !== false) {
                $publicUrlBase = "https://pub-{$this->accountId}.r2.dev/{$this->bucket}";
            }
            
            $url = "{$publicUrlBase}/{$key}";
            
            error_log("R2 Upload: URL = {$url}");
            
            return [
                'success' => true,
                'url' => $url,
                'key' => $key
            ];
        } catch (Exception $e) {
            error_log('R2 SDK Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'R2 upload failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Upload using cURL (fallback)
     */
    private function uploadWithCurl(string $filePath, string $key, string $mimeType): array {
        // Read file content
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return ['success' => false, 'error' => 'Failed to read file'];
        }
        
        $contentLength = strlen($fileContent);
        $host = "{$this->accountId}.r2.cloudflarestorage.com";
        $endpoint = "https://{$host}/{$this->bucket}/{$key}";
        
        // Try presigned URL first
        try {
            if ($this->useAwsSdk) {
                $command = $this->s3Client->getCommand('PutObject', [
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                    'ContentType' => $mimeType,
                ]);
                
                $presignedUrl = $this->s3Client->createPresignedRequest($command, '+5 minutes');
                $url = (string) $presignedUrl->getUri();
                
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_CUSTOMREQUEST => 'PUT',
                    CURLOPT_POSTFIELDS => $fileContent,
                    CURLOPT_HTTPHEADER => [
                        "Content-Type: {$mimeType}",
                        "Content-Length: {$contentLength}"
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_FAILONERROR => false,
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($curlError || ($httpCode < 200 || $httpCode >= 300)) {
                    return [
                        'success' => false,
                        'error' => "Presigned upload failed: " . ($curlError ?: "HTTP {$httpCode}")
                    ];
                }
                
                // ============================================================
                // FIX: Use CDN URL (https://cdn.kinas-group.com)
                // ============================================================
                $publicUrlBase = rtrim($this->publicUrl, '/');
                if (empty($publicUrlBase) || strpos($publicUrlBase, 'r2.cloudflarestorage.com') !== false) {
                    $publicUrlBase = "https://pub-{$this->accountId}.r2.dev/{$this->bucket}";
                }
                $finalUrl = "{$publicUrlBase}/{$key}";
                
                return [
                    'success' => true,
                    'url' => $finalUrl,
                    'key' => $key
                ];
            }
        } catch (Exception $e) {
            error_log('Presigned URL error: ' . $e->getMessage());
        }
        
        // Fallback to direct cURL with signature
        $date = gmdate('Ymd');
        $amzDate = gmdate('Ymd\THis\Z');
        $service = 's3';
        $region = 'auto';
        
        $contentHash = hash('sha256', $fileContent);
        
        $canonicalMethod = 'PUT';
        $canonicalUri = '/' . $key;
        $canonicalQueryString = '';
        $canonicalHeaders = "host:{$host}\nx-amz-content-sha256:{$contentHash}\nx-amz-date:{$amzDate}\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        
        $canonicalRequest = implode("\n", [
            $canonicalMethod,
            $canonicalUri,
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeaders,
            $contentHash
        ]);
        
        $algorithm = 'AWS4-HMAC-SHA256';
        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            $algorithm,
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);
        
        $kSecret = 'AWS4' . $this->secretKey;
        $kDate = hash_hmac('sha256', $date, $kSecret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        
        $authorization = "{$algorithm} Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $fileContent,
            CURLOPT_HTTPHEADER => [
                "Authorization: {$authorization}",
                "x-amz-content-sha256: {$contentHash}",
                "x-amz-date: {$amzDate}",
                "Content-Type: {$mimeType}",
                "Content-Length: {$contentLength}"
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FAILONERROR => false,
            CURLOPT_HEADER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $headerSize);
        curl_close($ch);
        
        if ($curlError) {
            return ['success' => false, 'error' => "cURL error: {$curlError}"];
        }
        
        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 204) {
            return ['success' => false, 'error' => "R2 upload failed (HTTP {$httpCode}): " . substr($body, 0, 200)];
        }
        
        $publicUrlBase = rtrim($this->publicUrl, '/');
        if (empty($publicUrlBase) || strpos($publicUrlBase, 'r2.cloudflarestorage.com') !== false) {
            $publicUrlBase = "https://pub-{$this->accountId}.r2.dev/{$this->bucket}";
        }
        $url = "{$publicUrlBase}/{$key}";
        
        return [
            'success' => true,
            'url' => $url,
            'key' => $key
        ];
    }
    
    /**
     * Upload multiple files to R2
     */
    public function uploadMultiple(array $files, array $options = []): array {
        $results = [];
        
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
    
    /**
     * Process image (resize) before upload
     */
    private function processImageIfNeeded(string $sourcePath, array $options, string $extension): string {
        if (!isset($options['maxWidth']) && !isset($options['maxHeight'])) {
            return $sourcePath;
        }
        
        $maxWidth = (int)($options['maxWidth'] ?? 1920);
        $maxHeight = (int)($options['maxHeight'] ?? 1080);
        $quality = (int)($options['quality'] ?? 85);
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return $sourcePath;
        }
        
        [$width, $height] = $imageInfo;
        
        // Calculate ratio
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        if ($ratio >= 1) {
            return $sourcePath;
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
        
        // Save to temporary file
        $tempFile = sys_get_temp_dir() . '/r2_resized_' . uniqid() . '.' . $extension;
        $this->saveImage($destinationImage, $tempFile, $quality);
        
        imagedestroy($sourceImage);
        imagedestroy($destinationImage);
        
        return $tempFile;
    }
    
    /**
     * Generate and upload thumbnail to R2
     */
    private function generateAndUploadThumbnail(string $sourcePath, string $subDir, string $filename, array $options): ?string {
        $thumbWidth = (int)($options['thumbWidth'] ?? 400);
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return null;
        }
        
        [$width, $height] = $imageInfo;
        $thumbHeight = (int)round(($thumbWidth / $width) * $height);
        
        $sourceImage = $this->createImageFromFile($sourcePath);
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        if ((@getimagesize($sourcePath))[2] === IMAGETYPE_PNG) {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
        }
        
        imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        
        // Save thumbnail to temporary file
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $tempFile = sys_get_temp_dir() . '/r2_thumb_' . uniqid() . '.' . $extension;
        $this->saveImage($thumbImage, $tempFile, 80);
        
        $thumbKey = $subDir . '/thumbnails/thumb_' . $filename;
        
        if ($this->useAwsSdk) {
            $uploadResult = $this->uploadWithSdk($tempFile, $thumbKey, $imageInfo['mime']);
        } else {
            $uploadResult = $this->uploadWithCurl($tempFile, $thumbKey, $imageInfo['mime']);
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
        unlink($tempFile);
        
        if ($uploadResult['success']) {
            return $uploadResult['url'];
        }
        
        return null;
    }
    
    /**
     * Create GD image from file
     */
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
    
    /**
     * Save image to file
     */
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
    
    /**
     * Generate unique filename
     */
    private function generateFilename(string $extension, array $options = []): string {
        $prefix = $options['prefix'] ?? '';
        return $prefix . uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
    
    /**
     * Delete file from R2
     */
    public function delete(string $key): bool {
        if ($this->useAwsSdk) {
            try {
                $this->s3Client->deleteObject([
                    'Bucket' => $this->bucket,
                    'Key' => $key,
                ]);
                return true;
            } catch (Exception $e) {
                error_log('R2 delete error: ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadError(int $code): string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        
        return $errors[$code] ?? 'Unknown upload error';
    }
    
    /**
     * Check if R2 is configured and available
     */
    public static function isConfigured(): bool {
        return !empty(getenv('R2_BUCKET')) || !empty($_ENV['R2_BUCKET']);
    }
    
    /**
     * Get allowed subdirectories
     */
    public static function getAllowedDirectories(): array {
        return self::ALLOWED_SUBDIRS;
    }
}
