document.addEventListener("DOMContentLoaded", function() {
    const registerForm = document.querySelector(".register-form");
    const loginForm = document.querySelector(".login-form");
    
    let errorElements = {};
    
    if (registerForm) {
        registerForm.addEventListener("submit", function(event) {
            event.preventDefault();
            clearErrors();
            
            let isValid = true;
            const username = document.getElementById("username");
            const email = document.getElementById("email");
            const password = document.getElementById("password");
            const terms = document.getElementById("terms");
            
            if (username.value.trim().length < 3) {
                showError(username, "Username must be at least 3 characters");
                isValid = false;
            }
            
            if (!validateEmail(email.value)) {
                showError(email, "Please enter a valid email address");
                isValid = false;
            }
            
            if (password.value.length < 8) {
                showError(password, "Password must be at least 8 characters");
                isValid = false;
            }
            
            if (!terms.checked) {
                showError(terms, "You must agree to the Terms & Conditions");
                isValid = false;
            }
            
            if (isValid) {
                registerUser(username.value, email.value, password.value);
            }
        });
        const passwordInput = document.getElementById("password");
        if (passwordInput) {
            passwordInput.addEventListener("input", function() {
                updatePasswordStrength(passwordInput.value);
            });
        }
    }

    if (loginForm) {
        loginForm.addEventListener("submit", function(event) {
            event.preventDefault();
            clearErrors();
            
            let isValid = true;
            const username = document.getElementById("username");
            const password = document.getElementById("password");
            
            if (username.value.trim() === "") {
                showError(username, "Please enter your username");
                isValid = false;
            }
            
            if (password.value === "") {
                showError(password, "Please enter your password");
                isValid = false;
            }
            
            if (isValid) {
                loginUser(username.value, password.value);
            }
        });
    }
    
    function validateEmail(email) {
        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    function showError(inputElement, message) {
        if (!errorElements[inputElement.id]) {
            const errorElement = document.createElement("div");
            errorElement.className = "error-message";
            errorElement.style.color = "#e74c3c";
            errorElement.style.fontSize = "12px";
            errorElement.style.marginTop = "5px";
            
            if (inputElement.type === "checkbox") {
                inputElement.parentElement.appendChild(errorElement);
            } else {
                inputElement.parentElement.parentElement.appendChild(errorElement);
            }
            
            errorElements[inputElement.id] = errorElement;
        }
        
        errorElements[inputElement.id].textContent = message;

        if (inputElement.type !== "checkbox") {
            inputElement.style.borderColor = "#e74c3c";
        }
    }
    
    function clearErrors() {
        for (let id in errorElements) {
            if (errorElements[id]) {
                errorElements[id].textContent = "";
            }
            
            const element = document.getElementById(id);
            if (element && element.type !== "checkbox") {
                element.style.borderColor = "";
            }
        }
    }
    
    function updatePasswordStrength(password) {
        if (!document.querySelector(".password-strength")) {
            const passwordInput = document.getElementById("password");
            const strengthIndicator = document.createElement("div");
            strengthIndicator.className = "password-strength";
            strengthIndicator.style.height = "5px";
            strengthIndicator.style.marginTop = "5px";
            strengthIndicator.style.borderRadius = "3px";
            strengthIndicator.style.transition = "all 0.3s";
            
            passwordInput.parentElement.parentElement.appendChild(strengthIndicator);
        }
        
        const strengthIndicator = document.querySelector(".password-strength");
        
        let strength = 0;
        if (password.length >= 8) strength += 1;
        if (password.match(/[A-Z]/)) strength += 1;
        if (password.match(/[0-9]/)) strength += 1;
        if (password.match(/[^a-zA-Z0-9]/)) strength += 1;
        
        switch (strength) {
            case 0:
                strengthIndicator.style.width = "0%";
                strengthIndicator.style.backgroundColor = "";
                break;
            case 1:
                strengthIndicator.style.width = "25%";
                strengthIndicator.style.backgroundColor = "#e74c3c";
                break;
            case 2:
                strengthIndicator.style.width = "50%";
                strengthIndicator.style.backgroundColor = "#f39c12";
                break;
            case 3:
                strengthIndicator.style.width = "75%";
                strengthIndicator.style.backgroundColor = "#f1c40f"; 
                break;
            case 4:
                strengthIndicator.style.width = "100%";
                strengthIndicator.style.backgroundColor = "#2ecc71"; 
                break;
        }
    }
});