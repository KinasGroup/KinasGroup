// KINAS GROUP - Image Gallery & Viewer
class ImageGallery {
    constructor(containerSelector) {
        this.container = document.querySelector(containerSelector);
        this.images = [];
        this.currentIndex = 0;
        this.init();
    }
    
    init() {
        this.loadImages();
        this.createViewer();
        this.bindEvents();
    }
    
    loadImages() {
        const imageElements = this.container.querySelectorAll('[data-full-image]');
        this.images = Array.from(imageElements).map(img => ({
            thumbnail: img.src,
            full: img.dataset.fullImage,
            alt: img.alt
        }));
    }
    
    createViewer() {
        // Create modal viewer
        const viewer = document.createElement('div');
        viewer.className = 'image-viewer-modal';
        viewer.innerHTML = `
            <div class="viewer-overlay"></div>
            <div class="viewer-content">
                <button class="viewer-close">✕</button>
                <button class="viewer-prev">‹</button>
                <button class="viewer-next">›</button>
                <div class="viewer-image-container">
                    <img src="" alt="" class="viewer-image">
                </div>
                <div class="viewer-thumbnails"></div>
                <div class="viewer-counter"></div>
            </div>
        `;
        document.body.appendChild(viewer);
        this.viewer = viewer;
    }
    
    bindEvents() {
        // Open viewer on thumbnail click
        this.container.addEventListener('click', (e) => {
            const thumb = e.target.closest('[data-full-image]');
            if (thumb) {
                const index = Array.from(this.container.querySelectorAll('[data-full-image]'))
                    .indexOf(thumb);
                this.openViewer(index);
            }
        });
        
        // Close viewer
        this.viewer.querySelector('.viewer-close').addEventListener('click', () => this.closeViewer());
        this.viewer.querySelector('.viewer-overlay').addEventListener('click', () => this.closeViewer());
        
        // Navigation
        this.viewer.querySelector('.viewer-prev').addEventListener('click', () => this.navigate(-1));
        this.viewer.querySelector('.viewer-next').addEventListener('click', () => this.navigate(1));
        
        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (!this.viewer.classList.contains('active')) return;
            
            switch(e.key) {
                case 'Escape': this.closeViewer(); break;
                case 'ArrowLeft': this.navigate(-1); break;
                case 'ArrowRight': this.navigate(1); break;
            }
        });
        
        // Swipe support for mobile
        let touchStartX = 0;
        this.viewer.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
        });
        
        this.viewer.addEventListener('touchend', (e) => {
            const touchEndX = e.changedTouches[0].clientX;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > 50) {
                this.navigate(diff > 0 ? 1 : -1);
            }
        });
    }
    
    openViewer(index) {
        this.currentIndex = index;
        this.updateViewer();
        this.viewer.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    closeViewer() {
        this.viewer.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    navigate(direction) {
        this.currentIndex += direction;
        if (this.currentIndex < 0) this.currentIndex = this.images.length - 1;
        if (this.currentIndex >= this.images.length) this.currentIndex = 0;
        this.updateViewer();
    }
    
    updateViewer() {
        const image = this.images[this.currentIndex];
        const viewerImage = this.viewer.querySelector('.viewer-image');
        
        // Add loading state
        viewerImage.style.opacity = '0';
        viewerImage.src = image.full;
        viewerImage.alt = image.alt;
        
        viewerImage.onload = () => {
            viewerImage.style.opacity = '1';
        };
        
        // Update counter
        this.viewer.querySelector('.viewer-counter').textContent = 
            `${this.currentIndex + 1} / ${this.images.length}`;
        
        // Update thumbnails
        const thumbnailsContainer = this.viewer.querySelector('.viewer-thumbnails');
        thumbnailsContainer.innerHTML = this.images.map((img, i) => `
            <img src="${img.thumbnail}" 
                 alt="${img.alt}" 
                 class="viewer-thumb ${i === this.currentIndex ? 'active' : ''}"
                 onclick="document.querySelector('.viewer-thumbnails').parentElement.parentElement.__gallery.navigate(${i - this.currentIndex})">
        `).join('');
    }
}

// Initialize gallery on detail pages
document.addEventListener('DOMContentLoaded', () => {
    const galleryContainer = document.querySelector('.listing-gallery');
    if (galleryContainer) {
        const gallery = new ImageGallery('.listing-gallery');
        // Store gallery instance for thumbnail clicks
        gallery.viewer.__gallery = gallery;
    }
});