document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-toggle');
    const pageWrapper = document.querySelector('.page-wrapper');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            //pageWrapper.classList.toggle('mobile-menu-open');
        });
    }

    const faqItems = document.querySelectorAll('.faq-item');
    
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        const toggleIcon = item.querySelector('.faq-toggle i');
        
        answer.style.maxHeight = '0';
    
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
            this.classList.toggle('saved');
            
            const icon = this.querySelector('i');
            const routineName = this.closest('.routine-card').querySelector('h3').textContent;
            
            if (this.classList.contains('saved')) {
                icon.className = 'fas fa-check';
                this.innerHTML = `<i class="fas fa-check"></i> Saved`;
                showToast(`${routineName} added to your saved routines`);
            } else {
                icon.className = 'fas fa-bookmark';
                this.innerHTML = `<i class="fas fa-bookmark"></i> Save`;
                showToast(`${routineName} removed from your saved routines`);
            }
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

                if (pageWrapper.classList.contains('mobile-menu-open')) {
                    pageWrapper.classList.remove('mobile-menu-open');
                }
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
            navbar.style.boxShadow = 'var(--shadow-md)';
        } else {
            navbar.style.boxShadow = 'var(--shadow-sm)';
        }
        
        lastScrollTop = scrollTop;
    });
    function showToast(message) {
        const existingToast = document.querySelector('.toast-notification');
        if (existingToast) {
            existingToast.remove();
        }
        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-info-circle"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close"><i class="fas fa-times"></i></button>
        `;
        
        document.body.appendChild(toast);
    
        setTimeout(() => {
            toast.classList.add('active');
        }, 10);
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.classList.remove('active');
            setTimeout(() => {
                toast.remove();
            }, 300);
        });
        setTimeout(() => {
            toast.classList.remove('active');
            
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
    }
    window.addEventListener('scroll', function() {
        const scrollHeight = document.documentElement.scrollHeight;
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const clientHeight = document.documentElement.clientHeight;
        
        if (scrollTop + clientHeight >= scrollHeight - 300) {
            document.querySelector('.cta').classList.add('animate-cta');
        }
    });
    window.logout = function() {
        showModal('Logout Confirmation', 'Are you sure you want to log out?', function() {
            showToast('You have been logged out');
            setTimeout(() => {
                window.location.href = 'WoW.html';
            }, 1500);
        });
    };
    function showModal(title, message, confirmCallback) {
        const existingModal = document.querySelector('.modal-overlay');
        if (existingModal) {
            existingModal.remove();
        }

        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-container">
                <div class="modal-header">
                    <h3>${title}</h3>
                    <button class="modal-close"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <p>${message}</p>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline modal-cancel">Cancel</button>
                    <button class="btn btn-primary modal-confirm">Confirm</button>
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
            if (typeof confirmCallback === 'function') {
                confirmCallback();
            }
            closeModal();
        });
    }
    const footballPlanBtn = document.getElementById('personalizedFootballPlanBtn');
    if (footballPlanBtn) {
        footballPlanBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showModal(
                'Authentication Required',
                'You need to be logged in to access your personalized football plan.<br><br><b>Please log in or create an account to continue.</b>',
                function() {
                    window.location.href = 'login.html'; 
                }
            );
        });
    }
});