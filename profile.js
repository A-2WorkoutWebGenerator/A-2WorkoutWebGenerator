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
            'goal': profile.goal,
            'activity_level': profile.activity_level,
            'injuries': profile.injuries
            //'equipment': profile.equipment
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
            document.getElementById('workout-suggestion-container').innerHTML = "<div style='color: #999; font-style: italic; text-align:center; margin-top: 30px;'>No workout suggestions have been generated yet.</div>";
        }
    });
}

function showAllSuggestions(suggestions) {
    const container = document.getElementById('workout-suggestion-container');
    container.innerHTML = "";
    suggestions.forEach(item => {
        const date = new Date(item.generated_at);
        const suggestion = item.suggestion;
        let workoutList = '';
        if (suggestion.workouts) {
            suggestion.workouts.forEach(workout => {
                workoutList += `<li>${workout}</li>`;
            });
        }
        container.innerHTML += `
            <div class="workout-suggestion" style="margin-top: 20px; padding: 20px; background: white; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                <div style="font-size: 0.9em; color: #888; margin-bottom: 5px;">
                    Generated: ${date.toLocaleString()}
                </div>
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
    });
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
    
    showTemporaryNotification("You can check your workout suggestion in <b>My Workouts</b> section!");
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

function logout() {
    localStorage.removeItem('authToken');
    console.log("Logged out successfully");
    window.location.href = 'login.html';
}