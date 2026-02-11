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

// Create logging table
register_activation_hook(__FILE__, 'wp_psynct_create_log_table');

function wp_psynct_create_log_table() {

    global $wpdb;

    $table = $wpdb->prefix . 'psync_logs';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        role VARCHAR(20) NOT NULL,
        action VARCHAR(50) NOT NULL,
        host_post_id BIGINT UNSIGNED NULL,
        target_post_id BIGINT UNSIGNED NULL,
        target_url TEXT NULL,
        status VARCHAR(20) NOT NULL,
        message TEXT NULL,
        time_taken FLOAT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Write log data
function wp_psynct_log($data = []) {

    global $wpdb;

    $table = $wpdb->prefix . 'psync_logs';

    $wpdb->insert(
        $table,
        [
            'role'           => sanitize_text_field($data['role'] ?? ''),
            'action'         => sanitize_text_field($data['action'] ?? ''),
            'host_post_id'   => intval($data['host_post_id'] ?? 0),
            'target_post_id' => intval($data['target_post_id'] ?? 0),
            'target_url'     => esc_url_raw($data['target_url'] ?? ''),
            'status'         => sanitize_text_field($data['status'] ?? ''),
            'message'        => sanitize_textarea_field($data['message'] ?? ''),
            'time_taken'     => floatval($data['time_taken'] ?? 0),
            'created_at'     => current_time('mysql')
        ],
        [
            '%s','%s','%d','%d','%s','%s','%s','%f','%s'
        ]
    );
}


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

    // Add log page
    add_submenu_page(
        'wp-psynct',
        'Sync Logs',
        'Logs',
        'manage_options',
        'wp-psynct-logs',
        'wp_psynct_render_logs_page'
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
    require_once WP_PSYNCT_PATH . 'admin/logs.php';

}

/* ---------------- LOAD REST ---------------- */

require_once WP_PSYNCT_PATH . 'admin/rest/receive.php';
require_once WP_PSYNCT_PATH . 'admin/rest/host-push.php';