<?php
if (!defined('ABSPATH'))
    exit;

final class SM_INV_Fixed_POI_Service
{
    const CACHE_TTL = 7 * DAY_IN_SECONDS; // 7 dni

    /* ===============================================================
       PUBLIC API
    =============================================================== */

    public static function get_poi_for_investment(int $investment_id): array
    {
        if ($investment_id <= 0) {
            return [];
        }

        return self::get_cached($investment_id);
    }

    /* ===============================================================
       CACHE
    =============================================================== */

    private static function get_cached(int $investment_id): array
    {
        global $wpdb;

        $tables = SM_INV_Fixed_DB::tables();
        if (empty($tables['poi'])) {
            return [];
        }

        $t = $tables['poi'];

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t}
                 WHERE investment_id = %d
                 AND fetched_at > %s",
                $investment_id,
                gmdate('Y-m-d H:i:s', time() - self::CACHE_TTL)
            ),
            ARRAY_A
        );

        return $rows ?: [];
    }

    private static function store(int $investment_id, array $pois): void
    {
        global $wpdb;

        $tables = SM_INV_Fixed_DB::tables();
        if (empty($tables['poi'])) {
            return;
        }

        $t = $tables['poi'];

        // usuń stare dane
        $wpdb->delete($t, ['investment_id' => $investment_id], ['%d']);

        foreach ($pois as $poi) {

            $wpdb->insert(
                $t,
                [
                    'investment_id' => $investment_id,
                    'category' => $poi['category'],
                    'name' => $poi['name'],
                    'lat' => $poi['lat'],
                    'lng' => $poi['lng'],
                    'osm_type' => $poi['osm_type'],
                    'osm_id' => $poi['osm_id'],
                    'meta_json' => wp_json_encode($poi['meta']),
                    'fetched_at' => current_time('mysql'),
                ],
                ['%d', '%s', '%s', '%f', '%f', '%s', '%d', '%s', '%s']
            );
        }
    }

    /* ===============================================================
       FETCH
    =============================================================== */
    public static function refresh_poi(int $investment_id): array
    {
        if ($investment_id <= 0) {
            return [];
        }

        $fresh = self::fetch_from_overpass($investment_id);

        if (!empty($fresh)) {
            self::store($investment_id, $fresh);
        }

        return $fresh;
    }

    private static function fetch_from_overpass(int $investment_id): array
    {
        $inv = SM_INV_Fixed_DB::investment_get($investment_id);

        if (!$inv || empty($inv['latitude']) || empty($inv['longitude'])) {
            error_log('POI: brak lat/lng dla inwestycji ' . $investment_id);
            return [];
        }

        $lat = (float) $inv['latitude'];
        $lng = (float) $inv['longitude'];
        $radius = 600;

        $query = self::build_overpass_query($lat, $lng, $radius);

        $endpoints = [
            'https://overpass.private.coffee/api/interpreter',
            'https://overpass.kumi.systems/api/interpreter',
            'https://overpass-api.de/api/interpreter',
        ];

        foreach ($endpoints as $endpoint) {

            error_log("POI TRY: " . $endpoint);

            $response = wp_remote_post(
                $endpoint,
                [
                    'timeout' => 40,
                    'headers' => [
                        'User-Agent' => 'Siemaszko-Investments/1.0',
                        'Content-Type' => 'text/plain'
                    ],
                    'body' => $query,
                ]
            );

            if (is_wp_error($response)) {
                error_log('POI ERROR: ' . $response->get_error_message());
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);

            if ($status !== 200) {
                error_log('POI HTTP ERROR: ' . $status);
                continue;
            }

            $body = wp_remote_retrieve_body($response);

            if (!$body) {
                error_log('POI EMPTY BODY');
                continue;
            }

            $json = json_decode($body, true);

            if (!empty($json['elements'])) {
                return self::parse_osm($json['elements']);
            }
        }

        error_log('POI: wszystkie endpointy zawiodły');
        return [];
    }

    /* ===============================================================
       OVERPASS QUERY
    =============================================================== */

    private static function build_overpass_query(float $lat, float $lng, int $radius): string
    {
        return "
            [out:json][timeout:25];
            (
            node(around:$radius,$lat,$lng)[\"amenity\"~\"restaurant|cafe|bar|school|kindergarten|university|hospital|clinic|pharmacy\"];
            node(around:$radius,$lat,$lng)[shop];
            node(around:$radius,$lat,$lng)[leisure];
            node(around:$radius,$lat,$lng)[highway=bus_stop];
            node(around:$radius,$lat,$lng)[railway=tram_stop];
            node(around:$radius,$lat,$lng)[railway=station];
            );
            out center;
            ";
    }

    /* ===============================================================
       PARSER
    =============================================================== */

    private static function parse_osm(array $elements): array
    {
        $pois = [];

        foreach ($elements as $el) {

            // node
            if (!empty($el['lat']) && !empty($el['lon'])) {
                $lat = $el['lat'];
                $lng = $el['lon'];
            }
            // way
            elseif (!empty($el['center']['lat']) && !empty($el['center']['lon'])) {
                $lat = $el['center']['lat'];
                $lng = $el['center']['lon'];
            } else {
                continue;
            }

            $category = self::detect_category($el['tags'] ?? []);
            if (!$category)
                continue;

            $pois[] = [
                'category' => $category,
                'name' => $el['tags']['name'] ?? ucfirst($category),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'osm_type' => $el['type'],
                'osm_id' => (int) $el['id'],
                'meta' => $el['tags'] ?? [],
            ];
        }

        return $pois;
    }

    /* ===============================================================
       CATEGORY MAPPING
    =============================================================== */

    private static function detect_category(array $tags): ?string
    {
        // KOMUNIKACJA
        if (!empty($tags['highway']) && $tags['highway'] === 'bus_stop') {
            return 'komunikacja';
        }

        if (!empty($tags['railway']) && in_array($tags['railway'], ['tram_stop', 'station'])) {
            return 'komunikacja';
        }

        // AMENITY
        if (!empty($tags['amenity'])) {

            if (in_array($tags['amenity'], ['restaurant', 'cafe', 'bar'])) {
                return 'restauracje';
            }

            if (in_array($tags['amenity'], ['school', 'kindergarten', 'university'])) {
                return 'oswiata';
            }

            if (in_array($tags['amenity'], ['hospital', 'clinic', 'pharmacy'])) {
                return 'zdrowie';
            }
        }

        // SKLEPY
        if (!empty($tags['shop'])) {
            return 'sklepy';
        }

        // SPORT
        if (!empty($tags['leisure'])) {
            return 'sport';
        }

        return null;
    }
}
