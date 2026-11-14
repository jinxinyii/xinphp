document.querySelectorAll('.toggle-password').forEach((button) => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.getAttribute('aria-controls'));

        if (!input) return;

        const showingPassword = input.type === 'text';
        input.type = showingPassword ? 'password' : 'text';
        button.textContent = showingPassword ? 'Show' : 'Hide';
        button.setAttribute('aria-label', showingPassword ? 'Show password' : 'Hide password');
    });
});

const transactionTypeInputs = document.querySelectorAll('[name="transaction_type"]');
const transactionCategorySelect = document.querySelector('[data-category-select]');

function filterTransactionCategories() {
    if (!transactionCategorySelect || transactionTypeInputs.length === 0) return;

    const selectedType = document.querySelector('[name="transaction_type"]:checked')?.value;
    let selectedOptionIsVisible = false;

    transactionCategorySelect.querySelectorAll('option[data-category-type]').forEach((option) => {
        const isVisible = option.dataset.categoryType === selectedType;
        option.hidden = !isVisible;
        option.disabled = !isVisible;
        selectedOptionIsVisible ||= isVisible && option.selected;
    });

    if (!selectedOptionIsVisible) transactionCategorySelect.value = '';
}

transactionTypeInputs.forEach((input) => input.addEventListener('change', filterTransactionCategories));
filterTransactionCategories();

document.querySelectorAll('.sidebar-toggle').forEach((button) => {
    const navigation = document.getElementById(button.getAttribute('aria-controls'));

    if (!navigation) return;

    const closeNavigation = () => {
        navigation.classList.add('hidden');
        navigation.classList.remove('flex', 'flex-col');
        button.setAttribute('aria-expanded', 'false');
        button.querySelector('.sidebar-toggle-arrow')?.classList.remove('rotate-180');
    };

    button.addEventListener('click', () => {
        const isExpanded = button.getAttribute('aria-expanded') === 'true';

        if (isExpanded) {
            closeNavigation();
            return;
        }

        navigation.classList.remove('hidden');
        navigation.classList.add('flex', 'flex-col');
        button.setAttribute('aria-expanded', 'true');
        button.querySelector('.sidebar-toggle-arrow')?.classList.add('rotate-180');
    });

    navigation.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeNavigation));
});

const birthdateInput = document.getElementById('birthdate');
const ageInput = document.getElementById('age');

function updateAgeFromBirthdate() {
    if (!birthdateInput || !ageInput) return;

    const value = birthdateInput.value;
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (!match) {
        ageInput.value = '';
        return;
    }

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const birthdate = new Date(year, month - 1, day);

    if (birthdate.getFullYear() !== year || birthdate.getMonth() !== month - 1 || birthdate.getDate() !== day) {
        ageInput.value = '';
        return;
    }

    const today = new Date();
    let age = today.getFullYear() - year;
    const birthdayHasPassed = today.getMonth() + 1 > month || (today.getMonth() + 1 === month && today.getDate() >= day);

    if (!birthdayHasPassed) age -= 1;

    ageInput.value = age >= 0 ? String(age) : '';
}

if (birthdateInput && ageInput) {
    birthdateInput.addEventListener('input', updateAgeFromBirthdate);
    birthdateInput.addEventListener('change', updateAgeFromBirthdate);
    updateAgeFromBirthdate();
}
<<<<<<< HEAD

const transactionDialog = document.getElementById('transaction-form');

if (transactionDialog instanceof HTMLDialogElement) {
    const openTransactionButtons = document.querySelectorAll('[data-open-transaction-dialog]');
    const closeTransactionButtons = transactionDialog.querySelectorAll('[data-close-transaction-dialog]');

    openTransactionButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (!transactionDialog.open) transactionDialog.showModal();
            window.setTimeout(() => transactionDialog.querySelector('#amount')?.focus(), 50);
        });
    });

    closeTransactionButtons.forEach((button) => {
        button.addEventListener('click', () => transactionDialog.close());
    });

    transactionDialog.addEventListener('click', (event) => {
        if (event.target === transactionDialog) transactionDialog.close();
    });

    if (transactionDialog.dataset.openOnLoad === 'true') {
        transactionDialog.showModal();
    }
}
=======
>>>>>>> c83d7030e3544987cabe1afc9ade26263b9cd767
