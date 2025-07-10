<?php
namespace Opencart\Catalog\Controller\Extension\Payop\Payment;
/**
 * Class Payop
 *
 */
class Payop extends \Opencart\System\Engine\Controller {
	private $curl;

	/**
	 * Display Payop payment option in the checkout
	 */
	public function index() {
		$this->load->language('extension/payop/payment/payop');
		$widget_lang = $this->language->get('code') == 'ru' ? 'ru-RU' : 'en-US';

		return $this->load->view('extension/payop/payment/payop', [
			'button_pay' => $this->language->get('button_pay'),
			'payop_url' => $this->url->link('extension/payop/payment/payop.pay')
		]);
	}

	/**
	 * Handle the payment request to Payop
	 */
	public function pay() {
		$this->response->addHeader('Content-Type: application/json');
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$order_products = $this->model_checkout_order->getProducts($this->session->data['order_id']);
		
		// Prepare order items
		$payop_order_items = array_map(function ($product) {
			return [
				'id' => $product['order_product_id'],
				'name' => trim($product['name'] . ' ' . $product['model']),
				'price' => $product['price']
			];
		}, $order_products);

		// Prepare the request data
		$request = $this->preparePaymentRequest($order_info, $payop_order_items);

		// Generate signature
		$request['signature'] = $this->generate_signature($request['order']);

		// Update order history
		$this->model_checkout_order->addHistory($order_info['order_id'], $this->config->get('payment_payop_order_status_wait'));

		// Send request to Payop API and get invoice ID
		$invoiceId = $this->makeRequest($request);
		if ($invoiceId) {
			$redirectUrl = "https://checkout.payop.com/{$this->language->get('code')}/payment/invoice-preprocessing/{$invoiceId}";
			$this->response->setOutput(json_encode($redirectUrl));
		} else {
			$this->log->write('Payop Invoice creation failed');
		}
	}

	/**
	 * Handle callback from Payop after payment processing
	 */
	public function callback() {
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$this->log->write('Invalid request method for Payop callback.');
			return;
		}

		$callback = json_decode(file_get_contents('php://input'), true);
		$this->log->write('Callback: ' . print_r($callback, true));
		if ($callback && isset($callback['invoice'])) {
			if ($this->callback_check($callback) === 'valid') {
				$this->processCallback($callback);
			} else {
				$this->log->write('Error callback: ' . $this->callback_check($callback));
			}
		} else {
			$this->log->write('Error. Callback is not an object or missing invoice.');
		}
	}

	/**
	 * Prepare payment request data
	 * @param $order_info
	 * @param $payop_order_items
	 * @return array
	 */
	private function preparePaymentRequest($order_info, $payop_order_items) {
		return [
			'publicKey' => $this->config->get('payment_payop_public_id'),
			'order' => [
				'id' => $order_info['order_id'],
				'amount' => number_format($this->currency->format($order_info['total'], $order_info['currency_code'], 0, false), 4, ".", ""),
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

	/**
	 * Process callback and update order status
	 * @param $callback
	 */
	private function processCallback($callback) {
		$this->load->model('checkout/order');
		if ($callback['transaction']['state'] === 2) {
			$this->model_checkout_order->addHistory($callback['transaction']['order']['id'], $this->config->get('payment_payop_order_status_success'));
		} elseif (in_array($callback['transaction']['state'], [3, 5])) {
			$this->model_checkout_order->addHistory($callback['transaction']['order']['id'], $this->config->get('payment_payop_order_status_error'));
		}
	}

	/**
	 * Check the callback validity
	 * @param $callback
	 * @return string
	 */
	private function callback_check($callback) {
		$invoiceId = !empty($callback['invoice']['id']) ? $callback['invoice']['id'] : null;
		$txid = !empty($callback['invoice']['txid']) ? $callback['invoice']['txid'] : null;
		$orderId = !empty($callback['transaction']['order']['id']) ? $callback['transaction']['order']['id'] : null;
		$state = !empty($callback['transaction']['state']) ? $callback['transaction']['state'] : null;

		if (!$invoiceId) return 'Empty invoice id';
		if (!$txid) return 'Empty transaction id';
		if (!$orderId) return 'Empty order id';
		if (!(1 <= $state && $state <= 5)) return 'State is not valid';
		
		return 'valid';
	}

	/**
	 * Generate signature for API request
	 * @param $order
	 * @return string
	 */
	private function generate_signature($order) {
		$sign_str = ['id' => $order['id'], 'amount' => $order['amount'], 'currency' => $order['currency']];
		ksort($sign_str, SORT_STRING);
		$sign_data = array_values($sign_str);
		array_push($sign_data, $this->config->get('payment_payop_secret_key'));
		return hash('sha256', implode(':', $sign_data));
	}

	/**
	 * Send the request to Payop API
	 * @param $request
	 * @return string
	 */
	private function makeRequest($request) {
		$request = json_encode($request);
		if (!$this->curl) {
			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_URL, 'https://api.payop.com/v1/invoices/create');
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_HEADER, true);
		}
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $request);

		$response = curl_exec($this->curl);
		$header_size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
		curl_close($this->curl);

		$headers = substr($response, 0, $header_size);
		$headers = explode("\r\n", $headers);
		$invoice_identifier = preg_grep("/^identifier/", $headers);
		$invoice_identifier = implode(',', $invoice_identifier);
		$invoice_identifier = substr($invoice_identifier, strrpos($invoice_identifier, ':') + 2);
		
		return $invoice_identifier ?: '';
	}
}
