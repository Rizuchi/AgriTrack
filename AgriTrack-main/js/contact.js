document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('contactForm');
    const messageBox = document.getElementById('contactFormMessage');
    const submitBtn = document.getElementById('contactSubmitBtn');

    if (!form || !messageBox || !submitBtn) {
        return;
    }

    const FORMSUBMIT_ENDPOINT = 'https://formsubmit.co/ajax/agritrack.ph@gmail.com';

    function setMessage(text, type) {
        messageBox.textContent = text;
        messageBox.className = 'form-message show ' + type;
    }

    function clearMessage() {
        messageBox.textContent = '';
        messageBox.className = 'form-message';
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearMessage();

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        if (!payload.firstName || !payload.lastName || !payload.email || !payload.message) {
            setMessage('Please fill in all fields.', 'error');
            return;
        }

        payload._subject = 'AgriTrack Contact Form: ' + payload.firstName + ' ' + payload.lastName;
        payload._template = 'table';
        payload._captcha = 'false';

        submitBtn.disabled = true;
        submitBtn.textContent = 'Ipinapadala...';

        try {
            const response = await fetch(FORMSUBMIT_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && (result.success === true || result.success === 'true')) {
                form.reset();
                setMessage('Naipadala ang iyong mensahe. Salamat!', 'success');
            } else {
                setMessage(result.message || 'Hindi naipadala ang mensahe. Subukan muli.', 'error');
            }
        } catch (error) {
            setMessage('Hindi makonekta sa server.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Ipadala';
        }
    });
});