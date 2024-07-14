<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (!class_exists('EasyShipSetting')) {
    class EasyShipSetting {

        public function __construct() {
            add_action('init', [$this, 'register_settings']);
            add_action('admin_menu', [$this, 'register_menu_page']);
            add_filter('plugin_action_links_' . EASYSHIP_BASENAME, [$this, 'settings_page_link']);
        }

        // Add settings link on plugin page
        public function settings_page_link($links) {
            $settings_link = '<a href="admin.php?page=' . EASYSHIP_MAIN_URL . '">Settings</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

        public function register_settings() {
            // General settings
            register_setting('easyship-settings-group', 'purchase_token');
            register_setting('easyship-settings-group', 'selected_tracking_page');
            register_setting('easyship-settings-group', 'before_ship_status');
            register_setting('easyship-settings-group', 'after_ship_status');
            register_setting('easyship-settings-group', 'es_map_prepaid_orders');


        }

        public function register_menu_page() {
            add_menu_page('EasyShip Dashboard', 'EasyShip', 'manage_options', EASYSHIP_MAIN_URL, [$this, 'easyship_dashboard_page'], 'dashicons-airplane', 6);
        }

        public function easyship_dashboard_page() {
            ?>
            <div class="wrap">
                <h1>easyship Tracking Settings</h1>
                <form method="post" action="options.php">
                    <?php settings_fields('easyship-settings-group'); ?>
                    <?php do_settings_sections('easyship-settings-group'); ?>
                    <table class="form-table">
                        <tr valign="top">
                            <form method="post">
                                <th scope="row">Purchase Token</th>
                                <td>
                                    <input type="text" name="purchase_token" value="<?php echo esc_attr(get_option('purchase_token')); ?>" />
                                    <button class="button btn" type="submit" name="es_activate_plugin">Activate Plugin</button>
                                </td>
                            </form>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Plugin Status</th>
                            <td>
                                <input type="text" name="plugin_status" value="<?php echo esc_attr(get_option('plugin_status') ?: 'Not Activated'); ?>" disabled />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Select a Tracking Page</th>
                            <td>
                                <select name="selected_tracking_page" id="myplugin_page">
                                    <option value="">-- Select a page --</option>
                                    <?php
                                    $pages = get_pages();
                                    foreach ($pages as $page) {
                                        $option = '<option value="' . $page->ID . '"' . selected(get_option('selected_tracking_page'), $page->ID, false) . '>';
                                        $option .= $page->post_title;
                                        $option .= '</option>';
                                        echo $option;
                                    }
                                    ?>
                                </select>
                            </td>
                            <td>Add this <strong>[EASYSHIP-TRACK]</strong> Shortcode to selected page</td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Shipping Button Show</th>
                            <td>
                                <select name="before_ship_status">
                                    <option value="">-- Select Status --</option>
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    $selected_status = get_option('before_ship_status');
                                    foreach ($order_statuses as $status => $status_label) {
                                        echo '<option value="' . esc_attr($status) . '" ' . selected($status, $selected_status, false) . '>' . esc_html($status_label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Updated Status After Ship</th>
                            <td>
                                <select name="after_ship_status">
                                    <option value="">-- Select Status --</option>
                                    <?php
                                    $order_statuses = wc_get_order_statuses();
                                    $selected_status = get_option('after_ship_status');
                                    foreach ($order_statuses as $status => $status_label) {
                                        echo '<option value="' . esc_attr($status) . '" ' . selected($status, $selected_status, false) . '>' . esc_html($status_label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Map Prepaid Orders</th>
                            <td>
                                <input type="text" name="es_map_prepaid_orders" value="<?php echo esc_attr(get_option('es_map_prepaid_orders')); ?>" />
                            </td>
                            <td>Paste here Payment Gateway title like - <strong>'Paytm Payment Gateway',</strong> separated by ' , '</td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Map COD Orders</th>
                            <td>
                                <input type="text" name="es_map_cod_orders" value="<?php echo esc_attr(get_option('es_map_cod_orders')); ?>" />
                            </td>
                            <td>Paste here COD payment title like - <strong>'Cash on delivery',</strong> separated by ' , '</td>
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
