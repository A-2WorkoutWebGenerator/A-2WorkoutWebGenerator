window.logout = function() {
    showModal('Logout Confirmation', 'Are you sure you want to log out?', function() {
        showToast('You have been logged out');
        setTimeout(() => {
            localStorage.removeItem('authToken');
            window.location.href = 'WoW.html';
        }, 1500);
    });
};

function showModal(title, message, confirmCallback) {
    const existingModal = document.querySelector('.modal-overlay');
    if (existingModal) {
        existingModal.remove();
    }
    
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.innerHTML = `
        <div class="modal-container">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline modal-cancel">Cancel</button>
                <button class="btn btn-primary modal-confirm">Confirm</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
    if (!document.getElementById('modal-styles')) {
        const modalStyles = document.createElement('style');
        modalStyles.id = 'modal-styles';
        modalStyles.textContent = `
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.3s ease;
                padding: 20px;
            }
            
            .modal-overlay.active {
                opacity: 1;
            }
            
            .modal-container {
                background-color: white;
                border-radius: 12px;
                width: 100%;
                max-width: 500px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                transform: scale(0.8);
                transition: transform 0.3s ease;
            }
            
            .modal-overlay.active .modal-container {
                transform: scale(1);
            }
            
            .modal-header {
                padding: 20px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .modal-header h3 {
                margin: 0;
            }
            
            .modal-close {
                background: transparent;
                border: none;
                font-size: 1.2rem;
                cursor: pointer;
                color: #999;
                transition: color 0.2s ease;
            }
            
            .modal-close:hover {
                color: #333;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .modal-footer {
                padding: 20px;
                border-top: 1px solid rgba(0, 0, 0, 0.1);
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
        `;
        document.head.appendChild(modalStyles);
    }
    
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
    
    const closeBtn = modal.querySelector('.modal-close');
    const cancelBtn = modal.querySelector('.modal-cancel');
    const confirmBtn = modal.querySelector('.modal-confirm');
    
    function closeModal() {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            document.body.style.overflow = '';
        }, 300);
    }
    
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    
    confirmBtn.addEventListener('click', function() {
        if (typeof confirmCallback === 'function') {
            confirmCallback();
        }
        closeModal();
    });
}

function showToast(message) {
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-info-circle"></i>
            <span>${message}</span>
        </div>
        <button class="toast-close"><i class="fas fa-times"></i></button>
    `;
    
    document.body.appendChild(toast);
    if (!document.getElementById('toast-styles')) {
        const toastStyles = document.createElement('style');
        toastStyles.id = 'toast-styles';
        toastStyles.textContent = `
            .toast-notification {
                position: fixed;
                bottom: 30px;
                right: 30px;
                background-color: white;
                color: #333;
                padding: 0 15px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                justify-content: space-between;
                max-width: 350px;
                transform: translateX(110%);
                transition: transform 0.3s ease;
                z-index: 9999;
            }
            
            .toast-notification.active {
                transform: translateX(0);
            }
            
            .toast-content {
                display: flex;
                align-items: center;
                padding: 15px 5px;
            }
            
            .toast-content i {
                color: #18D259;
                font-size: 1.2rem;
                margin-right: 10px;
            }
            
            .toast-close {
                background: transparent;
                border: none;
                color: #999;
                cursor: pointer;
                padding: 5px;
                transition: color 0.2s ease;
            }
            
            .toast-close:hover {
                color: #333;
            }
        `;
        document.head.appendChild(toastStyles);
    }
    
    setTimeout(() => {
        toast.classList.add('active');
    }, 10);

    const closeBtn = toast.querySelector('.toast-close');
    closeBtn.addEventListener('click', () => {
        toast.classList.remove('active');
        setTimeout(() => {
            toast.remove();
        }, 300);
    });

    setTimeout(() => {
        toast.classList.remove('active');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 4000);
}
const API_URL = "http://localhost:8081";

document.getElementById('contact-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fullName = document.getElementById('fullName').value.trim();
    const email = document.getElementById('email').value.trim();
    const message = document.getElementById('message').value.trim();
    
    if (!fullName || !email || !message) {
        showMessage('Please fill in all fields.', 'error');
        return;
    }
    const submitBtn = document.getElementById('submit-btn');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Sending...</span>';

    fetch(`${API_URL}/submit_contact.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            fullName: fullName,
            email: email,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            
            document.getElementById('contact-form').reset();
        
            setTimeout(() => {
                hideMessage();
            }, 5000);
        } else {
            showMessage(data.message || 'Failed to send message. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting contact form:', error);
        showMessage('Connection error. Please try again later.', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
});

function showMessage(message, type) {
    const container = document.getElementById('message-container');
    container.innerHTML = `<div class="message-container message-${type}">${message}</div>`;
    container.style.display = 'block';
}

function hideMessage() {
    const container = document.getElementById('message-container');
    container.style.display = 'none';
}

function logout() {
    localStorage.removeItem("authToken");
    window.location.href = "WoW.html";
}