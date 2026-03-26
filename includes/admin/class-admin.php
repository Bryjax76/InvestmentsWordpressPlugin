<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-investments-table.php';
require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-objects-table.php';
require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-floors-table.php';
require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-flats-table.php';
require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-roomtypes-table.php';
require_once SM_INV_FIXED_PATH . 'includes/admin/tables/class-standards-table.php';


final class SM_INV_Fixed_Admin
{
    const MENU_SLUG = 'sm-inv-fixed';

    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Admin-post handlers (save / delete)
        add_action('admin_post_sm_inv_fixed_save_investment', [__CLASS__, 'handle_save_investment']);
        add_action('admin_post_sm_inv_fixed_delete_investment', [__CLASS__, 'handle_delete_investment']);

        add_action('admin_post_sm_inv_fixed_save_object', [__CLASS__, 'handle_save_object']);
        add_action('admin_post_sm_inv_fixed_delete_object', [__CLASS__, 'handle_delete_object']);

        add_action('admin_post_sm_inv_fixed_save_floor', [__CLASS__, 'handle_save_floor']);
        add_action('admin_post_sm_inv_fixed_delete_floor', [__CLASS__, 'handle_delete_floor']);

        add_action('admin_post_sm_inv_fixed_save_flat', [__CLASS__, 'handle_save_flat']);
        add_action('admin_post_sm_inv_fixed_delete_flat', [__CLASS__, 'handle_delete_flat']);

        add_action('admin_post_sm_inv_fixed_save_roomtype', [__CLASS__, 'handle_save_roomtype']);
        add_action('admin_post_sm_inv_fixed_delete_roomtype', [__CLASS__, 'handle_delete_roomtype']);

        add_action('admin_post_sm_inv_fixed_save_standard', [__CLASS__, 'handle_save_standard']);
        add_action('admin_post_sm_inv_fixed_delete_standard', [__CLASS__, 'handle_delete_standard']);

        add_action('wp_ajax_sm_inv_refresh_poi', [__CLASS__, 'ajax_refresh_poi']);

