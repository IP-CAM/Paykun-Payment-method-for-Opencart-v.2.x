<?php
class ControllerPaymentPaykun extends Controller {

	private $error = array();

	private function getCallbackUrl(){
		$callback_url = "index.php?route=payment/paykun/callback";
		return $_SERVER['HTTPS']? HTTPS_CATALOG . $callback_url : HTTP_CATALOG . $callback_url;
	}

	public function index() {

		$data = [];
		$this->language->load('payment/paykun');
        $this->load->model('setting/setting');

		$data = array_merge($data, $this->language->load('payment/paykun'));
		$this->document->setTitle($this->language->get('heading_title'));



		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

			$this->model_setting_setting->editSetting('paykun', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'] . '&type=payment', 'SSL'));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['merchant_id'])) {
			$data['error_merchant_id'] = $this->error['error_merchant_id'];
		} else {
			$data['error_merchant_id'] = '';
		}

		if (isset($this->error['entry_access_token'])) {
			$data['error_access_token'] = $this->error['entry_access_token'];
		} else {
			$data['error_access_token'] = '';
		}

		if (isset($this->error['enc_key'])) {
			$data['error_enc_key'] = $this->error['error_enc_key'];
		} else {
			$data['error_enc_key'] = '';
		}
		
		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

  		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text'	  => $this->language->get('text_home'),
			'href'	  => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], 'SSL'),
		'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text'	  => $this->language->get('text_extension'),
			'href'	  => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'),
		'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text'	  => $this->language->get('text_payments'),
			'href'	  => $this->url->link('extension/payment', 'token=' . $this->session->data['token'] . '&type=payment', 'SSL'),
		'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text'	  => $this->language->get('heading_title'),
			'href'	  => $this->url->link('payment/paykun', 'token=' . $this->session->data['token'], 'SSL'),
		'separator' => ' :: '
		);

		$data['action'] = $this->url->link('payment/paykun', 'token=' . $this->session->data['token'], 'SSL');

		$data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'] . '&type=payment', 'SSL');


		if (isset($this->request->post['paykun_merchant_id'])) {
//		    var_dump($this->request->post);exit;
			$data['paykun_merchant_id'] = $this->request->post['paykun_merchant_id'];
		} else {
			$data['paykun_merchant_id'] = $this->config->get('paykun_merchant_id');
		}

		if (isset($this->request->post['paykun_access_token'])) {
			$data['paykun_access_token'] = $this->request->post['paykun_access_token'];
		} else {
			$data['paykun_access_token'] = $this->config->get('paykun_access_token');
		}

		if (isset($this->request->post['paykun_enc_key'])) {
			$data['paykun_enc_key'] = $this->request->post['paykun_enc_key'];
		} else {
			$data['paykun_enc_key'] = $this->config->get('paykun_enc_key');
		}
	
		$data["default_callback_url"] = $this->getCallbackUrl();

		if (isset($this->request->post['paykun_order_success_status_id'])) {
			$data['paykun_order_success_status_id'] = $this->request->post['paykun_order_success_status_id'];
		} else {
			$data['paykun_order_success_status_id'] = $this->config->get('paykun_order_success_status_id');
		}

		if (isset($this->request->post['paykun_order_failed_status_id'])) {
			$data['paykun_order_failed_status_id'] = $this->request->post['paykun_order_failed_status_id'];
		} else {
			$data['paykun_order_failed_status_id'] = $this->config->get('paykun_order_failed_status_id');
		}

		if (isset($this->request->post['paykun_sort_order'])) {
			$data['paykun_sort_order'] = $this->request->post['paykun_sort_order'];
		} else {
			$data['paykun_sort_order'] = (int)$this->config->get('paykun_sort_order');
		}

        if (isset($this->request->post['paykun_status'])) {
            $data['paykun_status'] = $this->request->post['paykun_status'];
        } else {
            $data['paykun_status'] = $this->config->get('paykun_status');
        }

        if (isset($this->request->post['paykun_log_status'])) {
            $data['paykun_log_status'] = $this->request->post['paykun_log_status'];
        } else {
            $data['paykun_log_status'] = $this->config->get('paykun_log_status');
        }

		$last_updated = "";
		$path = DIR_SYSTEM . "/paykun_version.txt";
		if(file_exists($path)){
			$handle = fopen($path, "r");
			if($handle !== false){
				$date = fread($handle, 10); // i.e. DD-MM-YYYY or 25-04-2018
				$last_updated = '<p>Last Updated: '. date("d F Y", strtotime($date)) .'</p>';
			}
		}

		$data['footer_text'] = '<div class="text-center">'.$last_updated.'<p>'.$this->language->get('text_opencart_version').': '.VERSION.'</p></div>';

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('payment/paykun.tpl', $data));


	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'payment/paykun')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		// trim all values except for Promo Codes
		foreach($this->request->post as $key=>&$val){
            $val = trim($val);
		}

		if (!$this->request->post['paykun_merchant_id']) {
			$this->error['error_merchant_id'] = $this->language->get('error_merchant_id');
		}
		if (!$this->request->post['paykun_access_token']) {
			$this->error['error_access_token'] = $this->language->get('error_access_token');
		}
		if (!$this->request->post['paykun_enc_key']) {
			$this->error['error_enc_key'] = $this->language->get('error_enc_key');
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}
}