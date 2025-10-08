document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM content loaded - initializing map');
    
    try {
        // Debug the map element
        const mapElement = document.getElementById('sites-map');
        console.log('Map element:', mapElement);
        
        if (!mapElement) {
            console.error('Map element not found!');
            return;
        }
    
        // Default center coordinates for India
        const INDIA_CENTER = [20.5937, 78.9629];
        const DEFAULT_ZOOM = 5; // Zoom level to show all of India

        // Initialize the map with default view of India
        console.log('Creating Leaflet map with default view of India...');
        const map = L.map('sites-map', {
            center: INDIA_CENTER,
            zoom: DEFAULT_ZOOM
        });
        console.log('Map created successfully:', map);

        console.log('Adding tile layer...');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        console.log('Tile layer added');
        
        // Process site data from the data-sites attribute on the map element
        const sites = JSON.parse(mapElement.dataset.sites || '[]');
        console.log('Sites data:', sites);
        
        const markers = [];
        const bounds = L.latLngBounds();
        
        // Add markers for each site but don't automatically zoom to them
        if (sites && sites.length > 0) {
            console.log(`Processing ${sites.length} sites...`);
            
            sites.forEach((site, index) => {
                console.log(`Processing site ${index}:`, site.society_name);
                
                if (site.latitude && site.longitude) {
                    const lat = parseFloat(site.latitude);
                    const lng = parseFloat(site.longitude);
                    
                    console.log(`Site coordinates: [${lat}, ${lng}]`);
                    
                    if (!isNaN(lat) && !isNaN(lng)) {
                        const latLng = [lat, lng];
                        
                        // Create a marker with popup
                        const marker = L.marker(latLng)
                            .addTo(map)
                            .bindPopup(`
                                <div class="font-semibold">${site.society_name}</div>
                                <div class="text-sm">${site.street_address}</div>
                                <div class="text-sm">${site.city}, ${site.state}</div>
                                <div class="text-sm">PIN: ${site.pin_code}</div>
                            `);
                        
                        markers.push(marker);
                        bounds.extend(latLng);
                        console.log(`Added marker for ${site.society_name}`);
                    } else {
                        console.warn(`Invalid coordinates for site ${site.society_name}: [${site.latitude}, ${site.longitude}]`);
                    }
                } else {
                    console.warn(`Missing coordinates for site ${site.society_name}`);
                }
            });
            
            // We don't automatically zoom to bounds - keep default India view
            console.log('Keeping default India view until a site is selected');
            
            // If there are no markers but sites exist, show the message
            if (markers.length === 0 && sites.length > 0) {
                document.getElementById('sites-map').innerHTML = `
                    <div class="h-full flex items-center justify-center text-gray-400">
                        <div class="text-center">
                            <i class="fas fa-globe text-4xl mb-3 opacity-50"></i>
                            <p class="text-lg">Sites are assigned but have no valid coordinates.</p>
                            <p class="text-sm mt-2">Update the site information with valid location data.</p>
                        </div>
                    </div>
                `;
            }
        }
        
        // Debug site cards
        const siteCards = document.querySelectorAll('.site-card');
        console.log(`Found ${siteCards.length} site cards`);
        
        // Handle site card clicks
        siteCards.forEach(card => {
            card.addEventListener('click', function() {
                const lat = parseFloat(this.dataset.latitude);
                const lng = parseFloat(this.dataset.longitude);
                const index = parseInt(this.dataset.index);
                
                console.log(`Site card clicked: [${lat}, ${lng}], index: ${index}`);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    // Zoom to the selected site
                    map.setView([lat, lng], 15);
                    if (markers[index]) {
                        markers[index].openPopup();
                    }
                    
                    // Highlight active card
                    document.querySelectorAll('.site-card').forEach(c => {
                        c.classList.remove('ring-2', 'ring-blue-500');
                    });
                    this.classList.add('ring-2', 'ring-blue-500');
                }
            });
        });
        
        // View on map buttons in table view
        const viewOnMapBtns = document.querySelectorAll('.view-on-map');
        console.log(`Found ${viewOnMapBtns.length} view-on-map buttons`);
        
        viewOnMapBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const lat = parseFloat(this.dataset.latitude);
                const lng = parseFloat(this.dataset.longitude);
                
                console.log(`View on map clicked: [${lat}, ${lng}]`);
                
                if (!isNaN(lat) && !isNaN(lng)) {
                    // Switch to map view
                    showMapView();
                    
                    // Zoom to location
                    map.setView([lat, lng], 15);
                    
                    // Find and open the popup
                    markers.forEach(marker => {
                        const markerLatLng = marker.getLatLng();
                        if (markerLatLng.lat === lat && markerLatLng.lng === lng) {
                            marker.openPopup();
                        }
                    });
                }
            });
        });
        
        // Toggle between map and list views
        const mapViewBtn = document.getElementById('map-view-btn');
        const listViewBtn = document.getElementById('list-view-btn');
        const mapContainer = document.getElementById('map-container');
        const sitesListContainer = document.getElementById('sites-list-container');
        const sitesTableContainer = document.getElementById('sites-table-container');
        
        console.log('View toggle elements:', { 
            mapViewBtn, 
            listViewBtn, 
            mapContainer, 
            sitesListContainer, 
            sitesTableContainer 
        });
        
        // Simplified view toggle functions
        function showMapView() {
            console.log('Switching to map view');
            
            // Update button styles
            mapViewBtn.classList.add('bg-indigo-600');
            mapViewBtn.classList.remove('bg-gray-700');
            listViewBtn.classList.remove('bg-indigo-600');
            listViewBtn.classList.add('bg-gray-700');
            
            // Show map elements
            mapContainer.classList.remove('hidden');
            sitesListContainer.classList.remove('hidden');
            sitesTableContainer.classList.add('hidden');
            
            // Force map to redraw
            setTimeout(() => {
                console.log('Invalidating map size after timeout');
                map.invalidateSize();
            }, 100);
        }

        function showListView() {
            console.log('Switching to list view');
            
            // Update button styles
            listViewBtn.classList.add('bg-indigo-600');
            listViewBtn.classList.remove('bg-gray-700');
            mapViewBtn.classList.remove('bg-indigo-600');
            mapViewBtn.classList.add('bg-gray-700');
            
            // Show list elements
            mapContainer.classList.add('hidden');
            sitesListContainer.classList.add('hidden');
            sitesTableContainer.classList.remove('hidden');
        }

        mapViewBtn.addEventListener('click', function() {
            showMapView();
        });

        listViewBtn.addEventListener('click', function() {
            showListView();
        });

        // Initialize with map view
        console.log('Initializing with map view');
        showMapView();
        
    } catch (error) {
        console.error('Error initializing map:', error);
        
        // Display error message on the map element
        const mapElement = document.getElementById('sites-map');
        if (mapElement) {
            mapElement.innerHTML = `
                <div class="h-full flex items-center justify-center text-red-500 p-4">
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-4xl mb-2"></i>
                        <p>Error loading map: ${error.message}</p>
                        <p class="text-sm mt-2">Please check console for more details.</p>
                    </div>
                </div>
            `;
        }
    }
}); 