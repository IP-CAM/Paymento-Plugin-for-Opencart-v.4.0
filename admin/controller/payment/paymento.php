<?php

namespace Opencart\Admin\Controller\Extension\PaymentoGateway\Payment;

class Paymento extends \Opencart\System\Engine\Controller {

	private $error = array();

	public function index() {
		$this->load->language('extension/paymento_gateway/payment/paymento');

		$this->document->setTitle($this->language->get('heading_title'));

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$json = array();
			if (!$this->request->post) {
				parse_str(file_get_contents('php://input'), $x);
				$this->request->post = $this->request->clean($x);
			}
			if ($this->validate()) {
				$this->load->model('setting/setting');
				$this->model_setting_setting->editSetting('payment_paymento', $this->request->post);

				// Set Endpoint URL when API key is provided
				if (!empty($this->request->post['payment_paymento_apikey'])) {
					$this->setEndpointUrl($this->request->post['payment_paymento_apikey']);
				}

				$json['success'] = $this->language->get('text_success');
			} else {
				$json['error'] = reset($this->error);
			}
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$data['heading_title'] = $this->language->get('heading_title');
		
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');

		$data['entry_title'] = $this->language->get('entry_title');
		$data['entry_apikey'] = $this->language->get('entry_apikey');
		$data['entry_risk'] = $this->language->get('entry_risk');
		$data['text_risk_0'] = $this->language->get('text_risk_0');
		$data['text_risk_1'] = $this->language->get('text_risk_1');
		$data['text_risk_2'] = $this->language->get('text_risk_2');
		$data['entry_debug'] = $this->language->get('entry_debug');
		$data['entry_completed_status'] = $this->language->get('entry_completed_status');
		$data['entry_failed_status'] = $this->language->get('entry_failed_status');
		$data['entry_pending_status'] = $this->language->get('entry_pending_status');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');

		$data['help_debug'] = $this->language->get('help_debug');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['tab_general'] = $this->language->get('tab_general');
		$data['tab_order_status'] = $this->language->get('tab_order_status');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['title'])) {
			$data['error_title'] = $this->error['title'];
		} else {
			$data['error_title'] = '';
		}
		if (isset($this->error['apikey'])) {
			$data['error_apikey'] = $this->error['apikey'];
		} else {
			$data['error_apikey'] = '';
		}
		if (isset($this->error['risk'])) {
			$data['error_risk'] = $this->error['risk'];
		} else {
			$data['error_risk'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/paymento_gateway/payment/paymento', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['save'] = $this->url->link('extension/paymento_gateway/payment/paymento', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		if (isset($this->request->post['payment_paymento_title'])) {
			$data['payment_paymento_title'] = $this->request->post['payment_paymento_title'];
		} else {
			$data['payment_paymento_title'] = $this->config->get('payment_paymento_title');
		}
		if (isset($this->request->post['payment_paymento_apikey'])) {
			$data['payment_paymento_apikey'] = $this->request->post['payment_paymento_apikey'];
		} else {
			$data['payment_paymento_apikey'] = $this->config->get('payment_paymento_apikey');
		}
		if (isset($this->request->post['payment_paymento_risk'])) {
			$data['payment_paymento_risk'] = $this->request->post['payment_paymento_risk'];
		} else {
			$data['payment_paymento_risk'] = $this->config->get('payment_paymento_risk');
		}

		if (isset($this->request->post['payment_paymento_debug'])) {
			$data['payment_paymento_debug'] = $this->request->post['payment_paymento_debug'];
		} else {
			$data['payment_paymento_debug'] = $this->config->get('payment_paymento_debug');
		}

		if (isset($this->request->post['payment_paymento_completed_status_id'])) {
			$data['payment_paymento_completed_status_id'] = $this->request->post['payment_paymento_completed_status_id'];
		} else {
			$data['payment_paymento_completed_status_id'] = $this->config->get('payment_paymento_completed_status_id');
		}

		if (isset($this->request->post['payment_paymento_failed_status_id'])) {
			$data['payment_paymento_failed_status_id'] = $this->request->post['payment_paymento_failed_status_id'];
		} else {
			$data['payment_paymento_failed_status_id'] = $this->config->get('payment_paymento_failed_status_id');
		}

		if (isset($this->request->post['payment_paymento_pending_status_id'])) {
			$data['payment_paymento_pending_status_id'] = $this->request->post['payment_paymento_pending_status_id'];
		} else {
			$data['payment_paymento_pending_status_id'] = $this->config->get('payment_paymento_pending_status_id');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_paymento_geo_zone_id'])) {
			$data['payment_paymento_geo_zone_id'] = $this->request->post['payment_paymento_geo_zone_id'];
		} else {
			$data['payment_paymento_geo_zone_id'] = $this->config->get('payment_paymento_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_paymento_status'])) {
			$data['payment_paymento_status'] = $this->request->post['payment_paymento_status'];
		} else {
			$data['payment_paymento_status'] = $this->config->get('payment_paymento_status');
		}

		if (isset($this->request->post['payment_paymento_sort_order'])) {
			$data['payment_paymento_sort_order'] = $this->request->post['payment_paymento_sort_order'];
		} else {
			$data['payment_paymento_sort_order'] = $this->config->get('payment_paymento_sort_order');
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/paymento_gateway/payment/paymento', $data));
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/paymento_gateway/payment/paymento')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (empty($this->request->post['payment_paymento_title'])) {
			$this->error['title'] = $this->language->get('error_title');
		}
		if (empty($this->request->post['payment_paymento_apikey'])) {
			$this->error['apikey'] = $this->language->get('error_apikey');
		}
		if (!isset($this->request->post['payment_paymento_risk'])) {
			$this->error['risk'] = $this->language->get('error_risk');
		}

		return !$this->error;
	}

	private function setEndpointUrl($apiKey) {
		$endpointUrl = 'https://api.paymento.io/payment/settings';
		$callbackUrl = HTTP_CATALOG . 'index.php?route=extension/paymento_gateway/payment/paymento&shf_action=paymento_callback';
	
		$data = json_encode([
			'apiEndpointPath' => $callbackUrl,
			'httpMethod' => 1 // HTTP POST
		]);
	
		$ch = curl_init($endpointUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Accept: text/plain',
			'Api-Key: ' . $apiKey
		));
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	
		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			error_log('Curl error: ' . curl_error($ch));
		}
		curl_close($ch);
	
		return $response;
	}
}
