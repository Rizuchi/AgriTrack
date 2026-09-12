function showPageToast(message) {
    let toast = document.getElementById('pageToast');

    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'pageToast';
        toast.className = 'page-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML = '<i class="fa-solid fa-circle-check" aria-hidden="true"></i><span></span>';
        document.body.appendChild(toast);
    }

    toast.querySelector('span').textContent = message;
    toast.classList.remove('show');
    void toast.offsetWidth;
    toast.classList.add('show');

    window.clearTimeout(toast.hideTimer);
    toast.hideTimer = window.setTimeout(() => toast.classList.remove('show'), 3600);
}
