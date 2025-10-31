// filepath: env-protection-admin/env-protection-admin/public/assets/js/main.js
document.addEventListener('DOMContentLoaded', function() {
    // Form validation for login and registration
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value;
            const age = document.querySelector('input[name="age"]').value;
            const address = document.querySelector('input[name="address"]').value;

            if (!name || !age || !address) {
                e.preventDefault();
                alert('Please fill in all fields.');
            }
        });
    }

    // Dynamic content updates for managing volunteers, events, and news
    const manageButtons = document.querySelectorAll('.manage-button');
    manageButtons.forEach(button => {
        button.addEventListener('click', function() {
            const action = this.dataset.action;
            fetch(`manage_${action}.php`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('content-area').innerHTML = data;
                })
                .catch(error => console.error('Error fetching data:', error));
        });
    });
});