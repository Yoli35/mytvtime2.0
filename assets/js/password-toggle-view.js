document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('input[type="password"]');

    inputs.forEach(input => {
        const toggle = input.closest('label').querySelector(".password-toggle-view");
        toggle.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
        });
    });
});
