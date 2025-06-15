const API_URL = "http://localhost:8081";
        let allMessages = [];
        let allExercises = [];
        let allStories = [];
        let currentFilter = 'all';
        let currentStoriesFilter = 'all';
        let isEditing = false;
        
        let currentPage = 1;
        let exercisesPerPage = 9;
        let filteredExercises = [];

        function checkAdminAuth() {
            return new Promise((resolve) => {
                const token = localStorage.getItem('authToken');
                
                if (!token) {
                    showAccessDenied();
                    resolve(false);
                    return;
                }
                
                try {
                    const payload = JSON.parse(atob(token.split('.')[1]));
                    
                    if (!payload.isAdmin) {
                        showAccessDenied();
                        resolve(false);
                        return;
                    }
                    if (payload.exp && payload.exp < Date.now() / 1000) {
                        showAccessDenied("Session expired. Please login again.");
                        resolve(false);
                        return;
                    }
                    
                    resolve(true);
                    
                } catch (error) {
                    console.error('Error verifying admin token:', error);
                    showAccessDenied();
                    resolve(false);
                }
            });
        }

        function showAccessDenied(message = "Access Denied - Admin privileges required") {
            document.body.innerHTML = `
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    font-family: 'Segoe UI', sans-serif;
                    margin: 0;
                    padding: 0;
                ">
                    <div style="
                        background: white;
                        padding: 3rem;
                        border-radius: 20px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                        text-align: center;
                        min-width: 400px;
                        animation: slideUp 0.3s ease;
                    ">
                        <div style="
                            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                            height: 80px;
                            border-radius: 50%;
                            margin: 0 auto 2rem;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: white;
                            font-size: 2rem;
                        ">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        
                        <h1 style="
                            color: #e74c3c;
                            margin-bottom: 1rem;
                            font-size: 2rem;
                            font-weight: 700;
                        ">403 - Access Denied</h1>
                        
                        <p style="
                            color: #666;
                            margin-bottom: 2rem;
                            font-size: 1.1rem;
                            line-height: 1.5;
                        ">${message}</p>
                        
                        <div style="display: flex; gap: 1rem; justify-content: center;">
                            <button onclick="window.location.href='login.html'" style="
                                background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
                                color: white;
                                border: none;
                                padding: 1rem 2rem;
                                border-radius: 50px;
                                cursor: pointer;
                                font-weight: 600;
                                font-size: 1rem;
                                transition: all 0.3s ease;
                                min-width: 120px;
                            ">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                            
                            <button onclick="window.location.href='WoW-Logged.html'" style="
                                background: #6c757d;
                                color: white;
                                border: none;
                                padding: 1rem 2rem;
                                border-radius: 50px;
                                cursor: pointer;
                                font-weight: 600;
                                font-size: 1rem;
                                transition: all 0.3s ease;
                                min-width: 120px;
                            ">
                                <i class="fas fa-home"></i> Home
                            </button>
                        </div>
                    </div>
                </div>
                
                <style>
                    @keyframes slideUp {
                        from {
                            opacity: 0;
                            transform: translateY(30px);
                        }
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }
                </style>
            `;
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');

            if (tabName === 'messages') {
                loadMessages();
            } else if (tabName === 'exercises') {
                loadExercises();
            } else if (tabName === 'stories') {
                loadStories();
            }
        }

        document.addEventListener('DOMContentLoaded', async function() {
            const isAuthenticated = await checkAdminAuth();
            
            if (!isAuthenticated) {
                return;
            }
            
            await loadMessages();
            setupMessageFilters();
            setupStoriesFilters();
            setupExerciseSearch();
            setupExerciseForm();
        });
        function setupMessageFilters() {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    currentFilter = this.dataset.filter;
                    displayMessages();
                });
            });
        }

        function loadMessages() {
            fetch(`${API_URL}/admin_messages.php`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allMessages = data.data;
                    updateMessageStats();
                    displayMessages();
                } else {
                    document.getElementById('messages-container').innerHTML = 
                        '<div class="loading">Failed to load messages</div>';
                }
            })
            .catch(error => {
                console.error('Error loading messages:', error);
                document.getElementById('messages-container').innerHTML = 
                    '<div class="loading">Error loading messages</div>';
            });
        }

        function updateMessageStats() {
            const total = allMessages.length;
            const unread = allMessages.filter(m => !m.isRead).length;
            const responded = allMessages.filter(m => m.responseSent).length;

            document.getElementById('total-count').textContent = total;
            document.getElementById('unread-count').textContent = unread;
            document.getElementById('responded-count').textContent = responded;
        }

        function displayMessages() {
            let filteredMessages = allMessages;

            if (currentFilter === 'unread') {
                filteredMessages = allMessages.filter(m => !m.isRead);
            } else if (currentFilter === 'responded') {
                filteredMessages = allMessages.filter(m => m.responseSent);
            }

            const container = document.getElementById('messages-container');

            if (filteredMessages.length === 0) {
                container.innerHTML = '<div class="loading">No messages found</div>';
                return;
            }

            container.innerHTML = filteredMessages.map(message => createMessageCard(message)).join('');
        }

        function createMessageCard(message) {
            const date = new Date(message.createdAt).toLocaleString();
            const cardClass = message.responseSent ? 'responded' : (!message.isRead ? 'unread' : '');
            
            return `
                <div class="message-card ${cardClass}">
                    <div class="message-header">
                        <div class="message-info">
                            <h3>${message.fullName}</h3>
                            <div class="email">${message.email}</div>
                            <div class="date">${date}</div>
                        </div>
                        <div class="message-status">
                            ${!message.isRead ? '<span class="status-badge status-unread">Unread</span>' : ''}
                            ${message.isRead && !message.responseSent ? '<span class="status-badge status-read">Read</span>' : ''}
                            ${message.responseSent ? '<span class="status-badge status-responded">Responded</span>' : ''}
                        </div>
                    </div>
                    
                    <div class="message-content">
                        ${message.message}
                    </div>
                    
                    <div class="message-actions">
                        ${!message.isRead ? `<button class="btn btn-secondary btn-small" onclick="markAsRead(${message.id})">
                            <i class="fas fa-eye"></i> Mark as Read
                        </button>` : ''}
                        
                        ${!message.responseSent ? `<button class="btn btn-primary btn-small" onclick="toggleResponseForm(${message.id})">
                            <i class="fas fa-reply"></i> Send Response
                        </button>` : ''}
                    </div>
                    
                    <div class="response-form" id="response-form-${message.id}">
                        <textarea placeholder="Type your response here..." id="response-text-${message.id}"></textarea>
                        <div class="response-actions">
                            <button class="btn btn-primary" onclick="sendResponse(${message.id})">
                                <i class="fas fa-paper-plane"></i> Send Response
                            </button>
                            <button class="btn btn-secondary" onclick="toggleResponseForm(${message.id})">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }

        function toggleResponseForm(messageId) {
            const form = document.getElementById(`response-form-${messageId}`);
            form.classList.toggle('active');
        }

        function markAsRead(messageId) {
            const message = allMessages.find(m => m.id === messageId);
            if (message) {
                message.isRead = true;
                updateMessageStats();
                displayMessages();
            }
        }

        function sendResponse(messageId) {
            const responseText = document.getElementById(`response-text-${messageId}`).value.trim();
            
            if (!responseText) {
                alert('Please enter a response message');
                return;
            }

            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            btn.disabled = true;

            fetch(`${API_URL}/admin_messages.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    messageId: messageId,
                    responseText: responseText,
                    adminEmail: 'support@fitgen.com'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Response sent successfully!');
                    
                    const message = allMessages.find(m => m.id === messageId);
                    if (message) {
                        message.responseSent = true;
                        message.isRead = true;
                    }
                    
                    updateMessageStats();
                    displayMessages();
                } else {
                    alert('Failed to send response: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error sending response:', error);
                alert('Error sending response');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function setupExerciseSearch() {
            document.getElementById('exercise-search').addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                if (searchTerm === '') {
                    filteredExercises = [...allExercises];
                } else {
                    filteredExercises = allExercises.filter(exercise => 
                        exercise.name.toLowerCase().includes(searchTerm) ||
                        exercise.description.toLowerCase().includes(searchTerm) ||
                        exercise.muscle_groups.some(muscle => muscle.toLowerCase().includes(searchTerm)) ||
                        exercise.goal.toLowerCase().includes(searchTerm) ||
                        exercise.location.toLowerCase().includes(searchTerm)
                    );
                }
                
                currentPage = 1;
                displayExercisesWithPagination();
            });
        }

        function setupExerciseForm() {
            document.getElementById('exerciseFormElement').addEventListener('submit', function(e) {
                e.preventDefault();
                saveExercise();
            });
        }

        function loadExercises() {
            fetch(`${API_URL}/admin_exercises.php`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allExercises = data.data;
                    filteredExercises = [...allExercises];
                    currentPage = 1;
                    displayExercisesWithPagination();
                } else {
                    document.getElementById('exercises-container').innerHTML = 
                        '<div class="loading">Failed to load exercises</div>';
                }
            })
            .catch(error => {
                console.error('Error loading exercises:', error);
                document.getElementById('exercises-container').innerHTML = 
                    '<div class="loading">Error loading exercises</div>';
            });
        }

        function displayExercisesWithPagination() {
            const container = document.getElementById('exercises-container');

            if (filteredExercises.length === 0) {
                container.innerHTML = '<div class="loading">No exercises found</div>';
                return;
            }

            const totalPages = Math.ceil(filteredExercises.length / exercisesPerPage);
            const startIndex = (currentPage - 1) * exercisesPerPage;
            const endIndex = startIndex + exercisesPerPage;
            const exercisesToShow = filteredExercises.slice(startIndex, endIndex);

            const resultsInfo = `
                <div class="exercises-header">
                    <div class="results-info">
                        Showing ${startIndex + 1}-${Math.min(endIndex, filteredExercises.length)} of ${filteredExercises.length} exercises
                    </div>
                </div>
            `;

            const exercisesGrid = `
                <div class="exercises-grid">
                    ${exercisesToShow.map(exercise => createExerciseCard(exercise)).join('')}
                </div>
            `;

            const pagination = createPagination(totalPages);

            container.innerHTML = resultsInfo + exercisesGrid + pagination;
        }

        function createPagination(totalPages) {
            if (totalPages <= 1) return '';

            let paginationHTML = '<div class="pagination-container">';
        
            paginationHTML += `
                <div class="pagination-info">
                    Page ${currentPage} of ${totalPages}
                </div>
            `;

            paginationHTML += '<div class="pagination">';
            paginationHTML += `
                <button class="pagination-btn" onclick="changePage(${currentPage - 1})" 
                        ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }
            if (startPage > 1) {
                paginationHTML += `
                    <button class="pagination-btn" onclick="changePage(1)">1</button>
                `;
                if (startPage > 2) {
                    paginationHTML += '<span class="pagination-btn" style="border: none; cursor: default;">...</span>';
                }
            }
            for (let i = startPage; i <= endPage; i++) {
                paginationHTML += `
                    <button class="pagination-btn ${i === currentPage ? 'active' : ''}" 
                            onclick="changePage(${i})">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += '<span class="pagination-btn" style="border: none; cursor: default;">...</span>';
                }
                paginationHTML += `
                    <button class="pagination-btn" onclick="changePage(${totalPages})">${totalPages}</button>
                `;
            }
            paginationHTML += `
                <button class="pagination-btn" onclick="changePage(${currentPage + 1})" 
                        ${currentPage === totalPages ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;

            paginationHTML += '</div></div>';

            return paginationHTML;
        }

        function changePage(newPage) {
            const totalPages = Math.ceil(filteredExercises.length / exercisesPerPage);
            
            if (newPage < 1 || newPage > totalPages) return;
            
            currentPage = newPage;
            displayExercisesWithPagination();
            
            document.getElementById('exercises-container').scrollIntoView({ 
                behavior: 'smooth', 
                block: 'start' 
            });
        }

        function createExerciseCard(exercise) {
            const muscleGroups = Array.isArray(exercise.muscle_groups) ? exercise.muscle_groups : [];
            const categoryNames = {
                1: 'Lower Back Pain Relief',
                2: 'Shoulder Mobility',
                3: 'Knee Rehabilitation', 
                4: 'Full-Body Stretching',
                5: 'Postural Correction',
                6: 'Core Stabilization',
                7: 'Functional Mobility',
                8: 'Neuromuscular Coordination',
                9: 'Football Training',
                10: 'Basketball Drills',
                11: 'Tennis Conditioning',
                12: 'Swimming Technique'
            };
            const getCategoryColor = (categoryId) => {
                if (categoryId >= 1 && categoryId <= 4) return 'physiotherapy';
                if (categoryId >= 5 && categoryId <= 8) return 'kinetotherapy';
                if (categoryId >= 9 && categoryId <= 12) return 'sports';
                return 'unknown';
            };
        
            const categoryColor = getCategoryColor(exercise.category_id);
        
            return `
                <div class="exercise-card">
                    <div class="exercise-header">
                        <div>
                            <div class="exercise-title">${exercise.name}</div>
                            <div class="exercise-category ${categoryColor}">${categoryNames[exercise.category_id] || 'Unknown'}</div>
                        </div>
                    </div>
                    
                    <div class="exercise-description">
                        ${exercise.description}
                    </div>
                    
                    <div class="exercise-meta">
                        <div class="meta-item">
                            <i class="fas fa-signal"></i>
                            <span>${exercise.difficulty}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-dumbbell"></i>
                            <span>${exercise.equipment_needed}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span>${exercise.duration_minutes || 'N/A'} min</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-fire"></i>
                            <span>${exercise.calories_per_minute || 'N/A'} cal/min</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>${exercise.location}</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-target"></i>
                            <span>${exercise.goal}</span>
                        </div>
                    </div>
                    
                    <div class="muscle-groups">
                        ${muscleGroups.map(muscle => `<span class="muscle-tag">${muscle}</span>`).join('')}
                    </div>
                    
                    <div class="exercise-actions">
                        <button class="btn btn-primary btn-small" onclick="editExercise(${exercise.id})">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-small" onclick="deleteExercise(${exercise.id})">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        ${exercise.video_url ? `<a href="${exercise.video_url}" target="_blank" class="btn btn-secondary btn-small">
                            <i class="fas fa-play"></i> Video
                        </a>` : ''}
                    </div>
                </div>
            `;
        }

        function showExerciseForm() {
            document.getElementById('exercise-form').classList.add('active');
            document.getElementById('form-title').innerHTML = '<i class="fas fa-plus"></i> Add New Exercise';
            isEditing = false;
            resetForm();
            setTimeout(() => {
                document.getElementById('exercise-form').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 100);
        }

        function hideExerciseForm() {
            document.getElementById('exercise-form').classList.remove('active');
            resetForm();
        }

        function resetForm() {
            document.getElementById('exerciseFormElement').reset();
            document.getElementById('exercise-id').value = '';
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        }

        function editExercise(exerciseId) {
            const exercise = allExercises.find(ex => ex.id === exerciseId);
            if (!exercise) return;

            isEditing = true;
            document.getElementById('exercise-form').classList.add('active');
            document.getElementById('form-title').innerHTML = '<i class="fas fa-edit"></i> Edit Exercise';

            document.getElementById('exercise-id').value = exercise.id;
            document.getElementById('exercise-name').value = exercise.name;
            document.getElementById('exercise-category').value = exercise.category_id;
            document.getElementById('exercise-difficulty').value = exercise.difficulty;
            document.getElementById('exercise-equipment').value = exercise.equipment_needed;
            document.getElementById('exercise-duration').value = exercise.duration_minutes || '';
            document.getElementById('exercise-calories').value = exercise.calories_per_minute || '';
            document.getElementById('exercise-location').value = exercise.location;
            document.getElementById('exercise-goal').value = exercise.goal;
            document.getElementById('exercise-min-age').value = exercise.min_age || '';
            document.getElementById('exercise-max-age').value = exercise.max_age || '';
            document.getElementById('exercise-gender').value = exercise.gender || '';
            document.getElementById('exercise-min-weight').value = exercise.min_weight || '';
            document.getElementById('exercise-video-url').value = exercise.video_url || '';
            document.getElementById('exercise-image-url').value = exercise.image_url || '';
            document.getElementById('exercise-description').value = exercise.description;
            document.getElementById('exercise-instructions').value = exercise.instructions;
            document.getElementById('exercise-contraindications').value = exercise.contraindications || '';

            const muscleGroups = Array.isArray(exercise.muscle_groups) ? exercise.muscle_groups : [];
            document.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.checked = muscleGroups.includes(cb.value);
            });

            setTimeout(() => {
                document.getElementById('exercise-form').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }, 100);
        }

        function saveExercise() {
            const formData = {
                id: document.getElementById('exercise-id').value || null,
                category_id: document.getElementById('exercise-category').value,
                name: document.getElementById('exercise-name').value,
                description: document.getElementById('exercise-description').value,
                instructions: document.getElementById('exercise-instructions').value,
                duration_minutes: document.getElementById('exercise-duration').value || null,
                difficulty: document.getElementById('exercise-difficulty').value,
                equipment_needed: document.getElementById('exercise-equipment').value,
                video_url: document.getElementById('exercise-video-url').value || null,
                image_url: document.getElementById('exercise-image-url').value || null,
                calories_per_minute: document.getElementById('exercise-calories').value || null,
                location: document.getElementById('exercise-location').value,
                min_age: document.getElementById('exercise-min-age').value || null,
                max_age: document.getElementById('exercise-max-age').value || null,
                gender: document.getElementById('exercise-gender').value || null,
                min_weight: document.getElementById('exercise-min-weight').value || null,
                goal: document.getElementById('exercise-goal').value,
                contraindications: document.getElementById('exercise-contraindications').value || null,
                muscle_groups: []
            };

            document.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
                formData.muscle_groups.push(cb.value);
            });

            if (formData.muscle_groups.length === 0) {
                alert('Please select at least one muscle group');
                return;
            }

            const btn = document.querySelector('#exerciseFormElement button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            btn.disabled = true;

            const method = isEditing ? 'PUT' : 'POST';
            
            fetch(`${API_URL}/admin_exercises.php`, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(isEditing ? 'Exercise updated successfully!' : 'Exercise created successfully!');
                    hideExerciseForm();
                    loadExercises();
                } else {
                    alert('Failed to save exercise: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error saving exercise:', error);
                alert('Error saving exercise');
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function deleteExercise(exerciseId) {
            if (!confirm('Are you sure you want to delete this exercise? This action cannot be undone.')) {
                return;
            }

            fetch(`${API_URL}/admin_exercises.php`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: exerciseId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Exercise deleted successfully!');
                    loadExercises();
                } else {
                    alert('Failed to delete exercise: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error deleting exercise:', error);
                alert('Error deleting exercise');
            });
        }
function setupStoriesFilters() {
    document.querySelectorAll('#stories-tab .filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#stories-tab .filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentStoriesFilter = this.dataset.filter;
            displayStories();
        });
    });
}

