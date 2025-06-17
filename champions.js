const API_URL = "http://localhost:8081";
let currentFilters = {};
document.addEventListener('DOMContentLoaded', function() {
    loadChampions();
    setupExportButtons();
});

function loadChampions() {
    const filters = {
        age_group: document.getElementById('age-filter').value,
        gender: document.getElementById('gender-filter').value,
        goal: document.getElementById('goal-filter').value,
        limit: document.getElementById('limit-filter').value
    };

    currentFilters = filters;
    const queryParams = new URLSearchParams();
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            queryParams.append(key, filters[key]);
        }
    });

    const container = document.getElementById('champions-list');
    container.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading champions...</div>';

    fetch(`${API_URL}/champions.php?${queryParams.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayChampions(data.data);
                updateExportUrls();
            } else {
                container.innerHTML = '<div class="empty-state">Failed to load champions data</div>';
            }
        })
        .catch(error => {
            console.error('Error loading champions:', error);
            container.innerHTML = '<div class="empty-state">Error loading champions data</div>';
        });
}

function displayChampions(champions) {
    const container = document.getElementById('champions-list');

    if (champions.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="fas fa-trophy"></i><br>No champions found with current filters</div>';
        return;
    }

    container.innerHTML = champions.map((champion, index) => {
        const rank = index + 1;
        const name = (champion.first_name && champion.last_name) 
            ? `${champion.first_name} ${champion.last_name}` 
            : champion.username;
        
        const initials = getInitials(name);
        const rankClass = rank <= 3 ? `top-3 rank-${rank}` : '';

        return `
            <div class="champion-item">
                <div class="rank ${rankClass}">${rank}</div>
                
                <div class="champion-avatar">
                    ${champion.profile_picture 
                        ? `<img src="${champion.profile_picture}" alt="${name}">` 
                        : initials}
                </div>

                <div class="champion-info">
                    <div class="champion-name">${name}</div>
                    <div class="champion-details">
                        ${champion.age ? `${champion.age} years old` : 'Age not specified'}
                        ${champion.gender ? ` • ${champion.gender}` : ''}
                        ${champion.goal ? ` • ${formatGoal(champion.goal)}` : ''}
                    </div>
                </div>

                <div class="champion-stats">
                    <div class="stat-item">
                        <div class="stat-value">${champion.stats.total_workouts}</div>
                        <div class="stat-label">Workouts</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${champion.stats.active_days}</div>
                        <div class="stat-label">Active Days</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${champion.stats.total_duration}</div>
                        <div class="stat-label">Minutes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${champion.stats.activity_score}</div>
                        <div class="stat-label">Score</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function getInitials(name) {
    return name.split(' ')
        .map(word => word.charAt(0).toUpperCase())
        .slice(0, 2)
        .join('');
}

function formatGoal(goal) {
    const goalDisplayNames = {
    'lose_weight': 'Lose Weight',
    'build_muscle': 'Build Muscle',
    'flexibility': 'Improve Flexibility',
    'endurance': 'Increase Endurance',
    'rehab': 'Rehabilitation',
    'mobility': 'Increase Mobility',
    'strength': 'Increase Strength',
    'posture': 'Greater Posture',
    'cardio': 'Improve Resistance'
    };

    return goalDisplayNames[goal] || goal.replace(/_/g, ' ')
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function clearFilters() {
    document.getElementById('age-filter').value = '';
    document.getElementById('gender-filter').value = '';
    document.getElementById('goal-filter').value = '';
    document.getElementById('limit-filter').value = '25';
    loadChampions();
}

function setupExportButtons() {
    document.getElementById('export-json').addEventListener('click', function(e) {
        e.preventDefault();
        exportData('json');
    });

    document.getElementById('export-pdf').addEventListener('click', function(e) {
        e.preventDefault();
        exportData('pdf');
    });

    document.getElementById('export-csv').addEventListener('click', function(e) {
        e.preventDefault();
        exportData('csv');
    });
}

function updateExportUrls() {
    const queryParams = new URLSearchParams();
    Object.keys(currentFilters).forEach(key => {
        if (currentFilters[key]) {
            queryParams.append(key, currentFilters[key]);
        }
    });

    const jsonUrl = `${API_URL}/champions.php?${queryParams.toString()}&format=json`;
    const pdfUrl = `${API_URL}/champions.php?${queryParams.toString()}&format=pdf`;
    const csvUrl = `${API_URL}/champions.php?${queryParams.toString()}&format=csv`;

    document.getElementById('export-json').href = jsonUrl;
    document.getElementById('export-pdf').href = pdfUrl;
    document.getElementById('export-csv').href = csvUrl;
}

function exportData(format) {
    const queryParams = new URLSearchParams();
    Object.keys(currentFilters).forEach(key => {
        if (currentFilters[key]) {
            queryParams.append(key, currentFilters[key]);
        }
    });
    queryParams.append('format', format);

    const url = `${API_URL}/champions.php?${queryParams.toString()}`;
    
    if (format === 'json') {
        window.open(url, '_blank');
    } else if (format === 'pdf') {
        window.open(url, '_blank');
    } else if (format === 'csv') {
        window.open(url, '_blank');
    }
}
setInterval(loadChampions, 300000);
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