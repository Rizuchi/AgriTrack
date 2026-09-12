document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.fa-moon').forEach(icon => icon.remove());

    const sidebar = document.querySelector('.user-sidebar, .admin-sidebar');
    if (!sidebar) return;

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'sidebar-toggle';
    toggle.setAttribute('aria-label', 'Open navigation menu');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.innerHTML = '<i class="fa-solid fa-bars" aria-hidden="true"></i>';

    const pageHeader = document.querySelector(
        'main > header, main > [class*="header"], '
        + '.dashboard-main > [class*="header"], .crops-main > [class*="header"], '
        + '.contact-main > [class*="header"], .profile-main > [class*="header"], '
        + '.reports-main > [class*="header"], .users-main > [class*="header"], '
        + '.pests-main > [class*="header"], .prediction-main > [class*="header"]'
    ) || document.querySelector('main, .dashboard-main, .crops-main, .contact-main, body');

    if (!pageHeader) return;

    const backdrop = document.createElement('button');
    backdrop.type = 'button';
    backdrop.className = 'sidebar-backdrop';
    backdrop.setAttribute('aria-label', 'Close navigation menu');

    pageHeader.prepend(toggle);
    document.body.append(backdrop);

    const setOpen = (open) => {
        sidebar.classList.toggle('mobile-open', open);
        backdrop.classList.toggle('show', open);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
        toggle.innerHTML = `<i class="fa-solid fa-${open ? 'xmark' : 'bars'}" aria-hidden="true"></i>`;
    };

    toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('mobile-open')));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setOpen(false)));
    window.addEventListener('resize', () => {
        if (window.innerWidth > 760) setOpen(false);
    });
});
