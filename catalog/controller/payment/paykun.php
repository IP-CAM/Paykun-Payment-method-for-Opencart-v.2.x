<?php

require_once DIR_SYSTEM."Paykun/Payment.php";
require_once DIR_SYSTEM."Paykun/Errors/ValidationException.php";


class ControllerPaymentPaykun extends Controller {

	private $_merchantId;
    private $_accessToken;
    private $_encKey;
    private $isLogEnabled;
    private $log;
    public function index() {

		$this->load->language('payment/paykun');
		$this->load->model('payment/paykun');
		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		/*Set payment Params*/
		$this->_merchantId = $this->config->get('paykun_merchant_id');
		$this->_accessToken = $this->config->get('paykun_access_token');
		$this->_encKey = $this->config->get('paykun_enc_key');

        // Get log settings
		$this->isLogEnabled = $this->config->get('paykun_log_status');
		// Add paykung log into the file paykun_payment.log
        $this->log = new Log('paykun_payment.log');

		$pkdata = $this->initPayment($this->getOrderDetail($order_info));

		if($order_info['currency_code'] != "INR"){

            echo "Only Indian (INR) currency is allowed. Please change your store currency settings.";
            exit;
            /*Do not allow transaction if currency is not indian*/
            //trigger error message

		}

		$data['action'] = $this->config->get('paykun_transaction_url');
		$data['button_confirm'] = $this->language->get('button_confirm');
        $data['paykunData'] = $pkdata;

		if(file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/paykun.tpl')) {
			return $this->load->view($this->config->get('config_template') . '/template/payment/paykun.tpl', $data);
		} else {
            if(version_compare(VERSION, '2.2.0.0', '<')) {
                $this->template = 'default/template/payment/paykun.tpl';
            } else {
                $this->template = 'payment/paykun.tpl';
            }
            return $this->load->view($this->template, $data);
		}
	}

    private function addLog($message) {

        if($this->isLogEnabled) {
            $this->log->write($message);

        }


    }

    private function initPayment($orderDetail) {

        try {

            $this->addLog(
                "merchantId => ".$this->_merchantId.
                ", accessToken=> ".$this->_accessToken.
                ", encKey => ".$this->_encKey.
                ", orderId => ".$orderDetail['orderId'].
                ", purpose=>".$orderDetail['purpose'].
                ", amount=> ".$orderDetail['amount']
            );

            $obj = new Payment($this->_merchantId, $this->_accessToken, $this->_encKey, true, true);

            // Initializing Order
            $obj->initOrder($orderDetail['orderId'], $orderDetail['purpose'], $orderDetail['amount'],
                $orderDetail['successUrl'], $orderDetail['failureUrl']);

            // Add Customer
            $obj->addCustomer($orderDetail['customerName'], $orderDetail['customerEmail'], $orderDetail['customerMoNo']);

            // Add Shipping address
            $obj->addShippingAddress($orderDetail['s_country'], $orderDetail['s_state'], $orderDetail['s_city'], $orderDetail['s_pinCode'],
                $orderDetail['s_addressString']);

            // Add Billing Address
            $obj->addBillingAddress($orderDetail['b_country'], $orderDetail['b_state'], $orderDetail['b_city'], $orderDetail['b_pinCode'],
                $orderDetail['b_addressString']);

            $obj->setCustomFields(['udf_1' => $orderDetail['orginalOrderId']]);
            //Render template and submit the form
            $data = $obj->submit();
            return $data;

        } catch (ValidationException $e) {

            $this->addLog($e->getMessage());
            echo $e->getMessage();
//            throw new ValidationException("Something went wrong.".$e->getMessage(), $e->getCode(), null);
            return null;
        }

    }

    private function getCallbackUrl($isFailed = false){

        $callback_url = "index.php?route=payment/paykun/callbackSuccess";

    	if($isFailed === true) {
            $callback_url = "index.php?route=payment/paykun/callbackFailed";
		}

        return $_SERVER['HTTPS']? HTTPS_SERVER . $callback_url : HTTP_SERVER . $callback_url;

    }

