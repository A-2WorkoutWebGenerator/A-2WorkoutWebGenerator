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
const API_URL = "http://fitgen.eu-north-1.elasticbeanstalk.com";
const STORIES_PER_PAGE = 6;
let currentPage = 1;
let totalStories = 0;
let totalPages = 0;

document.addEventListener('DOMContentLoaded', function() {
    loadStoriesFromDatabase(currentPage);
    setupPaginationEvents();
});

function setupPaginationEvents() {
    document.getElementById('prev-btn').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            loadStoriesFromDatabase(currentPage);
        }
    });

    document.getElementById('next-btn').addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            loadStoriesFromDatabase(currentPage);
        }
    });
}
function loadStoriesFromDatabase(page = 1) {
    const offset = (page - 1) * STORIES_PER_PAGE;
    
    fetch(`${API_URL}/get_stories.php?limit=${STORIES_PER_PAGE}&offset=${offset}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            totalStories = data.pagination.total;
            totalPages = Math.ceil(totalStories / STORIES_PER_PAGE);
            
            displayStories(data.data);
            updateStoriesInfo(page);
            updatePagination(page);
        } else {
            console.error('Failed to load stories:', data.message);
        }
    })
    .catch(error => {
        console.error('Error loading stories:', error);
    });
}
function displayStories(stories) {
    const userStoriesContainer = document.getElementById('user-stories');
    userStoriesContainer.innerHTML = '';

    if (stories.length === 0) {
        userStoriesContainer.innerHTML = '<p style="text-align: center; color: var(--text-light); grid-column: 1 / -1;">No stories found. Be the first to share your story!</p>';
        return;
    }

    stories.forEach(story => {
        const storyCard = createStoryCard(story);
        userStoriesContainer.appendChild(storyCard);
    });
}
function updateStoriesInfo(page) {
    const start = (page - 1) * STORIES_PER_PAGE + 1;
    const end = Math.min(page * STORIES_PER_PAGE, totalStories);
    
    const infoText = totalStories === 0 
        ? 'No stories yet' 
        : `Showing ${start}-${end} of ${totalStories} stories`;
    
    document.getElementById('stories-count').textContent = infoText;
}

function updatePagination(page) {
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');
    
    prevBtn.disabled = page <= 1;
    nextBtn.disabled = page >= totalPages;

    updatePaginationNumbers(page);
}

function updatePaginationNumbers(currentPage) {
    const container = document.getElementById('pagination-numbers');
    container.innerHTML = '';

    if (totalPages <= 1) {
        return;
    }

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, currentPage + 2);

    if (startPage > 1) {
        addPageNumber(container, 1, currentPage);
        if (startPage > 2) {
            addEllipsis(container);
        }
    }
    for (let i = startPage; i <= endPage; i++) {
        addPageNumber(container, i, currentPage);
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            addEllipsis(container);
        }
        addPageNumber(container, totalPages, currentPage);
    }
}
function addPageNumber(container, pageNum, currentPage) {
    const btn = document.createElement('button');
    btn.className = `pagination-number ${pageNum === currentPage ? 'active' : ''}`;
    btn.textContent = pageNum;
    btn.addEventListener('click', () => {
        if (pageNum !== currentPage) {
            currentPage = pageNum;
            loadStoriesFromDatabase(currentPage);
        }
    });
    container.appendChild(btn);
}
function addEllipsis(container) {
    const ellipsis = document.createElement('span');
    ellipsis.className = 'pagination-ellipsis';
    ellipsis.textContent = '...';
    container.appendChild(ellipsis);
}

function createStoryCard(story) {
    const storyCard = document.createElement('div');
    storyCard.className = 'user-story-card';
    const firstLetter = story.userName.charAt(0).toUpperCase();
    
    storyCard.innerHTML = `
        <div class="user-story-header">
            <div class="user-author-image">${firstLetter}</div>
            <div class="user-author-info">
                <h4>${story.userName}</h4>
                <span>${story.achievement}</span>
                <div class="user-story-date">${story.formattedDate}</div>
            </div>
        </div>
        <div class="user-story-content">
            <p>"${story.storyText}"</p>
        </div>
    `;
    
    return storyCard;
}

document.getElementById('story-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const userName = document.getElementById('user-name').value;
    const achievement = document.getElementById('achievement').value;
    const storyText = document.getElementById('story-text').value;
    
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    fetch(`${API_URL}/submit_story.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            userName: userName,
            achievement: achievement,
            storyText: storyText
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('success-message').style.display = 'block';
            
            this.reset();
            
            setTimeout(() => {
                currentPage = 1;
                loadStoriesFromDatabase(currentPage);
            }, 1000);

            setTimeout(() => {
                document.getElementById('success-message').style.display = 'none';
            }, 5000);
        } else {
            alert('Error: ' + (data.message || 'Failed to submit story'));
        }
    })
    .catch(error => {
        console.error('Error submitting story:', error);
        alert('Connection error. Please try again.');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
});
