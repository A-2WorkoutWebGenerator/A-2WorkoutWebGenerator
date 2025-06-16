const API_URL = "http://localhost:8081";

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
function logout() {
    localStorage.removeItem('authToken');

    const adminButton = document.querySelector('.admin-panel-btn');
    if (adminButton) {
        adminButton.remove();
    }
    window.location.href = 'login.html';
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