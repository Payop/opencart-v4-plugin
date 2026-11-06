<?php
require __DIR__ . '/payop.php';
function button(string $name, string $method = '', int $enabled = 1): array {
    return ['enabled' => $enabled, 'integration_type' => $method === '' ? 'hosted_page' : 'payment_method', 'payment_method' => $method,
        'language' => [1 => ['name' => $name, 'description' => $name . ' description'], 2 => ['name' => 'UA ' . $name, 'description' => 'UA description']]];
}
function optionsHarness(): Harness {
    $h = new Harness();
    $h->config->values['payment_payop_buttons'] = [
        'button_0000000000000001' => button('Crypto', '101'),
        'button_0000000000000002' => button('Revolut', '202'),
        'button_0000000000000003' => button('Other hosted page'),
        'button_0000000000000004' => button('Disabled', '404', 0)
    ];
    return $h;
}
$tests = [];
$tests['Revolut account type defaults to order currency, with session fallback'] = function () {
    foreach (['GBP' => 'GB', 'EUR' => 'NOT_GB', 'USD' => ''] as $currency => $expected) {
        foreach ([true, false] as $hasOrder) {
            $h = optionsHarness();
            $h->config->values['payment_payop_buttons']['button_0000000000000002']['revolut_fields'] = 1;
            $h->session->data['payment_method']['code'] = 'payop.button_0000000000000002';
            $h->session->data['currency'] = $hasOrder ? 'USD' : $currency;
            if ($hasOrder) $h->model_checkout_order->order['currency_code'] = $currency;
            else unset($h->session->data['order_id']);
            $h->load = new class {
                public array $data = [];
                public function model($name) {} public function language($name) {}
                public function view($name, $data) { $this->data = $data; return ''; }
            };
            $h->index();
            check($h->load->data['payop_iban_type'] === $expected, 'Incorrect account default');
        }
    }
};
$tests['Revolut optional payer fields are validated and isolated from other options'] = function () {
    foreach ([['GBP', '29.02.2000', 'GB', ['date_of_birth' => '29.02.2000', 'iban_type' => 'GB']],
              ['EUR', '31.02.2000', 'NOT_GB', ['iban_type' => 'NOT_GB']],
              ['EUR', '29.02.2000', 'GB', ['date_of_birth' => '29.02.2000']],
              ['GBP', '', 'NOT_GB', []], ['USD', '', 'GB', []],
              ['EUR', '01.01.2999', 'invalid', []], ['EUR', [], [], []], ['EUR', '', '', []]] as [$currency, $dob, $type, $expected]) {
        foreach ([true, false] as $enabled) {
            $h = optionsHarness();
            $h->config->values['payment_payop_buttons']['button_0000000000000002']['revolut_fields'] = (int)$enabled;
            $h->session->data['payment_method']['code'] = 'payop.button_0000000000000002';
            $h->model_checkout_order->order['currency_code'] = $currency;
            $h->request->post = ['date_of_birth' => $dob, 'iban_type' => $type, 'paymentMethod' => '999', 'amount' => '1', 'currency' => 'GBP'];
            $h->api = ['status' => 200, 'error' => 0, 'headers' => ['identifier' => 'new-invoice'], 'body' => '{}'];
            $h->pay(); $payload = json_decode($h->requests[0]['body'], true);
            check(($payload['payer']['extraFields'] ?? []) === ($enabled ? $expected : []), 'Incorrect optional payer fields');
            check($payload['paymentMethod'] === '202' && $payload['order']['currency'] === $currency && $payload['order']['amount'] === '10.0000', 'Client changed method or order');
            check($payload['payer']['name'] === 'Test Customer' && $payload['payer']['email'] === 'test@example.com', 'Order payer changed');
            check(!str_contains(implode(' ', $h->log->lines), '29.02.2000'), 'DOB was logged');
        }
    }
};
$tests['native checkout options, multilingual names and descriptions'] = function () {
    $h = optionsHarness(); $model = $h->model_extension_payop_payment_payop->checkout;
    $options = $model->getMethods()['option'];
    check(array_keys($options) === ['payop', 'button_0000000000000001', 'button_0000000000000002', 'button_0000000000000003'], 'Missing/disabled options');
    check($options['button_0000000000000001'] === ['code' => 'payop.button_0000000000000001', 'name' => 'Crypto — Crypto description'], 'Incorrect native option');
    $h->config->values['config_language_id'] = 2;
    check($model->getCheckoutOptions()['button_0000000000000001']['title'] === 'UA Crypto', 'Wrong translation');
    $h->config->values['payment_payop_buttons']['button_0000000000000001']['language'][2]['name'] = '<img src=x onerror=alert(1)>';
    check(!str_contains($model->getMethods()['option']['button_0000000000000001']['name'], '<img'), 'Checkout HTML injection');
};
$tests['main and additional Hosted Page omit paymentMethod and do not require JWT'] = function () {
    foreach (['payop.payop', 'payop.button_0000000000000003'] as $code) {
        $h = optionsHarness(); $h->session->data['payment_method']['code'] = $code;
        $h->api = ['status' => 200, 'error' => 0, 'headers' => ['identifier' => 'new-invoice'], 'body' => '{}']; $h->pay();
        check(!array_key_exists('paymentMethod', json_decode($h->requests[0]['body'], true)), 'Hosted Page sent paymentMethod');
        check(isset($h->model_extension_payop_payment_payop->rows['new-invoice']), 'No saved attempt');
    }
};
$tests['method ID comes only from current saved server settings'] = function () {
    $h = optionsHarness(); $h->session->data['payment_method'] = ['code' => 'payop.button_0000000000000001', 'payment_method' => '999'];
    $h->request->post = ['paymentMethod' => '999', 'payment_method' => '999', 'code' => 'payop.button_0000000000000002'];
    $h->api = ['status' => 200, 'error' => 0, 'headers' => ['identifier' => 'new-invoice'], 'body' => '{}']; $h->pay();
    check(json_decode($h->requests[0]['body'], true)['paymentMethod'] === '101', 'Shopper overrode saved method');
};
$tests['return and switch create separate invoices; late paid attempt is accepted once'] = function () {
    $h = optionsHarness(); $storage = $h->model_extension_payop_payment_payop;
    foreach (['1' => '101', '2' => '202', '3' => null] as $suffix => $method) {
        $h->session->data['payment_method']['code'] = 'payop.button_' . str_pad($suffix, 16, '0', STR_PAD_LEFT);
        $id = 'attempt-' . $suffix;
        $h->api = ['status' => 200, 'error' => 0, 'headers' => ['identifier' => $id], 'body' => '{}']; $h->pay();
        $request = json_decode(end($h->requests)['body'], true);
        check(($request['paymentMethod'] ?? null) === $method, 'Stale method reused');
        check(str_contains(json_decode($h->response->output), $id), 'Old checkout URL reopened');
    }
    check(count($storage->rows) === 4, 'Older attempts discarded');
    $h->config->values['payment_payop_buttons'] = []; // Deleting a button must not discard its existing invoices.
    $before = $h->model_checkout_order->history;
    $h->invoice(['identifier' => 'attempt-1', 'status' => 3, 'isOverdue' => true]);
    check($h->ipn(['invoice' => ['id' => 'attempt-1'], 'transaction' => ['state' => 3]]) === 200, 'Old failed notification rejected');
    check($before === $h->model_checkout_order->history, 'Old failure cancelled current attempt');
    $h->invoice(['identifier' => 'attempt-1', 'status' => 1, 'isOverdue' => true]);
    check($h->ipn(['invoice' => ['id' => 'attempt-1']]) === 200, 'Old paid invoice rejected');
    $after = $h->model_checkout_order->history;
    check(end($after) === [778, 5], 'Old invoice did not confirm original order');
    $h->api['status'] = 500;
    foreach (['attempt-1', 'attempt-2', 'attempt-3'] as $id) {
        check($h->ipn(['invoice' => ['id' => $id], 'transaction' => ['state' => 3]]) === 200, 'Late/repeat delivery rejected');
    }
    check($after === $h->model_checkout_order->history, 'Duplicate effects or downgrade after payment');
};
$tests['deleted, disabled, stale and unrelated selections cannot create invoices'] = function () {
    foreach (['payop.button_0000000000000004', 'payop.button_9999999999999999', 'other.method', ''] as $code) {
        $h = optionsHarness(); $h->session->data['payment_method']['code'] = $code; $h->pay();
        check(!$h->requests && !$h->model_checkout_order->history, 'Unavailable option accepted');
    }
    $h = optionsHarness(); $h->config->values['payment_payop_status'] = 0; $h->pay();
    check(!$h->requests, 'Disabled module created invoice');
    $h = optionsHarness(); $h->request->server['REQUEST_METHOD'] = 'GET'; $h->pay();
    check(!$h->requests, 'GET created invoice');
};
$tests['stable codes after deletion and no button count cap'] = function () {
    $h = optionsHarness(); unset($h->config->values['payment_payop_buttons']['button_0000000000000001']);
    $model = $h->model_extension_payop_payment_payop->checkout;
    check($model->getCheckoutOptions()['button_0000000000000002']['payment_method'] === '202', 'Deletion shifted another option');
    $h->config->values['payment_payop_buttons'] = [];
    for ($i = 0; $i < 300; $i++) $h->config->values['payment_payop_buttons']['button_' . sprintf('%016x', $i)] = button('Button ' . $i);
    check(count($model->getMethods()['option']) === 301, 'Artificial option limit');
};
foreach ($tests as $name => $test) { $test(); echo "PASS $name\n"; }
echo count($tests) . " checkout option groups passed\n";
