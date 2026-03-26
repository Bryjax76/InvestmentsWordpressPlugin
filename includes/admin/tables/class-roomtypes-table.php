<?php
if (!defined('ABSPATH')) { exit; }
require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-base-table.php';

final class SM_INV_Fixed_RoomTypes_Table extends SM_INV_Fixed_Base_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'roomtype',
            'plural'   => 'roomtypes',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'id'      => 'ID',
            'name'    => 'Nazwa',
            'slug'    => 'Slug',
            'actions' => 'Akcje',
        ];
    }

    protected function get_sortable_columns() {
        return [
            'id'   => ['id', false],
            'name' => ['name', false],
            'slug' => ['slug', false],
        ];
    }

    public function prepare_items() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $items = SM_INV_Fixed_DB::room_types_all();
        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => count($items),
            'per_page'    => count($items) ?: 1,
            'total_pages' => 1,
        ]);
    }

    public function column_actions($item) {
        $page = SM_INV_Fixed_Admin::MENU_SLUG . '-roomtypes';
        $edit = SM_INV_Fixed_Utils::admin_url_page($page, ['action' => 'edit', 'id' => (int)$item['id']]);

        $del_url = wp_nonce_url(
            admin_url('admin-post.php?action=sm_inv_fixed_delete_roomtype&id=' . (int)$item['id']),
            'sm_inv_fixed_delete_roomtype'
        );

        return sprintf(
            '<a class="button button-small" href="%s">Edytuj</a> <a class="button button-small button-link-delete" href="%s" onclick="return confirm(\'Usunąć?\')">Usuń</a>',
            esc_url($edit),
            esc_url($del_url)
        );
    }
}
