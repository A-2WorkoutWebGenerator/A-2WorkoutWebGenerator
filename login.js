function handleCredentialResponse(response) {
    console.log("Encoded JWT ID token: " + response.credential);
    showGoogleLoading(true);

    fetch('google-login.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            credential: response.credential
        })
    })
    .then(response => response.json())
    .then(data => {
        showGoogleLoading(false);
        
        if (data.success) {
            localStorage.setItem('authToken', data.token);
            showMessage('Login successful! Redirecting...', 'success');
            setTimeout(() => {
                window.location.href = 'WoW-Logged.html';
            }, 1500);
        } else {
            showMessage(data.message || 'Google login failed', 'error');
        }
    })
    .catch(error => {
        showGoogleLoading(false);
        console.error('Error:', error);
        showMessage('Connection error during Google login', 'error');
    });
}

function showGoogleLoading(show) {
    const formWrapper = document.querySelector('.form-wrapper');
    
    if (show) {
        if (formWrapper) formWrapper.classList.add('google-loading');
    } else {
        if (formWrapper) formWrapper.classList.remove('google-loading');
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
window.onload = function () {
    google.accounts.id.initialize({
        client_id: "452342585871-p9ofgvju1jnjdg1u6mh3urllevoatta0.apps.googleusercontent.com",
        callback: handleCredentialResponse
    });
};