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

		if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_payop', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension',
				'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
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

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payop/payment/payop')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$required_fields = ['public_id', 'secret_key'];

		foreach ($required_fields as $f) {
			if (empty($this->request->post['payment_payop_' . $f])) {
				$this->error[$f] = $this->language->get('error_' . $f);
			}
		}

		return empty($this->error);
	}

	public function install() {
		$this->load->model('extension/payop/payment/payop');
		if (method_exists($this->model_extension_payment_payop, 'install')) {
			$this->model_extension_payment_payop->install();
		}
	}

	public function uninstall() {
		$this->load->model('extension/payop/payment/payop');
		if (method_exists($this->model_extension_payment_payop, 'uninstall')) {
			$this->model_extension_payment_payop->uninstall();
		}
	}
}
