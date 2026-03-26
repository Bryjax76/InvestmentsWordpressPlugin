<?php
if (!defined('ABSPATH')) { exit; }

require_once SM_INV_FIXED_PATH . 'includes/class-db.php';
require_once SM_INV_FIXED_PATH . 'includes/class-utils.php';
require_once SM_INV_FIXED_PATH . 'includes/class-poi.php';

require_once SM_INV_FIXED_PATH . 'includes/admin/class-admin.php';

require_once SM_INV_FIXED_PATH . 'includes/rest/class-rest.php';


final class SM_INV_Fixed_Plugin {

    /** @var self|null */
    private static $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function __construct() {}

    private function init(): void {
        // Admin
        if (is_admin()) {
            SM_INV_Fixed_Admin::init();
        }

        // REST
        add_action('rest_api_init', ['SM_INV_Fixed_REST', 'register_routes']);
    }

    public static function activate(): void {
        // Create missing tables only (do not modify existing ones)
        SM_INV_Fixed_DB::maybe_create_tables();
    }
}
