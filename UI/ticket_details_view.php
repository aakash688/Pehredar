<?php
// UI/ticket_details_view.php
?>
<div id="ticket-details-container" class="max-w-7xl mx-auto">
    <!-- Loading Spinner -->
    <div id="loader" class="text-center py-20">
        <i class="fas fa-spinner fa-spin fa-3x text-indigo-400"></i>
        <p class="mt-4 text-white">Loading Ticket Details...</p>
    </div>
    <!-- Content will be loaded here by JS -->
</div>

<style>
    /* Minimalistic scrollbar for timeline */
    .custom-scrollbar::-webkit-scrollbar {
        width: 5px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #1f2937; /* bg-gray-800 */
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #4f46e5; /* bg-indigo-600 */
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #4338ca; /* bg-indigo-700 */
    }
    
    /* Enhanced UI styles */
    .ticket-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
    }
    
    .ticket-card.priority-low {
        border-left-color: #34D399;
    }
    
    .ticket-card.priority-medium {
        border-left-color: #FBBF24;
    }
    
    .ticket-card.priority-high {
        border-left-color: #F87171;
    }
    
    .ticket-card.priority-critical {
        border-left-color: #DC2626;
    }
    
    .hover-scale {
        transition: transform 0.2s ease;
    }
    
    .hover-scale:hover {
        transform: scale(1.02);
    }
    
    .comment-box {
        transition: all 0.3s ease;
    }
    
    .comment-box:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    
    .ticket-heading {
        position: relative;
        display: inline-block;
    }
    
    .ticket-heading::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(to right, #4f46e5, #818cf8);
        border-radius: 3px;
    }
    
    .timeline-item-dot {
        box-shadow: 0 0 0 5px rgba(79, 70, 229, 0.2);
        transition: all 0.3s ease;
    }
    
    .timeline-item:hover .timeline-item-dot {
        box-shadow: 0 0 0 8px rgba(79, 70, 229, 0.3);
    }
    
    .pulsing-dot {
        animation: pulse-animation 2s infinite;
    }
    
    @keyframes pulse-animation {
        0% { box-shadow: 0 0 0 0px rgba(79, 70, 229, 0.4); }
        100% { box-shadow: 0 0 0 15px rgba(79, 70, 229, 0); }
    }

    /* Attachment Modal Styles */
    #attachment-modal {
        transition: opacity 0.3s ease;
    }

    #attachment-modal-content {
        transition: transform 0.3s ease;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const ticketId = urlParams.get('id');

    if (ticketId) {
        fetchTicketDetails(ticketId);
    } else {
        document.getElementById('ticket-details-container').innerHTML = '<div class="bg-gray-800 p-6 rounded-lg text-red-400">Error: No Ticket ID provided.</div>';
    }
});