    private function getOrderDetail($orderInfo) {
        try {

            $this->load->model('account/order');
            $order_info = $this->model_checkout_order->getOrder($orderInfo['order_id']);
            $products = $this->model_account_order->getOrderProducts($orderInfo['order_id']);
            $itemPurpose = "";
            $totalItem = count($products);
            foreach ($products as $index => $value) {
                $stuff = ", ";
                if ($index == $totalItem - 1) {
                    $stuff = "";
                }
                $itemPurpose .= $value["name"].$stuff;
            }

            $oderdetail = [
                "orginalOrderId"    => $orderInfo['order_id'],
                /*Init data*/
                "orderId"   => $this->getOrderIdForPaykun($orderInfo['order_id']),
                "purpose" => $itemPurpose,
                "amount"    => $this->currency->format($orderInfo['total'], $orderInfo['currency_code'], $orderInfo['currency_value'], false),
                "successUrl"    => $this->getCallbackUrl(),
                "failureUrl"    => $this->getCallbackUrl(true),
                /*Init over*/

                /*customer data*/
                "customerName"  => $orderInfo['firstname']. ' '.$orderInfo['lastname'],
                "customerEmail" =>  $orderInfo['email'],
                "customerMoNo"  => $orderInfo['telephone'],
                /*customer data over*/

                /*Shipping detail*/
                "s_country" => $orderInfo["shipping_country"],
                "s_state" => $orderInfo["shipping_zone"],
                "s_city"  => $orderInfo["shipping_city"],
                "s_pinCode"   => $orderInfo["shipping_postcode"],
                "s_addressString" => $orderInfo["shipping_address_1"].$orderInfo["shipping_address_2"],
                /*Shipping detail over*/

                /*Billing detail*/
                "b_country" => $orderInfo["payment_country"],
                "b_state" => $orderInfo["payment_zone"],
                "b_city"  => $orderInfo["payment_city"],
                "b_pinCode"   => $orderInfo["payment_postcode"],
                "b_addressString" => $orderInfo["payment_address_1"].$orderInfo["payment_address_2"],
                /*Billing detail over*/
            ];

            return $oderdetail;

        } catch (ValidationException $e){
            $this->addLog($e->getMessage());
            return null;
        }


    }

    private function getOrderIdForPaykun($orderId) {

        $orderNumber = str_pad((string)$orderId, 10, '0', STR_PAD_LEFT);
        return $orderNumber;

    }

    public function callbackSuccess() {

        try {

            $data = [];
            $this->language->load('payment/paykun');
            $data = array_merge($data, $this->language->load('payment/paykun'));
            $this->load->model('checkout/order');

            $paymentId = $this->request->get['payment-id'];
            $response = $this->_getcurlInfo($paymentId);

            $data['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));
            $data['language'] = $this->language->get('code');
            $data['direction'] = $this->language->get('direction');
            $data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));


            $this->children = array(
                'common/column_left',
                'common/column_right',
                'common/content_top',
                'common/content_bottom',
                'common/footer',
                'common/header'
            );

            if(version_compare(VERSION, '2.2.0.0', '<')) {
                $this->template = 'default/template/payment/paykun_failure.tpl';
            } else {
                $this->template = 'payment/paykun_failure.tpl';
            }
            $data['continue'] = $this->url->link('checkout/cart');

            if($response && isset($response['status']) && $response['status'] == "1" || $response['status'] == 1 ) {
                $payment_status = $response['data']['transaction']['status'];
                $order_id = $response['data']['transaction']['custom_field_1'];
                if($payment_status === "Success") {
                    //if(1) {
                    $orderInfo = $this->model_checkout_order->getOrder($order_id);
                    $resAmout = $response['data']['transaction']['order']['gross_amount'];
                    if(($orderInfo['total']	== $resAmout)) {
                        $data['text_response'] = $this->language->get('text_response');
                        $data['text_success'] = $this->language->get('text_success');
                        $data['text_success_wait'] = sprintf($this->language->get('text_success_wait'), $this->url->link('checkout/success'));

                        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('paykun_order_success_status_id'), $this->getSuccessOrderStatusHtml($paymentId, true));

                        $data['continue'] = $this->url->link('checkout/success');
                        $this->cart->clear();
                        //                $this->response->setOutput($this->load->view($this->template, $data));

                        if(version_compare(VERSION, '2.2.0.0', '<')) {
                            $this->template = 'default/template/payment/paykun_success.tpl';
                        } else {
                            $this->template = 'payment/paykun_success.tpl';
                        }
                    } else {//Some Fraud activity is happening here

                        $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('paykun_order_failed_status_id'), $this->getSuccessOrderStatusHtml($paymentId));
                        $data['text_failure'] = $this->language->get('text_failure');
                        $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('checkout/cart'));
                        $this->addLog("Fraud activity is happening with payment Id => $paymentId");
                    }

                } else {

                    $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('paykun_order_failed_status_id'), $this->getSuccessOrderStatusHtml($paymentId));
                    $data['text_failure'] = $this->language->get('text_failure');
                    $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('checkout/cart'));

                    // unset order id if it is set, so new order id could be generated by paykun for next txns
                    if(isset($this->session->data['order_id']))
                        unset($this->session->data['order_id']);

