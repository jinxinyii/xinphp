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
