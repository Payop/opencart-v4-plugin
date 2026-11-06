<?php
namespace Opencart\Catalog\Model\Extension\Payop\Payment;
/**
 * Class Payop
 *
 */
class Payop extends \Opencart\System\Engine\Model {

	private bool $storageReady = false;

	/** Additive migration for existing installations; no reinstall or setting reset required. */
	private function ensureStorage(): void {
		if ($this->storageReady) return;
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payop_invoice` (
			`invoice_id` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			`order_id` int unsigned NOT NULL,
			`amount` varchar(64) NOT NULL,
			`currency` varchar(3) NOT NULL,
			`payment_state` tinyint unsigned NOT NULL DEFAULT 0,
			`date_added` datetime NOT NULL,
			PRIMARY KEY (`invoice_id`), KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$this->storageReady = true;
	}

	public function lockOrder(int $orderId): bool {
		$this->ensureStorage();
		$result = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($this->lockName($orderId)) . "', 5) AS acquired");
		return (int)($result->row['acquired'] ?? 0) === 1;
	}

	public function unlockOrder(int $orderId): void {
		$this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($this->lockName($orderId)) . "')");
	}

	private function lockName(int $orderId): string {
		return 'payop:' . sha1(DB_PREFIX . ':' . $orderId);
	}

	public function saveInvoice(int $orderId, string $invoiceId, string $amount, string $currency): void {
		$this->ensureStorage();
		$this->db->query("INSERT INTO `" . DB_PREFIX . "payop_invoice` SET `invoice_id` = '" . $this->db->escape($invoiceId) . "', `order_id` = '" . $orderId . "', `amount` = '" . $this->db->escape($amount) . "', `currency` = '" . $this->db->escape(strtoupper($currency)) . "', `date_added` = NOW()");
	}

	public function getInvoice(int $orderId, string $invoiceId): array {
		$this->ensureStorage();
		return $this->db->query("SELECT * FROM `" . DB_PREFIX . "payop_invoice` WHERE `order_id` = '" . $orderId . "' AND `invoice_id` = '" . $this->db->escape($invoiceId) . "'")->row;
	}

	public function hasProcessedPayment(int $orderId): bool {
		$this->ensureStorage();
		return (bool)$this->db->query("SELECT `invoice_id` FROM `" . DB_PREFIX . "payop_invoice` WHERE `order_id` = '" . $orderId . "' AND `payment_state` > 0 LIMIT 1")->num_rows;
	}

	public function isOrderPaid(int $orderId): bool {
		$this->ensureStorage();
		return (bool)$this->db->query("SELECT `invoice_id` FROM `" . DB_PREFIX . "payop_invoice` WHERE `order_id` = '" . $orderId . "' AND `payment_state` = 2 LIMIT 1")->num_rows;
	}

	public function markProcessing(int $orderId, string $invoiceId): void {
		$this->setPaymentState($orderId, $invoiceId, 1);
	}

	public function markPaid(int $orderId, string $invoiceId): void {
		$this->setPaymentState($orderId, $invoiceId, 2);
	}

	private function setPaymentState(int $orderId, string $invoiceId, int $state): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "payop_invoice` SET `payment_state` = '" . $state . "' WHERE `order_id` = '" . $orderId . "' AND `invoice_id` = '" . $this->db->escape($invoiceId) . "' AND `payment_state` = '" . ($state - 1) . "'");
		if ($this->db->countAffected() !== 1) {
			throw new \RuntimeException('Payment marker was not updated');
		}
	}

	/** Stable checkout codes stay unchanged when a merchant edits/reorders other buttons. */
	public function getCheckoutOptions(): array {
		$options = ['payop' => [
			'code' => 'payop.payop', 'title' => $this->language->get('heading_title'),
			'description' => $this->language->get('text_description'),
			'integration_type' => 'hosted_page', 'payment_method' => ''
		]];
		$languageId = (int)$this->config->get('config_language_id');
		$buttons = $this->config->get('payment_payop_buttons');
		foreach (is_array($buttons) ? $buttons : [] as $id => $button) {
			if (!preg_match('/^button_[a-f0-9]{16}$/D', (string)$id) || !is_array($button) || !in_array($button['enabled'] ?? 0, [1, '1', true], true)) continue;
			$type = $button['integration_type'] ?? '';
			$method = $button['payment_method'] ?? '';
			if (!in_array($type, ['hosted_page', 'payment_method'], true) ||
				($type === 'payment_method' && (!is_string($method) || !preg_match('/^[1-9][0-9]*$/D', $method)))) continue;
			$translations = $button['language'] ?? [];
			$translation = is_array($translations) ? ($translations[$languageId] ?? reset($translations)) : [];
			if (!is_array($translation) || !is_string($translation['name'] ?? null) || trim($translation['name']) === '') continue;
			$options[$id] = [
				'code' => 'payop.' . $id, 'title' => $translation['name'],
				'description' => is_string($translation['description'] ?? null) ? $translation['description'] : '',
				'integration_type' => $type, 'payment_method' => $type === 'payment_method' ? $method : '',
				'revolut_fields' => $type === 'payment_method' && in_array($button['revolut_fields'] ?? 0, [1, '1', true], true)
			];
		}
		return $options;
	}

	/** Resolve only current server settings; never use an ID supplied by the shopper. */
	public function getSelectedOption(string $code, array $address = []): ?array {
		$methods = $this->getMethods($address);
		foreach ($this->getCheckoutOptions() as $id => $option) {
			if ($option['code'] === $code && isset($methods['option'][$id])) return $option;
		}
		return null;
	}

	/**
	 * Get Methods
	 *
	 * @param array<string, mixed> $address array of data
	 *
	 * @return array<string, mixed>
	 */
	public function getMethods(array $address = []): array {
		$this->load->language('extension/payop/payment/payop');

		if (!$this->config->get('payment_payop_status') || $this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('config_checkout_payment_address')) {
			$status = true;
		} elseif (!$this->config->get('payment_payop_geo_zone_id')) {
			$status = true;
		} else {
			// Geo Zone
			$this->load->model('localisation/geo_zone');

			$results = $this->model_localisation_geo_zone->getGeoZone((int)$this->config->get('payment_payop_geo_zone_id'), (int)$address['country_id'], (int)$address['zone_id']);

			if ($results) {
				$status = true;
			} else {
				$status = false;
			}
		}

		$method_data = [];

		if ($status) {
			$option_data = [];
			foreach ($this->getCheckoutOptions() as $id => $option) {
				$name = $option['title'] . ($id !== 'payop' && $option['description'] !== '' ? ' — ' . $option['description'] : '');
				$option_data[$id] = ['code' => $option['code'], 'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8')];
			}

			$method_data = [
				'code'       => 'payop',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_payop_sort_order')
			];
		}

		return $method_data;
	}
}
