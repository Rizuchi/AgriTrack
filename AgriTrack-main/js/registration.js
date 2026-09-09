document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registerForm');
    const messageBox = document.getElementById('message');

    if (!form || !messageBox) {
        return;
    }

    document.querySelectorAll('.toggle-password').forEach((button) => {
        button.addEventListener('click', () => {
            const targetName = button.getAttribute('data-target');
            const input = form.elements[targetName];

            if (!input) {
                return;
            }

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            }
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        const nameFields = ['fname', 'lname'];
        const namePattern = /^[A-Za-z\s]+$/;
        
        if (!payload.fname || !payload.lname || !payload.userName || !payload.password || !payload.confirmPassword) {
            messageBox.textContent = 'Please fill in all required fields.';
            messageBox.style.color = '#b91c1c';
            return;
        }
        for (const fieldName of nameFields) {
            const value = (payload[fieldName] || '').trim();
            if (value && !namePattern.test(value)) {
                messageBox.textContent = 'First name and last name can only contain letters and spaces.';
                messageBox.style.color = '#b91c1c';
                form.elements[fieldName].focus();
                return;
            }
        }

        if (payload.password.length < 8) {
            messageBox.textContent = 'Password must be at least 8 characters long.';
            messageBox.style.color = '#b91c1c';
            form.elements.password.focus();
            return;
        }

        if (payload.password !== payload.confirmPassword) {
            messageBox.textContent = 'Passwords do not match.';
            messageBox.style.color = '#b91c1c';
            return;
        }

        messageBox.textContent = 'Creating account...';
        messageBox.style.color = '#14532d';

        await new Promise(resolve => setTimeout(resolve, 500));  // Delay
            try {
                const response = await fetch('../php/registration.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    messageBox.textContent = result.message;
                    messageBox.style.color = '#14532d';
                    form.reset();
                    redirectToLogin();
                } else {
                    messageBox.textContent = result.message || 'Registration failed.';
                    messageBox.style.color = '#b91c1c';
                }
            } catch (error) {
                messageBox.textContent = 'Unable to reach the server.';
                messageBox.style.color = '#b91c1c';
            }
    });

    function redirectToLogin() {
        document.body.style.transition = 'opacity 0.5s ease-out';
        document.body.style.opacity = '0';

        setTimeout(() => {
            window.location.href = 'login.html';
        }, 500);
    }
});