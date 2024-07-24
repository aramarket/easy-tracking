<?php
// common-functions.php

if (!class_exists('ESCommonFunctions ')) {
    class ESCommonFunctions  {
		
		public static function active_courier_list() {
			$courier_list = []; // Initialize the array

			// Check if each courier option is enabled and add to the array if true
			if(get_option('shiprocket_enable')) {
				$courier_list[] = "shiprocket";
			}
			if(get_option('delhivery_enable')) {
				$courier_list[] = "delhivery";
			}
			if(get_option('nimbuspost_enable')) {
				$courier_list[] = "nimbuspost";
			}

			return $courier_list; // Return the list of active couriers
		}
		
		public static function es_wa_simplify_order_status($status) {
			// Remove 'wc-' prefix if present
			if (strpos($status, 'wc-') === 0) {
				return substr($status, 3);
			}
			// If 'wc-' prefix is not present, return status as is
			return $status;
		}
		
        public static function get_tracking_url($order_id) {
            $get_page_id = esc_attr(get_option('selected_tracking_page'));
			if($get_page_id) {
				return get_permalink($get_page_id) . '?order-id=' . $order_id;
            } 
        }

		public static function save_tracking_details_in_meta($order_ID, $awb, $courier) {
			$order = wc_get_order($order_ID);
			update_post_meta($order_ID, ES_AWB_META , $awb);
			update_post_meta($order_ID, ES_COURIER_NAME_META, $courier);
			$tracking_link = ESCommonFunctions::get_tracking_url($order_ID);
			if($tracking_link){
				$order->add_order_note('Tracking Link - <a target="_blank" href="' . $tracking_link . '">' . $tracking_link . '</a>', true);
			} else{
				$order->add_order_note('Please select tracking page - <a target="_blank" href="' . EASYSHIP_MAIN_URL . '">' . 'click here' . '</a>'); 
			}
			$status = self::es_wa_simplify_order_status(get_option( 'after_ship_status' ));
			$order->update_status( $status );
		}
		
		public static function read_db_data($order_ID, $value){
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
		
		public static function get_tracking_details($order_ID) {
			// Retrieve tracking details from the database
			$es_awb_no = self::read_db_data($order_ID, 'awb_number');
			$es_courier_name = self::read_db_data($order_ID, 'shipped_through');

			// If the values are not found in the database, try to get them from the order metadata
			if (empty($es_awb_no) || empty($es_courier_name)) {
				$es_awb_no = get_post_meta($order_ID, ES_AWB_META, true);
				$es_courier_name = get_post_meta($order_ID, ES_COURIER_NAME_META, true);
			}

			// Check if tracking details are available
			if (empty($es_awb_no) || empty($es_courier_name)) {
				return [
					'success' => false,
					'message' => 'No tracking details available',
				];
			}

			// Return the array with success message and result
			return [
				'success' => true,
				'message' => 'Fetch get request successfully',
				'result'  => [
						'es_awb_no' => $es_awb_no,
						'es_courier_name' => $es_courier_name
					]
			];
		}
		
		public static function replaceSpecialChars($str, $replace = ' ') {
            return preg_replace('/[^A-Za-z0-9]/', $replace, $str);
        }
		
		public static function extractPhoneNumber($phone) {
			// Convert input to string if it is not already
			$phone = strval($phone);

			// Remove any non-numeric characters
			$cleaned_number = preg_replace('/[^0-9]/', '', $phone);

			// If the cleaned number is more than 10 digits, get the last 10 digits
			if (strlen($cleaned_number) > 10) {
				$cleaned_number = substr($cleaned_number, -10);
			}

			// Check if the cleaned input has fewer than 10 digits
			if (strlen($cleaned_number) < 10) {
				return array(
					'success' => false,
					'message' => 'Invalid phone number'
				);
			}

			return array(
				'success' => true,
				'message' => 'Phone number cleaned successfully',
				'result'  => $cleaned_number
			);
		}
		
		public static function make_string_ellipsis($string, $number) {
			$words = explode(' ', $string); // Split the string into an array of words
			if (count($words) > $number) {
				$shortened_words = array_slice($words, 0, $number); // Take the specified number of words
				$shortened_string = implode(' ', $shortened_words) . '...'; // Output the shortened string with ellipsis
			} else {
				$shortened_string = $string; // Return the original string if it's shorter than the limit
			}
			return $shortened_string;
		}
	}

}
