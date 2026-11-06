<?php
namespace Opencart\Catalog\Controller\Extension\Payop\Payment;

class Payop extends \Opencart\System\Engine\Controller {
	private const REVOLUT_IBAN_BY_CURRENCY = ['GBP' => 'GB', 'EUR' => 'NOT_GB'];

	private string $invoiceError = '';

	public function index() {
		$this->load->language('extension/payop/payment/payop');
		$this->load->model('extension/payop/payment/payop');
		$option = $this->selectedOption();
		if ($option === null) return '';
		$currency = $this->session->data['currency'] ?? '';
		if (!empty($option['revolut_fields'])) {
			$this->load->model('checkout/order');
			$order = $this->model_checkout_order->getOrder((int)($this->session->data['order_id'] ?? 0));
			$currency = $order['currency_code'] ?? $currency;
		}
		return $this->load->view('extension/payop/payment/payop', [
			'payop_title' => $option['title'],
			'payop_revolut_fields' => !empty($option['revolut_fields']),
			'payop_iban_type' => self::REVOLUT_IBAN_BY_CURRENCY[$currency] ?? '',
			'entry_date_of_birth' => $this->language->get('entry_date_of_birth'),
			'entry_iban_type' => $this->language->get('entry_iban_type'),
			'text_revolut_optional' => $this->language->get('text_revolut_optional'),
			'text_select_iban' => $this->language->get('text_select_iban'),
			'text_iban_gb' => $this->language->get('text_iban_gb'),
			'text_iban_not_gb' => $this->language->get('text_iban_not_gb'),
			'payop_description' => $option['description'],
			'error_payment' => $this->language->get('error_payment'),
			'button_pay' => $this->language->get('button_pay'),
			'payop_url' => $this->url->link('extension/payop/payment/payop.pay')
		]);
	}

