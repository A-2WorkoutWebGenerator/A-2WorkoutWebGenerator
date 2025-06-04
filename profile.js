const useAjaxSubmit = true;

document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    initializeNavigation();
    initializeProfilePhoto();
    initializeProfileForm();
    loadWorkoutSuggestions();
    document.querySelector('[data-section="workouts"]').addEventListener('click', () => {
        loadWorkoutSuggestions();
    });
    document.querySelector('[data-section="stats"]').addEventListener('click', () => {
        loadStatistics();
    });
    const workoutForm = document.getElementById('preferences-form');
    if (workoutForm) {
        workoutForm.addEventListener('submit', function(e) {
            e.preventDefault();
            generateWorkout();
        });
    }
});

function checkAuth() {
    const token = localStorage.getItem("authToken");

    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    fetch('get-profile.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.user) {
            const welcomeSection = document.getElementById('sidebar-username');
            if (welcomeSection) {
                welcomeSection.textContent = `Welcome, ${data.user.username}!`;
            }
            const profileTitle = document.getElementById('profile-title');
            if (profileTitle) {
                profileTitle.textContent = `${data.user.username}'s Profile`;
            }
        } else {
            logout();
        }
    })
    .catch(error => {
        console.error("Error fetching user info:", error);
        logout();
    });

    loadUserProfile();
}

function initializeNavigation() {
    const menuItems = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.section');
    
    menuItems.forEach(item => {
        item.addEventListener('click', (event) => {
            const target = item.getAttribute('data-section');
            if (target === "home") {
                return;
            }
            
            event.preventDefault();
            menuItems.forEach(i => i.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            item.classList.add('active');
            document.getElementById(target).classList.add('active');
        });
    });
}

function initializeProfilePhoto() {
    const profilePic = document.getElementById('profile_pic');
    const profilePreview = document.getElementById('profile_preview');
    
    if (profilePic && profilePreview) {
        profilePic.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    profilePreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }
}

function loadUserProfile() {
    const authToken = localStorage.getItem("authToken");
    if (!authToken) {
        console.error("No auth token found in localStorage");
        return;
    }
    const authTokenInput = document.getElementById('auth_token');
    if (authTokenInput) {
        authTokenInput.value = authToken;
    }

    const profileForm = document.getElementById('profile-form');
    if (!profileForm) return;
    
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-indicator';
    loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading profile...';
    loadingIndicator.style.textAlign = 'center';
    loadingIndicator.style.padding = '20px';
    loadingIndicator.style.marginBottom = '20px';
    profileForm.parentNode.insertBefore(loadingIndicator, profileForm);
    profileForm.style.display = 'none';

    fetch('get-profile.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateFormWithData(data);
        } else {
            showMessage(data.message || "Unable to load profile data.", "error");
            if (data.message && data.message.toLowerCase().includes("token")) {
                logout();
            }
        }
    })
    .catch(error => {
        console.error("Error during profile fetch:", error);
        showMessage("Connection error. Please try again later.", "error");
    })
    .finally(() => {
        profileForm.style.display = 'grid';
        if (loadingIndicator.parentNode) {
            loadingIndicator.parentNode.removeChild(loadingIndicator);
        }
    });
}

function populateFormWithData(data) {
    if (data.profile) {
        const profile = data.profile;
        const fields = {
            'first_name': profile.first_name,
            'last_name': profile.last_name,
            'gender': profile.gender,
            'age': profile.age,
            'weight': profile.weight,
            'goal': profile.goal,
            'injuries': profile.injuries
        };
        for (const [fieldId, value] of Object.entries(fields)) {
            const field = document.getElementById(fieldId);
            if (field && value !== undefined && value !== null) field.value = value;
        }
        if (profile.profile_picture_path) {
            const profilePreview = document.getElementById('profile_preview');
            if (profilePreview) {
                profilePreview.src = profile.profile_picture_path;
            }
        }
    }

    if (data.user && data.user.email) {
        const emailField = document.getElementById('email');
        if (emailField) emailField.value = data.user.email;
    }
}

function initializeProfileForm() {
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (useAjaxSubmit) {
                e.preventDefault();

                const formData = new FormData(profileForm);
                const authToken = localStorage.getItem("authToken");
                if (authToken && !formData.has('auth_token')) {
                    formData.append('auth_token', authToken);
                }
                const submitButton = profileForm.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                
                fetch('submit-profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showMessage(data.message || "Profile saved successfully!", "success");
                        if (data.suggestion) {
                            showWorkoutSuggestion(data.suggestion);
                        }
                        loadUserProfile();
                    } else {
                        showMessage(data.message || "Error saving profile!", "error");
                    }
                })
                .catch(error => {
                    console.error("Error submitting profile:", error);
                    showMessage("Connection error. Please try again later.", "error");
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                });
            }
        });
    }
}

