(() => {
    'use strict';
    const button = document.getElementById('button_pay');
    if (!button) return;
    button.addEventListener('click', async () => {
        button.disabled = true;
        try {
            // OpenCart stores the chosen option in the session; no method ID comes from this request.
            const options = { method: 'POST' };
            if (document.getElementById('payop-revolut-fields')) {
                options.body = new URLSearchParams({
                    date_of_birth: document.getElementById('payop-date-of-birth').value,
                    iban_type: document.getElementById('payop-iban-type').value
                });
            }
            const response = await fetch(button.dataset.payopUrl, options);
            const url = await response.json();
            if (!response.ok) {
                const message = url && typeof url.message === 'string' && url.message.trim() ? url.message : button.dataset.payopError;
                alert(message);
                return;
            }
            if (typeof url !== 'string' || new URL(url).origin !== 'https://checkout.payop.com') throw new Error('Invalid checkout URL');
            window.location.href = url;
        } catch (error) {
            alert(button.dataset.payopError);
        } finally {
            button.disabled = false;
        }
    });
})();
