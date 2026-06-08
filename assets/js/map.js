// KINAS GROUP - Map Integration for Property Listings
class PropertyMap {
    constructor() {
        this.map = null;
        this.markers = [];
        this.init();
    }
    
    async init() {
        const mapContainer = document.getElementById('property-map');
        if (!mapContainer) return;
        
        await this.loadGoogleMaps();
        this.initializeMap(mapContainer);
        this.loadPropertyMarkers();
        this.setupSearchBounds();
    }
    
    loadGoogleMaps() {
        return new Promise((resolve, reject) => {
            if (typeof google !== 'undefined' && google.maps) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places`;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    initializeMap(container) {
        this.map = new google.maps.Map(container, {
            center: { lat: 34.0522, lng: -118.2437 }, // Los Angeles default
            zoom: 10,
            styles: this.getCustomMapStyles(),
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
        });
        
        // Search box
        const searchInput = document.getElementById('map-search');
        if (searchInput) {
            const searchBox = new google.maps.places.SearchBox(searchInput);
            this.map.controls[google.maps.ControlPosition.TOP_LEFT].push(searchInput);
            
            searchBox.addListener('places_changed', () => {
                const places = searchBox.getPlaces();
                if (places.length === 0) return;
                
                const bounds = new google.maps.LatLngBounds();
                places.forEach(place => {
                    if (place.geometry.viewport) {
                        bounds.union(place.geometry.viewport);
                    } else {
                        bounds.extend(place.geometry.location);
                    }
                });
                this.map.fitBounds(bounds);
            });
        }
    }
    
    async loadPropertyMarkers() {
        try {
            const properties = await api.getListings({ division: 'real_estate', limit: 100 });
            this.addMarkers(properties);
            this.setupMarkerClustering();
        } catch (error) {
            console.error('Failed to load property markers:', error);
        }
    }
    
    addMarkers(properties) {
        const infoWindow = new google.maps.InfoWindow();
        
        properties.forEach(property => {
            if (!property.latitude || !property.longitude) return;
            
            const marker = new google.maps.Marker({
                position: { lat: parseFloat(property.latitude), lng: parseFloat(property.longitude) },
                map: this.map,
                title: property.title,
                icon: this.getCustomMarker(property.price),
                animation: google.maps.Animation.DROP
            });
            
            // Price label on marker
            const priceLabel = new google.maps.InfoWindow({
                content: `<div class="marker-price">${formatPrice(property.price)}</div>`,
                disableAutoPan: true
            });
            
            marker.addListener('mouseover', () => {
                priceLabel.open(this.map, marker);
            });
            
            marker.addListener('mouseout', () => {
                priceLabel.close();
            });
            
            // Click to show details
            marker.addListener('click', () => {
                infoWindow.setContent(this.createInfoWindowContent(property));
                infoWindow.open(this.map, marker);
            });
            
            this.markers.push(marker);
        });
    }
    
    createInfoWindowContent(property) {
        return `
            <div class="map-info-window">
                <img src="${property.thumbnail}" alt="${property.title}" style="width:200px; height:120px; object-fit:cover; border-radius:4px;">
                <div style="padding:10px;">
                    <h3 style="margin:0 0 5px; font-size:14px;">${property.title}</h3>
                    <p style="margin:0 0 5px; font-weight:bold; color:#151515;">${formatPrice(property.price)}</p>
                    <p style="margin:0; font-size:12px; color:#717171;">
                        ${property.beds} Beds · ${property.baths} Baths · ${property.sqft} sqft
                    </p>
                    <a href="/divisions/williams-connect-home/detail.php?id=${property.id}" 
                       style="display:inline-block; margin-top:8px; color:#006c75; font-size:13px;">
                        View Details →
                    </a>
                </div>
            </div>
        `;
    }
    
    getCustomMarker(price) {
        // Create custom marker based on price range
        const color = price > 1000000 ? '#ceb687' : '#006c75';
        
        return {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: color,
            fillOpacity: 0.9,
            strokeWeight: 2,
            strokeColor: '#ffffff',
            scale: 8
        };
    }
    
    setupMarkerClustering() {
        // Use MarkerClusterer if available
        if (typeof MarkerClusterer !== 'undefined') {
            new MarkerClusterer(this.map, this.markers, {
                imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m',
                gridSize: 50,
                maxZoom: 15
            });
        }
    }
    
    setupSearchBounds() {
        this.map.addListener('idle', () => {
            const bounds = this.map.getBounds();
            const ne = bounds.getNorthEast();
            const sw = bounds.getSouthWest();
            
            // Update listings based on map bounds
            this.updateListingsByBounds({
                ne_lat: ne.lat(),
                ne_lng: ne.lng(),
                sw_lat: sw.lat(),
                sw_lng: sw.lng()
            });
        });
    }
    
    async updateListingsByBounds(bounds) {
        try {
            const listings = await api.filterListings(bounds);
            this.updateSidebarListings(listings);
        } catch (error) {
            console.error('Failed to update listings:', error);
        }
    }
    
    updateSidebarListings(listings) {
        const container = document.getElementById('map-sidebar-listings');
        if (!container) return;
        
        container.innerHTML = listings.map(listing => `
            <div class="map-listing-item" onclick="highlightMarker(${listing.id})">
                <img src="${listing.thumbnail}" alt="${listing.title}">
                <div>
                    <strong>${listing.title}</strong>
                    <span>${formatPrice(listing.price)}</span>
                </div>
            </div>
        `).join('');
    }
    
    getCustomMapStyles() {
        return [
            {
                "featureType": "water",
                "elementType": "geometry",
                "stylers": [{"color": "#e9e9e9"}, {"lightness": 17}]
            },
            {
                "featureType": "landscape",
                "elementType": "geometry",
                "stylers": [{"color": "#f5f5f5"}, {"lightness": 20}]
            },
            {
                "featureType": "road.highway",
                "elementType": "geometry.fill",
                "stylers": [{"color": "#ffffff"}, {"lightness": 17}]
            },
            {
                "featureType": "road.highway",
                "elementType": "geometry.stroke",
                "stylers": [{"color": "#ffffff"}, {"lightness": 29}, {"weight": 0.2}]
            },
            {
                "featureType": "road.arterial",
                "elementType": "geometry",
                "stylers": [{"color": "#ffffff"}, {"lightness": 18}]
            },
            {
                "featureType": "poi",
                "elementType": "geometry",
                "stylers": [{"color": "#f5f5f5"}, {"lightness": 21}]
            },
            {
                "featureType": "poi.park",
                "elementType": "geometry",
                "stylers": [{"color": "#dedede"}, {"lightness": 21}]
            },
            {
                "elementType": "labels.text.stroke",
                "stylers": [{"visibility": "on"}, {"color": "#ffffff"}, {"lightness": 16}]
            },
            {
                "elementType": "labels.text.fill",
                "stylers": [{"saturation": 36}, {"color": "#333333"}, {"lightness": 40}]
            },
            {
                "elementType": "labels.icon",
                "stylers": [{"visibility": "off"}]
            }
        ];
    }
}

// Initialize map on property pages
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('property-map')) {
        new PropertyMap();
    }
});