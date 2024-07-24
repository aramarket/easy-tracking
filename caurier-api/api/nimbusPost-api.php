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
		private $bookShipmentApiUrl;
	
		public function __construct() {
			$this->trackingUrl = 'https://ship.nimbuspost.com/shipping/tracking/';
			$this->trackingApi = $this->api_base_url . 'shipments/track/';
			$this->genrateTokenApi = $this->api_base_url . 'users/login';
			$this->bookShipmentApiUrl = $this->api_base_url . 'shipments';
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
                    $this->nimbuspost_map_status($scan['status_code']),
                    $scan['message'],
                    $scan['location']
                );
            }

            return $trackingModel;
        }

		private function nimbuspost_map_status($input) {
			switch ($input) {
				case 'PP':
					return 'Pending Pickup';
				case 'IT':
					return 'In Transit';
				case 'EX':
					return 'Exception';
				case 'OFD':
					return 'Out For Delivery';
				case 'DL':
					return 'Delivered';
				case 'RT':
					return 'RTO';
				case 'RT-IT':
					return 'RTO In Transit';
				case 'RT-DL':
					return 'RTO Delivered';
				default:
					return $input;
			}
		}
		
		public function getShippingRate($order_ID, $order_weight) {
			$order = wc_get_order($order_ID);
			$paymentMode = ESShippingFunction::check_payment_mode($order->get_payment_method_title());
			// 			$product_dimensions = es_get_product_dimensions($order_ID);

			$url = $this->api_base_url . 'courier/serviceability';
			
			$body = json_encode(array(
				"origin"        => get_option('nimbusPost_pincode'),
				"destination"   => $order->get_billing_postcode(),
				"payment_type"  => $paymentMode,
				"order_amount"  => $order->get_total(),
				"weight"        => $order_weight,
				"length"        => '10',
				"breadth"       => '10',
				"height"        => '10',
			));
			$response = $this->postRequest($url, $body);
			
			if(!$response['success']){
				// Prepare the response array
				$error = array(
					'success' => false,  // Assuming the request was successful
					'message' => $response['message'],
				);
				return $error;
			} else {
				$list_of_couriers = $response['result']['data'];
				$results = [];

				foreach ($list_of_couriers as $courier) {
					$results[] = [
						'courier_id' => $courier['id'],
						'courier_name' => $courier['name'],
						'courier_price' => $courier['total_charges']
					];
				}
			}
			return [
				'success' => true, 
				'message' => 'Shipping rate fetched successfully',
				'result' => $results
			];
		}
	
		// Shipping code start here
		public function prepareOrderItemsData($orderID) {
			$order = wc_get_order($orderID);
			$items = $order->get_items();
			$orderItems = array();
			foreach ($items as $item) {
				$product = $item->get_product();
				$product_name = $item->get_name();
				$product_quantity = $item->get_quantity();
				$product_total = $item->get_total();
// 				$product_weight = $product->get_weight() * $item->get_quantity();
				$product_sku = $product->get_sku();
				$orderItem = array(
					'name'    => $product_name,
					'qty'     => $product_quantity,
					'price'   => $product_total / $product_quantity,
					'sku'     => $product_sku,
					'weight'  => 500, //$product_weight
				);
				$orderItems[] = $orderItem;
			}
			return $orderItems;
		}
		
		public function prepareShipmentData($orderID, $courierID){
			$order = wc_get_order( $orderID );
			$orderDate = date('Y-m-d H:i', strtotime($order->get_date_created()));

			$paymentMode = ESShippingFunction::check_payment_mode($order->get_payment_method_title());
// 			if ($getPaymentMode == 'prepaid') {
// 				$paymentMode = 'prepaid';
// 			} else {
// 				$paymentMode = 'cod';
// 			}

			$orderWeight = ESShippingFunction::get_order_weight($orderID); //weight should be in grams;
			$weight = $orderWeight['result']; //weight should be in kgs;

			$productDimensions = ESShippingFunction::get_product_dimensions($orderID); //dimentions should be in cm;

			$phoneResponce = ESCommonFunctions::extractPhoneNumber($order->get_billing_phone());
			if($phoneResponce['success']) {
				$phone = $phoneResponce['result'];
			} else{
				$phone = 0000000000;
			}

			$itemData = $this->prepareOrderItemsData($orderID);

			$orderData = array(
							"order_number" 			=> $orderID,
							"shipping_charges" 		=> $order->get_shipping_total(),
							"discount" 				=> $order->get_discount_total(),
							"cod_charges" 			=> 0,
							"payment_type" 			=> $paymentMode,
							"order_amount" 			=> $order->get_total(),
							"package_weight" 		=> $weight,   
							"package_length"  		=> $productDimensions['length'],
							"package_breadth" 		=> $productDimensions['width'],
							"package_height"  		=> $productDimensions['height'],
							"request_auto_pickup" 	=> "yes", // no for not auto request
							"courier_id" 			=> $courierID,
							"consignee" 			=> array(
								"name" 		=> $order->get_billing_first_name().' '.$order->get_billing_last_name(),
								"address" 	=> $order->get_billing_address_1(),
								"address_2" => $order->get_billing_address_2(),
								"city" 		=> $order->get_billing_city(),
								"state" 	=> $order->get_billing_state(),
								"pincode" 	=> $order->get_billing_postcode(),
								"phone" 	=> $phone,
							),
							"pickup" => array(
								"warehouse_name" => get_option( 'nimbusPost_warehouse_name' ),
								"name" 			 => get_option( 'nimbusPost_name' ),
								"address"		 => get_option( 'nimbusPost_address' ),
								"address_2"		 => get_option( 'nimbusPost_address_2' ),
								"city"			 => get_option( 'nimbusPost_city' ),
								"state"			 => get_option( 'nimbusPost_state' ),
								"pincode"		 => get_option( 'nimbusPost_pincode' ),
								"phone"			 => get_option( 'nimbusPost_phone' ),
								"gst_umber"      => get_option( 'nimbusPost_gst_umber' ),
							),							
							"order_items" 			=> $itemData,
						);
			return json_encode($orderData, true);
		}
		
		public function createShipments($orderID, $courierID){
			$url = $this->bookShipmentApiUrl;
            $shipmentData = $this->prepareShipmentData($orderID, $courierID);
			$response = $this->postRequest($url, $shipmentData);
			if(!$response['success']){
				// Prepare the response array
				return array(
					'success' => false,
					'message' => $response['message'],
				);
			}
			$result = $response['result'];
            return $this->handleCreatedShipmentResponse($orderID, $result);
		}

		public function handleCreatedShipmentResponse($orderID, $response){
			if(!$response['status']){
				return array(
					'success' => false,
					'message' => $response['message'],
				);
			}
			$awb = $response['data']['awb_number'];
			
			ESCommonFunctions::save_tracking_details_in_meta($orderID, $awb, 'nimbuspost');
			$result = [
				'success' => true,
				'message' => 'Order shipped successfully',
			];
			return $result;
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
			add_submenu_page(EASYSHIP_MENU_SLUG, 'NimbusPost settings', 'NimbusPost API', 'manage_options', 'easyship-nimbuspost', [$this, 'nimbuspost_settings_page']);
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