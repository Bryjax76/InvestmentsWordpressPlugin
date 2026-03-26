<?php
if (!defined('ABSPATH')) { exit; }

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

abstract class SM_INV_Fixed_Base_Table extends WP_List_Table {

    protected $per_page = 20;

    public function __construct($args = []) {
        parent::__construct($args);
        $this->per_page = (int) apply_filters('sm_inv_fixed_per_page', 20);
    }

    protected function get_paged(): int {
        return max(1, absint($_GET['paged'] ?? 1));
    }

    protected function get_order(): string {
        return SM_INV_Fixed_Utils::sanitize_order_dir($_GET['order'] ?? 'asc');
    }

    protected function get_orderby(string $default, array $allowed): string {
        return SM_INV_Fixed_Utils::sanitize_orderby($_GET['orderby'] ?? $default, $allowed, $default);
    }

    public function column_default($item, $column_name) {
        return isset($item[$column_name]) ? esc_html((string)$item[$column_name]) : '';
    }
}