function loadStories() {
    fetch(`${API_URL}/admin_stories.php`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            allStories = data.data;
            updateStoriesStats();
            displayStories();
        } else {
            document.getElementById('stories-container').innerHTML = 
                '<div class="loading">Failed to load success stories</div>';
        }
    })
    .catch(error => {
        console.error('Error loading stories:', error);
        document.getElementById('stories-container').innerHTML = 
            '<div class="loading">Error loading success stories</div>';
    });
}

function updateStoriesStats() {
    const total = allStories.length;
    const pending = allStories.filter(s => s.status === 'pending').length;
    const approved = allStories.filter(s => s.status === 'approved').length;
    const rejected = allStories.filter(s => s.status === 'rejected').length;

    document.getElementById('stories-total-count').textContent = total;
    document.getElementById('stories-pending-count').textContent = pending;
    document.getElementById('stories-approved-count').textContent = approved;
    document.getElementById('stories-rejected-count').textContent = rejected;
}

function displayStories() {
    let filteredStories = allStories;

    if (currentStoriesFilter === 'pending') {
        filteredStories = allStories.filter(s => s.status === 'pending');
    } else if (currentStoriesFilter === 'approved') {
        filteredStories = allStories.filter(s => s.status === 'approved');
    } else if (currentStoriesFilter === 'rejected') {
        filteredStories = allStories.filter(s => s.status === 'rejected');
    }

    const container = document.getElementById('stories-container');

    if (filteredStories.length === 0) {
        container.innerHTML = '<div class="loading">No success stories found</div>';
        return;
    }

    container.innerHTML = filteredStories.map(story => createStoryCard(story)).join('');
}

