function checkAndShowAdminButton() {
    const token = localStorage.getItem('authToken');
    
    if (!token) return;
    
    try {
        const payload = JSON.parse(atob(token.split('.')[1]));
        if (payload.isAdmin) {
            addAdminButtonToNavigation();
        }
    } catch (error) {
        console.error('Error checking admin status:', error);
    }
}

function addAdminButtonToNavigation() {
    if (document.querySelector('.admin-panel-btn')) {
        return;
    }
    let navContainer = null;
    navContainer = document.querySelector('.nav-buttons');
    if (!navContainer) {
        navContainer = document.querySelector('.navbar .nav-links');
    }
    if (!navContainer) {
        navContainer = document.querySelector('.sidebar .menu');
    }
    if (!navContainer) {
        navContainer = document.querySelector('nav ul');
    }

    if (!navContainer) {
        navContainer = document.querySelector('nav');
    }

    if (navContainer) {
        createAndInsertAdminButton(navContainer);
    } else {
        console.warn('Nu s-a găsit container de navigare pentru butonul admin');
    }
}
function createAndInsertAdminButton(container) {
    const adminButton = document.createElement('a');
    adminButton.href = 'admin-panel.html';
    adminButton.className = 'admin-panel-btn';
    if (container.classList.contains('nav-buttons')) {
        adminButton.innerHTML = '<i class="fas fa-cog"></i> Admin Panel';
        adminButton.className += ' btn btn-outline';
        styleAdminButtonForHeader(adminButton);
        container.insertBefore(adminButton, container.firstChild);
        
    } else if (container.classList.contains('menu')) {
        adminButton.innerHTML = '<i class="fas fa-shield-alt"></i> Admin Panel';
        adminButton.className = 'nav-link admin-panel-btn';
        adminButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = 'admin-panel.html';
        });
        
        styleAdminButtonForSidebar(adminButton);
        const listItem = document.createElement('li');
        listItem.appendChild(adminButton);
        container.insertBefore(listItem, container.firstChild);
        
    } else {
        adminButton.innerHTML = '<i class="fas fa-cog"></i> Admin';
        adminButton.className += ' nav-link';
        styleAdminButtonForGeneral(adminButton);
        
        if (container.tagName === 'UL') {
            const listItem = document.createElement('li');
            listItem.appendChild(adminButton);
            container.appendChild(listItem);
        } else {
            container.appendChild(adminButton);
        }
    }
    
    console.log('Admin Panel button added to navigation');
}

function styleAdminButtonForHeader(button) {
    button.style.cssText = `
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
        color: white !important;
        border: 2px solid #e74c3c !important;
        margin-right: 0.5rem;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    `;
    
    button.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 4px 15px rgba(231, 76, 60, 0.4)';
        this.style.background = 'linear-gradient(135deg, #c0392b 0%, #a93226 100%)';
    });
    
    button.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 2px 8px rgba(231, 76, 60, 0.3)';
        this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
    });
}

function styleAdminButtonForSidebar(button) {
    button.style.cssText = `
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
        color: white !important;
        border: 2px solid rgba(231, 76, 60, 0.3);
        margin-bottom: 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        text-decoration: none;
        display: flex;
        align-items: center;
        padding: 12px 15px;
        font-weight: 600;
    `;
    
    button.addEventListener('mouseenter', function() {
        this.style.transform = 'translateX(5px)';
        this.style.boxShadow = '0 4px 15px rgba(231, 76, 60, 0.4)';
        this.style.background = 'linear-gradient(135deg, #c0392b 0%, #a93226 100%)';
    });
    
    button.addEventListener('mouseleave', function() {
        this.style.transform = 'translateX(0)';
        this.style.boxShadow = '0 2px 8px rgba(231, 76, 60, 0.3)';
        this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
    });
}

function styleAdminButtonForGeneral(button) {
    button.style.cssText = `
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
        color: white !important;
        border: 1px solid #e74c3c;
        padding: 8px 16px;
        border-radius: 6px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-weight: 500;
    `;
    
    button.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 4px 15px rgba(231, 76, 60, 0.4)';
        this.style.background = 'linear-gradient(135deg, #c0392b 0%, #a93226 100%)';
    });
    
    button.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 2px 8px rgba(231, 76, 60, 0.3)';
        this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
    });
}

function removeAdminButton() {
    const adminButton = document.querySelector('.admin-panel-btn');
    if (adminButton) {
        const parentLi = adminButton.closest('li');
        if (parentLi && parentLi.children.length === 1) {
            parentLi.remove();
        } else {
            adminButton.remove();
        }
        console.log('Admin button removed from navigation');
    }
}


function globalLogout() {
    localStorage.removeItem('authToken');
    removeAdminButton();
    
    window.location.href = 'login.html';
}

function startAdminStatusCheck() {
    setInterval(() => {
        const token = localStorage.getItem('authToken');
        if (!token) {
            removeAdminButton();
            return;
        }
        
        try {
            const payload = JSON.parse(atob(token.split('.')[1]));
            const currentTime = Math.floor(Date.now() / 1000);
            if (payload.exp && payload.exp < currentTime) {
                removeAdminButton();
                localStorage.removeItem('authToken');
                return;
            }
            if (!payload.isAdmin) {
                removeAdminButton();
            }
        } catch (error) {
            console.error('Error checking admin status:', error);
            removeAdminButton();
        }
    }, 5 * 60 * 1000);
}

function initializeAdminGlobal() {
    checkAndShowAdminButton();
    startAdminStatusCheck();
    window.addEventListener('storage', function(e) {
        if (e.key === 'authToken') {
            if (e.newValue) {
                checkAndShowAdminButton();
            } else {
                removeAdminButton();
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    initializeAdminGlobal();
});
if (typeof window !== 'undefined') {
    window.AdminGlobal = {
        checkAndShowAdminButton,
        removeAdminButton,
        globalLogout,
        initializeAdminGlobal
    };
}