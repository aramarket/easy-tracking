<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('ShiprocketAPI')) {
    class ShiprocketAPI {
        private $token;
		private $genrateTokenApiUrl;
        private $trackingApiUrl;
		private $getRateApiUrl;
		private $walletBalanceApiUrl;
		private $bookShipmentApiUrl;
		private $generateAWBApiUrl;
		private $generateLabelApiUrl;
		
        public function __construct() {
			$this->genrateTokenApiUrl = 'https://apiv2.shiprocket.in/v1/external/auth/login';
            $this->trackingApiUrl = 'https://apiv2.shiprocket.in/v1/external/courier/track/awb/';
			$this->getRateApiUrl = 'https://apiv2.shiprocket.in/v1/external/courier/serviceability/?';
			$this->bookShipmentApiUrl = 'https://apiv2.shiprocket.in/v1/external/orders/create/adhoc';
			$this->walletBalanceApiUrl = 'https://apiv2.shiprocket.in/v1/external/account/details/wallet-balance';
			$this->generateAWBApiUrl = 'https://apiv2.shiprocket.in/v1/external/courier/assign/awb';
			$this->generateLabelApiUrl = 'https://apiv2.shiprocket.in/v1/external/courier/generate/label';

        }

        private function generateToken() {
            $transient_name = 'es_shiprocket_token';
            $token = get_transient($transient_name);
        
            if (false === $token) {
                $url = $this->genrateTokenApiUrl;
                $username = get_option('shiprocket_username');
                $password = get_option('shiprocket_password');
        
                $body = json_encode(array(
                    "email" => $username,
                    "password" => $password
                ), true);
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
        
                if ($token_data && property_exists($token_data, 'token')) {
                    $token = $token_data->token;
                    $expiration = 9 * DAY_IN_SECONDS;
                    set_transient($transient_name, $token, $expiration);
        
                    return array(
                        'success' => true,
                        'token' => $token,
                    );
                } else {
                    return array(
                        'success' => false,
                        'message' => 'Error: ' . $token_data['message'],
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
                'Authorization' => 'Bearer ' . $token,
            );
            $response = wp_remote_get($url, array(
                'headers' => $header_data,
                'timeout' => 10,
            ));
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Error: ' . $response->get_error_message(),
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
                'Authorization' => 'Bearer ' . $token
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
            $url = $this->trackingApiUrl . $awb;
            $result = $this->getRequest($url);
            if (!$result['success']) {
                return array(
                    'success' => false,
                    'message' => $result['message'],
                );
            }
            $response = $result['result'];
            if (empty($response['tracking_data']['track_status'])) {
                return array(
                    'success' => false,
                    'message' => $response['tracking_data']['error'],
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
            $shipmentStatus = $apiResponse['tracking_data']['shipment_track'][0];
            $shipmentProgress  = $apiResponse['tracking_data']['shipment_track_activities'];

            $trackingModel = new TrackingModel(
                $shipmentStatus['edd'],
                $shipmentStatus['current_status'],
                $shipmentStatus['courier_name'],
                $shipmentStatus['pickup_date'],
                $shipmentStatus['awb_code'],
                $apiResponse['tracking_data']['track_url'],
            );

            foreach ($shipmentProgress as $scan) {
                $trackingModel->addShipmentProgress(
                    $scan['date'],
                    $scan['sr-status-label'],
                    $scan['activity'],
                    $scan['location']
                );
            }

            return $trackingModel;
        }
		
		// Start show popup bulk rate 
        public function getShippingRate($order_ID, $orderWeight) {

			$order = wc_get_order($order_ID);
			$getPaymentMode = ESShippingFunction::check_payment_mode($order->get_payment_method_title());

			if ($getPaymentMode == 'prepaid') {
				$paymentMode = 1; // 1 for prepaid
			} else {
				$paymentMode = 0; // 0 for cod
			}

			$pickupPostcode = WC()->countries->get_base_postcode();
			$deliveryPostcode = $order->get_billing_postcode();
			$codValue = $order->get_total();
			
            $weight = $orderWeight / 1000;
            $codValue = $order->get_total();
			
            $url = $this->getRateApiUrl . 'pickup_postcode=' . $pickupPostcode . '&delivery_postcode=' . $deliveryPostcode . '&cod=' . $paymentMode . '&weight=' . $weight . '&declared_value=' . $codValue;

			$fetchedRateResponse = $this->getRequest($url);
			if(!$fetchedRateResponse['success']){
				// Prepare the response array
				$response = array(
					'success' => false,  // Assuming the request was successful
					'message' => $fetchedRateResponse['message'],
				);
				return $response;
			} else {
				$list_of_couriers = $fetchedRateResponse['result']['data']['available_courier_companies'];
				$results = [];

				foreach ($list_of_couriers as $courier) {
					$results[] = [
						'courier_id' => $courier['courier_company_id'],
						'courier_name' => $courier['courier_name'],
						'courier_price' => $courier['rate']
					];
				}
			}
			
			// Prepare the response array
			$response = array(
				'success' => true,  // Assuming the request was successful
				'message' => 'Shipping rate fetched successfully',
				'result' => $results  // The shipping rate result from the API
			);
			return $response;
        }

		// Shipping code start here
		public function prepareOrderItemsData($orderID){
			$order = wc_get_order( $orderID );
			$items = $order->get_items();
			$orderItems = array();
			foreach ( $items as $item ) {
				$product = $item->get_product();
				$product_name = $item->get_name();
				$product_quantity = $item->get_quantity();
				$product_total = $item->get_total();
				$product_sku = $product->get_sku();
				if(empty($product_sku)){ $product_sku = mt_rand(100000, 999999); }
				$orderItem = array(
					"name" 			=> $product_name,
					"sku" 			=> $product_sku,
					"units" 		=> $product_quantity,
					"selling_price" => $product_total / $product_quantity, //price per item,
					"discount" 		=> "",
					"tax" 			=> "",
					"hsn" 			=> 8240
				);
				$orderItems[] = $orderItem;
			}
			return $orderItems;
		}
		
		public function prepareShipmentData($orderID){

			$order = wc_get_order( $orderID );
			$orderDate = date('Y-m-d H:i', strtotime($order->get_date_created()));
			$getPaymentMode = ESShippingFunction::check_payment_mode($order->get_payment_method_title());
			if ($getPaymentMode == 'prepaid') {
				$paymentMode = 'Prepaid';
			} else {
				$paymentMode = 'COD';
			}
			
			$orderWeight = ESShippingFunction::get_order_weight($orderID);
			$weight = $orderWeight['result']/1000; //weight should be in kgs;
			
			$productDimensions = ESShippingFunction::get_product_dimensions($orderID);

			$itemData 			= $this->prepareOrderItemsData($orderID);

			$orderData = array(
							"order_id" 					=> $orderID,
							"order_date" 				=> $orderDate,
							"pickup_location" 			=> get_option( 'shiprocket_pickup_location' ),
							"channel_id" 				=> get_option( 'shiprocket_channel_id' ),
							"comment" 					=> "",
							"billing_customer_name"		=> $order->get_billing_first_name(),
							"billing_last_name"			=> $order->get_billing_last_name(),
							"billing_address" 			=> $order->get_billing_address_1(),
							"billing_address_2" 		=> $order->get_billing_address_2(),
							"billing_city" 				=> $order->get_billing_city(),
							"billing_pincode" 			=> $order->get_billing_postcode(),
							"billing_state" 			=> $order->get_billing_state(),
							"billing_country" 			=> "India",
							"billing_email" 			=> $order->get_billing_email(),
							"billing_phone" 			=> $order->get_billing_phone(),
							"shipping_is_billing" 		=> true,
							"shipping_customer_name" 	=> "",
							"shipping_last_name" 		=> "",
							"shipping_address" 			=> "",
							"shipping_address_2" 		=> "",
							"shipping_city" 			=> "",
							"shipping_pincode" 			=> "",
							"shipping_country" 			=> "",
							"shipping_state" 			=> "",
							"shipping_email" 			=> "",
							"shipping_phone" 			=> "",
							"order_items" 				=> $itemData,
							"payment_method" 			=> $paymentMode,
							"shipping_charges" 			=> 0,
							"giftwrap_charges" 			=> 0,
							"transaction_charges" 		=> 0,
							"total_discount" 			=> $order->get_discount_total(),
							"sub_total" 				=> $order->get_total(),
							"length" 					=> $productDimensions['length'],
							"breadth" 					=> $productDimensions['width'],
							"height" 					=> $productDimensions['height'],
							"weight" 					=> $weight
						);
			return json_encode($orderData, true);
		}
		
		public function createShipments($orderID, $courierID) {
			
			$url = $this->bookShipmentApiUrl;
            $shipmentData = $this->prepareShipmentData($orderID);
			$response = $this->postRequest($url, $shipmentData);
			if(!$response['success']){
				// Prepare the response array
				return array(
					'success' => false,
					'message' => $response['message'],
				);
			}
			$result = $response['result'];
            return $this->handleCreatedShipmentResponse($result, $courierID);
		}

		public function handleCreatedShipmentResponse($response, $courierID) {
			
			$orderID = $response['channel_order_id'];
			$shipmentID = $response['shipment_id'];

			if(!$shipmentID){
				return 'Error : '. json_encode($response);
			}
			
			$awbResponce = $this->shiprocketGenerateAWB($shipmentID, $courierID);
			if(!$awbResponce['success']){
				// Prepare the response array
				return array(
					'success' => false,
					'message' => $awbResponce['message'],
				);
			}
			$awbData = $awbResponce['result'];
			if (!(is_array($awbData))) {
				return array(
					'success' => false,
					'message' => $awbData,
				);
			}
			$awb = $awbData['response']['data']['awb_code'];
		
			ESCommonFunctions::save_tracking_details_in_meta($orderID, $awb, 'shiprocket');
			$result = [
				'success' => true,
				'message' => 'Order shipped successfully',
			];
			return $result;
		}
		
		public function shiprocketGenerateAWB($shipmentID, $courierID){
			$url   = $this->generateAWBApiUrl;
			$body = json_encode(array(
				"shipment_id" => $shipmentID,
				"courier_id"  => $courierID,
			), true);
			$response = $this->postRequest($url, $body);
			if(!$response['success']){
				// Prepare the response array
				return array(
					'success' => false,
					'message' => $response['message'],
				);
			}
			$result = $response['result'];

			if(empty($result['awb_assign_status'])){
				return array(
					'success' => false,
					'message' =>'Error : ' . $result['response']['data']['awb_assign_error'],
				);
			}
			return array(
				'success' => true,
				'message' => 'Awb generated successfully',
				'result' => $result
				);
		}
		
		public function generateLabel($awb) {
			//get shipment id
			$url = $this->trackingApiUrl . $awb;
            $result = $this->getRequest($url);
            if (!$result['success']) {
                return array(
                    'success' => false,
                    'message' => $result['message'],
                );
            }
            $response = $result['result'];
            if (empty($response['tracking_data']['track_status'])) {
                return array(
                    'success' => false,
                    'message' => $response['tracking_data']['error'],
                );
            }
			$shipmentID = $response['tracking_data']['shipment_track'][0]['shipment_id'];
			
			// Get label
			$url   = $this->generateLabelApiUrl;
			$body = json_encode(array(
				"shipment_id" => [$shipmentID],
			), true);
			$response = $this->postRequest($url, $body);
			if(!$response['success']){
				// Prepare the response array
				return array(
					'success' => false,
					'message' => $response['message'],
				);
			}
			$result = $response['result'];
			if(empty($result['label_created'])){
				return array(
					'success' => false,
					'message' =>'Error : ' . json_encode($result),
				);
			}
			return array(
				'success' => true,
				'message' => 'Label Generated successfully',
				'result' => $result['label_url']
			);
		}
		
		public function shiprocketWalletBallence() {
			$url   = $this->walletBalanceApiUrl;
			$response = $this->getRequest($url);
			if(!$response['success']){
				// Prepare the response array
				return array(
					'success' => false,
					'message' => $response['message'],
				);
			} else {
				return array(
					'success' => true,
					'message' => 'Get wallelt balance successfully',
					'result'  => $response['result']['data']['balance_amount']
				);
			}
		}
    }
}

if (!class_exists('ShiprocketSettings')) {
    class ShiprocketSettings { 
        public function __construct() {
            add_action('init', [$this, 'register_settings']);
            add_action('admin_menu', [$this, 'register_menu_page']);
        }
        public function register_settings() {
            // Shiprocket settings
            register_setting('easyship-shiprocket-group', 'shiprocket_enable');
            register_setting('easyship-shiprocket-group', 'shiprocket_username');
            register_setting('easyship-shiprocket-group', 'shiprocket_password');
            register_setting('easyship-shiprocket-group', 'shiprocket_pickup_location');
            register_setting('easyship-shiprocket-group', 'shiprocket_channel_id');

        }

        public function register_menu_page() {
            add_submenu_page(EASYSHIP_MENU_SLUG, 'Shiprocket settings', 'Shiprocket API', 'manage_options', 'easyship-shiprocket', [$this, 'shiprocket_settings_page']);
        }

        public function shiprocket_settings_page() {
            ?>
            <div class="shiprocket-settings-wrap">
                <h1>Shiprocket Settings</h1>
                <form method="post" action="options.php">
                    <?php settings_fields('easyship-shiprocket-group'); ?>
                    <?php do_settings_sections('easyship-shiprocket-group'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Shiprocket Enable/Disable</th>
                            <td>
                                <input type="checkbox" name="shiprocket_enable" value="1" <?php checked(get_option('shiprocket_enable'), 1); ?> /> Enable Shiprocket
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Shiprocket Username*</th>
                            <td>
                                <input type="text" name="shiprocket_username" value="<?php echo esc_attr(get_option('shiprocket_username')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Shiprocket Password*</th>
                            <td>
                                <input type="password" name="shiprocket_password" value="<?php echo esc_attr(get_option('shiprocket_password')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Pickup Location Name*</th>
                            <td>
                                <input type="text" name="shiprocket_pickup_location" value="<?php echo esc_attr(get_option('shiprocket_pickup_location')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Channel ID</th>
                            <td>
                                <input type="text" name="shiprocket_channel_id" value="<?php echo esc_attr(get_option('shiprocket_channel_id')); ?>" />
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
