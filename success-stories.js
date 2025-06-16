const API_URL = "http://localhost:8081";
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