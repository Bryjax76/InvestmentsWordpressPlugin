<?php
if (!defined('ABSPATH'))
    exit;

class SM_INV_Fixed_Shortcodes
{
    private static bool $css_printed = false;
    private static bool $frontend_enqueued = false;

    public static function init(): void
    {
        add_shortcode('sm_investments', [__CLASS__, 'shortcode_investments']);

        // Frontend: dynamic SVG navigation (buildings -> floors -> flats)
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('wp_ajax_sm_inv_step', [__CLASS__, 'ajax_inv_step']);
        add_action('wp_ajax_nopriv_sm_inv_step', [__CLASS__, 'ajax_inv_step']);

        // Register rewrite immediately (no nested init hook)
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
        add_rewrite_rule(
            '^inwestycja/([^/]+)/([0-9]+)/?$',
            'index.php?sm_inv_single=1&sm_inv_id=$matches[2]',
            'top'
        );

        // /mieszkanie/{slug}/{id}/  ->  index.php?sm_flat_single=1&sm_flat_id={id}
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

            include $template;
            exit;
        }

        // Investment single
        if ((int) get_query_var('sm_inv_single') !== 1) {
            return;
        }

        $id = (int) get_query_var('sm_inv_id');
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
            'status' => '1',     // 1 = aktywne
            'per_page' => '100',
            'page' => '1',
            'orderby' => 'order',
            'order' => 'ASC',
            'btn_text' => 'Dowiedz się więcej!',
            'css' => '1',
            'img_size' => 'large',
            'exclude' => '',      // NOWE: np. "12" albo "12,13"
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

        // per_page + page
        $per_page = max(1, (int) $atts['per_page']);
        $page = max(1, (int) $atts['page']);

        // Exclude ids
        $exclude_ids = [];
        if (!empty($atts['exclude'])) {
            preg_match_all('/\d+/', (string) $atts['exclude'], $m);
            $exclude_ids = array_values(array_filter(array_map('absint', $m[0] ?? [])));
        }

        // Jeśli wykluczamy ID, pobierzmy trochę więcej, żeby po filtrze nadal mieć per_page
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

        // Docięcie po filtrze exclude
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
            $thumb_id = (int) ($inv['thumb'] ?? 0);
            if ($thumb_id > 0) {
                $maybe = wp_get_attachment_image_url($thumb_id, 'full');
                if (!$maybe)
                    $maybe = wp_get_attachment_url($thumb_id);
                if ($maybe)
                    $img_url = $maybe;
            }
            if (!$img_url) {
                $media = trim((string) ($inv['media'] ?? ''));
                if ($media !== '') {
                    if (preg_match('~^https?://~i', $media)) {
                        $img_url = $media;
                    } elseif (function_exists('str_starts_with') && str_starts_with($media, '/')) {
                        $img_url = home_url($media);
                    } elseif (function_exists('str_starts_with') && (str_starts_with($media, 'wp-content/') || str_starts_with($media, 'uploads/'))) {
                        $img_url = home_url('/' . ltrim($media, '/'));
                    }
                }
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

            // SINGLE URL
            $url = esc_url(self::get_single_url($inv));

            $out .= '<article class="sm-invest-card">';
            $out .= '  <a class="sm-invest-card__image" href="' . $url . '">';
            $out .= '    <img src="' . esc_url($img_url) . '" alt="' . esc_attr($title) . '" loading="lazy">';
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

        $ver = defined('SM_INV_FIXED_VERSION') ? SM_INV_FIXED_VERSION : '1.0.0';

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

        wp_localize_script('sm-inv-frontend', 'SM_INV', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sm_inv_nonce'),
        ]);

