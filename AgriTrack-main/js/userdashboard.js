const API_BASE = '../php/';

const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const TAG_ROW_LABELS = {
    'Gawain': 'Gawain / Activities',
    'Kalagayan': 'Kalagayan / Condition',
    'Panahon': 'Panahon / Weather',
};

const NOTE_SAVE_LABEL = 'I-save / Save';
let noteCooldownTimer = null;

const state = {
    viewYear: new Date().getFullYear(),
    viewMonth: new Date().getMonth() + 1, 
    tasksByDate: {},   
    notesByDate: {},
    harvestByDate: {},
    selectedNoteDate: null,
    selectedPlantedCropId: null,
    plantedCrops: [],
    scheduledTasks: [],
    calendarTasks: [],
    taskFilter: 'all',
    focusedTaskId: null,
};

function pad2(n) {
    return String(n).padStart(2, '0');
}

function toDateKey(year, month, day) {
    return `${year}-${pad2(month)}-${pad2(day)}`;
}


function normalizeDateKey(raw) {
    if (!raw) return raw;
    return String(raw).slice(0, 10);
}

function isToday(year, month, day) {
    const now = new Date();
    return now.getFullYear() === year && (now.getMonth() + 1) === month && now.getDate() === day;
}

async function apiGet(path) {
    const res = await fetch(API_BASE + path, { credentials: 'same-origin' });
    const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
    if (!res.ok || !data.success) {
        throw new Error(data.message || 'Request failed.');
    }
    return data;
}

