(function() {
    'use strict';

    const SELECTORS = {
        cartView: '.virtuemart-cart-view',
        paymentMethodInput: 'input[name="virtuemart_paymentmethod_id"]',
        mollieMethod: '[data-mollie-method]',
        creditCardMethod: '[data-mollie-method="creditcard"]',
        methodContainer: 'tr, .payment-method-row, .vm-payment-plugin-single',
        submitButton: '#checkoutFormSubmit',
        checkoutForm: 'form[name="userForm"]',
        mollieWrapper: '.mollie-component-wrapper',
        portalWrapper: '.mollie-component-wrapper[data-mollie-portal]',
        ghostAnchor: '.mollie-ghost-anchor'
    };

    const DELAYS = {
        filteringCooldown: 100,
        componentMount: 100
    };

    let cachedMethods = null;
    let isFiltering = false;

    let portalInitialized = false;
    let pendingSyncRAF = null;

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        cachedMethods = null;
        initCountryBasedFiltering();
        initCreditCardComponents();
    }


    function initCountryBasedFiltering() {
        const countrySelect = document.getElementById('virtuemart_country_id_field');

        if (countrySelect) {
            setupCountryChangeListener(countrySelect);

            const selectedCountryId = countrySelect.value;
            if (selectedCountryId) {
                getCountryIsoCode(selectedCountryId)
                    .then(countryCode => {
                        if (countryCode) updatePaymentMethods(countryCode);
                    });
            }
        } else {
            const countryId = window.mollieCountryId || 0;
            if (!countryId) return;

            getCountryIsoCode(countryId)
                .then(countryCode => {
                    if (countryCode) updatePaymentMethods(countryCode);
                });
        }

        setupPaymentMethodObserver();
    }

    function setupCountryChangeListener(countrySelect) {
        const handler = function() {
            const countryId = this.value;
            if (countryId) {
                getCountryIsoCode(countryId)
                    .then(countryCode => {
                        if (countryCode) updatePaymentMethods(countryCode);
                    });
            }
        };

        if (window.jQuery) {
            jQuery(countrySelect).on('change', handler);
        } else {
            countrySelect.addEventListener('change', handler);
        }
    }

    function setupPaymentMethodObserver() {
        const target = document.querySelector(SELECTORS.cartView) || document.body;
        const observer = new MutationObserver(() => {
            if (cachedMethods && !isFiltering && shouldRefilter()) {
                filterPaymentMethods(cachedMethods);
            }
        });
        observer.observe(target, { childList: true, subtree: true });
    }

    function shouldRefilter() {
        return Array.from(document.querySelectorAll(SELECTORS.mollieMethod)).some(el => {
            const container = el.closest(SELECTORS.methodContainer);
            return container && !container.classList.contains('d-none');
        });
    }

    function updatePaymentMethods(countryCode) {
        const { currencyId, orderTotal } = getCartData();

        const params = new URLSearchParams({
            option: 'com_virtuemart',
            view: 'plugin',
            vmtype: 'vmpayment',
            name: 'mollie',
            task: 'getMethodsByCountry',
            country_code: countryCode,
            [Joomla.getOptions('csrf.token')]: '1'
        });

        if (currencyId) params.set('currency_id', currencyId);
        if (orderTotal) params.set('order_total', orderTotal);

        fetch(`/administrator/index.php?${params}`)
            .then(response => response.json())
            .then(jsonResponse => jsonResponse.data || jsonResponse)
            .then(data => {
                if (data.methods) {
                    cachedMethods = data.methods;
                    filterPaymentMethods(data.methods);
                }
            });
    }

    function filterPaymentMethods(availableMethods) {
        isFiltering = true;

        document.querySelectorAll(SELECTORS.paymentMethodInput).forEach(input => {
            const container = input.closest(SELECTORS.methodContainer);
            if (!container) return;

            const mollieMethod = container.querySelector(SELECTORS.mollieMethod);
            if (!mollieMethod) return;

            const methodId = mollieMethod.getAttribute('data-mollie-method');
            const isAvailable = availableMethods.includes(methodId);

            togglePaymentMethod(container, input, isAvailable);
        });

        setTimeout(() => { isFiltering = false; }, DELAYS.filteringCooldown);
    }

    function togglePaymentMethod(container, input, isAvailable) {
        if (isAvailable) {
            container.classList.remove('d-none');
            container.classList.add('d-flex');
            input.disabled = false;
        } else {
            container.classList.add('d-none');
            container.classList.remove('d-flex');
            input.disabled = true;

            if (input.checked) {
                input.checked = false;
                selectFirstVisibleMethod();
            }
        }
    }

    function selectFirstVisibleMethod() {
        const firstVisible = document.querySelector(`${SELECTORS.paymentMethodInput}:not([disabled])`);
        if (firstVisible) {
            firstVisible.checked = true;
            firstVisible.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function getCountryIsoCode(countryId) {
        const params = new URLSearchParams({
            option: 'com_virtuemart',
            view: 'plugin',
            vmtype: 'vmpayment',
            name: 'mollie',
            task: 'getCountryCode',
            country_id: countryId,
            [Joomla.getOptions('csrf.token')]: '1'
        });

        return fetch(`/administrator/index.php?${params}`)
            .then(res => res.json())
            .then(jsonResponse => jsonResponse.data || jsonResponse)
            .then(data => data.countryCode);
    }

    function getCartData() {
        return {
            currencyId: window.mollieCartData?.currencyId || null,
            orderTotal: window.mollieCartData?.orderTotal || null
        };
    }

    function initCreditCardComponents() {
        if (isCreditCardSelected() && isComponentsEnabled()) {
            setTimeout(mountIfActive, DELAYS.componentMount);
        }

        setupComponentsChangeListener();
        setupAnchorObserver();
        preventCreditCardReclick();
    }

    function setupComponentsChangeListener() {
        document.addEventListener('change', event => {
            if (event.target?.name !== 'virtuemart_paymentmethod_id') return;

            setTimeout(() => {
                if (isCreditCardSelected() && isComponentsEnabled()) {
                    mountIfActive();
                    scheduleSync();
                } else {
                    hidePortal();
                    MollieComponents.creditCard.unmount();
                }
            }, DELAYS.componentMount);
        });
    }

    function setupAnchorObserver() {
        const target = document.querySelector(SELECTORS.cartView) || document.body;

        const observer = new MutationObserver(() => {
            if (!portalInitialized) return;

            document.querySelectorAll(SELECTORS.mollieWrapper).forEach(el => {
                if (!el.hasAttribute('data-mollie-portal')) el.remove();
            });

            const anchor = document.querySelector(SELECTORS.ghostAnchor);
            if (!anchor && isCreditCardSelected()) {
                createAnchor();
            }

            scheduleSync();
        });

        observer.observe(target, { childList: true, subtree: true });
    }

    function mountIfActive() {
        const wrapper = document.querySelector(
            portalInitialized ? SELECTORS.portalWrapper : SELECTORS.mollieWrapper
        );
        if (!wrapper) return;

        ensurePortal(wrapper);

        MollieComponents.creditCard.mount(wrapper);
    }

    function ensurePortal(wrapper) {
        if (portalInitialized) return;

        document.body.appendChild(wrapper);
        wrapper.setAttribute('data-mollie-portal', '1');
        portalInitialized = true;

        Object.assign(wrapper.style, {
            position: 'fixed',
            zIndex: '9999',
            margin: '0',
            visibility: 'hidden',
            pointerEvents: 'none'
        });

        createAnchor();
        syncPosition();

        window.addEventListener('resize', scheduleSync);
        window.addEventListener('scroll', scheduleSync, { passive: true });
    }

    function createAnchor() {
        document.querySelectorAll(SELECTORS.ghostAnchor).forEach(el => el.remove());

        const ccSpan = document.querySelector(SELECTORS.creditCardMethod);
        const ccRow = ccSpan?.closest(SELECTORS.methodContainer);
        if (!ccRow) return;

        const anchor = document.createElement('div');
        anchor.className = 'mollie-ghost-anchor';

        Object.assign(anchor.style, {
            display: 'block',
            width: '100%',
            height: '320px',
            clear: 'both',
            visibility: 'hidden'
        });

        const target = ccSpan.parentElement;
        if (target) {
            target.appendChild(anchor);
        } else {
            ccRow.appendChild(anchor);
        }
    }

    function syncPosition() {
        const wrapper = document.querySelector(SELECTORS.portalWrapper);
        const anchor  = document.querySelector(SELECTORS.ghostAnchor);

        if (!wrapper || !anchor || !isCreditCardSelected()) {
            hidePortal();
            return;
        }

        const ccRow = document.querySelector(SELECTORS.creditCardMethod)?.closest(SELECTORS.methodContainer);
        const rowRect = ccRow.getBoundingClientRect();
        const anchorRect = anchor.getBoundingClientRect();

        const topOffset = 10;
        const sidePadding = 15;

        Object.assign(wrapper.style, {
            top:   (anchorRect.top + topOffset) + 'px',
            left:  (rowRect.left + sidePadding) + 'px',
            width: (rowRect.width - (sidePadding * 2)) + 'px'
        });

        showPortal();
    }

    function scheduleSync() {
        if (pendingSyncRAF) cancelAnimationFrame(pendingSyncRAF);
        pendingSyncRAF = requestAnimationFrame(() => {
            pendingSyncRAF = null;
            syncPosition();
        });
    }

    function showPortal() {
        const wrapper = document.querySelector(SELECTORS.portalWrapper);
        if (wrapper) {
            wrapper.style.visibility = 'visible';
            wrapper.style.pointerEvents = 'auto';
            wrapper.classList.remove('hidden');
        }
    }

    function hidePortal() {
        const wrapper = document.querySelector(SELECTORS.portalWrapper);
        if (wrapper) {
            wrapper.style.visibility = 'hidden';
            wrapper.style.pointerEvents = 'none';
        }
    }

    function preventCreditCardReclick() {
        document.addEventListener('click', event => {
            const radio = event.target.closest(SELECTORS.paymentMethodInput);
            if (!radio) return;

            const container = radio.closest(SELECTORS.methodContainer);
            if (!container) return;

            const isCreditCard = container.querySelector(SELECTORS.creditCardMethod);
            if (!isCreditCard) return;

            const wrapper = document.querySelector(SELECTORS.mollieWrapper);
            const hasIframes = wrapper?.querySelectorAll('iframe').length > 0;

            if (hasIframes && isCreditCardSelected()) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);
    }

    function isCreditCardSelected() {
        const methodSpan = document.querySelector(SELECTORS.creditCardMethod);
        if (!methodSpan) return false;

        const container = methodSpan.closest(SELECTORS.methodContainer);
        const input = container?.querySelector(SELECTORS.paymentMethodInput);
        return input?.checked || false;
    }

    function isComponentsEnabled() {
        const methodSpan = document.querySelector(SELECTORS.creditCardMethod);
        return methodSpan?.getAttribute('data-components-enabled') === '1';
    }

})();

window.MollieComponents = window.MollieComponents || {};

(function() {
    'use strict';

    const COMPONENT_IDS = {
        cardHolder:        '#mollie-card-holder',
        cardNumber:        '#mollie-card-number',
        expiryDate:        '#mollie-expiry-date',
        verificationCode:  '#mollie-verification-code'
    };

    const ERROR_IDS = {
        cardHolder:        '#mollie-card-holder-error',
        cardNumber:        '#mollie-card-number-error',
        expiryDate:        '#mollie-expiry-date-error',
        verificationCode:  '#mollie-verification-code-error'
    };

    const COMPONENT_STYLES = {
        base: {
            color: '#333',
            fontSize: '15px',
            lineHeight: '1.6',
            fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
            '::placeholder': { color: '#aaa' }
        }
    };

    function CreditCardService() {
        let mollieInstance  = null;
        let isMountedFlag   = false;
        let componentRefs   = {};

        this.mount   = mount;
        this.unmount = unmount;
        this.reset   = reset;

        function mount(cardWrapper) {
            if (isMountedFlag) return;

            const config = window.mollieConfig || {};
            if (!config.profileId) {
                console.error('Mollie: Profile ID not configured');
                return;
            }

            mollieInstance = Mollie(config.profileId, {
                locale:   config.locale   || 'en_US',
                testmode: config.testMode || false
            });

            mountComponents(cardWrapper, mollieInstance);
            attachSubmitListener(mollieInstance);
            isMountedFlag = true;
        }

        function mountComponents(cardWrapper, mollie) {
            const elements = {
                cardHolder:       document.querySelector(COMPONENT_IDS.cardHolder),
                cardNumber:       document.querySelector(COMPONENT_IDS.cardNumber),
                expiryDate:       document.querySelector(COMPONENT_IDS.expiryDate),
                verificationCode: document.querySelector(COMPONENT_IDS.verificationCode)
            };

            if (!Object.values(elements).every(Boolean)) {
                console.error('Mollie: Not all component elements found');
                return;
            }

            createAndMountComponent(mollie, 'cardHolder',       COMPONENT_IDS.cardHolder,       ERROR_IDS.cardHolder);
            createAndMountComponent(mollie, 'cardNumber',       COMPONENT_IDS.cardNumber,       ERROR_IDS.cardNumber);
            createAndMountComponent(mollie, 'expiryDate',       COMPONENT_IDS.expiryDate,       ERROR_IDS.expiryDate);
            createAndMountComponent(mollie, 'verificationCode', COMPONENT_IDS.verificationCode, ERROR_IDS.verificationCode);
        }

        function createAndMountComponent(mollie, type, elementId, errorId) {
            const component = mollie.createComponent(type, { styles: COMPONENT_STYLES });
            component.mount(elementId);
            componentRefs[type] = component;
            attachValidationListeners(component, elementId, errorId);
        }

        function attachValidationListeners(component, elementId, errorId) {
            const element      = document.querySelector(elementId);
            const errorElement = document.querySelector(errorId);
            if (!element || !errorElement) return;

            component.addEventListener('change', event => {
                element.parentNode.classList.toggle('is-dirty', event.dirty);
                errorElement.textContent = (event.error && event.touched) ? event.error : '';
            });

            component.addEventListener('focus', () => element.parentNode.classList.add('has-focus'));
            component.addEventListener('blur',  () => element.parentNode.classList.remove('has-focus'));
        }

        function attachSubmitListener(mollie) {
            document.addEventListener('click', async event => {
                const btn = event.target.closest('#checkoutFormSubmit');
                if (!btn) return;
                if (!shouldInterceptSubmit()) return;
                if (document.querySelector('input[name="mollieCardToken"]')) return;

                event.preventDefault();
                event.stopPropagation();

                await handleTokenCreation(mollie, btn);
            }, true);
        }

        function shouldInterceptSubmit() {
            const methodSpan = document.querySelector('[data-mollie-method="creditcard"]');
            if (!methodSpan) return false;

            const container         = methodSpan.closest('tr, .payment-method-row, .vm-payment-plugin-single');
            const input             = container?.querySelector('input[name="virtuemart_paymentmethod_id"]');
            const componentsEnabled = methodSpan.getAttribute('data-components-enabled') === '1';

            return input?.checked && componentsEnabled;
        }

        async function handleTokenCreation(mollie, submitButton) {
            clearAllErrors();

            const { token, error } = await mollie.createToken();

            if (error) {
                console.error('Mollie: Token creation failed:', error.message);
                showError(ERROR_IDS.cardHolder, error.message);
                return;
            }

            addTokenToForm(token);
            clearAllErrors();
            submitButton.click();
        }

        function addTokenToForm(token) {
            const form = document.querySelector('#checkoutFormSubmit')?.closest('form')
                || document.querySelector('form[name="userForm"]');

            if (form) {
                const input   = document.createElement('input');
                input.type    = 'hidden';
                input.name    = 'mollieCardToken';
                input.value   = token;
                form.appendChild(input);
            }
        }

        function showError(errorId, message) {
            const el = document.querySelector(errorId);
            if (el) el.textContent = message;
        }

        function clearAllErrors() {
            Object.values(ERROR_IDS).forEach(errorId => {
                const el = document.querySelector(errorId);
                if (el) el.textContent = '';
            });
        }

        function unmount() {
            try {
                Object.values(componentRefs).forEach(component => {
                    try { component.unmount(); } catch (e) {}
                });
                componentRefs   = {};
                isMountedFlag   = false;
                mollieInstance  = null;
            } catch (e) {
                console.error('Mollie: Error unmounting components:', e);
            }
        }

        function reset() {
            componentRefs   = {};
            isMountedFlag  = false;
            mollieInstance = null;
        }
    }

    MollieComponents.creditCard = new CreditCardService();
})();
