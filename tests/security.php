<?php
/** INS-1206: exercise the public callback with raw JSON, using API/OpenCart doubles. */
namespace Opencart\Catalog\Controller\Extension\Payop\Payment {
    function file_get_contents($filename) {
        return $filename === 'php://input' ? $GLOBALS['ipn_test_body'] : \file_get_contents($filename);
    }
}
namespace {
require __DIR__ . '/payop.php';
function securityHarness(): Harness {
    $h = new Harness();
    $h->model_checkout_order->order['order_id'] = 1;
    $h->model_extension_payop_payment_payop->rows['invoice-1']['order_id'] = 1;
    $h->invoice(['orderIdentifier' => '1']);
    return $h;
}
function deliver(Harness $h, string $json): int {
    $GLOBALS['ipn_test_body'] = $json;
    $h->response->headers = [];
    $h->callback();
    foreach ($h->response->headers as $header) {
        if (preg_match('/^HTTP\/1\.1 ([0-9]{3}) /', $header, $matches)) return (int)$matches[1];
    }
    throw new RuntimeException('Callback did not set an HTTP status');
}
function notification(string $id = 'invoice-1', int $state = 2, string $orderId = '1'): string {
    return json_encode(['invoice' => ['id' => $id, 'txid' => 'fake'], 'transaction' => ['order' => ['id' => $orderId], 'state' => $state]]);
}
$tests = [];
$tests['INS-1206 exact forged IPN returns HTTP 400 without order effects'] = function () {
    $h = securityHarness(); $rows = $h->model_extension_payop_payment_payop->rows;
    $payload = '{"invoice":{"id":"fake","txid":"fake"},"transaction":{"order":{"id":"1"},"state":2}}';
    check(deliver($h, $payload) === 400, 'Forged IPN accepted');
    check($h->model_checkout_order->order['order_status_id'] === 1, 'Forged IPN changed order status');
    check(!$h->model_checkout_order->history && !$h->requests, 'Forged IPN invoked order effects or API');
    check($h->model_extension_payop_payment_payop->rows === $rows, 'Forged IPN changed payment markers');
    check(json_decode($h->response->output, true)['message'] !== 'OK', 'Forged payment acknowledged');
};
$tests['forged success, pending and failure states cannot mutate orders'] = function () {
    foreach ([1, 2, 3, 4, 5] as $state) {
        $h = securityHarness();
        check(deliver($h, notification('fake', $state)) === 400 && !$h->model_checkout_order->history, 'Forged state accepted');
    }
    $h = securityHarness();
    check(deliver($h, notification('invoice-1', 2, '2')) === 400 && !$h->requests, 'Another order accepted this invoice');
};
$tests['knowledge of a real invoice ID is insufficient without paid API status'] = function () {
    $h = securityHarness(); $h->invoice(['orderIdentifier' => '1', 'status' => 0]);
    check(deliver($h, notification()) === 503 && !$h->model_checkout_order->history, 'Unpaid invoice marked complete');
    foreach (['orderIdentifier' => '2', 'identifier' => 'another-invoice', 'amount' => '0.01', 'currency' => 'USD'] as $field => $value) {
        $h = securityHarness(); $h->invoice(array_replace(['orderIdentifier' => '1'], [$field => $value]));
        check(deliver($h, notification()) === 400 && !$h->model_checkout_order->history, 'Mismatched ' . $field . ' accepted');
    }
    $h = securityHarness(); $h->api['status'] = 500;
    check(deliver($h, notification()) === 503 && !$h->model_checkout_order->history, 'API failure marked complete');
};
$tests['public callback validates method and JSON before changing state'] = function () {
    foreach (['{', 'null', '[]', '{}', '{"invoice":"fake"}', '{"invoice":{"id":"fake"},"transaction":true}'] as $body) {
        $h = securityHarness(); check(deliver($h, $body) === 400 && !$h->model_checkout_order->history, 'Malformed notification accepted');
    }
    $h = securityHarness(); $h->request->server['REQUEST_METHOD'] = 'GET';
    check(deliver($h, notification()) === 405 && !$h->requests && !$h->model_checkout_order->history, 'GET processed payment');
};
$tests['verified overdue payment works without JWT; duplicate callbacks have no effects'] = function () {
    $h = securityHarness(); $h->invoice(['orderIdentifier' => '1', 'isOverdue' => true]);
    check(deliver($h, notification()) === 200 && $h->model_checkout_order->history === [[1, 5]], 'Verified payment failed');
    $request = $h->requests[0];
    check($request['headers'] === ['Accept: application/json', 'publicKey: public-test', 'signature: ' . hash('sha256', 'invoice-1:secret-test')], 'Missing authenticated invoice verification');
    $h->api['status'] = 500;
    foreach ([1, 2, 3, 5] as $state) check(deliver($h, notification('invoice-1', $state)) === 200, 'Duplicate not acknowledged');
    check($h->model_checkout_order->history === [[1, 5]] && count($h->requests) === 1, 'Duplicate callback replayed effects');
};
foreach ($tests as $name => $test) { $test(); echo "PASS $name\n"; }
echo count($tests) . " INS-1206 security groups passed\n";
}
