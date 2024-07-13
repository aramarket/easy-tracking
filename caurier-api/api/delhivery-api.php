<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('DelhiveryAPI')) {
    class DelhiveryAPI { 
        
        private $token;
        private $trackingUrl;
        private $trackingApi;

        public function __construct() {
            $this->token = get_option('delhivery_token');
            $this->trackingUrl = 'https://www.delhivery.com/track/package/';
            $this->trackingApi = 'https://track.delhivery.com/api/v1/packages/json/?waybill=';
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
            $url = $this->trackingApi . $awb;
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

        // This Shipping code
        public function fetchAWBs($count)
        {
            $url = 'https://track.delhivery.com/waybill/api/bulk/json/?count=' . $count;
            return $this->getRequest($url);
        }

        public function generateLabel($awb)
        {
            $url = 'https://track.delhivery.com/api/p/packing_slip?pdf=true&wbns=' . $awb;
            $response = $this->getRequest($url);
            $label = json_decode($response, true);
            
            if (!$label['packages_found']) {
                return 'Error: Wrong awb - ' . $awb;
            }
            
            return $label['packages'][0]['pdf_download_link'];
        }

        public function getShippingRate($orderID, $orderWeight)
        {
            $order = wc_get_order($orderID);
            $paymentMode = $this->checkPaymentMode($order->get_payment_method_title(), 'DL');
            $basePin = WC()->countries->get_base_postcode();
            $destinationPin = $order->get_billing_postcode();
            $codValue = $order->get_total();

            $url = 'https://track.delhivery.com/api/kinko/v1/invoice/charges/.json?md=E&ss=Delivered&d_pin=' . $destinationPin . '&o_pin=' . $basePin . '&cgm=' . $orderWeight . '&pt=' . $paymentMode . '&cod=' . $codValue;

            return $this->getRequest($url);
        }

        private function getOrderItems($orderID)
        {
            $order = wc_get_order($orderID);
            $items = $order->get_items();
            $orderTitle = '';

            foreach ($items as $item) {
                $orderTitle .= $item->get_product()->get_name() . ' ';
            }

            return $this->replaceSpecialChars($orderTitle, '');
        }

        public function insertAWBData($orderID, $awb)
        {
            $order = wc_get_order($orderID);
            $orderWeight = $this->getOrderWeight($orderID);

            $awbData = new ES_db_data_format();
            $awbData->order_number = $orderID;
            $awbData->order_price = $order->get_total();
            $awbData->order_weight = $orderWeight;
            $awbData->courier = 'Delhivery';
            $awbData->courier_id = 'NA';
            $awbData->awb = $awb;
            $awbData->tp_company = 'DL';
            $awbData->label = 'NA';

            return es_insert_order_data_db($awbData);
        }

        private function prepareShipmentData($orderIDs)
        {
            $awbString = $this->fetchAWBs(count($orderIDs));
            $awbs = array_map(function ($value) {
                return str_replace('"', '', $value);
            }, explode(",", $awbString));
            
            $awbIndex = 0;
            $shipmentData = [
                "shipments" => [],
                "pickup_location" => ["name" => get_option('delhivery_pickup_location')]
            ];

            foreach ($orderIDs as $orderID) {
                $awb = $awbs[$awbIndex++];
                $order = wc_get_order($orderID);
                $orderDate = date('Y-m-d H:i', strtotime($order->get_date_created()));
                $paymentMode = $this->checkPaymentMode($order->get_payment_method_title(), 'DL');
                $itemList = $this->getOrderItems($orderID);
                $orderWeight = $this->getOrderWeight($orderID);
                $productDimensions = $this->getProductDimensions($orderID);

                $shipmentData["shipments"][] = [
                    "name" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    "add" => $this->replaceSpecialChars($order->get_billing_address_1()) . ' ' . $this->replaceSpecialChars($order->get_billing_address_2()),
                    "pin" => $order->get_billing_postcode(),
                    "city" => $order->get_billing_city(),
                    "state" => $order->get_billing_state(),
                    "country" => "India",
                    "phone" => $this->extractPhoneNumber($order->get_billing_phone()),
                    "order" => $orderID,
                    "payment_mode" => $paymentMode,
                    "products_desc" => $itemList,
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

        public function createShipments($orderIDs)
        {
            $url = 'https://track.delhivery.com/api/cmu/create.json';
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

            if (is_wp_error($response)) {
                return 'Error: While Creating Shipment API-' . $response->get_error_message();
            }

            return $this->handleCreatedShipmentResponse(wp_remote_retrieve_body($response));
        }

        private function handleCreatedShipmentResponse($response)
        {
            $responseJson = json_decode($response, true);
            if (!isset($responseJson['package_count'])) {
                return 'Error: ' . json_encode($responseJson);
            }

            $shipErrors = [];
            $counter = 0;

            foreach ($responseJson['packages'] as $package) {
                $orderID = $package['refnum'];
                $awb = $package['waybill'];
                $order = wc_get_order($orderID);

                if ($package['status'] == "Success") {
                    $dbResponse = $this->insertAWBData($orderID, $awb);
                    if ($dbResponse == 'success') {
                        $counter++;
                        $orderMessage = 'https://aramarket.in/tracking/?order-id=' . $orderID;
                        $directMessage = 'https://www.delhivery.com/track/package/' . $awb;
                        $selectedStatusWC = get_option('after_ship_status');
                        $selectedStatus = str_replace('wc-', '', $selectedStatusWC);
                        $order->update_status($selectedStatus, 'EasyShip Change -');
                        $order->add_order_note('Tracking Link - <a target="_blank" href="' . $orderMessage . '">' . $orderMessage . '</a>', true);
                        $order->add_order_note('Direct Link - <a target="_blank" href="' . $directMessage . '">' . $directMessage . '</a>');
                        $order->save();
                        $shipErrors[] = $orderID . ' - shipped';
                    } else {
                        $shipErrors[] = $orderID . ' - Error db - ' . $dbResponse;
                    }
                } else {
                    $shipErrors[] = $orderID . ' - Error - ' . json_encode($package['remarks']);
                }
            }

            if (count($responseJson['packages']) == 1) {
                return json_encode($shipErrors);
            } else {
                $totalSuccess = "Shipped " . $counter . " out of " . $responseJson['package_count'];
                array_unshift($shipErrors, $totalSuccess);
                return $shipErrors;
            }
        }

        private function checkPaymentMode($paymentMethodTitle, $type)
        {
            $paymentMode = 'Prepaid';

            if ($type == 'DL') {
                $delhiveryCod = get_option('delhivery_cod');

                foreach ($delhiveryCod as $cod) {
                    if (stripos($paymentMethodTitle, $cod) !== false) {
                        $paymentMode = 'COD';
                        break;
                    }
                }
            }

            return $paymentMode;
        }

        private function getAWBNumber($orderID)
        {
            global $wpdb;
            $awb = $wpdb->get_var("SELECT awb FROM wp_awb_tracking WHERE order_number = $orderID");

            return $awb ?: null;
        }

        private function getProductDimensions($orderID)
        {
            $order = wc_get_order($orderID);
            $dimensions = ['length' => 0, 'width' => 0, 'height' => 0];

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();

                $dimensions['length'] += $product->get_length();
                $dimensions['width'] += $product->get_width();
                $dimensions['height'] += $product->get_height();
            }

            return $dimensions;
        }

        private function getOrderWeight($orderID)
        {
            $order = wc_get_order($orderID);
            $totalWeight = 0;

            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $totalWeight += $product->get_weight() * $item->get_quantity();
            }

            return $totalWeight;
        }

        private function replaceSpecialChars($str, $replace = ' ')
        {
            return preg_replace('/[^A-Za-z0-9]/', $replace, $str);
        }

        private function extractPhoneNumber($phone)
        {
            preg_match('/\d{10}/', $phone, $matches);
            return $matches[0] ?? null;
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
            add_submenu_page(EASYSHIP_MAIN_URL, 'Delhivery settings', 'Delhivery API', 'manage_options', 'delhivery-nimbuspost', [$this, 'delhivery_settings_page']);
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
