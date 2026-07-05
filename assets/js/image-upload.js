/**
 * KINAS GROUP - Image Upload Optimization
 * Client-side image compression for listing uploads
 * Reduces file size BEFORE upload to save R2 storage and bandwidth
 *
 * BUILD MARKER: kinas-image-upload-v2 (event-based handoff, no self
 * re-dispatch). If a duplicate-image bug is seen again, check the browser
 * console for this line on page load — if it's missing or shows an older
 * marker, the browser/CDN is serving a stale cached copy of this file, not
 * running this code.
 */
console.log('%c[image-upload.js] kinas-image-upload-v2 loaded', 'color:#C6A43F');

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

            // ── Re-entrancy guard ─────────────────────────────────────
            // BUG FIX: this handler used to finish by doing
            //   this.files = <compressed files>;
            //   this.dispatchEvent(new Event('change', { bubbles: true }));
            // to let the page's own "add to selectedFiles" listener pick up
            // the compressed files. But dispatching a 'change' event on the
            // SAME input re-invokes every 'change' listener on it — including
            // this very handler — which would compress again and dispatch
            // again, forever. Each pass also made the page's listener append
            // the files to its array *again* (it appends, it never replaces),
            // so a single selection could balloon into 100+ duplicates in a
            // fraction of a second. The guard below makes sure this handler
            // can never re-enter itself while a run is already in flight.
            if (this.dataset.kinasProcessing === '1') {
                console.warn('[image-upload.js] Ignored a duplicate/overlapping change event while a previous selection was still being compressed.');
                return;
            }
            this.dataset.kinasProcessing = '1';
            
            const files = this.files;

            // Compression is an internal implementation detail (saves R2
            // storage/bandwidth) — intentionally silent. No loading overlay,
            // no "optimizing..." message, no success/failure toast. The
            // person uploading just sees their photo appear.
            try {
                // Compress images
                const compressedFiles = await optimizer.compressImages(files);
                
                // Create new FileList with compressed files
                const dataTransfer = new DataTransfer();
                compressedFiles.forEach(file => dataTransfer.items.add(file));
                this.files = dataTransfer.files;
                
                // Call the original onchange handler if the page assigned one
                // via input.onchange = fn (older pattern). This does NOT
                // re-trigger this listener, unlike dispatching 'change' did.
                if (typeof originalChange === 'function') {
                    originalChange.call(this, e);
                }
                
                // Tell the page the compressed files are ready. Pages should
                // listen for THIS event (not 'change') to add files to their
                // preview/selection state — see add-listing.php / edit-listing.php.
                // Using a distinct event type (rather than re-dispatching
                // 'change') means this handler can never be re-entered by its
                // own completion signal.
                this.dispatchEvent(new CustomEvent('kinas:images-ready', {
                    bubbles: true,
                    detail: { files: compressedFiles }
                }));
                
            } catch (error) {
                console.error('Compression error:', error);
                // Fall back to the original, uncompressed files silently —
                // the upload should never be blocked by an optimization
                // step failing. The page still needs to know about the
                // (uncompressed) files it selected, or nothing gets added.
                this.dispatchEvent(new CustomEvent('kinas:images-ready', {
                    bubbles: true,
                    detail: { files: Array.from(files) }
                }));
            } finally {
                this.dataset.kinasProcessing = '0';
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
