document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('registerForm');
    const messageBox = document.getElementById('message');

    if (!form || !messageBox) {
        return;
    }

    const barangayInput = form.elements.barangay;
    const barangayOptions = document.getElementById('barangayOptions');
    const barangays = [
        'Apollo', 'Bagong Paraiso', 'Balut', 'Bayan', 'Calero', 'Centro I', 'Centro II',
        'Doña', 'Kaparangan', 'Maria Fe', 'Masantol', 'Mulawin', 'Paking-Carbonero',
        'Palihan', 'Parang Parang', 'Puksuan', 'Silahis', 'Tagumpay', 'Talimundoc',
        'Tapulao', 'Tenejero', 'Tugatog', 'Wawa'
    ];
    let selectedBarangay = '';
    let activeOption = -1;

    const normalizeBarangay = (value) => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

    const closeBarangayOptions = () => {
        barangayOptions.hidden = true;
        barangayInput.setAttribute('aria-expanded', 'false');
        barangayInput.removeAttribute('aria-activedescendant');
        activeOption = -1;
    };

    const chooseBarangay = (value) => {
        barangayInput.value = value;
        selectedBarangay = value;
        closeBarangayOptions();
    };

    const renderBarangayOptions = () => {
        const query = normalizeBarangay(barangayInput.value.trim());
        barangayOptions.replaceChildren();
        activeOption = -1;

        const matches = barangays.filter((name) => normalizeBarangay(name).includes(query));
        if (matches.length === 0) {
            const emptyMessage = document.createElement('div');
            emptyMessage.className = 'barangay-empty';
            emptyMessage.textContent = 'No matching barangay.';
            barangayOptions.append(emptyMessage);
        } else {
            matches.forEach((name, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.id = `barangay-option-${index}`;
                option.className = 'barangay-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.tabIndex = -1;
                option.textContent = name;
                option.addEventListener('click', () => chooseBarangay(name));
                barangayOptions.append(option);
            });
        }

        barangayOptions.hidden = false;
        barangayInput.setAttribute('aria-expanded', 'true');
    };

    const setActiveOption = (index) => {
        const options = barangayOptions.querySelectorAll('[role="option"]');
        if (options.length === 0) {
            return;
        }

        activeOption = (index + options.length) % options.length;
        options.forEach((option, optionIndex) => {
            option.setAttribute('aria-selected', String(optionIndex === activeOption));
        });
        barangayInput.setAttribute('aria-activedescendant', options[activeOption].id);
        options[activeOption].scrollIntoView({ block: 'nearest' });
    };

    barangayInput.addEventListener('input', () => {
        selectedBarangay = '';
        renderBarangayOptions();
    });

    barangayInput.addEventListener('focus', renderBarangayOptions);

    barangayInput.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' && !barangayOptions.hidden) {
            event.preventDefault();
            setActiveOption(activeOption + 1);
        } else if (event.key === 'ArrowUp' && !barangayOptions.hidden) {
            event.preventDefault();
            setActiveOption(activeOption < 0 ? 0 : activeOption - 1);
        } else if (event.key === 'Enter' && activeOption >= 0 && !barangayOptions.hidden) {
            event.preventDefault();
            const active = barangayOptions.querySelectorAll('[role="option"]')[activeOption];
            if (active) {
                chooseBarangay(active.textContent);
            }
        } else if (event.key === 'Escape') {
            closeBarangayOptions();
        }
    });

    barangayInput.addEventListener('blur', () => {
        window.setTimeout(() => {
            if (!barangayOptions.contains(document.activeElement)) {
                closeBarangayOptions();
            }
        }, 150);
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.barangay-picker')) {
            closeBarangayOptions();
        }
    });

    form.addEventListener('paste', (event) => event.preventDefault(), true);
    form.addEventListener('drop', (event) => event.preventDefault(), true);
    form.addEventListener('beforeinput', (event) => {
        if (event.inputType === 'insertFromPaste' || event.inputType === 'insertFromDrop') {
            event.preventDefault();
        }
    }, true);

    document.querySelectorAll('.toggle-password').forEach((button) => {
        button.addEventListener('click', () => {
            const targetName = button.getAttribute('data-target');
            const input = form.elements[targetName];

            if (!input) {
                return;
            }

            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = button.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            }
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());
        const contactNumber = (payload.contactNumber || '').replace(/\D/g, '');

        const nameFields = ['fname', 'lname'];
        const namePattern = /^[A-Za-z\s]+$/;
        
        if (!payload.fname || !payload.lname || !payload.userName || !payload.email || !payload.barangay || !contactNumber || !payload.password || !payload.confirmPassword) {
            messageBox.textContent = 'Please fill in all required fields.';
            messageBox.style.color = '#b91c1c';
            return;
        }

        if (!/^9\d{9}$/.test(contactNumber)) {
            messageBox.textContent = 'Enter a valid Philippine mobile number after +63.';
            messageBox.style.color = '#b91c1c';
            form.elements.contactNumber.focus();
            return;
        }

        payload.contact = `${payload.contactCountry || '+63'}${contactNumber}`;
        delete payload.contactNumber;
        delete payload.contactCountry;

        if (!form.elements.email.checkValidity()) {
            messageBox.textContent = 'Please enter a valid email address.';
            messageBox.style.color = '#b91c1c';
            form.elements.email.focus();
            return;
        }

        if (!selectedBarangay || payload.barangay !== selectedBarangay) {
            messageBox.textContent = 'Please select a barangay from the list.';
            messageBox.style.color = '#b91c1c';
            barangayInput.focus();
            return;
        }
        for (const fieldName of nameFields) {
            const value = (payload[fieldName] || '').trim();
            if (value && !namePattern.test(value)) {
                messageBox.textContent = 'First name and last name can only contain letters and spaces.';
                messageBox.style.color = '#b91c1c';
                form.elements[fieldName].focus();
                return;
            }
        }

        if (payload.password.length < 8) {
            messageBox.textContent = 'Password must be at least 8 characters long.';
            messageBox.style.color = '#b91c1c';
            form.elements.password.focus();
            return;
        }

        if (payload.password !== payload.confirmPassword) {
            messageBox.textContent = 'Passwords do not match.';
            messageBox.style.color = '#b91c1c';
            return;
        }

        messageBox.textContent = 'Creating account...';
        messageBox.style.color = '#14532d';

        await new Promise(resolve => setTimeout(resolve, 500));  // Delay
            try {
                const response = await fetch('../php/registration.php', {
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
                    form.reset();
                    selectedBarangay = '';
                    closeBarangayOptions();
                } else {
                    messageBox.textContent = result.message || 'Registration failed.';
                    messageBox.style.color = '#b91c1c';
                }
            } catch (error) {
                messageBox.textContent = 'Unable to reach the server.';
                messageBox.style.color = '#b91c1c';
            }
    });

});