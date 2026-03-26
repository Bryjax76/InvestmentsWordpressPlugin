<?php
if (!defined('ABSPATH'))
    exit;

// DEBUG: Sprawdź czy stała jest zdefiniowana
if (!defined('SM_INV_FIXED_FILE')) {
    error_log('SM_INV_FIXED_FILE NOT DEFINED in class-shortcodes.php');
    // Próbuj zdefiniować fallback
    $possible_paths = [
        dirname(__DIR__) . '/siemaszko-investments-fixed.php',
        WP_PLUGIN_DIR . '/siemaszko-investments-fixed/siemaszko-investments-fixed.php',
        dirname(dirname(__FILE__)) . '/siemaszko-investments-fixed.php',
    ];

    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            define('SM_INV_FIXED_FILE', $path);
            error_log('Fallback SM_INV_FIXED_FILE defined: ' . $path);
            break;
        }
    }

    // Ostateczny fallback
    if (!defined('SM_INV_FIXED_FILE')) {
        define('SM_INV_FIXED_FILE', __FILE__);
        error_log('Ultimate fallback SM_INV_FIXED_FILE: ' . __FILE__);
    }
}


class SM_INV_Fixed_Shortcodes
{
    private static bool $css_printed = false;
    private static bool $frontend_enqueued = false;

    public static function init(): void
    {
        // LISTA INWESTYCJI
        add_shortcode('sm_investments', [__CLASS__, 'shortcode_investments']);

        // STANDARD INWESTYCJI (NOWE)
        add_shortcode('sm_inv_standards', [__CLASS__, 'shortcode_standards']);

        // Other
        add_shortcode('sm_other_investments', [__CLASS__, 'shortcode_other_investments']);
        add_action('wp_ajax_load_more_investments', [__CLASS__, 'ajax_load_more']);
        add_action('wp_ajax_nopriv_load_more_investments', [__CLASS__, 'ajax_load_more']);

        // Frontend: SVG / AJAX
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('wp_ajax_sm_inv_step', [__CLASS__, 'ajax_inv_step']);
        add_action('wp_ajax_nopriv_sm_inv_step', [__CLASS__, 'ajax_inv_step']);

        // Rewrite rules
        self::add_rewrite();
        add_filter('query_vars', [__CLASS__, 'register_query_vars']);
        add_action('template_redirect', [__CLASS__, 'template_redirect']);
        add_filter('body_class', [__CLASS__, 'body_class']);
    }


    /**
     * /inwestycja/{slug}/{id}/  ->  index.php?sm_inv_single=1&sm_inv_id={id}
     */
    public static function add_rewrite(): void
    {
        // /inwestycja/{slug}/{id}/
        add_rewrite_rule(
            '^inwestycja/([^/]+)/([0-9]+)/?$',
            'index.php?sm_inv_single=1&sm_inv_id=$matches[2]',
            'top'
        );

        // /inwestycje/{id}/...
        add_rewrite_rule(
            '^inwestycje/([0-9]+)(/.*)?$',
            'index.php?sm_inv_single=1&sm_inv_id=$matches[1]',
            'top'
        );

        // /mieszkanie/{slug}/{id}/
        add_rewrite_rule(
            '^mieszkanie/([^/]+)/([0-9]+)/?$',
            'index.php?sm_flat_single=1&sm_flat_id=$matches[2]',
            'top'
        );
    }

    public static function body_class(array $classes): array
    {
        if ((int) get_query_var('sm_inv_single') === 1) {
            $classes[] = 'single-investment';
            $classes[] = 'sm-investment-view';

            $inv_id = (int) get_query_var('sm_inv_id');
            if ($inv_id > 0) {
                $classes[] = 'investment-id-' . $inv_id;
            }
        }

        if ((int) get_query_var('sm_flat_single') === 1) {
            $classes[] = 'single-flat';
            $classes[] = 'sm-flat-view';

            $flat_id = (int) get_query_var('sm_flat_id');
            if ($flat_id > 0) {
                $classes[] = 'flat-id-' . $flat_id;
            }
        }

        return $classes;
    }

    public static function register_query_vars(array $vars): array
    {
        $vars[] = 'sm_inv_single';
        $vars[] = 'sm_inv_id';
        $vars[] = 'sm_inv_slug'; // ← DODANE
        $vars[] = 'sm_flat_single';
        $vars[] = 'sm_flat_id';
        return $vars;
    }


