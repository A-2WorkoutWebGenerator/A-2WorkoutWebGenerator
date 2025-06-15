const useAjaxSubmit = true;
function compressImage(file, maxWidth = 1200, maxHeight = 1200, quality = 0.8, maxSizeKB = 2000) {
    return new Promise((resolve, reject) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        
        img.onload = () => {
            let { width, height } = img;
            if (width > height) {
                if (width > maxWidth) {
                    height = (height * maxWidth) / width;
                    width = maxWidth;
                }
            } else {
                if (height > maxHeight) {
                    width = (width * maxHeight) / height;
                    height = maxHeight;
                }
            }
            
            canvas.width = width;
            canvas.height = height;
            ctx.drawImage(img, 0, 0, width, height);
            
            let currentQuality = quality;
            
            const tryCompress = () => {
                canvas.toBlob((blob) => {
                    if (!blob) {
                        reject(new Error('Failed to compress image'));
                        return;
                    }
                    
                    const sizeKB = blob.size / 1024;
                    console.log(`Compressed to ${Math.round(sizeKB)}KB with quality ${currentQuality}`);
                    if (sizeKB <= maxSizeKB || currentQuality <= 0.1) {
                        resolve(blob);
                    } else {
                        currentQuality -= 0.1;
                        tryCompress();
                    }
                }, 'image/jpeg', currentQuality);
            };
            
            tryCompress();
        };
        
        img.onerror = () => reject(new Error('Failed to load image'));
        img.src = URL.createObjectURL(file);
    });
}

function checkFileSize(file, maxSizeKB = 2048) {
    const sizeKB = file.size / 1024;
    console.log(`File size: ${Math.round(sizeKB)}KB`);
    return sizeKB <= maxSizeKB;
}
function showCompressionProgress(show = true) {
    let progressContainer = document.getElementById('compression-progress');
    
    if (show) {
        if (!progressContainer) {
            progressContainer = document.createElement('div');
            progressContainer.id = 'compression-progress';
            progressContainer.innerHTML = `
                <div style="
                    position: fixed;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                    z-index: 10000;
                    text-align: center;
                    min-width: 300px;
                ">
                    <div style="margin-bottom: 15px;">
                        <i class="fas fa-compress-arrows-alt" style="font-size: 2em; color: #3498db;"></i>
                    </div>
                    <h3 style="margin: 10px 0; color: #2c3e50;">Compressing Image...</h3>
                    <p style="color: #7f8c8d; margin: 10px 0;">Please wait while we optimize your image</p>
                    <div style="
                        width: 100%;
                        height: 6px;
                        background: #ecf0f1;
                        border-radius: 3px;
                        overflow: hidden;
                        margin: 15px 0;
                    ">
                        <div style="
                            width: 100%;
                            height: 100%;
                            background: linear-gradient(90deg, #3498db, #2980b9);
                            animation: progress 2s ease-in-out infinite;
                        "></div>
                    </div>
                </div>
                <div style="
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    z-index: 9999;
                "></div>
                <style>
                    @keyframes progress {
                        0% { transform: translateX(-100%); }
                        50% { transform: translateX(0%); }
                        100% { transform: translateX(100%); }
                    }
                </style>
            `;
            document.body.appendChild(progressContainer);
        }
        progressContainer.style.display = 'block';
    } else {
        if (progressContainer) {
            progressContainer.style.display = 'none';
            setTimeout(() => {
                if (progressContainer.parentNode) {
                    progressContainer.parentNode.removeChild(progressContainer);
                }
            }, 300);
        }
    }
}
function showCompressionResult(originalSizeKB, finalSizeKB) {
    const savings = Math.round(((originalSizeKB - finalSizeKB) / originalSizeKB) * 100);
    const message = `Image compressed successfully! 
    Original: ${Math.round(originalSizeKB)}KB → Compressed: ${Math.round(finalSizeKB)}KB
    Space saved: ${savings}%`;
    
    showTemporaryNotification(message, 'success');
}

