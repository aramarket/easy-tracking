<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'AddTrackingDetailPostbox' ) ) {
    class AddTrackingDetailPostbox {

        public function __construct() {
            add_action( 'add_meta_boxes', array( $this, 'add_tracking_detail_postbox' ) );
            add_action( 'save_post', array( $this, 'save_tracking_values' ), 10, 2 );
        }

        public function add_tracking_detail_postbox() {
            add_meta_box(
                'add-tracking-detail-postbox',
                __( 'Add Tracking Details', 'mhkh' ),
                array( $this, 'tracking_box_content' ),
                'shop_order',
                'side', //side, normal, or advanced
                'high' //high, default, low
            );
        }

        //easyship_awb_no, easyship_courier_name = delhivery, shiprocket
        public function tracking_box_content( $post ) {
            echo 'hi';
            // Get order ID
            // $order_id = $post->ID;

            // // Get custom field values
            // $selected_option = get_post_meta( $order_id, '_selected_option', true );
            // $awb_field_name = get_post_meta( $order_id, '_custom_awb_number', true );
            // $custom_field_options = array('Select Courier Company', 'Shiprocket', 'Delhivery', 'NimbusPost');
            // // Output HTML for custom postbox

        }

        public function save_tracking_values( $post_id, $post ) {
            // Check if nonce is set
            if ( ! isset( $_POST['custom_order_box_nonce'] ) ) {
                return;
            }
            // Verify nonce
            if ( ! wp_verify_nonce( $_POST['custom_order_box_nonce'], 'custom_order_box' ) ) {
                return;
            }
            // Check if user has permissions to save
            if ( ! current_user_can( 'edit_post', $order_ID ) ) {
                return;
            }
            // Save custom field values
            if (isset($_POST['custom_order_submit'])) {
                if ((isset( $_POST['custom_awb_number']))&&(isset( $_POST['selected_option']))) {
                    $awb = sanitize_text_field( $_POST['custom_awb_number'] );
                    $company = sanitize_text_field( $_POST['selected_option'] );
                    $shipping_cost = sanitize_text_field( $_POST['custom_shipping_cost'] );
                    update_post_meta($order_ID, '_custom_awb_number', $awb );
                    update_post_meta( $order_ID, '_selected_option', $company );
                    if($company == 'Delhivery'){ $company_initial = 'DL'; }
                    if($company == 'NimbusPost'){ $company_initial = 'NB'; }
                    if($company == 'Shiprocket'){ $company_initial = 'SR'; }
                    es_custom_box_created_handle($order_ID, $awb, $company_initial, $company);
                }else{
                // Assuming you have detected an error and need to display a message
                    $error_msg = 'There was an error updating the order. Please try again.';
                    // Add an error notice to the session
                    wc_add_notice( $error_msg, 'error' );
                }
            }
        }
    }
}