function createStoryCard(story) {
    const date = new Date(story.created_at).toLocaleString();
    const cardClass = story.status;
    
    return `
        <div class="story-card ${cardClass}">
            <div class="story-header">
                <div class="story-info">
                    <h3>${story.user_name}</h3>
                    <div class="achievement">${story.achievement}</div>
                    <div class="date">${date}</div>
                </div>
                <div class="story-status">
                    <span class="status-badge status-${story.status}">${story.status}</span>
                </div>
            </div>
            
            <div class="story-content">
                ${story.story_text}
            </div>
            
            <div class="story-actions">
                ${story.status === 'pending' ? `
                    <button class="btn btn-success btn-small" onclick="approveStory(${story.id})">
                        <i class="fas fa-check"></i> Approve
                    </button>
                    <button class="btn btn-danger btn-small" onclick="toggleRejectionForm(${story.id})">
                        <i class="fas fa-times"></i> Reject
                    </button>
                ` : story.status === 'approved' ? `
                    <button class="btn btn-danger btn-small" onclick="toggleRejectionForm(${story.id})">
                        <i class="fas fa-times"></i> Reject
                    </button>
                ` : story.status === 'rejected' ? `
                    <button class="btn btn-success btn-small" onclick="approveStory(${story.id})">
                        <i class="fas fa-check"></i> Approve
                    </button>
                ` : ''}
                
                <button class="btn btn-secondary btn-small" onclick="deleteStory(${story.id})" style="margin-left: auto;">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </div>
            
            <div class="rejection-form" id="rejection-form-${story.id}">
                <textarea placeholder="Reason for rejection (optional)..." id="rejection-reason-${story.id}"></textarea>
                <div class="rejection-actions">
                    <button class="btn btn-danger" onclick="rejectStory(${story.id})">
                        <i class="fas fa-times"></i> Confirm Rejection
                    </button>
                    <button class="btn btn-secondary" onclick="toggleRejectionForm(${story.id})">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    `;
}

