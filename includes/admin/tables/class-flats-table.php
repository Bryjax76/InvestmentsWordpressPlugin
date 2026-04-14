<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-base-table.php';

final class SM_INV_Fixed_Flats_Table extends SM_INV_Fixed_Base_Table
{
    /** @var array<int,string> */
    private array $floor_map = [];

    /** @var array<int,string> */
    private array $type_map = [];

    /** @var array<int,string> */
    private array $investment_map = [];

    public function __construct()
    {
        parent::__construct([
            'singular' => 'flat',
            'plural' => 'flats',
            'ajax' => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'id' => 'ID',
            'code' => 'Numer',
            'id_bud' => 'Piętro',
            'meters' => 'Metraż',
            'rooms' => 'Pokoje',
            'price' => 'Cena/m²',
            'total_price' => 'Cena całk.',
            'status' => 'Status',
            'type_id' => 'Typ',
            'actions' => 'Akcje',
        ];
    }

    protected function get_sortable_columns()
    {
        return [
            'id' => ['id', false],
            'code' => ['code', false],
            'id_bud' => ['id_bud', false],
            'meters' => ['meters', false],
            'rooms' => ['rooms', false],
            'price' => ['price', false],
            'total_price' => ['total_price', false],
            'status' => ['status', false],
            'type_id' => ['type_id', false],
        ];
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        // Currently selected investment
        $current_inv = absint($_REQUEST['filter_inv_id'] ?? 0);

        /*
         * =========================
         * FLOORS MAP
         * =========================
         * Show floors only for the selected investment.
         * If no investment is selected, keep the map empty
         * so the dropdown stays readable.
         */
        $this->floor_map = [];

        if ($current_inv > 0 && method_exists('SM_INV_Fixed_DB', 'floors_for_select_by_investment')) {
            $floors = SM_INV_Fixed_DB::floors_for_select_by_investment($current_inv);
        } else {
            $floors = [];
        }

        foreach ($floors as $f) {
            $id = (int) ($f['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $label = trim((string) ($f['name'] ?? ''));
            $no = (string) ($f['floor_no'] ?? ($f['floors_no'] ?? ''));

            if ($no !== '') {
                $label .= ' - piętro ' . $no;
            }

            $this->floor_map[$id] = $label !== '' ? $label : ('ID ' . $id);
        }

        if (!empty($this->floor_map)) {
            asort($this->floor_map, SORT_NATURAL | SORT_FLAG_CASE);
        }

        /*
         * =========================
         * TYPES MAP
         * =========================
         */
        $this->type_map = [];

        foreach (SM_INV_Fixed_DB::room_types_all() as $rt) {
            $id = (int) ($rt['id'] ?? 0);
            if (!$id) {
                continue;
            }

            $this->type_map[$id] = (string) ($rt['name'] ?? ('ID ' . $id));
        }

        if (!empty($this->type_map)) {
            asort($this->type_map, SORT_NATURAL | SORT_FLAG_CASE);
        }

        /*
         * =========================
         * INVESTMENTS MAP
         * =========================
         */
        $this->investment_map = [];

        if (method_exists('SM_INV_Fixed_DB', 'investments_all')) {
            foreach (SM_INV_Fixed_DB::investments_all() as $inv) {
                $id = (int) ($inv['id'] ?? 0);
                if (!$id) {
                    continue;
                }

                $this->investment_map[$id] = (string) ($inv['title'] ?? ('ID ' . $id));
            }
        }

        if (!empty($this->investment_map)) {
            asort($this->investment_map, SORT_NATURAL | SORT_FLAG_CASE);
        }

        /*
         * =========================
         * SORTING / PAGINATION
         * =========================
         */
        $paged = $this->get_paged();
        $order = $this->get_order();
        $orderby = $this->get_orderby('id', [
            'id',
            'code',
            'id_bud',
            'meters',
            'rooms',
            'price',
            'total_price',
            'status',
            'type_id',
        ]);

        /*
         * =========================
         * FILTERS
         * =========================
         */
        $filters = [
            's' => sanitize_text_field((string) ($_REQUEST['s'] ?? '')),
            'id_bud' => absint($_REQUEST['filter_floor_id'] ?? 0),
            'type_id' => absint($_REQUEST['filter_type_id'] ?? 0),
            'inv_id' => $current_inv,
        ];

        $status_raw = isset($_REQUEST['filter_status'])
            ? (string) $_REQUEST['filter_status']
            : '';

        if ($status_raw !== '' && is_numeric($status_raw)) {
            $filters['status'] = (int) $status_raw;
        } else {
            $filters['status'] = -999;
        }

        /*
         * =========================
         * FETCH DATA
         * =========================
         */
        [$items, $total] = SM_INV_Fixed_DB::flats_list(
            $this->per_page,
            $paged,
            $orderby,
            $order,
            $filters
        );

        $this->items = $items;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $this->per_page,
            'total_pages' => (int) ceil($total / $this->per_page),
        ]);
    }

    public function column_id_bud($item)
    {
        $id = (int) ($item['id_bud'] ?? 0);

        if (!$id) {
            return '—';
        }

        return esc_html($this->floor_map[$id] ?? ('ID ' . $id));
    }

    public function column_type_id($item)
    {
        $id = (int) ($item['type_id'] ?? 0);

        if (!$id) {
            return '—';
        }

        return esc_html($this->type_map[$id] ?? ('ID ' . $id));
    }

    public function column_status($item)
    {
        $v = (int) ($item['status'] ?? 0);

        $map = [
            0 => 'Niedostępne',
            1 => 'Dostępne',
            2 => 'Zarezerwowane',
        ];

        return esc_html($map[$v] ?? (string) $v);
    }

    public function extra_tablenav($which)
    {
        if ($which !== 'top') {
            return;
        }

        $current_inv = absint($_REQUEST['filter_inv_id'] ?? 0);
        $current_floor = absint($_REQUEST['filter_floor_id'] ?? 0);
        $current_type = absint($_REQUEST['filter_type_id'] ?? 0);
        $current_status = isset($_REQUEST['filter_status'])
            ? (string) $_REQUEST['filter_status']
            : '';

        echo '<div class="alignleft actions sm-inv-fixed-filters">';

        // Investment filter
        echo '<select name="filter_inv_id">';
        echo '<option value="0">Wszystkie inwestycje</option>';

        foreach ($this->investment_map as $id => $label) {
            printf(
                '<option value="%d" %s>%s</option>',
                (int) $id,
                selected($current_inv, (int) $id, false),
                esc_html($label)
            );
        }

        echo '</select>';

        // Floor filter
        // Show only floors assigned to the selected investment.
        // If no investment is selected, show a placeholder instead
        // of listing every floor in the system.
        echo '<select name="filter_floor_id">';

        if ($current_inv > 0) {
            echo '<option value="0">Wszystkie piętra</option>';

            foreach ($this->floor_map as $id => $label) {
                printf(
                    '<option value="%d" %s>%s</option>',
                    (int) $id,
                    selected($current_floor, (int) $id, false),
                    esc_html($label)
                );
            }
        } else {
            echo '<option value="0">Select an investment first</option>';
        }

        echo '</select>';

        // Type filter
        echo '<select name="filter_type_id">';
        echo '<option value="0">Wszystkie typy</option>';

        foreach ($this->type_map as $id => $label) {
            printf(
                '<option value="%d" %s>%s</option>',
                (int) $id,
                selected($current_type, (int) $id, false),
                esc_html($label)
            );
        }

        echo '</select>';

        // Status filter
        echo '<select name="filter_status">';
        echo '<option value="" ' . selected($current_status, '', false) . '>Wszystkie statusy</option>';
        echo '<option value="1" ' . selected($current_status, '1', false) . '>Dostępne</option>';
        echo '<option value="0" ' . selected($current_status, '0', false) . '>Niedostępne</option>';
        echo '<option value="2" ' . selected($current_status, '2', false) . '>Zarezerwowane</option>';
        echo '</select>';

        // Preserve sorting when filtering
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
        $page = SM_INV_Fixed_Admin::MENU_SLUG . '-flats';

        $edit = SM_INV_Fixed_Utils::admin_url_page($page, [
            'action' => 'edit',
            'id' => (int) $item['id'],
        ]);

        $del_url = wp_nonce_url(
            admin_url('admin-post.php?action=sm_inv_fixed_delete_flat&id=' . (int) $item['id']),
            'sm_inv_fixed_delete_flat'
        );

        return sprintf(
            '<a class="button button-small" href="%s">Edytuj</a>
             <a class="button button-small button-link-delete" href="%s" onclick="return confirm(\'Usunąć?\')">Usuń</a>',
            esc_url($edit),
            esc_url($del_url)
        );
    }
}