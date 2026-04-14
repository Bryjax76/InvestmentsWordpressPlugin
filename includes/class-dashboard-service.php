<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_INV_Fixed_Dashboard_Service
{
    public static function get_summary(): array
    {
        global $wpdb;

        $tables = SM_INV_Fixed_DB::tables();

        if (
            empty($tables['investments']) ||
            empty($tables['objects']) ||
            empty($tables['floors']) ||
            empty($tables['flats'])
        ) {
            return self::empty_summary();
        }

        $t_inv = $tables['investments'];
        $t_obj = $tables['objects'];
        $t_floor = $tables['floors'];
        $t_flat = $tables['flats'];

        $active_investments = (int) $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$t_inv}
            WHERE status = 1
        ");

        $flat_rows = $wpdb->get_results("
            SELECT f.status, COUNT(*) AS cnt
            FROM {$t_flat} f
            INNER JOIN {$t_floor} fl ON fl.id = f.id_bud
            INNER JOIN {$t_obj} o ON o.id = fl.id_object
            INNER JOIN {$t_inv} i ON i.id = o.inv_id
            WHERE i.status = 1
            GROUP BY f.status
        ", ARRAY_A);

        $available = 0;
        $reserved = 0;
        $unavailable = 0;

        foreach ($flat_rows as $row) {
            $status = (int) ($row['status'] ?? -1);
            $count = (int) ($row['cnt'] ?? 0);

            if ($status === 1) {
                $available += $count;
            } elseif ($status === 2) {
                $reserved += $count;
            } elseif ($status === 0) {
                $unavailable += $count;
            }
        }

        $all_flats = $available + $reserved + $unavailable;
        $sales_percent = $all_flats > 0 ? round(($unavailable / $all_flats) * 100) : 0;

        return [
            'active_investments' => $active_investments,
            'all_flats' => $all_flats,
            'available_flats' => $available,
            'reserved_flats' => $reserved,
            'sold_flats' => $unavailable,
            'sales_percent' => $sales_percent,
        ];
    }

    public static function get_chart_data(): array
    {
        $summary = self::get_summary();

        return [
            'labels' => ['Dostępne', 'Zarezerwowane', 'Sprzedane / niedostępne'],
            'values' => [
                (int) $summary['available_flats'],
                (int) $summary['reserved_flats'],
                (int) $summary['sold_flats'],
            ],
        ];
    }

    public static function get_investments_rows(): array
    {
        global $wpdb;

        $tables = SM_INV_Fixed_DB::tables();

        if (
            empty($tables['investments']) ||
            empty($tables['objects']) ||
            empty($tables['floors']) ||
            empty($tables['flats'])
        ) {
            return [];
        }

        $t_inv = $tables['investments'];
        $t_obj = $tables['objects'];
        $t_floor = $tables['floors'];
        $t_flat = $tables['flats'];

        $rows = $wpdb->get_results("
            SELECT
                i.id,
                i.title,
                i.status,
                COUNT(f.id) AS all_flats,
                SUM(CASE WHEN f.status = 1 THEN 1 ELSE 0 END) AS available_flats,
                SUM(CASE WHEN f.status = 2 THEN 1 ELSE 0 END) AS reserved_flats,
                SUM(CASE WHEN f.status = 0 THEN 1 ELSE 0 END) AS sold_flats
            FROM {$t_inv} i
            LEFT JOIN {$t_obj} o
                ON o.inv_id = i.id
            LEFT JOIN {$t_floor} fl
                ON fl.id_object = o.id
            LEFT JOIN {$t_flat} f
                ON f.id_bud = fl.id
            WHERE i.status = 1
            GROUP BY i.id, i.title, i.status
            ORDER BY i.title ASC
        ", ARRAY_A);

        if (empty($rows)) {
            return [];
        }

        foreach ($rows as &$row) {
            $investment_id = (int) ($row['id'] ?? 0);
            $all = (int) ($row['all_flats'] ?? 0);
            $available = (int) ($row['available_flats'] ?? 0);
            $reserved = (int) ($row['reserved_flats'] ?? 0);
            $sold = (int) ($row['sold_flats'] ?? 0);

            $row['id'] = $investment_id;
            $row['title'] = (string) ($row['title'] ?? '');
            $row['all_flats'] = $all;
            $row['available_flats'] = $available;
            $row['reserved_flats'] = $reserved;
            $row['sold_flats'] = $sold;
            $row['sales_percent'] = $all > 0 ? round(($sold / $all) * 100) : 0;

            $row['actions'] = [
                'investment' => SM_INV_Fixed_Utils::admin_url_page(
                    SM_INV_Fixed_Admin::MENU_SLUG,
                    [
                        'action' => 'edit',
                        'id' => $investment_id,
                    ]
                ),
                'objects' => SM_INV_Fixed_Utils::admin_url_page(
                    SM_INV_Fixed_Admin::MENU_SLUG . '-objects',
                    [
                        'filter_inv_id' => $investment_id,
                    ]
                ),
                'floors' => SM_INV_Fixed_Utils::admin_url_page(
                    SM_INV_Fixed_Admin::MENU_SLUG . '-floors',
                    [
                        'filter_inv_id' => $investment_id,
                        'filter_object_id' => 0,
                    ]
                ),
                'flats' => SM_INV_Fixed_Utils::admin_url_page(
                    SM_INV_Fixed_Admin::MENU_SLUG . '-flats',
                    [
                        'filter_inv_id' => $investment_id,
                        'filter_floor_id' => 0,
                        'filter_type_id' => 0,
                    ]
                ),
            ];
        }
        unset($row);

        return $rows;
    }

    public static function get_validations(): array
    {
        return [];
    }

    private static function empty_summary(): array
    {
        return [
            'active_investments' => 0,
            'all_flats' => 0,
            'available_flats' => 0,
            'reserved_flats' => 0,
            'sold_flats' => 0,
            'sales_percent' => 0,
        ];
    }
}