function toggleRejectionForm(storyId) {
    const form = document.getElementById(`rejection-form-${storyId}`);
    form.classList.toggle('active');
}

function approveStory(storyId) {
    updateStoryStatus(storyId, 'approved');
}

function rejectStory(storyId) {
    const reason = document.getElementById(`rejection-reason-${storyId}`).value.trim();
    updateStoryStatus(storyId, 'rejected', reason);
}

function updateStoryStatus(storyId, status, reason = '') {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    btn.disabled = true;

    fetch(`${API_URL}/admin_stories.php`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: storyId,
            status: status,
            rejection_reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(`Story ${status} successfully!`);
            
            const story = allStories.find(s => s.id === storyId);
            if (story) {
                story.status = status;
                if (reason) story.rejection_reason = reason;
            }
            
            updateStoriesStats();
            displayStories();
        } else {
            alert('Failed to update story: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error updating story:', error);
        alert('Error updating story');
    })
    .finally(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function deleteStory(storyId) {
    if (!confirm('Are you sure you want to delete this success story? This action cannot be undone.')) {
        return;
    }

    fetch(`${API_URL}/admin_stories.php`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ id: storyId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Story deleted successfully!');
            loadStories();
        } else {
            alert('Failed to delete story: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting story:', error);
        alert('Error deleting story');
    });
}