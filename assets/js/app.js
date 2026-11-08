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
