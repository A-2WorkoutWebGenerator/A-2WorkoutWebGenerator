document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-toggle');
    const pageWrapper = document.querySelector('.page-wrapper');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            pageWrapper.classList.toggle('mobile-menu-open');
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
        if (!document.getElementById('toast-styles')) {
            const toastStyles = document.createElement('style');
            toastStyles.id = 'toast-styles';
            toastStyles.textContent = `
                .toast-notification {
                    position: fixed;
                    bottom: 30px;
                    right: 30px;
                    background-color: white;
                    color: var(--text-dark);
                    padding: 0 15px;
                    border-radius: var(--radius-md);
                    box-shadow: var(--shadow-lg);
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    max-width: 350px;
                    transform: translateX(110%);
                    transition: transform 0.3s ease;
                    z-index: 9999;
                }
                
                .toast-notification.active {
                    transform: translateX(0);
                }
                
                .toast-content {
                    display: flex;
                    align-items: center;
                    padding: 15px 5px;
                }
                
                .toast-content i {
                    color: var(--primary);
                    font-size: 1.2rem;
                    margin-right: 10px;
                }
                
                .toast-close {
                    background: transparent;
                    border: none;
                    color: var(--text-muted);
                    cursor: pointer;
                    padding: 5px;
                    transition: color var(--transition-fast);
                }
                
                .toast-close:hover {
                    color: var(--text-dark);
                }
                
                @media (max-width: 480px) {
                    .toast-notification {
                        left: 20px;
                        right: 20px;
                        bottom: 20px;
                        max-width: none;
                    }
                }
            `;
            document.head.appendChild(toastStyles);
        }
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
    
    if (!document.getElementById('cta-styles')) {
        const ctaStyles = document.createElement('style');
        ctaStyles.id = 'cta-styles';
        ctaStyles.textContent = `
            @keyframes ctaPulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .animate-cta .btn-primary {
                animation: ctaPulse 2s infinite;
            }
        `;
        document.head.appendChild(ctaStyles);
    }
    
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
        if (!document.getElementById('modal-styles')) {
            const modalStyles = document.createElement('style');
            modalStyles.id = 'modal-styles';
            modalStyles.textContent = `
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(0, 0, 0, 0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 9999;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    padding: 20px;
                }
                
                .modal-overlay.active {
                    opacity: 1;
                }
                
                .modal-container {
                    background-color: white;
                    border-radius: var(--radius-md);
                    width: 100%;
                    max-width: 500px;
                    box-shadow: var(--shadow-lg);
                    transform: scale(0.8);
                    transition: transform 0.3s ease;
                }
                
                .modal-overlay.active .modal-container {
                    transform: scale(1);
                }
                
                .modal-header {
                    padding: 20px;
                    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .modal-header h3 {
                    margin: 0;
                }
                
                .modal-close {
                    background: transparent;
                    border: none;
                    font-size: 1.2rem;
                    cursor: pointer;
                    color: var(--text-muted);
                    transition: color var(--transition-fast);
                }
                
                .modal-close:hover {
                    color: var(--text-dark);
                }
                
                .modal-body {
                    padding: 20px;
                }
                
                .modal-footer {
                    padding: 20px;
                    border-top: 1px solid rgba(0, 0, 0, 0.1);
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                }
            `;
            document.head.appendChild(modalStyles);
        }
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
    if (!document.getElementById('faq-styles')) {
        const faqStyles = document.createElement('style');
        faqStyles.id = 'faq-styles';
        faqStyles.textContent = `
            .faq-answer {
                padding: 0 25px;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.5s ease, padding 0.5s ease;
            }
            
            .faq-item.active .faq-answer {
                padding: 0 25px 20px;
                /* Înălțimea maximă va fi setată dinamic prin JavaScript */
            }
            
            .faq-item.active .faq-question {
                background-color: var(--primary-light);
            }
            
            .faq-toggle i {
                transition: transform 0.3s ease;
            }
            
            .faq-item.active .faq-toggle i.fa-plus {
                transform: rotate(45deg);
            }
            
            .faq-item.active .faq-toggle i.fa-minus {
                transform: rotate(0);
            }
        `;
        document.head.appendChild(faqStyles);
    }
});
