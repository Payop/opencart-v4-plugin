<?php
namespace Opencart\Admin\Model\Extension\Payop\Payment;
/**
 * Class Payop
 *
 */
class Payop extends \Opencart\System\Engine\Model {
	/** JWT is used exclusively by the admin method catalogue, never by invoice requests. */
	public function getPaymentMethods(string $projectId, string $jwt): array {
		if (trim($projectId) === '' || trim($jwt) === '' || preg_match('/[\r\n]/', $jwt)) {
			return ['methods' => [], 'error' => 'error_methods_credentials'];
		}
		$response = $this->requestMethods('https://api.payop.com/v1/instrument-settings/payment-methods/available-for-application/' . rawurlencode(trim($projectId)), trim($jwt));
		if ($response['error']) return ['methods' => [], 'error' => 'error_methods_network'];
		if (in_array($response['status'], [401, 403], true)) return ['methods' => [], 'error' => 'error_methods_auth'];
		if ($response['status'] !== 200) return ['methods' => [], 'error' => 'error_methods_response'];
		$body = json_decode($response['body'], true);
		if (json_last_error() !== JSON_ERROR_NONE || !is_array($body) || !isset($body['data']) || !is_array($body['data'])) {
			return ['methods' => [], 'error' => 'error_methods_response'];
		}
		$methods = [];
		foreach ($body['data'] as $method) {
			if (!is_array($method) || !isset($method['identifier']) || (!is_string($method['identifier']) && !is_int($method['identifier']))) continue;
			$id = (string)$method['identifier'];
			if (!preg_match('/^[1-9][0-9]*$/D', $id)) continue;
			$methods[$id] = ['identifier' => $id, 'title' => is_string($method['title'] ?? null) ? $method['title'] : $id];
		}
		if ($body['data'] && !$methods) return ['methods' => [], 'error' => 'error_methods_response'];
		return ['methods' => array_values($methods), 'error' => ''];
	}

	protected function requestMethods(string $url, string $jwt): array {
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer ' . $jwt],
			CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false
		]);
		$body = curl_exec($curl);
		$result = ['status' => (int)curl_getinfo($curl, CURLINFO_HTTP_CODE), 'error' => curl_errno($curl), 'body' => $body === false ? '' : $body];
		curl_close($curl);
		return $result;
	}

	public function install() {
		$defaults = [
			'payment_payop_sort_order' => 0,
			'payment_payop_order_status_wait' => $this->config->get('config_order_status_id'),
			'payment_payop_order_status_success' => $this->config->get('config_order_status_id'),
			'payment_payop_order_status_error' => $this->config->get('config_order_status_id')
		];

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('payment_payop', $defaults);
	}

	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_payop');
	}
}
