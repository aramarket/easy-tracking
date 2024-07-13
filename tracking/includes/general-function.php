<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('ESTrackingFunction')) {
	class ESTrackingFunction {

        public function is_wc_order_id_exists($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                return true; // Order ID exists
            } else {
                return false; // Order ID does not exist
            }
        }

		public function make_string_ellipsis($string, $number) {
			$words = explode(' ', $string); // Split the string into an array of words
			if (count($words) > $number) {
				$shortened_words = array_slice($words, 0, $number); // Take the specified number of words
				$shortened_string = implode(' ', $shortened_words) . '...'; // Output the shortened string with ellipsis
			} else {
				$shortened_string = $string; // Return the original string if it's shorter than the limit
			}
			return $shortened_string;
		}
		
		// public function simplify_order_status($status) {
		// 	// Remove 'wc-' prefix if present
		// 	if (strpos($status, 'wc-') === 0) {
		// 		return substr($status, 3);
		// 	}
		// 	// If 'wc-' prefix is not present, return status as is
		// 	return $status;
		// }

		// public function extract_phone_number($input) {
		// 	// Convert input to string if it is not already
		// 	$input = strval($input);

		// 	// Remove any non-numeric characters
		// 	$cleaned_number = preg_replace('/[^0-9]/', '', $input);

		// 	// If the cleaned number is more than 10 digits, get the last 10 digits
		// 	if (strlen($cleaned_number) > 10) {
		// 		$cleaned_number = substr($cleaned_number, -10);
		// 	}

		// 	// Check if the cleaned input has fewer than 10 digits
		// 	if (strlen($cleaned_number) < 10) {
		// 		return array(
		// 			'success' => false,
		// 			'message' => 'Invalid phone number'
		// 		);
		// 	}

		// 	return array(
		// 		'success' => true,
		// 		'message' => 'Phone number cleaned successfully',
		// 		'result'  => $cleaned_number
		// 	);
		// }
		
		// public function remove_special_characters($input) {
		// 	// Convert input to string if it is not already
		// 	$input = strval($input);

		// 	// Remove any character that is not a letter, number, or space
		// 	$cleaned_input = preg_replace('/[^a-zA-Z0-9\s]/', '', $input);

		// 	// Check if the cleaned input is empty
		// 	if (strlen($cleaned_input) == 0) {
		// 		return array(
		// 			'success' => false,
		// 			'message' => 'Input only contains special characters'
		// 		);
		// 	}

		// 	return array(
		// 		'success' => true,
		// 		'message' => 'Special characters removed successfully',
		// 		'result'  => $cleaned_input
		// 	);
		// }

	}
}

?>