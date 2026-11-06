// Run with node tests/checkout.js. Tests the checkout client without network access.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../catalog/view/javascript/payop.js'), 'utf8');
async function run(response, error, revolut = false) {
    let click;
    const alerts = [], requests = [];
    const button = { disabled: false, dataset: { payopUrl: '/payment', payopError: 'Generic payment failure' }, addEventListener: (name, handler) => { click = handler; } };
    const window = { location: { href: '' } };
    vm.runInNewContext(source, {
        document: { getElementById: id => id === 'button_pay' ? button : !revolut ? null : ({
            'payop-revolut-fields': {}, 'payop-date-of-birth': {value: '01.01.1990'}, 'payop-iban-type': {value: 'GB'}
        }[id] || null) }, window, URL, URLSearchParams,
        alert: message => alerts.push(message),
        fetch: async (url, options) => { requests.push({url, options}); if (error) throw error; return response; }
    });
    await click();
    assert.equal(button.disabled, false, 'Payment button remained disabled');
    assert.equal(requests[0].options.method, 'POST');
    if (revolut) {
        assert.equal(requests[0].options.body.toString(), 'date_of_birth=01.01.1990&iban_type=GB');
    } else assert.equal(requests[0].options.body, undefined, 'Unexpected payer data for ordinary option');
    return { alerts, window };
}
(async () => {
    let result = await run({ ok: false, json: async () => ({ message: 'Payop could not create an invoice.' }) });
    assert.deepEqual(result.alerts, ['Payop could not create an invoice.']);
    assert.equal(result.window.location.href, '');
    result = await run({ ok: false, json: async () => ({ message: 'The selected payment option is unavailable.' }) });
    assert.deepEqual(result.alerts, ['The selected payment option is unavailable.']);
    result = await run({ ok: false, json: async () => ({}) });
    assert.deepEqual(result.alerts, ['Generic payment failure']);
    result = await run(null, new Error('Network error'));
    assert.deepEqual(result.alerts, ['Generic payment failure']);
    result = await run({ ok: true, json: async () => 'https://checkout.payop.com/en/payment/invoice-preprocessing/invoice-1' });
    assert.equal(result.window.location.href, 'https://checkout.payop.com/en/payment/invoice-preprocessing/invoice-1');
    result = await run({ ok: true, json: async () => 'https://untrusted.example/' });
    assert.deepEqual(result.alerts, ['Generic payment failure']);
    assert.equal(result.window.location.href, '');
    result = await run({ ok: true, json: async () => 'https://checkout.payop.com/en/payment/invoice-preprocessing/revolut' }, null, true);
    assert.equal(result.window.location.href, 'https://checkout.payop.com/en/payment/invoice-preprocessing/revolut');
    console.log('PASS checkout client: specific errors, fallback, POST, redirect and URL validation');
})().catch(error => { console.error(error); process.exitCode = 1; });
