// KINAS GROUP - Main JavaScript
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileMenu();
    initializeDropdowns();
    initializeSmoothScroll();
    initializeLazyLoading();
    initializeStickyHeader();
    initializeSearchAutocomplete();
});

// Mobile Menu Toggle
function initializeMobileMenu() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const nav = document.querySelector('.header-nav');
    
    if (menuBtn && nav) {
        menuBtn.addEventListener('click', () => {
            nav.classList.toggle('active');
            menuBtn.textContent = nav.classList.contains('active') ? '✕' : '☰';
        });
    }
}

// Smooth Scroll
function initializeSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
}

// Lazy Loading Images
function initializeLazyLoading() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

// Sticky Header
function initializeStickyHeader() {
    const header = document.querySelector('.je3-header');
    let lastScroll = 0;
    
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 100) {
            header.classList.add('sticky-shadow');
        } else {
            header.classList.remove('sticky-shadow');
        }
        lastScroll = currentScroll;
    });
}

// Search Autocomplete
function initializeSearchAutocomplete() {
    const searchInputs = document.querySelectorAll('.search-autocomplete');
    
    searchInputs.forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                timeout = setTimeout(() => {
                    fetchSearchSuggestions(query, input);
                }, 300);
            }
        });
    });
}

async function fetchSearchSuggestions(query, input) {
    try {
        const response = await fetch(`/api/listings/search.php?q=${encodeURIComponent(query)}&limit=5`);
        const data = await response.json();
        showSuggestions(data, input);
    } catch (error) {
        console.error('Search error:', error);
    }
}

function showSuggestions(results, input) {
    let dropdown = input.parentElement.querySelector('.search-dropdown');
    
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.className = 'search-dropdown';
        input.parentElement.appendChild(dropdown);
    }
    
    if (results.length === 0) {
        dropdown.innerHTML = '<div class="search-item">No results found</div>';
    } else {
        dropdown.innerHTML = results.map(item => `
            <a href="${item.url}" class="search-item">
                <img src="${item.thumbnail}" alt="${item.title}" width="40">
                <div>
                    <strong>${item.title}</strong>
                    <span>${item.price}</span>
                </div>
            </a>
        `).join('');
    }
    
    dropdown.style.display = 'block';
    
    document.addEventListener('click', function closeDropdown(e) {
        if (!input.parentElement.contains(e.target)) {
            dropdown.style.display = 'none';
            document.removeEventListener('click', closeDropdown);
        }
    });
}

// Form Validation
function validateForm(formElement) {
    const inputs = formElement.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        const errorMsg = input.parentElement.querySelector('.error-message');
        
        if (!input.value.trim()) {
            isValid = false;
            input.classList.add('error');
            if (errorMsg) errorMsg.textContent = 'This field is required';
        } else {
            input.classList.remove('error');
            if (errorMsg) errorMsg.textContent = '';
        }
        
        if (input.type === 'email' && input.value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) {
                isValid = false;
                input.classList.add('error');
                if (errorMsg) errorMsg.textContent = 'Invalid email address';
            }
        }
    });
    
    return isValid;
}