	public function pay() {
		$this->response->addHeader('Content-Type: application/json');
		$this->load->language('extension/payop/payment/payop');
		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$this->respond(405, 'POST required');
			return;
		}
		$this->load->model('checkout/order');
		$this->load->model('extension/payop/payment/payop');
		$orderId = (int)($this->session->data['order_id'] ?? 0);
		$model = $this->model_extension_payop_payment_payop;
		$locked = false;
		try {
			$locked = $orderId && $model->lockOrder($orderId);
			if (!$locked) {
				$this->respond(503, $this->language->get('error_payment'));
				return;
			}
			$order = $this->model_checkout_order->getOrder($orderId);
			if (!$order || $model->hasProcessedPayment($orderId) || $this->isPaidOrder($order)) {
				$this->respond(409, $this->language->get('error_order_unavailable'));
				return;
			}
			$option = $this->selectedOption();
			if ($option === null) {
				$this->respond(409, $this->language->get('error_payment_option'));
				return;
			}
			$products = $this->model_checkout_order->getProducts($orderId);
			$items = array_map(function ($product) {
				return ['id' => $product['order_product_id'], 'name' => trim($product['name'] . ' ' . $product['model']), 'price' => $product['price']];
			}, $products);
			$request = $this->preparePaymentRequest($order, $items);
			if ($option['integration_type'] === 'payment_method') $request['paymentMethod'] = $option['payment_method'];
			if (!empty($option['revolut_fields']) && $option['integration_type'] === 'payment_method') {
				$extra = $this->revolutExtraFields($this->request->post, $request['order']['currency']);
				if ($extra) $request['payer']['extraFields'] = $extra;
			}
			$request['signature'] = $this->generate_signature($request['order']);
			$invoiceId = $this->makeRequest($request);
			if (!$invoiceId) {
				$this->respond(503, $this->invoiceError ?: $this->language->get('error_invoice_creation'));
				return;
			}
			// Persist the exact amount/currency sent to Payop before exposing the payment URL.
			$model->saveInvoice($orderId, $invoiceId, $request['order']['amount'], $request['order']['currency']);
			$this->model_checkout_order->addHistory($orderId, $this->config->get('payment_payop_order_status_wait'), 'Payop invoice ' . $invoiceId . '; option ' . $option['code'] . '; payment method ' . ($option['payment_method'] ?: 'Hosted Page'));
			$url = 'https://checkout.payop.com/' . rawurlencode($this->language->get('code')) . '/payment/invoice-preprocessing/' . rawurlencode($invoiceId);
			$this->response->setOutput(json_encode($url));
		} catch (\Throwable $e) {
			$this->diagnostic('Invoice creation or storage failed', $orderId);
			$this->respond(503, $this->language->get('error_payment'));
		} finally {
			if ($locked) $model->unlockOrder($orderId);
		}
	}

	private function selectedOption(): ?array {
		$code = $this->session->data['payment_method']['code'] ?? '';
		if (!is_string($code)) return null;
		$address = $this->session->data['payment_address'] ?? $this->session->data['shipping_address'] ?? [];
		return $this->model_extension_payop_payment_payop->getSelectedOption($code, is_array($address) ? $address : []);
	}

	public function callback() {
		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST') {
			$this->respond(405, 'POST required');
			return;
		}
		$callback = json_decode(file_get_contents('php://input'), true);
		if (!is_array($callback)) {
			$this->diagnostic('Invalid IPN JSON');
			$this->respond(400, 'Invalid notification');
			return;
		}
		$code = $this->processCallback($callback);
		$this->respond($code, $code === 200 ? 'OK' : 'Invoice verification failed');
	}

	private function respond(int $code, string $message): void {
		$reasons = [200 => 'OK', 400 => 'Bad Request', 405 => 'Method Not Allowed', 409 => 'Conflict', 503 => 'Service Unavailable'];
		$this->response->addHeader('HTTP/1.1 ' . $code . ' ' . $reasons[$code]);
		$this->response->setOutput(json_encode(['message' => $message]));
	}

	protected function diagnostic(string $reason, int $orderId = 0, string $invoiceId = ''): void {
		// Only controlled reasons and identifiers; never log payloads, headers or credentials.
		$this->log->write('Payop: ' . $reason . ' order=' . $orderId . ' invoice=' . json_encode($invoiceId));
	}
	private function preparePaymentRequest($order_info, $payop_order_items) {
		return [
			'publicKey' => $this->config->get('payment_payop_public_id'),
			'order' => [
				'id' => $order_info['order_id'],
				'amount' => number_format($this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false), 4, ".", ""),
				'currency' => $order_info['currency_code'],
				'description' => sprintf($this->language->get('order_description'), $order_info['order_id']),
				'items' => $payop_order_items,
			],
			'payer' => [
				'email' => $order_info['email'],
				'phone' => $order_info['telephone'],
				'name' => $order_info['firstname'] . ' ' . $order_info['lastname']
			],
			'resultUrl' => $this->url->link('checkout/success'),
			'failPath' => $this->url->link('checkout/failure'),
			'language' => $this->language->get('code')
		];
	}

	/** Forward only supported optional payer fields; missing data stays on Hosted Page. */
	private function revolutExtraFields(array $input, string $currency): array {
		$extra = [];
		$dob = $input['date_of_birth'] ?? null;
		if (is_string($dob) && preg_match('/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/D', $dob, $parts) &&
			checkdate((int)$parts[2], (int)$parts[1], (int)$parts[3]) &&
			$parts[3] . $parts[2] . $parts[1] < date('Ymd')) {
			$extra['date_of_birth'] = $dob;
		}
		$ibanType = $input['iban_type'] ?? '';
		if (isset(self::REVOLUT_IBAN_BY_CURRENCY[$currency]) && $ibanType === self::REVOLUT_IBAN_BY_CURRENCY[$currency]) {
			$extra['iban_type'] = $ibanType;
		}
		return $extra;
	}

	/** IPN is a trigger only. Both payment status and order details come from the invoice API. */
	protected function processCallback(array $callback): int {
		$invoiceId = $callback['invoice']['id'] ?? null;
		$orderId = $callback['transaction']['order']['id'] ?? null;
		$state = $callback['transaction']['state'] ?? null;
		if (!is_string($invoiceId) || $invoiceId === '' || strlen($invoiceId) > 255 ||
			!((is_string($orderId) && preg_match('/^[1-9][0-9]*$/D', $orderId)) || (is_int($orderId) && $orderId > 0)) ||
			(string)(int)$orderId !== (string)$orderId || !is_int($state) || $state < 1 || $state > 5) {
			$this->diagnostic('Missing or invalid IPN invoice, order or state');
			return 400;
		}
		$orderId = (int)$orderId;
		$this->load->model('checkout/order');
		$this->load->model('extension/payop/payment/payop');
		$model = $this->model_extension_payop_payment_payop;
		$locked = false;
		try {
			$locked = $model->lockOrder($orderId);
			if (!$locked) {
				$this->diagnostic('Order verification is busy', $orderId, $invoiceId);
				return 503;
			}
			$attempt = $model->getInvoice($orderId, $invoiceId);
			$order = $this->model_checkout_order->getOrder($orderId);
			if (!$attempt || !$order) {
				$this->diagnostic('Invoice is not linked to this order', $orderId, $invoiceId);
				return 400;
			}
			// Persistent markers also protect orders moved to a different status after payment.
			if ($model->isOrderPaid($orderId)) {
				return 200;
			}
			if ($model->hasProcessedPayment($orderId)) {
				$this->diagnostic('Previous payment processing requires reconciliation', $orderId, $invoiceId);
				return 503;
			}
			if ($this->isPaidOrder($order)) return 200;
			$invoice = $this->getInvoice($invoiceId, $orderId);
			if ($invoice === null) {
				return 503;
			}
			if (!$this->matchesInvoice($invoice, $attempt, $orderId, $invoiceId)) {
				return 400;
			}
			if ($invoice['status'] !== 1) {
				$this->diagnostic('Invoice is not paid', $orderId, $invoiceId);
				// No failure/pending transition based on an unauthenticated notification.
				return $state === 2 ? 503 : 200;
			}
			$success = (int)$this->config->get('payment_payop_order_status_success');
			if ($success <= 0) {
				$this->diagnostic('Success status is not configured', $orderId, $invoiceId);
				return 503;
			}
			// Record intent first: an interrupted addHistory must never be replayed blindly.
			$model->markProcessing($orderId, $invoiceId);
			$this->model_checkout_order->addHistory($orderId, $success);
			$model->markPaid($orderId, $invoiceId);
			return 200;
		} catch (\Throwable $e) {
			$this->diagnostic('Payment verification or status update failed', $orderId, $invoiceId);
			return 503;
		} finally {
			if ($locked) {
				$model->unlockOrder($orderId);
			}
		}
	}

	private function isPaidOrder(array $order): bool {
		$status = (int)$order['order_status_id'];
		$paid = array_merge((array)$this->config->get('config_processing_status'), (array)$this->config->get('config_complete_status'));
		$success = (int)$this->config->get('payment_payop_order_status_success');
		if ($success !== (int)$this->config->get('payment_payop_order_status_wait') && $success !== (int)$this->config->get('payment_payop_order_status_error')) {
			$paid[] = $success;
		}
		$paid = array_diff(array_map('intval', $paid), [0, (int)$this->config->get('payment_payop_order_status_wait'), (int)$this->config->get('payment_payop_order_status_error')]);
		return $status > 0 && in_array($status, $paid, true);
	}

	protected function getInvoice(string $invoiceId, int $orderId = 0): ?array {
		$publicKey = $this->config->get('payment_payop_public_id');
		$secretKey = $this->config->get('payment_payop_secret_key');
		if (!$invoiceId || !is_string($publicKey) || trim($publicKey) === '' || !is_string($secretKey) || trim($secretKey) === '' || preg_match('/[\r\n]/', $publicKey)) {
			$this->diagnostic('Missing or invalid invoice ID or API settings', $orderId, $invoiceId);
			return null;
		}
		$response = $this->requestApi('GET', 'https://api.payop.com/v1/invoices/' . rawurlencode($invoiceId), [
			'Accept: application/json',
			'publicKey: ' . $publicKey,
			'signature: ' . hash('sha256', $invoiceId . ':' . $secretKey)
		]);
		if ($response['error']) {
			$this->diagnostic('Invoice network error (cURL ' . (int)$response['error'] . ')', $orderId, $invoiceId);
			return null;
		}
		if ($response['status'] !== 200) {
			$this->diagnostic('Invoice HTTP ' . (int)$response['status'], $orderId, $invoiceId);
			return null;
		}
		$body = json_decode($response['body']);
		if (json_last_error() !== JSON_ERROR_NONE || !is_object($body) || !isset($body->data) || !is_object($body->data)) {
			$this->diagnostic('Invalid invoice JSON or data object', $orderId, $invoiceId);
			return null;
		}
		$data = (array)$body->data;
		// Preserve JSON numeric lexemes before PHP can round a fractional amount to float.
		$exactBody = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|(-?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/s', static function ($match) {
			return isset($match[1]) ? '"' . $match[1] . '"' : $match[0];
		}, $response['body']);
		if ($exactBody === null) {
			$this->diagnostic('Invoice JSON amount parsing failed', $orderId, $invoiceId);
			return null;
		}
		$exact = json_decode($exactBody);
		if (json_last_error() !== JSON_ERROR_NONE || !is_object($exact) || !isset($exact->data) || !is_object($exact->data)) {
			$this->diagnostic('Invalid exact invoice amount encoding', $orderId, $invoiceId);
			return null;
		}
		if (isset($data['amount']) && (is_int($data['amount']) || is_float($data['amount']))) {
			$data['amount'] = $exact->data->amount;
		}
		if (!isset($data['identifier'], $data['orderIdentifier'], $data['status'], $data['amount'], $data['currency']) ||
			!is_string($data['identifier']) || $data['identifier'] === '' ||
			!((is_int($data['orderIdentifier']) && $data['orderIdentifier'] > 0) || (is_string($data['orderIdentifier']) && preg_match('/^[1-9][0-9]*$/D', $data['orderIdentifier']))) ||
			!is_int($data['status']) || $data['status'] < 0 ||
			!is_string($data['currency']) || !preg_match('/^[a-zA-Z]{3}$/D', $data['currency']) ||
			$this->normalizeAmount($data['amount']) === null) {
			$this->diagnostic('Invalid or missing invoice fields', $orderId, $invoiceId);
			return null;
		}
		return $data;
	}

	private function matchesInvoice(array $invoice, array $attempt, int $orderId, string $invoiceId): bool {
		$reason = '';
		if ($invoice['identifier'] !== $invoiceId || $invoiceId !== $attempt['invoice_id']) {
			$reason = 'Invoice ID mismatch';
		} elseif ((string)$invoice['orderIdentifier'] !== (string)$orderId) {
			$reason = 'Invoice order mismatch';
		} elseif ($this->normalizeAmount($attempt['amount']) === null || $this->normalizeAmount($invoice['amount']) !== $this->normalizeAmount($attempt['amount'])) {
			$reason = 'Invoice amount mismatch';
		} elseif (strtoupper($invoice['currency']) !== strtoupper($attempt['currency'])) {
			$reason = 'Invoice currency mismatch';
		}
		if ($reason) {
			$this->diagnostic($reason, $orderId, $invoiceId);
			return false;
		}
		return true;
	}

	/** Exact decimal comparison: strip insignificant zeros, never round API amounts. */
	private function normalizeAmount($amount): ?string {
		if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
			return null;
		}
		if (is_float($amount)) {
			if (!is_finite($amount)) return null;
			$amount = json_encode($amount, JSON_PRESERVE_ZERO_FRACTION);
		}
		$amount = (string)$amount;
		if (strlen($amount) > 256 || !preg_match('/^([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/D', $amount, $parts)) {
			return null;
		}
		$exponent = (int)($parts[3] ?? 0);
		if (abs($exponent) > 256) return null;
		$digits = $parts[1] . ($parts[2] ?? '');
		$point = strlen($parts[1]) + $exponent;
		if ($point < 0) {
			$digits = str_repeat('0', -$point) . $digits;
			$point = 0;
		}
		if ($point > strlen($digits)) $digits .= str_repeat('0', $point - strlen($digits));
		$integer = ltrim(substr($digits, 0, $point), '0');
		$fraction = rtrim(substr($digits, $point), '0');
		return ($integer === '' ? '0' : $integer) . ($fraction === '' ? '' : '.' . $fraction);
	}
	private function generate_signature($order) {
		$sign_str = ['id' => $order['id'], 'amount' => $order['amount'], 'currency' => $order['currency']];
		ksort($sign_str, SORT_STRING);
		$sign_data = array_values($sign_str);
		array_push($sign_data, $this->config->get('payment_payop_secret_key'));
		return hash('sha256', implode(':', $sign_data));
	}

	private function makeRequest(array $request): string {
		$this->invoiceError = '';
		if (empty($request['publicKey']) || !$this->config->get('payment_payop_secret_key')) {
			$this->diagnostic('Missing API settings', (int)$request['order']['id']);
			return '';
		}
		$response = $this->requestApi('POST', 'https://api.payop.com/v1/invoices/create', ['Accept: application/json', 'Content-Type: application/json'], json_encode($request));
		if ($response['error'] || $response['status'] < 200 || $response['status'] >= 300 || empty($response['headers']['identifier'])) {
			// Translate the known minimum-amount rejection without exposing raw API errors.
			$body = json_decode($response['body'], true);
			$message = is_array($body) ? ($body['message'] ?? null) : null;
			if (!$response['error'] && $response['status'] === 422 && is_string($message) &&
				preg_match('/^The specified amount [0-9]{1,20}(?:\.[0-9]{1,8})? is less than the permissible minimum for the selected currency - ([0-9]{1,20}(?:\.[0-9]{1,8})?) [A-Za-z][A-Za-z ]*$/D', $message, $matches)) {
				$this->invoiceError = sprintf($this->language->get('error_invoice_minimum'), $matches[1], $request['order']['currency']);
			}
			$this->diagnostic('Invoice creation failed HTTP ' . (int)$response['status'] . ' cURL ' . (int)$response['error'], (int)$request['order']['id']);
			return '';
		}
		$id = $response['headers']['identifier'];
		return strlen($id) <= 255 ? $id : '';
	}

	/** Shared bounded, TLS-verified transport. Redirects are not followed with credentials. */
	protected function requestApi(string $method, string $url, array $headers, ?string $body = null): array {
		$curl = curl_init();
		$responseHeaders = [];
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HEADERFUNCTION => static function ($handle, $line) use (&$responseHeaders) {
				if (strpos($line, ':') !== false) {
					[$name, $value] = explode(':', $line, 2);
					$responseHeaders[strtolower(trim($name))] = trim($value);
				}
				return strlen($line);
			}
		]);
		if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		$response = curl_exec($curl);
		$result = ['status' => (int)curl_getinfo($curl, CURLINFO_HTTP_CODE), 'error' => curl_errno($curl), 'body' => $response === false ? '' : $response, 'headers' => $responseHeaders];
		curl_close($curl);
		return $result;
	}
}
