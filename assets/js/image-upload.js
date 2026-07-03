/**
 * KINAS GROUP - Image Upload Optimization
 * Client-side image compression for listing uploads
 * Reduces file size BEFORE upload to save R2 storage and bandwidth
 */

class ImageOptimizer {
    /**
     * @param {Object} options
     * @param {number} options.maxWidth - Maximum width (default: 1920)
     * @param {number} options.maxHeight - Maximum height (default: 1080)
     * @param {number} options.quality - JPEG quality 0-1 (default: 0.82)
     * @param {number} options.maxFileSize - Max file size in bytes (default: 20MB)
     */
    constructor(options = {}) {
        this.maxWidth = options.maxWidth || 1920;
        this.maxHeight = options.maxHeight || 1080;
        this.quality = options.quality || 0.82;
        this.maxFileSize = options.maxFileSize || 20 * 1024 * 1024;
        this.outputFormat = options.outputFormat || 'image/jpeg';
        
        // Track compression stats for logging
        this.totalOriginal = 0;
        this.totalCompressed = 0;
        this.processedCount = 0;
    }

    /**
     * Compress a single image file
     * @param {File} file - The image file to compress
     * @returns {Promise<File>} - Compressed image as a File object
     */
    async compressImage(file) {
        // Skip non-image files
        if (!file.type.startsWith('image/')) {
            return file;
        }

        // Skip HEIC files (they need server-side conversion)
        if (file.type === 'image/heic' || file.name.match(/\.heic$/i)) {
            console.log('📸 HEIC file detected - will be converted on server');
            return file;
        }

        // Get image dimensions
        let dimensions;
        try {
            dimensions = await this.getImageDimensions(file);
        } catch (e) {
            // If we can't read dimensions, return original
            console.warn('Could not read image dimensions, skipping compression:', file.name);
            return file;
        }

        // Check if we need to compress
        const needsResize = dimensions.width > this.maxWidth || dimensions.height > this.maxHeight;
        const needsCompression = file.size > 500 * 1024; // 500KB

        if (!needsResize && !needsCompression) {
            console.log(`📸 ${file.name}: Already optimized (${this.formatSize(file.size)})`);
            return file;
        }

        try {
            // Load image into canvas
            const img = await this.loadImage(file);
            
            // Calculate new dimensions
            const newDimensions = this.calculateDimensions(img.width, img.height);
            
            // Create canvas and draw resized image
            const canvas = document.createElement('canvas');
            canvas.width = newDimensions.width;
            canvas.height = newDimensions.height;
            const ctx = canvas.getContext('2d');
            
            // Use better image scaling
            ctx.imageSmoothingEnabled = true;
            ctx.imageSmoothingQuality = 'high';
            
            // Draw image with white background for JPEG (to avoid transparency issues)
            if (this.outputFormat === 'image/jpeg') {
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            }
            
            ctx.drawImage(img, 0, 0, newDimensions.width, newDimensions.height);
            
            // Convert to blob
            const blob = await new Promise(resolve => {
                canvas.toBlob(resolve, this.outputFormat, this.quality);
            });
            
            if (!blob) {
                console.warn('Failed to compress image, using original:', file.name);
                return file;
            }
            
            // Determine file extension
            const extension = this.outputFormat === 'image/jpeg' ? 'jpg' : 'png';
            
            // Create new File object with proper extension
            const newFileName = file.name.replace(/\.[^.]+$/, '.' + extension);
            const compressedFile = new File([blob], newFileName, { 
                type: this.outputFormat 
            });
            
            // Track stats
            this.totalOriginal += file.size;
            this.totalCompressed += compressedFile.size;
            this.processedCount++;
            
            // Log compression stats
            const reduction = ((1 - compressedFile.size / file.size) * 100).toFixed(0);
            console.log(`📸 ${file.name}: ${this.formatSize(file.size)} → ${this.formatSize(compressedFile.size)} (${reduction}% reduction)`);
            
            return compressedFile;
            
        } catch (error) {
            console.error('Image compression failed for', file.name, ':', error);
            return file;
        }
    }

    /**
     * Compress multiple images
     * @param {FileList|File[]} files - List of image files
     * @param {Function} onProgress - Progress callback (current, total)
     * @returns {Promise<File[]>} - Array of compressed files
     */
    async compressImages(files, onProgress = null) {
        const results = [];
        const fileArray = Array.isArray(files) ? files : Array.from(files);
        
        this.totalOriginal = 0;
        this.totalCompressed = 0;
        this.processedCount = 0;
        
        for (let i = 0; i < fileArray.length; i++) {
            const compressed = await this.compressImage(fileArray[i]);
            results.push(compressed);
            
            if (onProgress) {
                onProgress(i + 1, fileArray.length);
            }
        }
        
        // Log summary
        if (this.processedCount > 0) {
            const totalReduction = ((1 - this.totalCompressed / this.totalOriginal) * 100).toFixed(0);
            console.log(`📸 Compression summary: ${this.processedCount} images, ${this.formatSize(this.totalOriginal)} → ${this.formatSize(this.totalCompressed)} (${totalReduction}% total reduction)`);
        }
        
        return results;
    }

