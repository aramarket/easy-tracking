<?php
/*
 * Plugin Name:       EasyShip
 * Plugin URI:        https://easy-ship.in
 * Description:       Most Affordable tracking and Shipping solution Spacial for India, Also Made in India.
 * Version:           2.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            AKASH
 * Update URI:        https://easy-ship.in
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

if(!defined( 'WPINC' )){
	die;
}

if(!defined('EASYSHIP_VERSTION')){
	define('EASYSHIP_VERSTION', '2.0.0');
}

if (!defined('EASYSHIP_DIR')) {
    define('EASYSHIP_DIR', plugin_dir_path(__FILE__)); //return  C:\xampp\htdocs\wp-content\plugins\EasyShip/
}

//Common
require_once EASYSHIP_DIR . 'common/constants.php';
require_once EASYSHIP_DIR . 'common/common_function.php';
require_once EASYSHIP_DIR . 'settings/settings.php';
require_once EASYSHIP_DIR . 'caurier-api/model/tracking-model.php';

//Caurier API
require_once EASYSHIP_DIR . 'caurier-api/api/shiprocket-api.php';
require_once EASYSHIP_DIR . 'caurier-api/api/delhivery-api.php';
require_once EASYSHIP_DIR . 'caurier-api/api/nimbusPost-api.php';


//Tracking
require_once EASYSHIP_DIR . 'tracking/includes/setting.php';
require_once EASYSHIP_DIR . 'tracking/includes/tracking-main.php';
require_once EASYSHIP_DIR . 'tracking/includes/general-function.php';
require_once EASYSHIP_DIR . 'tracking/includes/add-tracking-detail-postbox.php';

//Shipping
// require_once EASYSHIP_DIR . 'shipping/includes/general-function.php';
// require_once EASYSHIP_DIR . 'shipping/includes/order-shipping-actions.php';
// require_once EASYSHIP_DIR . 'shipping/assets/template/bulk-rate-list-template.php';
// require_once EASYSHIP_DIR . 'shipping/includes/shipping-main.php';

//Aramarket custom
// require_once EASYSHIP_DIR . 'aramarket-custom/aramarket-custom.php';

// Initialize the plugin
function easy_ship_main() {
    //Setting
    new EasyShipSetting();
    new ShiprocketSettings();
    new DelhiverySettings();
    new NimbuspostSettings();
	
    //Tracking
    new ESOrderTracking();
	new AddTrackingDetailPostbox();

    //Shipping
    // new ESOrderShippingActions();
    // new ESOrderShipping();
}

easy_ship_main();


?>
