<?php
/** Standalone regression suite: php tests/payop.php (no OpenCart installation/API keys required). */
namespace Opencart\System\Engine {
	#[\AllowDynamicProperties]
	class Controller {}
	#[\AllowDynamicProperties]
	class Model {}
}
namespace {
require __DIR__ . '/../catalog/controller/payment/payop.php';
require __DIR__ . '/../catalog/model/payment/payop.php';

function check($condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}
class Config {
	public array $values = [
		'payment_payop_status' => 1, 'config_language_id' => 1, 'payment_payop_public_id' => 'public-test', 'payment_payop_secret_key' => 'secret-test',
		'payment_payop_order_status_success' => 5, 'payment_payop_order_status_wait' => 1,
		'payment_payop_order_status_error' => 10, 'config_processing_status' => [2], 'config_complete_status' => [5]
	];
	public function get($key) { return $this->values[$key] ?? null; }
}
class Attempts {
	public array $rows = ['invoice-1' => ['invoice_id' => 'invoice-1', 'order_id' => 778, 'amount' => '10.0000', 'currency' => 'EUR', 'payment_state' => 0]];
	public bool $locked = false;
	public bool $busy = false;
	public function lockOrder($id) { if ($this->busy || $this->locked) return false; return $this->locked = true; }
	public function unlockOrder($id) { $this->locked = false; }
	public function getInvoice($id, $invoice) { return ($this->rows[$invoice]['order_id'] ?? 0) === $id ? $this->rows[$invoice] : []; }
	public function hasProcessedPayment($id) { return (bool)array_filter($this->rows, fn($row) => $row['order_id'] === $id && $row['payment_state'] > 0); }
	public function isOrderPaid($id) { return (bool)array_filter($this->rows, fn($row) => $row['order_id'] === $id && $row['payment_state'] === 2); }
	public function markProcessing($id, $invoice) { $this->rows[$invoice]['payment_state'] = 1; }
	public function markPaid($id, $invoice) { $this->rows[$invoice]['payment_state'] = 2; }
	public $checkout;
	public function getSelectedOption(string $code, array $address = []): ?array { return $this->checkout->getSelectedOption($code, $address); }
	public function saveInvoice($id, $invoice, $amount, $currency) {
		$this->rows[$invoice] = ['invoice_id' => $invoice, 'order_id' => $id, 'amount' => $amount, 'currency' => $currency, 'payment_state' => 0];
	}
}
class Orders {
	public array $order = ['order_id' => 778, 'order_status_id' => 1, 'total' => '8', 'currency_code' => 'EUR', 'currency_value' => '1.25', 'email' => 'test@example.com', 'telephone' => '', 'firstname' => 'Test', 'lastname' => 'Customer'];
	public array $history = [];
	public bool $fail = false;
	public function getOrder($id) { return $id === (int)$this->order['order_id'] ? $this->order : []; }
	public function getProducts($id) { return []; }
	public function addHistory($id, $status) {
		if ($this->fail) throw new RuntimeException('Simulated interrupted status update');
		$this->history[] = [$id, $status]; $this->order['order_status_id'] = $status;
	}
}
class Harness extends \Opencart\Catalog\Controller\Extension\Payop\Payment\Payop {
	public array $requests = [];
	public array $api;
	public function __construct() {
		$this->config = new Config();
		$this->model_extension_payop_payment_payop = new Attempts();
		$this->model_checkout_order = new Orders();
		$this->load = new class { public function model($name) {} public function language($name) {} };
		$this->log = new class { public array $lines = []; public function write($message) { $this->lines[] = $message; } };
		$this->response = new class {
			public array $headers = []; public string $output = '';
			public function addHeader($header) { $this->headers[] = $header; }
			public function setOutput($output) { $this->output = $output; }
		};
		$this->session = (object)['data' => ['order_id' => 778, 'payment_method' => ['code' => 'payop.payop']]];
		$this->request = (object)['server' => ['REQUEST_METHOD' => 'POST'], 'post' => []];
		$this->language = new class {
			private array $data;
			public function __construct() {
				$_ = []; require __DIR__ . '/../catalog/language/en-gb/payment/payop.php';
				$this->data = $_ + ['code' => 'en'];
			}
			public function get($key) { return $this->data[$key] ?? $key; }
		};
		$this->url = new class { public function link($route) { return 'https://store.example/' . $route; } };
		$this->currency = new class {
			public array $last = [];
			public function format($amount, $currency, $value, $format) { $this->last = func_get_args(); return $amount * $value; }
		};
		$checkout = new \Opencart\Catalog\Model\Extension\Payop\Payment\Payop();
		$checkout->config = $this->config; $checkout->language = $this->language; $checkout->load = $this->load;
		$checkout->cart = new class { public function hasSubscription() { return false; } };
		$this->model_extension_payop_payment_payop->checkout = $checkout;
		$this->invoice([]);
	}
	public function invoice(array $changes): void {
		$this->api = ['status' => 200, 'error' => 0, 'headers' => [], 'body' => json_encode(['data' => array_replace([
			'identifier' => 'invoice-1', 'orderIdentifier' => '778', 'status' => 1, 'amount' => 10, 'currency' => 'EUR', 'isOverdue' => false
		], $changes)])];
	}
	protected function requestApi(string $method, string $url, array $headers, ?string $body = null): array {
		$this->requests[] = compact('method', 'url', 'headers', 'body'); return $this->api;
	}
	public function ipn(array $changes = []): int {
		return $this->processCallback(array_replace_recursive(['invoice' => ['id' => 'invoice-1', 'txid' => 'tx-1'], 'transaction' => ['state' => 2, 'order' => ['id' => '778']]], $changes));
	}
	public function fetch(string $id = 'invoice-1') { return $this->getInvoice($id, 778); }
}
$tests = [];
$tests['paid invoice, current and overdue'] = function () {
	foreach ([false, true] as $overdue) {
		$h = new Harness(); $h->invoice(['isOverdue' => $overdue]);
		check($h->ipn() === 200, 'Paid invoice rejected');
		check($h->model_checkout_order->history === [[778, 5]], 'Configured success status not applied');
		check($h->model_extension_payop_payment_payop->rows['invoice-1']['payment_state'] === 2, 'Missing paid marker');
		check(!$h->model_extension_payop_payment_payop->locked, 'Lock leaked');
	}
};
$tests['invoice status is authoritative, including late pending IPN'] = function () {
	foreach ([1, 3, 5] as $state) {
		$h = new Harness(); check($h->ipn(['transaction' => ['state' => $state]]) === 200, 'API paid invoice rejected');
		check(count($h->model_checkout_order->history) === 1, 'API paid invoice not confirmed');
	}
	foreach ([0, 2, 3, 5] as $status) {
		foreach ([false, true] as $overdue) {
			$h = new Harness(); $h->invoice(['status' => $status, 'isOverdue' => $overdue]);
			check($h->ipn() === 503, 'Premature success IPN acknowledged');
			check(!$h->model_checkout_order->history, 'Unpaid invoice changed order');
			check($h->ipn(['transaction' => ['state' => 1]]) === 200, 'Valid unpaid pending not acknowledged');
		}
	}
};
$tests['each mismatched field blocks payment'] = function () {
	foreach (['identifier' => 'different', 'orderIdentifier' => '779', 'amount' => '10.00001', 'currency' => 'USD'] as $field => $value) {
		$h = new Harness(); $h->invoice([$field => $value]);
		check($h->ipn() === 400 && !$h->model_checkout_order->history, 'Mismatch accepted: ' . $field);
		check((bool)$h->log->lines, 'Mismatch not logged');
	}
};
$tests['missing and invalid field types'] = function () {
	$invalid = ['identifier' => [null, '', [], 1], 'orderIdentifier' => [null, [], true, 778.5, '0778'], 'amount' => [null, true, [], '-1', 'NaN', ''], 'currency' => [null, [], 'EU', 123], 'status' => [null, true, '1', 1.5, -1]];
	foreach ($invalid as $field => $values) {
		foreach ($values as $value) {
			$h = new Harness(); $h->invoice([$field => $value]);
			check($h->ipn() === 503 && !$h->model_checkout_order->history, 'Invalid field accepted: ' . $field);
		}
		$h = new Harness(); $body = json_decode($h->api['body'], true); unset($body['data'][$field]); $h->api['body'] = json_encode($body);
		check($h->ipn() === 503, 'Missing field accepted: ' . $field);
	}
};
$tests['HTTP errors, timeout, network and invalid JSON'] = function () {
	foreach ([['status' => 422, 'body' => 'Wrong signature'], ['status' => 422, 'body' => 'Invoice not found'], ['status' => 422, 'body' => 'Invoice is overdue'], ['status' => 500], ['status' => 201], ['error' => 28], ['error' => 7], ['body' => '{'], ['body' => '{"data":[]}'], ['body' => '{}']] as $response) {
		$h = new Harness(); $h->api = array_replace($h->api, $response);
		check($h->ipn() === 503 && !$h->model_checkout_order->history, 'API failure accepted');
		check((bool)$h->log->lines, 'API failure not logged');
	}
};
$tests['repeat success, other attempt and delayed notifications'] = function () {
	$h = new Harness(); check($h->ipn() === 200, 'First payment failed');
	$h->model_extension_payop_payment_payop->rows['invoice-2'] = array_replace($h->model_extension_payop_payment_payop->rows['invoice-1'], ['invoice_id' => 'invoice-2', 'payment_state' => 0]);
	$h->model_checkout_order->order['order_status_id'] = 7;
	$h->api['status'] = 500;
	foreach ([1, 2, 3, 5] as $state) check($h->ipn(['transaction' => ['state' => $state]]) === 200, 'Duplicate failed');
	check($h->ipn(['invoice' => ['id' => 'invoice-2']]) === 200, 'Second attempt not protected');
	check($h->model_checkout_order->history === [[778, 5]], 'Duplicate history or downgrade');
	check(count($h->requests) === 1, 'Duplicate needlessly queried API');
};
$tests['unlinked invoices and invalid callbacks'] = function () {
	foreach ([['invoice' => ['id' => 'unknown']], ['invoice' => ['id' => []]], ['transaction' => ['order' => ['id' => '779']]], ['transaction' => ['order' => ['id' => true]]], ['transaction' => ['state' => '2']]] as $change) {
		$h = new Harness(); check($h->ipn($change) === 400 && !$h->requests, 'Untrusted callback queried or accepted');
	}
};
$tests['credentials, signature and encoded URL without JWT'] = function () {
	$h = new Harness(); $h->fetch('invoice/a:b ?'); $r = $h->requests[0];
	check($r['method'] === 'GET' && $r['body'] === null, 'Wrong invoice method/body');
	check($r['url'] === 'https://api.payop.com/v1/invoices/invoice%2Fa%3Ab%20%3F', 'ID not URL encoded');
	check($r['headers'] === ['Accept: application/json', 'publicKey: public-test', 'signature: ' . hash('sha256', 'invoice/a:b ?:secret-test')], 'Wrong headers/signature');
	foreach (['payment_payop_public_id', 'payment_payop_secret_key'] as $key) {
		$h = new Harness(); $h->config->values[$key] = ''; check($h->ipn() === 503 && !$h->requests, 'Missing credentials sent to API');
	}
	$h = new Harness(); check($h->fetch('') === null && !$h->requests, 'Empty ID sent');
};
$tests['exact decimals, significant precision and currency case'] = function () {
	foreach (['10.0000000', '010.0', '1e1', '100e-1'] as $amount) {
		$h = new Harness(); $h->invoice(['amount' => $amount, 'currency' => 'eur']); check($h->ipn() === 200, 'Equivalent decimal rejected');
	}
	foreach (['10.0000000000000001', '9.9999999999999999', '10.00001'] as $amount) {
		$h = new Harness(); $h->api['body'] = str_replace('"amount":10', '"amount":' . $amount, $h->api['body']);
		check($h->ipn() === 400 && !$h->model_checkout_order->history, 'JSON float hid mismatch: ' . $amount);
	}
};
$tests['create invoice with historic conversion rate and persisted snapshot'] = function () {
	$h = new Harness(); $h->api = ['status' => 200, 'error' => 0, 'headers' => ['identifier' => 'invoice-new'], 'body' => '{}'];
	$h->pay();
	check(in_array('x-integration-tag: opencart4', $h->requests[0]['headers'], true), 'Missing invoice creation integration tag');
	$request = json_decode($h->requests[0]['body'], true);
	check($request['order']['amount'] === '10.0000', 'Wrong converted amount');
	check($h->currency->last === ['8', 'EUR', '1.25', false], 'Did not use saved rate');
	$row = $h->model_extension_payop_payment_payop->rows['invoice-new'];
	check($row['amount'] === '10.0000' && $row['currency'] === 'EUR', 'Snapshot not saved');
	check(str_contains(json_decode($h->response->output), 'invoice-new'), 'No checkout URL');
	$h->invoice(['identifier' => 'invoice-new']);
	check($h->ipn(['invoice' => ['id' => 'invoice-new']]) === 200, 'Converted payment rejected');
	check($h->model_checkout_order->history === [[778, 1], [778, 5]], 'Wrong payment history');
};
$tests['invoice API rejection is distinct from unavailable checkout option'] = function () {
	$h = new Harness(); $h->api = ['status' => 422, 'error' => 0, 'headers' => [], 'body' => '{"message":"Provider rejected request"}'];
	$h->pay();
	$message = json_decode($h->response->output, true)['message'];
	check($message === $h->language->get('error_invoice_creation'), 'Invoice error hidden by generic option error');
	check(!$h->model_checkout_order->history, 'Rejected creation changed order');
	$h = new Harness(); $h->session->data['payment_method']['code'] = 'payop.unknown'; $h->pay();
	check(json_decode($h->response->output, true)['message'] === $h->language->get('error_payment_option'), 'Unavailable option error lost');
	check(!$h->requests, 'Unavailable option sent to API');
};
$tests['Payop minimum amount rejection returns a safe actionable message'] = function () {
	$h = new Harness();
	$h->model_checkout_order->order['total'] = '1';
	$h->model_checkout_order->order['currency_code'] = 'USD';
	$h->model_checkout_order->order['currency_value'] = '1';
	$h->api = ['status' => 422, 'error' => 0, 'headers' => [], 'body' => json_encode(['message' => 'The specified amount 1 is less than the permissible minimum for the selected currency - 1.12 US Dollar'])];
	$h->pay();
	check(json_decode($h->response->output, true)['message'] === sprintf($h->language->get('error_invoice_minimum'), '1.12', 'USD'), 'Minimum amount not translated');
	check(!$h->model_checkout_order->history && !isset($h->model_extension_payop_payment_payop->rows['new-invoice']), 'Rejected invoice changed order');
	$request = json_decode($h->requests[0]['body'], true);
	check($request['order']['amount'] === '1.0000', 'Payment amount silently increased');
	$h = new Harness();
	$h->api = ['status' => 422, 'error' => 0, 'headers' => [], 'body' => json_encode(['message' => 'secret-test private@example.com'])];
	$h->pay();
	check(json_decode($h->response->output, true)['message'] === $h->language->get('error_invoice_creation'), 'Arbitrary API error exposed');
};
$tests['diagnostic logs contain no credentials or API payloads'] = function () {
	$h = new Harness(); $h->api['status'] = 422;
	$h->api['body'] = 'Wrong signature secret-test private-customer@example.com';
	check($h->ipn() === 503, 'Error acknowledged');
	$logs = implode("\n", $h->log->lines);
	foreach (['secret-test', 'public-test', 'private-customer@example.com', hash('sha256', 'invoice-1:secret-test')] as $sensitive) {
		check(!str_contains($logs, $sensitive), 'Sensitive value in log');
	}
	check(str_contains($logs, '778') && str_contains($logs, 'invoice-1') && str_contains($logs, '422'), 'Missing diagnostic context');
};
$tests['busy and interrupted processing are not replayed'] = function () {
	$h = new Harness(); $h->model_extension_payop_payment_payop->busy = true; check($h->ipn() === 503 && !$h->requests, 'Busy order accepted');
	$h = new Harness(); $h->model_checkout_order->fail = true;
	check($h->ipn() === 503, 'Failed status update acknowledged');
	$h->model_checkout_order->fail = false;
	check($h->ipn() === 503 && !$h->model_checkout_order->history, 'Interrupted payment replayed');
	check(!$h->model_extension_payop_payment_payop->locked, 'Failure leaked lock');
};
$tests['paid and processing orders cannot create more invoices'] = function () {
	foreach ([1, 2] as $marker) {
		$h = new Harness(); $h->model_extension_payop_payment_payop->rows['invoice-1']['payment_state'] = $marker; $h->pay();
		check(!$h->requests && !$h->model_checkout_order->history, 'Processed order restarted');
	}
};
$tests['waiting status may be an OpenCart processing status'] = function () {
	$h = new Harness(); $h->config->values['config_processing_status'] = [1];
	check($h->ipn() === 200 && count($h->requests) === 1 && $h->model_checkout_order->history === [[778, 5]], 'Waiting order bypassed verification');
};
if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
foreach ($tests as $name => $test) {
	$test(); echo "PASS $name\n";
}
echo count($tests) . " test groups passed\n";
}
}