function showTemporaryNotification(message, type = 'info') {
    let notification = document.querySelector('.profile-temp-notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.className = 'profile-temp-notification';
        notification.style.position = "fixed";
        notification.style.top = "30px";
        notification.style.right = "30px";
        notification.style.zIndex = "9999";
        notification.style.padding = "16px 24px";
        notification.style.borderRadius = "8px";
        notification.style.fontWeight = "500";
        notification.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
        notification.style.maxWidth = "400px";
        notification.style.transition = "all 0.3s ease";
        document.body.appendChild(notification);
    }
    
    if (type === 'success') {
        notification.style.background = "#d4edda";
        notification.style.color = "#155724";
        notification.style.border = "1px solid #c3e6cb";
    } else if (type === 'error') {
        notification.style.background = "#f8d7da";
        notification.style.color = "#721c24";
        notification.style.border = "1px solid #f5c6cb";
    } else {
        notification.style.background = "#d1ecf1";
        notification.style.color = "#0c5460";
        notification.style.border = "1px solid #bee5eb";
    }
    
    notification.innerHTML = message.replace(/\n/g, '<br>');
    notification.style.display = "block";
    notification.style.opacity = "1";

    setTimeout(() => {
        notification.style.opacity = "0";
        setTimeout(() => {
            notification.style.display = "none";
        }, 300);
    }, 5000);
}

document.addEventListener('DOMContentLoaded', () => {
    initializeResponsiveMenu()
    checkAuth();
    initializeNavigation();
    initializeProfilePhoto();
    initializeProfileForm(); 
    loadWorkoutSuggestions();
    fetchRSSLink();
    addVideoLinkStyles();
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
    const savedRoutinesLink = document.querySelector('[data-section="saved-routines"]');
    if (savedRoutinesLink) {
        savedRoutinesLink.addEventListener('click', () => {
            loadSavedRoutines();
        });
    }
    initializeSavedRoutinesFilters();

    setTimeout(handleHashOnLoad, 100);
});