    private static function get_single_url(array $inv): string
    {
        $id = (int) ($inv['id'] ?? 0);
        $slug = sanitize_title($inv['title'] ?? 'inwestycja');
        return home_url('/inwestycja/' . $slug . '/' . $id . '/');
    }

    public static function template_redirect(): void
    {
        // DEEP LINK: /inwestycje/{slug}/...
        if ((int) get_query_var('sm_inv_deeplink') === 1) {

            $slug = get_query_var('investment_slug');
            if (!$slug) {
                status_header(404);
                exit;
            }

            // znajdź inwestycję po slug
            $inv = SM_INV_Fixed_DB::investment_get_by_slug($slug);
            if (!$inv || (int) ($inv['status'] ?? -1) === -1) {
                status_header(404);
                exit;
            }

            // UDAJEMY standardowy single investment
            set_query_var('sm_inv_single', 1);
            set_query_var('sm_inv_id', (int) $inv['id']);

            // enqueue + template
            self::enqueue_frontend_assets();

            $template = self::locate_template('single-investment.php');
            include $template;
            exit;
        }

        // Flat single
        if ((int) get_query_var('sm_flat_single') === 1) {
            $flat_id = (int) get_query_var('sm_flat_id');
            if ($flat_id <= 0) {
                status_header(404);
                nocache_headers();
                echo '404';
                exit;
            }

            $flat = null;
            if (class_exists('SM_INV_Fixed_DB') && method_exists('SM_INV_Fixed_DB', 'flat_get')) {
                $flat = SM_INV_Fixed_DB::flat_get($flat_id);
            } else {
                global $wpdb;
                if (class_exists('SM_INV_Fixed_DB') && method_exists('SM_INV_Fixed_DB', 'tables')) {
                    $tables = SM_INV_Fixed_DB::tables();
                    $t = $tables['flats'] ?? '';
                    if ($t) {
                        $flat = $wpdb->get_row(
                            $wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $flat_id),
                            ARRAY_A
                        );
                    }
                }
            }

            if (empty($flat) || (int) ($flat['status'] ?? -1) === -1) {
                status_header(404);
                nocache_headers();
                echo '404';
                exit;
            }

            status_header(200);
            nocache_headers();

            if (method_exists(__CLASS__, 'enqueue_frontend_assets')) {
                self::enqueue_frontend_assets();
            }

            $template = '';
            if (method_exists(__CLASS__, 'locate_template')) {
                $template = self::locate_template('single-flat.php');
            }
            if (empty($template) || !file_exists($template)) {
                status_header(500);
                nocache_headers();
                echo 'Template missing: single-flat.php';
                exit;
            }

            /* ==========================================
             * KONTEKST: floor → object → investment
             * ========================================== */

            $floor = null;
            $object = null;
            $investment = null;

            // 1️⃣ floor (id_bud w flats to ID piętra)
            $floor_id = (int) ($flat['id_bud'] ?? 0);

            if ($floor_id > 0 && method_exists('SM_INV_Fixed_DB', 'floor_get')) {
                $floor = SM_INV_Fixed_DB::floor_get($floor_id);
            }

            // 2️⃣ inwestycja (przez floor)
            $inv_id = (int) ($floor['id_inv'] ?? 0);

            if ($inv_id > 0 && method_exists('SM_INV_Fixed_DB', 'investment_get')) {
                $investment = SM_INV_Fixed_DB::investment_get($inv_id);
            }

            // 3️⃣ budynek (opcjonalnie – do linku „wróć”)
            $object_id = (int) ($floor['id_object'] ?? 0);

            if ($object_id > 0 && method_exists('SM_INV_Fixed_DB', 'object_get')) {
                $object = SM_INV_Fixed_DB::object_get($object_id);
            }

            $investment_title = (string) ($investment['title'] ?? '');
            $investment_id = (int) ($investment['id'] ?? 0);

            // Budowanie linku powrotu do piętra
            $building_svg_no = (int) ($object['id_svg'] ?? 0);
            $floor_no = (int) ($floor['floors_no'] ?? 0);
            $floor_number = $floor_no;

            $building_slug = $building_svg_no > 0 ? ('b' . $building_svg_no) : '';
            $floor_slug = ($building_slug && $floor_no) ? ($building_slug . '-' . $floor_no) : '';

            $back_to_floor_url = '';

            if ($investment_id > 0 && $building_slug && $floor_slug) {
                $back_to_floor_url = home_url("/inwestycje/{$investment_id}/budynki/{$building_slug}/pietra/{$floor_slug}/");
            }

            include $template;
            exit;
        }

