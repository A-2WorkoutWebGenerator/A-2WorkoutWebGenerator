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