function fetchRSSLink() {
    const token = localStorage.getItem("authToken");
    if (!token) return;

    fetch('get-rss-link.php', {
        headers: {
            'Authorization': 'Bearer ' + token
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.rss_link) {
            const rssLink = document.getElementById('rss-link');
            if (rssLink) rssLink.href = data.rss_link;
        }
    });
}

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
    const menuItems = document.querySelectorAll('.nav-link:not(.external-link)');
    const sections = document.querySelectorAll('.section');
    
    menuItems.forEach(item => {
        item.addEventListener('click', (event) => {
            const target = item.getAttribute('data-section');
            if (target === "home") {
                return;
            }
            
            event.preventDefault();
            menuItems.forEach(i => i.classList.remove('active'));
            sections.forEach(s => {
                s.classList.remove('active');
                s.classList.add('hidden');
            });
            item.classList.add('active');
            const targetSection = document.getElementById(target);
            if (targetSection) {
                targetSection.classList.add('active');
                targetSection.classList.remove('hidden');
            }
            if (target === 'workouts') {
                loadWorkoutSuggestions();
            } else if (target === 'stats') {
                loadStatistics();
            } else if (target === 'saved-routines') {
                loadSavedRoutines();
            }
        });
    });
    
    handleHashOnLoad();
}
function handleHashOnLoad() {
    const hash = window.location.hash;
    
    if (hash) {
        console.log('Hash detectat:', hash);
        
        const hashToSection = {
            '#preferences': 'preferences',
            '#account': 'account',
            '#workouts': 'workouts',
            '#saved-routines': 'saved-routines',
            '#stats': 'stats'
        };
        
        const targetSection = hashToSection[hash];
        
        if (targetSection) {
            console.log('Navighez la secțiunea:', targetSection);
            
            const targetLink = document.querySelector(`[data-section="${targetSection}"]`);
            
            if (targetLink) {
                setTimeout(() => {
                    targetLink.click();
                    
                    if (targetSection === 'preferences') {
                        setTimeout(() => {
                            const firstInput = document.querySelector('#muscle_group');
                            if (firstInput) {
                                firstInput.focus();
                                document.getElementById('preferences-form').scrollIntoView({
                                    behavior: 'smooth',
                                    block: 'start'
                                });
                            }
                        }, 200);
                    }
                    window.history.replaceState(null, null, window.location.pathname);
                    
                }, 300);
            }
        }
    }
}
function initializeProfilePhoto() {
    const profilePic = document.getElementById('profile_pic');
    const profilePreview = document.getElementById('profile_preview');
    
    if (profilePic && profilePreview) {
        profilePic.addEventListener('change', async (event) => {
            const file = event.target.files[0];
            if (!file) return;
            
            console.log('File selected:', file.name, 'Size:', Math.round(file.size / 1024) + 'KB');
            
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                showMessage('Please select a valid image file (JPG, PNG, GIF)', 'error');
                event.target.value = '';
                return;
            }
            
            const originalSizeKB = file.size / 1024;
            console.log(`Original image size: ${Math.round(originalSizeKB)}KB`);
            
            let processedFile = file;
        
            if (!checkFileSize(file, 2048)) {
                try {
                    console.log('File too large, starting compression...');
                    showCompressionProgress(true);
                    
                    console.log('Trying compression level 1...');
                    processedFile = await compressImage(file, 1200, 1200, 0.8, 2000);
                    
                    if (!checkFileSize(processedFile, 2048)) {
                        console.log('Still too large, trying compression level 2...');
                        processedFile = await compressImage(file, 1000, 1000, 0.6, 1800);
                    }

                    if (!checkFileSize(processedFile, 2048)) {
                        console.log('Still too large, trying compression level 3...');
                        processedFile = await compressImage(file, 800, 800, 0.4, 1500);
                    }
                    
                    showCompressionProgress(false);
                    
                    const finalSizeKB = processedFile.size / 1024;
                    console.log(`Final compressed size: ${Math.round(finalSizeKB)}KB`);
                    
                    if (!checkFileSize(processedFile, 2048)) {
                        showMessage('Unable to compress image enough. Please try a different image or reduce quality manually.', 'error');
                        event.target.value = '';
                        return;
                    }
                    
                    showCompressionResult(originalSizeKB, finalSizeKB);
                    
                } catch (error) {
                    showCompressionProgress(false);
                    console.error('Compression error:', error);
                    showMessage('Error compressing image. Please try a smaller file.', 'error');
                    event.target.value = '';
                    return;
                }
            } else {
                console.log('Image size OK, no compression needed');
            }
            const reader = new FileReader();
            reader.onload = (e) => {
                profilePreview.src = e.target.result;
            };
            
            if (processedFile !== file) {
                reader.readAsDataURL(processedFile);
                const dt = new DataTransfer();
                const compressedFile = new File([processedFile], 
                    file.name.replace(/\.[^/.]+$/, '_compressed.jpg'), {
                    type: 'image/jpeg',
                    lastModified: Date.now()
                });
                dt.items.add(compressedFile);
                event.target.files = dt.files;
                
                console.log('File replaced with compressed version');
            } else {
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
            'weight': profile.weight,
            'goal': profile.goal,
            'injuries': profile.injuries
        };
        if (profile.age) {
            const currentYear = new Date().getFullYear();
            const birthYear = currentYear - profile.age;
            const birthYearField = document.getElementById('birth_year');
            if (birthYearField) {
                birthYearField.value = birthYear;
            }
        }
        
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

function calculateAgeFromBirthYear(birthYear) {
    const currentYear = new Date().getFullYear();
    return currentYear - birthYear;
}
function initializeProfileForm() {
    const profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (useAjaxSubmit) {
                e.preventDefault();

                const formData = new FormData(profileForm);
                const birthYear = document.getElementById('birth_year').value;
                if (birthYear) {
                    const age = calculateAgeFromBirthYear(parseInt(birthYear));
                    formData.append('age', age);
                }

                const authToken = localStorage.getItem("authToken");
                if (authToken && !formData.has('auth_token')) {
                    formData.append('auth_token', authToken);
                }
                
                const submitButton = profileForm.querySelector('button[type="submit"]');
                const originalText = submitButton.textContent;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                let totalSize = 0;
                for (let pair of formData.entries()) {
                    if (pair[1] instanceof File) {
                        totalSize += pair[1].size;
                    } else {
                        totalSize += new Blob([pair[1]]).size;
                    }
                }
                
                const totalSizeKB = totalSize / 1024;
                console.log(`Total form size: ${Math.round(totalSizeKB)}KB`);
                if (totalSizeKB > 2048) {
                    showMessage('Form data is too large. Please use a smaller image.', 'error');
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                    return;
                }
                
                fetch('submit-profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            showMessage(data.message || "Profile saved successfully!", "success");
                            loadUserProfile();
                        } else {
                            showMessage(data.message || "Error saving profile!", "error");
                        }
                    } catch (jsonError) {
                        console.error("JSON parse error:", jsonError);
                        console.error("Raw response:", text);
                        
                        if (text.includes('POST Content-Length') || text.includes('exceeds the limit')) {
                            showMessage('Image is too large for server. Please try a smaller image.', 'error');
                        } else if (text.includes('headers already sent')) {
                            showMessage('Server configuration error. Please try again.', 'error');
                        } else {
                            showMessage('Server error occurred. Please try again.', 'error');
                        }
                    }
                })
                .catch(error => {
                    console.error("Error submitting profile:", error);
                    if (error.message.includes('413')) {
                        showMessage('Image too large. Please compress further.', 'error');
                    } else if (error.message.includes('500')) {
                        showMessage('Server error. Please try again later.', 'error');
                    } else {
                        showMessage("Connection error. Please try again later.", "error");
                    }
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
                    ${exercise.video_url ? `<br><a href="${exercise.video_url}" target="_blank" class="watch-video-link">
                        <i class="fab fa-youtube"></i> Watch Video
                    </a>` : ''}
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
    
    addVideoLinkStyles();
}
function addVideoLinkStyles() {
    if (!document.getElementById('watch-video-styles')) {
        const styles = document.createElement('style');
        styles.id = 'watch-video-styles';
        styles.textContent = `
            .watch-video-link {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                background: linear-gradient(135deg, #ff4757 0%, #ff3742 100%);
                color: white !important;
                text-decoration: none !important;
                border-radius: 6px;
                font-weight: 500;
                font-size: 0.85rem;
                margin-top: 8px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 6px rgba(255, 71, 87, 0.25);
            }
            
            .watch-video-link:hover {
                background: linear-gradient(135deg, #ff3742 0%, #ff2f3a 100%);
                transform: translateY(-1px);
                box-shadow: 0 3px 10px rgba(255, 71, 87, 0.35);
                color: white !important;
                text-decoration: none !important;
            }
            
            .watch-video-link:active {
                transform: translateY(0px);
            }
            
            .watch-video-link .fab {
                font-size: 1em;
                color: white;
            }
            
            .watch-video-link:visited {
                color: white !important;
            }
        `;
        document.head.appendChild(styles);
    }
}


function generateWorkout() {
    const token = localStorage.getItem("authToken");
    const muscle_group = document.getElementById('muscle_group').value;
    const intensity = document.getElementById('intensity').value;
    const duration = document.getElementById('duration').value;
    const equipment = document.getElementById('equipment_pref').value;
    const location = document.getElementById('location').value;
    if (!duration || duration < 10) {
        showMessage('Please enter a valid workout duration (minimum 10 minutes)', 'error');
        return;
    }
    const submitButton = document.querySelector('#preferences-form button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';

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
            showTemporaryNotification('Workout generated successfully! Redirecting to My Workouts...', 'success');
            if (data.workout) {
                localStorage.setItem('latestWorkout', JSON.stringify(data.workout));
            }
            setTimeout(() => {
                document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
                document.querySelectorAll('.section').forEach(section => section.classList.remove('active'));
                
                const workoutsLink = document.querySelector('[data-section="workouts"]');
                const workoutsSection = document.getElementById('workouts');
                
                if (workoutsLink) workoutsLink.classList.add('active');
                if (workoutsSection) workoutsSection.classList.add('active');
                loadWorkoutSuggestions();
                window.scrollTo({ top: 0, behavior: 'smooth' });
                
            }, 1500);
            
        } else {
            showMessage(data.message || 'Error generating workout', "error");
        }
    })
    .catch(error => {
        console.error('Error generating workout:', error);
        showMessage('Connection error. Please try again.', 'error');
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
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
function initializeResponsiveMenu() {
    if (!document.querySelector('.mobile-header')) {
        const mobileHeader = document.createElement('div');
        mobileHeader.className = 'mobile-header';
        mobileHeader.innerHTML = `
            <div class="mobile-logo">
                <a href="WoW-Logged.html">FitGen</a>
            </div>
            <button class="hamburger" id="hamburger">
                <i class="fas fa-bars"></i>
            </button>
        `;
        const dashboard = document.querySelector('.dashboard');
        dashboard.insertBefore(mobileHeader, dashboard.firstChild);
    }
    
    if (!document.querySelector('.overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'overlay';
        overlay.id = 'overlay';
        document.body.appendChild(overlay);
    }
    
    const hamburger = document.getElementById('hamburger');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('overlay');
    
    function toggleMenu() {
        hamburger.classList.toggle('active');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
    }
    
    function closeMenu() {
        hamburger.classList.remove('active');
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', toggleMenu);
    overlay.addEventListener('click', closeMenu);
    
    document.querySelectorAll('.nav-link:not(.external-link)').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeMenu();
            }
        });
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeMenu();
        }
    });
}
function loadSavedRoutines() {
    const token = localStorage.getItem("authToken");
    const container = document.getElementById('saved-routines-container');
    const noRoutinesMessage = document.getElementById('no-saved-routines');

    container.innerHTML = `
        <div class="loading-saved-routines" style="grid-column: 1 / -1; text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2em; color: #3498db; margin-bottom: 15px;"></i>
            <p>Loading your saved routines...</p>
        </div>
    `;
    
    fetch('get-saved-routines.php', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.routines && data.routines.length > 0) {
            updateCategoryFilter(data.categories || []);
            displayRoutineStats(data.stats, data.category_counts);
            
            displaySavedRoutines(data.routines);
            container.style.display = 'grid';
            noRoutinesMessage.style.display = 'none';
        } else {
            container.style.display = 'none';
            noRoutinesMessage.style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error loading saved routines:', error);
        container.innerHTML = `
            <div class="error-message" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #e74c3c;">
                <i class="fas fa-exclamation-triangle" style="font-size: 2em; margin-bottom: 15px;"></i>
                <p>Error loading saved routines. Please try again later.</p>
            </div>
        `;
    });
    attachRemoveListeners();
}
function updateCategoryFilter(availableCategories) {
    const categoryFilter = document.getElementById('category-filter');
    if (!categoryFilter) return;

    const allOption = categoryFilter.querySelector('option[value="all"]');
    categoryFilter.innerHTML = '';
    categoryFilter.appendChild(allOption);

    const categoryNames = {
        'kinetotherapy': 'Kinetotherapy',
        'physiotherapy': 'Physiotherapy',
        'football': 'Football', 
        'basketball': 'Basketball',
        'tennis': 'Tennis',
        'swimming': 'Swimming',
        'general': 'General Workout'
    };
    
    availableCategories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = categoryNames[category] || category;
        categoryFilter.appendChild(option);
    });
}
function updateCategoryFilter(availableCategories) {
    const categoryFilter = document.getElementById('category-filter');
    if (!categoryFilter) return;

    const allOption = categoryFilter.querySelector('option[value="all"]');
    categoryFilter.innerHTML = '';
    categoryFilter.appendChild(allOption);

    const categoryNames = {
        'kinetotherapy': 'Kinetotherapy',
        'physiotherapy': 'Physiotherapy',
        'football': 'Football', 
        'basketball': 'Basketball',
        'tennis': 'Tennis',
        'swimming': 'Swimming',
        'general': 'General Workout'
    };
    
    availableCategories.forEach(category => {
        const option = document.createElement('option');
        option.value = category;
        option.textContent = categoryNames[category] || category;
        categoryFilter.appendChild(option);
    });
}

