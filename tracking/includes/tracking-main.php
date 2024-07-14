<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('ESOrderTracking')) {
	class ESOrderTracking {

		private $es_function;
		private $delhiveryAPI;
		private $shipRocketAPI;
		private $nimbusPostAPI;

		public function __construct() {
			add_shortcode('EASYSHIP-TRACK', array($this, 'easyship_shortcode'));
			// Instantiate the ESTrackingFunction class
			$this->es_function = new ESTrackingFunction();
			$this->delhiveryAPI = new DelhiveryAPI();
			$this->shipRocketAPI = new ShiprocketAPI();
			$this->nimbusPostAPI = new NimbuspostAPI();
		}
	
		public function easyship_shortcode() {
			ob_start();
			if (isset($_GET['order-id'])) {
				$order_ID = htmlspecialchars($_GET['order-id'], ENT_QUOTES, 'UTF-8');
				if ($this->es_function->is_wc_order_id_exists($order_ID)) {
					$this->es_tracking_page($order_ID);
				} else {
					$message = "Please Enter Correct Order ID.";
					echo '<script>alert("' . $message . '");</script>';
					$current_url = remove_query_arg(array_keys($_GET), $_SERVER['REQUEST_URI']);
					$url = site_url() . $current_url;
					echo '<script>window.location.href = "' . $url . '";</script>';
					exit;
				}
			} else {
				echo $this->es_enter_orderid_page();
			}
			return ob_get_clean();
		}
	
		private function es_enter_orderid_page() {
			ob_start();
			// Enqueue the CSS file
			if (!wp_style_is('es-enter-orderid-style', 'enqueued')) {
				wp_enqueue_style('es-enter-orderid-style', EASYSHIP_URL . 'tracking/assets/css/es-enter-orderid.css', [], '1.0', 'all');
			}
			include EASYSHIP_DIR . 'tracking/assets/templates/enter-orderid-page.php';
			return ob_get_clean();
		}
	
		private function es_tracking_page($order_ID) {
			//common
			$order = wc_get_order($order_ID);

			// Usage
			$shipping_details = $this->get_shipping_details($order_ID);
			$es_awb_no = $shipping_details['es_awb_no'];
			$es_courier_name = $shipping_details['es_courier_name'];
			
			
			$error;
			$easyshipLogo = EASYSHIP_URL . 'tracking/assets/img/easyship.png';

			//variable for Order Details
			$order = wc_get_order($order_ID);
			$items = $order->get_items();
			$order_date_raw = $order->get_date_created();
			$order_date = $order_date_raw->date_i18n('d, F Y');

			if (($es_courier_name == 'delhivery') || ($es_courier_name == 'DL')) {
				$response = $this->delhiveryAPI->getTrackingData($es_awb_no);
				if(!$response['success']){
					$error = $response['message'];
				}
			} elseif (($es_courier_name == 'shiprocket') || ($es_courier_name == 'SR')) {
				$response = $this->shipRocketAPI->getTrackingData($es_awb_no);
				if(!$response['success']){
					$error = $response['message'];
				}
			} elseif (($es_courier_name == 'nimbuspost') || ($es_courier_name == 'NB')) {
				$response = $this->nimbusPostAPI->getTrackingData($es_awb_no);
				if(!$response['success']){
					$error = $response['message'];
				}
			} elseif (!empty($es_awb_no) && !empty($es_courier_name)) {
				$response = array(
					'success' => false,
					'message' => 'No Caurier Match but awb present',
					'result'  => new TrackingModel('NA', 'In Transit', $es_courier_name, 'NA', $es_awb_no, '#', [])
				);
			}

			//Variable for order Shipment Status
			if(isset($response['result'])) {
				$trackingData = $response['result'];
			} else {
				$trackingData = new TrackingModel('NA', 'Not Shipped', 'NA', 'NA', 'NA', '#', []);
			}
			
			$expectedDeliveryDate 	= $trackingData->expectedDeliveryDate ?? 'NA';
			$shipmentStatus 		= $trackingData->shipmentStatus ?? 'NA';
			$shipThrough 			= $trackingData->shipThrough ?? 'NA';
			$shippedDate 			= $trackingData->shippedDate ?? 'NA';
			$awbNumber 				= $trackingData->awbNumber ?? 'NA';
			$trackingLink 			= $trackingData->trackingLink ?? '#';
			$shipmentProgress		= $trackingData->shipmentProgress ?? [];

			$tracking_status = $this->getDeliveryStatusCode($shipmentStatus);
			
			// Enqueue the CSS file
			if (!wp_style_is('es-tracking-page-style', 'enqueued')) {
				wp_enqueue_style('es-tracking-page-style', EASYSHIP_URL . 'tracking/assets/css/es-tracking-page.css', [], '1.0', 'all');
			}
			include EASYSHIP_DIR . 'tracking/assets/templates/tracking-page.php';
		}
		
		private function getDeliveryStatusCode($status) {
			$cancelled 		= array("cancelled");
			$pending_pickup = array("Pickup Generated", "Manifested", "booked", "pending pickup", "Label Generated");
			$in_Transit 	= array("In Transit", "in transit", "Out For Delivery", "out for delivery", "Dispatched", "Pending");
			$delivered 		= array("Delivered", "delivered");
			$return 		= array("RTO", "return");
			
				 if(in_array($status, $cancelled))		{ return 0; } //for order Cancelled
			else if(in_array($status, $pending_pickup))	{ return 2; } //for order Pending Pickup
			else if(in_array($status, $in_Transit))		{ return 3; } //for order In-Transit
			else if(in_array($status, $delivered))		{ return 4; } //for order Delivered
			else if(in_array($status, $return))			{ return 5; } //for order Return
			else 										{ return 1; } //for order Booked
		}
		
		private function read_db_data($order_ID, $value){
			global $wpdb;
			$table_name = $wpdb->prefix . 'easyship_db';
			$result = $wpdb->get_var( $wpdb->prepare( "SELECT $value FROM $table_name WHERE order_number = %d", $order_ID ) );
			// Check if a Value was found
			if ( !$result ) {
				return '';
			} else {
				return $result;
			}
		}
		
		private function get_shipping_details($order_ID) {
			$es_awb_no = $this->read_db_data($order_ID, 'awb_number');
			$es_courier_name = $this->read_db_data($order_ID, 'shipped_through');

			// If the values are not found in the database, try to get them from the order metadata
			if (empty($es_awb_no) || empty($es_courier_name)) {
				$es_awb_no = get_post_meta( $order_ID, ES_AWB_META, true );
				$es_courier_name = get_post_meta( $order_ID, ES_COURIER_NAME_META, true );
			}
			return [
				'es_awb_no' => $es_awb_no,
				'es_courier_name' => $es_courier_name
			];
		}
	}
}

?>