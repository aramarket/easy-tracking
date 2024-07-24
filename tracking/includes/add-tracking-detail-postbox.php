<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'AddTrackingDetailPostbox' ) ) {
    class AddTrackingDetailPostbox {

        public function __construct() {
            add_action( 'add_meta_boxes', array( $this, 'tracking_box_content_postbox' ) );
            add_action( 'save_post', array( $this, 'tracking_box_handle_submit' ), 10, 2 );
        }

        public function tracking_box_content_postbox() {
            add_meta_box(
                'add-tracking-detail-postbox',
                __( 'Add Tracking Details', 'mhkh' ),
                array( $this, 'tracking_box_content' ),
                'shop_order',
                'side', //side, normal, or advanced
                'default' //high, default, low
            );
        }

		public function tracking_box_content($post) {
			$order_id = $post->ID;

			$es_awb_no;
			$es_courier_name;
			$get_tracking_details = ESCommonFunctions::get_tracking_details($order_id);
			if($get_tracking_details['success']){
				$tracking_details = $get_tracking_details['result'];
				$es_awb_no = $tracking_details['es_awb_no'];
				$es_courier_name = $tracking_details['es_courier_name'];
			}

			$courier_list = array('shiprocket', 'delhivery', 'nimbuspost', 'dtdc', 'anjani', 'trackon');

			// Output HTML for custom postbox
			?>
			<div class="tracking-box">
				<p>
					<label for="awb_number"><?php _e('Enter AWB Number'); ?></label>
					<input type="text" name="awb_number" id="awb_number" value="<?php echo esc_attr($es_awb_no); ?>">
				</p>
				<p>
					<label for="courier_name"><?php _e('Courier Company'); ?></label>
					<input list="courier_names" name="courier_name" id="courier_name" value="<?php echo esc_attr($es_courier_name); ?>">
					<datalist id="courier_names">
						<?php foreach ($courier_list as $option) : ?>
							<option value="<?php echo esc_attr($option); ?>"></option>
						<?php endforeach; ?>
					</datalist>
				</p>
				<?php wp_nonce_field('tracking_box_nonce', 'tracking_box_nonce_value'); ?>
				<p><button type="submit" class="button button-primary" name="tracking_submit"><?php _e('Save'); ?></button></p>
			</div>
			<?php
		}

		public function tracking_box_handle_submit($order_ID, $order) {
			// Check if nonce is set
			if (!isset($_POST['tracking_box_nonce_value'])) {
				return;
			}

			// Verify nonce
			if (!wp_verify_nonce($_POST['tracking_box_nonce_value'], 'tracking_box_nonce')) {
				return;
			}

			// Check if user has permissions to save
			if (!current_user_can('edit_post', $order_ID)) {
				return;
			}

			// Save custom field values
			if (isset($_POST['tracking_submit'])) {
				if (isset($_POST['awb_number']) && isset($_POST['courier_name'])) {
					$awb = sanitize_text_field($_POST['awb_number']);
					$courier = sanitize_text_field($_POST['courier_name']);
					ESCommonFunctions::save_tracking_details_in_meta($order_ID, $awb, $courier);
				} else {
					// Assuming you have detected an error and need to display a message
					$error_msg = 'There was an error updating the order. Please try again.';
					// Add an error notice to the session
					wc_add_notice($error_msg, 'error');
				}
			}
		}
		
    }
}

