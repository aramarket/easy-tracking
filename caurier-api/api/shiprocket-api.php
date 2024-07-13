<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('ShiprocketAPI')) {
    class ShiprocketAPI {
        private $token;
        private $trackingApi;
        private $genrateTokenApi;

        public function __construct() {
            $this->trackingApi = 'https://apiv2.shiprocket.in/v1/external/courier/track/awb/';
            $this->genrateTokenApi = 'https://apiv2.shiprocket.in/v1/external/auth/login';
        }

        private function generateToken() {
            $transient_name = 'es_shiprocket_token';
            $token = get_transient($transient_name);
        
            if (false === $token) {
                $url = $this->genrateTokenApi;
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
            $url = $this->trackingApi . $awb;
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

        public function getShippingRate($orderID, $orderWeight) {
            $order = wc_get_order($orderID);
            $pickupPostcode = WC()->countries->get_base_postcode();
            $deliveryPostcode = $order->get_billing_postcode();
            $paymentMode = intval($this->checkPaymentMode($order->get_payment_method_title(), 'SR1'));
            $weight = $orderWeight / 1000;
            $codValue = $order->get_total();
            $url = 'https://apiv2.shiprocket.in/v1/external/courier/serviceability/?pickup_postcode=' . $pickupPostcode . '&delivery_postcode=' . $deliveryPostcode . '&cod=' . $paymentMode . '&weight=' . $weight . '&declared_value=' . $codValue;

            return $this->getRequest($url);
        }

        public function generateLabel($shipmentId) {
            $url = 'https://apiv2.shiprocket.in/v1/external/courier/generate/label';
            $body = json_encode(array(
                "shipment_id" => [$shipmentId],
            ), true);
            $result = $this->postRequest($url, $body);
            if (!$result['success']) {
                return 'Error: ' . $result['message'];
            }
            $response = $result['result'];
            if (empty($response['label_created'])) {
                return 'Error: label - ' . json_encode($response);
            }
            return stripslashes($response['label_url']);
        }

        public function generateAWB($shipmentId, $shipBy) {
            $url = 'https://apiv2.shiprocket.in/v1/external/courier/assign/awb';
            $body = json_encode(array(
                "shipment_id" => $shipmentId,
                "courier_id" => $shipBy,
            ), true);
            $result = $this->postRequest($url, $body);
            if (!$result['success']) {
                return 'Error: ' . $result['message'];
            }
            $response = $result['result'];
            if (empty($response['awb_assign_status'])) {
                return 'Error: awb - ' . json_encode($response);
            }
            return $response;
        }

        public function getItems($orderID) {
            $order = wc_get_order($orderID);
            $items = $order->get_items();
            $orderItems = array();
            foreach ($items as $item) {
                $product = $item->get_product();
                $productName = $item->get_name();
                $productQuantity = $item->get_quantity();
                $productTotal = $item->get_total();
                $productSku = $product->get_sku();
                if (empty($productSku)) { 
                    $productSku = mt_rand(100000, 999999); 
                }
                $orderItems[] = array(
                    "name" => $productName,
                    "sku" => $productSku,
                    "units" => $productQuantity,
                    "selling_price" => $productTotal / $productQuantity,
                    "discount" => "",
                    "tax" => "",
                    "hsn" => 8240
                );
            }
            return $orderItems;
        }

        public function insertAWBData($orderID, $awbResponse, $labelUrl) {
            $order = wc_get_order($orderID);
            $orderWeight = $this->getOrderWeight($orderID);

            $awbData = new ES_db_data_format();
            $awbData->order_number = $orderID;
            $awbData->order_status = $order->get_status();
            $awbData->awb = $awbResponse['awb_code'];
            $awbData->awb_status = $awbResponse['status'];
            $awbData->awb_courier_company_id = $awbResponse['courier_id'];
            $awbData->awb_label_url = $labelUrl;
            $awbData->awb_created_at = current_time('mysql');
            $awbData->awb_updated_at = current_time('mysql');
            $awbData->awb_weight = $orderWeight;

            $awbData->save();

            return $awbData;
        }

        private function checkPaymentMode($paymentMethodTitle, $defaultMode) {
            if ($paymentMethodTitle === 'Cash on Delivery') {
                return 'SR0';
            }
            return $defaultMode;
        }

        private function getOrderWeight($orderID) {
            $order = wc_get_order($orderID);
            $items = $order->get_items();
            $weight = 0;
            foreach ($items as $item) {
                $product = $item->get_product();
                $productWeight = $product->get_weight();
                $quantity = $item->get_quantity();
                $weight += $productWeight * $quantity;
            }
            return $weight;
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
            add_submenu_page(EASYSHIP_MAIN_URL, 'Shiprocket settings', 'Shiprocket API', 'manage_options', 'easyship-shiprocket', [$this, 'shiprocket_settings_page']);
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
