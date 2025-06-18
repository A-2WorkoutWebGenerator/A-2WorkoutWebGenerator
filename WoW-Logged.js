const API_URL = "http://fitgen.eu-north-1.elasticbeanstalk.com";

        document.addEventListener('DOMContentLoaded', function() {
            loadTestimonials();
        });

        function loadTestimonials() {
            fetch(`${API_URL}/get_stories.php?limit=2&offset=0`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    displayTestimonials(data.data);
                } else {
                    displayFallbackTestimonials();
                }
            })
            .catch(error => {
                console.error('Error loading testimonials:', error);
                displayFallbackTestimonials();
            });
        }

        function displayTestimonials(stories) {
            const container = document.getElementById('testimonials-container');
            container.innerHTML = '';

            stories.forEach(story => {
                const firstLetter = story.userName.charAt(0).toUpperCase();
                const testimonial = document.createElement('div');
                testimonial.className = 'testimonial';
                
                testimonial.innerHTML = `
                    <div class="testimonial-content">
                        <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                        <p>"${story.storyText}"</p>
                        <div class="testimonial-author">
                            <div class="testimonial-author-avatar">${firstLetter}</div>
                            <div class="author-info">
                                <h4>${story.userName}</h4>
                                <span>${story.achievement}</span>
                            </div>
                        </div>
                    </div>
                `;
                
                container.appendChild(testimonial);
            });
        }
        function displayFallbackTestimonials() {
            const container = document.getElementById('testimonials-container');
            container.innerHTML = `
                <div class="testimonial">
                    <div class="testimonial-content">
                        <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                        <p>"FitGen completely transformed my approach to fitness. The personalized workout plans and tracking features helped me lose 15kg in just 4 months!"</p>
                        <div class="testimonial-author">
                            <div class="author-image"></div>
                            <div class="author-info">
                                <h4>Demo User</h4>
                                <span>Sample Achievement</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                        <p>"As someone who travels frequently, having access to workouts I can do anywhere has been a game-changer. The app is intuitive and the community support is amazing."</p>
                        <div class="testimonial-author">
                            <div class="author-image"></div>
                            <div class="author-info">
                                <h4>Demo User</h4>
                                <span>Sample Achievement</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

function checkAndShowAdminButton() {
    const token = localStorage.getItem('authToken');
    
    if (!token) return;
    
    try {
        const payload = JSON.parse(atob(token.split('.')[1]));
        
        if (payload.isAdmin) {
            const navButtons = document.querySelector('.nav-buttons');
            
            if (navButtons && !document.querySelector('.admin-panel-btn')) {
                const adminButton = document.createElement('a');
                adminButton.href = 'admin-panel.html';
                adminButton.innerHTML = '<i class="fas fa-cog"></i> Admin Panel';
                adminButton.className = 'btn btn-outline admin-panel-btn';
            
                adminButton.style.cssText = `
                    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%) !important;
                    color: white !important;
                    border: 2px solid #e74c3c !important;
                    margin-left: 0.5rem;
                    transition: all 0.3s ease;
                    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
                `;
                adminButton.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 15px rgba(231, 76, 60, 0.4)';
                    this.style.background = 'linear-gradient(135deg, #c0392b 0%, #a93226 100%)';
                });
                
                adminButton.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 2px 8px rgba(231, 76, 60, 0.3)';
                    this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                });
                navButtons.insertBefore(adminButton, navButtons.firstChild);
                
                console.log('Admin Panel button added to navigation');
            }
        }
    } catch (error) {
        console.error('Error checking admin status:', error);
    }
}

function handleLoginSuccess(response) {
    if (response.success) {
        localStorage.setItem('authToken', response.token);
        checkAndShowAdminButton();
    }
}
function loadChampions() {
    console.log('Loading homepage champions...');
    const container = document.querySelector('.homepage-champions-list');
    
    if (!container) {
        console.error('Homepage champions container not found!');
        return;
    }
    
    fetch(`${API_URL}/champions.php?limit=3`)
    .then(response => {
        console.log('Champions API response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('Champions API data:', data);
        if (data.success && data.data && data.data.length > 0) {
            displayHomepageChampions(data.data.slice(0, 3));
        } else {
            console.log('No champions data, showing fallback');
            displayHomepageFallback();
        }
    })
    .catch(error => {
        console.error('Error loading champions:', error);
        displayHomepageFallback();
    });
}
function displayHomepageChampions(champions) {
    console.log('Displaying homepage champions leaderboard:', champions);
    const container = document.querySelector('.homepage-champions-list');
    
    if (!container) {
        console.error('Homepage champions container not found!');
        return;
    }
    
    let listHTML = '';
    
    champions.forEach((champion, index) => {
        const rank = champion.rank || (index + 1);
        const name = getChampionDisplayName(champion);
        const avatar = getChampionAvatar(champion);
        const hasProfilePic = champion.profile_picture && champion.profile_picture.trim() !== '';
        const stats = champion.stats || {
            total_workouts: champion.total_workouts || 0,
            active_days: champion.active_days || 0,
            total_duration: champion.total_duration || 0,
            activity_score: champion.activity_score || 0
        };
        const details = [];
        if (champion.age) details.push(`${champion.age} years old`);
        if (champion.gender) details.push(champion.gender);
        if (champion.goal) details.push(formatGoal(champion.goal));
        
        listHTML += `
            <div class="homepage-champion-item rank-${rank}">
                <div class="homepage-champion-rank">${rank}</div>
                
                <div class="homepage-champion-avatar ${hasProfilePic ? 'has-image' : ''}">
                    ${hasProfilePic 
                        ? `<img src="${champion.profile_picture}" alt="${name}" onerror="this.parentElement.innerHTML='${avatar}'; this.parentElement.classList.remove('has-image');">` 
                        : avatar
                    }
                </div>
                
                <div class="homepage-champion-info">
                    <div class="homepage-champion-name">${name}</div>
                    <div class="homepage-champion-details">
                        ${details.length > 0 ? details.join(' • ') : 'Fitness enthusiast'}
                    </div>
                </div>
                
                <div class="homepage-champion-score">
                    <span class="homepage-champion-score-value">${Math.round(stats.activity_score)}</span>
                    <span class="homepage-champion-score-label">Score</span>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = listHTML;
    console.log('Homepage champions leaderboard updated successfully');
}

