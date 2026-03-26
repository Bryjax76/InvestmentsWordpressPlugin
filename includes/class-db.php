<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SM_INV_Fixed_DB
{

    // Tables are intentionally NOT prefixed (legacy compatibility)
    public static function tables(): array
    {
        return [
            'investments' => 'sm_investments',
            // Additional products linked to investments
            'additional_products' => 'sm_additional_products',
            'objects' => 'sm_objects',
            'floors' => 'sm_buildings',
            'flats' => 'sm_flats',
            'room_types' => 'sm_room_type',
            'standards' => 'sm_inv_standards',
            'investment_standards' => 'sm_inv_investment_standards',
            'poi' => 'sm_inv_poi',
            'price_history' => 'sm_flats_price_history',
        ];
    }

    // Cache for column-existence checks
    private static array $columns_cache = [];

    private static function has_column(string $table, string $column): bool
    {
        global $wpdb;

        $key = $table . ':' . $column;
        if (isset(self::$columns_cache[$key])) {
            return self::$columns_cache[$key];
        }

        // Safety: allow only typical legacy table names
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            self::$columns_cache[$key] = false;
            return false;
        }

        $found = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table}` LIKE %s",
            $column
        ));

        self::$columns_cache[$key] = !empty($found);
        return self::$columns_cache[$key];
    }

    private static function column_type(string $table, string $column): ?string
    {
        global $wpdb;

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SHOW COLUMNS FROM `{$table}` LIKE %s",
            $column
        ), ARRAY_A);

        return $row['Type'] ?? null;
    }

    /**
     * Sanitize CSV of attachment IDs. Accepts "1,2, 3" and returns "1,2,3".
     */
    private static function sanitize_ids_csv($raw): string
    {
        $raw = (string) wp_unslash($raw);
        $raw = preg_replace('/[^0-9,\s]/', '', $raw);
        $parts = array_filter(array_map('trim', explode(',', $raw)), static function ($v) {
            return $v !== '';
        });
        $ids = array_map('absint', $parts);
        $ids = array_values(array_filter($ids, static function ($v) {
            return $v > 0;
        }));
        return implode(',', $ids);
    }

    public static function maybe_create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $tables = self::tables();
        $missing = [];

        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists !== $table)
                $missing[] = $table;
        }

        // Create only missing ones (existing installs may have extra columns - we don't touch them)
        if (in_array($tables['investments'], $missing, true)) {
            $t = $tables['investments'];
            $sql = "CREATE TABLE {$t} (
                id INT(11) NOT NULL AUTO_INCREMENT,
                create_date TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                content TEXT NULL,
                excerpt TEXT NULL,
                content_title TEXT NULL,
                author VARCHAR(255) NULL,
                city VARCHAR(255) NULL,
                district VARCHAR(255) NULL,
                status TINYINT(4) NOT NULL DEFAULT 1,
                lastminute TINYINT(1) NOT NULL DEFAULT 0,
                title VARCHAR(255) NULL,
                address VARCHAR(1023) NULL,
                media INT(11) NOT NULL DEFAULT 0,
                is_floor TINYINT(1) NOT NULL DEFAULT 0,
                image INT(11) NOT NULL DEFAULT 0,
                thumb INT(11) NOT NULL DEFAULT 0,
                thumb2 INT(11) NOT NULL DEFAULT 0,
                longitude VARCHAR(255) NULL,
                latitude VARCHAR(255) NULL,
                gallery TEXT NULL,
                hint VARCHAR(255) NULL,
                `order` INT(11) NOT NULL DEFAULT 0,
                buildings_no INT(11) NOT NULL DEFAULT 0,
                is_price SMALLINT(4) NOT NULL DEFAULT 0,
                google_map TEXT NULL,
                company_name VARCHAR(255) NULL,
                dg_voivodeship VARCHAR(100) NULL,
                dg_county VARCHAR(100) NULL,
                dg_commune VARCHAR(100) NULL,
                dg_city VARCHAR(100) NULL,
                dg_street VARCHAR(100) NULL,
                dg_project_number VARCHAR(100) NULL,
                dg_postcode VARCHAR(100) NULL,
                dg_property_type VARCHAR(100) NULL,
                PRIMARY KEY (id),
                KEY status (status),
                KEY `order` (`order`)
            ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['additional_products'], $missing, true)) {
            $t = $tables['additional_products'];
            $sql = "CREATE TABLE {$t} (
                id INT(11) NOT NULL AUTO_INCREMENT,
                inv_id INT(11) NOT NULL DEFAULT 0,
                name VARCHAR(255) NULL,
                price VARCHAR(64) NULL,
                icon INT(11) NOT NULL DEFAULT 0,
                `order` INT(11) NOT NULL DEFAULT 0,
                status TINYINT(4) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                KEY inv_id (inv_id),
                KEY status (status),
                KEY `order` (`order`)
            ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['objects'], $missing, true)) {
            $t = $tables['objects'];
            $sql = "CREATE TABLE {$t} (
                id INT(11) NOT NULL AUTO_INCREMENT,
                inv_id INT(11) NOT NULL DEFAULT 0,
                name VARCHAR(255) NULL,
                media INT(11) NOT NULL DEFAULT 0,
                id_svg INT(11) NOT NULL DEFAULT 0,
                status INT(11) NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                KEY inv_id (inv_id),
                KEY status (status)
            ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['floors'], $missing, true)) {
            $t = $tables['floors'];
            $sql = "CREATE TABLE {$t} (
                id INT(11) NOT NULL AUTO_INCREMENT,
                id_object INT(11) NOT NULL DEFAULT 0,
                id_inv INT(11) NOT NULL DEFAULT 0,
                name VARCHAR(128) NULL,
                floors_no INT(11) NOT NULL DEFAULT 0,
                status INT(11) NOT NULL DEFAULT 1,
                media INT(11) NOT NULL DEFAULT 0,
                id_svg INT(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY id_object (id_object),
                KEY id_inv (id_inv),
                KEY status (status)
            ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['flats'], $missing, true)) {
            $t = $tables['flats'];
            $sql = "CREATE TABLE {$t} (
                id INT(11) NOT NULL AUTO_INCREMENT,
                code VARCHAR(255) NULL,
                id_bud INT(11) NOT NULL DEFAULT 0,
                id_svg VARCHAR(255) NULL,
                meters FLOAT NOT NULL DEFAULT 0,
                rooms INT(11) NOT NULL DEFAULT 0,
                price FLOAT NOT NULL DEFAULT 0,
                status INT(11) NOT NULL DEFAULT 1,
                flat INT(11) NOT NULL DEFAULT 0,
                media INT(11) NOT NULL DEFAULT 0,
                type_id INT(11) NOT NULL DEFAULT 0,
                total_price FLOAT NOT NULL DEFAULT 0,
                dg_price_date DATETIME NULL,
                dg_total_price_date DATETIME NULL,
                dg_final_price VARCHAR(255) NULL,
                dg_final_price_date DATETIME NULL,
                dg_part_type VARCHAR(255) NULL,
                dg_part_code VARCHAR(255) NULL,
                dg_part_price VARCHAR(255) NULL,
                dg_part_price_date DATETIME NULL,
                dg_room_type VARCHAR(255) NULL,
                dg_room_code VARCHAR(255) NULL,
                dg_room_price VARCHAR(255) NULL,
                dg_room_price_date DATETIME NULL,
                dg_rights_description VARCHAR(255) NULL,
                dg_rights_price VARCHAR(255) NULL,
                dg_rights_price_date DATETIME NULL,
                dg_other_description VARCHAR(255) NULL,
                dg_other_price VARCHAR(255) NULL,
                dg_other_price_date DATETIME NULL,
                dg_prospect_url VARCHAR(255) NULL,
                dg_code VARCHAR(255) NULL,
                dg_price VARCHAR(255) NULL,
                dg_total_price VARCHAR(255) NULL,
                PRIMARY KEY (id),
                KEY id_bud (id_bud),
                KEY status (status),
                KEY type_id (type_id)
            ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['room_types'], $missing, true)) {
            $t = $tables['room_types'];
            $sql = "CREATE TABLE {$t} (
                id INT(11) NOT NULL AUTO_INCREMENT,
                name VARCHAR(255) NULL,
                slug VARCHAR(255) NULL,
                PRIMARY KEY (id),
                KEY slug (slug)
            ) {$charset};";
            dbDelta($sql);
        }

        // --- MIGRACJE / brakujące kolumny w istniejących instalacjach ---
        $investments_table = self::tables()['investments'];

        // thumb2
        if (!self::has_column($investments_table, 'thumb2')) {
            $wpdb->query("ALTER TABLE `{$investments_table}` ADD COLUMN `thumb2` INT(11) NOT NULL DEFAULT 0 AFTER `thumb`");
        }

        // gallery: upgrade from INT to TEXT if needed
        if (self::has_column($investments_table, 'gallery')) {
            $type = self::column_type($investments_table, 'gallery');
            if ($type && stripos($type, 'text') === false) {
                $wpdb->query("ALTER TABLE `{$investments_table}` MODIFY COLUMN `gallery` TEXT NULL");
            }
        }

        // 🔥 NOWE: external_url
        if (!self::has_column($investments_table, 'external_url')) {
            $wpdb->query("ALTER TABLE `{$investments_table}` ADD COLUMN `external_url` VARCHAR(1023) NULL AFTER `address`");
        }

        // 🔥 NOWE: completion_date
        if (!self::has_column($investments_table, 'completion_date')) {
            $wpdb->query("ALTER TABLE `{$investments_table}` ADD COLUMN `completion_date` VARCHAR(100) NULL AFTER `external_url`");
        }

        if (in_array($tables['standards'], $missing, true)) {
            $t = $tables['standards'];
            $sql = "CREATE TABLE {$t} (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                icon INT(11) NOT NULL DEFAULT 0,
                `order` INT DEFAULT 0,
                status TINYINT DEFAULT 1
            ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['investment_standards'], $missing, true)) {
            $t = $tables['investment_standards'];
            $sql = "CREATE TABLE {$t} (
                investment_id INT UNSIGNED NOT NULL,
                standard_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (investment_id, standard_id),
                KEY standard_id (standard_id)
            ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['poi'], $missing, true)) {
            $t = $tables['poi'];
            $sql = "CREATE TABLE {$t} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            investment_id INT(11) NOT NULL,
            category VARCHAR(50) NOT NULL,
            name VARCHAR(255) NULL,
            lat DECIMAL(10,7) NOT NULL,
            lng DECIMAL(10,7) NOT NULL,
            osm_type VARCHAR(10) NOT NULL,
            osm_id BIGINT(20) NOT NULL,
            meta_json LONGTEXT NULL,
            fetched_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY investment_id (investment_id),
            KEY category (category),
            UNIQUE KEY osm_unique (investment_id, osm_type, osm_id)
        ) {$charset};";
            dbDelta($sql);
        }

        if (in_array($tables['price_history'], $missing, true)) {
            $t = $tables['price_history'];
            $sql = "CREATE TABLE {$t} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        flat_id INT(11) NOT NULL,
        old_dg_price VARCHAR(255) NULL,
        old_price FLOAT NOT NULL,
        new_dg_price VARCHAR(255) NULL,
        new_price FLOAT NULL,
        change_date DATETIME NOT NULL,
        changed_by BIGINT UNSIGNED NULL,
        PRIMARY KEY (id),
        KEY flat_id (flat_id),
        KEY change_date (change_date)
    ) {$charset};";
            dbDelta($sql);
        }
    }

    public static function missing_tables(): array
    {
        global $wpdb;
        $missing = [];
        foreach (self::tables() as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($exists !== $table)
                $missing[] = $table;
        }
        return $missing;
    }

    // ---------- Investments ----------
    public static function investments_list(
        int $per_page,
        int $paged,
        string $orderby,
        string $order,
        ?int $status_filter = null,
        bool $include_deleted = false,
        string $search = ''
    ): array {
        global $wpdb;
        $t = self::tables()['investments'];

        $allowed_orderby = ['id', 'title', 'address', 'status', 'order', 'city', 'district', 'create_date'];
        $orderby = SM_INV_Fixed_Utils::sanitize_orderby($orderby, $allowed_orderby, 'id');

        // IMPORTANT: `order` is a reserved keyword in SQL -> must be backticked
        $orderby_sql = ($orderby === 'order') ? '`order`' : $orderby;

        $order = strtoupper(SM_INV_Fixed_Utils::sanitize_order_dir($order)); // ASC/DESC
        $per_page = max(1, (int) $per_page);
        $paged = max(1, (int) $paged);
        $offset = max(0, ($paged - 1) * $per_page);

        $where = [];
        $args = [];

        if ($status_filter !== null) {
            $where[] = 'status = %d';
            $args[] = (int) $status_filter;
        } elseif (!$include_deleted) {
            $where[] = 'status <> %d';
            $args[] = -1; // default: hide deleted
        }

        $search = trim((string) $search);
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(title LIKE %s OR address LIKE %s OR city LIKE %s OR district LIKE %s)';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;
        }

        $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT SQL_CALC_FOUND_ROWS *
            FROM {$t}
            {$where_sql}
            ORDER BY {$orderby_sql} {$order}
            LIMIT %d OFFSET %d";

        $args[] = $per_page;
        $args[] = $offset;

        $prepared = $wpdb->prepare($sql, ...$args);

        $items = $wpdb->get_results($prepared, ARRAY_A);
        $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');

        return [$items ?: [], $total];
    }


    public static function investment_get(int $id): ?array
    {
        global $wpdb;
        $t = self::tables()['investments'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function investment_upsert(?int $id, array $data): int
    {
        global $wpdb;
        $t = self::tables()['investments'];

        $clean = [
            'title' => sanitize_text_field($data['title'] ?? ''),
            'address' => sanitize_text_field($data['address'] ?? ''),
            'external_url' => esc_url_raw($data['external_url'] ?? ''), // 🔥 NOWE
            'completion_date' => sanitize_text_field($data['completion_date'] ?? ''), // 🔥 NOWE
            'city' => sanitize_text_field($data['city'] ?? ''),
            'district' => sanitize_text_field($data['district'] ?? ''),
            'excerpt' => wp_kses_post(wp_unslash($data['excerpt'] ?? '')),
            'content' => wp_kses_post(wp_unslash($data['content'] ?? '')),
            'content_title' => wp_kses_post(wp_unslash($data['content_title'] ?? '')),
            'author' => sanitize_text_field($data['author'] ?? ''),
            'google_map' => wp_kses_post(wp_unslash($data['google_map'] ?? '')),
            'latitude' => sanitize_text_field($data['latitude'] ?? ''),
            'longitude' => sanitize_text_field($data['longitude'] ?? ''),
            'buildings_no' => absint($data['buildings_no'] ?? 0),
            'gallery' => self::sanitize_ids_csv($data['gallery'] ?? ''),
            'hint' => sanitize_text_field($data['hint'] ?? ''),
            'is_floor' => !empty($data['is_floor']) ? 1 : 0,
            'order' => absint($data['order'] ?? 0),
            'status' => (int) ($data['status'] ?? 1),
            'lastminute' => !empty($data['lastminute']) ? 1 : 0,
            'is_price' => (int) ($data['is_price'] ?? 0),
            'company_name' => sanitize_text_field($data['company_name'] ?? ''),
            'media' => absint($data['media'] ?? 0),
            'image' => absint($data['image'] ?? 0),
            'thumb' => absint($data['thumb'] ?? 0),
            'thumb2' => absint($data['thumb2'] ?? 0),
        ];

        // dg fields
        $dg_fields = [
            'dg_voivodeship',
            'dg_county',
            'dg_commune',
            'dg_city',
            'dg_street',
            'dg_project_number',
            'dg_postcode',
            'dg_property_type'
        ];

        foreach ($dg_fields as $k) {
            if (array_key_exists($k, $data)) {
                $clean[$k] = sanitize_text_field($data[$k] ?? '');
            }
        }

        if (empty($id)) {
            $clean['create_date'] = current_time('mysql');
            $wpdb->insert($t, $clean, self::formats_for($clean));
            return (int) $wpdb->insert_id;
        }

        $wpdb->update($t, $clean, ['id' => $id], self::formats_for($clean), ['%d']);
        return (int) $id;
    }

    public static function investment_soft_delete(int $id): void
    {
        global $wpdb;
        $t = self::tables()['investments'];
        $wpdb->update($t, ['status' => -1], ['id' => $id], ['%d'], ['%d']);
    }

    // ---------- Additional Products ----------

    public static function additional_products_by_investment(int $inv_id): array
    {
        global $wpdb;
        $t = self::tables()['additional_products'];
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t} WHERE inv_id=%d AND status <> %d ORDER BY `order` ASC, id ASC",
                $inv_id,
                -1
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Replace all additional products for a given investment.
     * Expected input: array of rows with keys: name, price, icon.
     */
    public static function additional_products_replace(int $inv_id, array $rows): void
    {
        global $wpdb;

        $t = self::tables()['additional_products'];
        $inv_id = absint($inv_id);
        if (!$inv_id)
            return;

        // Delete old rows (hard delete) – it's configuration data
        $wpdb->delete($t, ['inv_id' => $inv_id], ['%d']);

        $order = 0;
        foreach ($rows as $row) {
            $name = sanitize_text_field($row['name'] ?? '');
            $price = sanitize_text_field($row['price'] ?? '');
            $icon = absint($row['icon'] ?? 0);

            if ($name === '' && $price === '' && $icon === 0) {
                continue;
            }

            $wpdb->insert(
                $t,
                [
                    'inv_id' => $inv_id,
                    'name' => $name,
                    'price' => $price,
                    'icon' => $icon,
                    'order' => $order,
                    'status' => 1,
                ],
                ['%d', '%s', '%s', '%d', '%d', '%d']
            );
            $order++;
        }
    }

    // ---------- Objects ----------
    public static function objects_list(int $per_page, int $paged, string $orderby, string $order, array $filters = []): array
    {
        global $wpdb;
        $t = self::tables()['objects'];

        $allowed_orderby = ['id', 'name', 'inv_id', 'status'];
        $orderby = SM_INV_Fixed_Utils::sanitize_orderby($orderby, $allowed_orderby, 'id');
        $order = SM_INV_Fixed_Utils::sanitize_order_dir($order);
        $offset = max(0, ($paged - 1) * $per_page);

        $where = ['status <> %d'];
        $args = [-1];

        $inv_id = absint($filters['inv_id'] ?? 0);
        if ($inv_id > 0) {
            $where[] = 'inv_id = %d';
            $args[] = $inv_id;
        }

        $status = isset($filters['status']) ? (int) $filters['status'] : null;
        if ($status !== null && $status !== -999) {
            // -999 means "all (except deleted)"
            $where[] = 'status = %d';
            $args[] = $status;
        }

        $search = trim((string) ($filters['s'] ?? $filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(name LIKE %s)';
            $args[] = $like;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT SQL_CALC_FOUND_ROWS *
                FROM {$t}
                {$where_sql}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        $args[] = $per_page;
        $args[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
        return [$items ?: [], $total];
    }


    public static function object_get(int $id): ?array
    {
        global $wpdb;
        $t = self::tables()['objects'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function object_upsert(?int $id, array $data): int
    {
        global $wpdb;
        $t = self::tables()['objects'];

        $clean = [
            'inv_id' => absint($data['inv_id'] ?? 0),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'media' => absint($data['media'] ?? 0),
            'id_svg' => (int) ($data['id_svg'] ?? 0),
            'status' => (int) ($data['status'] ?? 1),
        ];

        if (empty($id)) {
            $wpdb->insert($t, $clean, self::formats_for($clean));
            return (int) $wpdb->insert_id;
        }

        $wpdb->update($t, $clean, ['id' => $id], self::formats_for($clean), ['%d']);
        return (int) $id;
    }

    public static function object_soft_delete(int $id): void
    {
        global $wpdb;
        $t = self::tables()['objects'];
        $wpdb->update($t, ['status' => -1], ['id' => $id], ['%d'], ['%d']);
    }

    // ---------- Floors (sm_buildings) ----------
    public static function floors_list(int $per_page, int $paged, string $orderby, string $order, array $filters = []): array
    {
        global $wpdb;
        $t = self::tables()['floors'];

        $allowed_orderby = ['id', 'name', 'id_object', 'id_inv', 'floors_no', 'status'];
        $orderby = SM_INV_Fixed_Utils::sanitize_orderby($orderby, $allowed_orderby, 'id');
        $order = SM_INV_Fixed_Utils::sanitize_order_dir($order);
        $offset = max(0, ($paged - 1) * $per_page);

        $where = ['status <> %d'];
        $args = [-1];

        $id_inv = absint($filters['inv_id'] ?? $filters['id_inv'] ?? 0);
        if ($id_inv > 0) {
            $where[] = 'id_inv = %d';
            $args[] = $id_inv;
        }

        $id_object = absint($filters['id_object'] ?? 0);
        if ($id_object > 0) {
            $where[] = 'id_object = %d';
            $args[] = $id_object;
        }

        $status = isset($filters['status']) ? (int) $filters['status'] : null;
        if ($status !== null && $status !== -999) {
            $where[] = 'status = %d';
            $args[] = $status;
        }

        $search = trim((string) ($filters['s'] ?? $filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(name LIKE %s)';
            $args[] = $like;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT SQL_CALC_FOUND_ROWS *
                FROM {$t}
                {$where_sql}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        $args[] = $per_page;
        $args[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
        return [$items ?: [], $total];
    }


    public static function floor_get(int $id): ?array
    {
        global $wpdb;
        $t = self::tables()['floors'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function floor_upsert(?int $id, array $data): int
    {
        global $wpdb;
        $t = self::tables()['floors'];

        $id_object = absint($data['id_object'] ?? 0);
        $inv_id = 0;
        if ($id_object) {
            $obj = self::object_get($id_object);
            $inv_id = $obj ? (int) ($obj['inv_id'] ?? 0) : 0;
        }

        $clean = [
            'id_object' => $id_object,
            'id_inv' => $inv_id,
            'name' => sanitize_text_field($data['name'] ?? ''),
            'floors_no' => (int) ($data['floors_no'] ?? 0),
            'status' => (int) ($data['status'] ?? 1),
            'media' => absint($data['media'] ?? 0),
            'id_svg' => (int) ($data['id_svg'] ?? 0),
        ];

        if (empty($id)) {
            $wpdb->insert($t, $clean, self::formats_for($clean));
            return (int) $wpdb->insert_id;
        }

        $wpdb->update($t, $clean, ['id' => $id], self::formats_for($clean), ['%d']);
        return (int) $id;
    }

    public static function floor_soft_delete(int $id): void
    {
        global $wpdb;
        $t = self::tables()['floors'];
        $wpdb->update($t, ['status' => -1], ['id' => $id], ['%d'], ['%d']);
    }

    public static function standards_by_investment(int $inv_id): array
    {
        global $wpdb;
        $tS = self::tables()['standards'];
        $tR = self::tables()['investment_standards'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*
             FROM {$tS} s
             JOIN {$tR} r ON r.standard_id = s.id
             WHERE r.investment_id = %d
               AND s.status <> %d
             ORDER BY s.`order` ASC, s.id ASC",
                $inv_id,
                -1
            ),
            ARRAY_A
        ) ?: [];
    }

    public static function investment_standards_replace(int $inv_id, array $standard_ids): void
    {
        global $wpdb;
        $t = self::tables()['investment_standards'];

        // WYCZYŚĆ stare powiązania
        $wpdb->delete($t, ['investment_id' => $inv_id], ['%d']);

        foreach ($standard_ids as $sid) {
            $sid = absint($sid);
            if (!$sid) {
                continue;
            }

            $wpdb->insert(
                $t,
                [
                    'investment_id' => $inv_id,
                    'standard_id' => $sid,
                ],
                ['%d', '%d']
            );
        }
    }

    // ---------- Flats (sm_flats) ----------
    public static function flats_list(int $per_page, int $paged, string $orderby, string $order, array $filters = []): array
    {
        global $wpdb;
        $t = self::tables()['flats'];

        $allowed_orderby = ['id', 'code', 'id_bud', 'meters', 'rooms', 'price', 'total_price', 'status', 'type_id'];
        $orderby = SM_INV_Fixed_Utils::sanitize_orderby($orderby, $allowed_orderby, 'id');
        $order = SM_INV_Fixed_Utils::sanitize_order_dir($order);
        $offset = max(0, ($paged - 1) * $per_page);

        $where = ['status <> %d'];
        $args = [-1];

        $id_bud = absint($filters['id_bud'] ?? 0);
        if ($id_bud > 0) {
            $where[] = 'id_bud = %d';
            $args[] = $id_bud;
        }

        $type_id = absint($filters['type_id'] ?? 0);
        if ($type_id > 0) {
            $where[] = 'type_id = %d';
            $args[] = $type_id;
        }

        $status = isset($filters['status']) ? (int) $filters['status'] : null;
        if ($status !== null && $status !== -999) {
            $where[] = 'status = %d';
            $args[] = $status;
        }

        $search = trim((string) ($filters['s'] ?? $filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(code LIKE %s)';
            $args[] = $like;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT SQL_CALC_FOUND_ROWS *
                FROM {$t}
                {$where_sql}
                ORDER BY {$orderby} {$order}
                LIMIT %d OFFSET %d";

        $args[] = $per_page;
        $args[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A);
        $total = (int) $wpdb->get_var('SELECT FOUND_ROWS()');
        return [$items ?: [], $total];
    }


    public static function flat_get(int $id): ?array
    {
        global $wpdb;
        $t = self::tables()['flats'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function flat_upsert(?int $id, array $data): int
    {
        global $wpdb;
        $t = self::tables()['flats'];

        $meters = SM_INV_Fixed_Utils::sanitize_float($data['meters'] ?? 0);
        $price = SM_INV_Fixed_Utils::sanitize_float($data['price'] ?? 0);
        $total = $meters * $price;

        // IMPORTANT: do NOT overwrite dg_* columns (external sync / triggers etc.)
        $clean = [
            'code' => sanitize_text_field($data['code'] ?? ''),
            'id_bud' => absint($data['id_bud'] ?? 0),
            'id_svg' => sanitize_text_field($data['id_svg'] ?? ''),
            'meters' => (float) $meters,
            'rooms' => absint($data['rooms'] ?? 0),
            'price' => (float) $price,
            'total_price' => (float) $total,
            'status' => (int) ($data['status'] ?? 1),
            'flat' => absint($data['flat'] ?? 0),
            'media' => absint($data['media'] ?? 0),
            'type_id' => absint($data['type_id'] ?? 0),
        ];

        if (empty($id)) {
            $wpdb->insert($t, $clean, self::formats_for($clean));
            return (int) $wpdb->insert_id;
        }

        $wpdb->update($t, $clean, ['id' => $id], self::formats_for($clean), ['%d']);
        return (int) $id;
    }

    public static function flat_soft_delete(int $id): void
    {
        global $wpdb;
        $t = self::tables()['flats'];
        $wpdb->update($t, ['status' => -1], ['id' => $id], ['%d'], ['%d']);
    }

    // ---------- Room Types (sm_room_type: id, name, slug) ----------

    public static function room_types_all(): array
    {
        global $wpdb;
        $t = self::tables()['room_types'];

        // If legacy install has "status", exclude soft-deleted
        if (self::has_column($t, 'status')) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$t} WHERE status <> %d ORDER BY id ASC",
                -1
            ), ARRAY_A);
        }

        return $wpdb->get_results("SELECT * FROM {$t} ORDER BY id ASC", ARRAY_A);
    }
    public static function standards_list_all(): array
    {
        global $wpdb;
        $t = self::tables()['standards'];

        return $wpdb->get_results(
            "SELECT * FROM {$t} WHERE status <> -1 ORDER BY `order` ASC, id ASC",
            ARRAY_A
        ) ?: [];
    }
    public static function standards_ids_by_investment(int $inv_id): array
    {
        global $wpdb;
        $t = self::tables()['investment_standards'];

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT standard_id FROM {$t} WHERE investment_id = %d",
                $inv_id
            )
        );

        return array_map('intval', $ids ?: []);
    }

    public static function room_type_get(int $id): ?array
    {
        global $wpdb;
        $t = self::tables()['room_types'];
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $id), ARRAY_A);
        return $row ?: null;
    }

    public static function room_type_upsert(?int $id, array $data): int
    {
        global $wpdb;
        $t = self::tables()['room_types'];

        $name = sanitize_text_field($data['name'] ?? '');
        $slug = sanitize_text_field($data['slug'] ?? '');
        if ($slug === '')
            $slug = sanitize_title($name);

        $clean = [
            'name' => $name,
            'slug' => $slug,
        ];

        if (empty($id)) {
            $wpdb->insert($t, $clean, self::formats_for($clean));
            return (int) $wpdb->insert_id;
        }

        $wpdb->update($t, $clean, ['id' => $id], self::formats_for($clean), ['%d']);
        return (int) $id;
    }

    public static function room_type_delete(int $id): void
    {
        global $wpdb;
        $t = self::tables()['room_types'];
        $wpdb->delete($t, ['id' => $id], ['%d']);
    }

    // w SM_INV_Fixed_DB

    public static function objects_by_investment(int $inv_id): array
    {
        global $wpdb;
        $t = self::tables()['objects'];
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$t} WHERE inv_id=%d AND status <> %d ORDER BY id ASC", $inv_id, -1),
            ARRAY_A
        );
    }

    public static function floors_by_object(int $object_id): array
    {
        global $wpdb;
        $t = self::tables()['floors']; // sm_buildings
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$t} WHERE id_object=%d AND status <> %d ORDER BY floors_no DESC, id ASC", $object_id, -1),
            ARRAY_A
        );
    }


    // ---------- Lookups ----------

    public static function investments_for_select(): array
    {
        global $wpdb;
        $t = self::tables()['investments'];

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, title, address 
         FROM {$t} 
         WHERE status = %d 
         AND (external_url IS NULL OR external_url = '')
         ORDER BY `order` ASC, id ASC",
            1
        ), ARRAY_A);
    }

    public static function objects_for_select(): array
    {
        global $wpdb;
        $t = self::tables()['objects'];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, inv_id, name FROM {$t} WHERE status <> %d ORDER BY id ASC",
            -1
        ), ARRAY_A);
    }

    public static function floors_for_select(): array
    {
        global $wpdb;
        $t = self::tables()['floors'];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, id_object, id_inv, name, floors_no FROM {$t} WHERE status <> %d ORDER BY id ASC",
            -1
        ), ARRAY_A);
    }

    // ---------- Helpers ----------
    public static function floors_for_select_by_investment(int $inv_id): array
    {
        global $wpdb;

        $t = self::tables()['floors']; // sm_buildings

        if ($inv_id <= 0) {
            return self::floors_for_select();
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "
            SELECT id, id_object, id_inv, name, floors_no
            FROM {$t}
            WHERE id_inv = %d
              AND status <> %d
            ORDER BY floors_no ASC, id ASC
            ",
                $inv_id,
                -1
            ),
            ARRAY_A
        ) ?: [];
    }
    public static function investments_all(): array
    {
        global $wpdb;

        $t = self::tables()['investments'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title FROM {$t} WHERE status <> %d ORDER BY title ASC",
                -1
            ),
            ARRAY_A
        ) ?: [];
    }
    private static function formats_for(array $data): array
    {
        $formats = [];
        foreach ($data as $v) {
            if (is_int($v))
                $formats[] = '%d';
            elseif (is_float($v))
                $formats[] = '%f';
            else
                $formats[] = '%s';
        }
        return $formats;
    }

    public static function standards_all(): array
    {
        global $wpdb;
        $t = self::tables()['standards'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t} WHERE status <> %d ORDER BY `order` ASC, id ASC",
                -1
            ),
            ARRAY_A
        ) ?: [];
    }
    public static function standard_get(int $id): ?array
    {
        global $wpdb;
        $t = self::tables()['standards'];

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$t} WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        ) ?: null;
    }
    public static function standards_list(): array
    {
        global $wpdb;
        $t = self::tables()['standards'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t} WHERE status <> %d ORDER BY `order` ASC, id ASC",
                -1
            ),
            ARRAY_A
        ) ?: [];
    }
    public static function standard_upsert(?int $id, array $data): int
    {
        global $wpdb;
        $t = self::tables()['standards'];

        $clean = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'icon' => absint($data['icon'] ?? 0),
            'status' => (int) ($data['status'] ?? 1),
            'order' => absint($data['order'] ?? 0),
        ];

        if (empty($id)) {
            $wpdb->insert($t, $clean, self::formats_for($clean));
            return (int) $wpdb->insert_id;
        }

        $wpdb->update(
            $t,
            $clean,
            ['id' => $id],
            self::formats_for($clean),
            ['%d']
        );

        return (int) $id;
    }
    public static function standard_soft_delete(int $id): void
    {
        global $wpdb;
        $t = self::tables()['standards'];

        $wpdb->update(
            $t,
            ['status' => -1],
            ['id' => $id],
            ['%d'],
            ['%d']
        );
    }
    public static function investment_get_by_slug(string $slug): ?array
    {
        global $wpdb;
        $tables = self::tables();
        $t = $tables['investments'] ?? '';

        if (!$t) {
            return null;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$t} WHERE slug = %s LIMIT 1",
                $slug
            ),
            ARRAY_A
        );
    }

    public static function price_history(int $flat_id): array
    {
        global $wpdb;

        $t = self::tables()['price_history'];

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
             FROM {$t}
             WHERE flat_id = %d
             ORDER BY change_date DESC",
                $flat_id
            ),
            ARRAY_A
        ) ?: [];
    }

    // ---------- Count Flats Module ----------
    public static function count_available_flats_by_investment(int $inv_id): int
    {
        global $wpdb;

        $sql = "
        SELECT COUNT(f.id)
        FROM sm_flats f
        JOIN sm_buildings fl ON fl.id = f.id_bud
        JOIN sm_objects o ON o.id = fl.id_object
        WHERE o.inv_id = %d
        AND f.status = 1
    ";

        return (int) $wpdb->get_var($wpdb->prepare($sql, $inv_id));
    }
    // ---------- Search Module ----------
    public static function flats_search(array $filters = []): array
    {
        global $wpdb;

        $t_flats = self::tables()['flats'];
        $t_floors = self::tables()['floors'];  // sm_buildings
        $t_objects = self::tables()['objects']; // sm_objects
        $t_investments = self::tables()['investments']; // sm_investments

        $per_page = isset($filters['per_page']) ? max(1, (int) $filters['per_page']) : 9;
        $page = isset($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $offset = ($page - 1) * $per_page;

        // Tylko aktywne mieszkania (status = 1)
        $where = ['f.status = %d'];
        $args = [1];

        $sql = "
        FROM {$t_flats} f
        LEFT JOIN {$t_floors} b ON b.id = f.id_bud
        LEFT JOIN {$t_objects} o ON o.id = b.id_object
        LEFT JOIN {$t_investments} inv ON inv.id = o.inv_id
    ";

        // ========================
        // FILTRY
        // ========================

        // Inwestycja
        if (!empty($filters['investment'])) {
            $where[] = 'o.inv_id = %d';
            $args[] = (int) $filters['investment'];
        }

        // Piętro
        if (!empty($filters['floor'])) {
            $where[] = 'b.floors_no = %d';
            $args[] = (int) $filters['floor'];
        }

        // Pokoje
        if (!empty($filters['rooms'])) {
            if ((int) $filters['rooms'] === 4) {
                $where[] = 'f.rooms >= %d';
                $args[] = 4;
            } else {
                $where[] = 'f.rooms = %d';
                $args[] = (int) $filters['rooms'];
            }
        }

        // Cena
        if (!empty($filters['price_from'])) {
            $where[] = 'f.price >= %f';
            $args[] = (float) $filters['price_from'];
        }

        if (!empty($filters['price_to'])) {
            $where[] = 'f.price <= %f';
            $args[] = (float) $filters['price_to'];
        }

        // Metraż
        if (!empty($filters['meters_from'])) {
            $where[] = 'f.meters >= %f';
            $args[] = (float) $filters['meters_from'];
        }

        if (!empty($filters['meters_to'])) {
            $where[] = 'f.meters <= %f';
            $args[] = (float) $filters['meters_to'];
        }

        $where_sql = ' WHERE ' . implode(' AND ', $where);

        // ========================
        // SORTOWANIE
        // ========================

        $order_sql = ' ORDER BY f.id DESC';

        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'price_asc':
                    $order_sql = ' ORDER BY f.price ASC';
                    break;
                case 'price_desc':
                    $order_sql = ' ORDER BY f.price DESC';
                    break;
                case 'meters_asc':
                    $order_sql = ' ORDER BY f.meters ASC';
                    break;
                case 'meters_desc':
                    $order_sql = ' ORDER BY f.meters DESC';
                    break;
                case 'rooms_asc':
                    $order_sql = ' ORDER BY f.rooms ASC';
                    break;
                case 'rooms_desc':
                    $order_sql = ' ORDER BY f.rooms DESC';
                    break;
            }
        }

        // ========================
        // COUNT (bez LIMIT)
        // ========================

        $count_sql = "SELECT COUNT(*) " . $sql . $where_sql;

        $total = (int) $wpdb->get_var(
            $wpdb->prepare($count_sql, ...$args)
        );

        // ========================
        // DANE (z LIMIT)
        // ========================

        $data_sql = "
        SELECT 
            f.*, 
            b.floors_no AS floor_no, 
            o.inv_id,
            inv.title AS investment_title
        {$sql}
        {$where_sql}
        {$order_sql}
        LIMIT %d OFFSET %d
    ";

        $rows = $wpdb->get_results(
            $wpdb->prepare($data_sql, ...array_merge($args, [$per_page, $offset])),
            ARRAY_A
        ) ?: [];

        return [
            'rows' => $rows,
            'total' => $total,
            'per_page' => $per_page,
            'current_page' => $page,
            'pages' => (int) ceil($total / $per_page),
        ];
    }
}
