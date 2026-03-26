<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

final class SM_INV_Fixed_Standards_Table extends WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'standard',
            'plural' => 'standards',
            'ajax' => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => __('Nazwa', 'sm-inv-fixed'),
            'icon' => __('Ikona', 'sm-inv-fixed'),
            'status' => __('Status', 'sm-inv-fixed'),
            'order' => __('Kolejność', 'sm-inv-fixed'),
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'name' => ['name', false],
            'order' => ['order', true],
            'status' => ['status', false],
        ];
    }

    protected function column_cb($item): string
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%d" />',
            (int) $item['id']
        );
    }

    protected function column_name($item): string
    {
        $edit_url = SM_INV_Fixed_Utils::admin_url_page(
            SM_INV_Fixed_Admin::MENU_SLUG . '-standards',
            ['action' => 'edit', 'id' => (int) $item['id']]
        );

        $delete_url = wp_nonce_url(
            SM_INV_Fixed_Utils::admin_url_page(
                SM_INV_Fixed_Admin::MENU_SLUG . '-standards',
                ['action' => 'delete', 'id' => (int) $item['id']]
            ),
            'sm_inv_fixed_delete_standard'
        );

        $actions = [
            'edit' => '<a href="' . esc_url($edit_url) . '">' . __('Edytuj', 'sm-inv-fixed') . '</a>',
            'delete' => '<a href="' . esc_url($delete_url) . '" class="submitdelete">' . __('Usuń', 'sm-inv-fixed') . '</a>',
        ];

        return sprintf(
            '<strong>%s</strong> %s',
            esc_html($item['name'] ?? ''),
            $this->row_actions($actions)
        );
    }

    protected function column_icon($item): string
    {
        $icon_id = (int) ($item['icon'] ?? 0);
        if (!$icon_id) {
            return '—';
        }

        $url = wp_get_attachment_image_url($icon_id, 'thumbnail');
        if (!$url) {
            return '—';
        }

        return '<img src="' . esc_url($url) . '" style="width:40px;height:auto;" alt="">';
    }

    protected function column_status($item): string
    {
        $status = (int) ($item['status'] ?? 1);

        return match ($status) {
            0 => '<span style="color:#999;">Nieaktywny</span>',
            1 => '<span style="color:green;">Aktywny</span>',
            default => (string) $status,
        };
    }

    protected function column_order($item): string
    {
        return (string) ($item['order'] ?? 0);
    }

    protected function column_default($item, $column_name)
    {
        return esc_html($item[$column_name] ?? '');
    }

    public function prepare_items(): void
    {
        $per_page = 20;
        $paged = max(1, (int) ($_GET['paged'] ?? 1));

        global $wpdb;
        $table = SM_INV_Fixed_DB::tables()['standards'];

        $orderby = $_GET['orderby'] ?? 'order';
        $order = strtoupper($_GET['order'] ?? 'ASC');
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'ASC';
        }

        // 🔥 WAŻNE: order to keyword SQL
        if ($orderby === 'order') {
            $orderby = '`order`';
        }

        $offset = ($paged - 1) * $per_page;

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT SQL_CALC_FOUND_ROWS *
             FROM {$table}
             WHERE status <> %d
             ORDER BY {$orderby} {$order}
             LIMIT %d OFFSET %d",
                -1,
                $per_page,
                $offset
            ),
            ARRAY_A
        ) ?: [];

        $total_items = (int) $wpdb->get_var("SELECT FOUND_ROWS()");

        // 🔥 TO JEST KLUCZ
        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);
    }
}