        wp_enqueue_style('sm-inv-frontend');
        wp_enqueue_script('sm-inv-frontend');
    }

    public static function ajax_inv_step(): void
    {
        // Basic hardening
        if (!check_ajax_referer('sm_inv_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 403);
        }

        $inv_id = isset($_POST['inv_id']) ? (int) $_POST['inv_id'] : 0;
        $object_id = isset($_POST['object_id']) ? (int) $_POST['object_id'] : 0;
        $floor_id = isset($_POST['floor_id']) ? (int) $_POST['floor_id'] : 0;

        if ($inv_id <= 0) {
            wp_send_json_error(['message' => 'Missing inv_id'], 400);
        }

        $inv = SM_INV_Fixed_DB::investment_get($inv_id);
        if (!$inv) {
            wp_send_json_error(['message' => 'Investment not found'], 404);
        }

        /* =====================================================
         * 1) INVESTMENT → OBJECTS (budynki)
         * ===================================================== */
        if ($object_id <= 0) {
            $svg_html = self::inline_svg_from_attachment((int) ($inv['svg'] ?? 0));
            $objects = SM_INV_Fixed_DB::objects_list($inv_id, 200, 0)['items'] ?? [];

            wp_send_json_success([
                'step' => 'objects',
                'svg_html' => $svg_html,
                'objects' => array_values(array_map(static function ($o) {
                    return [
                        'id' => (int) ($o['id'] ?? 0),
                        'name' => (string) ($o['name'] ?? ''),
                    ];
                }, $objects)),
            ]);
        }

        $object = SM_INV_Fixed_DB::objects_get($object_id);
        if (!$object || (int) ($object['id_inv'] ?? 0) !== $inv_id) {
            wp_send_json_error(['message' => 'Object not found'], 404);
        }

        /* =====================================================
         * 2) OBJECT → FLOORS (piętra)
         * ===================================================== */
        if ($floor_id <= 0) {
            $svg_html = self::inline_svg_from_attachment((int) ($object['id_svg'] ?? 0));
            $floors = SM_INV_Fixed_DB::floors_get($inv_id, $object_id, 200, 0)['items'] ?? [];

            $has_object_svg = trim($svg_html) !== '';

            wp_send_json_success([
                'step' => 'floors',
                'svg_html' => $svg_html,
                'has_object_svg' => $has_object_svg,
                'floors' => array_values(array_map(static function ($f) {
                    return [
                        'id' => (int) ($f['id'] ?? 0),
                        'floor_no' => (int) ($f['floor_no'] ?? 0),
                    ];
                }, $floors)),
            ]);
        }

        $floor = SM_INV_Fixed_DB::floors_get_single($floor_id);
        if (!$floor || (int) ($floor['id_object'] ?? 0) !== $object_id) {
            wp_send_json_error(['message' => 'Floor not found'], 404);
        }

        /* =====================================================
         * 3) FLOOR → FLATS (mieszkania + statusy)
         * ===================================================== */
        $svg_html = self::inline_svg_from_attachment((int) ($floor['id_svg'] ?? 0));
        $rows = SM_INV_Fixed_DB::flats_list($floor_id, 1000, 0)['items'] ?? [];

        $flats = [];

        foreach ($rows as $r) {
            $idSvg = trim((string) ($r['id_svg'] ?? ''));
            if ($idSvg === '') {
                continue;
            }

            // mapowanie statusów DB → CSS
            $status = match ((int) ($r['status'] ?? 1)) {
                2 => 'reserved',
                3 => 'sold',
                default => 'available',
            };

            $flats[] = [
                'id' => (int) $r['id'],
                'id_svg' => $idSvg,      // np. "1", "11a"
                'status' => $status,     // available | reserved | sold
            ];
        }

        wp_send_json([
            'ok' => true,
            'step' => 'floor',
            'svg_html' => $svg_html,
            'flats' => $flats,
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
        // slug is cosmetic (friendly url); id is authoritative
        $flat = SM_INV_Fixed_DB::flats_get($flat_id);
        $slug = $flat ? sanitize_title((string) ($flat['name'] ?? 'mieszkanie')) : 'mieszkanie';
        return home_url('/mieszkanie/' . $slug . '/' . $flat_id . '/');
    }

    private static function css(): string
    {
        return '<style>
            .sm-investments-grid{
                display:grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 42px 48px;
                align-items:start;
            }
            @media (max-width: 900px){
                .sm-investments-grid{ grid-template-columns: 1fr; gap: 28px; }
            }

            .sm-invest-card{ display:block; }
            .sm-invest-card__image{
                position:relative;
                display:block;
                overflow:hidden;
                border-radius: 4px;
                background:#f2f2f2;
                aspect-ratio: 16 / 9;
            }
            .sm-invest-card__image img{
                width:100%;
                height:100%;
                object-fit:cover;
                display:block;
                transform: scale(1.001);
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
