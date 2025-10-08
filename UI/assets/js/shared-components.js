/**
 * Shared UI Components for Gaurd Dashboard
 * Contains reusable UI components like loaders, toasts, and modals
 */

// Global Loader
const GaurdUI = {
    /**
     * Shows a fullscreen loader with optional custom message
     * @param {string} message - Optional custom message to display
     */
    showLoader: function(message = 'Loading...') {
        // Check if loader already exists
        let loader = document.getElementById('gaurd-global-loader');
        if (!loader) {
            // Create loader if it doesn't exist
            loader = document.createElement('div');
            loader.id = 'gaurd-global-loader';
            loader.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            loader.innerHTML = `
                <div class="bg-gray-900 p-6 rounded-lg shadow-lg flex items-center space-x-4">
                    <div class="spinner-border animate-spin inline-block w-8 h-8 border-4 rounded-full border-t-transparent border-white"></div>
                    <p class="text-white text-lg font-medium loader-message">${message}</p>
                </div>
            `;
            document.body.appendChild(loader);
        } else {
            // Update message if loader exists
            loader.querySelector('.loader-message').textContent = message;
            loader.classList.remove('hidden');
        }
    },

    /**
     * Hides the fullscreen loader
     */
    hideLoader: function() {
        const loader = document.getElementById('gaurd-global-loader');
        if (loader) {
            loader.classList.add('hidden');
        }
    },

    /**
     * Shows a shimmer loading effect on an element
     * @param {string} elementId - ID of the element to apply shimmer to
     * @param {string} shimmerElementId - ID of the shimmer element (optional)
     */
    showShimmer: function(elementId, shimmerElementId = null) {
        const element = document.getElementById(elementId);
        const shimmerId = shimmerElementId || `${elementId}-shimmer`;
        
        if (element) {
            // Check if shimmer already exists
            let shimmer = document.getElementById(shimmerId);
            if (!shimmer) {
                // Create shimmer container
                shimmer = document.createElement('div');
                shimmer.id = shimmerId;
                shimmer.className = 'w-full animate-pulse';
                
                // Create shimmer content based on element height
                const height = element.offsetHeight;
                const shimmerRows = Math.max(3, Math.ceil(height / 50));
                
                let shimmerContent = '<div class="h-12 bg-gray-800 w-full mb-1"></div>';
                for (let i = 0; i < shimmerRows; i++) {
                    shimmerContent += '<div class="h-16 bg-gray-800 bg-opacity-70 w-full mb-1"></div>';
                }
                
                shimmer.innerHTML = shimmerContent;
                
                // Insert shimmer before the element
                element.parentNode.insertBefore(shimmer, element);
            }
            
            // Hide the actual element and show shimmer
            element.classList.add('hidden');
            shimmer.classList.remove('hidden');
        }
    },

    /**
     * Hides the shimmer effect and shows the original element
     * @param {string} elementId - ID of the element with shimmer
     * @param {string} shimmerElementId - ID of the shimmer element (optional)
     */
    hideShimmer: function(elementId, shimmerElementId = null) {
        const element = document.getElementById(elementId);
        const shimmerId = shimmerElementId || `${elementId}-shimmer`;
        const shimmer = document.getElementById(shimmerId);
        
        if (element) {
            element.classList.remove('hidden');
        }
        
        if (shimmer) {
            shimmer.classList.add('hidden');
        }
    },

    /**
     * Shows a toast notification
     * @param {string} message - Message to display in the toast
     * @param {string} type - Type of toast: 'success', 'error', 'info', 'warning'
     * @param {number} duration - Duration in milliseconds before auto-dismiss
     */
    showToast: function(message, type = 'success', duration = 5000) {
        // Check if toast container exists
        let toastContainer = document.getElementById('gaurd-toast-container');
        if (!toastContainer) {
            // Create toast container if it doesn't exist
            toastContainer = document.createElement('div');
            toastContainer.id = 'gaurd-toast-container';
            toastContainer.className = 'fixed top-4 right-4 z-50';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `mb-3 p-4 rounded-lg shadow-lg flex items-center justify-between transform transition-all duration-300 ease-in-out translate-x-full`;
        
        // Set background color based on type
        if (type === 'success') {
            toast.classList.add('bg-green-500', 'text-white');
        } else if (type === 'error') {
            toast.classList.add('bg-red-500', 'text-white');
        } else if (type === 'warning') {
            toast.classList.add('bg-yellow-500', 'text-white');
        } else {
            toast.classList.add('bg-blue-500', 'text-white');
        }
        
        // Set content
        const icon = type === 'success' ? 'check-circle' : 
                    type === 'error' ? 'exclamation-circle' : 
                    type === 'warning' ? 'exclamation-triangle' : 'info-circle';
        
        toast.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-${icon} mr-3"></i>
                <span class="font-medium">${message}</span>
            </div>
            <button class="ml-4 text-white focus:outline-none hover:text-gray-200">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        // Add to container
        toastContainer.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 10);
        
        // Add close button functionality
        toast.querySelector('button').addEventListener('click', () => {
            this.dismissToast(toast);
        });
        
        // Auto dismiss after duration
        setTimeout(() => {
            this.dismissToast(toast);
        }, duration);
        
        return toast;
    },

    /**
     * Dismisses a toast notification with animation
     * @param {HTMLElement} toast - The toast element to dismiss
     */
    dismissToast: function(toast) {
        toast.classList.add('translate-x-full');
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    },

    /**
     * Shows a confirmation dialog
     * @param {string} title - Dialog title
     * @param {string} message - Dialog message
     * @param {Function} onConfirm - Callback function when confirmed
     * @param {Function} onCancel - Callback function when cancelled (optional)
     * @param {Object} options - Additional options (confirmText, cancelText, etc.)
     */
    confirm: function(title, message, onConfirm, onCancel = null, options = {}) {
        const confirmText = options.confirmText || 'Confirm';
        const cancelText = options.cancelText || 'Cancel';
        const confirmClass = options.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-blue-600 hover:bg-blue-700';
        const confirmIcon = options.danger ? 'trash' : 'check';
        
        // Create modal backdrop
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50';
        
        // Create modal content
        modal.innerHTML = `
            <div class="bg-gray-900 rounded-xl shadow-xl p-8 w-full max-w-md relative border border-gray-700 transform transition-all duration-300">
                <h2 class="text-xl font-bold text-white mb-4">${title}</h2>
                <p class="text-gray-300 mb-6">${message}</p>
                <div class="flex justify-end gap-3">
                    <button id="gaurd-confirm-cancel" class="px-5 py-2 rounded-md bg-gray-700 text-gray-300 hover:bg-gray-600 font-semibold transition-all duration-300">
                        ${cancelText}
                    </button>
                    <button id="gaurd-confirm-ok" class="${confirmClass} text-white px-6 py-2 rounded-md font-semibold transition-all duration-300">
                        <i class="fas fa-${confirmIcon} mr-1"></i> ${confirmText}
                    </button>
                </div>
            </div>
        `;
        
        // Add to body
        document.body.appendChild(modal);
        
        // Add event listeners
        document.getElementById('gaurd-confirm-ok').addEventListener('click', () => {
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
            document.body.removeChild(modal);
        });
        
        document.getElementById('gaurd-confirm-cancel').addEventListener('click', () => {
            if (typeof onCancel === 'function') {
                onCancel();
            }
            document.body.removeChild(modal);
        });
        
        // Close on ESC key
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                if (typeof onCancel === 'function') {
                    onCancel();
                }
                document.body.removeChild(modal);
                document.removeEventListener('keydown', escHandler);
            }
        };
        
        document.addEventListener('keydown', escHandler);
    }
}; 