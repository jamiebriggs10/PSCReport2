/**
 * Presswick Sailing Club Issue Reporting System
 * Main JavaScript functionality
 */

// Photo capture and file selection functions
function triggerCamera() {
    const input = document.getElementById('images');
    if (input) {
        // Set capture attribute for camera
        input.setAttribute('capture', 'environment');
        input.click();
    }
}

function triggerFileSelect() {
    const input = document.getElementById('images');
    if (input) {
        // Remove capture attribute for file selection
        input.removeAttribute('capture');
        input.click();
    }
}

class PSCApp {
    constructor() {
        this.init();
    }

    init() {
        // Initialize when DOM is loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupEventListeners());
        } else {
            this.setupEventListeners();
        }
    }

    setupEventListeners() {
        // Header menu toggle
        this.setupHeaderMenu();
        
        // Form validation
        this.setupFormValidation();
        
        // File upload handling
        this.setupFileUpload();
        
        // Image gallery
        this.setupImageGallery();
        
        // Modal handling
        this.setupModals();
        
        // Filter forms
        this.setupFilters();
        
        // CSRF token handling
        this.setupCSRFTokens();
        
        // Share functionality
        this.setupShareFunctionality();
    }

    setupHeaderMenu() {
        const menuButton = document.querySelector('.menu-button');
        const dropdownMenu = document.querySelector('.dropdown-menu');
        
        if (menuButton && dropdownMenu) {
            menuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
            });

            // Close menu when clicking outside
            document.addEventListener('click', () => {
                dropdownMenu.classList.remove('show');
            });

            // Prevent menu from closing when clicking inside
            dropdownMenu.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    }

    setupFormValidation() {
        const forms = document.querySelectorAll('form[data-validate]');
        
        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });

            // Real-time validation
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('blur', () => {
                    this.validateField(input);
                });
            });
        });
    }

    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                isValid = false;
            }
        });

        return isValid;
    }

    validateField(field) {
        let isValid = true;
        const value = field.value.trim();

        // Clear previous errors
        this.clearFieldError(field);

        // Required field validation
        if (field.hasAttribute('required') && !value) {
            this.showFieldError(field, 'This field is required');
            isValid = false;
        }

        // Specific field validations
        switch (field.type) {
            case 'email':
                if (value && !this.isValidEmail(value)) {
                    this.showFieldError(field, 'Please enter a valid email address');
                    isValid = false;
                }
                break;
            case 'password':
                // No minimum length requirement
                break;
        }

        // Custom validations
        if (field.hasAttribute('data-match')) {
            const matchField = document.querySelector(field.getAttribute('data-match'));
            if (matchField && value !== matchField.value) {
                this.showFieldError(field, 'Passwords do not match');
                isValid = false;
            }
        }

        return isValid;
    }

    showFieldError(field, message) {
        field.classList.add('is-invalid');
        
        let errorElement = field.parentNode.querySelector('.invalid-feedback');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
    }

    clearFieldError(field) {
        field.classList.remove('is-invalid');
        const errorElement = field.parentNode.querySelector('.invalid-feedback');
        if (errorElement) {
            errorElement.remove();
        }
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    setupFileUpload() {
        const MAX_FILES = 4;
        const fileInputs = document.querySelectorAll('input[type="file"][multiple]');

        const fileKey = (f) => `${f.name}|${f.size}|${f.lastModified}`;

        // Merge newly-picked files into the input's existing FileList (dedupe + cap).
        const mergeIntoInput = (input, incoming, preview) => {
            const existing = input._accumulatedFiles || [];
            const seen = new Set(existing.map(fileKey));
            const merged = existing.slice();
            for (const f of Array.from(incoming)) {
                const k = fileKey(f);
                if (!seen.has(k)) {
                    seen.add(k);
                    merged.push(f);
                }
                if (merged.length >= MAX_FILES) break;
            }
            const dt = new DataTransfer();
            merged.forEach(f => dt.items.add(f));
            input.files = dt.files;
            input._accumulatedFiles = merged;
            if (preview) this.handleFilePreview(input, preview);
        };

        fileInputs.forEach(input => {
            input._accumulatedFiles = [];
            const preview = input.parentNode.querySelector('.file-preview');
            input.addEventListener('change', (e) => {
                // e.target.files is the NEW selection from this picker invocation.
                // Pull it off first, then merge with previously accumulated.
                const justPicked = Array.from(e.target.files);
                // Temporarily empty the input so merge sees only previously-accumulated.
                const dt = new DataTransfer();
                (input._accumulatedFiles || []).forEach(f => dt.items.add(f));
                input.files = dt.files;
                mergeIntoInput(input, justPicked, preview);
            });
            // Expose for the remove handler
            input._mergeFiles = (files) => mergeIntoInput(input, files, preview);
        });

        // Drag and drop functionality
        const fileUploadAreas = document.querySelectorAll('.file-upload');
        fileUploadAreas.forEach(area => {
            area.addEventListener('dragover', (e) => {
                e.preventDefault();
                area.classList.add('dragover');
            });

            area.addEventListener('dragleave', () => {
                area.classList.remove('dragover');
            });

            area.addEventListener('drop', (e) => {
                e.preventDefault();
                area.classList.remove('dragover');

                const input = area.querySelector('input[type="file"][multiple]');
                const files = e.dataTransfer.files;

                if (input && files.length > 0 && input._mergeFiles) {
                    input._mergeFiles(files);
                }
            });
        });
    }

    handleFilePreview(input, preview) {
        preview.innerHTML = '';
        const files = Array.from(input.files);
        const maxFiles = 4; // MAX_ATTACHMENTS (reuses constant)

        files.slice(0, maxFiles).forEach((file, index) => {
            const previewItem = document.createElement('div');
            previewItem.className = 'file-preview-item';

            const removeButtonHtml = `<button type="button" class="file-remove" data-index="${index}" title="Remove">&times;</button>`;

            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewItem.innerHTML = `<img src="${e.target.result}" alt="${file.name}">${removeButtonHtml}`;
                    const removeBtn = previewItem.querySelector('.file-remove');
                    removeBtn.addEventListener('click', () => this.removeFile(input, index, preview));
                };
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('video/')) {
                previewItem.innerHTML = `<div class="file-generic video">VIDEO</div>${removeButtonHtml}`;
                const removeBtn = previewItem.querySelector('.file-remove');
                removeBtn.addEventListener('click', () => this.removeFile(input, index, preview));
            } else {
                // Generic file icon with first letter of extension or 'F'
                const ext = (file.name.split('.').pop() || '?').substring(0,4).toUpperCase();
                previewItem.innerHTML = `<div class="file-generic">${ext || 'FILE'}</div>${removeButtonHtml}`;
                const removeBtn = previewItem.querySelector('.file-remove');
                removeBtn.addEventListener('click', () => this.removeFile(input, index, preview));
            }
            preview.appendChild(previewItem);
        });
    }

    removeFile(input, indexToRemove, preview) {
        const dt = new DataTransfer();
        const files = Array.from(input.files);
        const kept = [];

        files.forEach((file, index) => {
            if (index !== indexToRemove) {
                dt.items.add(file);
                kept.push(file);
            }
        });

        input.files = dt.files;
        input._accumulatedFiles = kept;
        this.handleFilePreview(input, preview);
    }

    setupImageGallery() {
        const galleryImages = document.querySelectorAll('.image-gallery img');
        
        galleryImages.forEach(img => {
            // Only add event listener if there's no existing onclick handler
            if (!img.hasAttribute('onclick')) {
                img.addEventListener('click', () => {
                    this.openImageModal(img.src, img.alt);
                });
            }
        });
    }

    openImageModal(src, alt) {
        const modal = document.createElement('div');
        modal.className = 'modal show';
        modal.innerHTML = `
            <div class="modal-content" style="max-width: 90vw; max-height: 90vh;">
                <div class="modal-header">
                    <h5 class="modal-title">${alt}</h5>
                    <button type="button" class="modal-close">&times;</button>
                </div>
                <div class="modal-body" style="padding: 0;">
                    <img src="${src}" alt="${alt}" style="width: 100%; height: auto; max-height: 80vh; object-fit: contain;">
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Close modal events
        const closeBtn = modal.querySelector('.modal-close');
        closeBtn.addEventListener('click', () => {
            document.body.removeChild(modal);
        });
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });
        
        // Close with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (document.body.contains(modal)) {
                    document.body.removeChild(modal);
                }
            }
        });
    }

    setupModals() {
        // Generic modal handling
        const modalTriggers = document.querySelectorAll('[data-modal]');
        
        modalTriggers.forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.getAttribute('data-modal');
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('show');
                }
            });
        });

        // Close modal buttons
        const closeButtons = document.querySelectorAll('.modal-close, [data-modal-close]');
        closeButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal');
                if (modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Close modal on backdrop click
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });
    }

    setupFilters() {
        const filterForms = document.querySelectorAll('.filter-form');
        
        filterForms.forEach(form => {
            const inputs = form.querySelectorAll('select, input');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    // Auto-submit filter form
                    form.submit();
                });
            });
        });
    }

    setupCSRFTokens() {
        // Add CSRF tokens to AJAX requests
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            // Store token for AJAX requests
            this.csrfToken = csrfToken.getAttribute('content');
        }
    }

    setupShareFunctionality() {
        const shareButtons = document.querySelectorAll('[data-share]');
        
        shareButtons.forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                
                const shareData = {
                    title: btn.getAttribute('data-share-title') || 'PSC Issues',
                    text: btn.getAttribute('data-share-text') || '',
                    url: btn.getAttribute('data-share-url') || window.location.href
                };
                
                // Try native Web Share API first (mobile)
                if (navigator.share && this.isMobile()) {
                    try {
                        await navigator.share(shareData);
                    } catch (err) {
                        console.log('Share cancelled or failed:', err);
                    }
                } else {
                    // Fallback: copy to clipboard
                    this.copyToClipboard(shareData.text || shareData.url);
                    this.showToast('Copied to clipboard!');
                }
            });
        });
    }

    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
        }
    }

    showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `alert alert-${type}`;
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.zIndex = '2000';
        toast.style.minWidth = '250px';
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, 3000);
    }

    isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    // Utility method for AJAX requests
    async makeRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (this.csrfToken) {
            defaultOptions.headers['X-CSRF-Token'] = this.csrfToken;
        }
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, mergedOptions);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            } else {
                return await response.text();
            }
        } catch (error) {
            console.error('Request failed:', error);
            this.showToast('Request failed. Please try again.', 'danger');
            throw error;
        }
    }

    // Loading spinner utility
    showLoading(element) {
        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        spinner.setAttribute('data-loading', 'true');
        element.appendChild(spinner);
    }

    hideLoading(element) {
        const spinner = element.querySelector('[data-loading="true"]');
        if (spinner) {
            spinner.remove();
        }
    }
}

// Initialize the app
const app = new PSCApp();

// Export for potential module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PSCApp;
}