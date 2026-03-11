document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('adminForm');
    if (form) {
        form.setAttribute('enctype', 'multipart/form-data');
    }

    const currencySelect = document.getElementById('currency_id');
    const mollieMethodSelect = document.getElementById('mollie_payment_method');
    const mollieLogoInput = document.getElementById('payment_method_logo');
    const mollieTransactionDesc = document.querySelector('[name="params[transaction_description]"]');
    const logoPreview = document.getElementById('mollie_logo_preview');
    const logoImg = document.getElementById('mollie_logo_img');
    const componentsRow = document.getElementById('mollie_components_row');
    const captureModeRow = document.getElementById('capture_mode_row');
    const captureModeSelect = document.getElementById('capture_mode');

    let methodsData = {};
    let captureRestrictions = window.mollieCaptureRestrictions || {
        manualOnly: [],
        automaticOnly: []
    };

    function updateComponentsVisibility(methodId) {
        if (!componentsRow) return;

        if (methodId === 'creditcard') {
            componentsRow.style.display = '';
        } else {
            componentsRow.style.display = 'none';
        }
    }

    function updateCaptureFields(methodId) {
        if (!captureModeRow || !captureModeSelect) return;

        if (!methodId) {
            captureModeRow.style.display = 'none';
            return;
        }

        if (captureRestrictions.automaticOnly.includes(methodId)) {
            captureModeSelect.value = 'automatic';
            captureModeRow.style.display = 'none';
        } else {
            captureModeRow.style.display = '';
            captureModeSelect.disabled = false;
        }
    }

    function updateLogoPreview(methodId) {
        if (!methodId || !methodsData[methodId]) {
            logoPreview.style.display = 'none';
            return;
        }

        logoImg.src = methodsData[methodId].logo;
        logoPreview.style.display = 'flex';
    }

    function fetchPaymentMethods(currencyId, currentSelection, updateDropdown) {
        const ajaxUrl = window.mollieAjaxUrl || '';
        if (!ajaxUrl) {
            console.error('Mollie AJAX URL not defined');
            return;
        }

        const originalHTML = mollieMethodSelect.innerHTML;

        if (updateDropdown) {
            mollieMethodSelect.disabled = true;
        }

        const url = new URL(ajaxUrl, window.location.origin);
        url.searchParams.append('currency_id', currencyId);
        url.searchParams.append('current_selection', currentSelection);

        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(jsonResponse => jsonResponse.data || jsonResponse)
            .then(data => {
                if (data.error) {
                    console.error('Error fetching payment methods:', data.error);
                    if (updateDropdown) {
                        mollieMethodSelect.disabled = false;
                    }
                    return;
                }

                if (!data.methods || data.methods.length === 0) {
                    if (updateDropdown) {
                        mollieMethodSelect.closest('tr').style.display = 'none';
                        mollieLogoInput.closest('tr').style.display = 'none';
                        mollieTransactionDesc.closest('tr').style.display = 'none';
                    }
                    return;
                }

                if (updateDropdown) {
                    mollieMethodSelect.closest('tr').style.display = '';
                    mollieLogoInput.closest('tr').style.display = '';
                    mollieTransactionDesc.closest('tr').style.display = '';
                }

                methodsData = {};
                data.methods.forEach(method => {
                    methodsData[method.id] = method;
                });

                if (data.captureRestrictions) {
                    captureRestrictions = data.captureRestrictions;
                }

                if (updateDropdown) {
                    const $select = jQuery(mollieMethodSelect);
                    if ($select.data('chosen')) {
                        $select.chosen('destroy');
                    }

                    mollieMethodSelect.innerHTML = '';
                    data.methods.forEach(method => {
                        const option = document.createElement('option');
                        option.value = method.id;
                        option.textContent = method.name;
                        if (method.id === data.selectedMethod) {
                            option.selected = true;
                        }
                        mollieMethodSelect.appendChild(option);
                    });

                    mollieMethodSelect.disabled = false;

                    if (typeof jQuery.fn.chosen !== 'undefined') {
                        $select.chosen();
                    }

                    updateLogoPreview(data.selectedMethod);
                    updateComponentsVisibility(data.selectedMethod);
                    updateCaptureFields(data.selectedMethod);
                } else {
                    const currentMethod = mollieMethodSelect.value;
                    if (currentMethod) {
                        updateLogoPreview(currentMethod);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching payment methods:', error);
                if (updateDropdown) {
                    mollieMethodSelect.innerHTML = originalHTML;
                    mollieMethodSelect.disabled = false;
                }
            });
    }

    jQuery(mollieMethodSelect).on('change', function() {
        updateLogoPreview(this.value);
        updateComponentsVisibility(this.value);
        updateCaptureFields(this.value);
    });

    if (currencySelect && mollieMethodSelect) {
        const isVisible = mollieMethodSelect.closest('tr').style.display !== 'none';
        if (isVisible) {
            const initialCurrencyId = currencySelect.value;
            const initialSelection = mollieMethodSelect.value;
            if (initialCurrencyId) {
                fetchPaymentMethods(initialCurrencyId, initialSelection, false);
            }
            updateCaptureFields(initialSelection);
        }
    } else if (mollieMethodSelect) {
        updateCaptureFields(mollieMethodSelect.value);
    }

        jQuery(currencySelect).on('change', function() {
            const currencyId = this.value;
            const currentSelection = mollieMethodSelect.value;

            if (!currencyId) return;

            console.log('Currency changed to:', currencyId);
            fetchPaymentMethods(currencyId, currentSelection, true);
        });
})
