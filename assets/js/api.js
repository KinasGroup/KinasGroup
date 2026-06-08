// KINAS GROUP - API Handler
class KinasAPI {
    constructor(baseURL = '/api') {
        this.baseURL = baseURL;
        this.token = localStorage.getItem('kinas_token');
    }
    
    async request(endpoint, options = {}) {
        const url = `${this.baseURL}/${endpoint}`;
        const headers = {
            'Content-Type': 'application/json',
            ...(this.token && { 'Authorization': `Bearer ${this.token}` })
        };
        
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    ...headers,
                    ...options.headers
                }
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'API request failed');
            }
            
            return data;
        } catch (error) {
            console.error(`API Error [${endpoint}]:`, error);
            throw error;
        }
    }
    
    // Auth API Calls
    async login(email, password) {
        const data = await this.request('auth/login.php', {
            method: 'POST',
            body: JSON.stringify({ email, password })
        });
        this.token = data.token;
        localStorage.setItem('kinas_token', data.token);
        return data;
    }
    
    async register(userData) {
        return await this.request('auth/register.php', {
            method: 'POST',
            body: JSON.stringify(userData)
        });
    }
    
    async logout() {
        localStorage.removeItem('kinas_token');
        this.token = null;
        window.location.href = '/';
    }
    
    // Listings API
    async getListings(params = {}) {
        const query = new URLSearchParams(params).toString();
        return await this.request(`listings/list.php?${query}`);
    }
    
    async getListing(id) {
        return await this.request(`listings/get.php?id=${id}`);
    }
    
    async createListing(listingData) {
        return await this.request('listings/create.php', {
            method: 'POST',
            body: JSON.stringify(listingData)
        });
    }
    
    async updateListing(id, listingData) {
        return await this.request(`listings/update.php?id=${id}`, {
            method: 'PUT',
            body: JSON.stringify(listingData)
        });
    }
    
    async deleteListing(id) {
        return await this.request(`listings/delete.php?id=${id}`, {
            method: 'DELETE'
        });
    }
    
    // Search & Filter
    async searchListings(query) {
        return await this.request(`listings/search.php?q=${encodeURIComponent(query)}`);
    }
    
    async filterListings(filters) {
        return await this.request('listings/filter.php', {
            method: 'POST',
            body: JSON.stringify(filters)
        });
    }
    
    // Agent API
    async verifyAgent(agentId, documents) {
        const formData = new FormData();
        Object.keys(documents).forEach(key => {
            formData.append(key, documents[key]);
        });
        
        return await this.request(`agent/upload-kyc.php?agent_id=${agentId}`, {
            method: 'POST',
            headers: {}, // Let browser set multipart/form-data
            body: formData
        });
    }
    
    // Messages
    async sendMessage(recipientId, message) {
        return await this.request('messages/send.php', {
            method: 'POST',
            body: JSON.stringify({ recipient_id: recipientId, message })
        });
    }
    
    async getInbox() {
        return await this.request('messages/inbox.php');
    }
}

// Initialize global API instance
const api = new KinasAPI();