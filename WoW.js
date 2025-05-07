document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-toggle');
    const navLinks = document.querySelector('.nav-links');
    const navButtons = document.querySelector('.nav-buttons');
    
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            if (!document.querySelector('.mobile-menu')) {
                const mobileMenu = document.createElement('div');
                mobileMenu.className = 'mobile-menu';
                const linksClone = navLinks.cloneNode(true);
                mobileMenu.appendChild(linksClone);
                const buttonsClone = navButtons.cloneNode(true);
                mobileMenu.appendChild(buttonsClone);
                
                document.querySelector('.navbar').appendChild(mobileMenu);
                mobileMenu.style.position = 'absolute';
                mobileMenu.style.top = '100%';
                mobileMenu.style.left = '0';
                mobileMenu.style.width = '100%';
                mobileMenu.style.backgroundColor = 'white';
                mobileMenu.style.padding = '2rem';
                mobileMenu.style.boxShadow = 'var(--shadow-md)';
                mobileMenu.style.display = 'none';
                mobileMenu.style.flexDirection = 'column';
                mobileMenu.style.gap = '2rem';
                mobileMenu.style.zIndex = '1000';
                
                linksClone.style.display = 'flex';
                linksClone.style.flexDirection = 'column';
                linksClone.style.gap = '1.5rem';
                
                buttonsClone.style.display = 'flex';
                buttonsClone.style.flexDirection = 'column';
                buttonsClone.style.gap = '1rem';
            }
            const mobileMenu = document.querySelector('.mobile-menu');
            if (mobileMenu.style.display === 'none' || mobileMenu.style.display === '') {
                mobileMenu.style.display = 'flex';
                mobileToggle.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                mobileMenu.style.display = 'none';
                mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        });
    }

    const links = document.querySelectorAll('a[href^="#"]');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            if (href !== '#') {
                e.preventDefault();
                
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    const mobileMenu = document.querySelector('.mobile-menu');
                    if (mobileMenu && mobileMenu.style.display === 'flex') {
                        mobileMenu.style.display = 'none';
                        mobileToggle.innerHTML = '<i class="fas fa-bars"></i>';
                    }
                    
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    const navbar = document.querySelector('.navbar');
    let lastScrollTop = 0;
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > 100) {
            navbar.style.padding = '1rem 5%';
            navbar.style.boxShadow = 'var(--shadow-md)';
        } else {
            navbar.style.padding = '1.5rem 5%';
            navbar.style.boxShadow = 'var(--shadow-sm)';
        }
        
        lastScrollTop = scrollTop;
    });
    const animateElements = document.querySelectorAll('.feature-card, .workout-card, .trainer-card, .testimonial, .pricing-card');
    animateElements.forEach(element => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(30px)';
        element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    });
    
    function isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top <= (window.innerHeight || document.documentElement.clientHeight) * 0.85 &&
            rect.bottom >= 0
        );
    }
    function checkScroll() {
        animateElements.forEach(element => {
            if (isInViewport(element)) {
                element.style.opacity = '1';
                element.style.transform = 'translateY(0)';
            }
        });
    }

    window.addEventListener('load', checkScroll);
    window.addEventListener('scroll', checkScroll);

    const statNumbers = document.querySelectorAll('.stat-number');
    
    function animateStats() {
        statNumbers.forEach(stat => {
            const targetValue = stat.textContent;
            let currentValue = 0;
            const duration = 2000
            const interval = 20;
            const isPercentage = targetValue.includes('%');
            const numericValue = parseInt(targetValue.replace(/[^0-9]/g, ''));
            const increment = numericValue / (duration / interval);
            
            stat.textContent = '0' + (isPercentage ? '%' : '');
            
            const counter = setInterval(() => {
                currentValue += increment;
                if (currentValue >= numericValue) {
                    clearInterval(counter);
                    stat.textContent = targetValue;
                } else {
                    stat.textContent = Math.floor(currentValue) + (targetValue.includes('+') ? '+' : '') + (isPercentage ? '%' : '');
                }
            }, interval);
        });
    }
    setTimeout(animateStats, 500);
    const workoutSlider = document.querySelector('.workout-slider');
    
    if (workoutSlider) {
        const prevArrow = document.createElement('div');
        prevArrow.className = 'slider-arrow prev-arrow';
        prevArrow.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevArrow.style.position = 'absolute';
        prevArrow.style.top = '50%';
        prevArrow.style.left = '1rem';
        prevArrow.style.transform = 'translateY(-50%)';
        prevArrow.style.backgroundColor = 'white';
        prevArrow.style.borderRadius = '50%';
        prevArrow.style.width = '40px';
        prevArrow.style.height = '40px';
        prevArrow.style.display = 'flex';
        prevArrow.style.alignItems = 'center';
        prevArrow.style.justifyContent = 'center';
        prevArrow.style.cursor = 'pointer';
        prevArrow.style.boxShadow = 'var(--shadow-md)';
        prevArrow.style.zIndex = '2';
        
        const nextArrow = document.createElement('div');
        nextArrow.className = 'slider-arrow next-arrow';
        nextArrow.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextArrow.style.position = 'absolute';
        nextArrow.style.top = '50%';
        nextArrow.style.right = '1rem';
        nextArrow.style.transform = 'translateY(-50%)';
        nextArrow.style.backgroundColor = 'white';
        nextArrow.style.borderRadius = '50%';
        nextArrow.style.width = '40px';
        nextArrow.style.height = '40px';
        nextArrow.style.display = 'flex';
        nextArrow.style.alignItems = 'center';
        nextArrow.style.justifyContent = 'center';
        nextArrow.style.cursor = 'pointer';
        nextArrow.style.boxShadow = 'var(--shadow-md)';
        nextArrow.style.zIndex = '2';

        const workoutsSection = document.querySelector('.workouts');
        const workoutSliderContainer = document.createElement('div');
        workoutSliderContainer.style.position = 'relative';
        workoutSliderContainer.style.marginBottom = '3rem';

        workoutSlider.parentNode.insertBefore(workoutSliderContainer, workoutSlider);
        workoutSliderContainer.appendChild(workoutSlider);

        workoutSliderContainer.appendChild(prevArrow);
        workoutSliderContainer.appendChild(nextArrow);
        nextArrow.addEventListener('click', function() {
            workoutSlider.scrollBy({ left: 370, behavior: 'smooth' });
        });
        
        prevArrow.addEventListener('click', function() {
            workoutSlider.scrollBy({ left: -370, behavior: 'smooth' });
        });
    }

    const testimonialSlider = document.querySelector('.testimonial-slider');
    
    if (testimonialSlider) {
        const dotsContainer = document.createElement('div');
        dotsContainer.className = 'slider-dots';
        dotsContainer.style.display = 'flex';
        dotsContainer.style.justifyContent = 'center';
        dotsContainer.style.gap = '0.5rem';
        dotsContainer.style.marginTop = '2rem';
        
        const testimonials = testimonialSlider.querySelectorAll('.testimonial');
        
        testimonials.forEach((_, index) => {
            const dot = document.createElement('div');
            dot.className = 'slider-dot';
            dot.style.width = '10px';
            dot.style.height = '10px';
            dot.style.borderRadius = '50%';
            dot.style.backgroundColor = index === 0 ? 'var(--primary)' : '#ccc';
            dot.style.cursor = 'pointer';
            dot.style.transition = 'all var(--transition-normal)';
            
            dot.addEventListener('click', function() {
                dotsContainer.querySelectorAll('.slider-dot').forEach(d => {
                    d.style.backgroundColor = '#ccc';
                });
                this.style.backgroundColor = 'var(--primary)';
                testimonialSlider.scrollTo({
                    left: testimonialSlider.offsetWidth * index,
                    behavior: 'smooth'
                });
            });
            
            dotsContainer.appendChild(dot);
        });
        
        testimonialSlider.parentNode.insertBefore(dotsContainer, testimonialSlider.nextSibling);
    }
});