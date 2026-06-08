<!-- Image Viewer Modal -->
<div id="image-viewer-modal" class="admin-modal" style="display: none; z-index: 2000;">
    <div class="viewer-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.95);"></div>
    
    <div class="viewer-content" style="position: relative; z-index: 1; width: 100%; height: 100%; display: flex; flex-direction: column;">
        <!-- Top Bar -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; color: white;">
            <span id="viewer-counter" style="font-size: 14px;">1 / 5</span>
            <div style="display: flex; gap: 15px;">
                <button onclick="toggleFullscreen()" style="background: none; border: none; color: white; cursor: pointer; font-size: 20px;" title="Fullscreen">
                    ⛶
                </button>
                <button onclick="closeImageViewer()" style="background: none; border: none; color: white; cursor: pointer; font-size: 28px;" title="Close">
                    ✕
                </button>
            </div>
        </div>
        
        <!-- Main Image Area -->
        <div style="flex: 1; display: flex; align-items: center; justify-content: center; position: relative;">
            <!-- Navigation Arrows -->
            <button class="viewer-nav viewer-prev" 
                    onclick="navigateImage(-1)"
                    style="position: absolute; left: 20px; background: rgba(255,255,255,0.2); border: none; color: white; width: 50px; height: 50px; border-radius: 50%; font-size: 24px; cursor: pointer; z-index: 2; transition: background 0.2s; display: flex; align-items: center; justify-content: center;">
                ‹
            </button>
            
            <button class="viewer-nav viewer-next" 
                    onclick="navigateImage(1)"
                    style="position: absolute; right: 20px; background: rgba(255,255,255,0.2); border: none; color: white; width: 50px; height: 50px; border-radius: 50%; font-size: 24px; cursor: pointer; z-index: 2; transition: background 0.2s; display: flex; align-items: center; justify-content: center;">
                ›
            </button>
            
            <!-- Image Container -->
            <div id="viewer-image-container" style="max-width: 90%; max-height: 80vh; display: flex; align-items: center; justify-content: center;">
                <img id="viewer-main-image" src="" alt="" style="max-width: 100%; max-height: 80vh; object-fit: contain; transition: opacity 0.3s;">
            </div>
        </div>
        
        <!-- Thumbnail Strip -->
        <div id="viewer-thumbnail-strip" style="display: flex; justify-content: center; gap: 10px; padding: 15px; overflow-x: auto;">
            <!-- Thumbnails inserted dynamically -->
        </div>
    </div>
</div>

<script>
let viewerImages = [];
let viewerCurrentIndex = 0;

