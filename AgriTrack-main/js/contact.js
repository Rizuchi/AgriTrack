document.addEventListener('DOMContentLoaded', () => {
    const inbox = document.getElementById('contactInbox');
    const inboxOverlay = document.getElementById('inboxOverlay');
    const openInboxButton = document.getElementById('openInbox');
    const closeInboxButton = document.getElementById('closeInbox');
    const sortInboxButton = document.getElementById('sortInbox');
    const inboxSortLabel = document.getElementById('inboxSortLabel');
    const form = document.getElementById('contactForm');
    const messageBox = document.getElementById('contactFormMessage');
    const submitBtn = document.getElementById('contactSubmitBtn');

    const CONTACT_ENDPOINT = '../php/submit_contact_message.php';
    const INBOX_ENDPOINT = '../php/get_contact_inbox.php';
    let inboxMessages = [];
    let oldestInboxFirst = false;

    function formatDate(value) {
        if (!value) return 'Walang petsa';
        return new Date(value.replace(' ', 'T')).toLocaleString('fil-PH', { dateStyle: 'medium', timeStyle: 'short' });
    }

    function renderInbox(messages) {
        inbox.replaceChildren();
        if (!messages.length) {
            const empty = document.createElement('p');
            empty.className = 'contact-inbox-state';
            empty.textContent = 'Wala ka pang mga mensahe sa inbox.';
            inbox.append(empty);
            return;
        }

        [...messages]
            .sort((first, second) => {
                const firstDate = new Date(first.createdAt.replace(' ', 'T')).getTime();
                const secondDate = new Date(second.createdAt.replace(' ', 'T')).getTime();
                return oldestInboxFirst ? firstDate - secondDate : secondDate - firstDate;
            })
            .forEach(message => {
                const thread = document.createElement('article');
                thread.className = 'contact-inbox-thread';
                const subject = document.createElement('header');
                const title = document.createElement('h3');
                title.textContent = 'Iyong mensahe';
                const date = document.createElement('time');
                date.textContent = formatDate(message.createdAt);
                subject.append(title, date);
                const original = document.createElement('p');
                original.className = 'contact-inbox-message';
                original.textContent = message.message;
                thread.append(subject, original);

                (message.replies || []).forEach(reply => {
                    const response = document.createElement('section');
                    response.className = 'contact-inbox-reply';
                    const replyHeader = document.createElement('header');
                    const sender = document.createElement('strong');
                    sender.textContent = 'Admin';
                    const replyDate = document.createElement('time');
                    replyDate.textContent = formatDate(reply.createdAt);
                    replyHeader.append(sender, replyDate);
                    const replyBody = document.createElement('p');
                    replyBody.textContent = reply.reply;
                    response.append(replyHeader, replyBody);
                    thread.append(response);
                });
                inbox.append(thread);
            });
    }

    async function loadInbox() {
        if (!inbox) return;
        try {
            const response = await fetch(INBOX_ENDPOINT, { credentials: 'include', headers: { Accept: 'application/json' } });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Hindi ma-load ang iyong inbox.');
            inboxMessages = result.messages || [];
            renderInbox(inboxMessages);
        } catch (error) {
            const status = document.createElement('p');
            status.className = 'contact-inbox-state is-error';
            status.setAttribute('role', 'alert');
            status.textContent = error.message || 'Hindi ma-load ang iyong inbox.';
            inbox.replaceChildren(status);
        }
    }

    function openInbox() {
        if (!inboxOverlay || !openInboxButton) return;
        inboxOverlay.hidden = false;
        inboxOverlay.classList.add('show');
        document.body.classList.add('inbox-open');
        openInboxButton.setAttribute('aria-expanded', 'true');
        closeInboxButton?.focus();
        loadInbox();
    }

    function closeInbox() {
        if (!inboxOverlay || !openInboxButton) return;
        inboxOverlay.hidden = true;
        inboxOverlay.classList.remove('show');
        document.body.classList.remove('inbox-open');
        openInboxButton.setAttribute('aria-expanded', 'false');
        openInboxButton.focus();
    }

    openInboxButton?.addEventListener('click', openInbox);
    sortInboxButton?.addEventListener('click', () => {
        oldestInboxFirst = !oldestInboxFirst;
        sortInboxButton.setAttribute('aria-pressed', String(oldestInboxFirst));
        sortInboxButton.setAttribute(
            'aria-label',
            oldestInboxFirst ? 'Pinakaluma muna; pindutin para unahin ang pinakabago' : 'Pinakabago muna; pindutin para unahin ang pinakaluma',
        );
        inboxSortLabel.textContent = oldestInboxFirst ? 'Pinakaluma' : 'Pinakabago';
        renderInbox(inboxMessages);
    });
    closeInboxButton?.addEventListener('click', closeInbox);
    inboxOverlay?.addEventListener('click', event => {
        if (event.target === inboxOverlay) closeInbox();
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && inboxOverlay && !inboxOverlay.hidden) closeInbox();
    });
    if (window.location.hash === '#inbox') openInbox();

    if (!form || !messageBox || !submitBtn) return;

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

        submitBtn.disabled = true;
        submitBtn.textContent = 'Ipinapadala...';

        try {
            const response = await fetch(CONTACT_ENDPOINT, {
                credentials: 'include',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (response.ok && result.success) {
                form.reset();
                setMessage(result.emailSent
                    ? 'Naipadala ang iyong mensahe sa mga admin. Salamat!'
                    : 'Nai-save ang iyong mensahe, pero hindi naipadala ang email notification. Makikita ito ng mga admin sa inbox.', result.emailSent ? 'success' : 'warning');
                if (inboxOverlay && !inboxOverlay.hidden) loadInbox();
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