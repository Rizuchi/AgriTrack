const API_BASE = '../php/';

const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const state = {
    viewYear: new Date().getFullYear(),
    viewMonth: new Date().getMonth() + 1, // 1-12
    tasksByDate: {},   // 'YYYY-MM-DD' FORMAT FOR TASK AND NOTES
    notesByDate: {},   
    selectedNoteDate: null, 
};

function pad2(n) {
    return String(n).padStart(2, '0');
}

function toDateKey(year, month, day) {
    return `${year}-${pad2(month)}-${pad2(day)}`;
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
        throw new Error(data.message || 'Request failed.');
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
        document.getElementById('statScheduledTasks').textContent = String(data.scheduledTasks).padStart(2, '0');
    } catch (err) {
        console.error('Failed to load stats:', err);
    }
}

// ---------- MAIN CALENDAR ----------

async function loadMonthData(year, month) {
    try {
        const data = await apiGet(`calendar_get.php?year=${year}&month=${month}`);

        state.tasksByDate = {};
        (data.tasks || []).forEach(t => {
            const key = t.StartDate;
            if (!state.tasksByDate[key]) state.tasksByDate[key] = [];
            state.tasksByDate[key].push(t);
        });

        state.notesByDate = {};
        (data.notes || []).forEach(n => {
            const key = n.EntryDate;
            if (!state.notesByDate[key]) state.notesByDate[key] = [];
            state.notesByDate[key].push(n);
        });
    } catch (err) {
        console.error('Failed to load calendar data:', err);
        state.tasksByDate = {};
        state.notesByDate = {};
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
    const { viewYear, viewMonth } = state;

    document.getElementById('modalMonthYear').textContent = `${MONTH_NAMES[viewMonth - 1]} ${viewYear}`;
    document.getElementById('modalMonthLabel').textContent = MONTH_NAMES[viewMonth - 1];

    const grid = document.getElementById('modalCalendarGrid');
    grid.innerHTML = '';

    const firstWeekday = new Date(viewYear, viewMonth - 1, 1).getDay();
    const daysInMonth = new Date(viewYear, viewMonth, 0).getDate();

    for (let i = 0; i < firstWeekday; i++) {
        grid.appendChild(document.createElement('span'));
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const key = toDateKey(viewYear, viewMonth, day);
        const span = document.createElement('span');
        span.textContent = String(day).padStart(2, '0');

        if (key === selectedKey) {
            span.classList.add('selected');
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

async function loadExistingNotesForDate(key) {
    const container = document.getElementById('modalExistingNotes');
    container.innerHTML = '';
    try {
        const data = await apiGet(`notes_get.php?date=${key}`);
        if (!data.notes || data.notes.length === 0) return;

        const heading = document.createElement('p');
        heading.className = 'modal-existing-notes-heading';
        heading.textContent = 'Mga nakaraang tala sa araw na ito / Existing notes for this day:';
        container.appendChild(heading);

        data.notes.forEach(n => {
            const p = document.createElement('p');
            p.className = 'modal-existing-note';
            p.textContent = n.Message;
            container.appendChild(p);
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
}

function getSelectedTags(group) {
    return Array.from(document.querySelectorAll(`.tag-list[data-group="${group}"] button.selected`))
        .map(btn => btn.textContent.trim());
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
    saveBtn.disabled = true;
    saveBtn.textContent = 'Sine-save...';

    try {
        await apiPost('notes_add.php', payload);
        noteModal.classList.remove('show');
        await loadMonthData(state.viewYear, state.viewMonth);
    } catch (err) {
        errorEl.textContent = err.message || 'Hindi na-save ang tala. / Failed to save note.';
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = 'I-save / Save';
    }
}

// ---------- WIRE UP ----------

document.addEventListener('DOMContentLoaded', () => {
    loadGreeting();
    loadStats();
    loadMonthData(state.viewYear, state.viewMonth);

    document.getElementById('prevMonthBtn').addEventListener('click', () => goToMonth(-1));
    document.getElementById('nextMonthBtn').addEventListener('click', () => goToMonth(1));

    document.getElementById('openNoteModal').addEventListener('click', () => openNoteModal(null));

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
