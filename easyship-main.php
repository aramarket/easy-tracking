<?php
/*
 * Plugin Name:       EasyShip
 * Plugin URI:        https://easy-ship.in
 * Description:       Most Affordable tracking and Shipping solution Spacial for India, Also Made in India.
 * Version:           1.0.0
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

if (!defined('EASYSHIP_URL')) {
    define('EASYSHIP_URL', plugin_dir_url(__FILE__)); //return http://localhost/wp-content/plugins/EasyShip/
}
if (!defined('EASYSHIP_BASENAME')) {
    define('EASYSHIP_BASENAME', plugin_basename(__FILE__)); //return EasyShip/easyship-main.php
}
if (!defined('EASYSHIP_MAIN_URL')) {
    define('EASYSHIP_MAIN_URL', 'easyship-main'); //return easyship-main
}

//Caurier API
require_once EASYSHIP_DIR . 'settings/settings.php';
require_once EASYSHIP_DIR . 'caurier-api/model/tracking-model.php';

require_once EASYSHIP_DIR . 'caurier-api/api/shiprocket-api.php';
require_once EASYSHIP_DIR . 'caurier-api/api/delhivery-api.php';
require_once EASYSHIP_DIR . 'caurier-api/api/nimbusPost-api.php';


//Tracking
require_once EASYSHIP_DIR . 'tracking/includes/setting.php';
require_once EASYSHIP_DIR . 'tracking/includes/tracking-main.php';
require_once EASYSHIP_DIR . 'tracking/includes/general-function.php';
require_once EASYSHIP_DIR . 'tracking/includes/add-tracking-detail-postbox.php';

//Shipping




//Libraries
// require_once EASYSHIP_DIR . '/libs/tcpdf/vendor/autoload.php';

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
}

easy_ship_main();


?>
