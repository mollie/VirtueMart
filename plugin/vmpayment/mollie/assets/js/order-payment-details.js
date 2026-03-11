document.addEventListener('DOMContentLoaded', function() {
    const captureBtn = document.getElementById('mollie-capture-btn');
    const captureAmountRow = document.getElementById('mollie-capture-amount-row');
    const captureConfirmBtn = document.getElementById('mollie-capture-confirm-btn');
    const captureAmountInput = document.getElementById('mollie-capture-amount');
    const refundBtn = document.getElementById('mollie-refund-btn');

    function hasMoreThanTwoDecimals(value) {
        const parts = value.toString().split('.');
        return parts.length > 1 && parts[1].length > 2;
    }

    if (captureBtn) {
        captureBtn.addEventListener('click', function(e) {
            e.preventDefault();

            captureBtn.classList.add('disabled');
            captureBtn.style.opacity = '0.5';
            captureBtn.style.pointerEvents = 'none';

            if (captureAmountRow) {
                captureAmountRow.style.display = 'block';

                if (captureAmountInput) {
                    captureAmountInput.focus();
                    captureAmountInput.select();
                }
            }

            return false;
        });
    }

    if (captureConfirmBtn) {
        captureConfirmBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const amount = captureAmountInput ? captureAmountInput.value : null;
            const maxAmount = captureAmountInput ? parseFloat(captureAmountInput.dataset.maxAmount) : 0;
            const currency = document.getElementById('mollie-capture-currency')?.textContent || '';

            if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
                showMessage('Please enter a valid amount', 'error');
                return false;
            }

            if (hasMoreThanTwoDecimals(amount)) {
                showMessage('Amount can have maximum 2 decimal places', 'error');
                return false;
            }

            if (parseFloat(amount) > maxAmount) {
                showMessage('Capture amount cannot exceed ' + maxAmount + ' ' + currency, 'error');
                return false;
            }

            captureConfirmBtn.classList.add('disabled');
            captureConfirmBtn.style.opacity = '0.5';
            captureConfirmBtn.style.pointerEvents = 'none';
            const originalText = captureConfirmBtn.textContent;
            captureConfirmBtn.textContent = captureConfirmBtn.dataset.processingText || 'Processing...';

            const orderId = document.querySelector('[data-order-id]')?.dataset.orderId;

            if (!orderId) {
                showMessage('Order ID not found', 'error');
                resetButton(captureConfirmBtn, originalText);
                return false;
            }

            const ajaxUrl = window.location.origin + '/administrator/index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=mollie&task=capturePayment';

            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('amount', amount);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.data && data.data.error) {
                    showMessage(data.data.error, 'error');
                    resetButton(captureConfirmBtn, originalText);
                } else if (data.success === false || data.error) {
                    showMessage(data.error || data.message || 'Capture failed', 'error');
                    resetButton(captureConfirmBtn, originalText);
                } else if (data.success && data.data) {
                    showMessage(data.data.message || 'Capture initiated.', 'success');
                    setTimeout(() => window.location.reload(), 2500);
                } else {
                    showMessage('Unexpected response from server', 'error');
                    resetButton(captureConfirmBtn, originalText);
                }
            })
            .catch(error => {
                showMessage('An error occurred: ' + error.message, 'error');
                resetButton(captureConfirmBtn, originalText);
            });

            return false;
        });
    }

    function showMessage(message, type) {
        const existingMsg = document.querySelector('.mollie-message');
        if (existingMsg) {
            existingMsg.remove();
        }

        const msgDiv = document.createElement('div');
        msgDiv.className = 'mollie-message mollie-message-' + type;
        msgDiv.textContent = message;

        const detailsSection = document.querySelector('.mollie-payment-details');
        if (detailsSection && detailsSection.firstChild) {
            detailsSection.insertBefore(msgDiv, detailsSection.firstChild);
        }
    }

    function resetButton(button, originalText) {
        button.classList.remove('disabled');
        button.style.opacity = '1';
        button.style.pointerEvents = 'auto';
        button.textContent = originalText;
    }

    const refundAmountRow = document.getElementById('mollie-refund-amount-row');
    const refundConfirmBtn = document.getElementById('mollie-refund-confirm-btn');
    const refundAmountInput = document.getElementById('mollie-refund-amount');

    if (refundBtn) {
        refundBtn.addEventListener('click', function(e) {
            e.preventDefault();

            refundBtn.classList.add('disabled');
            refundBtn.style.opacity = '0.5';
            refundBtn.style.pointerEvents = 'none';

            if (refundAmountRow) {
                refundAmountRow.style.display = 'block';

                if (refundAmountInput) {
                    refundAmountInput.focus();
                    refundAmountInput.select();
                }
            }

            return false;
        });
    }

    if (refundConfirmBtn) {
        refundConfirmBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const amount = refundAmountInput ? refundAmountInput.value : null;
            const maxAmount = refundAmountInput ? parseFloat(refundAmountInput.dataset.maxAmount) : 0;
            const currency = document.getElementById('mollie-refund-currency')?.textContent || '';

            if (!amount || isNaN(amount) || parseFloat(amount) <= 0) {
                showMessage('Please enter a valid amount', 'error');
                return false;
            }

            if (hasMoreThanTwoDecimals(amount)) {
                showMessage('Amount can have maximum 2 decimal places', 'error');
                return false;
            }

            if (parseFloat(amount) > maxAmount) {
                showMessage('Refund amount cannot exceed ' + maxAmount + ' ' + currency, 'error');
                return false;
            }

            refundConfirmBtn.classList.add('disabled');
            refundConfirmBtn.style.opacity = '0.5';
            refundConfirmBtn.style.pointerEvents = 'none';
            const originalText = refundConfirmBtn.textContent;
            refundConfirmBtn.textContent = refundConfirmBtn.dataset.processingText || 'Processing...';

            const orderId = document.querySelector('[data-order-id]')?.dataset.orderId;

            if (!orderId) {
                showMessage('Order ID not found', 'error');
                resetButton(refundConfirmBtn, originalText);
                return false;
            }

            const ajaxUrl = window.location.origin + '/administrator/index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=mollie&task=refundPayment';

            const formData = new FormData();
            formData.append('order_id', orderId);
            formData.append('amount', amount);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.data && data.data.error) {
                    showMessage(data.data.error, 'error');
                    resetButton(refundConfirmBtn, originalText);
                } else if (data.success === false || data.error) {
                    showMessage(data.error || data.message || 'Refund failed', 'error');
                    resetButton(refundConfirmBtn, originalText);
                } else if (data.success && data.data) {
                    showMessage(data.data.message || 'Refund initiated.', 'success');
                    resetButton(refundConfirmBtn, originalText);
                } else {
                    showMessage('Unexpected response from server', 'error');
                    resetButton(refundConfirmBtn, originalText);
                }
            })
            .catch(error => {
                showMessage('An error occurred: ' + error.message, 'error');
                resetButton(refundConfirmBtn, originalText);
            });

            return false;
        });
    }
});
