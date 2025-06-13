class MobileMenu {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.setupMobileMenu();
            this.handleInitialLoad();
        });
        
        window.addEventListener('resize', () => {
            this.handleResize();
        });
    }

    handleInitialLoad() {
        if (window.innerWidth > 992) {
            this.cleanupMobileMenu();
        }
    }

    handleResize() {
        if (window.innerWidth > 992) {
            this.cleanupMobileMenu();
        }
    }

    cleanupMobileMenu() {
        const mobileMenu = document.querySelector('.mobile-menu');
        const overlay = document.querySelector('.mobile-overlay');
        const mobileToggle = document.querySelector('.mobile-toggle');
        
        if (mobileMenu) {
            mobileMenu.classList.remove('active');
            mobileMenu.remove();
        }
        if (overlay) {
            overlay.classList.remove('active');
            overlay.remove();
        }
        if (mobileToggle) {
            mobileToggle.classList.remove('active');
        }
        
        document.body.style.overflow = '';
    }

    setupMobileMenu() {
        const mobileToggle = document.querySelector('.mobile-toggle');
        const navbar = document.querySelector('.navbar');
        
        if (!mobileToggle || !navbar) {
            console.warn('Mobile toggle or navbar not found');
            return;
        }

        mobileToggle.addEventListener('click', () => {
            this.toggleMobileMenu();
        });
    }

    toggleMobileMenu() {
        let mobileMenu = document.querySelector('.mobile-menu');
        
        if (!mobileMenu) {
            this.createMobileMenu();
            mobileMenu = document.querySelector('.mobile-menu');
        }
        
        const overlay = document.querySelector('.mobile-overlay');
        const mobileToggle = document.querySelector('.mobile-toggle');

        mobileMenu.classList.toggle('active');
        overlay.classList.toggle('active');
        mobileToggle.classList.toggle('active');
        
        if (mobileMenu.classList.contains('active')) {
            overlay.addEventListener('click', () => this.closeMobileMenu());
            document.addEventListener('keydown', (e) => this.handleEscapeKey(e));
        }
    }

    isUserAdmin() {
        const token = localStorage.getItem('authToken');
        if (!token) return false;
        
        try {
            const payload = JSON.parse(atob(token.split('.')[1]));
            return payload.isAdmin === true;
        } catch (error) {
            console.error('Error checking admin status:', error);
            return false;
        }
    }

    createMobileMenu() {
        const navbar = document.querySelector('.navbar');
        const currentPage = this.getCurrentPage();
        const isAdmin = this.isUserAdmin();
        
        let headerButtons = `
            <a href="profile.html" class="btn btn-outline">
                <i class="fas fa-user"></i> Profile
            </a>
            <a href="${currentPage.logoutLink}" class="btn btn-outline">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        `;

        if (isAdmin) {
            headerButtons = `
                <a href="admin-panel.html" class="btn btn-admin">
                    <i class="fas fa-cog"></i> Admin
                </a>
                <a href="profile.html" class="btn btn-outline">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="${currentPage.logoutLink}" class="btn btn-outline">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            `;
        }
        
        const mobileMenuHTML = `
            <div class="mobile-menu">
                <div class="mobile-menu-header">
                    ${headerButtons}
                </div>
                
                <nav class="mobile-menu-nav">
                    <div class="mobile-nav-item" style="--delay: 1">
                        <a href="#" class="mobile-nav-link has-dropdown" data-dropdown="workouts">
                            <span><i class="fas fa-dumbbell"></i> Workout routines</span>
                        </a>
                        <div class="mobile-dropdown" id="workouts-dropdown">
                            <a href="physiotherapy.html" class="mobile-dropdown-item">Physiotherapy</a>
                            <a href="kinetotherapy.html" class="mobile-dropdown-item">Kinetotherapy</a>
                            <a href="#" class="mobile-dropdown-item has-nested" data-nested="sports">
                                Sports
                                <div class="mobile-nested-dropdown" id="sports-nested">
                                    <a href="football.html" class="mobile-nested-item">Football</a>
                                    <a href="basketball.html" class="mobile-nested-item">Basketball</a>
                                    <a href="tennis.html" class="mobile-nested-item">Tennis</a>
                                    <a href="swimming.html" class="mobile-nested-item">Swimming</a>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <div class="mobile-menu-separator"></div>
                    
                    <div class="mobile-nav-item" style="--delay: 2">
                        <a href="champions.html" class="mobile-nav-link">
                            <span><i class="fas fa-trophy"></i> Champions</span>
                        </a>
                    </div>
                    
                    <div class="mobile-nav-item" style="--delay: 3">
                        <a href="success-stories.html" class="mobile-nav-link">
                            <span><i class="fas fa-star"></i> Success stories</span>
                        </a>
                    </div>
                    
                    <div class="mobile-nav-item" style="--delay: 4">
                        <a href="contact.html" class="mobile-nav-link">
                            <span><i class="fas fa-envelope"></i> Contacts</span>
                        </a>
                    </div>
                </nav>
            </div>
            
            <div class="mobile-overlay"></div>
        `;
        
        navbar.insertAdjacentHTML('beforeend', mobileMenuHTML);
        this.setupDropdownListeners();
    }

    getCurrentPage() {
        const path = window.location.pathname;
        const filename = path.split('/').pop();

        if (filename === 'WoW-Logged.html' || filename === '' || filename === 'index.html') {
            return {
                logoutLink: 'WoW.html'
            };
        } else {
            return {
                logoutLink: 'WoW.html'
            };
        }
    }

    setupDropdownListeners() {
        const dropdownTriggers = document.querySelectorAll('.mobile-nav-link.has-dropdown');
        dropdownTriggers.forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const dropdownId = trigger.getAttribute('data-dropdown') + '-dropdown';
                const dropdown = document.getElementById(dropdownId);

                dropdown.classList.toggle('active');
                trigger.classList.toggle('active');
            });
        });
        
        const nestedTriggers = document.querySelectorAll('.mobile-dropdown-item.has-nested');
        nestedTriggers.forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const nestedId = trigger.getAttribute('data-nested') + '-nested';
                const nested = document.getElementById(nestedId);
                nested.classList.toggle('active');
            });
        });
    }

    closeMobileMenu() {
        const mobileMenu = document.querySelector('.mobile-menu');
        const overlay = document.querySelector('.mobile-overlay');
        const mobileToggle = document.querySelector('.mobile-toggle');
        
        if (mobileMenu) {
            mobileMenu.classList.remove('active');
            overlay.classList.remove('active');
            mobileToggle.classList.remove('active');
        }
        
        const overlayElement = document.querySelector('.mobile-overlay');
        if (overlayElement) {
            overlayElement.removeEventListener('click', () => this.closeMobileMenu());
        }
        document.removeEventListener('keydown', (e) => this.handleEscapeKey(e));
    }

    handleEscapeKey(e) {
        if (e.key === 'Escape') {
            this.closeMobileMenu();
        }
    }
}

const mobileMenu = new MobileMenu();