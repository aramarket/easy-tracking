<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('DelhiveryAPI')) {
    class DelhiveryAPI { 
        
        private $token;
        private $trackingUrl;
        private $trackingApiUrl;
		private $getRateApiUrl;
		private $genrateAwbApiUrl;
		private $bookShipmentApiUrl;
		private $generatedLabelApiUrl;

        public function __construct() {
            $this->token = get_option('delhivery_token');
			//	Tracking
            $this->trackingUrl = 'https://www.delhivery.com/track/package/';
            $this->trackingApiUrl = 'https://track.delhivery.com/api/v1/packages/json/?waybill=';
			//	Get rate
			$this->getRateApiUrl  = 'https://track.delhivery.com/api/kinko/v1/invoice/charges/.json?md=E&ss=Delivered&';
			//	Booking shipment
			$this->genrateAwbApiUrl  = 'https://track.delhivery.com/waybill/api/bulk/json/?count=';
			$this->bookShipmentApiUrl  = 'https://track.delhivery.com/api/cmu/create.json';
			$this->generatedLabelApiUrl  = 'https://track.delhivery.com/api/p/packing_slip?pdf=true&wbns=';
			
        }

        // Start Tracking code
        private function getRequest($url) {
            $headers = array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Token ' . $this->token
            );
        
            $response = wp_remote_get($url, array('headers' => $headers, 'timeout' => 20));
        
            $body = wp_remote_retrieve_body($response);
            $status_code = wp_remote_retrieve_response_code($response);
        
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Error: ' . $response->get_error_message(),
                );
            }
        
            if ($status_code !== 200) {
                return array(
                    'success' => false,
                    'message' => 'Error: Received status code ' . $status_code . ' - ' . $body,
                );
            }
        
            $decoded_body = json_decode($body, true);
            if ($decoded_body === null) {
                return array(
                    'success' => false,
                    'message' => 'Error: Invalid JSON response',
                );
            }
        
            return array(
                'success' => true,
                'message' => 'Fetch get request successfully',
                'result'  => $decoded_body
            );
        }	

        public function getTrackingData($awb) {
            // $awb = $this->getAWBFromOrderId($orderID);
            $url = $this->trackingApiUrl . $awb;
            $result = $this->getRequest($url);
            if(!$result['success']){
                return array(
                    'success' => false,
                    'message' => $result['message'],
                );
            }
            $response = $result['result'];
            if (isset($response['Success']) && !$response['Success']) {
                return array(
                    'success' => false,
                    'message' => $response['rmk'],
                );
            }
            $trackingData = $this->mapApiResponseToModel($response);
            return array(
                'success' => true,
                'message' => 'Fetch tracking data successfully',
                'result'  => $trackingData
            );
        }

        private function mapApiResponseToModel($apiResponse) {
            $shipmentStatus = $apiResponse['ShipmentData'][0]['Shipment'];
            $shipmentProgress  = $shipmentStatus['Scans'];

            $trackingModel = new TrackingModel(
                $shipmentStatus['ExpectedDeliveryDate'],
                $shipmentStatus['Status']['Status'],
                'Delhivery',
                $shipmentStatus['PickUpDate'],
                $shipmentStatus['AWB'],
                $this->trackingUrl.$shipmentStatus['AWB'],
            );
            $scans = array_reverse($shipmentProgress);
            foreach ($scans as $scan) {
                $scanDetail = $scan['ScanDetail'];
                $trackingModel->addShipmentProgress(
                    $scanDetail['ScanDateTime'],
                    $scanDetail['Scan'],
                    $scanDetail['Instructions'],
                    $scanDetail['ScannedLocation']
                );
            }

            return $trackingModel;
        }
        // End of Tracking code

        // Start code for show popup rate list
		public function getShippingRate($order_ID, $orderWeight) {
			$order = wc_get_order($order_ID);
			$getPaymentMode = ESShippingFunction::check_payment_mode($order->get_payment_method_title());

			if ($getPaymentMode == 'prepaid') {
				$paymentMode = 'Pre-paid';
			} else {
				$paymentMode = 'COD';
			}

			$basePin = WC()->countries->get_base_postcode();
			$destinationPin = $order->get_billing_postcode();
			$codValue = $order->get_total();

			$url = $this->getRateApiUrl.'d_pin='.$destinationPin.'&o_pin='.$basePin.'&cgm='.$orderWeight . '&pt=' . $paymentMode . '&cod=' . $codValue;

			$response = $this->getRequest($url);
			if(!$response['success']){
				// Prepare the response array
				return array(
					'success' => false,  // Assuming the request was successful
					'message' => $response['message'],
				);
			} else {
				$results[] = [
					'courier_id' => '1', //optional
					'courier_name' => 'Delhivery',
					'courier_price' => $response['result'][0]['total_amount']
				];
			}
			
			// Prepare the response array
			return array(
				'success' => true,  // Assuming the request was successful
				'message' => 'Shipping rate fetched successfully',
				'result' => $results  // The shipping rate result from the API
			);
		}
		// End code for show popup rate list
		
		//Start code for shipping
		private function genrateAwbs($count) {
			$url = $this->genrateAwbApiUrl . $count;
			$response = $this->getRequest($url);

			if (!$response['success']) {
				// Prepare the response array for failure case
				return array(
					'success' => false,
					'message' => 'Error - ' . $response['message'],
				);
			} 

			// Prepare the response array for success case
			return array(
				'success' => true,
				'message' => 'Generated AWBs successfully',
				'result' => $response['result']
			);
		}
		
		private function productTitlesCombined($order) {
			$items = $order->get_items();
			$orderTitle = '';
			$counter = 0;
			
			foreach ($items as $item) {
				$counter ++;
				$product = $item->get_product();
				$productName = ESCommonFunctions::replaceSpecialChars( $product->get_name());
				$quantity = $item->get_quantity();
				$serial = $product->get_sku(); // Assuming SKU is used as the serial number
				 // - | ()  these are tested
				$orderTitle .= "Item {$counter}: (Qnty x {$quantity}) {$productName} || ";
			}

			return $orderTitle;
		}
		
		public function prepareShipmentData($orderIDs) {
			$awbResponse = $this->genrateAwbs(count($orderIDs));

			if (!$awbResponse['success']) {
				return array(
					'success' => false,
					'message' => $awbResponse['message'],
				);
			}
			$awbString = $awbResponse['result'];
            $awbs = array_map(function ($value) {return str_replace('"', '', $value);}, explode(",", $awbString));
            $awbIndex = 0;
            $shipmentData = [
                "shipments" => [],
                "pickup_location" => ["name" => get_option('delhivery_pickup_location')]
            ];
			
            foreach ($orderIDs as $orderID) {
                $awb = $awbs[$awbIndex++];
                $order = wc_get_order($orderID);
                $orderDate = date('Y-m-d H:i', strtotime($order->get_date_created()));
				
				$getPaymentMode = ESShippingFunction::check_payment_mode($order->get_payment_method_title());
				if ($getPaymentMode == 'prepaid') {
					$paymentMode = 'Pre-paid';
				} else {
					$paymentMode = 'COD';
				}
                $productTitlesCombined = $this->productTitlesCombined($order);
				$order_weight_response = ESShippingFunction::get_order_weight($orderID);
				$orderWeight = $order_weight_response['result'];
                $productDimensions = ESShippingFunction::get_product_dimensions($orderID);
				$phoneResponce = ESCommonFunctions::extractPhoneNumber($order->get_billing_phone());
				if($phoneResponce['success']) {
					$phone = $phoneResponce['result'];
				} else{
					$phone = 0000000000;
				}
                $shipmentData["shipments"][] = [
                    "name" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    "add" => ESCommonFunctions::replaceSpecialChars($order->get_billing_address_1()) 
							. ' ' . ESCommonFunctions::replaceSpecialChars($order->get_billing_address_2()),
                    "pin" => $order->get_billing_postcode(),
                    "city" => $order->get_billing_city(),
                    "state" => $order->get_billing_state(),
                    "country" => "India",
                    "phone" => $phone,
                    "order" => $orderID,
                    "payment_mode" => $paymentMode,
                    "products_desc" => $productTitlesCombined,
                    "cod_amount" => $order->get_total(),
                    "order_date" => $orderDate,
                    "total_amount" => $order->get_total(),
                    "quantity" => $order->get_item_count(),
                    "shipment_length" => $productDimensions['length'],
                    "shipment_width" => $productDimensions['width'],
                    "shipment_height" => $productDimensions['height'],
                    "weight" => $orderWeight,
                    "shipping_mode" => "Express",
                    "address_type" => "home",
                    "waybill" => $awb,
                    "source" => "Woocommerce"
                ];
            }

            return json_encode($shipmentData, true);
        }
		
        public function createShipments($orderIDs) {
            $url = $this->bookShipmentApiUrl;
            $shipmentData = $this->prepareShipmentData($orderIDs);
			
            $headers = array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Token ' . $this->token
            );

            $response = wp_remote_post($url, array(
                'headers' => $headers,
                'body' => 'format=json&data=' . $shipmentData,
                'timeout' => count($orderIDs) * 5
            ));
			
			$body = wp_remote_retrieve_body($response);
            $status_code = wp_remote_retrieve_response_code($response);
        
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => 'Error: ' . $response->get_error_message(),
                );
            }
        
            if ($status_code !== 200) {
                return array(
                    'success' => false,
                    'message' => 'Error: Received status code ' . $status_code . ' - ' . $body,
                );
            }
        
            $decoded_body = json_decode($body, true);
            if ($decoded_body === null) {
                return array(
                    'success' => false,
                    'message' => 'Error: Invalid JSON response',
                );
            }

            return $this->handleCreatedShipmentResponse($decoded_body);
        }


        private function handleCreatedShipmentResponse($response) {
            $shipErrors = [];
            $counter = 0;
			if (!isset($response['package_count'])) {
				return [
					'success' => false,
					'message' => 'Error: ' . json_encode($response),
					'result'  => []
				];
            }
			
            foreach ($response['packages'] as $package) {
                $orderID = $package['refnum'];
                $awb = $package['waybill'];

                if ($package['status'] == "Success") {
					$counter++;
					ESCommonFunctions::save_tracking_details_in_meta($orderID, $awb, 'delhivery');
                } else {
                    $shipErrors[$orderID] = ' Error - ' . json_encode($package['remarks']);
                }
            }

// 			$totalSuccess = "Shipped " . $counter . " out of " . $response['package_count'] . "\n \n";
// 			array_unshift($shipErrors, $totalSuccess);
			
			if(empty($shipErrors)) {
				$result = [
					'success' => true,
					'message' => 'Order shipped successfully',
				];
			} else {
				$result = [
					'success' => false,
					'message' => $shipErrors,
				];
			}

			return $result;
        }
		
        public function generateLabel($awb) {
            $url = $this->generatedLabelApiUrl . $awb;
            $response = $this->getRequest($url);
			if (!$response['success']) {
				// Prepare the response array for failure case
				return array(
					'success' => false,
					'message' => 'Error - ' . $response['message'],
				);
			} 

			if (!$response['result']['packages_found']) {
				return array(
					'success' => false,
					'message' => 'Error: Wrong awb - ' . $awb,
				);
            }
			
			// Prepare the response array for success case
			return array(
				'success' => true,
				'message' => 'Label Generated successfully',
				'result' => $response['result']['packages'][0]['pdf_download_link']
			);
        }
    }
}