async function apiPost(path, body) {
    const res = await fetch(API_BASE + path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
    if (!res.ok || !data.success) {
        const err = new Error(data.message || 'Request failed.');
        err.status = res.status;
        err.retryAfter = data.retryAfter;
        throw err;
    }
    return data;
}

// ---------- GREETING ----------

async function loadGreeting() {
    try {
        const data = await apiGet('get_current_user.php');
        const name = data.fname || data.lname;
        document.getElementById('welcomeTitle').textContent = name ? `Welcome, ${name}!` : 'Welcome!';
    } catch (err) {
        console.error('Failed to load user info:', err);
    }
}

// ---------- STATS ----------

async function loadStats() {
    try {
        const data = await apiGet('dashboard_stats.php');
        document.getElementById('statTotalCrops').textContent = String(data.totalCrops).padStart(2, '0');
        document.getElementById('statActiveMonitoring').textContent = String(data.activeMonitoring).padStart(2, '0');
        document.getElementById('statPestAlerts').textContent = String(data.pestAlerts).padStart(2, '0');
        const pestCropNames = data.pestCropNames || [];
        const pestHint = document.getElementById('pestAlertHint');
        if (pestCropNames.length > 0) {
            const visibleNames = pestCropNames.slice(0, 3).join(', ');
            const moreCount = pestCropNames.length - 3;
            pestHint.textContent = `Pest noted: ${visibleNames}${moreCount > 0 ? ` +${moreCount} more` : ''}`;
            pestHint.hidden = false;
            window.setTimeout(() => {
                pestHint.hidden = true;
            }, 7000);
        }
    } catch (err) {
        console.error('Failed to load stats:', err);
    }
}

function showWeather(data) {
    const content = document.getElementById('weatherContent');
    const update = document.getElementById('weatherUpdate');
    const gate = document.getElementById('weatherGate');

    if (data.needsFetch) {
        update.textContent = "Today's weather update is not available yet.";
        return;
    }

    gate.hidden = true;
    content.setAttribute('aria-hidden', 'false');
    update.setAttribute('aria-hidden', 'false');

    if (!data.weather) {
        document.getElementById('weatherDescription').textContent = 'Weather update unavailable';
        update.textContent = data.error || 'Today’s shared weather request did not return conditions. The daily call has already been used.';
        update.classList.add('is-error');
        return;
    }

    const weather = data.weather;
    document.getElementById('weatherDescription').textContent = weather.description || 'Conditions unavailable';
    document.getElementById('weatherLocation').textContent = weather.location || 'Orani, Bataan';
    document.getElementById('weatherTemperature').textContent = `${weather.temperature}°C`;
    document.getElementById('weatherRainfall').textContent = `${weather.rainfall} mm`;
    document.getElementById('weatherFeelsLike').textContent = `${weather.feelsLike}°C`;
    document.getElementById('weatherHumidity').textContent = `${weather.humidity}%`;
    document.getElementById('weatherWind').textContent = `${weather.windSpeed} km/h`;

    const icon = document.getElementById('weatherIcon');
    const description = String(weather.description || '').toLowerCase();
    icon.className = description.includes('thunder')
        ? 'fa-solid fa-cloud-bolt'
        : description.includes('rain') || description.includes('drizzle')
            ? 'fa-solid fa-cloud-showers-heavy'
            : description.includes('cloud') || description.includes('overcast')
                ? 'fa-solid fa-cloud'
                : description.includes('sun') || description.includes('clear')
                    ? 'fa-solid fa-sun'
                    : 'fa-solid fa-cloud-sun';

    const updatedAt = new Date(data.fetchedAt);
    const timeLabel = Number.isNaN(updatedAt.getTime())
        ? 'today'
        : updatedAt.toLocaleString('en-PH', {
            timeZone: 'Asia/Manila',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    update.textContent = data.cached
        ? `Shared current-conditions update · checked ${timeLabel} Philippine time. Refreshes daily.`
        : `Shared current-conditions update · checked ${timeLabel} Philippine time.`;
    update.classList.remove('is-error');
}

async function loadWeather(requestUpdate = false) {
    const content = document.getElementById('weatherContent');
    const update = document.getElementById('weatherUpdate');
    const button = document.getElementById('loadWeatherButton');
    const buttonLabel = button.querySelector('.weather-button-label');
    const gate = document.getElementById('weatherGate');

    if (requestUpdate && button.disabled) return;
    gate.hidden = true;
    update.setAttribute('aria-hidden', 'false');
    update.classList.remove('is-error');
    update.textContent = "Checking today's weather update…";
    content.setAttribute('aria-busy', 'true');
    button.disabled = true;
    buttonLabel.textContent = 'Loading…';

    try {
        let data = await apiGet('get_weather.php');
        if (data.needsFetch || requestUpdate) {
            data = await apiPost('get_weather.php', {});
        }
        showWeather(data);
    } catch (error) {
        update.textContent = error.message || 'Could not check the shared weather update.';
        update.classList.add('is-error');
        gate.hidden = false;
        console.error('Failed to load shared weather:', error);
    } finally {
        content.setAttribute('aria-busy', 'false');
        button.disabled = false;
        buttonLabel.textContent = 'Get Weather';
    }
}

function taskDateValue(value) {
    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day);
}

function taskDateLabel(value) {
    return taskDateValue(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
}

function todayDateKey() {
    const today = new Date();
    return toDateKey(today.getFullYear(), today.getMonth() + 1, today.getDate());
}

function scheduledTaskItems() {
    const cropTasks = state.scheduledTasks.map(task => ({
        ...task,
        key: String(task.id),
        isCalendarTask: false,
    }));
    const calendarTasks = state.calendarTasks.map(task => ({
        ...task,
        id: `calendar-${task.CalendarID}`,
        key: `calendar-${task.CalendarID}`,
        type: task.TaskType,
        due_date: task.StartDate,
        status: task.Status === 'Completed' ? 'Done' : 'Pending',
        priority: 'Scheduled',
        is_urgent: 0,
        isCalendarTask: true,
    }));
    return [...cropTasks, ...calendarTasks];
}

function scheduledTaskStatusLabel(task) {
    if (task.status === 'Pending' && normalizeDateKey(task.due_date) > todayDateKey()) {
        return 'Upcoming / Paparating';
    }
    if (task.status === 'Pending') return 'Pending / Nakabinbin';
    if (task.status === 'Overdue') return 'Overdue / Lampas na sa takdang araw';
    if (task.status === 'Done') return 'Done / Tapos na';
    return task.status;
}

function taskTipsData(task) {
    if (task.tips && typeof task.tips === 'object') return task.tips;
    try {
        return JSON.parse(task.tips || '{}');
    } catch {
        return {};
    }
}

function visibleTasks() {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const weekEnd = new Date(today);
    weekEnd.setDate(weekEnd.getDate() + 6);

    return scheduledTaskItems().filter(task => {
        if (task.status === 'Done') return false;
        const due = taskDateValue(task.due_date);
        const overdue = task.status === 'Overdue' || due < today;
        if (state.taskFilter === 'all') return true;
        if (state.taskFilter === 'overdue') return overdue && task.status !== 'Done';
        if (state.taskFilter === 'today') return due.getTime() === today.getTime();
        return due >= today && due <= weekEnd;
    }).sort((a, b) => Number(b.is_urgent) - Number(a.is_urgent) || a.due_date.localeCompare(b.due_date));
}

function renderScheduledTasks() {
    const list = document.getElementById('scheduledTaskList');
    const tasks = visibleTasks();
    const focusedTask = state.focusedTaskId === null
        ? null
        : scheduledTaskItems().find(task => task.key === String(state.focusedTaskId) && task.status !== 'Done');
    if (focusedTask && !tasks.includes(focusedTask)) tasks.unshift(focusedTask);
    list.innerHTML = '';

    if (tasks.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'task-empty';
        empty.textContent = state.taskFilter === 'overdue'
            ? 'No overdue tasks.'
            : state.taskFilter === 'all' ? 'No active tasks.' : 'No tasks for this period.';
        list.appendChild(empty);
        return;
    }

    tasks.forEach(task => {
        const item = document.createElement('article');
        item.className = `scheduled-task${Number(task.is_urgent) ? ' is-urgent' : ''}${task.status === 'Done' ? ' is-done' : ''}`;
        item.dataset.taskId = task.key;
        const isFocusedTask = task.key === String(state.focusedTaskId);
        if (isFocusedTask) {
            item.classList.add('is-targeted');
            item.tabIndex = -1;
        }

        const content = document.createElement('div');
        content.className = 'scheduled-task-main';
        const heading = document.createElement('h3');
        heading.textContent = task.typeLabel || task.type;
        const crop = document.createElement('p');
        crop.className = 'scheduled-task-crop';
        crop.textContent = task.PlantLabel && task.CropName
            ? `${task.PlantLabel} · ${task.CropName}`
            : task.PlantLabel || task.CropName || 'General task';
        const meta = document.createElement('div');
        meta.className = 'scheduled-task-meta';
        meta.textContent = `${taskDateLabel(task.due_date)} · ${scheduledTaskStatusLabel(task)} · ${task.priority}`;
        content.append(heading, crop, meta);

        const actions = document.createElement('div');
        actions.className = 'scheduled-task-actions';
        const tipsButton = document.createElement('button');
        tipsButton.type = 'button';
        tipsButton.className = 'task-action-link';
        tipsButton.textContent = 'Tingnan ang Tip';
        tipsButton.addEventListener('click', () => openTaskTips(task));
        actions.appendChild(tipsButton);

        if (task.status !== 'Done') {
            const doneButton = document.createElement('button');
            doneButton.type = 'button';
            doneButton.className = 'task-done-button';
            doneButton.textContent = 'Markahan bilang Tapos na';
            doneButton.addEventListener('click', () => task.isCalendarTask
                ? markCalendarTaskDone(task, doneButton)
                : markTaskDone(task, doneButton));
            actions.appendChild(doneButton);
        }

        item.append(content, actions);
        list.appendChild(item);
    });
}

async function loadScheduledTasks() {
    const list = document.getElementById('scheduledTaskList');
    const summary = document.getElementById('scheduledTaskSummary');
    try {
        const data = await apiGet('scheduled_tasks.php');
        state.scheduledTasks = data.tasks || [];
        state.calendarTasks = data.calendarTasks || [];
        const activeCount = scheduledTaskItems().filter(task => task.status !== 'Done').length;
        const urgentCount = state.scheduledTasks.filter(task => Number(task.is_urgent) === 1 && task.status !== 'Done').length;
        document.getElementById('statScheduledTasks').textContent = String(activeCount).padStart(2, '0');
        summary.textContent = `${activeCount} active tasks · ${urgentCount} urgent`;
        renderScheduledTasks();
        renderMainCalendar();
        window.AgriTrackNotifications?.update(state.scheduledTasks, '', data.contactReplies || [], data.calendarTasks || []);
    } catch (err) {
        state.calendarTasks = [];
        summary.textContent = 'Tasks could not be loaded.';
        list.innerHTML = '';
        const message = document.createElement('p');
        message.className = 'task-empty task-error';
        message.textContent = err.message || 'Unable to load crop tasks.';
        list.appendChild(message);
        window.AgriTrackNotifications?.update([], err.message || 'Notifications could not be loaded.');
        console.error('Failed to load scheduled tasks:', err);
    }
}

function openTaskTips(task) {
    const tips = taskTipsData(task);
    document.getElementById('taskTipsCrop').textContent = `${task.PlantLabel || task.CropName || 'Pangkalahatang gawain'} · ${task.typeLabel || task.type}`;
    document.getElementById('taskTipsTitle').textContent = 'Mga Tip sa Gawain';
    document.getElementById('taskTipsActionWrap').hidden = !tips.action;
    document.getElementById('taskTipsAction').textContent = tips.action || '';
    document.getElementById('taskTipsFix').textContent = tips.fix || 'Sundin ang inirerekomendang pag-aalaga para sa gawaing ito.';
    document.getElementById('taskTipsPrevention').textContent = tips.prevention || 'Regular na suriin ang pananim at panatilihing malinis ang mga kagamitan.';
    document.getElementById('taskTipsFollowUp').textContent = tips.followUp || 'Suriin muli ang pananim makalipas ang 3 araw.';
    document.getElementById('taskTipsModal').hidden = false;
    document.getElementById('closeTaskTips').focus();
}

async function markTaskDone(task, button) {
    button.disabled = true;
    try {
        await apiPost('scheduled_task_update.php', { taskId: Number(task.id) });
        task.status = 'Done';
        renderScheduledTasks();
        renderMainCalendar();
        window.AgriTrackNotifications?.update(state.scheduledTasks);
        const activeCount = scheduledTaskItems().filter(item => item.status !== 'Done').length;
        document.getElementById('statScheduledTasks').textContent = String(activeCount).padStart(2, '0');
    } catch (err) {
        button.disabled = false;
        console.error('Failed to complete scheduled task:', err);
    }
}

async function markCalendarTaskDone(task, button) {
    button.disabled = true;
    try {
        const calendarId = Number(String(task.id).replace('calendar-', ''));
        await apiPost('calendar_update_status.php', { calendarId, status: 'Completed' });
        const savedTask = state.calendarTasks.find(item => Number(item.CalendarID) === calendarId);
        if (savedTask) savedTask.Status = 'Completed';
        renderScheduledTasks();
        renderMainCalendar();
        window.AgriTrackNotifications?.update(state.scheduledTasks, '', undefined, state.calendarTasks);
        const activeCount = scheduledTaskItems().filter(item => item.status !== 'Done').length;
        document.getElementById('statScheduledTasks').textContent = String(activeCount).padStart(2, '0');
    } catch (err) {
        button.disabled = false;
        console.error('Failed to complete calendar task:', err);
    }
}

// ---------- MAIN CALENDAR ----------

function showMainCalendarSkeleton(year, month) {
    const grid = document.getElementById('calendarGrid');
    const cells = Math.ceil((new Date(year, month - 1, 1).getDay() + new Date(year, month, 0).getDate()) / 7) * 7;
    grid.setAttribute('aria-busy', 'true');
    grid.innerHTML = Array.from({ length: cells }, () =>
        '<span class="skeleton-bar skeleton-calendar-cell" aria-hidden="true"></span>'
    ).join('');
}

async function loadMonthData(year, month) {
    showMainCalendarSkeleton(year, month);
    try {
        const data = await apiGet(`calendar_get.php?year=${year}&month=${month}`);

        state.tasksByDate = {};
        (data.tasks || []).forEach(t => {
            const key = normalizeDateKey(t.StartDate);
            if (!state.tasksByDate[key]) state.tasksByDate[key] = [];
            state.tasksByDate[key].push(t);
        });

        state.notesByDate = {};
        (data.notes || []).forEach(n => {
            const key = normalizeDateKey(n.EntryDate);
            if (!state.notesByDate[key]) state.notesByDate[key] = [];
            state.notesByDate[key].push(n);
        });

        state.harvestByDate = {};
        (data.harvests || []).forEach(h => {
            const key = normalizeDateKey(h.ExpectedHarvestDate);
            if (!state.harvestByDate[key]) state.harvestByDate[key] = [];
            state.harvestByDate[key].push(h);
        });
    } catch (err) {
        console.error('Failed to load calendar data:', err);
        state.tasksByDate = {};
        state.notesByDate = {};
        state.harvestByDate = {};
    }

    renderMainCalendar();
}

function renderMainCalendar() {
    const { viewYear, viewMonth } = state;

    document.getElementById('calendarMonthYear').textContent = `${MONTH_NAMES[viewMonth - 1]} ${viewYear}`;
    document.getElementById('calendarMonthLabel').textContent = MONTH_NAMES[viewMonth - 1];

    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';
    grid.setAttribute('aria-busy', 'false');

    const firstWeekday = new Date(viewYear, viewMonth - 1, 1).getDay(); // 0=Sun
    const daysInMonth = new Date(viewYear, viewMonth, 0).getDate();

    for (let i = 0; i < firstWeekday; i++) {
        grid.appendChild(document.createElement('span'));
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const key = toDateKey(viewYear, viewMonth, day);
        const span = document.createElement('span');
        span.className = 'date';
        span.textContent = String(day).padStart(2, '0');

        if (isToday(viewYear, viewMonth, day)) {
            span.classList.add('today');
        }
        const calendarTasks = state.tasksByDate[key] || [];
        const cropTasks = state.scheduledTasks.filter(task =>
            task.status !== 'Done' && normalizeDateKey(task.due_date) === key
        );
        const scheduledCount = calendarTasks.length + cropTasks.length;
        if (scheduledCount || state.notesByDate[key]) {
            span.classList.add('has-entry');
        }
        if (scheduledCount) {
            span.classList.add('has-scheduled-task');
            const taskNames = [
                ...calendarTasks.map(task => task.TaskType),
                ...cropTasks.map(task => task.type),
            ];
            span.title = `${scheduledCount} scheduled task${scheduledCount === 1 ? '' : 's'}: ${taskNames.join(', ')}`;
            span.setAttribute('aria-label', `${key}: ${span.title}`);
        } else {
            span.setAttribute('aria-label', key);
        }
        if (state.notesByDate[key]) {
            span.classList.add('has-note');
            span.title = `${span.title ? `${span.title} · ` : ''}May tala / Has note`;
        }
        if (state.harvestByDate[key]) {
            span.classList.add('harvest-ready');
            span.title = state.harvestByDate[key]
                .map(h => `${h.CropName || h.EnglishName} — handa nang anihin`)
                .join(', ');
        }

        span.addEventListener('click', () => openNoteModal(key));
        grid.appendChild(span);
    }
}

// ---------- MONTH NAVIGATION ----------

function goToMonth(delta) {
    let { viewYear, viewMonth } = state;
    viewMonth += delta;
    if (viewMonth < 1) {
        viewMonth = 12;
        viewYear -= 1;
    } else if (viewMonth > 12) {
        viewMonth = 1;
        viewYear += 1;
    }
    state.viewYear = viewYear;
    state.viewMonth = viewMonth;
    loadMonthData(viewYear, viewMonth);
}

// ---------- ADD NOTE MODAL ----------

const noteModal = document.getElementById('noteModal');

function renderModalCalendar(selectedKey) {
    // Derive the month/year to render from the selected key itself, rather
    // than always trusting state.viewYear/viewMonth, so the highlighted day
    // is always in the month actually being shown.
    const [selYear, selMonth] = selectedKey
        ? selectedKey.split('-').map(Number)
        : [state.viewYear, state.viewMonth];

    document.getElementById('modalMonthYear').textContent = `${MONTH_NAMES[selMonth - 1]} ${selYear}`;
    document.getElementById('modalMonthLabel').textContent = MONTH_NAMES[selMonth - 1];

    const grid = document.getElementById('modalCalendarGrid');
    grid.innerHTML = '';

    const firstWeekday = new Date(selYear, selMonth - 1, 1).getDay();
    const daysInMonth = new Date(selYear, selMonth, 0).getDate();

    for (let i = 0; i < firstWeekday; i++) {
        grid.appendChild(document.createElement('span'));
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const key = toDateKey(selYear, selMonth, day);
        const span = document.createElement('span');
        span.textContent = String(day).padStart(2, '0');

        if (key === selectedKey) {
            span.classList.add('selected');
        }
        if (state.harvestByDate[key]) {
            span.classList.add('harvest-ready');
        }

        span.addEventListener('click', () => {
            grid.querySelectorAll('span').forEach(s => s.classList.remove('selected'));
            span.classList.add('selected');
            setSelectedNoteDate(key);
        });

        grid.appendChild(span);
    }
}

function setSelectedNoteDate(key) {
    state.selectedNoteDate = key;
    document.getElementById('modalSelectedDateText').textContent = key;
    loadExistingNotesForDate(key);
}

async function loadPlantedCrops() {
    try {
        const res = await fetch(API_BASE + 'get_planted_crops.php', { credentials: 'same-origin' });
        const crops = await res.json();
        if (!res.ok || !Array.isArray(crops)) throw new Error('Invalid crop response.');
        state.plantedCrops = crops;

        const select = document.getElementById('modalCropSelect');
        select.innerHTML = '<option value="">Pangkalahatang tala / General note</option>';
        crops.forEach(crop => {
            const option = document.createElement('option');
            option.value = crop.PlantedCropID;
            option.textContent = `${crop.PlantLabel} - ${crop.CropName}`;
            select.appendChild(option);
        });
    } catch (err) {
        console.error('Failed to load planted crops:', err);
    }
}

// Parses a stored Message string like:
//   "Tala: Sobrang init ngayon | Gawain: Nagdilig | Panahon: Maaraw"
// into { freeText, tagGroups: [{ label, values: [...] }] }
function parseNoteMessage(message) {
    const segments = String(message || '').split('|').map(s => s.trim()).filter(Boolean);
    let freeText = '';
    const tagGroups = [];

    segments.forEach(segment => {
        const colonIndex = segment.indexOf(':');
        if (colonIndex === -1) {
            // No label at all — treat as free text (covers notes saved
            // before the "Tala:" label existed).
            freeText = freeText ? `${freeText} ${segment}` : segment;
            return;
        }

        const label = segment.slice(0, colonIndex).trim();
        const value = segment.slice(colonIndex + 1).trim();

        if (label === 'Tala') {
            freeText = freeText ? `${freeText} ${value}` : value;
            return;
        }

        const displayLabel = TAG_ROW_LABELS[label] || label;
        tagGroups.push({
            label: displayLabel,
            values: value.split(',').map(v => v.trim()).filter(Boolean),
        });
    });

    return { freeText, tagGroups };
}

function buildNoteCard(note) {
    const { freeText, tagGroups } = parseNoteMessage(note.Message);

    const card = document.createElement('div');
    card.className = 'note-entry';

    if (freeText) {
        const freeTextEl = document.createElement('p');
        freeTextEl.className = 'note-freetext';
        freeTextEl.textContent = freeText;
        card.appendChild(freeTextEl);
    }

    tagGroups.forEach(group => {
        const row = document.createElement('div');
        row.className = 'note-tag-row';

        const labelEl = document.createElement('span');
        labelEl.className = 'note-tag-row-label';
        labelEl.textContent = group.label + ':';
        row.appendChild(labelEl);

        group.values.forEach(value => {
            const pill = document.createElement('span');
            pill.className = 'note-tag-pill';
            pill.textContent = value;
            row.appendChild(pill);
        });

        card.appendChild(row);
    });

    if (!freeText && tagGroups.length === 0) {
        const fallback = document.createElement('p');
        fallback.className = 'note-freetext';
        fallback.textContent = note.Message || '';
        card.appendChild(fallback);
    }

    return card;
}

// Renders the "🌾 crop ready" banner for a date that has one or more
// planted crops reaching their ExpectedHarvestDate that day.
function buildHarvestBanner(harvests) {
    const banner = document.createElement('div');
    banner.className = 'harvest-banner';

    harvests.forEach(h => {
        const item = document.createElement('p');
        item.className = 'harvest-banner-item';
        const name = h.CropName || h.EnglishName || 'Pananim';
        item.textContent = `🌾 ${name} — handa nang anihin / ready to harvest`;
        banner.appendChild(item);
    });

    return banner;
}

async function loadExistingNotesForDate(key) {
    const container = document.getElementById('modalExistingNotes');
    container.innerHTML = '';

    const scheduledTasks = [
        ...(state.tasksByDate[key] || []).map(task => ({
            title: task.TaskType,
            details: `${task.Status === 'Pending' && normalizeDateKey(task.StartDate) > todayDateKey() ? 'Upcoming / Paparating' : task.Status === 'Pending' ? 'Pending / Nakabinbin' : task.Status === 'Completed' ? 'Done / Tapos na' : task.Status}${task.CropName ? ` · ${task.PlantLabel || task.CropName}` : ''}`,
        })),
        ...state.scheduledTasks
            .filter(task => task.status !== 'Done' && normalizeDateKey(task.due_date) === key)
            .map(task => ({
                title: task.type,
                details: `${scheduledTaskStatusLabel(task)} · ${task.PlantLabel || task.CropName} · ${task.priority} priority`,
            })),
    ];
    if (scheduledTasks.length > 0) {
        const heading = document.createElement('p');
        heading.className = 'modal-existing-notes-heading';
        heading.textContent = 'Scheduled tasks for this day / Mga naka-iskedyul na gawain sa araw na ito:';
        container.appendChild(heading);
        scheduledTasks.forEach(task => {
            const item = document.createElement('p');
            item.className = 'calendar-task-entry';
            const title = document.createElement('strong');
            title.textContent = task.title;
            item.append(title, document.createTextNode(` · ${task.details}`));
            container.appendChild(item);
        });
    }

    const harvests = state.harvestByDate[key];
    if (harvests && harvests.length > 0) {
        container.appendChild(buildHarvestBanner(harvests));
    }

    try {
        const cropQuery = state.selectedPlantedCropId === null
            ? ''
            : `&plantedCropId=${encodeURIComponent(state.selectedPlantedCropId)}`;
        const data = await apiGet(`notes_get.php?date=${key}${cropQuery}`);
        if (!data.notes || data.notes.length === 0) return;

        const heading = document.createElement('p');
        heading.className = 'modal-existing-notes-heading';
        heading.textContent = 'Mga nakaraang tala sa araw na ito / Existing notes for this day:';
        container.appendChild(heading);

        data.notes.forEach(n => {
            container.appendChild(buildNoteCard(n));
        });
    } catch (err) {
        console.error('Failed to load notes for date:', err);
    }
}

function openNoteModal(dateKey) {
    const target = dateKey || toDateKey(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate());
    resetModalSelections();
    setNoteModalView('calendar');
    renderModalCalendar(target);
    setSelectedNoteDate(target);
    noteModal.classList.add('show');
}

function setNoteModalView(view) {
    const modalContent = document.querySelector('.note-modal-content');
    const isCalendar = view === 'calendar';
    modalContent.classList.toggle('show-calendar', isCalendar);
    modalContent.classList.toggle('show-actions', !isCalendar);
    document.querySelectorAll('[data-modal-view]').forEach(tab => {
        const active = tab.dataset.modalView === view;
        tab.classList.toggle('is-active', active);
        tab.setAttribute('aria-selected', String(active));
        tab.tabIndex = active ? 0 : -1;
    });
}

async function scheduleCalendarTask() {
    const input = document.getElementById('calendarTaskInput');
    const error = document.getElementById('calendarTaskError');
    const button = document.getElementById('scheduleCalendarTask');
    const taskType = input.value.trim();
    error.textContent = '';
    if (!state.selectedNoteDate) {
        error.textContent = 'Select a date first / Pumili muna ng petsa.';
        return;
    }
    if (!taskType) {
        error.textContent = 'Enter a task / Maglagay ng gawain.';
        input.focus();
        return;
    }

    button.disabled = true;
    try {
        await apiPost('calendar_add.php', {
            taskType,
            startDate: state.selectedNoteDate,
            plantedCropId: state.selectedPlantedCropId,
        });
        input.value = '';
        await loadMonthData(state.viewYear, state.viewMonth);
        await loadExistingNotesForDate(state.selectedNoteDate);
        showSaveToast('Task scheduled / Naka-iskedyul na ang gawain.');
    } catch (err) {
        error.textContent = err.message
            ? `${err.message} / Hindi na-iskedyul ang gawain.`
            : 'The task could not be scheduled / Hindi na-iskedyul ang gawain.';
        console.error('Failed to schedule calendar task:', err);
    } finally {
        button.disabled = false;
    }
}

function resetModalSelections() {
    document.querySelectorAll('.tag-list button').forEach(btn => {
        btn.classList.remove('selected');
        if (btn.dataset.customValue) btn.remove();
    });
    document.querySelectorAll('.custom-tag-input').forEach(input => input.remove());
    document.getElementById('modalFreeText').value = '';
    document.getElementById('modalErrorText').textContent = '';
    document.getElementById('calendarTaskError').textContent = '';
    document.getElementById('calendarTaskInput').value = '';
    state.selectedPlantedCropId = null;
    const cropSelect = document.getElementById('modalCropSelect');
    cropSelect.value = '';
    cropSelect.size = 1;
}

function addCustomTag(button) {
    const group = button.closest('.tag-list');
    const existingInput = group.querySelector('.custom-tag-input');
    if (existingInput) {
        existingInput.focus();
        return;
    }

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'custom-tag-input';
    input.placeholder = 'Custom choice';
    input.maxLength = 80;
    input.pattern = '[A-Za-z0-9 ]+';
    group.insertBefore(input, button);
    input.focus();

    const commit = () => {
        const value = input.value.trim();
        if (!value) return;
        if (!/^[a-z0-9]+(?:[a-z0-9 ]*)$/i.test(value)) {
            input.setCustomValidity('Use letters, numbers, and spaces only.');
            input.reportValidity();
            input.focus();
            return;
        }
        input.remove();

        const duplicate = Array.from(group.querySelectorAll('button'))
            .some(tag => tag.dataset.customValue === value || tag.textContent.trim() === value);
        if (duplicate) return;

        const customTag = document.createElement('button');
        customTag.type = 'button';
        customTag.className = 'custom-tag-value selected';
        customTag.dataset.customValue = value;
        customTag.textContent = value;
        group.insertBefore(customTag, button);
        customTag.addEventListener('click', () => customTag.classList.toggle('selected'));
    };

    input.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            commit();
        } else if (event.key === 'Escape') {
            input.remove();
        }
    });
    input.addEventListener('blur', commit);
}

