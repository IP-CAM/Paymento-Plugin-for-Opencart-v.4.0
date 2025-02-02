<?php

namespace Opencart\Catalog\Model\Extension\PaymentoGateway\Payment;

class Paymento extends \Opencart\System\Engine\Model {

    public function getMethods(array $address): array {
		$this->load->language('extension/paymento_gateway/payment/paymento');

		$address['country_id'] = $address['country_id'] ?? 0;
		$address['zone_id'] = $address['zone_id'] ?? 0;
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_paymento_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('payment_paymento_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		if ($status) {
			return array(
				'code'       => 'paymento',
				'name'       => $this->config->get('payment_paymento_title'),
				'option'     => array('paymento' => array('code' => 'paymento.paymento', 'name' => $this->config->get('payment_paymento_title'))),
				'error'      => '',
				'sort_order' => $this->config->get('payment_paymento_sort_order'),
			);
		}

		return array();
	}

    public function getMethod(array $address): array {
		$this->load->language('extension/paymento_gateway/payment/paymento');

		$address['country_id'] = $address['country_id'] ?? 0;
		$address['zone_id'] = $address['zone_id'] ?? 0;
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_paymento_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

		if ($this->cart->hasSubscription()) {
			$status = false;
		} elseif (!$this->config->get('payment_paymento_geo_zone_id')) {
			$status = true;
		} elseif ($query->num_rows) {
			$status = true;
		} else {
			$status = false;
		}

		if ($status) {
			return array(
				'code'       => 'paymento',
				'title'      => $this->config->get('payment_paymento_title'),
				'sort_order' => $this->config->get('payment_paymento_sort_order')
			);
		}

		return array();
	}
}