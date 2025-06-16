const API_URL = "http://localhost:8081";

document.getElementById('contact-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fullName = document.getElementById('fullName').value.trim();
    const email = document.getElementById('email').value.trim();
    const message = document.getElementById('message').value.trim();
    
    if (!fullName || !email || !message) {
        showMessage('Please fill in all fields.', 'error');
        return;
    }
    const submitBtn = document.getElementById('submit-btn');
    const originalContent = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Sending...</span>';

    fetch(`${API_URL}/submit_contact.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            fullName: fullName,
            email: email,
            message: message
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message, 'success');
            
            document.getElementById('contact-form').reset();
        
            setTimeout(() => {
                hideMessage();
            }, 5000);
        } else {
            showMessage(data.message || 'Failed to send message. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting contact form:', error);
        showMessage('Connection error. Please try again later.', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalContent;
    });
});

function showMessage(message, type) {
    const container = document.getElementById('message-container');
    container.innerHTML = `<div class="message-container message-${type}">${message}</div>`;
    container.style.display = 'block';
}

function hideMessage() {
    const container = document.getElementById('message-container');
    container.style.display = 'none';
}

function logout() {
    localStorage.removeItem("authToken");
    window.location.href = "WoW.html";
}