function showAllSupervisorsModal(supervisors) {
    const modal = document.getElementById('allSupervisorsModal');
    const modalContent = document.getElementById('allSupervisorsModalContent');
    
    // Clear previous content
    modalContent.innerHTML = '';
    
    if (supervisors.length === 0) {
        modalContent.innerHTML = '<div class="text-center text-gray-400">No supervisors assigned to this site.</div>';
    } else {
        supervisors.forEach(supervisor => {
            const supervisorEl = document.createElement('div');
            supervisorEl.className = 'flex items-center p-2 border-b border-gray-700';
            
            // Profile photo
            const photoContainer = document.createElement('div');
            photoContainer.className = 'h-10 w-10 rounded-full overflow-hidden bg-gray-600 mr-3';
            
            if (supervisor.profile_photo) {
                const img = document.createElement('img');
                img.src = supervisor.profile_photo;
                img.alt = supervisor.name;
                img.className = 'h-full w-full object-cover';
                photoContainer.appendChild(img);
            } else {
                const iconContainer = document.createElement('div');
                iconContainer.className = 'h-full w-full flex items-center justify-center bg-gray-600 text-gray-300';
                const icon = document.createElement('i');
                icon.className = 'fas fa-user-shield';
                iconContainer.appendChild(icon);
                photoContainer.appendChild(iconContainer);
            }
            
            supervisorEl.appendChild(photoContainer);
            
            // Name and details
            const infoEl = document.createElement('div');
            infoEl.innerHTML = `
                <div class="font-medium text-white">${supervisor.name}</div>
                <div class="text-sm text-gray-400">${supervisor.user_type}</div>
            `;
            supervisorEl.appendChild(infoEl);
            
            modalContent.appendChild(supervisorEl);
        });
    }
    
    // Show the modal
    modal.classList.remove('hidden');
} 