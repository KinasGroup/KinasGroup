// KINAS GROUP - Advanced Filtering System
class FilterSystem {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.activeFilters = {};
        this.listings = [];
        this.filteredListings = [];
        this.init();
    }
    
    init() {
        this.loadListings();
        this.bindFilterEvents();
        this.bindSortEvents();
        this.bindPagination();
        this.bindViewToggle();
    }
    
    async loadListings() {
        const params = new URLSearchParams(window.location.search);
        const division = this.container.dataset.division;
        
        try {
            const data = await api.filterListings({
                division: division,
                ...Object.fromEntries(params)
            });
            this.listings = data.listings;
            this.filteredListings = [...this.listings];
            this.render();
        } catch (error) {
            console.error('Failed to load listings:', error);
            this.showError('Failed to load listings. Please try again.');
        }
    }
    
    bindFilterEvents() {
        // Price Range
        const priceMin = document.getElementById('price-min');
        const priceMax = document.getElementById('price-max');
        if (priceMin && priceMax) {
            [priceMin, priceMax].forEach(input => {
                input.addEventListener('change', () => {
                    this.activeFilters.priceMin = priceMin.value;
                    this.activeFilters.priceMax = priceMax.value;
                    this.applyFilters();
                });
            });
        }
        
        // Category/Brand filters
        document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                const filterType = checkbox.dataset.filterType;
                if (!this.activeFilters[filterType]) {
                    this.activeFilters[filterType] = [];
                }
                
                if (checkbox.checked) {
                    this.activeFilters[filterType].push(checkbox.value);
                } else {
                    this.activeFilters[filterType] = this.activeFilters[filterType]
                        .filter(v => v !== checkbox.value);
                }
                this.applyFilters();
            });
        });
        
        // Location filter
        const locationInput = document.getElementById('location-filter');
        if (locationInput) {
            locationInput.addEventListener('input', debounce(() => {
                this.activeFilters.location = locationInput.value;
                this.applyFilters();
            }, 300));
        }
        
        // Year range (for cars)
        const yearMin = document.getElementById('year-min');
        const yearMax = document.getElementById('year-max');
        if (yearMin && yearMax) {
            [yearMin, yearMax].forEach(input => {
                input.addEventListener('change', () => {
                    this.activeFilters.yearMin = yearMin.value;
                    this.activeFilters.yearMax = yearMax.value;
                    this.applyFilters();
                });
            });
        }
        
        // Beds/Baths (for properties)
        const bedsFilter = document.getElementById('beds-filter');
        const bathsFilter = document.getElementById('baths-filter');
        if (bedsFilter) {
            bedsFilter.addEventListener('change', () => {
                this.activeFilters.beds = bedsFilter.value;
                this.applyFilters();
            });
        }
        if (bathsFilter) {
            bathsFilter.addEventListener('change', () => {
                this.activeFilters.baths = bathsFilter.value;
                this.applyFilters();
            });
        }
        
        // Clear all filters
        const clearBtn = document.getElementById('clear-filters');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                this.activeFilters = {};
                this.filteredListings = [...this.listings];
                this.resetFilterInputs();
                this.render();
            });
        }
    }
    
    applyFilters() {
        this.filteredListings = this.listings.filter(listing => {
            // Price filter
            if (this.activeFilters.priceMin && listing.price < this.activeFilters.priceMin) return false;
            if (this.activeFilters.priceMax && listing.price > this.activeFilters.priceMax) return false;
            
            // Category/Brand filter
            for (const [filterType, values] of Object.entries(this.activeFilters)) {
                if (Array.isArray(values) && values.length > 0) {
                    if (!values.includes(listing[filterType]?.toString())) return false;
                }
            }
            
            // Location filter
            if (this.activeFilters.location) {
                const location = listing.location?.toLowerCase() || '';
                if (!location.includes(this.activeFilters.location.toLowerCase())) return false;
            }
            
            // Year filter
            if (this.activeFilters.yearMin && listing.year < this.activeFilters.yearMin) return false;
            if (this.activeFilters.yearMax && listing.year > this.activeFilters.yearMax) return false;
            
            // Beds filter
            if (this.activeFilters.beds && listing.beds < this.activeFilters.beds) return false;
            
            // Baths filter
            if (this.activeFilters.baths && listing.baths < this.activeFilters.baths) return false;
            
            return true;
        });
        
        this.render();
        this.updateResultCount();
    }
    
    bindSortEvents() {
        const sortSelect = document.getElementById('sort-by');
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                const sortBy = sortSelect.value;
                this.sortListings(sortBy);
            });
        }
    }
    
    sortListings(sortBy) {
        switch(sortBy) {
            case 'price-asc':
                this.filteredListings.sort((a, b) => a.price - b.price);
                break;
            case 'price-desc':
                this.filteredListings.sort((a, b) => b.price - a.price);
                break;
            case 'newest':
                this.filteredListings.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
                break;
            case 'oldest':
                this.filteredListings.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                break;
            case 'popular':
                this.filteredListings.sort((a, b) => b.views - a.views);
                break;
            default:
                break;
        }
        this.render();
    }
    
    bindPagination() {
        this.currentPage = 1;
        this.itemsPerPage = 12;
        
        document.addEventListener('click', (e) => {
            if (e.target.matches('.pagination-btn')) {
                this.currentPage = parseInt(e.target.dataset.page);
                this.render();
                window.scrollTo({ top: this.container.offsetTop - 100, behavior: 'smooth' });
            }
        });
    }
    
    bindViewToggle() {
        const gridBtn = document.getElementById('grid-view');
        const listBtn = document.getElementById('list-view');
        
        if (gridBtn && listBtn) {
            gridBtn.addEventListener('click', () => {
                this.container.classList.remove('list-view');
                this.container.classList.add('grid-view');
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
            });
            
            listBtn.addEventListener('click', () => {
                this.container.classList.remove('grid-view');
                this.container.classList.add('list-view');
                listBtn.classList.add('active');
                gridBtn.classList.remove('active');
            });
        }
    }
    
    render() {
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const end = start + this.itemsPerPage;
        const pageItems = this.filteredListings.slice(start, end);
        
        const listingContainer = this.container.querySelector('.listings-grid');
        if (!listingContainer) return;
        
        if (pageItems.length === 0) {
            listingContainer.innerHTML = `
                <div class="no-results">
                    <p>No listings match your criteria</p>
                    <button class="je2-button" onclick="document.getElementById('clear-filters').click()">
                        Clear Filters
                    </button>
                </div>
            `;
        } else {
            listingContainer.innerHTML = pageItems.map(listing => this.renderListingCard(listing)).join('');
        }
        
        this.renderPagination();
        this.renderActiveFilters();
    }
    
    renderListingCard(listing) {
        const isFeatured = listing.featured ? '<span class="listing-card-badge">Featured</span>' : '';
        const verifiedBadge = listing.agent_verified ? '<span class="verified-badge">✓ Verified Agent</span>' : '';
        const imageUrl = listing.images?.[0] || '/assets/images/placeholder/car-placeholder.jpg';
        
        return `
            <div class="listing-card" data-id="${listing.id}">
                <div class="listing-card-image">
                    <img src="${imageUrl}" alt="${listing.title}" loading="lazy">
                    ${isFeatured}
                    <button class="favorite-btn" onclick="toggleFavorite(${listing.id})" aria-label="Save to favorites">
                        ♡
                    </button>
                </div>
                <div class="listing-card-content">
                    <p class="listing-card-title">${listing.division}</p>
                    <p class="listing-card-tags">${listing.title}</p>
                    <p class="listing-card-price">${formatPrice(listing.price)}</p>
                    <div class="listing-card-meta">
                        ${listing.year ? `<span>${listing.year}</span>` : ''}
                        ${listing.mileage ? `<span>${listing.mileage}</span>` : ''}
                        ${listing.beds ? `<span>${listing.beds} Beds</span>` : ''}
                        ${listing.baths ? `<span>${listing.baths} Baths</span>` : ''}
                    </div>
                    ${verifiedBadge}
                </div>
            </div>
        `;
    }
    
    renderPagination() {
        const totalPages = Math.ceil(this.filteredListings.length / this.itemsPerPage);
        const paginationContainer = this.container.querySelector('.pagination');
        if (!paginationContainer || totalPages <= 1) return;
        
        let pages = '';
        for (let i = 1; i <= totalPages; i++) {
            pages += `<button class="pagination-btn ${i === this.currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }
        
        paginationContainer.innerHTML = `
            <button class="pagination-btn" data-page="${Math.max(1, this.currentPage - 1)}" ${this.currentPage === 1 ? 'disabled' : ''}>← Previous</button>
            ${pages}
            <button class="pagination-btn" data-page="${Math.min(totalPages, this.currentPage + 1)}" ${this.currentPage === totalPages ? 'disabled' : ''}>Next →</button>
        `;
    }
    
    renderActiveFilters() {
        const filterTagsContainer = document.getElementById('active-filters');
        if (!filterTagsContainer) return;
        
        const tags = [];
        
        if (this.activeFilters.priceMin || this.activeFilters.priceMax) {
            tags.push(`Price: $${this.activeFilters.priceMin || 0} - $${this.activeFilters.priceMax || 'Any'}`);
        }
        
        for (const [key, value] of Object.entries(this.activeFilters)) {
            if (Array.isArray(value) && value.length > 0) {
                tags.push(`${key}: ${value.join(', ')}`);
            }
        }
        
        filterTagsContainer.innerHTML = tags.map(tag => `
            <span class="filter-tag">
                ${tag}
                <button onclick="removeFilter('${tag}')">×</button>
            </span>
        `).join('');
    }
    
    updateResultCount() {
        const countElement = document.getElementById('result-count');
        if (countElement) {
            countElement.textContent = `${this.filteredListings.length} listings found`;
        }
    }
    
    resetFilterInputs() {
        document.querySelectorAll('.filter-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('input[type="number"]').forEach(input => input.value = '');
        document.getElementById('sort-by') && (document.getElementById('sort-by').value = 'newest');
    }
    
    showError(message) {
        const listingContainer = this.container.querySelector('.listings-grid');
        if (listingContainer) {
            listingContainer.innerHTML = `
                <div class="alert alert-danger">
                    <p>${message}</p>
                    <button class="je2-button" onclick="location.reload()">Retry</button>
                </div>
            `;
        }
    }
}

// Utility: Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Utility: Format price
function formatPrice(price) {
    return '$' + Number(price).toLocaleString('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

// Utility: Toggle favorite
async function toggleFavorite(listingId) {
    try {
        await api.request(`listings/favorite.php`, {
            method: 'POST',
            body: JSON.stringify({ listing_id: listingId })
        });
        const btn = event.target;
        btn.textContent = btn.textContent === '♡' ? '♥' : '♡';
    } catch (error) {
        if (typeof showSuccessBanner === 'function') {
            showSuccessBanner('Please log in to save favorites', true);
        } else {
            console.warn('Please log in to save favorites');
        }
    }
}

// Initialize filters on listing pages
if (document.querySelector('.listings-grid')) {
    new FilterSystem('listings-container');
}