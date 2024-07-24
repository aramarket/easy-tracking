<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('ESOrderTracking')) {
	class ESOrderTracking {

		private $delhiveryAPI;
		private $shipRocketAPI;
		private $nimbusPostAPI;

		public function __construct() {
			add_shortcode('EASYSHIP-TRACK', array($this, 'easyship_shortcode'));
			// Instantiate the ESTrackingFunction class
			$this->delhiveryAPI = new DelhiveryAPI();
			$this->shipRocketAPI = new ShiprocketAPI();
			$this->nimbusPostAPI = new NimbuspostAPI();
		}
	
		public function easyship_shortcode() {
			ob_start();
			if (isset($_GET['order-id'])) {
				$order_ID = htmlspecialchars($_GET['order-id'], ENT_QUOTES, 'UTF-8');
				$order = wc_get_order($order_ID); 
				if ($order) {
					$this->es_tracking_page($order_ID, false);
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
	
		public function es_tracking_page($order_ID, $return_tracking_status) {
			//common
			$order = wc_get_order($order_ID);

			// Usage
			$es_awb_no;
			$es_courier_name;
			$get_tracking_details = ESCommonFunctions::get_tracking_details($order_ID);
			if($get_tracking_details['success']){
				$tracking_details = $get_tracking_details['result'];
				$es_awb_no = $tracking_details['es_awb_no'];
				$es_courier_name = $tracking_details['es_courier_name'];
			}
			
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
			
			if($return_tracking_status) {
				return $tracking_status;
			} else {
				// Enqueue the CSS file
				if (!wp_style_is('es-tracking-page-style', 'enqueued')) {
					wp_enqueue_style('es-tracking-page-style', EASYSHIP_URL . 'tracking/assets/css/es-tracking-page.css', [], '1.0', 'all');
				}
				include EASYSHIP_DIR . 'tracking/assets/templates/tracking-page.php';
			}
		}
		
		public function getDeliveryStatusCode($status) {
			$cancelled 		= array("cancelled");
			$pending_pickup = array("Pickup Generated", "Manifested", "booked", "pending pickup", "Label Generated", "pending-pickup");
			$in_Transit 	= array("In Transit", "in transit", "Out For Delivery", "out for delivery", "Dispatched", "Pending", "intransit");
			$delivered 		= array("Delivered", "delivered");
			$return 		= array("RTO", "return");
			
				 if(in_array($status, $cancelled))		{ return 0; } //for order Cancelled
			else if(in_array($status, $pending_pickup))	{ return 2; } //for order Pending Pickup
			else if(in_array($status, $in_Transit))		{ return 3; } //for order In-Transit
			else if(in_array($status, $delivered))		{ return 4; } //for order Delivered
			else if(in_array($status, $return))			{ return 5; } //for order Return
			else 										{ return 1; } //for order Booked
		}
		
	}
}

?>