function fetchTicketDetails(ticketId) {
    fetch(`index.php?action=get_ticket_details&id=${ticketId}`)
        .then(response => response.json())
        .then(data => {
            const container = document.getElementById('ticket-details-container');
            if (data.success) {
                renderTicketDetails(container, data.ticket, data.is_admin_viewer);
                attachEventListeners(data.ticket.id, data.ticket);
            } else {
                container.innerHTML = `<div class="bg-gray-800 p-6 rounded-lg text-red-400">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error fetching ticket details:', error);
            const container = document.getElementById('ticket-details-container');
            container.innerHTML = `<div class="bg-gray-800 p-6 rounded-lg text-red-400">Failed to load ticket details. Please try again.</div>`;
        });
}

function renderTicketDetails(container, ticket, isAdmin) {
    container.innerHTML = `
        <div class="flex justify-between items-center mb-6">
            <h1 class="ticket-heading text-3xl font-bold text-white">Ticket #${'TKT-' + ticket.id.toString().padStart(6, '0')}</h1>
            <a href="index.php?page=ticket-list" class="flex items-center space-x-1 text-indigo-400 hover:text-indigo-300 transition duration-300">
                <i class="fas fa-arrow-left text-sm"></i>
                <span>Back to Ticket List</span>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Ticket Info -->
            <div class="lg:col-span-2 bg-gray-800 p-6 rounded-lg shadow-lg ticket-card priority-${ticket.priority.toLowerCase()}">
                <div class="flex justify-between items-start">
                    <h2 class="text-xl font-semibold text-white mb-4 border-b border-gray-700 pb-3 flex-grow">${escapeHTML(ticket.title)}</h2>
                    ${ticket.attachments && ticket.attachments.length > 0 ? `
                        <button class="view-attachments-btn ml-4 flex-shrink-0" data-type="ticket">
                            <i class="fas fa-paperclip text-indigo-400"></i>
                            <span class="text-xs ml-1 text-gray-400">${ticket.attachments.length}</span>
                        </button>
                    ` : ''}
                </div>
                <div class="text-gray-300 prose max-w-none bg-gray-750 p-4 rounded-lg shadow-inner">
                   <p>${escapeHTML(ticket.description).replace(/\\n/g, '<br>')}</p>
                </div>

                <!-- Aggregated Attachments Section -->
                ${renderAllAttachmentsSection(ticket)}

                <!-- Comments Section -->
                ${renderCommentsSection(ticket)}
            </div>

            <!-- Sidebar Info -->
            <div class="bg-gray-800 p-6 rounded-lg shadow-lg self-start hover-scale">
                <h3 class="text-lg font-semibold text-white mb-4">Ticket Details</h3>
                <ul class="space-y-4 text-sm">
                    <li class="flex justify-between items-center p-2 hover:bg-gray-750 rounded-lg transition duration-300">
                        <span class="font-semibold text-gray-400">Status:</span>
                        <span id="ticket-status-badge" class="px-3 py-1 rounded-full text-xs font-bold ${getStatusClass(ticket.status)}">${escapeHTML(ticket.status)}</span>
                    </li>
                    <li class="flex justify-between items-center p-2 hover:bg-gray-750 rounded-lg transition duration-300">
                        <span class="font-semibold text-gray-400">Priority:</span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold" style="background-color: ${getPriorityBgColor(ticket.priority)}; color: ${getPriorityTextColor(ticket.priority)}">${escapeHTML(ticket.priority)}</span>
                    </li>
                    <li class="flex justify-between items-center p-2 hover:bg-gray-750 rounded-lg transition duration-300">
                        <span class="font-semibold text-gray-400">Society:</span>
                        <span class="text-gray-200">${escapeHTML(ticket.society_name)}</span>
                    </li>
                    <li class="flex justify-between items-center p-2 hover:bg-gray-750 rounded-lg transition duration-300">
                        <span class="font-semibold text-gray-400">Created By:</span>
                        <span class="text-gray-200">${escapeHTML(ticket.creator_name)}</span>
                    </li>
                     <li class="flex justify-between items-center p-2 hover:bg-gray-750 rounded-lg transition duration-300">
                        <span class="font-semibold text-gray-400">Created At:</span>
                        <span class="text-gray-200">${new Date(ticket.created_at).toLocaleString()}</span>
                    </li>
                    <li class="flex justify-between items-center p-2 hover:bg-gray-750 rounded-lg transition duration-300">
                        <span class="font-semibold text-gray-400">Last Updated:</span>
                        <span class="text-gray-200">${new Date(ticket.updated_at).toLocaleString()}</span>
                    </li>
                </ul>
                
                ${isAdmin ? renderAdminControls(ticket) : ''}

                <!-- Timeline -->
                ${renderTimeline(ticket.history)}
            </div>
        </div>

        <!-- Attachment Modal -->
        <div id="attachment-modal" class="fixed inset-0 bg-black bg-opacity-70 z-50 items-center justify-center hidden opacity-0" onclick="closeAttachmentModal()">
            <div id="attachment-modal-content" class="bg-gray-800 rounded-lg shadow-2xl w-full max-w-3xl p-6 transform scale-95" onclick="event.stopPropagation()">
                <div class="flex justify-between items-center border-b border-gray-700 pb-3 mb-4">
                    <h3 id="attachment-modal-title" class="text-xl font-semibold text-white"></h3>
                    <button onclick="closeAttachmentModal()" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times fa-lg"></i>
                    </button>
                </div>
                <div class="max-h-[80vh] overflow-y-auto custom-scrollbar pr-2">
                    <div id="attachment-modal-text" class="text-gray-300 prose max-w-none bg-gray-900/50 p-4 rounded-lg shadow-inner mb-4"></div>
                    <div id="attachment-modal-gallery" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
                </div>
            </div>
        </div>
    `;
}

// Build an aggregated attachments gallery from ticket description and all comments
function renderAllAttachmentsSection(ticket) {
    const aggregated = aggregateAttachments(ticket);
    if (!aggregated || aggregated.length === 0) {
        return '';
    }

    const thumbnails = aggregated.map(att => `
        <a href="${escapeHTML(att.file_path)}" target="_blank" class="block group hover-scale">
            <div class="relative overflow-hidden rounded-lg">
                <img src="${escapeHTML(att.file_path)}" alt="${escapeHTML(att.file_name || 'attachment')}" class="w-full h-28 object-cover rounded-lg transition transform group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-center">
                    <p class="text-xs text-center p-1 text-white truncate">${escapeHTML(att.file_name || '')}</p>
                </div>
            </div>
        </a>
    `).join('');

    return `
        <div id="all-attachments" class="mt-6 pt-4 border-t border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center">
                    <h3 class="text-lg font-semibold text-white">All Attachments</h3>
                    <span class="ml-2 px-2 py-1 bg-indigo-600 text-xs rounded-full text-white">${aggregated.length}</span>
                </div>
                <button id="view-all-attachments-btn" class="text-indigo-400 hover:text-indigo-300 transition text-sm">
                    <i class="fas fa-images mr-1"></i> View Larger
                </button>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">${thumbnails}</div>
        </div>
    `;
}

function aggregateAttachments(ticket) {
    const result = [];
    if (ticket && Array.isArray(ticket.attachments)) {
        ticket.attachments.forEach(a => a && a.file_path && result.push(a));
    }
    if (ticket && Array.isArray(ticket.comments)) {
        ticket.comments.forEach(c => {
            if (c && Array.isArray(c.attachments)) {
                c.attachments.forEach(a => a && a.file_path && result.push(a));
            }
        });
    }
    return result;
}

function renderCommentsSection(ticket) {
    const comments = ticket.comments;
    let commentsHTML = `
        <div id="comments-section" class="mt-8 pt-6 border-t border-gray-700">
            <div class="flex items-center mb-4">
                <h3 class="text-lg font-semibold text-white">Comments</h3>
                <span class="ml-2 px-2 py-1 bg-indigo-600 text-xs rounded-full text-white">${comments?.length || 0}</span>
            </div>
            <div id="comments-list" class="space-y-4 mb-6 max-h-80 overflow-y-auto custom-scrollbar pr-2">
    `;
    if (!comments || comments.length === 0) {
        commentsHTML += '<div class="bg-gray-750 p-4 rounded-lg text-gray-400 text-center">No comments yet. Be the first to comment!</div>';
    } else {
        comments.slice().reverse().forEach((comment, index) => {
            const reversedIndex = comments.length - 1 - index;
            commentsHTML += `
                <div class="flex items-start space-x-3 comment-box hover:bg-gray-750 p-2 rounded-lg">
                    <img class="h-10 w-10 rounded-full object-cover border-2 border-indigo-500" src="${escapeHTML(comment.user_avatar) || 'https://i.pravatar.cc/50'}" alt="avatar">
                    <div class="flex-1 bg-gray-700 p-3 rounded-lg">
                        <div class="flex justify-between items-center mb-1">
                            <p class="font-semibold text-white text-sm">${escapeHTML(comment.user_name)}</p>
                            <div class="flex items-center">
                                <p class="text-xs text-gray-500 mr-4">${new Date(comment.created_at).toLocaleString()}</p>
                                ${comment.attachments && comment.attachments.length > 0 ? `
                                    <button class="view-attachments-btn" data-type="comment" data-comment-index="${reversedIndex}">
                                        <i class="fas fa-paperclip text-indigo-400"></i>
                                        <span class="text-xs ml-1 text-gray-400">${comment.attachments.length}</span>
                                    </button>
                                ` : ''}
                            </div>
                        </div>
                        <p class="text-gray-300 text-sm py-2">${escapeHTML(comment.comment)}</p>
                    </div>
                </div>
            `;
        });
    }
    commentsHTML += `
            </div>
            ${ticket.status === 'Closed' ? `
                <div class="mt-4 bg-gray-750 p-4 rounded-lg text-center text-gray-400">
                    <i class="fas fa-lock mr-2"></i> Commenting is disabled as this ticket is closed.
                </div>
            ` : `
                <form id="add-comment-form" class="mt-4 bg-gray-750 p-4 rounded-lg shadow-md" enctype="multipart/form-data">
                    <textarea name="comment" class="bg-gray-900 text-white w-full p-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-300" rows="3" placeholder="Add a comment..." required></textarea>
                    
                    <div class="mt-4 p-4 border border-dashed border-gray-600 rounded-lg bg-gray-800/50 transition-all duration-300 hover:bg-gray-800">
                        <div class="flex flex-col items-center justify-center">
                            <i class="fas fa-cloud-upload-alt text-3xl text-indigo-400 mb-2"></i>
                            <label for="comment-attachments" class="block text-sm font-medium text-gray-300 mb-2 cursor-pointer">
                                <span class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 rounded-full text-white transition">
                                    <i class="fas fa-paperclip mr-1"></i> Attach Files
                                </span>
                            </label>
                            <p class="text-xs text-gray-400 text-center">Drop files here or click to browse</p>
                            <input id="comment-attachments" type="file" name="attachments[]" multiple accept="image/*" class="comment-attachments-input hidden">
                        </div>
                        <div class="comment-file-preview-container mt-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
                    </div>

                    <div class="flex justify-between items-center mt-4">
                        <div class="file-counter text-sm text-gray-400"></div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg transition duration-300 flex items-center shadow-lg">
                            <i class="fas fa-paper-plane mr-2"></i> Add Comment
                        </button>
                    </div>
                </form>
            `}
        </div>
    `;
    return commentsHTML;
}

function renderTimeline(history) {
    if (!history || history.length === 0) return '';
    let timelineHTML = '<div class="mt-6 pt-4 border-t border-gray-700"><div class="flex items-center mb-4"><h3 class="text-lg font-semibold text-white">Ticket History</h3><span class="ml-2 px-2 py-1 bg-indigo-600 text-xs rounded-full text-white">' + history.length + '</span></div><div class="custom-scrollbar relative border-l-2 border-gray-700 max-h-96 overflow-y-auto pl-1">';
    history.forEach((item, index) => {
        const isFirst = index === 0;
        timelineHTML += `
        <div class="mb-4 ml-6 relative timeline-item">
            <span class="absolute flex items-center justify-center w-6 h-6 bg-indigo-600 rounded-full -left-3 ring-8 ring-gray-800 timeline-item-dot ${isFirst ? 'pulsing-dot' : ''}">
                <i class="fas ${item.icon} text-white text-xs"></i>
            </span>
            <div class="p-3 bg-gray-700 rounded-lg shadow-sm hover:bg-gray-650 transition-all duration-300">
                <p class="text-sm text-gray-300">${item.activity}</p>
                <time class="text-xs font-normal text-gray-500">${new Date(item.timestamp).toLocaleString()}</time>
            </div>
        </div>
        `;
    });
    timelineHTML += '</div></div>';
    return timelineHTML;
}

function renderAdminControls(ticket) {
    const statuses = ['Open', 'In Progress', 'On Hold', 'Closed'];
    let options = statuses.map(s => `<option value="${s}" ${ticket.status === s ? 'selected' : ''}>${s}</option>`).join('');
    
    return `
        <div id="admin-controls" class="mt-6 pt-4 border-t border-gray-700">
            <h3 class="text-lg font-semibold text-white mb-3">Admin Controls</h3>
            <form id="update-status-form" class="bg-gray-750 p-4 rounded-lg">
                <label for="status-select" class="block text-sm font-medium text-gray-400 mb-2">
                    <i class="fas fa-tasks mr-1"></i> Update Status
                </label>
                <div class="flex space-x-2">
                    <select id="status-select" name="status" class="bg-gray-700 text-white w-full p-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 transition duration-300">
                        ${options}
                    </select>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg transition duration-300">
                        <i class="fas fa-save"></i>
                    </button>
                </div>
            </form>
        </div>
    `;
}

function attachEventListeners(ticketId, ticketData) {
    const container = document.getElementById('ticket-details-container');
    
    // Store selected files for the comment form
    let selectedFiles = [];
    
    container.addEventListener('submit', function(e) {
        e.preventDefault();

        if (e.target.id === 'add-comment-form') {
            handleCommentSubmission(e.target, ticketId, selectedFiles);
        } else if (e.target.id === 'update-status-form') {
            handleStatusUpdate(e.target, ticketId);
        }
    });

    container.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.view-attachments-btn');
        if (viewBtn) {
            const type = viewBtn.dataset.type;
            if (type === 'ticket') {
                openAttachmentModal('Ticket Description', ticketData.description, ticketData.attachments);
            } else if (type === 'comment') {
                const commentIndex = parseInt(viewBtn.dataset.commentIndex, 10);
                const comment = ticketData.comments[commentIndex];
                openAttachmentModal(`Comment by ${comment.user_name}`, comment.comment, comment.attachments);
            }
        }
        const viewAll = e.target.closest('#view-all-attachments-btn');
        if (viewAll) {
            const all = aggregateAttachments(ticketData);
            openAttachmentModal('All Attachments', '', all);
        }
    });

    // Setup drag and drop for file upload area
    container.addEventListener('dragover', function(e) {
        const dropArea = e.target.closest('.border-dashed');
        if (dropArea) {
            e.preventDefault();
            dropArea.classList.add('border-indigo-400', 'bg-gray-750');
        }
    });

    container.addEventListener('dragleave', function(e) {
        const dropArea = e.target.closest('.border-dashed');
        if (dropArea) {
            e.preventDefault();
            dropArea.classList.remove('border-indigo-400', 'bg-gray-750');
        }
    });

    container.addEventListener('drop', function(e) {
        const dropArea = e.target.closest('.border-dashed');
        if (dropArea) {
            e.preventDefault();
            dropArea.classList.remove('border-indigo-400', 'bg-gray-750');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = container.querySelector('.comment-attachments-input');
                handleFileSelection(fileInput, files);
            }
        }
    });

    // Add event listener for comment attachment previews
    container.addEventListener('change', function(e) {
        if (e.target.classList.contains('comment-attachments-input')) {
            handleFileSelection(e.target, e.target.files);
        }
    });

    // Helper function to handle file selection
    function handleFileSelection(fileInput, files) {
        const previewContainer = container.querySelector('.comment-file-preview-container');
        const fileCounter = container.querySelector('.file-counter');
        
        // Convert FileList to Array and store
        selectedFiles = Array.from(files);
        
        // Clear previews
        previewContainer.innerHTML = '';
        
        if (selectedFiles.length > 0) {
            // Update file counter
            fileCounter.textContent = `${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''} selected`;
            
            // Generate previews
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = function(e_reader) {
                    const previewWrapper = document.createElement('div');
                    previewWrapper.className = 'relative group hover-scale';
                    
                    const img = document.createElement('img');
                    img.src = e_reader.target.result;
                    img.className = 'w-full h-24 object-cover rounded-lg shadow-md';
                    
                    const overlay = document.createElement('div');
                    overlay.className = 'absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center';
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:bg-red-600 transition z-10';
                    removeBtn.innerHTML = '<i class="fas fa-times text-xs"></i>';
                    removeBtn.type = 'button';
                    removeBtn.addEventListener('click', function() {
                        // Remove file from array
                        selectedFiles.splice(index, 1);
                        
                        // Update file counter
                        fileCounter.textContent = selectedFiles.length > 0 
                            ? `${selectedFiles.length} file${selectedFiles.length > 1 ? 's' : ''} selected` 
                            : '';
                        
                        // Remove preview
                        previewWrapper.remove();
                        
                        // Re-create the FileList
                        updateFileInput();
                    });
                    
                    const fileName = document.createElement('div');
                    fileName.className = 'absolute bottom-0 left-0 right-0 bg-black/50 text-white text-xs p-1 truncate text-center';
                    fileName.textContent = file.name;
                    
                    previewWrapper.appendChild(img);
                    previewWrapper.appendChild(overlay);
                    previewWrapper.appendChild(removeBtn);
                    previewWrapper.appendChild(fileName);
                    previewContainer.appendChild(previewWrapper);
                }
                reader.readAsDataURL(file);
            });
        } else {
            fileCounter.textContent = '';
        }
    }
    
    // Function to update file input with selected files
    function updateFileInput() {
        const fileInput = container.querySelector('.comment-attachments-input');
        
        // Create a new DataTransfer object
        const dataTransfer = new DataTransfer();
        
        // Add selected files to DataTransfer
        selectedFiles.forEach(file => {
            dataTransfer.items.add(file);
        });
        
        // Update the file input
        fileInput.files = dataTransfer.files;
    }
}

function handleCommentSubmission(form, ticketId, selectedFiles) {
    const formData = new FormData(form);
    formData.append('ticket_id', ticketId);
    formData.append('action', 'add_ticket_comment');
    
    // Ensure attachments are included properly
    if (selectedFiles && selectedFiles.length > 0) {
        // Remove any existing attachment entries
        formData.delete('attachments[]');
        
        // Add each file individually
        selectedFiles.forEach(file => {
            formData.append('attachments[]', file);
        });
    }

    // Simple feedback, can be improved
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Posting...';

    fetch('index.php?action=add_ticket_comment', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            // Show success message
            showToast('Comment added successfully!', 'success');
            
            // Reset form
            form.reset();
            form.querySelector('.comment-file-preview-container').innerHTML = '';
            form.querySelector('.file-counter').textContent = '';
            
            // Refresh the whole ticket view to show the new comment
            fetchTicketDetails(ticketId); 
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('An unexpected error occurred.', 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalText;
    });
}

function handleStatusUpdate(form, ticketId) {
    const formData = new FormData(form);
    formData.append('ticket_id', ticketId);
    formData.append('action', 'update_ticket_status');
    
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    
    // Show visual feedback
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    fetch('index.php?action=update_ticket_status', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            const newStatus = formData.get('status');
            const badge = document.getElementById('ticket-status-badge');
            badge.textContent = newStatus;
            badge.className = `px-3 py-1 rounded-full text-xs font-bold ${getStatusClass(newStatus)}`;
            
            // Show success message
            showToast('Status updated successfully!', 'success');
        } else {
            showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('An unexpected error occurred.', 'error');
    })
    .finally(() => {
        button.disabled = false;
        button.innerHTML = originalContent;
    });
}

// Toast notification system
function showToast(message, type = 'info') {
    // Remove any existing toasts
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'fixed top-4 right-4 z-50';
        document.body.appendChild(toastContainer);
    }
    
    // Set background color based on type
    let bgColor = 'bg-blue-500';
    let icon = 'fa-info-circle';
    
    if (type === 'success') {
        bgColor = 'bg-green-500';
        icon = 'fa-check-circle';
    } else if (type === 'error') {
        bgColor = 'bg-red-500';
        icon = 'fa-exclamation-circle';
    } else if (type === 'warning') {
        bgColor = 'bg-yellow-500';
        icon = 'fa-exclamation-triangle';
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast-notification ${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center justify-between mb-3 transition-all duration-300 transform translate-x-full`;
    toast.innerHTML = `
        <div class="flex items-center">
            <i class="fas ${icon} mr-2"></i>
            <span>${message}</span>
        </div>
        <button class="ml-4 text-white focus:outline-none">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-x-full');
    }, 10);
    
    // Set up close button
    const closeButton = toast.querySelector('button');
    closeButton.addEventListener('click', () => {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            toast.remove();
        }, 300);
    });
    
    // Auto dismiss after 5 seconds
    setTimeout(() => {
        if (toast.parentElement) {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Helper functions (could be moved to a global script)
function getStatusClass(status) {
    switch(status) {
        case 'Open': return 'bg-green-600 text-green-100';
        case 'In Progress': return 'bg-yellow-600 text-yellow-100';
        case 'Closed': return 'bg-red-600 text-red-100';
        case 'On Hold': return 'bg-gray-500 text-gray-100';
        default: return 'bg-gray-600 text-gray-100';
    }
}

function getPriorityBgColor(priority) {
    switch(priority) {
        case 'Low': return '#065f46'; // Dark green
        case 'Medium': return '#92400e'; // Dark amber
        case 'High': return '#991b1b'; // Dark red
        case 'Critical': return '#7f1d1d'; // Darker red
        default: return '#1f2937'; // Dark gray
    }
}

function getPriorityTextColor(priority) {
    switch(priority) {
        case 'Low': return '#34D399'; // Light green
        case 'Medium': return '#FBBF24'; // Light amber
        case 'High': return '#F87171'; // Light red
        case 'Critical': return '#FCA5A5'; // Lighter red
        default: return '#9CA3AF'; // Light gray
    }
}

function escapeHTML(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[match];
    });
}

function openAttachmentModal(title, text, attachments) {
    const modal = document.getElementById('attachment-modal');
    const modalTitle = document.getElementById('attachment-modal-title');
    const modalText = document.getElementById('attachment-modal-text');
    const modalGallery = document.getElementById('attachment-modal-gallery');
    
    modalTitle.textContent = title;
    modalText.innerHTML = escapeHTML(text).replace(/\\n/g, '<br>');
    
    modalGallery.innerHTML = '';
    if (attachments && attachments.length > 0) {
        attachments.forEach(file => {
            modalGallery.innerHTML += `
                <a href="${escapeHTML(file.file_path)}" target="_blank" class="block group hover-scale">
                    <div class="relative overflow-hidden rounded-lg">
                        <img src="${escapeHTML(file.file_path)}" alt="${escapeHTML(file.file_name)}" class="w-full h-32 object-cover rounded-lg transition transform group-hover:scale-110">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end justify-center">
                            <p class="text-xs text-center p-1 text-white truncate">${escapeHTML(file.file_name)}</p>
                        </div>
                    </div>
                </a>
            `;
        });
    } else {
        modalGallery.innerHTML = '<p class="text-gray-400">No attachments found.</p>';
    }
    
    modal.classList.remove('hidden', 'opacity-0');
    modal.classList.add('flex', 'opacity-100');
    
    document.getElementById('attachment-modal-content').classList.remove('scale-95');
    document.getElementById('attachment-modal-content').classList.add('scale-100');
}

function closeAttachmentModal() {
    const modal = document.getElementById('attachment-modal');
    modal.classList.add('opacity-0');
    document.getElementById('attachment-modal-content').classList.add('scale-95');
    
    setTimeout(() => {
        modal.classList.add('hidden');
    }, 300);
}
</script> 