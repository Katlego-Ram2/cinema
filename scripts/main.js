document.addEventListener('DOMContentLoaded', function() {
    // Example JavaScript code for client-side validations
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        const password = document.querySelector('input[name="password"]').value;
        const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
        
        if (password !== confirmPassword) {
            alert('Passwords do not match!');
            event.preventDefault();
        }
    });
});
