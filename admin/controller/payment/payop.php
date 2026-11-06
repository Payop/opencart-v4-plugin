<?php

namespace Opencart\Admin\Controller\Extension\Payop\Payment;

/**
 * Class Payop
 *
 */
class Payop extends \Opencart\System\Engine\Controller {
	protected $error = [];

	public function index() {
		$this->load->language('extension/payop/payment/payop');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate($languages)) {
			$settings = $this->model_setting_setting->getSetting('payment_payop');
			$this->model_setting_setting->editSetting('payment_payop', array_replace($settings, $this->request->post));
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension',
				'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
			return;
		}

		$data['error_warning'] = $this->error['warning'] ?? '';
		$data['error_public_id'] = $this->error['public_id'] ?? '';
		$data['error_secret_key'] = $this->error['secret_key'] ?? '';

		$data['breadcrumbs'] = [
			[
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
			],
			[
				'text' => $this->language->get('text_extension'),
				'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
			],
			[
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/payop/payment/payop', 'user_token=' . $this->session->data['user_token'], true)
			]
		];

		$data['action'] = $this->url->link('extension/payop/payment/payop',
			'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension',
			'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		$fields = [
			'payment_payop_project_id',
			'payment_payop_jwt_token',
			'payment_payop_buttons',
			'payment_payop_public_id',
			'payment_payop_secret_key',
			'payment_payop_order_status_wait',
			'payment_payop_order_status_success',
			'payment_payop_order_status_error',
			'payment_payop_status',
			'payment_payop_geo_zone_id',
			'payment_payop_sort_order'
		];

		foreach ($fields as $f) {
			$data[$f] = $this->request->post[$f] ?? $this->config->get($f);
		}