function showMessage(message, type) {
    let messageElement = document.querySelector('.message-container');
    
    if (!messageElement) {
        messageElement = document.createElement('div');
        messageElement.className = 'message-container';
        messageElement.style.padding = '15px';
        messageElement.style.marginBottom = '20px';
        messageElement.style.borderRadius = '8px';
        messageElement.style.textAlign = 'center';
        messageElement.style.fontWeight = '500';
        messageElement.style.transition = 'all 0.3s';
        
        const profileForm = document.getElementById('profile-form');
        profileForm.parentNode.insertBefore(messageElement, profileForm);
    }
    
    if (type === "success") {
        messageElement.style.backgroundColor = "rgba(46, 204, 113, 0.15)";
        messageElement.style.color = "#2ecc71";
        messageElement.style.border = "1px solid #2ecc71";
    } else {
        messageElement.style.backgroundColor = "rgba(231, 76, 60, 0.15)";
        messageElement.style.color = "#e74c3c";
        messageElement.style.border = "1px solid #e74c3c";
    }

    messageElement.textContent = message;

    messageElement.style.display = 'block';
    messageElement.style.opacity = '1';

    setTimeout(() => {
        messageElement.style.opacity = '0';
        setTimeout(() => {
            messageElement.style.display = 'none';
        }, 300);
    }, 5000);
}

function showWorkoutSuggestion(suggestion) {
    let suggestionContainer = document.getElementById('workout-suggestion-container');
    if (!suggestionContainer) {
        suggestionContainer = document.createElement('div');
        suggestionContainer.id = 'workout-suggestion-container';
        const workoutsSection = document.getElementById('workouts');
        if (workoutsSection) {
            workoutsSection.insertBefore(suggestionContainer, workoutsSection.firstChild.nextSibling);
        }
    }
    let workoutList = '';
    if (suggestion.workouts) {
        suggestion.workouts.forEach(workout => {
            workoutList += `<li>${workout}</li>`;
        });
    }
    suggestionContainer.innerHTML = `
        <div class="workout-suggestion" style="margin-top: 20px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <h3 style="color: #2ecc71; margin-bottom: 10px;">${suggestion.title || 'Your Workout Plan'}</h3>
            <p>${suggestion.description || ''}</p>
            <h4>Recommended Workouts:</h4>
            <ul style="margin-top: 15px; padding-left: 20px;">${workoutList}</ul>
            ${suggestion.intensity ? `<p><strong>Intensity:</strong> ${suggestion.intensity}</p>` : ''}
            ${suggestion.frequency ? `<p><strong>Frequency:</strong> ${suggestion.frequency}</p>` : ''}
            ${suggestion.caution ? `<div style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #3498db; font-style: italic;"><strong>Important:</strong> ${suggestion.caution}</div>` : ''}
            ${suggestion.age_note ? `<div style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #3498db; font-style: italic;">${suggestion.age_note}</div>` : ''}
        </div>
    `;
}

function loadWorkoutSuggestions() {
    const token = localStorage.getItem("authToken");
    fetch('workout-suggestions.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.suggestions) {
            showAllSuggestions(data.suggestions);
        } else {
            document.getElementById('workout-suggestion-container').innerHTML =
                "<div style='color: #999; font-style: italic; text-align:center; margin-top: 30px;'>No workout suggestions have been generated yet.</div>";
        }
    });
}

function showAllSuggestions(suggestions) {
    const container = document.getElementById('workout-suggestion-container');
    container.innerHTML = "";
    if (!Array.isArray(suggestions) || suggestions.length === 0) {
        container.innerHTML = "<div style='color: #999; font-style: italic; text-align:center; margin-top: 30px;'>No workout suggestions have been generated yet.</div>";
        return;
    }
    suggestions.forEach(item => {
        let workoutList = '';
        if (Array.isArray(item.exercises)) {
            item.exercises.forEach(exercise => {
                workoutList += `<li>
                    <strong>${exercise.name}</strong>
                    ${exercise.difficulty ? ` (${exercise.difficulty})` : ''}
                    ${exercise.duration_minutes ? ` - ${exercise.duration_minutes} min` : ''}
                    <br>${exercise.description || ""}
                    <br><em>${exercise.instructions || ""}</em>
                </li>`;
            });
        }
        container.innerHTML += `
            <div class="workout-suggestion" style="margin-top: 20px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <div style="font-size: 0.9em; color: #888; margin-bottom: 5px;">
                    Generated: ${item.generated_at}
                </div>
                <ul style="margin-top: 15px; padding-left: 20px;">${workoutList}</ul>
            </div>
        `;
    });
}

function showTemporaryNotification(message) {
    let notification = document.querySelector('.profile-temp-notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.className = 'profile-temp-notification';
        notification.style.position = "fixed";
        notification.style.top = "30px";
        notification.style.right = "30px";
        notification.style.zIndex = "9999";
        notification.style.background = "#fffbe6";
        notification.style.color = "#8e7300";
        notification.style.border = "1px solid #ffe066";
        notification.style.padding = "14px 24px";
        notification.style.borderRadius = "8px";
        notification.style.fontWeight = "500";
        notification.style.boxShadow = "0 2px 10px rgba(0,0,0,0.08)";
        document.body.appendChild(notification);
    }
    notification.innerHTML = message;
    notification.style.display = "block";
    notification.style.opacity = "1";

    setTimeout(() => {
        notification.style.opacity = "0";
        setTimeout(() => {
            notification.style.display = "none";
        }, 400);
    }, 4000);
}

function generateWorkout() {
    const token = localStorage.getItem("authToken");
    const muscle_group = document.getElementById('muscle_group').value;
    const intensity = document.getElementById('intensity').value;
    const duration = document.getElementById('duration').value;
    const equipment = document.getElementById('equipment_pref').value;
    const location = document.getElementById('location').value;

    fetch('generate-workout.php', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            muscle_group,
            intensity,
            duration,
            equipment,
            location
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showAllSuggestions(data.workout);
        } else {
            showMessage(data.message, "error");
        }
    });
}

function logout() {
    localStorage.removeItem('authToken');
    console.log("Logged out successfully");
    window.location.href = 'login.html';
}

function loadStatistics() {
    const token = localStorage.getItem("authToken");
    const statsSection = document.getElementById('stats');
    statsSection.innerHTML = `
        <h2>Statistics</h2>
        <div style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #3498db;"></i>
            <p style="margin-top: 15px; color: #666;">Loading your statistics...</p>
        </div>
    `;
    
    fetch('get-statistics.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.statistics) {
            displayStatistics(data.statistics);
        } else {
            statsSection.innerHTML = `
                <h2>Statistics</h2>
                <div style="text-align: center; padding: 40px; color: #e74c3c;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2em;"></i>
                    <p style="margin-top: 15px;">Error loading statistics: ${data.message || 'Unknown error'}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading statistics:', error);
        statsSection.innerHTML = `
            <h2>Statistics</h2>
            <div style="text-align: center; padding: 40px; color: #e74c3c;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2em;"></i>
                <p style="margin-top: 15px;">Connection error. Please try again later.</p>
            </div>
        `;
    });
}

