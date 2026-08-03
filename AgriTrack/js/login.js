function togglePassword(button) {
    const targetName = button.getAttribute('data-target');
    const input = document.getElementById(targetName);

    if (!input) {
        return;
    }

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const icon = button.querySelector('i');
    if (icon) {
        icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    }

    button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const messageBox = document.getElementById('message');

    if (!form || !messageBox) {
        return;
    }

    // TRANSITION
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease-in';
    requestAnimationFrame(() => {
        document.body.style.opacity = '1';
    });

    document.querySelectorAll('.toggle-password').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            togglePassword(button);
        });
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
                redirectToDashboard(result.redirect);
            } else {
                messageBox.textContent = result.message || 'Login failed.';
                messageBox.style.color = '#b91c1c';
            }
        } catch (error) {
            messageBox.textContent = 'Unable to reach the server.';
            messageBox.style.color = '#b91c1c';
        }
    });

    function redirectToDashboard(destination) {
    document.body.style.transition = 'opacity 0.5s ease-out';
    document.body.style.opacity = '0';

    setTimeout(() => {
        window.location.href = destination || 'userdashboard.html';
    }, 500);
}
});