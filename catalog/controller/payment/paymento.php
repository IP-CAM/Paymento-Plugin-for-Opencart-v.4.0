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
            
            if (!isset($this->session->data['order_id'])) {
                if ($this->config->get('payment_paymento_debug')) {
                    $this->log->write('Paymento :: No order ID in session');
                }
                return $this->language->get('error_no_order');
            }

            $order_id = $this->session->data['order_id'];
            $order_info = $this->model_checkout_order->getOrder($order_id);
            
            if ($order_info && $order_info['order_status_id'] == $this->config->get('payment_paymento_pending_status_id')) {
                // There's already a pending payment for this order
                $this->response->redirect($this->url->link('extension/paymento_gateway/payment/paymento|processing', 'order_id=' . $order_id, true));
                return;
            }

            if (!$order_info) {
                if ($this->config->get('payment_paymento_debug')) {
                    $this->log->write('Paymento :: Invalid order ID: ' . $order_id);
                }
                return $this->language->get('error_invalid_order');
            }

            if ($order_info['order_status_id'] == $this->config->get('payment_paymento_completed_status_id')) {
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
    
            // Log the current order ID for debugging
            if ($this->config->get('payment_paymento_debug')) {
                $this->log->write('Paymento :: Current order ID: ' . $order_id);
            }

            $ch = curl_init('https://api.paymento.io/v1/payment/request');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'fiatAmount' => $order_info['total'],
            'fiatCurrency' => strtoupper($order_info['currency_code']),
            'returnUrl' => $this->url->link('extension/paymento_gateway/payment/paymento', 'shf_action=paymento_callback&order_id=' . $order_id . '&shf_key=' . $this->session->getId(), true),
            'orderId' => $order_id,
            'Speed' => ($this->config->get('payment_paymento_risk') ? 0 : 1),
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

        if ($error) {
            if ($this->config->get('payment_paymento_debug')) {
                $this->log->write('Paymento :: cURL Error: ' . $error);
            }
            return $this->language->get('error_curl');
        }

        if ($this->config->get('payment_paymento_debug')) {
            $this->log->write('Paymento :: API Response: ' . $response);
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if ($this->config->get('payment_paymento_debug')) {
                $this->log->write('Paymento :: JSON decode error: ' . json_last_error_msg());
            }
            return $this->language->get('error_invalid_json');
        }

        if (isset($result['success']) && $result['success'] === true && isset($result['body'])) {
            $token = $result['body'];
            
            // Return the view with the token and confirmation button
            return $this->load->view('extension/paymento_gateway/payment/paymento', [
                'action' => 'https://app.paymento.io/gateway',
                'token' => $token,
                'button_confirm' => $this->language->get('button_confirm'),
            ]);
        } else {
            if ($this->config->get('payment_paymento_debug')) {
                $this->log->write('Paymento :: Invalid API response structure: ' . print_r($result, true));
            }
            return $this->language->get('error_invalid_response');
        }
    } catch (Exception $e) {
        if ($this->config->get('payment_paymento_debug')) {
            $this->log->write('Paymento :: Exception: ' . $e->getMessage());
        }
        return $this->language->get('error_exception') . ' : ' . $e->getMessage();
    }
    }


    private function verifyHmacSignature($payload, $receivedSignature) {
        $secretKey = $this->config->get('payment_paymento_secret_key');
        $calculatedSignature = strtoupper(hash_hmac('sha256', $payload, $secretKey));
        
        if ($this->config->get('payment_paymento_debug')) {
            $this->log->write('Paymento :: Secret Key: ' . substr($secretKey, 0, 5) . '...');  // Log only first 5 characters for security
            $this->log->write('Paymento :: Calculated Signature: ' . $calculatedSignature);
            $this->log->write('Paymento :: Received Signature: ' . $receivedSignature);
        }
    
        return hash_equals($calculatedSignature, $receivedSignature);
    }
    
    public function callback($isGet = false) {
        $this->load->language('extension/paymento_gateway/payment/paymento');
        $this->load->model('checkout/order');
    
        if ($this->config->get('payment_paymento_debug')) {
            $this->log->write('Paymento :: Callback initiated');
        }
    
        if ($isGet) {
            // Handle callback from URL query (client redirection)
            $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
            $token = isset($this->request->get['token']) ? $this->request->get['token'] : '';
            $status = isset($this->request->get['status']) ? $this->request->get['status'] : '';
    
            // Log GET data
            if ($this->config->get('payment_paymento_debug')) {
                $this->log->write('Paymento :: GET data: ' . print_r($this->request->get, true));
            }
    
            // For GET requests, we skip HMAC verification
        } else {
            // Handle callback from Paymento via POST
            $signature = $this->request->server['HTTP_X_HMAC_SHA256_SIGNATURE'] 
                       ?? $this->request->server['X_HMAC_SHA256_SIGNATURE'] 
                       ?? '';
    
            $payload = file_get_contents('php://input');
    
            // Log the raw POST data for debugging
            if ($this->config->get('payment_paymento_debug')) {
                $this->log->write('Paymento :: Raw POST data: ' . $payload);
            }
    
            // Verify HMAC signature for POST requests
            if (!$this->verifyHmacSignature($payload, $signature)) {
                if ($this->config->get('payment_paymento_debug')) {
                    $this->log->write('Paymento :: Invalid HMAC signature');
                    $this->log->write('Paymento :: Payload: ' . $payload);
                    $this->log->write('Paymento :: Received Signature: ' . $signature);
                }
                $this->response->addHeader('HTTP/1.0 400 Bad Request');
                $this->response->setOutput('Invalid signature!');
                return;
            }
    
            $data = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                if ($this->config->get('payment_paymento_debug')) {
                    $this->log->write('Paymento :: JSON decode error: ' . json_last_error_msg());
                }
                $this->response->addHeader('HTTP/1.0 400 Bad Request');
                $this->response->setOutput('Invalid JSON data received!');
                return;
            }
    
            $order_id = isset($data['OrderId']) ? (int)$data['OrderId'] : 0;
            $token = $data['Token'] ?? '';
            $status = $data['OrderStatus'] ?? '';
        }
    
        // Process the order (common for both GET and POST)
        $order_info = $this->model_checkout_order->getOrder($order_id);
    
        if ($order_info) {
            if ($order_info['order_status_id'] == $this->config->get('payment_paymento_completed_status_id')) {
                $this->response->redirect($this->url->link('checkout/success', '', true));
                return;
            }
    
            $ok = false;
            $order_status_id = $this->config->get('config_order_status_id');
            $msg = "status: $status, orderId: $order_id, token: $token";
    
            if ($this->config->get('payment_paymento_debug')) {
                $this->log->write('Paymento :: OrderID=' . $order_id . ' :: Callback data=' . print_r(['status' => $status, 'orderId' => $order_id, 'token' => $token], true));
            }
    
            if ($status == 3) { // Waiting to confirm
                $ok = true;
                $order_status_id = $this->config->get('payment_paymento_pending_status_id');
                if ($this->config->get('payment_paymento_debug')) {
                    $this->log->write('Paymento :: OrderID=' . $order_id . ' :: pay pending confirmation :: status=' . $status);
                }
                $this->model_checkout_order->addHistory($order_id, $order_status_id, $msg, $ok);
                
                // Clear the cart here
                $this->cart->clear();
                
                // Unset necessary session data
                unset($this->session->data['shipping_method']);
                unset($this->session->data['shipping_methods']);
                unset($this->session->data['payment_method']);
                unset($this->session->data['payment_methods']);
                unset($this->session->data['guest']);
                unset($this->session->data['comment']);
                unset($this->session->data['order_id']);
                unset($this->session->data['coupon']);
                unset($this->session->data['reward']);
                unset($this->session->data['voucher']);
                unset($this->session->data['vouchers']);
                unset($this->session->data['totals']);
                
                $this->response->redirect($this->url->link('extension/paymento_gateway/payment/paymento|processing', 'order_id=' . $order_id, true));
                return;
            } elseif ($status == 5) {
                $order_status_id = $this->config->get('payment_paymento_failed_status_id');
                if ($this->config->get('payment_paymento_debug')) {
                    $this->log->write('Paymento :: OrderID=' . $order_id . ' :: pay cancelled by user :: status=' . $status);
                }
            } elseif ($status == 7) { // Assuming status 7 means payment completed
                try {
                    $ch = curl_init('https://api.paymento.io/v1/payment/verify');
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
                } catch (Exception $e) {
                    echo ($this->language->get('text_error') . ' : ' . $e->getMessage());
                    exit;
                }
            }
            $this->model_checkout_order->addHistory($order_id, $order_status_id, $msg, $ok);
        if ($ok == true) {
            $this->cart->clear();
            $this->response->redirect($this->url->link('checkout/success', '', true));
        } else {
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    	} else {
        	echo 'Order Data Not Found!';
        	exit;
   	 }
    }

	public function processing() {
        $this->load->language('extension/paymento_gateway/payment/paymento');
    
        $this->document->setTitle($this->language->get('heading_processing'));
    
        $data['breadcrumbs'] = array();
    
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
    
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_cart'),
            'href' => $this->url->link('checkout/cart')
        );
    
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );
    
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_processing'),
            'href' => $this->url->link('extension/paymento_gateway/payment/paymento|processing', '', true)
        );
    
        $data['heading_title'] = $this->language->get('heading_processing');
    
        $data['text_message'] = $this->language->get('text_processing_message');
    
        $data['button_continue'] = $this->language->get('button_continue');
    
        $data['continue'] = $this->url->link('common/home');
    
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');
    
        $this->response->setOutput($this->load->view('extension/paymento_gateway/payment/paymento_processing', $data));
    }
}