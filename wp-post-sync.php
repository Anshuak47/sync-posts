<?php
/**
 * Plugin Name: WP Post Sync
 * Plugin URI: https://anshukushwaha.com
 * Description: Synchronize posts across WordPress sites
 * Version: 1.0.0
 * Author: Anshu Kushwaha
 * Author URI: https://anshukushwaha.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-post-sync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly

if (!defined('ABSPATH')) {
    exit;
}

define('WP_PSYNCT_OPTION', 'wp_psynct_settings');
define('WP_PSYNCT_PATH', plugin_dir_path(__FILE__));
define('WP_PSYNCT_URL', plugin_dir_url(__FILE__));

/* -------------------------------------------------------
   ADMIN MENU
------------------------------------------------------- */

add_action('admin_menu', function () {

    add_menu_page(
        'WP Post Sync',
        'WP Post Sync',
        'manage_options',
        'wp-psynct',
        'wp_psynct_render_settings_page',
        'dashicons-randomize',
        80
    );
});

// Enqueue admin styles and scripts
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'toplevel_page_wp-psynct') {
        return;
    }
    
    wp_enqueue_style(
        'wp-psynct-admin',
        plugins_url('admin/assets/css/style.css', __FILE__),
        array(),      
        time(),        
        'all'
    );
    
    wp_enqueue_script(
        'wp-psynct-admin',
        plugins_url('admin/assets/js/scripts.js', __FILE__),
        [],
        time(),
        true
    );
});


/* -------------------------------------------------------
   REGISTER SETTINGS
------------------------------------------------------- */

add_action('admin_init', function () {

    register_setting(
        'wp_psynct_group',
        WP_PSYNCT_OPTION,
        [
            'sanitize_callback' => 'wp_psynct_sanitize_settings'
        ]
    );
});

if (is_admin()) {
    require_once WP_PSYNCT_PATH . 'admin/settings.php';
}

/* ---------------- LOAD REST ---------------- */

require_once WP_PSYNCT_PATH . 'admin/rest/receive.php';
require_once WP_PSYNCT_PATH . 'admin/rest/host-push.php';