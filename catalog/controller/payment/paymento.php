<?php

namespace Opencart\Catalog\Controller\Extension\PaymentoGateway\Payment;

class Paymento extends \Opencart\System\Engine\Controller {

	public function index() {
		// Check if the request is a callback via GET parameters (client redirection)
		if (isset($this->request->get['shf_action'], $this->request->get['order_id'], $this->request->get['shf_key']) && $this->request->get['shf_action'] == 'paymento_callback') {
			return $this->callback(true);
		}
	
		// Check if the request is a callback from Paymento via POST
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			return $this->callback(false);
		}
	
		// If not a callback, proceed with payment request creation
		try {
			$this->load->language('extension/paymento_gateway/payment/paymento');
			$this->load->model('checkout/order');
			
			if (isset($this->session->data['order_id'])) {
				$order_id = $this->session->data['order_id'];
				$order_info = $this->model_checkout_order->getOrder($order_id);
	
				if ($order_info && $order_info['order_status_id'] == $this->config->get('payment_paymento_completed_status_id')) {
					// Log the completion and reset the order ID
					if ($this->config->get('payment_paymento_debug')) {
						$this->log->write('Paymento :: OrderID=' . $order_id . ' is already completed.');
					}
	
					// Clear the cart and session data to start a new order
					$this->cart->clear();
					unset($this->session->data['order_id']);
					unset($this->session->data['payment_address']);
					unset($this->session->data['payment_method']);
					unset($this->session->data['payment_methods']);
					unset($this->session->data['shipping_address']);
					unset($this->session->data['shipping_method']);
					unset($this->session->data['shipping_methods']);
					unset($this->session->data['comment']);
					unset($this->session->data['coupon']);
					unset($this->session->data['reward']);
					unset($this->session->data['voucher']);
					unset($this->session->data['vouchers']);
					unset($this->session->data['totals']);
	
					// Create a new order ID (this would be done during the next checkout process)
					$this->response->redirect($this->url->link('checkout/checkout', '', true));
					return;
				}
			} else {
				// Redirect to checkout if no order ID exists
				$this->response->redirect($this->url->link('checkout/checkout', '', true));
				return;
			}
	
			$order_info = $this->model_checkout_order->getOrder($order_id);
	
			// Log the current order ID for debugging
			if ($this->config->get('payment_paymento_debug')) {
				$this->log->write('Paymento :: Current order ID: ' . $order_id);
			}
	
			if ($order_info) {
				$ch = curl_init('https://api.paymento.io/payment/request');
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
					'fiatAmount' => $order_info['total'],
					'fiatCurrency' => strtoupper($order_info['currency_code']),
					'callBackUrl' => $this->url->link('extension/paymento_gateway/payment/paymento', 'shf_action=paymento_callback&order_id=' . $order_id . '&shf_key=' . $this->session->getId(), true),
					'orderId' => $order_id,
					'riskSpeed' => ($this->config->get('payment_paymento_risk') ? 0 : 1),
				]));
				curl_setopt($ch, CURLOPT_HTTPHEADER, [
					'Api-key: ' . $this->config->get('payment_paymento_apikey'),
					'Content-Type: application/json',
					'Accept: application/json',
				]);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_TIMEOUT, 15);
				$response = curl_exec($ch);
				$error = curl_error($ch);
				$info = curl_getinfo($ch);
				curl_close($ch);
				$result = json_decode($response);
	
				if (isset($result->body) && $result->body) {
					return $this->load->view('extension/paymento_gateway/payment/paymento', [
						'token' => $result->body,
						'button_confirm' => $this->language->get('button_confirm'),
					]);
				} else {
					return $this->language->get('text_error');
				}
			}
		} catch (exception $e) {
			return ($this->language->get('text_error') . ' : ' . $e->getMessage());
		}
	}
	

	public function callback($isGet = false) {
		if ($isGet) {
			// Handle callback from URL query (client redirection)
			$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
			$token = isset($this->request->get['token']) ? $this->request->get['token'] : '';
			$status = isset($this->request->get['status']) ? $this->request->get['status'] : '';
	
			// Log GET data
			if ($this->config->get('payment_paymento_debug')) {
				$this->log->write('Paymento :: GET data: ' . print_r($this->request->get, true));
			}
		} else {
			// Handle callback from Paymento via POST
			$postData = file_get_contents('php://input');
	
			// Log the raw POST data for debugging
			if ($this->config->get('payment_paymento_debug')) {
				$this->log->write('Paymento :: Raw POST data: ' . $postData);
			}
	
			// Clean up the JSON data
			$postData = trim($postData);
			$postData = preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $postData); // Remove BOM if present
	
			// Decode the JSON data
			$data = json_decode($postData, true);
	
			// Check for JSON decoding errors
			if (json_last_error() !== JSON_ERROR_NONE) {
				if ($this->config->get('payment_paymento_debug')) {
					$this->log->write('Paymento :: JSON decode error: ' . json_last_error_msg());
				}
				echo 'Invalid JSON data received!';
				exit;
			}
	
			// Log the decoded data for debugging
			if ($this->config->get('payment_paymento_debug')) {
				$this->log->write('Paymento :: Decoded POST data: ' . print_r($data, true));
			}
	
			// Ensure the necessary fields are present
			if (!isset($data['OrderId']) || !isset($data['Token']) || !isset($data['OrderStatus'])) {
				echo 'Invalid data received!';
				exit;
			}
	
			// Retrieve the order ID and other data
			$order_id = (int)$data['OrderId'];
			$token = $data['Token'];
			$status = $data['OrderStatus'];
		}
	
		// Load the order model and get order details
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_id);
	
		if ($order_info) {
			if ($order_info['order_status_id'] == $this->config->get('payment_paymento_completed_status_id')) {
				header('Location: ' . $this->url->link('checkout/success'));
				exit;
			}
	
			$ok = false;
			$order_status_id = $this->config->get('config_order_status_id');
			$msg = "status: $status, orderId: $order_id, token: $token";
	
			if ($this->config->get('payment_paymento_debug')) {
				$this->log->write('Paymento :: OrderID=' . $order_id . ' :: Callback data=' . print_r(['status' => $status, 'orderId' => $order_id, 'token' => $token], true));
			}
	
			if ($this->config->get('payment_paymento_risk') == 2 && $status == 3) {
				$ok = true;
				$order_status_id = $this->config->get('payment_paymento_pending_status_id');
				if ($this->config->get('payment_paymento_debug')) {
					$this->log->write('Paymento :: OrderID=' . $order_id . ' :: pay pending confirmation :: status=' . $status);
				}
			} elseif ($status == 5) {
				$order_status_id = $this->config->get('payment_paymento_failed_status_id');
				if ($this->config->get('payment_paymento_debug')) {
					$this->log->write('Paymento :: OrderID=' . $order_id . ' :: pay cancelled by user :: status=' . $status);
				}
			} elseif ($status == 7) { // Assuming status 7 means payment completed
				try {
					$ch = curl_init('https://api.paymento.io/payment/verify');
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
						'token' => $token
					]));
					curl_setopt($ch, CURLOPT_HTTPHEADER, [
						'Api-key: ' . $this->config->get('payment_paymento_apikey'),
						'Content-Type: application/json',
						'Accept: application/json'
					]);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_TIMEOUT, 15);
					$response = curl_exec($ch);
					$error = curl_error($ch);
					$info = curl_getinfo($ch);
					curl_close($ch);
					$result = json_decode($response);
	
					if ($this->config->get('payment_paymento_debug')) {
						$this->log->write('Paymento :: OrderID=' . $order_id . ' :: pay verify result=' . print_r($result, true));
					}
	
					if (isset($result->success) && $result->success) {
						if (isset($result->body->token, $result->body->orderId) && $result->body->token == $token && $result->body->orderId == $order_id) {
							$ok = true;
							$order_status_id = $this->config->get('payment_paymento_completed_status_id');
							if ($this->config->get('payment_paymento_debug')) {
								$this->log->write('Paymento :: OrderID=' . $order_id . ' :: pay completed :: status=' . $status);
							}
						} else {
							$order_status_id = $this->config->get('payment_paymento_failed_status_id');
							if ($this->config->get('payment_paymento_debug')) {
								$this->log->write('Paymento :: OrderID=' . $order_id . ' :: error in verify data :: status=' . $status . ' :: verify data=' . print_r($result, true));
							}
						}
					} else {
						$order_status_id = $this->config->get('payment_paymento_failed_status_id');
						if ($this->config->get('payment_paymento_debug')) {
							if (isset($result->message) && $result->message) {
								$error = $result->message;
							}
							if (!$error) {
								$error = 'Unexpected Error!';
							}
							$this->log->write('Paymento :: OrderID=' . $order_id . ' :: error in verify=' . $error);
						}
					}
				} catch (exception $e) {
					echo ($this->language->get('text_error') . ' : ' . $e->getMessage());
					exit;
				}
			}
			$this->model_checkout_order->addHistory($order_id, $order_status_id, $msg, $ok);
			if ($ok == true) {
				$this->cart->clear();
				header('Location: ' . $this->url->link('checkout/success'));
			} else {
				header('Location: ' . $this->url->link('checkout/checkout', '', true));
			}
			exit;
		} else {
			echo 'Order Data Not Found!';
			exit;
		}
	}
	
}
