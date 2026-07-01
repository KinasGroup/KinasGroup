// KINAS GROUP - Admin Panel
class AdminPanel {
    constructor() {
        this.init();
    }
    
    async init() {
        await this.loadDashboardStats();
        this.setupAgentApproval();
        this.setupListingManagement();
        this.setupActivityLogs();
    }
    
    async loadDashboardStats() {
        const statsContainer = document.getElementById('admin-stats');
        if (!statsContainer) return;
        
        try {
            const stats = await api.request('admin/dashboard-stats.php');
            this.renderStats(stats, statsContainer);
        } catch (error) {
            console.error('Failed to load stats:', error);
        }
    }
    
    renderStats(stats, container) {
        container.innerHTML = `
            <div class="stat-card">
                <h3>${stats.pendingApprovals}</h3>
                <p>Pending Approvals</p>
            </div>
            <div class="stat-card">
                <h3>${stats.totalAgents}</h3>
                <p>Total Agents</p>
            </div>
            <div class="stat-card">
                <h3>${stats.activeListings}</h3>
                <p>Active Listings</p>
            </div>
            <div class="stat-card">
                <h3 class="text-red">${stats.flaggedItems}</h3>
                <p>Flagged Items</p>
            </div>
        `;
    }
    
    setupAgentApproval() {
        const approveButtons = document.querySelectorAll('.approve-agent');
        const rejectButtons = document.querySelectorAll('.reject-agent');
        
        approveButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                const agentId = btn.dataset.agentId;
                kinasConfirm('Approve this agent? They will be granted listing privileges.', async function() {
                    await api.request('admin/approve-agent.php', {
                        method: 'POST',
                        body: JSON.stringify({ agent_id: agentId })
                    });
                    location.reload();
                }, { title: 'Approve Agent', confirm: 'Approve', variant: 'gold', icon: 'fa-user-check' });
            });
        });
        
        rejectButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                const agentId = btn.dataset.agentId;
                const reason = prompt('Reason for rejection:');
                if (reason) {
                    await api.request('admin/reject-agent.php', {
                        method: 'POST',
                        body: JSON.stringify({ agent_id: agentId, reason })
                    });
                    location.reload();
                }
            });
        });
    }
    
    setupListingManagement() {
        const removeButtons = document.querySelectorAll('.remove-listing');
        const flagButtons = document.querySelectorAll('.flag-listing');
        
        removeButtons.forEach(btn => {
            btn.addEventListener('click', async () => {
                const listingId = btn.dataset.listingId;
                kinasConfirm('Remove this listing? This action cannot be undone.', async function() {
                    await api.request('admin/remove-listing.php', {
                        method: 'POST',
                        body: JSON.stringify({ listing_id: listingId })
                    });
                    btn.closest('tr').remove();
                }, { title: 'Remove Listing', confirm: 'Remove', warning: 'This is a permanent, irreversible action.' });
            });
        });
    }
    
    setupActivityLogs() {
        const logContainer = document.getElementById('activity-logs');
        if (!logContainer) return;
        
        setInterval(async () => {
            const logs = await api.request('admin/activity-log.php?limit=10');
            this.renderLogs(logs, logContainer);
        }, 30000); // Refresh every 30 seconds
    }
    
    renderLogs(logs, container) {
        container.innerHTML = logs.map(log => `
            <div class="log-entry">
                <span class="log-time">${new Date(log.created_at).toLocaleString()}</span>
                <span class="log-action">${log.action}</span>
                <span class="log-user">${log.user_name}</span>
                <span class="log-details">${log.details}</span>
            </div>
        `).join('');
    }
}

// Initialize admin panel on admin pages
if (document.querySelector('.admin-layout')) {
    new AdminPanel();
}