    /**
     * Load image from File object
     */
    loadImage(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => resolve(img);
                img.onerror = () => reject(new Error('Failed to decode image: ' + file.name));
                img.src = e.target.result;
            };
            reader.onerror = () => reject(new Error('Failed to read file: ' + file.name));
            reader.readAsDataURL(file);
        });
    }

    /**
     * Get image dimensions without loading full image
     */
    getImageDimensions(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.onload = () => {
                    resolve({ width: img.width, height: img.height });
                };
                img.onerror = () => reject(new Error('Failed to load image'));
                img.src = e.target.result;
            };
            reader.onerror = () => reject(new Error('Failed to read file'));
            reader.readAsDataURL(file);
        });
    }

    /**
     * Calculate new dimensions while maintaining aspect ratio
     */
    calculateDimensions(width, height) {
        let newWidth = width;
        let newHeight = height;
        
        if (width > this.maxWidth) {
            newWidth = this.maxWidth;
            newHeight = Math.round(height * (this.maxWidth / width));
        }
        
        if (newHeight > this.maxHeight) {
            newHeight = this.maxHeight;
            newWidth = Math.round(newWidth * (this.maxHeight / newHeight));
        }
        
        return { width: newWidth, height: newHeight };
    }

    /**
     * Format file size for display
     */
    formatSize(bytes) {
        if (bytes < 1024) return bytes + 'B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + 'KB';
        return (bytes / (1024 * 1024)).toFixed(1) + 'MB';
    }
}

// ============================================================
// AUTO-INITIALIZE FOR AGENT LISTING FORMS
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // Initialize image optimizer
    const optimizer = new ImageOptimizer({
        maxWidth: 1920,
        maxHeight: 1080,
        quality: 0.82,
        maxFileSize: 20 * 1024 * 1024,
        outputFormat: 'image/jpeg'
    });

    // ============================================================
    // AGENT ADD/EDIT LISTING - Image Upload Handler
    // ============================================================
    
    // Find all image upload inputs on the page
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    
    imageInputs.forEach(input => {
        // Check if this is a listing image upload (by ID or name)
        const isListingUpload = input.id === 'imageUpload' || 
                               input.name === 'images[]' || 
                               input.id === 'listing-images';
        
        if (!isListingUpload) return;
        
        // Store original event listeners
        const originalChange = input.onchange;
        
        // Replace with our compressed version
        input.addEventListener('change', async function(e) {
            if (!this.files || this.files.length === 0) return;
            
            const files = this.files;
            
            // Find the upload area container
            const uploadArea = this.closest('.image-upload-area');
            let loadingOverlay = null;
            
            // Show loading state
            if (uploadArea) {
                loadingOverlay = document.createElement('div');
                loadingOverlay.className = 'upload-loading-overlay';
                loadingOverlay.style.cssText = `
                    position: absolute; top: 0; left: 0; 
                    width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.5); 
                    color: white; 
                    display: flex; 
                    flex-direction: column;
                    align-items: center; 
                    justify-content: center; 
                    font-family: 'Inter', sans-serif;
                    border-radius: 16px;
                    z-index: 10;
                `;
                loadingOverlay.innerHTML = `
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 12px;"></i>
                    <span style="font-size: 14px; font-weight: 500;">Optimizing ${files.length} image${files.length > 1 ? 's' : ''}...</span>
                    <span style="font-size: 12px; opacity: 0.7; margin-top: 4px;">This may take a moment</span>
                `;
                uploadArea.style.position = 'relative';
                uploadArea.appendChild(loadingOverlay);
            }
            
            try {
                // Compress images
                const compressedFiles = await optimizer.compressImages(files, function(current, total) {
                    // Update progress
                    if (loadingOverlay) {
                        loadingOverlay.innerHTML = `
                            <i class="fas fa-spinner fa-spin" style="font-size: 32px; margin-bottom: 12px;"></i>
                            <span style="font-size: 14px; font-weight: 500;">Optimizing ${current} of ${total}...</span>
                            <span style="font-size: 12px; opacity: 0.7; margin-top: 4px;">This may take a moment</span>
                        `;
                    }
                });
                
                // Create new FileList with compressed files
                const dataTransfer = new DataTransfer();
                compressedFiles.forEach(file => dataTransfer.items.add(file));
                this.files = dataTransfer.files;
                
                // Trigger the original onchange handler if it exists
                if (typeof originalChange === 'function') {
                    originalChange.call(this, e);
                }
                
                // Trigger a change event to update previews
                this.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Show success notification via existing toast system
                const totalSize = compressedFiles.reduce((sum, f) => sum + f.size, 0);
                if (typeof kinasToast === 'function') {
                    kinasToast(`✅ ${compressedFiles.length} image${compressedFiles.length > 1 ? 's' : ''} optimized for upload`, 'success');
                } else if (typeof showNotification === 'function') {
                    showNotification(`✅ ${compressedFiles.length} image${compressedFiles.length > 1 ? 's' : ''} optimized for upload`, 'success');
                }
                
            } catch (error) {
                console.error('Compression error:', error);
                if (typeof kinasToast === 'function') {
                    kinasToast('⚠️ Image optimization failed. Using originals.', 'warning');
                } else if (typeof showNotification === 'function') {
                    showNotification('⚠️ Image optimization failed. Using originals.', 'warning');
                }
            } finally {
                // Remove loading overlay
                if (uploadArea && loadingOverlay) {
                    loadingOverlay.remove();
                }
            }
        });
    });
});

// ============================================================
// EXPORT FOR USE IN OTHER SCRIPTS
// ============================================================
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImageOptimizer;
}
