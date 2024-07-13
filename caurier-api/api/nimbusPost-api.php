<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('NimbuspostAPI')) {
	class NimbuspostAPI {
		private $token;
		private $trackingUrl;
		private $trackingApi;
		private $genrateTokenApi;
		private $transient_name = 'es_nimbuspost_token';
		private $api_base_url = 'https://api.nimbuspost.com/v1/';
	
		public function __construct() {
			$this->trackingUrl = 'https://ship.nimbuspost.com/shipping/tracking/';
			$this->trackingApi = $this->api_base_url . 'shipments/track/';
			$this->genrateTokenApi = $this->api_base_url . 'users/login';
		}
	
		private function generateToken() {
			$token = get_transient($this->transient_name);
		
			if (false === $token) {
				$url = $this->genrateTokenApi;
				$username = get_option('nimbusPost_username');
				$password = get_option('nimbusPost_password');
		
				$body = json_encode(array(
					'email' => $username,
					'password' => $password
				));
				$header_data = array(
					'Content-Type' => 'application/json',
				);
				$response = wp_remote_post($url, array(
					'headers' => $header_data,
					'body' => $body,
					'timeout' => 10,
				));
		
				if (is_wp_error($response)) {
					return array(
						'success' => false,
						'message' => 'Error: ' . $response->get_error_message(),
					);
				}
		
				$res_body = wp_remote_retrieve_body($response);
				$token_data = json_decode($res_body);
		
				if ($token_data && property_exists($token_data, 'status') && $token_data->status) {
					$token = $token_data->data;
					set_transient($this->transient_name, $token, 3 * HOUR_IN_SECONDS);
		
					return array(
						'success' => true,
						'token' => $token,
					);
				} else {
					return array(
						'success' => false,
						'message' => 'Error: ' . (property_exists($token_data, 'message') ? $token_data->message : 'Unknown error'),
					);
				}
			}
		
			return array(
				'success' => true,
				'token' => $token,
			);
		}
		
	
		private function getRequest($url) {
            $tokenResponse = $this->generateToken();
            if(!$tokenResponse['success']){
                return array(
                    'success' => false,
                    'token' => $tokenResponse['message'],
                );
            }
            $token = $tokenResponse['token'];
	
			$header_data = array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Token ' . $token,
			);
			$response = wp_remote_get($url, array(
				'headers' => $header_data,
				'timeout' => 10,
			));
			if (is_wp_error($response)) {
				return array(
					'success' => false,
					'message' => 'Error api NB: ' . $response->get_error_message(),
				);
			}
			$body = wp_remote_retrieve_body($response);
			$decoded_body = json_decode($body, true);
			$status_code = wp_remote_retrieve_response_code($response);
			if ($status_code !== 200) {
				return array(
					'success' => false,
					'message' => 'Error: Received status code ' . $status_code . ' - ' . $decoded_body['message'],
				);
			}
			if ($decoded_body === null) {
				return array(
					'success' => false,
					'message' => 'Error: Invalid JSON response',
				);
			}
			return array(
				'success' => true,
				'message' => 'Fetch get request successfully',
				'result' => $decoded_body
			);
		}
	
		private function postRequest($url, $body) {
			$token = $this->generateToken();
			if (strpos($token, 'Error') !== false) {
				return array(
					'success' => false,
					'message' => 'Error token: ' . $token,
				);
			}
	
			$header_data = array(
				'Content-Type' => 'application/json',
				'Authorization' => 'Token ' . $token,
			);
			$response = wp_remote_post($url, array(
				'headers' => $header_data,
				'body' => $body,
				'timeout' => 10,
			));
			if (is_wp_error($response)) {
				return array(
					'success' => false,
					'message' => 'Error api NB: ' . $response->get_error_message(),
				);
			}
			$body = wp_remote_retrieve_body($response);
			$decoded_body = json_decode($body, true);
			if ($decoded_body === null) {
				return array(
					'success' => false,
					'message' => 'Error: Invalid JSON response',
				);
			}
			return array(
				'success' => true,
				'message' => 'Fetch post request successfully',
				'result' => $decoded_body
			);
		}
	
		public function getTrackingData($awb) {
			$url = $this->trackingApi . $awb;
			$result = $this->getRequest($url);
            if (!$result['success']) {
                return array(
                    'success' => false,
                    'message' => $result['message'],
                );
            }
            $response = $result['result'];
            if (!$response['status']) {
                return array(
                    'success' => false,
                    'message' => $response['message'],
                );
            }
            $trackingData = $this->mapApiResponseToModel($response);
            return array(
                'success' => true,
                'message' => 'Fetch tracking data successfully',
                'result' => $trackingData
            );
		}
	
        private function mapApiResponseToModel($apiResponse) {
            $shipmentStatus = $apiResponse['data'];
            $shipmentProgress  = $apiResponse['data']['history'];
            $trackingModel = new TrackingModel(
                $shipmentStatus['edd'],
                $shipmentStatus['status'],
                $shipmentStatus['courier_name'],
                $shipmentStatus['pickup_date'],
                $shipmentStatus['awb_number'],
                $this->trackingUrl . $shipmentStatus['awb_number'],
            );

            foreach ($shipmentProgress as $scan) {
                $trackingModel->addShipmentProgress(
                    $scan['event_time'],
                    $scan['status_code'],
                    $scan['status_code'],
                    $scan['location']
                );
            }

            return $trackingModel;
        }

		public function get_rate($order_ID, $order_weight) {
			$order = wc_get_order($order_ID);
			$payment_mode = es_check_payment_mode($order->get_payment_method_title(), 'NP');
			$product_dimensions = es_get_product_dimensions($order_ID);
	
			$url = $this->api_base_url . 'courier/serviceability';
	
			$body = json_encode(array(
				"origin"        => get_option('nimbusPost_pincode'),
				"destination"   => $order->get_billing_postcode(),
				"payment_type"  => $payment_mode,
				"order_amount"  => $order->get_total(),
				"weight"        => $order_weight,
				"length"        => $product_dimensions['length'],
				"breadth"       => $product_dimensions['width'],
				"height"        => $product_dimensions['height'],
			));
			$response = $this->post_request($url, $body);
			$response_decode = json_decode($response, true);
			if (!($response_decode['status'])) {
				return 'Error : ' . $response;
			}
			return $response;
		}
	
		public function manifest($awbs) {
			$url = $this->api_base_url . 'shipments/manifest';
	
			$body = json_encode(array(
				"awbs" => $awbs
			));
			$response = $this->post_request($url, $body);
			$response_decode = json_decode($response, true);
			if (!($response_decode['status'])) {
				return 'Error : ' . $response;
			}
			return $response;
		}
	
		public function get_items($order_ID) {
			$order = wc_get_order($order_ID);
			$items = $order->get_items();
			$order_items = array();
			foreach ($items as $item) {
				$product = $item->get_product();
				$product_name = $item->get_name();
				$product_quantity = $item->get_quantity();
				$product_total = $item->get_total();
				$product_weight = $product->get_weight() * $item->get_quantity();
				$product_sku = $product->get_sku();
				$order_item = array(
					'name'    => $product_name,
					'qty'     => $product_quantity,
					'price'   => $product_total / $product_quantity,
					'sku'     => $product_sku,
					'weight'  => $product_weight,
				);
				$order_items[] = $order_item;
			}
			return json_encode($order_items);
		}
	
		public function insert_data_db($order_ID, $response) {
			$order = wc_get_order($order_ID);
			$order_weight = es_order_weight($order_ID);
	
			$awb_data = new ES_db_data_format();
			$awb_data->order_number = $order_ID;
			$awb_data->order_price = $order->get_total();
			$awb_data->order_weight = $order_weight;
			$awb_data->courier = $response->data->courier_name;
			$awb_data->courier_id = $response->data->courier_id;
			$awb_data->awb = $response->data->awb_number;
			$awb_data->tp_company = 'NB';
			$awb_data->label = $response->data->label;
	
			return es_insert_order_data_db($awb_data);
		}
	
		public function show_single_rate_popup($order_ID) {
			$shipping_response = $this->get_rate($order_ID, es_order_weight($order_ID));
			$shipping_rate = json_decode($shipping_response, true);
			?>
			<div class="eashyship-popup-body">
				<header class="eashyship-popup-header">
					<a href="https://easy-ship.in/" target="_blank"><img class="eashyship-logo" src="<?php echo EASYSHIP_DIR . '/assets/img/easyship.png' ?>" alt="easyship"></a>
					<button class="eashyship-close-js eashyship-close-btn"><span class="eashyship-close-icon">&times;</span></button>
				</header>
				<article class="eashyship-popup-article">
					<a href="https://ship.nimbuspost.com/dash" target="_blank"> <p class="eashyship-wallet">NimbusPost Recharge</p></a>
					<?php echo es_product_table($order_ID); ?><br>
					<div class="eashyship-shipping-price">
						<table class="eashyship-table eashyship-shipping-table">
							<tr>
								<th>ID</th>
								<th>Courier Name</th>
								<th>Forward</th>
								<th>COD</th>
								<th>Total</th>
							</tr>
							<form id="es_create_single_shipment">
								<?php 
								if (strpos($shipping_response, 'Error') !== false) { 
									exit($shipping_response);
								}
								usort($shipping_rate['data'], function($a, $b) {
									return $a['total_charges'] - $b['total_charges'];
								});
								foreach ($shipping_rate['data'] as $carrier) : ?>
									<tr>
										<td><input type="radio" id="shipping_id" name="shipping_id" value="'<?php echo $carrier['id'] ?>'"></td>
										<td><?php echo $carrier['name']; ?></td>
										<td><?php echo '₹' . $carrier['freight_charges']; ?></td>
										<td><?php echo '₹' . $carrier['cod_charges']; ?></td>
										<td><?php echo '₹' . $carrier['total_charges']; ?></td>
									</tr>
								<?php endforeach; ?>
								<input type="hidden" id="OrderId" name="OrderId" value="'<?php echo $order_ID ?>'">
								<input type="hidden" id="ship_by" name="ship_by" value="NP">
							</form>        
						</table>
					</div>
				</article>
				<footer class="eashyship-popup-footer">
					<div class="eashyship-btn-group">
						<button id="easyship-create-shipment" type="submit" class="easyship-btn easyship-popup-create-shipment"><?php echo __('Create Shipment') ?></button>
					</div>
				</footer>
			</div>
			<?php
		}
	}
}