		$data['languages'] = $languages;
		$data['error_buttons'] = $this->error['buttons'] ?? '';
		$data['payment_payop_buttons'] = is_array($data['payment_payop_buttons']) ? $data['payment_payop_buttons'] : [];
		$data['payment_buttons_json'] = json_encode((object)$data['payment_payop_buttons'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
		$data['methods_url'] = $this->url->link('extension/payop/payment/payop.methods', 'user_token=' . $this->session->data['user_token']);
		$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE;
		$data['languages_json'] = json_encode(array_values($languages), $jsonFlags);
		$labels = [];
		foreach (['entry_revolut_fields', 'entry_status', 'entry_button_name', 'entry_button_description', 'entry_integration_type', 'entry_payment_method', 'text_enabled', 'text_disabled', 'text_hosted_page', 'text_payment_method', 'text_saved_method', 'text_select_method', 'button_remove', 'text_methods_loaded', 'error_methods_network'] as $key) $labels[$key] = $this->language->get($key);
		$data['button_labels_json'] = json_encode($labels, $jsonFlags);
		$data['methods_url_json'] = json_encode(html_entity_decode($data['methods_url'], ENT_QUOTES, 'UTF-8'), $jsonFlags);

		$data['ipn_url'] = HTTP_CATALOG . 'index.php?route=extension/payop/payment/payop.callback';

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payop/payment/payop', $data));
	}

	protected function validate(array $languages) {
		if (!$this->user->hasPermission('modify', 'extension/payop/payment/payop')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$required_fields = ['public_id', 'secret_key'];

		foreach ($required_fields as $f) {
			if (!is_string($this->request->post['payment_payop_' . $f] ?? null) || trim($this->request->post['payment_payop_' . $f]) === '') {
				$this->error[$f] = $this->language->get('error_' . $f);
			}
		}

		$this->validateButtons($languages);
		return empty($this->error);
	}

	/** This endpoint only reads the API. Failed refreshes never write module settings. */
	public function methods() {
		$this->load->language('extension/payop/payment/payop');
		$this->response->addHeader('Content-Type: application/json');
		if (($this->request->server['REQUEST_METHOD'] ?? '') !== 'POST' || !$this->user->hasPermission('modify', 'extension/payop/payment/payop')) {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			$this->response->setOutput(json_encode(['error' => $this->language->get('error_permission')]));
			return;
		}
		$result = $this->loadMethods();
		$this->response->setOutput(json_encode(['methods' => $result['methods'], 'error' => $result['error'] ? $this->language->get($result['error']) : '']));
	}

	private function loadMethods(): array {
		$project = $this->request->post['payment_payop_project_id'] ?? '';
		$jwt = $this->request->post['payment_payop_jwt_token'] ?? '';
		if (!is_string($project) || !is_string($jwt)) return ['methods' => [], 'error' => 'error_methods_credentials'];
		$this->load->model('extension/payop/payment/payop');
		try {
			return $this->model_extension_payop_payment_payop->getPaymentMethods($project, $jwt);
		} catch (\Throwable $e) {
			return ['methods' => [], 'error' => 'error_methods_network'];
		}
	}

	private function validateButtons(array $languages): void {
		$post = &$this->request->post;
		// One JSON form field avoids PHP max_input_vars truncating a long list of buttons.
		$json = $post['payment_payop_buttons_json'] ?? json_encode((object)($this->config->get('payment_payop_buttons') ?: []));
		// OpenCart Request::clean HTML-encodes POST strings, including JSON quotes.
		if (isset($post['payment_payop_buttons_json']) && is_string($json)) $json = html_entity_decode($json, ENT_COMPAT, 'UTF-8');
		$decoded = is_string($json) ? json_decode($json) : null;
		$buttons = $decoded instanceof \stdClass ? json_decode($json, true) : null;
		unset($post['payment_payop_buttons_json']);
		if (!is_array($buttons)) {
			$post['payment_payop_buttons'] = $this->config->get('payment_payop_buttons') ?: [];
			$this->error['buttons'] = $this->language->get('error_buttons');
			return;
		}
		$post['payment_payop_buttons'] = $buttons;
		$previous = $this->config->get('payment_payop_buttons');
		$previous = is_array($previous) ? $previous : [];
		$newMethods = [];
		$normalized = [];
		foreach ($buttons as $id => $button) {
			if (!preg_match('/^button_[a-f0-9]{16}$/D', (string)$id) || !is_array($button) ||
				!in_array($button['enabled'] ?? null, [0, 1, '0', '1'], true) ||
				!in_array($button['integration_type'] ?? null, ['hosted_page', 'payment_method'], true) || !is_array($button['language'] ?? null)) {
				$this->error['buttons'] = $this->language->get('error_buttons');
				continue;
			}
			$type = $button['integration_type'];
			$method = $button['payment_method'] ?? '';
			if ($type === 'payment_method' && (!is_string($method) || !preg_match('/^[1-9][0-9]*$/D', $method))) {
				$this->error['buttons'] = $this->language->get('error_button_method');
				continue;
			}
			$row = ['enabled' => (int)$button['enabled'], 'integration_type' => $type, 'payment_method' => $type === 'payment_method' ? $method : '', 'language' => [], 'revolut_fields' => $type === 'payment_method' && in_array($button['revolut_fields'] ?? 0, [1, '1'], true) ? 1 : 0];
			foreach ($languages as $language) {
				$languageId = (int)$language['language_id'];
				$text = $button['language'][$languageId] ?? [];
				$name = $text['name'] ?? '';
				$description = $text['description'] ?? '';
				if (!is_string($name) || !is_string($description) || ($row['enabled'] && trim($name) === '')) {
					$this->error['buttons'] = $this->language->get('error_button_name');
					continue;
				}
				$row['language'][$languageId] = ['name' => trim($name), 'description' => trim($description)];
			}
			$normalized[$id] = $row;
			if ($type === 'payment_method' &&
				(($previous[$id]['payment_method'] ?? '') !== $method || ($previous[$id]['integration_type'] ?? '') !== $type ||
				($post['payment_payop_project_id'] ?? '') !== ($this->config->get('payment_payop_project_id') ?? ''))) {
				$newMethods[$method] = true;
			}
		}
		if (empty($this->error) && $newMethods) {
			$result = $this->loadMethods();
			if ($result['error']) {
				$this->error['buttons'] = $this->language->get($result['error']);
			} else {
				$available = array_column($result['methods'], 'identifier');
				foreach (array_keys($newMethods) as $id) {
					if (!in_array((string)$id, $available, true)) $this->error['buttons'] = $this->language->get('error_button_method');
				}
			}
		}
		if (empty($this->error)) $post['payment_payop_buttons'] = $normalized;
	}

	public function install() {
		$this->load->model('extension/payop/payment/payop');
		if (method_exists($this->model_extension_payop_payment_payop, 'install')) {
			$this->model_extension_payop_payment_payop->install();
		}
	}

	public function uninstall() {
		$this->load->model('extension/payop/payment/payop');
		if (method_exists($this->model_extension_payop_payment_payop, 'uninstall')) {
			$this->model_extension_payop_payment_payop->uninstall();
		}
	}
}
