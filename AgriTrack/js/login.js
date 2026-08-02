document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const messageBox = document.getElementById('message');

    if (!form || !messageBox) {
        return;
    }

    // Fade in on page load
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease-in';
    requestAnimationFrame(() => {
        document.body.style.opacity = '1';
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        if (!payload.userName || !payload.password) {
            messageBox.textContent = 'Please fill in all fields.';
            messageBox.style.color = '#b91c1c';
            return;
        }

        messageBox.textContent = 'Logging in...';
        messageBox.style.color = '#14532d';

        try {
            const response = await fetch('../php/login.php', {
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
                redirectToDashboard();
            } else {
                messageBox.textContent = result.message || 'Login failed.';
                messageBox.style.color = '#b91c1c';
            }
        } catch (error) {
            messageBox.textContent = 'Unable to reach the server.';
            messageBox.style.color = '#b91c1c';
        }
    });

    function redirectToDashboard() {
        document.body.style.transition = 'opacity 0.5s ease-out';
        document.body.style.opacity = '0';

        setTimeout(() => {
            window.location.href = 'userdashboard.html';
        }, 500);
    }
});