function formatGoal(goal) {
    return goal.replace(/_/g, ' ')
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}


function getChampionDisplayName(champion) {
    const fullName = `${champion.first_name || ''} ${champion.last_name || ''}`.trim();
    return fullName || champion.username || 'Anonymous';
}

function getChampionAvatar(champion) {
    const name = getChampionDisplayName(champion);
    return name.charAt(0).toUpperCase();
}


function displayFallbackChampions() {
    const container = document.querySelector('.podium-container');
    container.innerHTML = `
        <div class="podium">
            <div class="podium-place medium">
                <div class="champion-info">
                    <div class="champion-avatar">D</div>
                    <h3>Demo User 2</h3>
                    <p>45 workouts</p>
                    <span class="medal">🥈</span>
                </div>
                <div class="podium-base">
                    <span>2</span>
                </div>
            </div>
            <div class="podium-place tall">
                <div class="champion-info">
                    <div class="champion-avatar">C</div>
                    <h3>Champion User</h3>
                    <p>67 workouts</p>
                    <span class="medal">🥇</span>
                </div>
                <div class="podium-base">
                    <span>1</span>
                </div>
            </div>
            <div class="podium-place short">
                <div class="champion-info">
                    <div class="champion-avatar">T</div>
                    <h3>Third Place</h3>
                    <p>32 workouts</p>
                    <span class="medal">🥉</span>
                </div>
                <div class="podium-base">
                    <span>3</span>
                </div>
            </div>
        </div>
    `;
}

document.addEventListener('DOMContentLoaded', function() {
    checkAndShowAdminButton();
    
    loadTestimonials();
    loadChampions();
    
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
});
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

if (!document.querySelector('#podium-styles')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'podium-styles';
    styleElement.innerHTML = podiumStyles;
    document.head.appendChild(styleElement);
}

function smoothScrollTo(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

document.querySelectorAll('a[href^="#"]').forEach(link => {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('href').substring(1);
        smoothScrollTo(targetId);
    });
});