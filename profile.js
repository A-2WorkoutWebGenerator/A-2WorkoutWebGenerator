document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    initializeNavigation();
    
    initializeProfilePhoto();
    initializeProfileForm();
});

function checkAuth() {
    const userData = localStorage.getItem("user");
    const token = localStorage.getItem("authToken");
    
    if (!token) {
        window.location.href = 'login.html';
        return;
    }
    let user;
    try {
        user = userData ? JSON.parse(userData) : null;
    } catch (e) {
        console.error("Error parsing user data:", e);
        user = null;
    }
    if (user && user.username) {
        const welcomeSection = document.getElementById('sidebar-username');
        if (welcomeSection) {
            welcomeSection.textContent = `Welcome, ${user.username}!`;
        }
        const profileTitle = document.getElementById('profile-title');
        if (profileTitle) {
            profileTitle.textContent = `${user.username}'s Profile`;
        }
    } else {
        const username = localStorage.getItem("username");
        if (username) {
            const welcomeSection = document.getElementById('sidebar-username');
            if (welcomeSection) {
                welcomeSection.textContent = `Welcome, ${username}!`;
            }
            const profileTitle = document.getElementById('profile-title');
            if (profileTitle) {
                profileTitle.textContent = `${username}'s Profile`;
            }
        }
    }
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

function populateEmailField() {
    const userData = localStorage.getItem("user");
    if (userData) {
        try {
            const user = JSON.parse(userData);
            if (user && user.email) {
                const emailField = document.getElementById('email');
                if (emailField) {
                    emailField.value = user.email;
                }
            }
        } catch (e) {
            console.error("Error parsing user data:", e);
        }
    } else {
        const email = localStorage.getItem("email");
        if (email) {
            const emailField = document.getElementById('email');
            if (emailField) {
                emailField.value = email;
            }
        }
    }
}

function loadUserProfile() {
    const authToken = localStorage.getItem("authToken");
    console.log("Auth token from localStorage:", authToken);
    if (!authToken) {
        console.error("No auth token found in localStorage");
        return;
    }
    const authTokenInput = document.getElementById('auth_token');
    if (authTokenInput) {
        authTokenInput.value = authToken;
    }
    const userData = localStorage.getItem("user");
    console.log("User data from localStorage:", userData);
    
    let userId = null;
    try {
        const user = JSON.parse(userData);
        userId = user?.id;
    } catch (e) {
        console.error("Error parsing user data:", e);
    }
    if (!userId) {
        console.error("No user ID found in localStorage");
        return;
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
    
    const data = { user_id: userId };
    console.log("Sending data:", data);
    console.log("Auth headers:", { Authorization: `Bearer ${authToken}` });
    fetch('get-profile.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${authToken}`
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log("Server response status:", response.status);
        console.log("Server response headers:", Object.fromEntries([...response.headers]));
        return response.json();
    })
    .then(data => {
        console.log("Server response data:", data);
        
        if (data.success) {
            populateFormWithData(data);
        } else {
            console.warn("Profile load failed:", data.message);
            console.log("Trying alternative method with token in body...");
            return fetch('get-profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    auth_token: authToken
                })
            })
            .then(response => response.json())
            .then(altData => {
                console.log("Alternative method response:", altData);
                
                if (altData.success) {
                    populateFormWithData(altData);
                } else {
                    console.error("Both methods failed. Unable to load profile data.");
                }
            });
        }
    })
    .catch(error => {
        console.error("Error during profile fetch:", error);
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
        console.log("Populating form with profile data");
        const profile = data.profile;

        const fields = {
            'first_name': profile.first_name,
            'last_name': profile.last_name,
            'gender': profile.gender,
            'age': profile.age,
            'goal': profile.goal,
            'activity_level': profile.activity_level,
            'injuries': profile.injuries,
            'equipment': profile.equipment
        };
        
        for (const [fieldId, value] of Object.entries(fields)) {
            const field = document.getElementById(fieldId);
            if (field && value) field.value = value;
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
    } else {
        const userData = localStorage.getItem("user");
        try {
            const user = JSON.parse(userData);
            if (user && user.email) {
                const emailField = document.getElementById('email');
                if (emailField) emailField.value = user.email;
            }
        } catch (e) {
            console.error("Error parsing user data for email:", e);
        }
    }
}

function initializeProfileForm() {
    const profileForm = document.getElementById('profile-form');
    
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (useAjaxSubmit) {
                e.preventDefault();

                const formData = new FormData(profileForm);
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
    localStorage.removeItem('user');
    localStorage.removeItem('username');
    localStorage.removeItem('email');
    console.log("Logged out successfully");
    window.location.href = 'login.html';
}
const useAjaxSubmit = false;