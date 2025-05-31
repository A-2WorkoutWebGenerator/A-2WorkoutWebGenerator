const useAjaxSubmit = true;

document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    initializeNavigation();
    initializeProfilePhoto();
    initializeProfileForm();
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
    let suggestionElement = document.querySelector('.workout-suggestion');
    
    if (!suggestionElement) {
        suggestionElement = document.createElement('div');
        suggestionElement.className = 'workout-suggestion';
        suggestionElement.style.marginTop = '30px';
        suggestionElement.style.padding = '20px';
        suggestionElement.style.backgroundColor = 'white';
        suggestionElement.style.borderRadius = '10px';
        suggestionElement.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.05)';
        const profileForm = document.getElementById('profile-form');
        profileForm.parentNode.appendChild(suggestionElement);
    }
    let workoutList = '';
    if (suggestion.workouts) {
        suggestion.workouts.forEach(workout => {
            workoutList += `<li>${workout}</li>`;
        });
    }
    
    suggestionElement.innerHTML = `
        <h3 style="color: #2ecc71; margin-bottom: 10px;">${suggestion.title || 'Your Workout Plan'}</h3>
        <p>${suggestion.description || ''}</p>
        
        <h4>Recommended Workouts:</h4>
        <ul style="margin-top: 15px; padding-left: 20px;">
            ${workoutList}
        </ul>
        
        ${suggestion.intensity ? `<p><strong>Intensity:</strong> ${suggestion.intensity}</p>` : ''}
        ${suggestion.frequency ? `<p><strong>Frequency:</strong> ${suggestion.frequency}</p>` : ''}
        
        ${suggestion.caution ? `<div style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #3498db; font-style: italic;"><strong>Important:</strong> ${suggestion.caution}</div>` : ''}
        ${suggestion.age_note ? `<div style="margin-top: 20px; padding: 15px; background-color: #f9f9f9; border-left: 4px solid #3498db; font-style: italic;">${suggestion.age_note}</div>` : ''}
    `;
    setTimeout(() => {
        suggestionElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 500);
}

function logout() {
    localStorage.removeItem('authToken');
    console.log("Logged out successfully");
    window.location.href = 'login.html';
}