function displayRoutineStats(stats, categoryCounts) {
    const savedRoutinesHeader = document.querySelector('.saved-routines-header');

    const existingStats = savedRoutinesHeader.querySelector('.routine-stats');
    if (existingStats) {
        existingStats.remove();
    }
    
    if (!stats) return;
    
    const statsDiv = document.createElement('div');
    statsDiv.className = 'routine-stats';
    statsDiv.innerHTML = `
        <div class="stats-container">
            <div class="stat-item">
                <span class="stat-number">${stats.total_routines}</span>
                <span class="stat-label">Total Routines</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">${stats.unique_categories}</span>
                <span class="stat-label">Categories</span>
            </div>
            ${stats.most_saved_category ? `
                <div class="stat-item">
                    <span class="stat-number">${categoryCounts[stats.most_saved_category]}</span>
                    <span class="stat-label">Most: ${getCategoryDisplayName(stats.most_saved_category)}</span>
                </div>
            ` : ''}
        </div>
    `;

    statsDiv.style.cssText = `
        margin-top: 20px;
        padding: 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        border: 1px solid #dee2e6;
    `;
    
    savedRoutinesHeader.appendChild(statsDiv);

    if (!document.getElementById('routine-stats-styles')) {
        const styles = document.createElement('style');
        styles.id = 'routine-stats-styles';
        styles.textContent = `
            .stats-container {
                display: flex;
                justify-content: center;
                gap: 30px;
                flex-wrap: wrap;
            }
            
            .stat-item {
                text-align: center;
                min-width: 80px;
            }
            
            .stat-number {
                display: block;
                font-size: 1.8em;
                font-weight: bold;
                color: #18D259;
                margin-bottom: 5px;
            }
            
            .stat-label {
                font-size: 0.9em;
                color: #6c757d;
                font-weight: 500;
            }
            
            @media (max-width: 600px) {
                .stats-container {
                    gap: 20px;
                }
                
                .stat-number {
                    font-size: 1.5em;
                }
                
                .stat-label {
                    font-size: 0.8em;
                }
            }
        `;
        document.head.appendChild(styles);
    }
}