function getSelectedTags(group) {
    return Array.from(document.querySelectorAll(`.tag-list[data-group="${group}"] button.selected`))
        .map(btn => btn.textContent.trim());
}

function startNoteCooldown(button, seconds) {
    if (noteCooldownTimer) clearInterval(noteCooldownTimer);
    let remaining = seconds;
    button.disabled = true;
    const tick = () => {
        button.textContent = `Pakihintay (${remaining}s)`;
        remaining -= 1;
        if (remaining < 0) {
            clearInterval(noteCooldownTimer);
            noteCooldownTimer = null;
            button.disabled = false;
            button.textContent = NOTE_SAVE_LABEL;
        }
    };
    tick();
    noteCooldownTimer = setInterval(tick, 1000);
}

function showSaveToast(message) {
    const toast = document.getElementById('saveToast');
    document.getElementById('saveToastText').textContent = message;
    toast.classList.remove('show');
    void toast.offsetWidth;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3600);
}

async function saveNote() {
    const errorEl = document.getElementById('modalErrorText');
    errorEl.textContent = '';

    if (!state.selectedNoteDate) {
        errorEl.textContent = 'Pumili ng petsa. / Please select a date.';
        return;
    }

    const payload = {
        entryDate: state.selectedNoteDate,
        plantedCropId: state.selectedPlantedCropId,
        activityTags: getSelectedTags('activity'),
        conditionTags: getSelectedTags('condition'),
        weatherTags: getSelectedTags('weather'),
        message: document.getElementById('modalFreeText').value.trim(),
    };

    if (
        payload.activityTags.length === 0 &&
        payload.conditionTags.length === 0 &&
        payload.weatherTags.length === 0 &&
        payload.message === ''
    ) {
        errorEl.textContent = 'Pumili ng tag o sumulat ng tala. / Select a tag or write a note.';
        return;
    }

    const saveBtn = document.getElementById('saveNoteBtn');
    if (saveBtn.disabled) return; // extra guard against double-fire
    saveBtn.disabled = true;
    saveBtn.textContent = 'Sine-save...';

    try {
        await apiPost('notes_add.php', payload);
        noteModal.classList.remove('show');
        saveBtn.disabled = false;
        saveBtn.textContent = NOTE_SAVE_LABEL;
        await loadMonthData(state.viewYear, state.viewMonth);
        showSaveToast('Na-save ang impormasyon ng pananim. / Crop information saved.');
    } catch (err) {
        if (err.status === 429) {
            errorEl.textContent = err.message || 'Masyadong mabilis. Pakihintay saglit.';
            startNoteCooldown(saveBtn, err.retryAfter || 4);
            return;
        }
        errorEl.textContent = err.message || 'Hindi na-save ang tala. / Failed to save note.';
        console.error('saveNote failed:', err);
        saveBtn.disabled = false;
        saveBtn.textContent = NOTE_SAVE_LABEL;
    }
}

