const API_URL = "http://localhost:8081";

function registerUser(username, email, password) {
    toggleLoadingState(true, "Creating account...");
        const userData = {
        username: username,
        email: email,
        password: password
    };
    
    fetch(`${API_URL}/register.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(userData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage("Account created successfully! Redirecting to login...", "success");

            setTimeout(() => {
                window.location.href = "login.html";
            }, 2000);
        } else {
            showMessage(data.message || "Registration failed. Please try again.", "error");
        }
    })
    .catch(error => {
        showMessage("Connection error. Please try again later.", "error");
        console.error("API Error:", error);
    })
    .finally(() => {
        toggleLoadingState(false);
    });
}

function loginUser(username, password) {
    toggleLoadingState(true, "Logging in...");
    const loginData = {
        username: username,
        password: password
    };
    
    fetch(`${API_URL}/login.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(loginData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage("Login successful! Redirecting...", "success");
            
            if (data.token) {
                localStorage.setItem("authToken", data.token);
                localStorage.setItem("user", JSON.stringify(data.user));
            }
            
            setTimeout(() => {
                window.location.href = "WoW-Logged.html";
            }, 1500);
        } else {
            showMessage(data.message || "Login failed. Please check your credentials.", "error");
        }
    })
    .catch(error => {
        showMessage("Connection error. Please try again later.", "error");
        console.error("API Error:", error);
    })
    .finally(() => {
        toggleLoadingState(false);
    });
}

function toggleLoadingState(isLoading, message = "Loading...") {
    const submitBtn = document.querySelector(".submit-btn");
    
    if (!submitBtn) return;
    
    if (isLoading) {
        if (!submitBtn.getAttribute("data-original-text")) {
            submitBtn.setAttribute("data-original-text", submitBtn.querySelector("span").textContent);
        }
        
        submitBtn.disabled = true;
        submitBtn.querySelector("span").textContent = message;
    } else {
        submitBtn.disabled = false;
        submitBtn.querySelector("span").textContent = submitBtn.getAttribute("data-original-text") || "Submit";
    }
}

function showMessage(message, type) {
    let messageElement = document.querySelector(".message-container");
    
    if (!messageElement) {
        messageElement = document.createElement("div");
        messageElement.className = "message-container";
        messageElement.style.padding = "12px";
        messageElement.style.marginTop = "20px";
        messageElement.style.borderRadius = "5px";
        messageElement.style.textAlign = "center";
        messageElement.style.fontWeight = "500";
        messageElement.style.transition = "all 0.3s";
        
        const form = document.querySelector("form");
        if (form) {
            form.parentNode.insertBefore(messageElement, form.nextSibling);
        } else {
            document.querySelector(".form-wrapper").appendChild(messageElement);
        }
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

    messageElement.style.display = "block";
    messageElement.style.opacity = "1";

    setTimeout(() => {
        messageElement.style.opacity = "0";
        setTimeout(() => {
            messageElement.style.display = "none";
        }, 300);
    }, 5000);
}