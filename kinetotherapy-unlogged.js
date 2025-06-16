document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navLinks = document.querySelector('.nav-links');
    
    if (mobileToggle && navLinks) {
        mobileToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }
    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        const toggleIcon = item.querySelector('.faq-toggle i');
        answer.style.maxHeight = '0';
        answer.style.overflow = 'hidden';
        answer.style.transition = 'max-height 0.5s ease, padding 0.5s ease';
        
        question.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            faqItems.forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                    otherItem.querySelector('.faq-answer').style.maxHeight = '0';
                    otherItem.querySelector('.faq-toggle i').className = 'fas fa-plus';
                }
            });
            
            if (isActive) {
                item.classList.remove('active');
                answer.style.maxHeight = '0';
                toggleIcon.className = 'fas fa-plus';
            } else {
                item.classList.add('active');
                answer.style.maxHeight = answer.scrollHeight + 'px';
                toggleIcon.className = 'fas fa-minus';
            }
        });
    });
    const saveButtons = document.querySelectorAll('.save-routine');
    
    saveButtons.forEach(button => {
        button.addEventListener('click', function() {
            const routineName = this.closest('.routine-card').querySelector('h3').textContent;
            showLoginRequiredModal(routineName);
        });
    });
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const target = document.querySelector(this.getAttribute('href'));
            
            if (target) {
                e.preventDefault();
                window.scrollTo({
                    top: target.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });

    let lastScrollTop = 0;
    const navbar = document.querySelector('.navbar');
    const navbarHeight = navbar.offsetHeight;
    
    window.addEventListener('scroll', function() {
        let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > navbarHeight) {
            navbar.style.transform = 'translateY(-100%)';
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        if (scrollTop > 10) {
            navbar.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.1)';
        } else {
            navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.05)';
        }
        
        lastScrollTop = scrollTop;
    });
});
function showLoginRequiredModal(routineName) {
    const modal = document.createElement('div');
    Object.assign(modal.style, {
        position: 'fixed',
        top: '0',
        left: '0',
        right: '0',
        bottom: '0',
        backgroundColor: 'rgba(0, 0, 0, 0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: '9999',
        opacity: '0',
        transition: 'opacity 0.3s ease',
        padding: '20px'
    });
    
    modal.innerHTML = `
        <div style="
            background-color: white;
            border-radius: 10px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            transform: scale(0.8);
            transition: transform 0.3s ease;
            overflow: hidden;
        ">
            <div style="
                padding: 20px;
                border-bottom: 1px solid rgba(0, 0, 0, 0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
            ">
                <h3 style="
                    margin: 0;
                    color: #18D259;
                    font-size: 1.3rem;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-family: 'Segoe UI', 'Roboto', sans-serif;
                ">
                    <i class="fas fa-lock"></i> Login Required
                </h3>
                <button onclick="closeLoginModal()" style="
                    background: transparent;
                    border: none;
                    font-size: 1.2rem;
                    cursor: pointer;
                    color: #999;
                    padding: 5px;
                    transition: color 0.2s ease;
                " 
                onmouseover="this.style.color='#333'" 
                onmouseout="this.style.color='#999'">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div style="padding: 20px;">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-bookmark" style="font-size: 3em; color: #18D259; margin-bottom: 15px;"></i>
                    <p style="font-size: 1.1em; margin-bottom: 15px; font-family: 'Segoe UI', 'Roboto', sans-serif;">
                        <strong>Save "${routineName}" routine?</strong>
                    </p>
                    <p style="color: #666; margin-bottom: 20px; font-family: 'Segoe UI', 'Roboto', sans-serif;">
                        You need to be logged in to save routines to your profile.
                    </p>
                    <p style="color: #666; font-family: 'Segoe UI', 'Roboto', sans-serif;">
                        Login or create an account to access your personalized saved routines and track your progress.
                    </p>
                </div>
            </div>
            <div style="
                padding: 20px;
                border-top: 1px solid rgba(0, 0, 0, 0.1);
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            ">
                <button onclick="closeLoginModal()" style="
                    padding: 10px 20px;
                    border-radius: 8px;
                    background: transparent;
                    color: #666;
                    border: 1px solid #ddd;
                    cursor: pointer;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    font-family: 'Segoe UI', 'Roboto', sans-serif;
                "
                onmouseover="this.style.backgroundColor='#f8f9fa'; this.style.borderColor='#adb5bd';"
                onmouseout="this.style.backgroundColor='transparent'; this.style.borderColor='#ddd';">
                    Cancel
                </button>
                <button onclick="goToLogin()" style="
                    padding: 10px 20px;
                    border-radius: 8px;
                    background-color: #18D259;
                    color: white;
                    border: none;
                    cursor: pointer;
                    font-weight: 500;
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    font-family: 'Segoe UI', 'Roboto', sans-serif;
                "
                onmouseover="this.style.backgroundColor='#3fcb70'; this.style.transform='translateY(-1px)';"
                onmouseout="this.style.backgroundColor='#18D259'; this.style.transform='translateY(0)';">
                    <i class="fas fa-sign-in-alt"></i> Login / Sign Up
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    modal.id = 'loginModal';

    setTimeout(() => {
        modal.style.opacity = '1';
        modal.querySelector('div').style.transform = 'scale(1)';
    }, 10);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeLoginModal();
        }
    });
}

function closeLoginModal() {
    const modal = document.getElementById('loginModal');
    if (modal) {
        modal.style.opacity = '0';
        modal.querySelector('div').style.transform = 'scale(0.8)';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

function goToLogin() {
    window.location.href = 'login.html';
}