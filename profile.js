// const username = localStorage.getItem("username");
//   if (username) {
//     document.getElementById("greeting").textContent = `Hello, ${username}! Complete Your Profile`;
//   }


  document.addEventListener('DOMContentLoaded', () => {
    const menuItems = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.section');

    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            event.preventDefault();
            // Remove active state
            menuItems.forEach(i => i.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));

            // Activate selected section
            item.classList.add('active');
            const target = item.getAttribute('data-section');
            document.getElementById(target).classList.add('active');
        });
    });
});
