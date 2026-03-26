<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-base-table.php';

final class SM_INV_Fixed_Objects_Table extends SM_INV_Fixed_Base_Table
{

    /** @var array<int,string> */
    private array $inv_map = [];

    public function __construct()
    {
        parent::__construct([
            'singular' => 'object',
            'plural' => 'objects',
            'ajax' => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'id' => 'ID',
            'inv_id' => 'Inwestycja',
            'name' => 'Nazwa',
            'svg_selector' => 'Numer SVG',
            'svg_file' => 'SVG (rzut)',
            'status' => 'Status',
            'actions' => 'Akcje',
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'id' => ['id', false],
            'inv_id' => ['inv_id', false],
            'name' => ['name', false],
            'status' => ['status', false],
        ];
    }

    public function column_svg_selector($item)
    {
        $v = isset($item['svg_selector']) ? (int) $item['svg_selector'] : 0;
        return $v ? esc_html($v) : '—';
    }

    public function column_svg_file($item)
    {
        if (empty($item['svg_file']))
            return '—';

        $url = esc_url($item['svg_file']);
        return sprintf(
            '<a href="%s" target="_blank">Podgląd SVG</a>',
            $url
        );
    }


    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Map inwestycji (ID => "Tytuł — adres")
        $this->inv_map = [];
        foreach (SM_INV_Fixed_DB::investments_for_select() as $inv) {
            $id = (int) ($inv['id'] ?? 0);
            if (!$id)
                continue;
            $label = trim((string) ($inv['title'] ?? ''));
            $addr = trim((string) ($inv['address'] ?? ''));
            if ($addr !== '')
                $label .= ' — ' . $addr;
            $this->inv_map[$id] = $label !== '' ? $label : ('ID ' . $id);
        }

        $paged = $this->get_paged();
        $order = $this->get_order();
        $orderby = $this->get_orderby('id', ['id', 'name', 'inv_id', 'status']);

        $filters = [
            's' => sanitize_text_field((string) ($_REQUEST['s'] ?? '')),
            'inv_id' => absint($_REQUEST['filter_inv_id'] ?? 0),
        ];

        $status_raw = isset($_REQUEST['filter_status']) ? (string) $_REQUEST['filter_status'] : '';
        if ($status_raw !== '' && is_numeric($status_raw)) {
            $filters['status'] = (int) $status_raw;
        } else {
            $filters['status'] = -999; // wszystkie (bez usuniętych)
        }

        [$items, $total] = SM_INV_Fixed_DB::objects_list($this->per_page, $paged, $orderby, $order, $filters);
        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $this->per_page,
            'total_pages' => (int) ceil($total / $this->per_page),
        ]);
    }

    public function column_inv_id($item)
    {
        $id = (int) ($item['inv_id'] ?? 0);
        if (!$id)
            return '—';
        return esc_html($this->inv_map[$id] ?? ('ID ' . $id));
    }

    public function column_status($item)
    {
        $v = (int) ($item['status'] ?? 0);
        $map = [
            -1 => 'Usunięte',
            0 => 'Nieaktywne',
            1 => 'Aktywne',
            2 => 'Wstrzymane',
        ];
        return esc_html($map[$v] ?? (string) $v);
    }

    public function extra_tablenav($which)
    {
        if ($which !== 'top')
            return;

        $current_inv = absint($_REQUEST['filter_inv_id'] ?? 0);
        $current_status = isset($_REQUEST['filter_status']) ? (string) $_REQUEST['filter_status'] : '';

        echo '<div class="alignleft actions sm-inv-fixed-filters">';

        // Inwestycja
        echo '<label class="screen-reader-text" for="filter_inv_id">Inwestycja</label>';
        echo '<select name="filter_inv_id" id="filter_inv_id">';
        echo '<option value="0">Wszystkie inwestycje</option>';
        foreach ($this->inv_map as $id => $label) {
            printf('<option value="%d" %s>%s</option>', (int) $id, selected($current_inv, (int) $id, false), esc_html($label));
        }
        echo '</select>';

        // Status
        echo '<label class="screen-reader-text" for="filter_status">Status</label>';
        echo '<select name="filter_status" id="filter_status">';
        echo '<option value="" ' . selected($current_status, '', false) . '>Wszystkie statusy</option>';
        echo '<option value="1" ' . selected($current_status, '1', false) . '>Aktywne</option>';
        echo '<option value="0" ' . selected($current_status, '0', false) . '>Nieaktywne</option>';
        echo '<option value="2" ' . selected($current_status, '2', false) . '>Wstrzymane</option>';
        echo '</select>';

        // Zachowaj sortowanie
        if (isset($_GET['orderby'])) {
            echo '<input type="hidden" name="orderby" value="' . esc_attr((string) $_GET['orderby']) . '">';
        }
        if (isset($_GET['order'])) {
            echo '<input type="hidden" name="order" value="' . esc_attr((string) $_GET['order']) . '">';
        }

        submit_button('Filtruj', '', 'filter_action', false);
        echo '</div>';
    }

    public function column_actions($item)
    {
        $page = SM_INV_Fixed_Admin::MENU_SLUG . '-objects';
        $edit = SM_INV_Fixed_Utils::admin_url_page($page, ['action' => 'edit', 'id' => (int) $item['id']]);
        $del_url = wp_nonce_url(
            admin_url('admin-post.php?action=sm_inv_fixed_delete_object&id=' . (int) $item['id']),
            'sm_inv_fixed_delete_object'
        );

        return sprintf(
            '<a class="button button-small" href="%s">Edytuj</a> <a class="button button-small button-link-delete" href="%s" onclick="return confirm(\'Usunąć?\')">Usuń</a>',
            esc_url($edit),
            esc_url($del_url)
        );
    }
}
