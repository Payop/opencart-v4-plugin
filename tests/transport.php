<?php
/** Exercise the production transport with a cURL test double, without network access. */
namespace Opencart\System\Engine {
	class Controller {}
	class Model {}
}
namespace Opencart\Catalog\Controller\Extension\Payop\Payment {
	function curl_init() { return (object)['options' => []]; }
	function curl_setopt_array($handle, $options) { $handle->options = $options; $GLOBALS['curl_handle'] = $handle; return true; }
	function curl_setopt($handle, $key, $value) { $handle->options[$key] = $value; return true; }
	function curl_exec($handle) {
		$header = $handle->options[CURLOPT_HEADERFUNCTION];
		$header($handle, "HTTP/1.1 200 OK\r\n");
		$header($handle, "Identifier: invoice-1\r\n");
		return $GLOBALS['curl_error'] ? false : '{"data":{}}';
	}
	function curl_getinfo($handle, $key) { return $GLOBALS['curl_error'] ? 0 : 200; }
	function curl_errno($handle) { return $GLOBALS['curl_error']; }
	function curl_close($handle) {}
}
namespace Opencart\Admin\Model\Extension\Payop\Payment {
	function curl_init() { return \Opencart\Catalog\Controller\Extension\Payop\Payment\curl_init(); }
	function curl_setopt_array($handle, $options) { return \Opencart\Catalog\Controller\Extension\Payop\Payment\curl_setopt_array($handle, $options); }
	function curl_exec($handle) { return $GLOBALS['curl_error'] ? false : '{"data":[]}'; }
	function curl_getinfo($handle, $key) { return $GLOBALS['curl_error'] ? 0 : 200; }
	function curl_errno($handle) { return $GLOBALS['curl_error']; }
	function curl_close($handle) {}
}

namespace {
	require __DIR__ . '/../catalog/controller/payment/payop.php';
	require __DIR__ . '/../admin/model/payment/payop.php';
	class Transport extends \Opencart\Catalog\Controller\Extension\Payop\Payment\Payop {
		public function send($method, $body = null) { return $this->requestApi($method, 'https://api.payop.com/v1/invoices/test', ['Accept: application/json'], $body); }
	}
	function verify($condition, $message) { if (!$condition) throw new RuntimeException($message); }
	$GLOBALS['curl_error'] = 0;
	$t = new Transport(); $response = $t->send('GET'); $options = $GLOBALS['curl_handle']->options;
	verify($options[CURLOPT_SSL_VERIFYPEER] === true && $options[CURLOPT_SSL_VERIFYHOST] === 2, 'TLS verification disabled');
	verify($options[CURLOPT_TIMEOUT] > 0 && $options[CURLOPT_CONNECTTIMEOUT] > 0, 'Unbounded request');
	verify($options[CURLOPT_FOLLOWLOCATION] === false, 'Redirects could leak credentials');
	verify($options[CURLOPT_HTTPHEADER] === ['Accept: application/json'], 'Wrong invoice headers');
	verify($options[CURLOPT_CUSTOMREQUEST] === 'GET' && !isset($options[CURLOPT_POSTFIELDS]), 'GET body or wrong method');
	verify($response === ['status' => 200, 'error' => 0, 'body' => '{"data":{}}', 'headers' => ['identifier' => 'invoice-1']], 'Response/header parsing failed');
	$t->send('POST', '{"order":{}}');
	verify($GLOBALS['curl_handle']->options[CURLOPT_POSTFIELDS] === '{"order":{}}', 'POST body lost');
	$GLOBALS['curl_error'] = 28;
	verify($t->send('GET')['error'] === 28 && $t->send('GET')['body'] === '', 'Timeout not propagated');
	$GLOBALS['curl_error'] = 0;
	$catalogue = new \Opencart\Admin\Model\Extension\Payop\Payment\Payop();
	verify($catalogue->getPaymentMethods('project/id', 'jwt-test')['error'] === '', 'Admin transport failed');
	$options = $GLOBALS['curl_handle']->options;
	verify($options[CURLOPT_URL] === 'https://api.payop.com/v1/instrument-settings/payment-methods/available-for-application/project%2Fid', 'Wrong catalogue URL');
	verify($options[CURLOPT_HTTPHEADER] === ['Accept: application/json', 'Authorization: Bearer jwt-test'], 'Wrong catalogue headers');
	verify($options[CURLOPT_SSL_VERIFYPEER] === true && $options[CURLOPT_SSL_VERIFYHOST] === 2 && $options[CURLOPT_TIMEOUT] === 20 && $options[CURLOPT_CONNECTTIMEOUT] > 0 && $options[CURLOPT_FOLLOWLOCATION] === false, 'Unsafe admin transport');

	echo "PASS invoice and admin transports: TLS, finite timeouts, redirect policy, headers, POST and timeout propagation\n";
}
