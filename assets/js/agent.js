// KINAS GROUP - Agent Dashboard Functionality
class AgentDashboard {
    constructor() {
        this.charts = {};
        this.init();
    }
    
    async init() {
        await this.loadDashboardData();
        this.initializeCharts();
        this.setupListingManager();
        this.setupMessageSystem();
        this.setupAnalytics();
    }
    
    async loadDashboardData() {
        try {
            const data = await api.request('agent/dashboard-stats.php');
            this.updateStats(data.stats);
            this.updateRecentActivity(data.recentActivity);
            this.updateListings(data.listings);
        } catch (error) {
            console.error('Failed to load dashboard:', error);
        }
    }
    
    updateStats(stats) {
        const elements = {
            totalListings: document.getElementById('total-listings'),
            activeListings: document.getElementById('active-listings'),
            totalViews: document.getElementById('total-views'),
            inquiries: document.getElementById('total-inquiries'),
            salesThisMonth: document.getElementById('sales-month'),
            revenue: document.getElementById('monthly-revenue')
        };
        
        for (const [key, element] of Object.entries(elements)) {
            if (element && stats[key] !== undefined) {
                element.textContent = stats[key];
            }
        }
    }
    
    updateRecentActivity(activities) {
        const container = document.getElementById('recent-activity');
        if (!container) return;
        
        container.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <span class="activity-icon">${this.getActivityIcon(activity.type)}</span>
                <div class="activity-content">
                    <p>${activity.description}</p>
                    <span class="activity-time">${timeAgo(activity.created_at)}</span>
                </div>
            </div>
        `).join('');
    }
    
    updateListings(listings) {
        const container = document.getElementById('my-listings');
        if (!container) return;
        
        container.innerHTML = listings.map(listing => `
            <div class="listing-card manage-card">
                <div class="listing-card-image">
                    <img src="${listing.thumbnail}" alt="${listing.title}">
                    <span class="listing-status status-${listing.status}">${listing.status}</span>
                </div>
                <div class="listing-card-content">
                    <p class="listing-card-title">${listing.title}</p>
                    <p class="listing-card-price">${formatPrice(listing.price)}</p>
                    <div class="listing-stats">
                        <span>👁 ${listing.views} views</span>
                        <span>💬 ${listing.inquiries} inquiries</span>
                    </div>
                    <div class="listing-actions">
                        <button onclick="editListing(${listing.id})" class="je2-button">Edit</button>
                        <button onclick="toggleListingStatus(${listing.id})" class="je2-button">
                            ${listing.status === 'active' ? 'Pause' : 'Activate'}
                        </button>
                        <button onclick="deleteListing(${listing.id})" class="je2-button delete">Delete</button>
                    </div>
                </div>
            </div>
        `).join('');
    }
    
    initializeCharts() {
        // Views over time chart
        const viewsCanvas = document.getElementById('views-chart');
        if (viewsCanvas) {
            this.createViewsChart(viewsCanvas);
        }
        
        // Inquiries by division chart
        const inquiriesCanvas = document.getElementById('inquiries-chart');
        if (inquiriesCanvas) {
            this.createInquiriesChart(inquiriesCanvas);
        }
    }
    
    createViewsChart(canvas) {
        // Using Chart.js if available, otherwise simple SVG chart
        if (typeof Chart !== 'undefined') {
            this.charts.views = new Chart(canvas, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [{
                        label: 'Listing Views',
                        data: [65, 59, 80, 81, 56, 55, 40],
                        borderColor: '#006c75',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }
    
    createInquiriesChart(canvas) {
        if (typeof Chart !== 'undefined') {
            this.charts.inquiries = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['Automobile', 'Real Estate', 'Solar', 'Marketplace'],
                    datasets: [{
                        data: [30, 25, 20, 25],
                        backgroundColor: ['#006c75', '#27AE60', '#8E44AD', '#ceb687']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }
    }
    
    setupListingManager() {
        // Add listing form
        const addForm = document.getElementById('add-listing-form');
        if (addForm) {
            addForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(addForm);
                
                try {
                    const response = await api.createListing(formData);
                    if (typeof showSuccessBanner === 'function') { showSuccessBanner('Listing created successfully!', false); } else { console.log('Listing created successfully!'); }
                    window.location.reload();
                } catch (error) {
                    if (typeof showSuccessBanner === 'function') { showSuccessBanner('Failed to create listing: ' + error.message, true); } else { console.error('Failed to create listing:', error.message); }
                }
            });
        }
        
        // Image upload preview
        const imageInput = document.getElementById('listing-images');
        if (imageInput) {
            imageInput.addEventListener('change', this.previewImages);
        }
    }
    
    previewImages(event) {
        const files = event.target.files;
        const previewContainer = document.getElementById('image-previews');
        if (!previewContainer) return;
        
        previewContainer.innerHTML = '';
        
        Array.from(files).forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}">
                    <button type="button" onclick="this.parentElement.remove()" class="remove-image">×</button>
                `;
                previewContainer.appendChild(preview);
            };
            reader.readAsDataURL(file);
        });
    }
    
    setupMessageSystem() {
        const messageContainer = document.getElementById('messages');
        if (!messageContainer) return;
        
        // Load conversations
        this.loadConversations();
        
        // Send message form
        const sendForm = document.getElementById('send-message-form');
        if (sendForm) {
            sendForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const message = sendForm.querySelector('textarea').value;
                const recipientId = sendForm.dataset.recipientId;
                
                try {
                    await api.sendMessage(recipientId, message);
                    sendForm.reset();
                    this.loadConversation(recipientId);
                } catch (error) {
                    if (typeof showSuccessBanner === 'function') { showSuccessBanner('Failed to send message', true); } else { console.error('Failed to send message'); }
                }
            });
        }
    }
    
    async loadConversations() {
        try {
            const conversations = await api.getInbox();
            this.renderConversations(conversations);
        } catch (error) {
            console.error('Failed to load messages:', error);
        }
    }
    
    renderConversations(conversations) {
        const container = document.getElementById('conversation-list');
        if (!container) return;
        
        container.innerHTML = conversations.map(conv => `
            <div class="conversation-item ${conv.unread ? 'unread' : ''}" 
                 onclick="loadConversation(${conv.id})">
                <div class="conv-avatar">${conv.name[0]}</div>
                <div class="conv-content">
                    <div class="conv-header">
                        <strong>${conv.name}</strong>
                        <span class="conv-time">${timeAgo(conv.last_message_time)}</span>
                    </div>
                    <p class="conv-preview">${conv.last_message}</p>
                    ${conv.unread ? '<span class="unread-badge">New</span>' : ''}
                </div>
            </div>
        `).join('');
    }
    
    setupAnalytics() {
        const dateRange = document.getElementById('analytics-date-range');
        if (dateRange) {
            dateRange.addEventListener('change', async () => {
                const range = dateRange.value;
                const analytics = await api.request(`agent/analytics.php?range=${range}`);
                this.updateAnalytics(analytics);
            });
        }
    }
    
    updateAnalytics(data) {
        // Update charts and stats with new data
        if (this.charts.views) {
            this.charts.views.data.datasets[0].data = data.viewsData;
            this.charts.views.update();
        }
    }
    
    getActivityIcon(type) {
        const icons = {
            'inquiry': '💬',
            'sale': '💰',
            'view': '👁',
            'listing': '📋',
            'verification': '✅'
        };
        return icons[type] || '📌';
    }
}

// Initialize agent dashboard
if (document.querySelector('.agent-dashboard')) {
    new AgentDashboard();
}

// Utility functions
function timeAgo(timestamp) {
    const diff = Date.now() - new Date(timestamp).getTime();
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;
    return new Date(timestamp).toLocaleDateString();
}

async function editListing(id) {
    window.location.href = `/agent/edit-listing.php?id=${id}`;
}

async function toggleListingStatus(id) {
    try {
        await api.updateListing(id, { toggle_status: true });
        window.location.reload();
    } catch (error) {
        if (typeof showSuccessBanner === 'function') { showSuccessBanner('Failed to update listing status', true); } else { console.error('Failed to update listing status'); }
    }
}

async function deleteListing(id) {
    kinasConfirm('Are you sure you want to delete this listing? This cannot be undone.', async function() {
        try {
            await api.deleteListing(id);
            window.location.reload();
        } catch (error) {
            kinasToast('Failed to delete listing', 'error');
        }
    }, { title: 'Delete Listing', warning: 'This is a permanent, irreversible action.' });
}