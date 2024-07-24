<?php

if (!defined('EASYSHIP_URL')) {
	define('EASYSHIP_URL', plugin_dir_url(dirname(__FILE__))); // This will return the URL up to the /plugins/ directory
}
if (!defined('EASYSHIP_BASENAME')) {
    define('EASYSHIP_BASENAME', plugin_basename(__FILE__)); //return EasyShip/easyship-main.php
}

if (!defined('EASYSHIP_MENU_SLUG')) {
    define('EASYSHIP_MENU_SLUG', 'easyship-main'); //return easyship-main
}

if (!defined('EASYSHIP_MAIN_URL')) {
    define('EASYSHIP_MAIN_URL', 'admin.php?page=' . EASYSHIP_MENU_SLUG); //return easyship-main
}

//define variable for awb and courier name in order meta
if (!defined('ES_AWB_META')) {
    define('ES_AWB_META', 'es_awb_no'); //return easyship-main
}
if (!defined('ES_COURIER_NAME_META')) {
    define('ES_COURIER_NAME_META', 'es_courier_name'); //return easyship-main
}