function openImageViewer(images, startIndex = 0) {
    viewerImages = images;
    viewerCurrentIndex = startIndex;
    
    document.getElementById('image-viewer-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    updateViewerImage();
    renderViewerThumbnails();
}

function closeImageViewer() {
    document.getElementById('image-viewer-modal').style.display = 'none';
    document.body.style.overflow = '';
    
    // Exit fullscreen if active
    if (document.fullscreenElement) {
        document.exitFullscreen();
    }
}

function navigateImage(direction) {
    viewerCurrentIndex += direction;
    
    if (viewerCurrentIndex < 0) {
        viewerCurrentIndex = viewerImages.length - 1;
    } else if (viewerCurrentIndex >= viewerImages.length) {
        viewerCurrentIndex = 0;
    }
    
    updateViewerImage();
    updateActiveThumbnail();
}

function updateViewerImage() {
    const img = document.getElementById('viewer-main-image');
    const counter = document.getElementById('viewer-counter');
    
    if (viewerImages.length > 0 && viewerImages[viewerCurrentIndex]) {
        // Fade out
        img.style.opacity = '0';
        
        setTimeout(() => {
            img.src = viewerImages[viewerCurrentIndex].full || viewerImages[viewerCurrentIndex].url;
            img.alt = viewerImages[viewerCurrentIndex].alt || 'Listing image';
            
            // Fade in
            img.onload = () => {
                img.style.opacity = '1';
            };
        }, 150);
        
        counter.textContent = (viewerCurrentIndex + 1) + ' / ' + viewerImages.length;
    }
}

function renderViewerThumbnails() {
    const strip = document.getElementById('viewer-thumbnail-strip');
    
    if (viewerImages.length <= 1) {
        strip.innerHTML = '';
        strip.style.display = 'none';
        return;
    }
    
    strip.style.display = 'flex';
    strip.innerHTML = viewerImages.map((img, index) => `
        <div onclick="viewerCurrentIndex = ${index}; updateViewerImage(); updateActiveThumbnail();" 
             style="flex-shrink: 0; width: 60px; height: 60px; border-radius: 6px; overflow: hidden; cursor: pointer; border: 2px solid ${index === viewerCurrentIndex ? 'white' : 'transparent'}; opacity: ${index === viewerCurrentIndex ? '1' : '0.5'}; transition: all 0.2s;"
             onmouseover="this.style.opacity='1'"
             onmouseout="if(${index} !== viewerCurrentIndex) this.style.opacity='0.5'">
            <img src="${img.thumbnail || img.url}" 
                 alt="Thumbnail ${index + 1}"
                 style="width: 100%; height: 100%; object-fit: cover;">
        </div>
    `).join('');
}

function updateActiveThumbnail() {
    const thumbs = document.querySelectorAll('#viewer-thumbnail-strip > div');
    thumbs.forEach((thumb, index) => {
        thumb.style.borderColor = index === viewerCurrentIndex ? 'white' : 'transparent';
        thumb.style.opacity = index === viewerCurrentIndex ? '1' : '0.5';
    });
}

function toggleFullscreen() {
    const modal = document.getElementById('image-viewer-modal');
    
    if (!document.fullscreenElement) {
        if (modal.requestFullscreen) {
            modal.requestFullscreen();
        } else if (modal.webkitRequestFullscreen) {
            modal.webkitRequestFullscreen();
        } else if (modal.msRequestFullscreen) {
            modal.msRequestFullscreen();
        }
    } else {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        }
    }
}

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    const modal = document.getElementById('image-viewer-modal');
    if (modal.style.display !== 'flex') return;
    
    switch(e.key) {
        case 'Escape':
            closeImageViewer();
            break;
        case 'ArrowLeft':
            navigateImage(-1);
            break;
        case 'ArrowRight':
            navigateImage(1);
            break;
        case 'f':
            toggleFullscreen();
            break;
    }
});

// Touch/swipe support
let touchStartX = 0;
let touchEndX = 0;

document.getElementById('image-viewer-modal')?.addEventListener('touchstart', function(e) {
    touchStartX = e.changedTouches[0].screenX;
});

document.getElementById('image-viewer-modal')?.addEventListener('touchend', function(e) {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
});

function handleSwipe() {
    const swipeThreshold = 50;
    const diff = touchStartX - touchEndX;
    
    if (Math.abs(diff) > swipeThreshold) {
        if (diff > 0) {
            navigateImage(1); // Swipe left
        } else {
            navigateImage(-1); // Swipe right
        }
    }
}

// Close when clicking overlay (but not the image)
document.getElementById('image-viewer-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeImageViewer();
    }
});

// Mouse wheel zoom
let currentZoom = 1;
document.getElementById('viewer-image-container')?.addEventListener('wheel', function(e) {
    e.preventDefault();
    
    if (e.deltaY < 0 && currentZoom < 3) {
        currentZoom += 0.2;
    } else if (e.deltaY > 0 && currentZoom > 0.5) {
        currentZoom -= 0.2;
    }
    
    document.getElementById('viewer-main-image').style.transform = `scale(${currentZoom})`;
});
</script>

<style>
.viewer-nav:hover {
    background: rgba(255,255,255,0.4) !important;
}

#viewer-main-image {
    transition: opacity 0.3s ease, transform 0.2s ease;
    cursor: zoom-in;
}

#viewer-main-image:hover {
    cursor: zoom-out;
}

@media (max-width: 768px) {
    .viewer-nav {
        width: 40px !important;
        height: 40px !important;
        font-size: 20px !important;
    }
    
    #viewer-thumbnail-strip {
        gap: 5px;
        padding: 10px;
    }
    
    #viewer-thumbnail-strip > div {
        width: 45px !important;
        height: 45px !important;
    }
}
</style>