        add_action('admin_notices', [__CLASS__, 'maybe_notice_missing_tables']);
    }

    public static function enqueue_assets($hook): void
    {
        if (empty($_GET['page'])) {
            return;
        }

        $page = sanitize_key($_GET['page']);
        if (strpos($page, self::MENU_SLUG) !== 0) {
            return;
        }

        wp_enqueue_style(
            'sm-inv-fixed-admin',
            SM_INV_FIXED_URL . 'assets/admin.css',
            [],
            SM_INV_FIXED_VERSION
        );

        wp_enqueue_media();

        wp_enqueue_script(
            'sm-inv-fixed-admin',
            SM_INV_FIXED_URL . 'assets/admin.js',
            ['jquery'],
            time(),
            true
        );

        wp_localize_script(
            'sm-inv-fixed-admin',
            'SM_INV_ADMIN',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('sm_inv_refresh_poi'),
            ]
        );
    }

    public static function maybe_notice_missing_tables(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $missing = SM_INV_Fixed_DB::missing_tables();
        if (!empty($missing)) {
            SM_INV_Fixed_Utils::admin_notice_missing_tables($missing);
        }
    }


    public static function register_menu(): void
    {
        add_menu_page(
            __('Inwestycje', 'sm-inv-fixed'),
            __('Inwestycje', 'sm-inv-fixed'),
            'manage_options',
            self::MENU_SLUG,
            [__CLASS__, 'render_investments'],
            'dashicons-building',
            26
        );

        add_submenu_page(self::MENU_SLUG, __('Inwestycje', 'sm-inv-fixed'), __('Inwestycje', 'sm-inv-fixed'), 'manage_options', self::MENU_SLUG, [__CLASS__, 'render_investments']);
        add_submenu_page(self::MENU_SLUG, __('Budynki', 'sm-inv-fixed'), __('Budynki', 'sm-inv-fixed'), 'manage_options', self::MENU_SLUG . '-objects', [__CLASS__, 'render_objects']);
        add_submenu_page(self::MENU_SLUG, __('Piętra', 'sm-inv-fixed'), __('Piętra', 'sm-inv-fixed'), 'manage_options', self::MENU_SLUG . '-floors', [__CLASS__, 'render_floors']);
        add_submenu_page(self::MENU_SLUG, __('Mieszkania', 'sm-inv-fixed'), __('Mieszkania', 'sm-inv-fixed'), 'manage_options', self::MENU_SLUG . '-flats', [__CLASS__, 'render_flats']);
        add_submenu_page(self::MENU_SLUG, __('Typy pomieszczeń', 'sm-inv-fixed'), __('Typy pomieszczeń', 'sm-inv-fixed'), 'manage_options', self::MENU_SLUG . '-roomtypes', [__CLASS__, 'render_roomtypes']);
        add_submenu_page(self::MENU_SLUG, __('Standard inwestycji', 'sm-inv-fixed'), __('Standard inwestycji', 'sm-inv-fixed'), 'manage_options', self::MENU_SLUG . '-standards', [__CLASS__, 'render_standards']);

    }

    // ---------- Renderers ----------

    public static function render_investments(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        $action = SM_INV_Fixed_Utils::sanitize_key_or_default($_GET['action'] ?? '', 'list');
        $id = absint($_GET['id'] ?? 0);

        echo '<div class="wrap sm-inv-fixed">';
        echo '<h1 class="wp-heading-inline">Inwestycje</h1> ';
        echo '<a class="page-title-action" href="' . esc_url(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG, ['action' => 'edit'])) . '">Dodaj nową</a>';
        echo '<hr class="wp-header-end">';

        if ($action === 'edit') {
            self::render_investment_form($id);
        } else {
            $table = new SM_INV_Fixed_Investments_Table();
            $table->prepare_items();
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG) . '">';
            $table->search_box('Szukaj', 'sm-inv-fixed');
            $table->display();
            echo '</form>';
        }

        echo '</div>';
    }

    public static function render_objects(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        $action = SM_INV_Fixed_Utils::sanitize_key_or_default($_GET['action'] ?? '', 'list');
        $id = absint($_GET['id'] ?? 0);

        echo '<div class="wrap sm-inv-fixed">';
        echo '<h1 class="wp-heading-inline">Budynki</h1> ';
        echo '<a class="page-title-action" href="' . esc_url(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-objects', ['action' => 'edit'])) . '">Dodaj nowy</a>';
        echo '<hr class="wp-header-end">';

        if ($action === 'edit') {
            self::render_object_form($id);
        } else {
            $table = new SM_INV_Fixed_Objects_Table();
            $table->prepare_items();
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG . '-objects') . '">';
            $table->search_box('Szukaj', 'sm-inv-fixed');
            $table->display();
            echo '</form>';
        }

        echo '</div>';
    }

    public static function render_floors(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        $action = SM_INV_Fixed_Utils::sanitize_key_or_default($_GET['action'] ?? '', 'list');
        $id = absint($_GET['id'] ?? 0);

        echo '<div class="wrap sm-inv-fixed">';
        echo '<h1 class="wp-heading-inline">Piętra</h1> ';
        echo '<a class="page-title-action" href="' . esc_url(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-floors', ['action' => 'edit'])) . '">Dodaj nowe</a>';
        echo '<hr class="wp-header-end">';

        if ($action === 'edit') {
            self::render_floor_form($id);
        } else {
            $table = new SM_INV_Fixed_Floors_Table();
            $table->prepare_items();
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG . '-floors') . '">';
            $table->search_box('Szukaj', 'sm-inv-fixed');
            $table->display();
            echo '</form>';
        }

        echo '</div>';
    }

    public static function render_flats(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        $action = SM_INV_Fixed_Utils::sanitize_key_or_default($_GET['action'] ?? '', 'list');
        $id = absint($_GET['id'] ?? 0);

        echo '<div class="wrap sm-inv-fixed">';
        echo '<h1 class="wp-heading-inline">Mieszkania</h1> ';
        echo '<a class="page-title-action" href="' . esc_url(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-flats', ['action' => 'edit'])) . '">Dodaj nowe</a>';
        echo '<hr class="wp-header-end">';

        if ($action === 'edit') {
            self::render_flat_form($id);
        } else {
            $table = new SM_INV_Fixed_Flats_Table();
            $table->prepare_items();
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG . '-flats') . '">';
            $table->search_box('Szukaj', 'sm-inv-fixed');
            $table->display();
            echo '</form>';
        }

        echo '</div>';
    }

    public static function render_roomtypes(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        $action = SM_INV_Fixed_Utils::sanitize_key_or_default($_GET['action'] ?? '', 'list');
        $id = absint($_GET['id'] ?? 0);

        echo '<div class="wrap sm-inv-fixed">';
        echo '<h1 class="wp-heading-inline">Typy pomieszczeń</h1> ';
        echo '<a class="page-title-action" href="' . esc_url(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-roomtypes', ['action' => 'edit'])) . '">Dodaj nowy</a>';
        echo '<hr class="wp-header-end">';

        if ($action === 'edit') {
            self::render_roomtype_form($id);
        } else {
            $table = new SM_INV_Fixed_RoomTypes_Table();
            $table->prepare_items();
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG . '-roomtypes') . '">';
            $table->search_box('Szukaj', 'sm-inv-fixed');
            $table->display();
            echo '</form>';
        }

        echo '</div>';
    }

    public static function render_standards(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage()) {
            SM_INV_Fixed_Utils::admin_die_forbidden();
        }

        $action = SM_INV_Fixed_Utils::sanitize_key_or_default($_GET['action'] ?? '', 'list');
        $id = absint($_GET['id'] ?? 0);

        echo '<div class="wrap sm-inv-fixed">';
        echo '<h1 class="wp-heading-inline">Standard inwestycji</h1> ';
        echo '<a class="page-title-action" href="' .
            esc_url(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-standards', ['action' => 'edit'])) .
            '">Dodaj nowy</a>';
        echo '<hr class="wp-header-end">';

        if ($action === 'edit') {
            self::render_standard_form($id);
        } else {
            $table = new SM_INV_Fixed_Standards_Table();
            $table->prepare_items();
            echo '<form method="get">';
            echo '<input type="hidden" name="page" value="' . esc_attr(self::MENU_SLUG . '-standards') . '">';
            $table->search_box('Szukaj', 'sm-inv-fixed');
            $table->display();
            echo '</form>';
        }

        echo '</div>';
    }


    // ---------- Forms ----------

    private static function render_investment_form(int $id): void
    {
        $item = $id ? SM_INV_Fixed_DB::investment_get($id) : null;

        // ===== STANDARDY =====
        $all_standards = SM_INV_Fixed_DB::standards_list_all();
        $selected_standards = $id
            ? SM_INV_Fixed_DB::standards_ids_by_investment($id)
            : [];

        // ===== DODATKOWE PRODUKTY =====
        $additional_products = $id
            ? SM_INV_Fixed_DB::additional_products_by_investment($id)
            : [];

        $statuses = [
            0 => 'Wyłączone',
            1 => 'Aktywne',
            2 => 'W przygotowaniu',
            3 => 'Sprzedaż wkrótce',
            4 => 'Gotowe do odbioru',
        ];
        $status_val = isset($item['status']) ? (int) $item['status'] : 1;

        echo '<h2>' . ($id ? 'Edycja inwestycji' : 'Dodaj inwestycję') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sm_inv_fixed_save_investment');
        echo '<input type="hidden" name="action" value="sm_inv_fixed_save_investment">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';
        // 🔥 NOWE POLE
        self::tr_input('Link zewnętrzny', 'external_url', $item['external_url'] ?? '');
        echo '<tr><th></th><td><p class="description">
        Jeśli ustawione – inwestycja będzie przekierowywać na ten adres zamiast strony szczegółów. Zostaw puste jeśli nie jest to wymagane.
        </p></td></tr>';
        // 🔥 NOWE POLE
        self::tr_input('Termin realizacji', 'completion_date', $item['completion_date'] ?? '');
        self::tr_input('Tytuł', 'title', $item['title'] ?? '');
        self::tr_input('Adres', 'address', $item['address'] ?? '');
        self::tr_input('Miasto', 'city', $item['city'] ?? '');
        self::tr_input('Dzielnica', 'district', $item['district'] ?? '');
        self::tr_editor('Opis (excerpt)', 'excerpt', $item['excerpt'] ?? '');
        self::tr_input('Tytuł sekcji (content_title)', 'content_title', $item['content_title'] ?? '');
        self::tr_textarea('Mapa / embed (google_map)', 'google_map', $item['google_map'] ?? '');
        self::tr_input('Latitude', 'latitude', $item['latitude'] ?? '');
        self::tr_input('Longitude', 'longitude', $item['longitude'] ?? '');
        self::tr_input(
            'Liczba budynków (buildings_no)',
            'buildings_no',
            (string) ($item['buildings_no'] ?? 0),
            'number'
        );
        self::tr_input(
            'Kolejność (order)',
            'order',
            (string) ($item['order'] ?? 0),
            'number'
        );

        self::tr_gallery_picker('Galeria', 'gallery', (string) ($item['gallery'] ?? ''));

        // ===== STANDARD INWESTYCJI =====
        echo '<tr>';
        echo '<th scope="row"><label>Standard inwestycji</label></th>';
        echo '<td>';

        if (empty($all_standards)) {
            echo '<em>Brak zdefiniowanych standardów</em>';
        } else {
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">';

            foreach ($all_standards as $std) {
                $checked = in_array((int) $std['id'], $selected_standards, true);

                echo '<label style="display:flex;align-items:center;gap:6px;">';
                echo '<input type="checkbox" name="standards[]" value="' . (int) $std['id'] . '" ' .
                    checked($checked, true, false) . '>';
                echo esc_html($std['name']);
                echo '</label>';
            }

            echo '</div>';
        }

        echo '</td>';
        echo '</tr>';

        self::tr_textarea('Hint', 'hint', $item['hint'] ?? '');
        self::tr_checkbox('Is floor', 'is_floor', !empty($item['is_floor']));

        // ===== DODATKOWE PRODUKTY =====
        self::tr_additional_products($additional_products);

        echo '<tr><th scope="row"><label>Status</label></th><td><select name="status">';
        foreach ($statuses as $k => $label) {
            printf(
                '<option value="%d"%s>%s</option>',
                $k,
                selected($status_val, $k, false),
                esc_html($label)
            );
        }
        echo '</select></td></tr>';

        self::tr_media('Obrazek (image)', 'image', (int) ($item['image'] ?? 0));
        self::tr_media('Media (media)', 'media', (int) ($item['media'] ?? 0));
        self::tr_media('Miniaturka (thumb)', 'thumb', (int) ($item['thumb'] ?? 0));
        self::tr_media('Miniaturka 2 (thumb2)', 'thumb2', (int) ($item['thumb2'] ?? 0));

        echo '</tbody></table>';

        if ($id) {
            echo '<hr>';
            echo '<button type="button"
            class="button"
            id="sm-refresh-poi"
            data-investment-id="' . esc_attr($id) . '">
            🔄 Odśwież POI
          </button>';
            echo ' <span id="sm-poi-status" style="margin-left:10px;"></span>';
        }

        submit_button('Zapisz');
        echo '</form>';
    }

    private static function render_object_form(int $id): void
    {
        $item = $id ? SM_INV_Fixed_DB::object_get($id) : [];
        $investments = SM_INV_Fixed_DB::investments_for_select();
        $status_val = isset($item['status']) ? (int) $item['status'] : 1;

        echo '<h2>' . ($id ? 'Edycja budynku' : 'Dodaj budynek') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sm_inv_fixed_save_object');
        echo '<input type="hidden" name="action" value="sm_inv_fixed_save_object">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';

        // Inwestycja
        echo '<tr>';
        echo '<th scope="row"><label for="inv_id">Inwestycja</label></th>';
        echo '<td><select name="inv_id" id="inv_id">';
        $inv_selected = (int) ($item['inv_id'] ?? 0);
        foreach ($investments as $inv) {
            $label = trim(($inv['title'] ?? '') . ' — ' . ($inv['address'] ?? ''));
            printf(
                '<option value="%d"%s>%s</option>',
                (int) $inv['id'],
                selected($inv_selected, (int) $inv['id'], false),
                esc_html($label)
            );
        }
        echo '</select></td>';
        echo '</tr>';

        // Nazwa
        self::tr_input('Nazwa', 'name', $item['name'] ?? '');

        // NUMER Z SVG (bud-3 -> 3) -> zapisuje się do sm_objects.id_svg
        self::tr_input(
            'Numer budynku (SVG)',
            'id_svg',
            (string) ($item['id_svg'] ?? 0),
            'number'
        );

        // PLIK SVG (rzut pięter) -> zapisuje się do sm_objects.media (attachment ID)
        self::tr_media(
            'SVG – rzut pięter',
            'media',
            (int) ($item['media'] ?? 0)
        );

        self::tr_select_status($status_val);

        echo '</tbody></table>';

        submit_button('Zapisz');
        echo '</form>';
    }



    private static function render_floor_form(int $id): void
    {
        $item = $id ? SM_INV_Fixed_DB::floor_get($id) : null;
        $objects = SM_INV_Fixed_DB::objects_for_select();
        $status_val = isset($item['status']) ? (int) $item['status'] : 1;
        $obj_selected = (int) ($item['id_object'] ?? 0);

        // minimal cache inwestycji, żeby nie robić N+1
        $inv_cache = [];

        echo '<h2>' . ($id ? 'Edycja piętra' : 'Dodaj piętro') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sm_inv_fixed_save_floor');
        echo '<input type="hidden" name="action" value="sm_inv_fixed_save_floor">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label>Budynek</label></th><td><select name="id_object">';
        foreach ($objects as $obj) {
            $inv_id = (int) ($obj['inv_id'] ?? 0);
            if (!isset($inv_cache[$inv_id])) {
                $inv_cache[$inv_id] = $inv_id ? SM_INV_Fixed_DB::investment_get($inv_id) : null;
            }
            $inv = $inv_cache[$inv_id];
            $inv_label = $inv ? ($inv['title'] ?? '') : ('ID ' . $inv_id);

            $label = sprintf('(%d) %s — %s', (int) $obj['id'], $inv_label, ($obj['name'] ?? ''));
            printf('<option value="%d"%s>%s</option>', (int) $obj['id'], selected($obj_selected, (int) $obj['id'], false), esc_html($label));
        }
        echo '</select><p class="description">id_inv jest ustawiane automatycznie na podstawie wybranego budynku.</p></td></tr>';

        self::tr_input('Nazwa', 'name', $item['name'] ?? '');

        self::tr_input(
            'Numer piętra (SVG)',
            'id_svg',
            isset($item['id_svg']) ? (int) $item['id_svg'] : '',
            'number'
        );
        // ↑ np. floor-2 → wpisujesz 2

        self::tr_input(
            'Kolejność piętra',
            'floors_no',
            (string) ($item['floors_no'] ?? 0),
            'number'
        );

        // SVG – RZUT MIESZKAŃ NA PIĘTRZE
        self::tr_media(
            'SVG – rzut mieszkań',
            'media',
            isset($item['media']) ? (int) $item['media'] : 0
        );

        self::tr_select_status($status_val);

        echo '</tbody></table>';

        submit_button('Zapisz');
        echo '</form>';
    }

    private static function render_flat_form(int $id): void
    {
        $item = $id ? SM_INV_Fixed_DB::flat_get($id) : null;
        $floors = SM_INV_Fixed_DB::floors_for_select();

        // FIX: brakowało przypisania + średnika
        $roomtypes = SM_INV_Fixed_DB::room_types_all();

        $status_val = isset($item['status']) ? (int) $item['status'] : 1;

        // minimal cache inwestycji do labeli
        $inv_cache = [];

        echo '<h2>' . ($id ? 'Edycja mieszkania' : 'Dodaj mieszkanie') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sm_inv_fixed_save_flat');
        echo '<input type="hidden" name="action" value="sm_inv_fixed_save_flat">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';

        self::tr_input('Numer (code)', 'code', $item['code'] ?? '');

        echo '<tr><th scope="row"><label>Piętro</label></th><td><select name="id_bud">';
        $floor_selected = (int) ($item['id_bud'] ?? 0);
        foreach ($floors as $f) {
            $inv_id = (int) ($f['id_inv'] ?? 0);
            if (!isset($inv_cache[$inv_id])) {
                $inv_cache[$inv_id] = $inv_id ? SM_INV_Fixed_DB::investment_get($inv_id) : null;
            }
            $inv = $inv_cache[$inv_id];

            $inv_label = $inv ? ($inv['title'] ?? '') : ('ID ' . $inv_id);
            $label = sprintf('%s / %s / %s', $inv_label, ($f['name'] ?? ''), (string) ($f['floors_no'] ?? ''));
            printf('<option value="%d"%s>%s</option>', (int) $f['id'], selected($floor_selected, (int) $f['id'], false), esc_html($label));
        }
        echo '</select></td></tr>';

        self::tr_input('Metraż (meters)', 'meters', (string) ($item['meters'] ?? ''), 'text');
        self::tr_input('Liczba pokoi (rooms)', 'rooms', (string) ($item['rooms'] ?? 0), 'number');
        self::tr_input('Cena za metr (price)', 'price', (string) ($item['price'] ?? ''), 'text');

        echo '<tr><th scope="row"><label>Status</label></th><td><select name="status">';
        $statuses = [
            0 => 'Niedostępne',
            1 => 'Dostępne',
            2 => 'Zarezerwowane',
        ];
        foreach ($statuses as $k => $label) {
            printf('<option value="%d"%s>%s</option>', $k, selected($status_val, $k, false), esc_html($label));
        }
        echo '</select></td></tr>';

        echo '<tr><th scope="row"><label>Typ pomieszczenia</label></th><td><select name="type_id">';
        $type_selected = (int) ($item['type_id'] ?? 0);
        foreach ($roomtypes as $rt) {
            printf('<option value="%d"%s>%s</option>', (int) $rt['id'], selected($type_selected, (int) $rt['id'], false), esc_html($rt['name'] ?? ''));
        }
        echo '</select></td></tr>';

        // sm_flats.id_svg jest VARCHAR => zwykły input
        self::tr_input('ID SVG', 'id_svg', (string) ($item['id_svg'] ?? ''));
        self::tr_media('Media (media)', 'media', (int) ($item['media'] ?? 0));

        echo '</tbody></table>';

        submit_button('Zapisz');
        echo '</form>';
    }

    private static function render_roomtype_form(int $id): void
    {
        $item = $id ? SM_INV_Fixed_DB::room_type_get($id) : null;

        echo '<h2>' . ($id ? 'Edycja typu' : 'Dodaj typ') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sm_inv_fixed_save_roomtype');
        echo '<input type="hidden" name="action" value="sm_inv_fixed_save_roomtype">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

        echo '<table class="form-table" role="presentation"><tbody>';
        self::tr_input('Nazwa', 'name', $item['name'] ?? '');
        self::tr_input('Slug', 'slug', $item['slug'] ?? '');
        echo '<tr><th scope="row"></th><td><p class="description">Jeśli slug jest pusty, zostanie wygenerowany automatycznie z nazwy.</p></td></tr>';
        echo '</tbody></table>';

        submit_button('Zapisz');
        echo '</form>';
    }

    private static function render_standard_form(int $id): void
    {
        $item = $id ? SM_INV_Fixed_DB::standard_get($id) : null;

        echo '<h2>' . ($id ? 'Edycja standardu' : 'Dodaj standard') . '</h2>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('sm_inv_fixed_save_standard');
        echo '<input type="hidden" name="action" value="sm_inv_fixed_save_standard">';
        echo '<input type="hidden" name="id" value="' . esc_attr($id) . '">';

        echo '<table class="form-table"><tbody>';
        self::tr_input('Nazwa', 'name', $item['name'] ?? '');
        self::tr_media('Ikona', 'icon', (int) ($item['icon'] ?? 0));
        self::tr_select_status((int) ($item['status'] ?? 1));
        echo '</tbody></table>';

        submit_button('Zapisz');
        echo '</form>';
    }


    // ---------- Fields helpers ----------
    private static function tr_editor(string $label, string $name, string $value): void
    {
        // Upewnij się, że edytor jest dostępny na stronie wtyczki
        if (function_exists('wp_enqueue_editor')) {
            wp_enqueue_editor();
        }

        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';

        ob_start();
        wp_editor(
            $value,
            'sm_inv_' . sanitize_key($name), // unikalne ID edytora
            [
                'textarea_name' => $name,     // WAŻNE: nazwa pola w POST (np. excerpt)
                'media_buttons' => true,
                'teeny' => false,
                'textarea_rows' => 8,
                'quicktags' => true,
            ]
        );
        echo ob_get_clean();

        echo '</td></tr>';
    }
    private static function tr_input(string $label, string $name, string $value, string $type = 'text'): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><input name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" type="' . esc_attr($type) . '" class="regular-text" value="' . esc_attr($value) . '"></td></tr>';
    }

    private static function tr_textarea(string $label, string $name, string $value): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th>';
        echo '<td><textarea name="' . esc_attr($name) . '" id="' . esc_attr($name) . '" class="large-text" rows="5">' . esc_textarea($value) . '</textarea></td></tr>';
    }

    private static function tr_checkbox(string $label, string $name, bool $checked): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        echo '<label><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '> ' . esc_html__('Tak', 'sm-inv-fixed') . '</label>';
        echo '</td></tr>';
    }

    private static function tr_media(string $label, string $name, int $attachment_id): void
    {
        $url = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
        $img = $url ? '<img class="sm-inv-fixed-thumb" src="' . esc_url($url) . '" alt="">' : '';
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        echo '<div class="sm-inv-fixed-media" data-field="' . esc_attr($name) . '">';
        echo $img;
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($attachment_id) . '"> ';
        echo '<button type="button" class="button sm-inv-fixed-pick">' . esc_html__('Wybierz', 'sm-inv-fixed') . '</button> ';
        echo '<button type="button" class="button sm-inv-fixed-clear">' . esc_html__('Usuń', 'sm-inv-fixed') . '</button>';
        echo '</div>';
        echo '</td></tr>';
    }

    /**
     * Gallery stored as CSV of attachment IDs in investments.gallery.
     * Provides a multi-image picker UI + keeps raw CSV in a textarea (for manual edits / debugging).
     */
    private static function tr_gallery_picker(string $label, string $name, string $csv): void
    {
        $csv = (string) $csv;
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>';
        echo '<div class="sm-inv-fixed-gallery" data-field="' . esc_attr($name) . '">';
        echo '<p><button type="button" class="button sm-inv-fixed-pick-gallery">' . esc_html__('Wybierz zdjęcia', 'sm-inv-fixed') . '</button> ';
        echo '<button type="button" class="button sm-inv-fixed-clear-gallery">' . esc_html__('Wyczyść', 'sm-inv-fixed') . '</button></p>';
        echo '<div class="sm-inv-fixed-gallery-preview" style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;"></div>';
        echo '<textarea name="' . esc_attr($name) . '" class="large-text" rows="3" placeholder="np. 12,34,56">' . esc_textarea($csv) . '</textarea>';
        echo '<p class="description">Zapis przechowuje ID załączników jako CSV (np. 12,34,56). Kolejność w CSV = kolejność w galerii.</p>';
        echo '</div>';
        echo '</td></tr>';
    }

    /**
     * Additional products repeater: icon (attachment ID), name, price.
     * Saves to sm_additional_products via SM_INV_Fixed_DB::additional_products_replace().
     */
    private static function tr_additional_products(array $rows): void
    {
        // Ensure at least one row
        if (empty($rows)) {
            $rows = [['icon' => 0, 'name' => '', 'price' => '']];
        }

        echo '<tr><th scope="row">' . esc_html__('Dodatkowe produkty', 'sm-inv-fixed') . '</th><td>';
        echo '<div class="sm-inv-fixed-repeater">';
        echo '<table class="widefat striped" style="max-width:900px;">';
        echo '<thead><tr>';
        echo '<th style="width:220px;">' . esc_html__('Ikona', 'sm-inv-fixed') . '</th>';
        echo '<th>' . esc_html__('Nazwa', 'sm-inv-fixed') . '</th>';
        echo '<th style="width:200px;">' . esc_html__('Cena', 'sm-inv-fixed') . '</th>';
        echo '<th style="width:80px;"></th>';
        echo '</tr></thead>';
        echo '<tbody class="sm-inv-fixed-repeater-rows">';

        $i = 0;
        foreach ($rows as $row) {
            $icon_id = absint($row['icon'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            $price = (string) ($row['price'] ?? '');
            echo self::additional_product_row_html($i, $icon_id, $name, $price);
            $i++;
        }

        echo '</tbody></table>';
        echo '<p><button type="button" class="button sm-inv-fixed-add-row">' . esc_html__('Dodaj produkt', 'sm-inv-fixed') . '</button></p>';

        // Template row (JS replaces __i__)
        echo '<script type="text/html" id="sm-inv-fixed-repeater-template" class="sm-inv-fixed-repeater-template">';
        echo self::additional_product_row_html('__i__', 0, '', '');
        echo '</script>';

        echo '</div>';
        echo '<p class="description">Produkty zapisują się osobno dla tej inwestycji. Możesz dodać dowolną liczbę.</p>';
        echo '</td></tr>';
    }

    /** @return string HTML */
    private static function additional_product_row_html($i, int $icon_id, string $name, string $price): string
    {
        $i_attr = esc_attr((string) $i);
        $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : '';
        $img = $icon_url ? '<img class="sm-inv-fixed-thumb" src="' . esc_url($icon_url) . '" alt="">' : '';

        ob_start();
        echo '<tr class="sm-inv-fixed-repeater-row" data-index="' . $i_attr . '">';
        echo '<td>';
        echo '<div class="sm-inv-fixed-media" data-field="additional_products[' . $i_attr . '][icon]" data-input="additional_products[' . $i_attr . '][icon]">';
        echo $img;
        echo '<input type="hidden" name="additional_products[' . $i_attr . '][icon]" value="' . esc_attr((string) $icon_id) . '"> ';
        echo '<button type="button" class="button sm-inv-fixed-pick">' . esc_html__('Wybierz', 'sm-inv-fixed') . '</button> ';
        echo '<button type="button" class="button sm-inv-fixed-clear">' . esc_html__('Usuń', 'sm-inv-fixed') . '</button>';
        echo '</div>';
        echo '</td>';
        echo '<td><input class="regular-text" type="text" name="additional_products[' . $i_attr . '][name]" value="' . esc_attr($name) . '" placeholder="np. Miejsce postojowe"></td>';
        echo '<td><input class="regular-text" type="text" name="additional_products[' . $i_attr . '][price]" value="' . esc_attr($price) . '" placeholder="np. od 70 000,00 zł brutto"></td>';
        echo '<td><button type="button" class="button link-delete sm-inv-fixed-remove-row">' . esc_html__('Usuń', 'sm-inv-fixed') . '</button></td>';
        echo '</tr>';
        return ob_get_clean();
    }

    private static function tr_select_status(int $selected): void
    {
        $statuses = [
            0 => 'Nieaktywne',
            1 => 'Aktywne',
            2 => 'Wstrzymane',
        ];
        echo '<tr><th scope="row"><label>Status</label></th><td><select name="status">';
        foreach ($statuses as $k => $label) {
            printf('<option value="%d"%s>%s</option>', $k, selected($selected, $k, false), esc_html($label));
        }
        echo '</select></td></tr>';
    }

    // ---------- Handlers ----------

    public static function handle_save_investment(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage()) {
            SM_INV_Fixed_Utils::admin_die_forbidden();
        }

        check_admin_referer('sm_inv_fixed_save_investment');

        $id = absint($_POST['id'] ?? 0);

        // 1. ZAPIS INWESTYCJI
        $new_id = SM_INV_Fixed_DB::investment_upsert($id ?: null, $_POST);

        // 2. DODATKOWE PRODUKTY
        $rows = $_POST['additional_products'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }
        SM_INV_Fixed_DB::additional_products_replace($new_id, $rows);

        // 3. STANDARDY INWESTYCJI  🔴 TO BYŁ PROBLEM
        $standards = $_POST['standards'] ?? [];
        if (!is_array($standards)) {
            $standards = [];
        }

        SM_INV_Fixed_DB::investment_standards_replace($new_id, $standards);

        // 4. REDIRECT
        wp_safe_redirect(
            SM_INV_Fixed_Utils::admin_url_page(
                self::MENU_SLUG,
                ['action' => 'edit', 'id' => $new_id, 'updated' => 1]
            )
        );
        exit;
    }

    public static function handle_delete_investment(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_delete_investment');

        $id = absint($_GET['id'] ?? 0);
        if ($id)
            SM_INV_Fixed_DB::investment_soft_delete($id);

        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG, ['deleted' => 1]));
        exit;
    }

    public static function handle_save_object(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_save_object');

        $id = absint($_POST['id'] ?? 0);
        $new_id = SM_INV_Fixed_DB::object_upsert($id ?: null, $_POST);
        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-objects', ['action' => 'edit', 'id' => $new_id, 'updated' => 1]));
        exit;
    }

    public static function handle_delete_object(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_delete_object');

        $id = absint($_GET['id'] ?? 0);
        if ($id)
            SM_INV_Fixed_DB::object_soft_delete($id);

        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-objects', ['deleted' => 1]));
        exit;
    }

    public static function handle_save_floor(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_save_floor');

        $id = absint($_POST['id'] ?? 0);
        $new_id = SM_INV_Fixed_DB::floor_upsert($id ?: null, $_POST);
        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-floors', ['action' => 'edit', 'id' => $new_id, 'updated' => 1]));
        exit;
    }

    public static function handle_delete_floor(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_delete_floor');

        $id = absint($_GET['id'] ?? 0);
        if ($id)
            SM_INV_Fixed_DB::floor_soft_delete($id);

        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-floors', ['deleted' => 1]));
        exit;
    }

    public static function handle_save_flat(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_save_flat');

        $id = absint($_POST['id'] ?? 0);
        $new_id = SM_INV_Fixed_DB::flat_upsert($id ?: null, $_POST);
        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-flats', ['action' => 'edit', 'id' => $new_id, 'updated' => 1]));
        exit;
    }

    public static function handle_delete_flat(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_delete_flat');

        $id = absint($_GET['id'] ?? 0);
        if ($id)
            SM_INV_Fixed_DB::flat_soft_delete($id);

        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-flats', ['deleted' => 1]));
        exit;
    }

    public static function handle_save_roomtype(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_save_roomtype');

        $id = absint($_POST['id'] ?? 0);
        $new_id = SM_INV_Fixed_DB::room_type_upsert($id ?: null, $_POST);
        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-roomtypes', ['action' => 'edit', 'id' => $new_id, 'updated' => 1]));
        exit;
    }

    public static function handle_delete_roomtype(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage())
            SM_INV_Fixed_Utils::admin_die_forbidden();
        check_admin_referer('sm_inv_fixed_delete_roomtype');

        $id = absint($_GET['id'] ?? 0);
        if ($id)
            SM_INV_Fixed_DB::room_type_delete($id);

        wp_safe_redirect(SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-roomtypes', ['deleted' => 1]));
        exit;
    }

    public static function handle_save_standard(): void
    {
        check_admin_referer('sm_inv_fixed_save_standard');
        $id = absint($_POST['id'] ?? 0);
        $new_id = SM_INV_Fixed_DB::standard_upsert($id ?: null, $_POST);

        wp_safe_redirect(
            SM_INV_Fixed_Utils::admin_url_page(
                self::MENU_SLUG . '-standards',
                ['action' => 'edit', 'id' => $new_id, 'updated' => 1]
            )
        );
        exit;
    }

    public static function handle_delete_standard(): void
    {
        check_admin_referer('sm_inv_fixed_delete_standard');
        $id = absint($_GET['id'] ?? 0);
        if ($id) {
            SM_INV_Fixed_DB::standard_soft_delete($id);
        }
        wp_safe_redirect(
            SM_INV_Fixed_Utils::admin_url_page(self::MENU_SLUG . '-standards', ['deleted' => 1])
        );
        exit;
    }

    public static function ajax_refresh_poi(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage()) {
            wp_send_json_error('Brak uprawnień');
        }

        check_ajax_referer('sm_inv_refresh_poi');

        $investment_id = absint($_POST['investment_id'] ?? 0);

        if (!$investment_id) {
            wp_send_json_error('Brak ID inwestycji');
        }

        $pois = SM_INV_Fixed_POI_Service::refresh_poi($investment_id);

        if (empty($pois)) {
            wp_send_json_error('Brak wyników lub błąd API');
        }

        wp_send_json_success([
            'count' => count($pois),
        ]);
    }
}
