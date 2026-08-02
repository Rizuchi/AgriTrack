document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registerForm');
    const messageBox = document.getElementById('message');

    if (!form || !messageBox) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

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