if (!class_exists('DelhiverySettings')) {
    class DelhiverySettings { 
        public function __construct() {
            add_action('init', [$this, 'register_settings']);
            add_action('admin_menu', [$this, 'register_menu_page']);
        }
        public function register_settings() {
            // Delhivery settings
            register_setting('easyship-delhivery-group', 'delhivery_enable');
            register_setting('easyship-delhivery-group', 'delhivery_token');
            register_setting('easyship-delhivery-group', 'delhivery_pickup_location');

        }

        public function register_menu_page() {
            add_submenu_page(EASYSHIP_MENU_SLUG, 'Delhivery settings', 'Delhivery API', 'manage_options', 'delhivery-nimbuspost', [$this, 'delhivery_settings_page']);
        }

        public function delhivery_settings_page() {
            ?>
            <div class="wrap">
                <h1>Delhivery Settings</h1>
                <form method="post" action="options.php">
                    <?php settings_fields('easyship-delhivery-group'); ?>
                    <?php do_settings_sections('easyship-delhivery-group'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Delhivery Enable/Disable</th>
                            <td>
                                <input type="checkbox" name="delhivery_enable" value="1" <?php checked(get_option('delhivery_enable'), 1); ?> /> Enable Delhivery
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Delhivery Token</th>
                            <td>
                                <input type="text" name="delhivery_token" value="<?php echo esc_attr(get_option('delhivery_token')); ?>" />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Pickup Location Name</th>
                            <td>
                                <input type="text" name="delhivery_pickup_location" value="<?php echo esc_attr(get_option('delhivery_pickup_location')); ?>" />
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