if (!class_exists('NimbuspostSettings')) {
    class NimbuspostSettings { 
        public function __construct() {
            add_action('init', [$this, 'register_settings']);
            add_action('admin_menu', [$this, 'register_menu_page']);
        }
        public function register_settings() {
            // Nimbuspost settings
            register_setting('easyship-nimbuspost-group', 'nimbuspost_enable');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_username');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_password');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_warehouse_name');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_name');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_address');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_address_2');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_city');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_state');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_pincode');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_phone');
            register_setting('easyship-nimbuspost-group', 'nimbusPost_gst_umber');

        }

        public function register_menu_page() {
			add_submenu_page(EASYSHIP_MAIN_URL, 'NimbusPost settings', 'NimbusPost API', 'manage_options', 'easyship-nimbuspost', [$this, 'nimbuspost_settings_page']);
        }

        public function nimbuspost_settings_page() {
            ?>
            <div class="wrap">
                <h1>Nimbuspost Settings</h1>
                <form method="post" action="options.php">
                    <?php settings_fields('easyship-nimbuspost-group'); ?>
                    <?php do_settings_sections('easyship-nimbuspost-group'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Nimbuspost Enable/Disable</th>
                            <td>
                                <input type="checkbox" name="nimbuspost_enable" value="1" <?php checked(get_option('nimbuspost_enable'), 1); ?> /> Enable Nimbuspost
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">NimbusPost Username</th>
                            <td>
                                <input type="text" name="nimbusPost_username" value="<?php echo esc_attr(get_option('nimbusPost_username')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">NimbusPost Password</th>
                            <td>
                                <input type="password" name="nimbusPost_password" value="<?php echo esc_attr(get_option('nimbusPost_password')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><h2>Warehouse Details</h2></th>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Warehouse Name</th>
                            <td>
                                <input type="text" name="nimbusPost_warehouse_name" value="<?php echo esc_attr(get_option('nimbusPost_warehouse_name')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Name</th>
                            <td>
                                <input type="text" name="nimbusPost_name" value="<?php echo esc_attr(get_option('nimbusPost_name')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Address Line 1</th>
                            <td>
                                <input type="text" name="nimbusPost_address" value="<?php echo esc_attr(get_option('nimbusPost_address')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Address Line 2</th>
                            <td>
                                <input type="text" name="nimbusPost_address_2" value="<?php echo esc_attr(get_option('nimbusPost_address_2')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">City</th>
                            <td>
                                <input type="text" name="nimbusPost_city" value="<?php echo esc_attr(get_option('nimbusPost_city')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">State</th>
                            <td>
                                <input type="text" name="nimbusPost_state" value="<?php echo esc_attr(get_option('nimbusPost_state')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Pincode</th>
                            <td>
                                <input type="text" name="nimbusPost_pincode" value="<?php echo esc_attr(get_option('nimbusPost_pincode')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Phone</th>
                            <td>
                                <input type="text" name="nimbusPost_phone" value="<?php echo esc_attr(get_option('nimbusPost_phone')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">GST Number</th>
                            <td>
                                <input type="text" name="nimbusPost_gst_umber" value="<?php echo esc_attr(get_option('nimbusPost_gst_umber')); ?>" />
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }
    }
}

?>