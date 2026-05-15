// Service Worker Registration for offline support
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then(registration => {
            console.log('ServiceWorker registration successful');
        }).catch(err => {
            console.log('ServiceWorker registration failed: ', err);
        });
    });
}

// Toast notifications
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Form validation
function validateForm(form) {
    const required = form.querySelectorAll('[required]');
    let isValid = true;
    
    required.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#f44336';
            isValid = false;
        } else {
            field.style.borderColor = '#ddd';
        }
    });
    
    return isValid;
}

// Auto-save functionality
let autoSaveTimer;
function autoSave(formData) {
    clearTimeout(autoSaveTimer);
    autoSaveTimer = setTimeout(() => {
        localStorage.setItem('draft_transaction', JSON.stringify(formData));
    }, 1000);
}

// Load draft
function loadDraft() {
    const draft = localStorage.getItem('draft_transaction');
    if (draft) {
        return JSON.parse(draft);
    }
    return null;
}

// Clear draft
function clearDraft() {
    localStorage.removeItem('draft_transaction');
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load draft for new transactions
    if (window.location.href.includes('add_transaction')) {
        const draft = loadDraft();
        if (draft) {
            // Fill form with draft data
            Object.keys(draft).forEach(key => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field) field.value = draft[key];
            });
        }
        
        // Auto-save on input
        document.querySelectorAll('input, textarea, select').forEach(field => {
            field.addEventListener('input', () => {
                const formData = {};
                document.querySelectorAll('input, textarea, select').forEach(f => {
                    formData[f.name] = f.value;
                });
                autoSave(formData);
            });
        });
    }
});