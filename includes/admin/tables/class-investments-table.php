<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-base-table.php';

final class SM_INV_Fixed_Investments_Table extends SM_INV_Fixed_Base_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => 'investment',
            'plural'   => 'investments',
            'ajax'     => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'id'       => 'ID',
            'title'    => 'Tytuł',
            'address'  => 'Adres',
            'city'     => 'Miasto',
            'district' => 'Dzielnica',
            'status'   => 'Status',
            'order'    => 'Kolejność',
            'actions'  => 'Akcje',
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'id'     => ['id', false],
            'title'  => ['title', false],
            'status' => ['status', false],
            'order'  => ['order', false],
        ];
    }

    public function prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $paged   = $this->get_paged();
        $order   = $this->get_order();
        $orderby = $this->get_orderby('id', ['id', 'title', 'address', 'status', 'order', 'city', 'district']);

        $search = sanitize_text_field((string) ($_REQUEST['s'] ?? ''));

        // --- FILTER: investment status ---
        // inv_status:
        //  - ""    => show all except deleted (-1)
        //  - "all" => show all including deleted
        //  - number (e.g. 1,0,2,3,4,-1) => show only that status
        // default: 1 (active)
        $status_raw = isset($_GET['inv_status']) ? (string) $_GET['inv_status'] : '1';

        $status_filter   = 1;      // default: active
        $include_deleted = false;

        if ($status_raw === 'all') {
            $status_filter = null;
            $include_deleted = true;
        } elseif ($status_raw !== '' && is_numeric($status_raw)) {
            $status_filter = (int) $status_raw;
        } else {
            $status_filter = 1;
        }

        [$items, $total] = SM_INV_Fixed_DB::investments_list(
            $this->per_page,
            $paged,
            $orderby,
            $order,
            $status_filter,
            $include_deleted,
            $search
        );

        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $this->per_page,
            'total_pages' => (int) ceil($total / $this->per_page),
        ]);
    }

    public function column_actions($item)
    {
        $page = SM_INV_Fixed_Admin::MENU_SLUG;

        $edit = SM_INV_Fixed_Utils::admin_url_page(
            $page,
            ['action' => 'edit', 'id' => (int) $item['id']]
        );

        $del_url = wp_nonce_url(
            admin_url('admin-post.php?action=sm_inv_fixed_delete_investment&id=' . (int) $item['id']),
            'sm_inv_fixed_delete_investment'
        );

        return sprintf(
            '<a class="button button-small" href="%s">Edytuj</a> 
             <a class="button button-small button-link-delete" href="%s" onclick="return confirm(\'Usunąć?\')">Usuń</a>',
            esc_url($edit),
            esc_url($del_url)
        );
    }

    public function column_status($item)
    {
        $v = (int) ($item['status'] ?? 0);

        $map = [
            -1 => 'Usunięte',
            0  => 'Wyłączone',
            1  => 'Aktywne',
            2  => 'W przygotowaniu',
            3  => 'Sprzedaż wkrótce',
            4  => 'Gotowe do odbioru',
        ];

        return esc_html($map[$v] ?? (string) $v);
    }

    public function extra_tablenav($which)
    {
        if ($which !== 'top') {
            return;
        }

        // default selected filter: active (1)
        $current = isset($_GET['inv_status']) ? (string) $_GET['inv_status'] : '1';

        echo '<div class="alignleft actions">';

        echo '<label class="screen-reader-text" for="inv_status">Filter by status</label>';
        echo '<select name="inv_status" id="inv_status">';

        // show all except deleted
        echo '<option value="" ' . selected($current, '', false) . '>Wszystkie (bez usuniętych)</option>';

        // show all including deleted
        echo '<option value="all" ' . selected($current, 'all', false) . '>Wszystkie (z usuniętymi)</option>';

        // statuses
        echo '<option value="1" ' . selected($current, '1', false) . '>Aktywne</option>';
        echo '<option value="0" ' . selected($current, '0', false) . '>Wyłączone</option>';
        echo '<option value="2" ' . selected($current, '2', false) . '>W przygotowaniu</option>';
        echo '<option value="3" ' . selected($current, '3', false) . '>Sprzedaż wkrótce</option>';
        echo '<option value="4" ' . selected($current, '4', false) . '>Gotowe do odbioru</option>';

        // only deleted
        echo '<option value="-1" ' . selected($current, '-1', false) . '>Usunięte</option>';

        echo '</select>';

        // preserve sorting after filtering
        if (isset($_GET['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr((string) $_GET['orderby']) . '">';
        }

        if (isset($_GET['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr((string) $_GET['order']) . '">';
        }

        submit_button('Filtruj', '', 'filter_action', false);

        echo '</div>';
    }
}