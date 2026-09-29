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
    taskFilter: 'week',
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
    } catch (err) {
        console.error('Failed to load stats:', err);
    }
}

function taskDateValue(value) {
    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);
    return new Date(year, month - 1, day);
}

function taskDateLabel(value) {
    return taskDateValue(value).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
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

    return state.scheduledTasks.filter(task => {
        const due = taskDateValue(task.due_date);
        const overdue = task.status === 'Overdue' || due < today;
        if (state.taskFilter === 'overdue') return overdue && task.status !== 'Done';
        if (state.taskFilter === 'today') return due.getTime() === today.getTime();
        return due >= today && due <= weekEnd;
    }).sort((a, b) => Number(b.is_urgent) - Number(a.is_urgent) || a.due_date.localeCompare(b.due_date));
}

function renderScheduledTasks() {
    const list = document.getElementById('scheduledTaskList');
    const tasks = visibleTasks();
    list.innerHTML = '';

    if (tasks.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'task-empty';
        empty.textContent = state.taskFilter === 'overdue' ? 'No overdue tasks.' : 'No tasks for this period.';
        list.appendChild(empty);
        return;
    }

    tasks.forEach(task => {
        const item = document.createElement('article');
        item.className = `scheduled-task${Number(task.is_urgent) ? ' is-urgent' : ''}${task.status === 'Done' ? ' is-done' : ''}`;

        const content = document.createElement('div');
        content.className = 'scheduled-task-main';
        const heading = document.createElement('h3');
        heading.textContent = task.type;
        const crop = document.createElement('p');
        crop.className = 'scheduled-task-crop';
        crop.textContent = `${task.PlantLabel || task.CropName} · ${task.CropName}`;
        const meta = document.createElement('div');
        meta.className = 'scheduled-task-meta';
        meta.textContent = `${taskDateLabel(task.due_date)} · ${task.status} · ${task.priority}`;
        content.append(heading, crop, meta);

        const actions = document.createElement('div');
        actions.className = 'scheduled-task-actions';
        const tipsButton = document.createElement('button');
        tipsButton.type = 'button';
        tipsButton.className = 'task-action-link';
        tipsButton.textContent = 'View Tips';
        tipsButton.addEventListener('click', () => openTaskTips(task));
        actions.appendChild(tipsButton);

        if (task.status !== 'Done') {
            const doneButton = document.createElement('button');
            doneButton.type = 'button';
            doneButton.className = 'task-done-button';
            doneButton.textContent = 'Mark Done';
            doneButton.addEventListener('click', () => markTaskDone(task, doneButton));
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
        const activeCount = state.scheduledTasks.filter(task => task.status !== 'Done').length;
        const urgentCount = state.scheduledTasks.filter(task => Number(task.is_urgent) === 1 && task.status !== 'Done').length;
        document.getElementById('statScheduledTasks').textContent = String(activeCount).padStart(2, '0');
        summary.textContent = `${activeCount} active tasks · ${urgentCount} urgent`;
        renderScheduledTasks();
    } catch (err) {
        summary.textContent = 'Tasks could not be loaded.';
        list.innerHTML = '';
        const message = document.createElement('p');
        message.className = 'task-empty task-error';
        message.textContent = err.message || 'Unable to load crop tasks.';
        list.appendChild(message);
        console.error('Failed to load scheduled tasks:', err);
    }
}

function openTaskTips(task) {
    const tips = taskTipsData(task);
    document.getElementById('taskTipsCrop').textContent = `${task.PlantLabel || task.CropName} · ${task.type}`;
    document.getElementById('taskTipsTitle').textContent = 'Task tips';
    document.getElementById('taskTipsActionWrap').hidden = !tips.action;
    document.getElementById('taskTipsAction').textContent = tips.action || '';
    document.getElementById('taskTipsFix').textContent = tips.fix || 'Follow the recommended crop care for this task.';
    document.getElementById('taskTipsPrevention').textContent = tips.prevention || 'Inspect the crop regularly and keep tools clean.';
    document.getElementById('taskTipsFollowUp').textContent = tips.followUp || 'Re-check the crop in 3 days.';
    document.getElementById('taskTipsModal').hidden = false;
    document.getElementById('closeTaskTips').focus();
}

async function markTaskDone(task, button) {
    button.disabled = true;
    try {
        await apiPost('scheduled_task_update.php', { taskId: Number(task.id) });
        task.status = 'Done';
        renderScheduledTasks();
        const activeCount = state.scheduledTasks.filter(item => item.status !== 'Done').length;
        document.getElementById('statScheduledTasks').textContent = String(activeCount).padStart(2, '0');
    } catch (err) {
        button.disabled = false;
        console.error('Failed to complete scheduled task:', err);
    }
}

// ---------- MAIN CALENDAR ----------

async function loadMonthData(year, month) {
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
        if (state.tasksByDate[key] || state.notesByDate[key]) {
            span.classList.add('has-entry');
        }
        if (state.notesByDate[key]) {
            span.classList.add('has-note');
            span.title = 'May tala / Has note';
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
    renderModalCalendar(target);
    setSelectedNoteDate(target);
    noteModal.classList.add('show');
}

function resetModalSelections() {
    document.querySelectorAll('.tag-list button').forEach(btn => btn.classList.remove('selected'));
    document.getElementById('modalFreeText').value = '';
    document.getElementById('modalErrorText').textContent = '';
    state.selectedPlantedCropId = null;
    const cropSelect = document.getElementById('modalCropSelect');
    cropSelect.value = '';
    cropSelect.size = 1;
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
    loadScheduledTasks();
    loadPlantedCrops();
    loadMonthData(state.viewYear, state.viewMonth);

    document.querySelectorAll('[data-task-filter]').forEach(button => {
        button.addEventListener('click', () => {
            state.taskFilter = button.dataset.taskFilter;
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
    const closeScheduledTasks = () => {
        scheduledTasksModal.hidden = true;
        openScheduledTasksButton.focus();
    };
    openScheduledTasksButton.addEventListener('click', () => {
        scheduledTasksModal.hidden = false;
        document.getElementById('closeScheduledTasks').focus();
    });
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

    document.getElementById('prevMonthBtn').addEventListener('click', () => goToMonth(-1));
    document.getElementById('nextMonthBtn').addEventListener('click', () => goToMonth(1));

    document.getElementById('openNoteModal').addEventListener('click', () => openNoteModal(null));

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
            this.classList.toggle('selected');
        });
    });

    document.getElementById('saveNoteBtn').addEventListener('click', saveNote);
});