// ---------- WIRE UP ----------

document.addEventListener('DOMContentLoaded', () => {
    loadGreeting();
    loadStats();
    document.getElementById('loadWeatherButton').addEventListener('click', () => loadWeather(true));
    loadWeather();
    const scheduledTasksLoad = loadScheduledTasks();
    loadPlantedCrops();
    const calendarLoad = loadMonthData(state.viewYear, state.viewMonth);

    document.querySelectorAll('[data-task-filter]').forEach(button => {
        button.addEventListener('click', () => {
            state.taskFilter = button.dataset.taskFilter;
            state.focusedTaskId = null;
            document.querySelectorAll('[data-task-filter]').forEach(filter => {
                const active = filter === button;
                filter.classList.toggle('is-active', active);
                filter.setAttribute('aria-pressed', String(active));
            });
            renderScheduledTasks();
        });
    });

    const scheduledTasksModal = document.getElementById('scheduledTasksModal');
    const openScheduledTasksButton = document.getElementById('openScheduledTasks');
    let scheduledTasksOpener = openScheduledTasksButton;
    const closeScheduledTasks = () => {
        scheduledTasksModal.hidden = true;
        scheduledTasksOpener.focus();
    };
    const showScheduledTasks = event => {
        scheduledTasksOpener = event.currentTarget;
        scheduledTasksModal.hidden = false;
        document.getElementById('closeScheduledTasks').focus();
    };
    openScheduledTasksButton.addEventListener('click', showScheduledTasks);
    document.getElementById('closeScheduledTasks').addEventListener('click', closeScheduledTasks);
    scheduledTasksModal.addEventListener('click', event => {
        if (event.target === scheduledTasksModal) closeScheduledTasks();
    });

    const taskTipsModal = document.getElementById('taskTipsModal');
    document.getElementById('closeTaskTips').addEventListener('click', () => {
        taskTipsModal.hidden = true;
    });
    taskTipsModal.addEventListener('click', event => {
        if (event.target === taskTipsModal) taskTipsModal.hidden = true;
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (!taskTipsModal.hidden) {
            taskTipsModal.hidden = true;
        } else if (!scheduledTasksModal.hidden) {
            closeScheduledTasks();
        }
    });

    const dashboardParams = new URLSearchParams(window.location.search);
    const requestedTaskId = Number(dashboardParams.get('taskId'));
    const requestedCalendarTaskId = Number(dashboardParams.get('calendarTaskId'));
    if (dashboardParams.get('openTasks') === '1') {
        scheduledTasksLoad.then(() => {
            const task = requestedCalendarTaskId
                ? scheduledTaskItems().find(item => item.isCalendarTask && Number(item.CalendarID) === requestedCalendarTaskId)
                : state.scheduledTasks.find(item => Number(item.id) === requestedTaskId);
            if (task) {
                state.focusedTaskId = task.key;
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const dueDate = taskDateValue(task.due_date);
                if (task.status === 'Overdue' || dueDate < today) state.taskFilter = 'overdue';
                else if (dueDate.getTime() === today.getTime()) state.taskFilter = 'today';
                else state.taskFilter = 'week';

                document.querySelectorAll('[data-task-filter]').forEach(button => {
                    const active = button.dataset.taskFilter === state.taskFilter;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-pressed', String(active));
                });
                renderScheduledTasks();
            }

            if (dashboardParams.get('openCalendar') === '1') {
                const date = dashboardParams.get('date');
                Promise.all([calendarLoad, scheduledTasksLoad]).then(async () => {
                    if (/^\d{4}-\d{2}-\d{2}$/.test(date || '')) {
                        const [year, month] = date.split('-').map(Number);
                        if (year !== state.viewYear || month !== state.viewMonth) {
                            state.viewYear = year;
                            state.viewMonth = month;
                            await loadMonthData(year, month);
                        }
                        openNoteModal(date);
                    } else {
                        openNoteModal(null);
                    }
                    window.history.replaceState(null, '', `${window.location.pathname}${window.location.hash}`);
                });
            }

            openScheduledTasksButton.click();
            if (task) {
                window.history.replaceState(null, '', `${window.location.pathname}${window.location.hash}`);
                window.requestAnimationFrame(() => {
                    const taskElement = document.querySelector(`[data-task-id="${task.key}"]`);
                    taskElement?.scrollIntoView({ block: 'nearest' });
                    (taskElement?.querySelector('.task-done-button') || taskElement)?.focus();
                });
            }
        });
    }

    document.getElementById('prevMonthBtn').addEventListener('click', () => goToMonth(-1));
    document.getElementById('nextMonthBtn').addEventListener('click', () => goToMonth(1));

    document.getElementById('openNoteModal').addEventListener('click', () => openNoteModal(null));
    document.getElementById('closeNoteModal').addEventListener('click', () => noteModal.classList.remove('show'));
    document.querySelectorAll('[data-modal-view]').forEach((tab, index, tabs) => {
        tab.addEventListener('click', () => setNoteModalView(tab.dataset.modalView));
        tab.addEventListener('keydown', event => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            const nextTab = tabs[(index + direction + tabs.length) % tabs.length];
            setNoteModalView(nextTab.dataset.modalView);
            nextTab.focus();
        });
    });

    const cropSelect = document.getElementById('modalCropSelect');

    cropSelect.addEventListener('focus', () => {
        cropSelect.size = Math.min(6, Math.max(1, cropSelect.options.length));
    });

    cropSelect.addEventListener('blur', () => {
        cropSelect.size = 1;
    });

    cropSelect.addEventListener('change', (event) => {
        state.selectedPlantedCropId = event.target.value === '' ? null : Number(event.target.value);
        if (state.selectedNoteDate) loadExistingNotesForDate(state.selectedNoteDate);
        window.setTimeout(() => {
            cropSelect.size = 1;
        }, 0);
    });

    noteModal.addEventListener('click', (event) => {
        if (event.target === noteModal) {
            noteModal.classList.remove('show');
        }
    });

    document.querySelectorAll('.tag-list button').forEach(button => {
        button.addEventListener('click', function () {
            if (this.hasAttribute('data-custom-tag')) {
                addCustomTag(this);
                return;
            }
            this.classList.toggle('selected');
        });
    });

    document.getElementById('saveNoteBtn').addEventListener('click', saveNote);
    document.getElementById('scheduleCalendarTask').addEventListener('click', scheduleCalendarTask);
    document.getElementById('calendarTaskInput').addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            scheduleCalendarTask();
        }
    });
});