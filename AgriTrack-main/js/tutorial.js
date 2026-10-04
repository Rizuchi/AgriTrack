(() => {
    const API_BASE = '../php/';
    const pageGuides = {
        'userdashboard.html': [
            ['.calendar-card', 'Kalendaryo / Calendar', 'Makikita rito ang mga araw ng buwan at ang mga tala o gawaing nakatalaga sa mga ito. / See the days of the month and the notes or tasks saved for them.'],
            ['#prevMonthBtn', 'Nakaraang Buwan / Previous Month', 'Pindutin para tingnan ang nakaraang buwan. / Select to view the previous month.'],
            ['#nextMonthBtn', 'Susunod na Buwan / Next Month', 'Pindutin para tingnan ang susunod na buwan. / Select to view the next month.'],
            ['#calendarGrid', 'Mga Araw at Palatandaan / Days and Markers', 'Ang araw na may palatandaan ay may nakatalang tala o gawain. Pindutin ang isang araw para makita ang detalye. / A marked day has a saved note or task. Select a day to see its details.'],
            ['#openNoteModal', 'Gumawa ng Tala / Add a Note', 'Magtala ng aktibidad, kondisyon ng pananim, o lagay ng panahon. Piliin muna ang tamang petsa. / Record an activity, crop condition, or weather. Pick the right date first.'],
            ['#openScheduledTasks', 'Mga Nakaiskedyul na Gawain / Scheduled Tasks', 'Tingnan ang mga paalala sa pag-aalaga ng pananim. Markahang tapos ang gawain kapag nagawa mo na ito. / View your crop-care reminders. Mark a task done once you have finished it.'],
            ['.stats-grid .stat-card[aria-label^="Kabuuang Pananim"]', 'Bilang ng Pananim / Crop Count', 'Ipinapakita ang bilang ng mga naitala mong pananim. Pindutin ito para makita ang listahan. / Shows how many crops you have recorded. Select it to see the list.'],
            ['.stats-grid .stat-card[aria-label^="Mga Aktibong Pagsubaybay"]', 'Aktibong Pagsubaybay / Active Monitoring', 'Ipinapakita ang bilang ng mga pananim na sinusubaybayan. Pindutin ito para makita ang kanilang kalagayan. / Shows how many crops you are monitoring. Select it to see their status.'],
            ['.stats-grid .stat-card[aria-label^="Mga Babala sa Peste"]', 'Babala sa Peste / Pest Alerts', 'Ipinapakita ang mga pananim na may naiulat na peste o sakit. Suriin ang mga ito sa bukid. / Shows crops with a reported pest or disease. Check them in the field.'],
            ['.weather-gate', 'Panahon / Weather', 'Pindutin ang “Get Weather” para makita ang kasalukuyang panahon sa Orani, Bataan. / Select “Get Weather” to see current conditions in Orani, Bataan.'],
            ['#openNotifications', 'Mga Abiso / Notifications', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.'],
            ['#openTutorial', 'Gabay / Guide', 'Pindutin ito anumang oras para makita muli ang gabay na ito. / Select this anytime to see this guide again.'],
        ],
        'crops.html': [
            ['#openCropModal', 'Magdagdag ng Pananim / Add a Crop', 'Pindutin ang “Susunod” para buksan ang form. Gagamit tayo ng halimbawa: kamatis sa Hilera A. / Select “Next” to open the form. We will use an example: tomato in Row A.'],
            ['#cropLabel', 'Pangalan / Name', 'Maglagay ng pangalan na madaling tandaan, halimbawa “Kamatis - Hilera A”. / Enter an easy-to-remember name, such as “Tomato - Row A.”'],
            ['#cropSearchInput', 'Uri ng Pananim / Crop Type', 'I-type ang “Kamatis” at piliin ang tamang resulta sa listahan. Kung wala, subukan ang pangalan sa Ingles. / Type “Tomato” and pick the right result from the list. If it is missing, try the English name.'],
            ['#plantingDate', 'Petsa ng Pagtatanim / Planting Date', 'Piliin ang araw na itinanim mo ito. Hindi maaaring pumili ng petsa sa hinaharap. / Choose the day you planted it. Future dates are not allowed.'],
            ['#expectedHarvestPreview', 'Tinatayang Pag-aani / Estimated Harvest', 'Awtomatikong makukuwenta ito. Pagtataya lamang ito at maaaring magbago. / This is calculated automatically. It is only an estimate and may change.'],
            ['#cropNotes', 'Tala (Opsyonal) / Notes (Optional)', 'Magdagdag ng detalye tulad ng lokasyon o kondisyon ng lupa, o iwanang blangko. / Add details such as location or soil condition, or leave it blank.'],
            ['#cropForm button[type="submit"]', 'I-save / Save', 'Suriin ang mga detalye bago i-save. Gagawa ito ng totoong tala sa iyong account. / Check the details before saving. This creates a real record in your account.'],
        ],
        'monitoring.html': [
            ['#monitoringSearch', 'Maghanap / Search', 'I-type ang pangalan ng pananim para hanapin ito. / Type a crop name to find it.'],
            ['#cropTable', 'Talahanayan / Table', 'Bawat hanay ay isang pananim, kasama ang yugto, kondisyon, peste o sakit, at progreso. / Each row is a crop, with its stage, condition, pests or diseases, and progress.'],
            ['#cropTableBody', 'Buksan ang Detalye / Open Details', 'Piliin ang “Aksyon” sa isang hanay para makita ang detalye at magtala ng pagbabago. / Choose “Aksyon” on a row to see details and record an update.'],
            ['#harvestGaugeGrid', 'Progreso ng Ani / Harvest Progress', 'Ipinapakita kung gaano na kalapit ang pananim sa pag-aani. Pagtataya lamang ito. / Shows how close a crop is to harvest. This is an estimate.'],
            ['#readyHarvestGrid', 'Handa nang Anihin / Ready to Harvest', 'Nakalista rito ang mga pananim na handa nang anihin. Suriin muna ang mga ito sa bukid. / Crops that are ready to harvest are listed here. Check them in the field first.'],
            ['#openNotifications', 'Mga Abiso / Alerts', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.'],
        ],
        'monitoring-details.html': [
            ['#openHistoryBtn', 'Kasaysayan / History', 'Tingnan ang mga naunang tala para sa pananim na ito. / View earlier records for this crop.'],
            ['#monitoringForm button[type="submit"]:not([hidden])', 'I-save / Save', 'I-save ang pinakabagong kalagayan at mga tala. / Save the latest condition and notes.'],
        ],
        'crop-detail.html': [
            ['.detail-header .back-btn', 'Bumalik / Back', 'Bumalik sa listahan ng mga pananim. / Return to the crop list.'],
        ],
        'recommendation.html': [
            ['.current-weather', 'Kasalukuyang Panahon / Current Season', 'Ang rekomendasyon ay batay sa panahong ito. / The recommendations are based on this season.'],
            ['#cropGrid', 'Mga Iminumungkahing Pananim / Suggested Crops', 'Ihambing ang mga pananim. Hindi ito garantiya, kaya isaalang-alang din ang lupa, tubig, at lokasyon. / Compare the crops. This is not a guarantee, so also consider soil, water, and location.'],
            ['.view-toggle-btn[data-view="list"]', 'Ayos ng Resulta / Layout', 'Piliin ang list o gallery view. Hindi nito binabago ang rekomendasyon. / Choose list or gallery view. It does not change the recommendations.'],
            ['#openNotifications', 'Mga Abiso / Notifications', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.'],
        ],
        'recommendation-detail.html': [
            ['.detail-header .back-btn', 'Bumalik / Back', 'Bumalik sa mga inirerekomendang pananim. / Return to the recommended crops.'],
        ],
        'prediction.html': [
            ['.season-section', 'Mga Prediksyon / Predictions', 'Ipinapakita ang mga peste at sakit na posibleng lumitaw sa napiling panahon. Hindi nito pinatutunayang may problema ang pananim mo. / Shows pests and diseases that may appear in the selected season. It does not confirm a problem with your crop.'],
            ['.season-btn[data-season="all"]', 'Salain ayon sa Panahon / Filter by Season', 'Piliin ang Tag-Ulan, Tag-Init, o All Seasons. / Choose Wet, Dry, or All Seasons.'],
            ['#prediction-grid', 'Mga Peste at Sakit / Pests and Diseases', 'Basahin ang pangalan at buod sa bawat kard. / Read the name and summary on each card.'],
            ['.prediction-title', 'Detalye / Details', 'Piliin ang “Details” para makita ang mga sintomas at paraan ng pamamahala. / Select “Details” to see symptoms and management tips.'],
            ['#openNotifications', 'Mga Abiso / Alerts', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.'],
        ],
        'references.html': [
            ['.reference-button', 'Kategorya / Category', 'Piliin ang sanggunian sa pananim o sa peste. / Choose crop references or pest references.'],
            ['.reference-card', 'Mga Sanggunian / References', 'Basahin ang pamagat at pinagmulan. Tiyaking angkop ang payo sa iyong pananim at lugar. / Read the title and source. Make sure the advice fits your crop and area.'],
            ['#openNotifications', 'Mga Abiso / Notifications', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.'],
        ],
        'crop-references.html': [
            ['.reference-list', 'Sanggunian sa Pananim / Crop References', 'Bawat entry ay may pinagmulan at paksa tungkol sa pagtatanim at pangangalaga. / Each entry has a source and a topic on planting and crop care.'],
            ['.reference-list a[href^="http"]', 'Buksan ang Link / Open the Link', 'Magbubukas ito sa bagong tab. Tiyaking mapagkakatiwalaan ang pinagmulan. / It opens in a new tab. Make sure the source is trustworthy.'],
            ['.crop-header-title .back-button', 'Bumalik / Back', 'Bumalik sa mga kategorya. / Return to the categories.'],
            ['#openNotifications', 'Mga Abiso / Notifications', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.'],
        ],
        'pest-references.html': [
            ['.reference-list', 'Sanggunian sa Peste / Pest References', 'Bawat entry ay may pangalan ng peste o sakit at pinagmulan. Ihambing sa aktuwal na sintomas bago kumilos. / Each entry has a pest or disease name and a source. Compare with actual symptoms before acting.'],
            ['.reference-list a[href^="http"]', 'Buksan ang Link / Open the Link', 'Basahin ang orihinal na materyal. Tingnan ang petsa nito dahil maaaring magbago ang payo. / Read the original material. Check its date, since advice can change.'],
            ['.pest-header-title .back-button', 'Bumalik / Back', 'Bumalik sa mga kategorya. / Return to the categories.'],
            ['#openNotifications', 'Mga Abiso / Notifications', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.'],
        ],
        'contact.html': [
            ['#firstName', 'Unang Pangalan / First Name', 'Ilagay ang iyong unang pangalan. / Enter your first name.'],
            ['#lastName', 'Apelyido / Last Name', 'Ilagay ang iyong apelyido. / Enter your last name.'],
            ['#email', 'Email / Email', 'Gumamit ng email na nababasa mo para makatanggap ng sagot. / Use an email you can check so you can get a reply.'],
            ['#message', 'Mensahe / Message', 'Isulat ang iyong tanong o problema. Huwag isama ang password. / Write your question or issue. Do not include your password.'],
            ['#contactSubmitBtn', 'Ipadala / Send', 'Suriin ang mga detalye bago ipadala. Totoong mensahe ito. / Check your details before sending. This is a real message.'],
        ],
        'profile.html': [
            ['#updateInfoBtn', 'I-update / Update', 'Pindutin para ma-edit ang mga field. / Select to edit the fields.'],
            ['#firstName', 'Unang Pangalan / First Name', 'Ilagay ang tamang unang pangalan. / Enter your correct first name.'],
            ['#lastName', 'Apelyido / Last Name', 'Ilagay ang tamang apelyido. / Enter your correct last name.'],
            ['#email', 'Email / Email', 'Gumamit ng email na pag-aari mo at nababasa mo. / Use an email you own and can check.'],
            ['#contact', 'Numero ng Contact / Contact Number', 'Ilagay ang numerong maaaring tawagan. / Enter a number where you can be reached.'],
            ['#username', 'Username / Username', 'Ito ang gamit mo sa pag-log in. Huwag ilagay ang password. / This is what you use to log in. Do not enter your password.'],
            ['#profileSaveBtn', 'I-save / Save', 'Suriin ang mga pagbabago bago i-save. / Check your changes before saving.'],
        ],
        'pest-detail.html': [
            ['.detail-header .back-btn', 'Bumalik / Back', 'Bumalik sa listahan ng mga prediksyon. / Return to the prediction list.'],
        ],
    };

    let steps = [];
    let currentStep = 0;
    let firstRun = false;
    let language = 'tl';
    let highlighted = null;
    let originalFocus = null;
    let inertBackground = [];
    let dialog;
    let indicator;
    let helpButton;
    let languageToggle;

    function localize(text) {
        const [tagalog, english] = text.split(' / ');
        return language === 'en' ? (english || tagalog) : tagalog;
    }

    function updateLanguageControls() {
        const isTagalog = language === 'tl';
        languageToggle.textContent = isTagalog ? 'English' : 'Tagalog';
        languageToggle.setAttribute('aria-label', isTagalog ? 'Lumipat sa Ingles' : 'Switch to Tagalog');
        languageToggle.title = isTagalog ? 'Lumipat sa Ingles' : 'Switch to Tagalog';
        helpButton.setAttribute('aria-label', isTagalog ? 'Buksan ang gabay' : 'Open the guide');
        helpButton.setAttribute('title', isTagalog ? 'Gabay' : 'Guide');
    }

    function renderStepCopy() {
        const step = steps[currentStep];
        dialog.querySelector('#tutorialProgress').textContent = language === 'tl'
            ? `HAKBANG ${currentStep + 1} SA ${steps.length}`
            : `STEP ${currentStep + 1} OF ${steps.length}`;
        dialog.querySelector('#tutorialTitle').textContent = localize(step.title);
        dialog.querySelector('#tutorialText').textContent = localize(step.text);
        dialog.querySelector('.tutorial-skip').textContent = language === 'tl' ? 'Laktawan' : 'Skip';
        dialog.querySelector('.tutorial-back').textContent = language === 'tl' ? 'Bumalik' : 'Back';
        dialog.querySelector('.tutorial-next').textContent = currentStep === steps.length - 1
            ? (language === 'tl' ? 'Tapusin' : 'Finish')
            : (language === 'tl' ? 'Susunod' : 'Next');
        dialog.querySelector('.tutorial-back').disabled = currentStep === 0;
    }

    function isVisible(element) {
        if (!element || element.hidden) return false;
        const rect = element.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    }

    function firstVisible(selectors) {
        for (const selector of selectors || []) {
            const element = document.querySelector(selector);
            if (isVisible(element)) return element;
        }
        return null;
    }

    function addStep(target, title, text) {
        if (!isVisible(target) || steps.some(step => step.target === target)) return;
        steps.push({ target, title, text });
    }

    function buildSteps() {
        steps = [];
        const filename = window.location.pathname.split('/').pop();
        if (filename === 'crops.html') {
            (pageGuides[filename] || []).forEach(([selector, title, text], index) => {
                let target = document.querySelector(selector);
                if (index === 3 && target) target = target.closest('.form-group') || target;
                if (target) steps.push({ target, title, text, cropModalStep: index > 0 });
            });
        } else {
            (pageGuides[filename] || []).forEach(([selector, title, text]) => {
                const selectors = filename === 'userdashboard.html' && selector === '#calendarGrid'
                    ? [selector, '.calendar-weekdays']
                    : [selector];
                if (filename === 'prediction.html' && selector === '.prediction-title') {
                    const target = firstVisible(['#prediction-grid .details-btn', ...selectors]);
                    if (target) steps.push({ target, title, text, predictionDetailsStep: true });
                } else {
                    addStep(firstVisible(selectors), title, text);
                }
            });
        }
        if (filename !== 'userdashboard.html' && filename !== 'crops.html') {
            addStep(document.querySelector('.notification-trigger'), 'Mga Abiso / Notifications', 'Buksan ang kampana para makita ang mga paalala at babala. / Open the bell to see reminders and alerts.');
        }
    }

    function buildDialog() {
        helpButton = document.createElement('button');
        helpButton.type = 'button';
        helpButton.id = 'openTutorial';
        helpButton.className = 'tutorial-help-button';
        helpButton.innerHTML = '<i class="fa-solid fa-circle-question" aria-hidden="true"></i>';

        const iconContainer = document.querySelector('.dashboard-icons, .header-icons');
        if (iconContainer) iconContainer.appendChild(helpButton);
        else document.body.appendChild(helpButton);

        dialog = document.createElement('section');
        dialog.className = 'tutorial-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'tutorialTitle');
        dialog.hidden = true;
        dialog.innerHTML = `
            <div class="tutorial-dialog-top">
                <p class="tutorial-progress" id="tutorialProgress"></p>
                <button type="button" class="tutorial-language-toggle"></button>
            </div>
            <h2 id="tutorialTitle"></h2>
            <p id="tutorialText"></p>
            <div class="tutorial-actions">
                <button type="button" class="tutorial-skip"></button>
                <button type="button" class="tutorial-back"></button>
                <button type="button" class="tutorial-next"></button>
            </div>`;
        indicator = document.createElement('div');
        indicator.className = 'tutorial-indicator';
        indicator.setAttribute('aria-hidden', 'true');
        indicator.hidden = true;
        document.body.appendChild(indicator);
        document.body.appendChild(dialog);

        languageToggle = dialog.querySelector('.tutorial-language-toggle');
        updateLanguageControls();
        languageToggle.addEventListener('click', () => {
            language = language === 'tl' ? 'en' : 'tl';
            try {
                localStorage.setItem('agritrackTutorialLanguage', language);
            } catch (error) {
                console.warn('Could not save language preference:', error);
            }
            updateLanguageControls();
            renderStepCopy();
        });

        helpButton.addEventListener('click', () => startTour(false));
        dialog.querySelector('.tutorial-skip').addEventListener('click', finishTour);
        dialog.querySelector('.tutorial-back').addEventListener('click', () => showStep(currentStep - 1));
        dialog.querySelector('.tutorial-next').addEventListener('click', () => {
            if (currentStep === steps.length - 1) finishTour();
            else showStep(currentStep + 1);
        });
        document.addEventListener('keydown', event => {
            if (dialog.hidden) return;
            if (event.key === 'Escape') finishTour();
            if (event.key === 'ArrowRight' && currentStep < steps.length - 1) showStep(currentStep + 1);
            if (event.key === 'ArrowLeft' && currentStep > 0) showStep(currentStep - 1);
        });
        window.addEventListener('resize', positionIndicator);
        document.addEventListener('scroll', positionIndicator, true);
    }

    function positionIndicator() {
        if (dialog.hidden || !highlighted) return;
        const rect = highlighted.getBoundingClientRect();
        indicator.style.top = `${rect.top}px`;
        indicator.style.left = `${rect.left}px`;
        indicator.style.width = `${rect.width}px`;
        indicator.style.height = `${rect.height}px`;
    }

    function showStep(index) {
        if (highlighted) highlighted.classList.remove('tutorial-highlight');
        currentStep = index;
        const step = steps[currentStep];
        if (window.location.pathname.endsWith('/crops.html') && step.cropModalStep) {
            const cropModal = document.querySelector('#cropModal');
            if (!cropModal.classList.contains('show')) {
                document.querySelector('#openCropModal').click();
            }
        }
        if (step.predictionDetailsStep) {
            step.target = firstVisible(['#prediction-grid .details-btn']) || step.target;
        }
        highlighted = step.target;
        highlighted.classList.add('tutorial-highlight');
        indicator.hidden = false;
        highlighted.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        positionIndicator();
        requestAnimationFrame(positionIndicator);

        renderStepCopy();
    }

    function setBackgroundInert(inert) {
        if (inert) {
            inertBackground = Array.from(document.body.children)
                .filter(element => element !== dialog && element !== indicator)
                .map(element => ({ element, wasInert: element.inert }));
            inertBackground.forEach(({ element }) => { element.inert = true; });
            return;
        }

        inertBackground.forEach(({ element, wasInert }) => { element.inert = wasInert; });
        inertBackground = [];
    }

    function startTour() {
        buildSteps();
        if (!steps.length) return;
        originalFocus = document.activeElement;
        dialog.hidden = false;
        setBackgroundInert(true);
        document.body.classList.add('tutorial-open');
        showStep(0);
        dialog.querySelector('.tutorial-next').focus();
    }

    async function finishTour() {
        if (highlighted) highlighted.classList.remove('tutorial-highlight');
        highlighted = null;
        dialog.hidden = true;
        indicator.hidden = true;
        document.body.classList.remove('tutorial-open');
        setBackgroundInert(false);
        if (firstRun) {
            firstRun = false;
            try {
                await fetch(`${API_BASE}complete_tutorial.php`, {
                    method: 'POST',
                    credentials: 'same-origin',
                });
            } catch (error) {
                console.error('Could not save guide status:', error);
            }
        }
        if (originalFocus?.isConnected) originalFocus.focus();
    }

    async function initialize() {
        try {
            language = localStorage.getItem('agritrackTutorialLanguage') === 'en' ? 'en' : 'tl';
        } catch {
            language = 'tl';
        }
        buildDialog();
        try {
            const response = await fetch(`${API_BASE}get_current_user.php`, { credentials: 'same-origin' });
            if (!response.ok) return;
            const user = await response.json();
            firstRun = Number(user.sessionStatus) === 1;
            if (firstRun) startTour();
        } catch (error) {
            console.error('Could not check guide status:', error);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();