function displayStatistics(stats) {
    const statsSection = document.getElementById('stats');
    
    statsSection.innerHTML = `
        <h2>Your Fitness Statistics</h2>
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-dumbbell"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.total_workouts}</h3>
                    <p>Total Workouts</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-fire"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.workout_streak_days}</h3>
                    <p>Day Streak</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.total_duration_formatted}</h3>
                    <p>Total Time</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3>${stats.avg_workout_duration}min</h3>
                    <p>Avg Duration</p>
                </div>
            </div>
        </div>
        
        <div class="stats-row">
            <div class="stats-card">
                <h3>This Month Progress</h3>
                <div class="progress-stats">
                    <div class="progress-item">
                        <span class="progress-label">This Week</span>
                        <span class="progress-value">${stats.this_week_workouts} workouts</span>
                    </div>
                    <div class="progress-item">
                        <span class="progress-label">This Month</span>
                        <span class="progress-value">${stats.this_month_workouts} workouts</span>
                    </div>
                    <div class="progress-item">
                        <span class="progress-label">Last Workout</span>
                        <span class="progress-value">${stats.last_workout_formatted}</span>
                    </div>
                </div>
            </div>
            
            <div class="stats-card">
                <h3>Preferences</h3>
                <div class="preferences-stats">
                    <div class="pref-item">
                        <span class="pref-label">Favorite Muscle Group</span>
                        <span class="pref-value">${stats.most_popular_muscle_group}</span>
                    </div>
                    <div class="pref-item">
                        <span class="pref-label">Preferred Difficulty</span>
                        <span class="pref-value">${stats.most_used_difficulty}</span>
                    </div>
                    <div class="pref-item">
                        <span class="pref-label">Equipment Used</span>
                        <span class="pref-value">${stats.most_used_equipment}</span>
                    </div>
                </div>
            </div>
        </div>
    
        <div class="stats-row">
            <div class="stats-card chart-card">
                <h3>Monthly Activity</h3>
                <div class="chart-container">
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <div class="stats-card chart-card">
                <h3>Muscle Groups</h3>
                <div class="muscle-groups-chart">
                    ${generateMuscleGroupBars(stats.muscle_group_stats)}
                </div>
            </div>
        </div>
        <div class="stats-card">
            <h3>Recent Workouts</h3>
            <div class="recent-workouts">
                ${generateRecentWorkouts(stats.recent_workouts)}
            </div>
        </div>

        <div class="stats-card">
            <h3>Difficulty Distribution</h3>
            <div class="difficulty-chart">
                ${generateDifficultyBars(stats.difficulty_stats)}
            </div>
        </div>
    `;
    
    drawMonthlyChart(stats.monthly_chart_data);
}