//                $this->response->setOutput($this->load->view($this->template, $data));

                }
            }
            else {
                $data['text_failure'] = "Unauthorised Transaction Id";
                $data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('checkout/cart'));
            }

            $this->response->setOutput($this->load->view($this->template, $data));

        } catch (ValidationException $e) {

            $this->addLog($e->getMessage());
            echo "Oops! Communication error occurred from Paykun server.".$e->getMessage();
            return null;
        }

    }



    public function callbackFailed() {

        try {

            $data = [];
            $this->language->load('payment/paykun');
            $data = array_merge($data, $this->language->load('payment/paykun'));

            $paymentId = $this->request->get['payment-id'];
            $response = $this->_getcurlInfo($paymentId);

            $data['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));
            $data['language'] = $this->language->get('code');
            $data['direction'] = $this->language->get('direction');
            $data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

            if(isset($response['status']) && $response['status'] == "1" || $response['status'] == 1 ) {

                $payment_status = $response['data']['transaction']['status'];
                $data['text_response'] = "Payment Failed.";
                $data['text_failure'] = "You have cancelled payment.";
                $data['text_failure_wait'] = "You will be redirected to the cart soon...";
                $this->load->model('checkout/order');
                $order_id = $response['data']['transaction']['custom_field_1'];

                /*Set default order status from paykun config*/
                $this->model_checkout_order->addOrderHistory($order_id, $this->config->get('paykun_order_failed_status_id'), $this->getSuccessOrderStatusHtml($paymentId));

                $data['continue'] = $this->url->link('checkout/cart');


                // unset order id if it is set, so new order id could be generated by paykun for next txns
                if(isset($this->session->data['order_id']))
                    unset($this->session->data['order_id']);

            } else {

                $data['continue'] = $this->url->link('checkout/cart');
                $data['text_response'] = "Could not get response from server.";
                $data['text_failure'] = "You have cancelled payment.";
                $data['text_failure_wait'] = "You will be redirected to the cart soon...";
                $this->load->model('checkout/order');

            }

            if(version_compare(VERSION, '2.2.0.0', '<')) {
                $this->template = 'default/template/payment/paykun_failure.tpl';
            } else {
                $this->template = 'payment/paykun_failure.tpl';
            }

            $this->children = array(
                'common/column_left',
                'common/column_right',
                'common/content_top',
                'common/content_bottom',
                'common/footer',
                'common/header'
            );

            $this->response->setOutput($this->load->view($this->template, $data));

        } catch (ValidationException $e) {

            $this->addLog($e->getMessage());
            echo "Oops! Communication error occurred from Paykun server.".$e->getMessage();
            return null;
        }

    }

    private function _getcurlInfo($iTransactionId) {

        try {

            $this->_merchantId = $this->config->get('paykun_merchant_id');
            $this->_accessToken = $this->config->get('paykun_access_token');
            $this->_encKey = $this->config->get('paykun_enc_key');

            $cUrl        = 'https://api.paykun.com/v1/merchant/transaction/' . $iTransactionId . '/';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $cUrl);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array("MerchantId:$this->_merchantId", "AccessToken:$this->_accessToken"));

            $response       = curl_exec($ch);
            $error_number   = curl_errno($ch);
            $error_message  = curl_error($ch);

            $res = json_decode($response, true);
            curl_close($ch);

            return ($error_message) ? null : $res;

        } catch (ValidationException $e) {

            $this->addLog($e->getMessage());
            echo $e->getMessage();
            //throw new ValidationException("Server couldn't respond, ".$e->getMessage(), $e->getCode(), null);
            return null;

        }

    }

    /**
     * @param $paymentId
     * @param $status true = success, false = failed
     */
    private function getSuccessOrderStatusHtml($paymentId, $status = false) {

        $colorCode = "#ea3232"; //red for failed
        $html = "";
        if($status) {
            $colorCode = "#577949";
        }
        return '<span style="padding: 5px;border-radius: 50%;background: '.$colorCode.';height: 5px;display: inline-block;"></span><span style="margin-left: 4px;">Paykun Payment Id => '.$paymentId.'</span>';

    }
}
?>
