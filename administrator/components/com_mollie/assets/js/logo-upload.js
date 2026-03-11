document.addEventListener('DOMContentLoaded', function() {
    const logoInput = document.getElementById('payment_method_logo');
    if (!logoInput) return;

    logoInput.addEventListener('change', function() {
        const file = this.files[0];
        const filenameDisplay = document.getElementById('logo_filename');
        const preview = document.getElementById('mollie_logo_preview');
        const img = document.getElementById('mollie_logo_img');

        if (filenameDisplay) {
            filenameDisplay.textContent = file?.name || '';
        }

        if (file && preview && img) {
            const reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
                preview.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }
    });
});