function getCategoryDisplayName(category) {
    const categoryNames = {
        'kinetotherapy': 'Kinetotherapy',
        'physiotherapy': 'Physiotherapy',
        'football': 'Football',
        'basketball': 'Basketball', 
        'tennis': 'Tennis',
        'swimming': 'Swimming',
        'general': 'General'
    };
    
    return categoryNames[category] || category;
}

function displaySavedRoutines(routines) {
    const container = document.getElementById('saved-routines-container');
    
    container.innerHTML = routines.map(routine => `
        <div class="saved-routine-card" data-category="${routine.category}" data-difficulty="${routine.difficulty}">
            <div class="routine-header">
                <div class="routine-icon">
                    <i class="${routine.icon}"></i>
                </div>
                <h3>${routine.name}</h3>
                <div class="category-badge">
                    <i class="fas fa-tag"></i>
                    <span>${routine.category_display || getCategoryDisplayName(routine.category)}</span>
                </div>
                <div class="difficulty">
                    <span class="difficulty-label">${routine.difficulty}</span>
                    <div class="difficulty-meter">
                        ${generateDifficultyMeter(routine.difficulty)}
                    </div>
                </div>
                <div class="saved-badge">
                    <i class="fas fa-bookmark"></i>
                    <span>Saved</span>
                </div>
            </div>
            
            <div class="routine-body">
                <p>${routine.description}</p>
                <ul class="exercise-list">
                    ${routine.exercises.map(exercise => 
                        `<li><i class="fas fa-check"></i> ${exercise}</li>`
                    ).join('')}
                </ul>
                <div class="routine-meta">
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span>${routine.duration}</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar-day"></i>
                        <span>${routine.frequency}</span>
                    </div>
                </div>
                <div class="saved-info">
                    <small><i class="fas fa-calendar-plus"></i> Saved on ${formatSavedDate(routine.saved_at)}</small>
                </div>
            </div>
            
            <div class="routine-footer">
                <a href="${routine.video_url}" target="_blank" 
                   class="btn btn-primary"
                   style="color: white !important; background-color: #18D259 !important; text-decoration: none !important;"
                   onmouseover="this.style.backgroundColor='#3fcb70';"
                   onmouseout="this.style.backgroundColor='#18D259';">
                    <i class="fab fa-youtube" style="color: white !important;"></i> Watch Tutorial
                </a>
                <button class="btn btn-outline remove-routine" data-routine-id="${routine.id}">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
        </div>
    `).join('');
    const removeButtons = container.querySelectorAll('.remove-routine');
    removeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const routineId = this.getAttribute('data-routine-id');
            const routineName = this.closest('.saved-routine-card').querySelector('h3').textContent;
            showRemoveConfirmation(routineId, routineName);
        });
    });
}