        // Investment single (ID lub SLUG)
        if ((int) get_query_var('sm_inv_single') !== 1) {
            return;
        }

        $id = (int) get_query_var('sm_inv_id');
        $slug = get_query_var('sm_inv_slug');

        // jeśli nie ma ID, ale jest slug → znajdź ID po slugu
        if ($id <= 0 && $slug) {
            $inv = SM_INV_Fixed_DB::investment_get_by_slug($slug);
            if ($inv) {
                $id = (int) $inv['id'];
            }
        }

        if ($id <= 0) {
            status_header(404);
            nocache_headers();
            echo '404';
            exit;
        }


        // Get investment row
        $inv = null;

        if (class_exists('SM_INV_Fixed_DB') && method_exists('SM_INV_Fixed_DB', 'investment_get')) {
            $inv = SM_INV_Fixed_DB::investment_get($id);
        } else {
            // Fallback query (prevents fatal if method is missing)
            global $wpdb;
            if (class_exists('SM_INV_Fixed_DB') && method_exists('SM_INV_Fixed_DB', 'tables')) {
                $tables = SM_INV_Fixed_DB::tables();
                $t = $tables['investments'] ?? '';
                if ($t) {
                    $inv = $wpdb->get_row(
                        $wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $id),
                        ARRAY_A
                    );
                }
            }
        }

        // Not found / deleted
        if (empty($inv) || (int) ($inv['status'] ?? -1) === -1) {
            status_header(404);
            nocache_headers();
            echo '404';
            exit;
        }

        // OK
        status_header(200);
        nocache_headers();

        if (method_exists(__CLASS__, 'enqueue_frontend_assets')) {
            self::enqueue_frontend_assets();
        }

        // Choose template (theme override > plugin default)
        $template = '';
        if (method_exists(__CLASS__, 'locate_template')) {
            $template = self::locate_template('single-investment.php');
        }

        if (empty($template) || !file_exists($template)) {
            status_header(500);
            nocache_headers();
            echo 'Template missing: single-investment.php';
            exit;
        }

        // Make $inv available in template scope
        $inv = $inv;

        include $template;
        exit;
    }

    public static function shortcode_investments($atts): string
    {
        $atts = shortcode_atts([
            'status' => '1',
            'per_page' => '100',
            'page' => '1',
            'orderby' => 'order',
            'order' => 'ASC',
            'btn_text' => 'Dowiedz się więcej!',
            'css' => '1',
            'img_size' => 'large',
            'exclude' => '',
        ], $atts, 'sm_investments');

        $status_raw = (string) $atts['status'];

        $status_filter = null;
        $include_deleted = false;

        if ($status_raw === 'all') {
            $include_deleted = true;
        } elseif ($status_raw !== '' && is_numeric($status_raw)) {
            $status_filter = (int) $status_raw;
        } else {
            $status_filter = 1;
        }

        $allowed_orderby = ['id', 'title', 'address', 'status', 'order', 'city', 'district', 'create_date'];
        $orderby = in_array($atts['orderby'], $allowed_orderby, true) ? $atts['orderby'] : 'order';

        $order = strtoupper((string) $atts['order']);
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

        $per_page = max(1, (int) $atts['per_page']);
        $page = max(1, (int) $atts['page']);

        $exclude_ids = [];
        if (!empty($atts['exclude'])) {
            preg_match_all('/\d+/', (string) $atts['exclude'], $m);
            $exclude_ids = array_values(array_filter(array_map('absint', $m[0] ?? [])));
        }

        $fetch = $per_page + max(0, count($exclude_ids));

        [$items, $total] = SM_INV_Fixed_DB::investments_list(
            $fetch,
            $page,
            $orderby,
            $order,
            $status_filter,
            $include_deleted,
            ''
        );

        if (!empty($exclude_ids) && !empty($items)) {
            $items = array_values(array_filter($items, function ($row) use ($exclude_ids) {
                return !in_array((int) ($row['id'] ?? 0), $exclude_ids, true);
            }));
        }

        if (count($items) > $per_page) {
            $items = array_slice($items, 0, $per_page);
        }

        if (empty($items)) {
            return '<div class="sm-investments sm-investments--empty">Brak inwestycji do wyświetlenia.</div>';
        }

        $out = '';
        if ($atts['css'] === '1' && !self::$css_printed) {
            self::$css_printed = true;
            $out .= self::css();
        }

        $btn_text = (string) $atts['btn_text'];

        $out .= '<div class="sm-investments-grid">';

        foreach ($items as $inv) {

            $title = (string) ($inv['title'] ?? '');
            $address = (string) ($inv['address'] ?? '');
            $city = (string) ($inv['city'] ?? '');
            $district = (string) ($inv['district'] ?? '');

            $meta = trim(implode(', ', array_filter([$address, $city, $district])));

            // IMAGE
            $img_url = '';
            $img_hover_url = '';

            $thumb_id = (int) ($inv['thumb'] ?? 0);
            if ($thumb_id > 0) {
                $maybe = wp_get_attachment_image_url($thumb_id, 'large');
                if (!$maybe)
                    $maybe = wp_get_attachment_url($thumb_id);
                if ($maybe)
                    $img_url = $maybe;
            }

            // HOVER IMAGE
            $thumb_hover_id = (int) ($inv['thumb2'] ?? 0);
            if ($thumb_hover_id > 0) {
                $maybe_hover = wp_get_attachment_image_url($thumb_hover_id, 'large');
                if (!$maybe_hover)
                    $maybe_hover = wp_get_attachment_url($thumb_hover_id);
                if ($maybe_hover)
                    $img_hover_url = $maybe_hover;
            }

            if (!$img_hover_url) {
                $img_hover_url = $img_url;
            }

            if (!$img_url) {
                $img_url = 'data:image/svg+xml;utf8,' . rawurlencode(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675">
                    <rect width="100%" height="100%" fill="#f2f2f2"/>
                    <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
                          font-family="Arial" font-size="42" fill="#bdbdbd">Brak zdjęcia</text>
                 </svg>'
                );
            }

            $external_url = trim((string) ($inv['external_url'] ?? ''));

            if (!empty($external_url)) {
                $url = esc_url($external_url);
                $target = ' target="_blank" rel="noopener noreferrer"';
            } elseif ($status_filter == 3 || $status_filter == 1) {
                $url = esc_url(self::get_single_url($inv));
                $target = '';
            } else {
                $url = '#';
                $target = '';
            }

            $out .= '<article class="sm-invest-card ' . $status_filter . '">';
            $out .= '  <a class="sm-invest-card__image" href="' . $url . '"' . $target . '>';

            $out .= '    <img class="sm-invest-card__img sm-invest-card__img--main"
                    src="' . esc_url($img_url) . '"
                    alt="' . esc_attr($title) . '"
                    loading="lazy">';

            $out .= '    <img class="sm-invest-card__img sm-invest-card__img--hover"
                    src="' . esc_url($img_hover_url) . '"
                    alt=""
                    loading="lazy">';

            $out .= '    <span class="sm-invest-card__btn">' . esc_html($btn_text) . '</span>';

            $out .= '  </a>';

            $out .= '  <div class="sm-invest-card__body">';
            $out .= '    <div class="sm-invest-card__title">' . esc_html($title) . '</div>';

            if ($meta !== '') {
                $out .= '  <div class="sm-invest-card__meta">' . esc_html($meta) . '</div>';
            }

            $out .= '  </div>';
            $out .= '</article>';
        }

        $out .= '</div>';

        return $out;
    }

    public static function shortcode_other_investments($atts): string
    {
        $current_id = (int) get_query_var('sm_inv_id');

        $atts = shortcode_atts([
            'status' => '1',
            'orderby' => 'order',
            'order' => 'ASC',
            'btn_text' => 'Dowiedz się więcej!',
        ], $atts, 'sm_other_investments');

        $status_filter = is_numeric($atts['status']) ? (int) $atts['status'] : 1;

        $allowed_orderby = ['id', 'title', 'address', 'status', 'order', 'city', 'district', 'create_date'];
        $orderby = in_array($atts['orderby'], $allowed_orderby, true) ? $atts['orderby'] : 'order';

        $order = strtoupper((string) $atts['order']);
        $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

        [$items] = SM_INV_Fixed_DB::investments_list(
            10,
            1,
            $orderby,
            $order,
            $status_filter,
            false,
            ''
        );

        if (empty($items)) {
            return '';
        }

        // ❌ usuń aktualną inwestycję
        $items = array_values(array_filter($items, function ($row) use ($current_id) {
            return (int) ($row['id'] ?? 0) !== $current_id;
        }));

        // 🔥 tylko 2
        $items = array_slice($items, 0, 2);

        if (empty($items)) {
            return '';
        }

        $out = '<div class="sm-investments-grid">';

        foreach ($items as $inv) {

            $title = (string) ($inv['title'] ?? '');
            $address = (string) ($inv['address'] ?? '');
            $city = (string) ($inv['city'] ?? '');
            $district = (string) ($inv['district'] ?? '');

            $meta = trim(implode(', ', array_filter([$address, $city, $district])));

            // IMAGE
            $img_url = '';
            $thumb_id = (int) ($inv['thumb'] ?? 0);

            if ($thumb_id > 0) {
                $img_url = wp_get_attachment_image_url($thumb_id, 'large');
            }

            if (!$img_url) {
                $img_url = 'data:image/svg+xml;utf8,' . rawurlencode(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="675">
                <rect width="100%" height="100%" fill="#f2f2f2"/>
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
                      font-family="Arial" font-size="42" fill="#bdbdbd">Brak zdjęcia</text>
             </svg>'
                );
            }

            // 🔥 URL LOGIKA
            $external_url = trim((string) ($inv['external_url'] ?? ''));

            if (!empty($external_url)) {
                $url = esc_url($external_url);
                $target = ' target="_blank" rel="noopener noreferrer"';
            } else {
                $url = esc_url(self::get_single_url($inv));
                $target = '';
            }

            $out .= '<article class="sm-invest-card">';
            $out .= '  <a class="sm-invest-card__image" href="' . $url . '"' . $target . '>';
            $out .= '    <img class="sm-invest-card__img sm-invest-card__img--main" src="' . esc_url($img_url) . '" alt="' . esc_attr($title) . '">';
            $out .= '    <span class="sm-invest-card__btn">' . esc_html($atts['btn_text']) . '</span>';
            $out .= '  </a>';

            $out .= '  <div class="sm-invest-card__body">';
            $out .= '    <div class="sm-invest-card__title">' . esc_html($title) . '</div>';

            if ($meta !== '') {
                $out .= '<div class="sm-invest-card__meta">' . esc_html($meta) . '</div>';
            }

            $out .= '  </div>';
            $out .= '</article>';
        }

        $out .= '</div>';

        return $out;
    }

    public static function shortcode_standards(): string
    {
        // Działa TYLKO na stronie pojedynczej inwestycji
        if ((int) get_query_var('sm_inv_single') !== 1) {
            return '';
        }

        $inv_id = (int) get_query_var('sm_inv_id');
        if ($inv_id <= 0) {
            return '';
        }

        // Bez DB -> bez renderu
        if (
            !class_exists('SM_INV_Fixed_DB') ||
            !method_exists('SM_INV_Fixed_DB', 'standards_by_investment')
        ) {
            return '';
        }

        // Pobierz standardy przypisane do inwestycji
        $standards = SM_INV_Fixed_DB::standards_by_investment($inv_id);

        if (empty($standards)) {
            return '';
        }

        ob_start();
        ?>
        <div class="standards-wrapper">
            <?php foreach ($standards as $std): ?>
                <div class="standard-item">
                    <?php if (!empty($std['icon'])): ?>
                        <div class="standard-icon">
                            <?= wp_get_attachment_image(
                                (int) $std['icon'],
                                'thumbnail'
                            ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="standard-name">
                        <span><?= esc_html($std['name'] ?? ''); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private static function locate_template(string $template_name): string
    {
        // 1) Override w motywie: /sm-investments/single-investment.php
        $theme_path = locate_template('sm-investments/' . $template_name);
        if (!empty($theme_path)) {
            return $theme_path;
        }

        // 2) Domyślnie template z wtyczki
        return SM_INV_FIXED_PATH . 'templates/' . $template_name;
    }

    public static function enqueue_frontend_assets(): void
    {
        if (self::$frontend_enqueued) {
            return;
        }

        $is_inv = ((int) get_query_var('sm_inv_single') === 1);
        $is_flat = ((int) get_query_var('sm_flat_single') === 1);

        if (!$is_inv && !$is_flat) {
            return;
        }

        self::$frontend_enqueued = true;

        $ver = time(); // dev versioning

        /*
        |--------------------------------------------------------------------------
        | GŁÓWNE STYLE / SKRYPTY WTYCZKI
        |--------------------------------------------------------------------------
        */

        wp_register_style(
            'sm-inv-frontend',
            plugins_url('assets/frontend.css', SM_INV_FIXED_FILE),
            [],
            $ver
        );

        wp_register_script(
            'sm-inv-frontend',
            plugins_url('assets/frontend.js', SM_INV_FIXED_FILE),
            ['jquery'],
            $ver,
            true
        );

        wp_register_script(
            'sm-inv-dynamic-hotlinks',
            plugins_url('assets/dynamic-hotlinks.js', SM_INV_FIXED_FILE),
            ['jquery'],
            $ver,
            true
        );

        /*
        |--------------------------------------------------------------------------
        | LEAFLET + POI MAP (TYLKO NA WIDOKU INWESTYCJI)
        |--------------------------------------------------------------------------
        */

        if ($is_inv) {

            // Leaflet
            wp_register_style(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                [],
                null
            );

            wp_register_script(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                [],
                null,
                true
            );

            // MarkerCluster
            wp_register_style(
                'leaflet-cluster',
                'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css',
                ['leaflet'],
                null
            );

            wp_register_style(
                'leaflet-cluster-default',
                'https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css',
                ['leaflet'],
                null
            );

            wp_register_script(
                'leaflet-cluster',
                'https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js',
                ['leaflet'],
                null,
                true
            );

            // Font Awesome
            wp_register_style(
                'fontawesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
                [],
                null
            );

            // Nasz skrypt mapy
            wp_register_script(
                'sm-poi-map',
                plugins_url('assets/poi-map.js', SM_INV_FIXED_FILE),
                ['leaflet', 'leaflet-cluster'],
                $ver,
                true
            );

        }

        /*
        |--------------------------------------------------------------------------
        | Localize SPA
        |--------------------------------------------------------------------------
        */

        $inv_slug = get_query_var('name');

        wp_localize_script('sm-inv-frontend', 'SM_INV', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sm_inv_nonce'),
            'investmentSlug' => $inv_slug ?: '',
            'investmentId' => (int) get_query_var('sm_inv_id'),
            'assetsSvgBase' => plugins_url('assets/svg/', SM_INV_FIXED_FILE),
        ]);

        /*
        |--------------------------------------------------------------------------
        | ENQUEUE
        |--------------------------------------------------------------------------
        */

        wp_enqueue_style('sm-inv-frontend');
        wp_enqueue_script('sm-inv-frontend');
        wp_enqueue_script('sm-inv-dynamic-hotlinks');

        if ($is_inv) {

            wp_enqueue_style('leaflet');
            wp_enqueue_style('leaflet-cluster');
            wp_enqueue_style('leaflet-cluster-default');
            wp_enqueue_style('fontawesome');

            wp_enqueue_script('leaflet');
            wp_enqueue_script('leaflet-cluster');
            wp_enqueue_script('sm-poi-map');
        }
    }


    private static function map_flat_status(int $status): string
    {
        // 1 = dostępne, 2 = zarezerwowane, 3 = sprzedane
        // Dostosuj do swoich wartości z bazy danych
        return match ($status) {
            2 => 'reserved',
            0 => 'sold',
            default => 'available',
        };
    }
    public static function ajax_inv_step(): void
    {
        if (!check_ajax_referer('sm_inv_nonce', 'nonce', false)) {
            wp_send_json(['ok' => false, 'message' => 'Invalid nonce']);
        }

        $inv_id = isset($_POST['inv_id']) ? (int) $_POST['inv_id'] : 0;
        $object_selector = isset($_POST['object_id']) ? (int) $_POST['object_id'] : 0;

        // 🔥 KLUCZOWA ZMIANA
        $has_floor = array_key_exists('floor_id', $_POST);
        $floor_selector = $has_floor ? (int) $_POST['floor_id'] : null;

        if ($inv_id <= 0) {
            wp_send_json(['ok' => false, 'message' => 'Missing inv_id']);
        }

        /* ======================================================
         * KROK 1: KLIK BUDYNKU (SVG -> id_svg)
         * ====================================================== */
        if ($object_selector > 0 && !$has_floor) {

            $objects = SM_INV_Fixed_DB::objects_by_investment($inv_id);

            $object = null;
            foreach ($objects as $obj) {
                if ((int) ($obj['id_svg'] ?? 0) === $object_selector) {
                    $object = $obj;
                    break;
                }
            }

            if (!$object) {
                wp_send_json([
                    'ok' => false,
                    'message' => 'Object not found',
                    'debug' => [
                        'inv_id' => $inv_id,
                        'object_selector' => $object_selector,
                        'available_objects' => array_map(static function ($o) {
                            return [
                                'id' => (int) ($o['id'] ?? 0),
                                'name' => (string) ($o['name'] ?? ''),
                                'id_svg' => (int) ($o['id_svg'] ?? 0),
                            ];
                        }, $objects),
                    ],
                ]);
            }

            $real_object_id = (int) $object['id'];
            $svg_html = self::inline_svg_from_attachment((int) ($object['media'] ?? 0));
            $floors = SM_INV_Fixed_DB::floors_by_object($real_object_id);

            wp_send_json([
                'ok' => true,
                'step' => 'object',
                'object_id' => $real_object_id,
                'title' => 'Wybierz piętro',
                'breadcrumb' => ['Budynek ' . $object_selector],
                'svg_html' => $svg_html,
                'floors' => array_values(array_map(static function ($f) {
                    return [
                        'id' => (int) ($f['id'] ?? 0),
                        'floor_no' => (int) ($f['floors_no'] ?? 0),
                        'id_svg' => (int) ($f['id_svg'] ?? 0),
                        'name' => (string) ($f['name'] ?? ''),
                    ];
                }, $floors)),
            ]);
        }

        /* ======================================================
         * KROK 2: KLIK PIĘTRA (SVG -> id_svg)
         * ====================================================== */
        if ($object_selector > 0 && $has_floor) {

            $real_object_id = $object_selector;
            $floors = SM_INV_Fixed_DB::floors_by_object($real_object_id);

            $floor = null;
            foreach ($floors as $f) {
                if ((int) ($f['id_svg'] ?? 0) === $floor_selector) {
                    $floor = $f;
                    break;
                }
            }

            if (!$floor) {
                wp_send_json([
                    'ok' => false,
                    'message' => 'Floor not found',
                    'debug' => [
                        'object_id' => $real_object_id,
                        'floor_selector' => $floor_selector,
                        'available_floors' => array_map(static function ($f) {
                            return [
                                'id' => (int) ($f['id'] ?? 0),
                                'id_svg' => (int) ($f['id_svg'] ?? 0),
                                'floors_no' => (int) ($f['floors_no'] ?? 0),
                            ];
                        }, $floors),
                    ],
                ]);
            }

            $real_floor_id = (int) $floor['id'];
            $svg_html = self::inline_svg_from_attachment((int) ($floor['media'] ?? 0));

            [$flats] = SM_INV_Fixed_DB::flats_list(
                2000,
                1,
                'id',
                'ASC',
                ['id_bud' => $real_floor_id]
            );

            $map = [];
            $flats_data = [];

            foreach ($flats as $r) {
                $svgId = (string) ($r['id_svg'] ?? '');
                $id = (int) ($r['id'] ?? 0);

                if ($svgId !== '' && $id > 0) {
                    $map[$svgId] = $id;

                    $flats_data[] = [
                        'id' => $id,
                        'id_svg' => $svgId,
                        'status' => self::map_flat_status((int) ($r['status'] ?? 1)),
                        'name' => (string) ($r['name'] ?? ''),
                        'url' => self::flat_url($id),
                    ];
                }
            }

            wp_send_json([
                'ok' => true,
                'step' => 'floor',
                'floor_id' => $real_floor_id,
                'title' => 'Wybierz mieszkanie',
                'breadcrumb' => [
                    'Budynek ' . $real_object_id,
                    'Piętro ' . ((int) ($floor['floors_no'] ?? 0)),
                ],
                'svg_html' => $svg_html,
                'flats_map' => $map,
                'flats' => $flats_data,
            ]);
        }

        /* ======================================================
         * KROK 0: PLAN INWESTYCJI
         * ====================================================== */

        $svg_html = '';
        $inv = SM_INV_Fixed_DB::investment_get($inv_id);

        if ($inv) {
            $svg_html = self::inline_svg_from_attachment((int) ($inv['media'] ?? 0));
        }

        $objects = SM_INV_Fixed_DB::objects_by_investment($inv_id);

        wp_send_json([
            'ok' => true,
            'step' => 'investment',
            'title' => 'Wybierz budynek',
            'breadcrumb' => [],
            'svg_html' => $svg_html,
            'objects' => array_values(array_map(static function ($o) {
                return [
                    'id' => (int) ($o['id'] ?? 0),
                    'id_svg' => (int) ($o['id_svg'] ?? 0),
                    'name' => (string) ($o['name'] ?? ''),
                ];
            }, $objects)),
        ]);
    }


    private static function inline_svg_from_attachment(int $attachment_id): string
    {
        if ($attachment_id <= 0) {
            return '';
        }

        $file = get_attached_file($attachment_id);
        if (!$file || !file_exists($file)) {
            return '';
        }

        $svg = file_get_contents($file);
        if (!is_string($svg)) {
            return '';
        }

        // Remove XML declaration to avoid invalid nesting
        $svg = preg_replace('/^\s*<\?xml[^>]*>\s*/i', '', $svg);
        return $svg ?: '';
    }

    public static function flat_url(int $flat_id): string
    {
        $flat = SM_INV_Fixed_DB::flat_get($flat_id);

        if (!$flat) {
            return home_url('/mieszkanie/' . $flat_id . '/');
        }

        $label = '';

        if (!empty($flat['code'])) {
            $label = str_replace(['/', '.', ' '], '-', $flat['code']);
        } elseif (!empty($flat['flat'])) {
            $label = 'mieszkanie-' . $flat['flat'];
        } else {
            $label = 'mieszkanie';
        }

        $slug = sanitize_title($label);

        return home_url('/mieszkanie/' . $slug . '/' . $flat_id . '/');
    }

    public static function ajax_load_more(): void
    {
        $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;

        echo self::shortcode_investments([
            'status' => '0',
            'per_page' => '4',
            'page' => $page,
            'orderby' => 'order',
            'order' => 'ASC',
            'css' => '0', // Do not load CSS once again
        ]);

        wp_die();
    }

    private static function css(): string
    {
        return '<style>
            .sm-investments-grid{
                display:grid;
                grid-template-columns: repeat(auto-fit, minmax(620px, 1fr));
                gap: 42px 48px;
                align-items:start;
                justify-items:center;
            }
            @media (max-width: 900px){
                .sm-investments-grid{ grid-template-columns: 1fr; gap: 28px; }
            }

            .sm-invest-card{
                display:block;
                width:100%;
                max-width:720px;
            }
            .sm-invest-card__image{
                position:relative;
                display:block;
                overflow:hidden;
                border-radius: 4px;
                background:#f2f2f2;
                aspect-ratio: 12 / 9;
            }
            .sm-invest-card__image img{
                width:100%;
                height:100%;
                object-fit:cover;
                display:block;
                transform: scale(1.001);
            }
            .sm-invest-card__image {
                position: relative;
                display: block;
                overflow: hidden;
            }

            .sm-invest-card__img {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: opacity .35s ease;
            }

            .sm-invest-card__img--main {
                opacity: 1;
            }

            .sm-invest-card__img--hover {
                opacity: 0;
            }

            .sm-invest-card:hover .sm-invest-card__img--hover {
                opacity: 1;
            }

            .sm-invest-card:hover .sm-invest-card__img--main {
                opacity: 0;
            }
            .sm-invest-card__btn{
                position:absolute;
                right:14px;
                bottom:14px;
                background:#2b2d83;
                color:#fff;
                padding:10px 16px;
                border-radius: 999px;
                font-size: 14px;
                line-height:1;
                box-shadow: 0 6px 14px rgba(0,0,0,.15);
                white-space:nowrap;
            }

            .sm-invest-card__body{
                padding-top: 18px;
            }
            .sm-invest-card__title{
                font-size: 34px;
                letter-spacing: .02em;
                font-weight: 300;
                color: #bdbdbd;
                text-transform: uppercase;
                margin: 0 0 8px 0;
            }
            .sm-invest-card__meta{
                font-size: 14px;
                font-weight: 700;
                letter-spacing: .06em;
                text-transform: uppercase;
                color:#111;
            }
        </style>';
    }
}
