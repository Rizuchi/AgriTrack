(() => {
    const READ_STORAGE_KEY = 'agritrack.readNotifications.v1';
    const SESSION_READ_STORAGE_KEY = 'agritrack.sessionReadNotifications.v1';
    const readNotificationKeys = loadReadNotificationKeys();
    const sessionReadNotificationKeys = loadSessionReadNotificationKeys();
    let latestTasks = [];
    let latestContactReplies = [];
    let latestCalendarTasks = [];
    let drawerIsOpen = false;

    function loadReadNotificationKeys() {
        try {
            return new Set(JSON.parse(localStorage.getItem(READ_STORAGE_KEY) || '[]'));
        } catch {
            return new Set();
        }
    }

    function loadSessionReadNotificationKeys() {
        try {
            return new Set(JSON.parse(sessionStorage.getItem(SESSION_READ_STORAGE_KEY) || '[]'));
        } catch {
            return new Set();
        }
    }

    function isPersistentlyRead(key) {
        return readNotificationKeys.has(key);
    }

    function isDismissedForSession(key) {
        return isPersistentlyRead(key) || sessionReadNotificationKeys.has(key);
    }

    function notificationKey(task) {
        return `${task.id}:${task.due_date}:${task.type}`;
    }

    function contactReplyNotificationKey(reply) {
        return `contact-reply:${reply.ReplyID}`;
    }

    function calendarTaskNotificationKey(task) {
        return `calendar-task:${task.CalendarID}`;
    }

    function saveReadNotificationKeys() {
        try {
            localStorage.setItem(READ_STORAGE_KEY, JSON.stringify([...readNotificationKeys]));
        } catch (error) {
            console.warn('Notification read state could not be saved:', error);
        }
    }

    function saveSessionReadNotificationKeys() {
        try {
            sessionStorage.setItem(SESSION_READ_STORAGE_KEY, JSON.stringify([...sessionReadNotificationKeys]));
        } catch (error) {
            console.warn('Session notification read state could not be saved:', error);
        }
    }

    const pad2 = value => String(value).padStart(2, '0');

    function dateKey(date) {
        return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
    }

    function formatDate(value) {
        const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
        return new Date(year, month - 1, day).toLocaleDateString('en-PH', {
            month: 'short', day: 'numeric', year: 'numeric',
        });
    }

    function buildNotifications(tasks) {
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);
        const today = dateKey(todayDate);
        return tasks
            .filter(task => task.status !== 'Done')
            .map(task => {
                const dueDate = String(task.due_date).slice(0, 10);
                const overdue = task.status === 'Overdue' || dueDate < today;
                let label = '';
                if (overdue) label = 'Overdue / Lampas sa takdang araw';
                else if (dueDate === today) label = 'Due today / Takda ngayong araw';
                return label ? { task, dueDate, label, overdue } : null;
            })
            .filter(Boolean)
            .sort((first, second) => first.dueDate.localeCompare(second.dueDate));
    }

    function buildCalendarTaskNotifications(tasks) {
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);
        const today = dateKey(todayDate);
        return tasks
            .filter(task => task.Status === 'Pending')
            .map(task => {
                const dueDate = String(task.StartDate).slice(0, 10);
                const overdue = dueDate < today;
                const label = overdue
                    ? 'Overdue / Lampas sa takdang araw'
                    : dueDate === today ? 'Due today / Takda ngayong araw' : '';
                return label ? { task, dueDate, label, overdue } : null;
            })
            .filter(Boolean)
            .sort((first, second) => first.dueDate.localeCompare(second.dueDate));
    }

    function render(tasks, errorMessage = '', contactReplies = latestContactReplies, calendarTasks = latestCalendarTasks) {
        const list = document.getElementById('notificationList');
        const count = document.getElementById('notificationCount');
        const trigger = document.getElementById('openNotifications');
        if (!list || !count || !trigger) return;

        if (!errorMessage) {
            latestTasks = tasks;
            latestContactReplies = contactReplies;
            latestCalendarTasks = calendarTasks;
        }
        const notifications = buildNotifications(tasks);
        const calendarTaskNotifications = errorMessage ? [] : buildCalendarTaskNotifications(calendarTasks);
        const replyNotifications = errorMessage ? [] : contactReplies;
        if (drawerIsOpen && !errorMessage) {
            let hasNewReadItems = false;
            notifications.forEach(({ task }) => {
                const key = notificationKey(task);
                if (!isDismissedForSession(key)) {
                    sessionReadNotificationKeys.add(key);
                    hasNewReadItems = true;
                }
            });
            replyNotifications.forEach(reply => {
                const key = contactReplyNotificationKey(reply);
                if (!isDismissedForSession(key)) {
                    sessionReadNotificationKeys.add(key);
                    hasNewReadItems = true;
                }
            });
            calendarTaskNotifications.forEach(({ task }) => {
                const key = calendarTaskNotificationKey(task);
                if (!isDismissedForSession(key)) {
                    sessionReadNotificationKeys.add(key);
                    hasNewReadItems = true;
                }
            });
            if (hasNewReadItems) saveSessionReadNotificationKeys();
        }
        const unreadCount = notifications.filter(item => !isDismissedForSession(notificationKey(item.task))).length
            + calendarTaskNotifications.filter(item => !isDismissedForSession(calendarTaskNotificationKey(item.task))).length
            + replyNotifications.filter(reply => !isDismissedForSession(contactReplyNotificationKey(reply))).length;
        count.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        count.hidden = unreadCount === 0;
        trigger.setAttribute('aria-label', unreadCount ? `Mga abiso, ${unreadCount} unread` : 'Mga abiso');
        list.replaceChildren();

        if (errorMessage || (notifications.length === 0 && calendarTaskNotifications.length === 0 && replyNotifications.length === 0)) {
            const message = document.createElement('p');
            message.className = 'notification-empty';
            message.textContent = errorMessage || "You're all caught up. No new notifications.";
            list.appendChild(message);
            return;
        }

        notifications.forEach(({ task, dueDate, label, overdue }) => {
            const key = notificationKey(task);
            const isRead = isPersistentlyRead(key);
            const entry = document.createElement('article');
            entry.className = `notification-entry${overdue ? ' is-overdue' : ''}${isRead ? ' is-read' : ' is-unread'}`;

            const link = document.createElement('a');
            link.className = 'notification-entry-link';
            link.href = `userdashboard.html?openTasks=1&taskId=${encodeURIComponent(task.id)}`;
            link.setAttribute('aria-label', `Open Scheduled Tasks for ${task.type}: ${task.PlantLabel || task.CropName || 'crop'}`);
            link.title = 'Open Scheduled Tasks';
            link.addEventListener('click', () => {
                readNotificationKeys.add(key);
                saveReadNotificationKeys();
            });

            const copy = document.createElement('div');
            copy.className = 'notification-entry-copy';
            const date = document.createElement('div');
            date.className = 'notification-entry-date';
            date.textContent = `${label} · ${formatDate(dueDate)}`;

            const title = document.createElement('div');
            title.className = 'notification-entry-row';
            const taskName = document.createElement('strong');
            taskName.textContent = `${task.type}: `;
            title.append(taskName, document.createTextNode(task.PlantLabel || task.CropName || 'Crop'));

            const detail = document.createElement('div');
            detail.className = 'notification-entry-row';
            detail.textContent = `${task.CropName || 'Crop'} · ${task.priority || 'Normal'} priority`;

            copy.append(date, title, detail);
            const arrow = document.createElement('span');
            arrow.className = 'notification-entry-arrow';
            arrow.setAttribute('aria-hidden', 'true');
            arrow.innerHTML = '<i class="fa-solid fa-arrow-right"></i>';
            link.append(copy, arrow);
            entry.appendChild(link);
            list.appendChild(entry);
        });

        calendarTaskNotifications.forEach(({ task, dueDate, label, overdue }) => {
            const key = calendarTaskNotificationKey(task);
            const entry = document.createElement('article');
            entry.className = `notification-entry${overdue ? ' is-overdue' : ''}${isPersistentlyRead(key) ? ' is-read' : ' is-unread'}`;
            const link = document.createElement('a');
            link.className = 'notification-entry-link';
            link.href = `userdashboard.html?openTasks=1&calendarTaskId=${encodeURIComponent(task.CalendarID)}`;
            link.setAttribute('aria-label', `Open calendar task / Buksan ang naka-iskedyul na gawain: ${task.TaskType}`);
            const copy = document.createElement('div');
            copy.className = 'notification-entry-copy';
            const date = document.createElement('div');
            date.className = 'notification-entry-date';
            date.textContent = `${label} · ${formatDate(dueDate)}`;
            const title = document.createElement('div');
            title.className = 'notification-entry-row';
            const taskName = document.createElement('strong');
            taskName.textContent = `${task.TaskType}: `;
            title.append(taskName, document.createTextNode(task.PlantLabel || task.CropName || 'General task'));
            copy.append(date, title);
            const arrow = document.createElement('span');
            arrow.className = 'notification-entry-arrow';
            arrow.setAttribute('aria-hidden', 'true');
            arrow.innerHTML = '<i class="fa-solid fa-arrow-right"></i>';
            link.append(copy, arrow);
            link.addEventListener('click', () => {
                readNotificationKeys.add(key);
                saveReadNotificationKeys();
            });
            entry.appendChild(link);
            list.appendChild(entry);
        });

        replyNotifications.forEach(reply => {
            const key = contactReplyNotificationKey(reply);
            const isRead = isPersistentlyRead(key);
            const entry = document.createElement('article');
            entry.className = `notification-entry${isRead ? ' is-read' : ' is-unread'}`;

            const button = document.createElement('a');
            button.className = 'notification-entry-link';
            button.href = 'contact.html#inbox';
            button.setAttribute('aria-label', `An Admin responded to your message. ${formatDate(reply.created_at)}`);

            const copy = document.createElement('div');
            copy.className = 'notification-entry-copy';
            const date = document.createElement('div');
            date.className = 'notification-entry-date';
            date.textContent = formatDate(reply.created_at);
            const title = document.createElement('div');
            title.className = 'notification-entry-row';
            const label = document.createElement('strong');
            label.textContent = 'An Admin responded to your message.';
            title.append(label);
            copy.append(date, title);

            const check = document.createElement('span');
            check.className = 'notification-entry-arrow';
            check.setAttribute('aria-hidden', 'true');
            check.innerHTML = '<i class="fa-solid fa-check"></i>';
            button.append(copy, check);
            button.addEventListener('click', () => {
                readNotificationKeys.add(key);
                saveReadNotificationKeys();
                entry.classList.remove('is-unread');
                entry.classList.add('is-read');
                const unreadCount = buildNotifications(latestTasks)
                    .filter(item => !isDismissedForSession(notificationKey(item.task))).length
                    + buildCalendarTaskNotifications(latestCalendarTasks)
                        .filter(item => !isDismissedForSession(calendarTaskNotificationKey(item.task))).length
                    + latestContactReplies.filter(item => !isDismissedForSession(contactReplyNotificationKey(item))).length;
                count.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
                count.hidden = unreadCount === 0;
                trigger.setAttribute('aria-label', unreadCount ? `Mga abiso, ${unreadCount} unread` : 'Mga abiso');
            });
            entry.append(button);
            list.appendChild(entry);
        });
    }

    function closeDrawer(overlay, trigger) {
        drawerIsOpen = false;
        overlay.hidden = true;
        overlay.classList.remove('show');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.focus();
    }

    async function loadNotifications() {
        try {
            const response = await fetch('../php/scheduled_tasks.php', { credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Notifications could not be loaded.');
            render(data.tasks || [], '', data.contactReplies || [], data.calendarTasks || []);
        } catch (error) {
            render([], error.message || 'Notifications could not be loaded.');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const overlay = document.getElementById('notificationOverlay');
        const trigger = document.getElementById('openNotifications');
        const closeButton = document.getElementById('closeNotifications');
        if (!overlay || !trigger || !closeButton) return;

        trigger.addEventListener('click', () => {
            drawerIsOpen = true;
            render(latestTasks, '', latestContactReplies);
            overlay.hidden = false;
            overlay.classList.add('show');
            trigger.setAttribute('aria-expanded', 'true');
            closeButton.focus();
        });
        closeButton.addEventListener('click', () => closeDrawer(overlay, trigger));
        overlay.addEventListener('click', event => {
            if (event.target === overlay) closeDrawer(overlay, trigger);
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !overlay.hidden) closeDrawer(overlay, trigger);
        });

        if (document.getElementById('scheduledTaskList')) {
            window.AgriTrackNotifications = { update: render };
            return;
        }
        loadNotifications();
    });
})();