function generateDifficultyMeter(difficulty) {
    const levels = {
        'Beginner': 1,
        'Intermediate': 2, 
        'Advanced': 3,
        'All Levels': 2
    };
    
    const level = levels[difficulty] || 1;
    let meter = '';
    
    for (let i = 1; i <= 3; i++) {
        meter += `<span${i <= level ? ' class="active"' : ''}></span>`;
    }
    
    return meter;
}

function formatSavedDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function showRemoveConfirmation(routineId, routineName) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay remove-routine-modal';
    modal.innerHTML = `
        <div class="modal-container">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Remove Routine</h3>
                <button class="modal-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to remove <strong>"${routineName}"</strong> from your saved routines?</p>
                <p style="color: #666; font-size: 0.9em; margin-top: 10px;">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline modal-cancel">Cancel</button>
                <button class="btn btn-danger modal-confirm">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    
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
        removeRoutine(routineId);
        closeModal();
    });
}

function removeRoutine(routineId) {
    const token = localStorage.getItem("authToken");
    
    fetch('remove-routine.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ routine_id: routineId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showTemporaryNotification('Routine removed successfully!', 'success');
            loadSavedRoutines(); 
        } else {
            showTemporaryNotification(data.message || 'Failed to remove routine', 'error');
        }
    })
    .catch(error => {
        console.error('Error removing routine:', error);
        showTemporaryNotification('Connection error. Please try again.', 'error');
    });
}

function initializeSavedRoutinesFilters() {
    const categoryFilter = document.getElementById('category-filter');
    const difficultyFilter = document.getElementById('difficulty-filter');
    const clearFiltersBtn = document.getElementById('clear-filters');
    
    if (categoryFilter) {
        categoryFilter.addEventListener('change', applySavedRoutinesFilters);
    }
    
    if (difficultyFilter) {
        difficultyFilter.addEventListener('change', applySavedRoutinesFilters);
    }
    
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            categoryFilter.value = 'all';
            difficultyFilter.value = 'all';
            applySavedRoutinesFilters();
        });
    }
}

function applySavedRoutinesFilters() {
    const categoryFilter = document.getElementById('category-filter').value;
    const difficultyFilter = document.getElementById('difficulty-filter').value;
    const routineCards = document.querySelectorAll('.saved-routine-card');
    
    let visibleCount = 0;
    
    routineCards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');
        const cardDifficulty = card.getAttribute('data-difficulty');
        
        const categoryMatch = categoryFilter === 'all' || cardCategory === categoryFilter;
        const difficultyMatch = difficultyFilter === 'all' || cardDifficulty === difficultyFilter;
        
        if (categoryMatch && difficultyMatch) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    const container = document.getElementById('saved-routines-container');
    let noResultsMessage = container.querySelector('.no-filter-results');
    
    if (visibleCount === 0 && routineCards.length > 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-filter-results';
            noResultsMessage.style.cssText = `
                grid-column: 1 / -1;
                text-align: center;
                padding: 40px;
                color: #666;
            `;
            noResultsMessage.innerHTML = `
                <i class="fas fa-filter" style="font-size: 2em; margin-bottom: 15px;"></i>
                <p>No routines match your current filters.</p>
                <button id="reset-filters" class="btn btn-outline btn-sm" style="margin-top: 10px;">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            `;
            container.appendChild(noResultsMessage);
            const resetBtn = noResultsMessage.querySelector('#reset-filters');
            resetBtn.addEventListener('click', function() {
                document.getElementById('category-filter').value = 'all';
                document.getElementById('difficulty-filter').value = 'all';
                applySavedRoutinesFilters();
            });
        }
        noResultsMessage.style.display = 'block';
    } else if (noResultsMessage) {
        noResultsMessage.style.display = 'none';
    }
}
function attachRemoveListeners() {
    const container = document.getElementById('saved-routines-container');
    
    if (!container) return;
    container.removeEventListener('click', handleRemoveClick);
    container.addEventListener('click', handleRemoveClick);
}

function handleRemoveClick(event) {
    const button = event.target.closest('.remove-routine');
    
    if (!button) return;
    
    event.preventDefault();
    event.stopPropagation();
    const routineId = button.getAttribute('data-routine-id');
    const routineName = button.closest('.saved-routine-card').querySelector('h3').textContent;
    
    if (!routineId) {
        alert('Error: No routine ID found');
        return;
    }
    if (confirm(`Are you sure you want to remove "${routineName}"?`)) {
        removeRoutineQuick(routineId, routineName);
    }
}

function removeRoutineQuick(routineId, routineName) {
    const token = localStorage.getItem("authToken");
    
    if (!token) {
        alert('Please login again');
        return;
    }
    const button = document.querySelector(`[data-routine-id="${routineId}"]`);
    if (button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
        button.disabled = true;
    }
    
    fetch('remove-routine.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ routine_id: routineId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Routine removed successfully!');
            loadSavedRoutines();
        } else {
            alert('Error: ' + (data.message || 'Failed to remove routine'));
            if (button) {
                button.innerHTML = '<i class="fas fa-trash"></i> Remove';
                button.disabled = false;
            }
        }
    })
    .catch(error => {
        alert('Connection error. Please try again.');
        if (button) {
            button.innerHTML = '<i class="fas fa-trash"></i> Remove';
            button.disabled = false;
        }
    });
}
