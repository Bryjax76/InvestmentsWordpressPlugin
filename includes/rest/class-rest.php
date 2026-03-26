<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SM_INV_Fixed_REST
{
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void
    {
        register_rest_route('siemaszko/v1', '/investments/(?P<id>\d+)/objects', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_objects'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('siemaszko/v1', '/objects/(?P<id>\d+)/floors', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_floors'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('siemaszko/v1', '/floors/(?P<id>\d+)/flats', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_flats'],
            'permission_callback' => '__return_true',
        ]);
    }

    /* =========================
     * CALLBACKS
     * ========================= */

    public static function get_objects(\WP_REST_Request $req): \WP_REST_Response
    {
        $inv_id = (int) $req['id'];
        if ($inv_id <= 0) {
            return new \WP_REST_Response(['error' => 'Invalid investment id'], 400);
        }

        $objects = SM_INV_Fixed_DB::objects_by_investment($inv_id);

        return new \WP_REST_Response(array_map(static function ($o) {
            return [
                'id'             => (int) $o['id'],
                'name'           => (string) $o['name'],
                'id_svg'         => (string) ($o['id_svg'] ?? ''),
                'media'          => (int) ($o['media'] ?? 0),
                'has_object_svg' => !empty($o['id_svg']),
            ];
        }, $objects));
    }

    public static function get_floors(\WP_REST_Request $req): \WP_REST_Response
    {
        $object_id = (int) $req['id'];
        if ($object_id <= 0) {
            return new \WP_REST_Response(['error' => 'Invalid object id'], 400);
        }

        $floors = SM_INV_Fixed_DB::floors_by_object($object_id);

        return new \WP_REST_Response(array_map(static function ($f) {
            return [
                'id'        => (int) $f['id'],
                'name'      => (string) $f['name'],
                'floor_no'  => (int) $f['floor_no'],
                'id_svg'    => (string) ($f['id_svg'] ?? ''),
            ];
        }, $floors));
    }

    public static function get_flats(\WP_REST_Request $req): \WP_REST_Response
    {
        $floor_id = (int) $req['id'];
        if ($floor_id <= 0) {
            return new \WP_REST_Response(['error' => 'Invalid floor id'], 400);
        }

        [$rows] = SM_INV_Fixed_DB::flats_list(
            2000,
            1,
            'id',
            'ASC',
            ['id_bud' => $floor_id]
        );

        $out = [];

        foreach ($rows as $f) {
            $key = trim((string) ($f['id_svg'] ?? ''));
            if ($key === '') continue;

            $out[] = [
                'id'     => (int) $f['id'],
                'id_svg' => $key, // np. "1", "11a"
                'status' => self::map_status((int) ($f['status'] ?? 1)),
            ];
        }

        return new \WP_REST_Response($out);
    }

    /* =========================
     * HELPERS
     * ========================= */

    private static function map_status(int $status): string
    {
        // DOSTOSUJ JEŚLI MASZ INNE WARTOŚCI W DB
        // 1 = dostępne, 2 = zarezerwowane, 3 = sprzedane
        return match ($status) {
            2 => 'reserved',
            3 => 'sold',
            default => 'available',
        };
    }
}
