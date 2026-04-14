<?php
/**
 * Plugin Name: Siemaszko Investments (Fixed)
 * Description: Bezpieczna, utrzymywalna wersja wtyczki CRUD dla inwestycji.
 * Version: 1.0.0
 * Author: Siemaszko
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SM_INV_FIXED_VERSION', '1.0.0');
define('SM_INV_FIXED_FILE', __FILE__);
define('SM_INV_FIXED_PATH', plugin_dir_path(__FILE__));
define('SM_INV_FIXED_URL', plugin_dir_url(__FILE__));

require_once SM_INV_FIXED_PATH . 'includes/class-plugin.php';
require_once SM_INV_FIXED_PATH . 'includes/class-db.php';
require_once SM_INV_FIXED_PATH . 'includes/class-shortcodes.php';
require_once SM_INV_FIXED_PATH . 'includes/rest/class-rest.php';
require_once SM_INV_FIXED_PATH . 'includes/class-commercial-locals-shortcode.php';

require_once SM_INV_FIXED_PATH . 'includes/class-pdf.php';

require_once SM_INV_FIXED_PATH . 'includes/class-search.php';
require_once SM_INV_FIXED_PATH . 'includes/class-search-ajax.php';

register_activation_hook(__FILE__, ['SM_INV_Fixed_Plugin', 'activate']);

add_action('plugins_loaded', function () {
    SM_INV_Fixed_Plugin::instance();
});

add_action('init', ['SM_INV_Fixed_Shortcodes', 'init']);

// Initialize search shortcode
add_action('init', ['SM_INV_Fixed_Search', 'init']);

// Initialize AJAX handlers for flat search
add_action('init', ['SM_INV_Fixed_Search_Ajax', 'init']);

add_action('rest_api_init', ['SM_INV_Fixed_REST', 'register_routes']);
add_action('init', function () {
    add_rewrite_rule(
        '^inwestycje/([^/]+)/(.*)?$',
        'index.php?post_type=investment&name=$matches[1]',
        'top'
    );
});

add_action('admin_post_sm_inv_flat_pdf', function () {
    $flat_id = isset($_GET['flat_id']) ? (int) $_GET['flat_id'] : 0;

    // opcjonalnie nonce:
    // if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'sm_inv_flat_pdf_' . $flat_id)) {
    //     wp_die('Nieprawidłowy nonce.');
    // }

    SM_INV_Fixed_PDF::render_flat_pdf($flat_id);
});

add_action('admin_post_nopriv_sm_inv_flat_pdf', function () {
    $flat_id = isset($_GET['flat_id']) ? (int) $_GET['flat_id'] : 0;
    SM_INV_Fixed_PDF::render_flat_pdf($flat_id);
});

// AJAX search endpoints
add_action('wp_ajax_sm_flats_search', ['SM_INV_Fixed_Search_Ajax', 'handle']);
add_action('wp_ajax_nopriv_sm_flats_search', ['SM_INV_Fixed_Search_Ajax', 'handle']);

add_action('init', ['SM_INV_Commercial_Locals_Shortcode', 'init']);