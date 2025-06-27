const API_URL = "http://localhost:8081";

document.addEventListener('DOMContentLoaded', function() {
    loadTestimonials();
    loadChampions();
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href').substring(1);
            const element = document.getElementById(targetId);
            if (element) {
                element.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
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
                        <h4>Andreea M.</h4>
                        <span>Member since 2023</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="testimonial">
            <div class="testimonial-content">
                <div class="quote-icon"><i class="fas fa-quote-left"></i></div>
                <p>"As someone who travels frequently, having access to workouts I can do anywhere has been a game-changer. The app is intuitive and the community support is amazing."</p>
                <div class="testimonial-author">
                    <div class="author-image author-image-2"></div>
                    <div class="author-info">
                        <h4>Radu C.</h4>
                        <span>Member since 2024</span>
                    </div>
                </div>
            </div>
        </div>
    `;
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

function displayHomepageFallback() {
    const container = document.querySelector('.homepage-champions-list');
    container.innerHTML = `
        <div class="homepage-champion-item rank-1">
            <div class="homepage-champion-rank">1</div>
            <div class="homepage-champion-avatar">C</div>
            <div class="homepage-champion-info">
                <div class="homepage-champion-name">Champion User</div>
                <div class="homepage-champion-details">Fitness enthusiast</div>
            </div>
            <div class="homepage-champion-score">
                <span class="homepage-champion-score-value">856</span>
                <span class="homepage-champion-score-label">Score</span>
            </div>
        </div>
        <div class="homepage-champion-item rank-2">
            <div class="homepage-champion-rank">2</div>
            <div class="homepage-champion-avatar">F</div>
            <div class="homepage-champion-info">
                <div class="homepage-champion-name">Fitness Pro</div>
                <div class="homepage-champion-details">Personal trainer</div>
            </div>
            <div class="homepage-champion-score">
                <span class="homepage-champion-score-value">742</span>
                <span class="homepage-champion-score-label">Score</span>
            </div>
        </div>
        <div class="homepage-champion-item rank-3">
            <div class="homepage-champion-rank">3</div>
            <div class="homepage-champion-avatar">A</div>
            <div class="homepage-champion-info">
                <div class="homepage-champion-name">Active User</div>
                <div class="homepage-champion-details">Regular member</div>
            </div>
            <div class="homepage-champion-score">
                <span class="homepage-champion-score-value">634</span>
                <span class="homepage-champion-score-label">Score</span>
            </div>
        </div>
    `;
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