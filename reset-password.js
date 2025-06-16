document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    
    if (!token) {
        showError('No reset token provided');
        return;
    }
    verifyToken(token);
    
    const form = document.getElementById('reset-form');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const submitBtn = document.getElementById('submit-btn');
    
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
    
    passwordInput.addEventListener('input', function() {
        validatePassword(this.value);
        checkFormValidity();
    });
    
    confirmPasswordInput.addEventListener('input', function() {
        checkFormValidity();
    });
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        if (password !== confirmPassword) {
            showMessage('Passwords do not match', 'error');
            return;
        }
        
        if (!isPasswordValid(password)) {
            showMessage('Password does not meet requirements', 'error');
            return;
        }
        
        resetPassword(token, password);
    });
    
    function verifyToken(token) {
        fetch('verify-reset-token.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ token: token })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                showError(data.message || 'Invalid or expired reset token');
            }
        })
        .catch(error => {
            console.error('Error verifying token:', error);
            showError('Error verifying reset token');
        });
    }
    
    function resetPassword(token, password) {
        const formWrapper = document.querySelector('.form-wrapper');
        formWrapper.classList.add('loading');
        submitBtn.disabled = true;
        
        fetch('reset-password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                token: token, 
                password: password 
            })
        })
        .then(response => response.json())
        .then(data => {
            formWrapper.classList.remove('loading');
            submitBtn.disabled = false;
            
            if (data.success) {
                showSuccess();
            } else {
                showMessage(data.message || 'Failed to reset password', 'error');
            }
        })
        .catch(error => {
            formWrapper.classList.remove('loading');
            submitBtn.disabled = false;
            console.error('Error:', error);
            showMessage('Connection error. Please try again.', 'error');
        });
    }
    
    function validatePassword(password) {
        const requirements = {
            'req-length': password.length >= 8,
            'req-upper': /[A-Z]/.test(password),
            'req-lower': /[a-z]/.test(password),
            'req-number': /\d/.test(password),
            'req-special': /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        let validCount = 0;
        
        Object.entries(requirements).forEach(([id, isValid]) => {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            
            if (isValid) {
                element.classList.add('valid');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-check');
                validCount++;
            } else {
                element.classList.remove('valid');
                icon.classList.remove('fa-check');
                icon.classList.add('fa-times');
            }
        });
        
        const strengthFill = document.querySelector('.strength-fill');
        const strengthText = document.querySelector('.strength-text');
        const percentage = (validCount / 5) * 100;
        
        strengthFill.style.width = percentage + '%';
        
        if (percentage < 40) {
            strengthFill.style.background = '#dc3545';
            strengthText.textContent = 'Weak password';
            strengthText.style.color = '#dc3545';
        } else if (percentage < 80) {
            strengthFill.style.background = '#ffc107';
            strengthText.textContent = 'Medium password';
            strengthText.style.color = '#ffc107';
        } else {
            strengthFill.style.background = '#28a745';
            strengthText.textContent = 'Strong password';
            strengthText.style.color = '#28a745';
        }
        
        return validCount === 5;
    }
    
    function isPasswordValid(password) {
        return password.length >= 8 &&
               /[A-Z]/.test(password) &&
               /[a-z]/.test(password) &&
               /\d/.test(password) &&
               /[!@#$%^&*(),.?":{}|<>]/.test(password);
    }
    
    function checkFormValidity() {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;
        
        const isValid = isPasswordValid(password) && 
                       password === confirmPassword && 
                       password.length > 0;
        
        submitBtn.disabled = !isValid;
    }
    
    function showSuccess() {
        document.getElementById('reset-form').style.display = 'none';
        document.getElementById('success-message').style.display = 'block';
    }
    
    function showError(message) {
        document.getElementById('reset-form').style.display = 'none';
        document.getElementById('error-message').style.display = 'block';
        
        const errorContent = document.querySelector('.error-content p');
        if (errorContent) {
            errorContent.textContent = message;
        }
    }
    
    function showMessage(message, type) {
        let messageEl = document.querySelector('.auth-message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'auth-message';
            messageEl.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                font-weight: 500;
                z-index: 10000;
                max-width: 300px;
                transition: all 0.3s ease;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            `;
            document.body.appendChild(messageEl);
        }
        
        if (type === 'success') {
            messageEl.style.background = '#d4edda';
            messageEl.style.color = '#155724';
            messageEl.style.border = '1px solid #c3e6cb';
        } else {
            messageEl.style.background = '#f8d7da';
            messageEl.style.color = '#721c24';
            messageEl.style.border = '1px solid #f5c6cb';
        }

        messageEl.textContent = message;
        messageEl.style.opacity = '1';
        
        setTimeout(() => {
            messageEl.style.opacity = '0';
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.parentNode.removeChild(messageEl);
                }
            }, 300);
        }, 5000);
    }
});