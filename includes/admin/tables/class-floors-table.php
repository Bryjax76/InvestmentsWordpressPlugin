<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-base-table.php';

final class SM_INV_Fixed_Floors_Table extends SM_INV_Fixed_Base_Table
{
    /** @var array<int,string> */
    private array $inv_map = [];

    /** @var array<int,string> */
    private array $obj_map = [];

    /** @var array<int,int> */
    private array $obj_inv_map = [];

    public function __construct()
    {
        parent::__construct([
            'singular' => 'floor',
            'plural'   => 'floors',
            'ajax'     => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'id'        => 'ID',
            'id_inv'    => 'Inwestycja',
            'id_object' => 'Budynek',
            'name'      => 'Nazwa',
            'floors_no' => 'Nr piętra',
            'id_svg'    => 'ID SVG',
            'status'    => 'Status',
            'actions'   => 'Akcje',
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'id'        => ['id', false],
            'id_inv'    => ['id_inv', false],
            'id_object' => ['id_object', false],
            'name'      => ['name', false],
            'floors_no' => ['floors_no', false],
            'status'    => ['status', false],
        ];
    }

    public function prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Build investments map
        $this->inv_map = [];
        foreach (SM_INV_Fixed_DB::investments_for_select() as $inv) {
            $id = (int)($inv['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $label = trim((string)($inv['title'] ?? ''));
            $addr  = trim((string)($inv['address'] ?? ''));

            if ($addr !== '') {
                $label .= ' — ' . $addr;
            }

            $this->inv_map[$id] = $label !== '' ? $label : ('ID ' . $id);
        }

        // Build buildings map and relation map (building => investment)
        $this->obj_map = [];
        $this->obj_inv_map = [];

        foreach (SM_INV_Fixed_DB::objects_for_select() as $obj) {
            $id = (int)($obj['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $inv_id = (int)($obj['inv_id'] ?? 0);
            $this->obj_inv_map[$id] = $inv_id;

            $name = trim((string)($obj['name'] ?? ''));
            $this->obj_map[$id] = $name !== '' ? $name : ('Budynek ' . $id);
        }

        $paged   = $this->get_paged();
        $order   = $this->get_order();
        $orderby = $this->get_orderby('id', ['id', 'name', 'id_object', 'id_inv', 'floors_no', 'status']);

        $filters = [
            's'         => sanitize_text_field((string)($_REQUEST['s'] ?? '')),
            'id_inv'    => absint($_REQUEST['filter_inv_id'] ?? 0),
            'id_object' => absint($_REQUEST['filter_object_id'] ?? 0),
        ];

        $status_raw = isset($_REQUEST['filter_status']) ? (string)$_REQUEST['filter_status'] : '';
        if ($status_raw !== '' && is_numeric($status_raw)) {
            $filters['status'] = (int)$status_raw;
        } else {
            $filters['status'] = -999;
        }

        [$items, $total] = SM_INV_Fixed_DB::floors_list(
            $this->per_page,
            $paged,
            $orderby,
            $order,
            $filters
        );

        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $this->per_page,
            'total_pages' => (int) ceil($total / $this->per_page),
        ]);
    }

    public function column_id_inv($item)
    {
        $id = (int)($item['id_inv'] ?? 0);
        if (!$id) {
            return '—';
        }

        return esc_html($this->inv_map[$id] ?? ('ID ' . $id));
    }

    public function column_id_object($item)
    {
        $id = (int)($item['id_object'] ?? 0);
        if (!$id) {
            return '—';
        }

        return esc_html($this->obj_map[$id] ?? ('ID ' . $id));
    }

    public function column_status($item)
    {
        $v = (int)($item['status'] ?? 0);

        $map = [
            -1 => 'Usunięte',
            0  => 'Nieaktywne',
            1  => 'Aktywne',
            2  => 'Wstrzymane',
        ];

        return esc_html($map[$v] ?? (string)$v);
    }

    public function extra_tablenav($which)
    {
        if ($which !== 'top') {
            return;
        }

        $current_inv    = absint($_REQUEST['filter_inv_id'] ?? 0);
        $current_obj    = absint($_REQUEST['filter_object_id'] ?? 0);
        $current_status = isset($_REQUEST['filter_status']) ? (string)$_REQUEST['filter_status'] : '';

        echo '<div class="alignleft actions sm-inv-fixed-filters">';

        // Investment filter
        echo '<label class="screen-reader-text" for="filter_inv_id">Investment</label>';
        echo '<select name="filter_inv_id" id="filter_inv_id">';
        echo '<option value="0">Wszystkie inwestycje</option>';

        foreach ($this->inv_map as $id => $label) {
            printf(
                '<option value="%d" %s>%s</option>',
                (int)$id,
                selected($current_inv, (int)$id, false),
                esc_html($label)
            );
        }

        echo '</select>';

        // Building filter
        // Show only buildings assigned to the selected investment.
        // If no investment is selected, show a placeholder instead of the full global list.
        echo '<label class="screen-reader-text" for="filter_object_id">Building</label>';
        echo '<select name="filter_object_id" id="filter_object_id">';

        if ($current_inv > 0) {
            echo '<option value="0">Wszystkie budynki</option>';

            foreach ($this->obj_map as $id => $label) {
                $obj_inv_id = (int)($this->obj_inv_map[$id] ?? 0);

                if ($obj_inv_id !== $current_inv) {
                    continue;
                }

                printf(
                    '<option value="%d" %s>%s</option>',
                    (int)$id,
                    selected($current_obj, (int)$id, false),
                    esc_html($label)
                );
            }
        } else {
            echo '<option value="0">Select an investment first</option>';
        }

        echo '</select>';

        // Status filter
        echo '<label class="screen-reader-text" for="filter_status">Status</label>';
        echo '<select name="filter_status" id="filter_status">';
        echo '<option value="" ' . selected($current_status, '', false) . '>Wszystkie statusy</option>';
        echo '<option value="1" ' . selected($current_status, '1', false) . '>Aktywne</option>';
        echo '<option value="0" ' . selected($current_status, '0', false) . '>Nieaktywne</option>';
        echo '<option value="2" ' . selected($current_status, '2', false) . '>Wstrzymane</option>';
        echo '</select>';

        // Preserve sorting when filtering
        if (isset($_GET['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr((string)$_GET['orderby']) . '">';
        }

        if (isset($_GET['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr((string)$_GET['order']) . '">';
        }

        submit_button('Filtruj', '', 'filter_action', false);

        echo '</div>';
    }

    public function column_actions($item)
    {
        $page = SM_INV_Fixed_Admin::MENU_SLUG . '-floors';

        $edit = SM_INV_Fixed_Utils::admin_url_page($page, [
            'action' => 'edit',
            'id'     => (int)$item['id'],
        ]);

        $del_url = wp_nonce_url(
            admin_url('admin-post.php?action=sm_inv_fixed_delete_floor&id=' . (int)$item['id']),
            'sm_inv_fixed_delete_floor'
        );

        return sprintf(
            '<a class="button button-small" href="%s">Edytuj</a> <a class="button button-small button-link-delete" href="%s" onclick="return confirm(\'Usunąć?\')">Usuń</a>',
            esc_url($edit),
            esc_url($del_url)
        );
    }
}