function generateMuscleGroupBars(muscleGroups) {
    if (!muscleGroups || muscleGroups.length === 0) {
        return '<p style="text-align: center; color: #666; padding: 20px;">No data available</p>';
    }
    
    return muscleGroups.map(group => `
        <div class="muscle-bar">
            <div class="muscle-label">${group.muscle_group}</div>
            <div class="muscle-progress">
                <div class="muscle-fill" style="width: ${group.percentage}%"></div>
            </div>
            <div class="muscle-count">${group.count}</div>
        </div>
    `).join('');
}

function generateDifficultyBars(difficulties) {
    if (!difficulties || difficulties.length === 0) {
        return '<p style="text-align: center; color: #666; padding: 20px;">No data available</p>';
    }
    
    const difficultyColors = {
        'beginner': '#2ecc71',
        'intermediate': '#f39c12',
        'advanced': '#e74c3c',
        'all_levels': '#3498db'
    };
    
    return difficulties.map(diff => `
        <div class="difficulty-bar">
            <div class="difficulty-label">${diff.difficulty}</div>
            <div class="difficulty-progress">
                <div class="difficulty-fill" 
                     style="width: ${diff.percentage}%; background-color: ${difficultyColors[diff.difficulty] || '#95a5a6'}">
                </div>
            </div>
            <div class="difficulty-count">${diff.count} (${diff.percentage}%)</div>
        </div>
    `).join('');
}

function generateRecentWorkouts(recentWorkouts) {
    if (!recentWorkouts || recentWorkouts.length === 0) {
        return '<p style="text-align: center; color: #666; padding: 20px;">No recent workouts found</p>';
    }
    
    return recentWorkouts.map(workout => `
        <div class="recent-workout-item">
            <div class="workout-date">${workout.date}</div>
            <div class="workout-summary">
                <span class="workout-exercises">${workout.exercises_count} exercises</span>
                <span class="workout-duration">${workout.total_duration} minutes</span>
            </div>
            <div class="workout-exercises-list">
                ${workout.exercises ? workout.exercises.map(ex => 
                    `<span class="exercise-tag">${ex.name}</span>`
                ).join('') : ''}
            </div>
        </div>
    `).join('');
}

function drawMonthlyChart(monthlyData) {
    const canvas = document.getElementById('monthlyChart');
    if (!canvas || !monthlyData) return;
    
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    ctx.clearRect(0, 0, width, height);
    
    if (monthlyData.length === 0) {
        ctx.fillStyle = '#666';
        ctx.font = '16px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('No data available', width/2, height/2);
        return;
    }
    
    const maxWorkouts = Math.max(...monthlyData.map(d => d.workouts), 1);
    const barWidth = (width - 60) / monthlyData.length;
    const chartHeight = height - 60;
    monthlyData.forEach((data, index) => {
        const barHeight = (data.workouts / maxWorkouts) * chartHeight;
        const x = 30 + index * barWidth;
        const y = height - 30 - barHeight;
        
        ctx.fillStyle = '#3498db';
        ctx.fillRect(x + 5, y, barWidth - 10, barHeight);
        
        ctx.fillStyle = '#2c3e50';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(data.workouts, x + barWidth/2, y - 5);
        
        ctx.save();
        ctx.translate(x + barWidth/2, height - 10);
        ctx.rotate(-Math.PI/4);
        ctx.font = '10px Arial';
        ctx.textAlign = 'right';
        ctx.fillText(data.month, 0, 0);
        ctx.restore();
    });
    
    ctx.strokeStyle = '#bdc3c7';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.moveTo(30, height - 30);
    ctx.lineTo(width - 30, height - 30);
    ctx.moveTo(30, height - 30);
    ctx.lineTo(30, 30);
    ctx.stroke();
}