document.addEventListener('DOMContentLoaded', function() {
    const filterType = document.getElementById('filter-type');
    const supervisorFilterContainer = document.getElementById('supervisor-filter-container');
    const siteSearchContainer = document.getElementById('site-search-container');
    const supervisorFilter = document.getElementById('supervisor-filter');
    const siteSearch = document.getElementById('site-search');
    const applyFilterBtn = document.getElementById('apply-filter');
    const siteRows = document.querySelectorAll('.site-row');
    
    // Modal elements
    const assignModal = document.getElementById('assign-modal');
    const siteNameDisplay = document.getElementById('site-name-display');
    const supervisorSelect = document.getElementById('supervisor-select');
    const addSupervisorBtn = document.getElementById('add-supervisor-btn');
    const currentSupervisorsContainer = document.getElementById('current-supervisors');
    const cancelAssignBtn = document.getElementById('cancel-assign');
    const confirmAssignBtn = document.getElementById('confirm-assign');
    
    let currentSiteId = null;
    let assignedSupervisors = [];
    
    // Filter type change handler
    filterType.addEventListener('change', function() {
        supervisorFilterContainer.classList.add('hidden');
        siteSearchContainer.classList.add('hidden');
        
        if (this.value === 'supervisor') {
            supervisorFilterContainer.classList.remove('hidden');
        } else if (this.value === 'site') {
            siteSearchContainer.classList.remove('hidden');
        }
    });
    
    // Apply filter button
    applyFilterBtn.addEventListener('click', function() {
        const type = filterType.value;
        
        // Use URL parameters for filtering to maintain state on refresh
        let url = 'index.php?page=assign-sites';
        
        if (type === 'all') {
            // No additional parameters
        } else if (type === 'assigned') {
            url += '&filter=assigned';
        } else if (type === 'unassigned') {
            url += '&filter=unassigned';
        } else if (type === 'supervisor') {
            const selectedSupervisorId = supervisorFilter.value;
            if (selectedSupervisorId) {
                url += '&filter=supervisor&supervisor_id=' + selectedSupervisorId;
            } else {
                url += '&filter=supervisor';
            }
        } else if (type === 'site') {
            const searchText = siteSearch.value;
            if (searchText) {
                url += '&filter=site&search=' + encodeURIComponent(searchText);
            } else {
                url += '&filter=site';
            }
        }
        
        window.location.href = url;
    });
    
    // Open assignment modal
    window.openAssignModal = async function(siteId, siteName) {
        currentSiteId = siteId;
        siteNameDisplay.textContent = `Site: ${siteName}`;
        assignedSupervisors = [];
        
        // Clear the supervisors container and show loading
        currentSupervisorsContainer.innerHTML = '<div class="text-gray-400 italic text-sm">Loading assigned supervisors...</div>';
        
        // Clear the supervisor select and show loading
        supervisorSelect.innerHTML = '<option value="">Loading available supervisors...</option>';
        supervisorSelect.disabled = true;
        
        // Reset the supervisor preview
        updateSupervisorPreview();
        
        // Show the modal
        assignModal.classList.remove('hidden');
        
        // Fetch data in parallel
        try {
            // Create FormData objects
            const assignedFormData = new FormData();
            assignedFormData.append('action', 'get_site_supervisors');
            assignedFormData.append('site_id', siteId);
            
            const availableFormData = new FormData();
            availableFormData.append('action', 'get_available_supervisors');
            availableFormData.append('site_id', siteId);
            
            const [assignedResponse, availableResponse] = await Promise.all([
                // Fetch current assignments for this site
                fetch('index.php', {
                    method: 'POST',
                    body: assignedFormData
                }),
                // Fetch available supervisors for this site
                fetch('index.php', {
                    method: 'POST',
                    body: availableFormData
                })
            ]);
            
            const assignedResult = await assignedResponse.json();
            const availableResult = await availableResponse.json();
            
            // Process assigned supervisors
            if (assignedResult.success) {
                assignedSupervisors = assignedResult.supervisors || [];
                renderAssignedSupervisors();
            } else {
                showError('Error loading assigned supervisors: ' + (assignedResult.error || 'Unknown error'));
            }
            
            // Process available supervisors
            if (availableResult.success) {
                renderAvailableSupervisors(availableResult.supervisors || []);
            } else {
                supervisorSelect.innerHTML = '<option value="">Error loading supervisors</option>';
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Failed to load data');
            supervisorSelect.innerHTML = '<option value="">Error loading supervisors</option>';
        } finally {
            supervisorSelect.disabled = false;
        }
    };
    
    // Render available supervisors in the dropdown
    function renderAvailableSupervisors(supervisors) {
        // Clear the dropdown
        supervisorSelect.innerHTML = '';
        
        // Add the default option
        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        
        if (supervisors.length === 0) {
            defaultOption.textContent = 'No unassigned supervisors available';
            supervisorSelect.appendChild(defaultOption);
            supervisorSelect.disabled = true;
            addSupervisorBtn.disabled = true;
            addSupervisorBtn.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }
        
        defaultOption.textContent = 'Select a supervisor';
        supervisorSelect.appendChild(defaultOption);
        supervisorSelect.disabled = false;
        addSupervisorBtn.disabled = false;
        addSupervisorBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        
        // Add each supervisor as an option
        supervisors.forEach(supervisor => {
            const option = document.createElement('option');
            option.value = supervisor.id;
            option.textContent = `${supervisor.name} (${supervisor.user_type})`;
            option.dataset.name = supervisor.name;
            option.dataset.type = supervisor.user_type;
            option.dataset.photo = supervisor.profile_photo || '';
            supervisorSelect.appendChild(option);
        });
    }
    
    // Render the list of assigned supervisors
    function renderAssignedSupervisors() {
        if (assignedSupervisors.length === 0) {
            currentSupervisorsContainer.innerHTML = '<div class="text-gray-400 italic">No supervisors assigned yet</div>';
            return;
        }
        
        currentSupervisorsContainer.innerHTML = '';
        
        assignedSupervisors.forEach(supervisor => {
            const supervisorEl = document.createElement('div');
            supervisorEl.className = 'flex items-center justify-between bg-gray-700 p-2 rounded';
            
            // Supervisor info
            const infoEl = document.createElement('div');
            infoEl.className = 'flex items-center';
            
            // Photo
            const photoContainer = document.createElement('div');
            photoContainer.className = 'h-8 w-8 rounded-full overflow-hidden bg-gray-600 flex-shrink-0 mr-2';
            
            if (supervisor.profile_photo) {
                const img = document.createElement('img');
                img.src = supervisor.profile_photo;
                img.alt = supervisor.name;
                img.className = 'h-full w-full object-cover';
                photoContainer.appendChild(img);
            } else {
                const iconContainer = document.createElement('div');
                iconContainer.className = 'h-full w-full flex items-center justify-center text-gray-400';
                const icon = document.createElement('i');
                icon.className = 'fas fa-user';
                iconContainer.appendChild(icon);
                photoContainer.appendChild(iconContainer);
            }
            
            infoEl.appendChild(photoContainer);
            
            // Name and type
            const nameEl = document.createElement('div');
            nameEl.textContent = `${supervisor.name} (${supervisor.user_type})`;
            nameEl.className = 'text-white text-sm';
            infoEl.appendChild(nameEl);
            
            supervisorEl.appendChild(infoEl);
            
            // Remove button
            const removeBtn = document.createElement('button');
            removeBtn.className = 'text-red-400 hover:text-red-300';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.addEventListener('click', () => removeSupervisor(supervisor.id));
            supervisorEl.appendChild(removeBtn);
            
            currentSupervisorsContainer.appendChild(supervisorEl);
        });
    }
    
    // Remove a supervisor from the assigned list
    function removeSupervisor(supervisorId) {
        // Find the supervisor to remove
        const removedSupervisor = assignedSupervisors.find(s => s.id === supervisorId);
        
        // Remove from the assigned list
        assignedSupervisors = assignedSupervisors.filter(s => s.id !== supervisorId);
        renderAssignedSupervisors();
        
        // Re-enable the supervisor in the dropdown
        if (removedSupervisor) {
            const options = Array.from(supervisorSelect.options);
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === supervisorId) {
                    options[i].disabled = false;
                    options[i].style.display = '';
                    break;
                }
            }
        }
    }
    
    // Add supervisor button click handler
    addSupervisorBtn.addEventListener('click', function() {
        const supervisorId = supervisorSelect.value;
        if (!supervisorId) {
            alert('Please select a supervisor first.');
            return;
        }
        
        // Check if already assigned (in the current session)
        if (assignedSupervisors.some(s => s.id === supervisorId)) {
            alert('This supervisor is already assigned to this site.');
            supervisorSelect.value = '';
            updateSupervisorPreview();
            return;
        }
        
        const option = supervisorSelect.options[supervisorSelect.selectedIndex];
        const supervisor = {
            id: supervisorId,
            name: option.dataset.name,
            user_type: option.dataset.type,
            profile_photo: option.dataset.photo || null
        };
        
        assignedSupervisors.push(supervisor);
        renderAssignedSupervisors();
        
        // Disable this option in the dropdown
        option.disabled = true;
        option.style.display = 'none';
        
        // Reset select
        supervisorSelect.value = '';
        updateSupervisorPreview();
    });
    
    // Show supervisor preview when selecting from dropdown
    supervisorSelect.addEventListener('change', updateSupervisorPreview);
    
    function updateSupervisorPreview() {
        const preview = document.getElementById('selected-supervisor-preview');
        const photoContainer = document.getElementById('supervisor-photo');
        const nameContainer = document.getElementById('supervisor-name');
        
        if (supervisorSelect.value) {
            const selectedOption = supervisorSelect.options[supervisorSelect.selectedIndex];
            const photoUrl = selectedOption.dataset.photo;
            const supervisorName = selectedOption.textContent;
            
            nameContainer.textContent = supervisorName;
            
            // Update photo
            if (photoUrl) {
                photoContainer.innerHTML = `<img src="${photoUrl}" alt="Supervisor" class="h-full w-full object-cover">`;
            } else {
                photoContainer.innerHTML = `<div class="h-full w-full flex items-center justify-center text-gray-400"><i class="fas fa-user"></i></div>`;
            }
            
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
    }
    
    // Show error message
    function showError(message) {
        currentSupervisorsContainer.innerHTML = `
            <div class="text-red-400 bg-red-900/30 p-2 rounded text-sm">
                <i class="fas fa-exclamation-circle mr-1"></i> ${message}
            </div>
        `;
    }
    
    // Cancel assignment
    cancelAssignBtn.addEventListener('click', function() {
        assignModal.classList.add('hidden');
        currentSiteId = null;
        assignedSupervisors = [];
    });
    
    // Confirm assignment
    confirmAssignBtn.addEventListener('click', async function() {
        if (currentSiteId === null) return;
        
        const formData = new FormData();
        formData.append('action', 'assign_supervisors');
        formData.append('site_id', currentSiteId);
        formData.append('supervisor_ids', JSON.stringify(assignedSupervisors.map(s => s.id)));
        
        try {
            const response = await fetch('index.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Reload page to show updated assignments
                window.location.reload();
            } else {
                alert('Error: ' + (result.error || 'Unknown error occurred'));
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while updating the assignments');
        }
        
        assignModal.classList.add('hidden');
    });

    // All Supervisors Modal elements
    const allSupervisorsModal = document.getElementById('all-supervisors-modal');
    const allSupervisorsSiteName = document.getElementById('all-supervisors-site-name');
    const allSupervisorsList = document.getElementById('all-supervisors-list');
    const closeAllSupervisorsBtn = document.getElementById('close-all-supervisors');
    const manageSupervisorsBtn = document.getElementById('manage-supervisors-btn');
    let currentViewingSiteId = null;
    
    // View all supervisors click handler
    document.querySelectorAll('.view-all-supervisors').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            
            const siteId = this.dataset.siteId;
            const siteName = this.dataset.siteName;
            currentViewingSiteId = siteId;
            
            allSupervisorsSiteName.textContent = `Site: ${siteName}`;
            allSupervisorsList.innerHTML = '<div class="text-gray-400 italic text-sm">Loading supervisors...</div>';
            
            allSupervisorsModal.classList.remove('hidden');
            
            // Fetch supervisors for this site
            try {
                const formData = new FormData();
                formData.append('action', 'get_site_supervisors');
                formData.append('site_id', siteId);
                
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    renderAllSupervisors(result.supervisors || []);
                } else {
                    allSupervisorsList.innerHTML = `
                        <div class="text-red-400 bg-red-900/30 p-3 rounded">
                            <i class="fas fa-exclamation-circle mr-1"></i> 
                            Error loading supervisors: ${result.error || 'Unknown error'}
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error:', error);
                allSupervisorsList.innerHTML = `
                    <div class="text-red-400 bg-red-900/30 p-3 rounded">
                        <i class="fas fa-exclamation-circle mr-1"></i> 
                        Failed to load supervisors
                    </div>
                `;
            }
        });
    });
    
    // Render all supervisors in the modal
    function renderAllSupervisors(supervisors) {
        if (supervisors.length === 0) {
            allSupervisorsList.innerHTML = '<div class="text-gray-400 italic">No supervisors assigned</div>';
            return;
        }
        
        allSupervisorsList.innerHTML = '';
        
        supervisors.forEach(supervisor => {
            const supervisorEl = document.createElement('div');
            supervisorEl.className = 'flex items-center bg-gray-700 p-3 rounded';
            
            // Photo
            const photoContainer = document.createElement('div');
            photoContainer.className = 'h-10 w-10 rounded-full overflow-hidden bg-gray-600 flex-shrink-0 mr-3';
            
            if (supervisor.profile_photo) {
                const img = document.createElement('img');
                img.src = supervisor.profile_photo;
                img.alt = supervisor.name;
                img.className = 'h-full w-full object-cover';
                photoContainer.appendChild(img);
            } else {
                const iconContainer = document.createElement('div');
                iconContainer.className = 'h-full w-full flex items-center justify-center text-gray-400';
                const icon = document.createElement('i');
                icon.className = 'fas fa-user';
                iconContainer.appendChild(icon);
                photoContainer.appendChild(iconContainer);
            }
            
            supervisorEl.appendChild(photoContainer);
            
            // Info
            const infoEl = document.createElement('div');
            
            const nameEl = document.createElement('div');
            nameEl.textContent = supervisor.name;
            nameEl.className = 'text-white';
            infoEl.appendChild(nameEl);
            
            const typeEl = document.createElement('div');
            typeEl.textContent = supervisor.user_type;
            typeEl.className = 'text-gray-400 text-sm';
            infoEl.appendChild(typeEl);
            
            supervisorEl.appendChild(infoEl);
            allSupervisorsList.appendChild(supervisorEl);
        });
    }
    
    // Close all supervisors modal
    closeAllSupervisorsBtn.addEventListener('click', function() {
        allSupervisorsModal.classList.add('hidden');
        currentViewingSiteId = null;
    });
    
    // Manage assignments button
    manageSupervisorsBtn.addEventListener('click', function() {
        if (currentViewingSiteId) {
            const siteName = allSupervisorsSiteName.textContent.replace('Site: ', '');
            
            // Close the all supervisors modal
            allSupervisorsModal.classList.add('hidden');
            
            // Open the assignment modal
            openAssignModal(currentViewingSiteId, siteName);
        }
    });
}); 