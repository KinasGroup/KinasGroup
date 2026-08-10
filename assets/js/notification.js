// /assets/js/notification.js
// Real-time Notification Badge for Unread Messages

const NOTIFICATION_CONFIG = {
    refreshInterval: 30000, // Check every 30 seconds
    apiEndpoint: '/api/messages/unread-count.php',
};

let notificationTimeout = null;

function getAuthToken() {
    // Try to get token from various storage methods
    return localStorage.getItem('auth_token') || 
           localStorage.getItem('jwt_token') || 
           sessionStorage.getItem('auth_token') ||
           null;
}

function updateNotificationBadge() {
    const token = getAuthToken();
    if (!token) {
        console.log('No auth token found, skipping notification check');
        return;
    }
    
    fetch(NOTIFICATION_CONFIG.apiEndpoint, {
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to fetch notifications');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const count = data.unread_count;
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                if (count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = count > 99 ? '99+' : count;
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    })
    .catch(error => {
        console.error('Error fetching notifications:', error);
        // Don't hide badge on error - keep showing last known state
    });
}

function createNotificationBadge() {
    // Check if badge already exists
    if (document.querySelector('.notification-badge')) {
        return;
    }
    
    // Find notification container
    let container = document.querySelector('.notification-container');
    
    if (!container) {
        // Create container if it doesn't exist
        container = document.createElement('div');
        container.className = 'notification-container';
        container.style.cssText = 'position: relative; display: inline-block; margin-right: 15px;';
        
        // Find where to insert it - look for user menu or header
        const userMenu = document.querySelector('.user-menu, .nav-user, .header-right, .navbar-nav');
        if (userMenu) {
            userMenu.prepend(container);
        } else {
            // Fallback: add to header
            const header = document.querySelector('header, .header, .navbar');
            if (header) {
                header.appendChild(container);
            }
        }
    }
    
    // Create icon if it doesn't exist
    if (!container.querySelector('.notification-icon')) {
        const icon = document.createElement('a');
        icon.href = '/messages.php';
        icon.className = 'notification-icon';
        icon.innerHTML = '📬';
        icon.style.cssText = 'font-size: 24px; text-decoration: none; cursor: pointer; display: inline-block; padding: 5px;';
        container.appendChild(icon);
    }
    
    // Create badge if it doesn't exist
    if (!container.querySelector('.notification-badge')) {
        const badge = document.createElement('span');
        badge.className = 'notification-badge';
        badge.style.cssText = `
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            padding: 2px 7px;
            font-size: 12px;
            font-weight: bold;
            min-width: 20px;
            text-align: center;
            border: 2px solid white;
            display: none;
            z-index: 1000;
            line-height: 1.4;
        `;
        container.appendChild(badge);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    createNotificationBadge();
    updateNotificationBadge();
    
    // Start auto-refresh
    notificationTimeout = setInterval(updateNotificationBadge, NOTIFICATION_CONFIG.refreshInterval);
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (notificationTimeout) {
        clearInterval(notificationTimeout);
        notificationTimeout = null;
    }
});

// Also check when page becomes visible again (user returns to tab)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        updateNotificationBadge();
    }
});