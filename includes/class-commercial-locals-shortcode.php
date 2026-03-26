<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SM_INV_Commercial_Locals_Shortcode
{
    public static function init(): void
    {
        add_shortcode('sm_commercial_locals', [self::class, 'render']);
    }

    public static function render($atts): string
    {
        global $wpdb;

        $flats_table       = 'sm_flats';
        $buildings_table   = 'sm_buildings';
        $investments_table = 'sm_investments';

        $sql = "
            SELECT f.*,
                   b.floors_no AS floor_no,
                   inv.title   AS investment_title
            FROM {$flats_table} f
            LEFT JOIN {$buildings_table} b ON f.id_bud = b.id
            LEFT JOIN {$investments_table} inv ON b.id_inv = inv.id
            WHERE f.type_id = 2
              AND f.status = 1
            ORDER BY f.price ASC
        ";

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return self::render_grid($rows);
    }

    // ============================
    // GRID (identyczny jak wyszukiwarka, ale niezależny)
    // ============================
    private static function render_grid(array $rows): string
    {
        if (empty($rows)) {
            return '<div class="sm-search-empty">Brak lokali usługowych.</div>';
        }

        ob_start();
        ?>
        <div class="sm-search-grid sm-commercial-grid">
            <?php foreach ($rows as $flat): ?>
                <?php
                $flat_id  = (int) ($flat['id'] ?? 0);
                $code     = (string) ($flat['code'] ?? '');
                $meters   = (float) ($flat['meters'] ?? 0);
                $rooms    = (int) ($flat['rooms'] ?? 0);
                $price    = (float) ($flat['price'] ?? 0);
                $floor_no = isset($flat['floor_no']) ? (int) $flat['floor_no'] : null;
                $inv_title = (string) ($flat['investment_title'] ?? '');
                $plan_id  = (int) ($flat['media'] ?? 0);

                $slug = sanitize_title($code);
                $details_url = home_url("/mieszkanie/{$slug}/{$flat_id}/");
                ?>
                <article class="sm-flat-card sm-commercial-card">
                    <header class="sm-flat-card__header">
                        <div class="sm-flat-card__code">
                            <?= esc_html($code); ?>
                        </div>

                        <?php if ($inv_title !== ''): ?>
                            <div class="sm-flat-card__inv">
                                <?= esc_html($inv_title); ?>
                            </div>
                        <?php endif; ?>
                    </header>

                    <div class="sm-flat-card__plan">
                        <?php if ($plan_id > 0): ?>
                            <?php
                            echo wp_get_attachment_image($plan_id, 'large', false, [
                                'class'   => 'sm-flat-card__plan-img',
                                'loading' => 'lazy',
                            ]);
                            ?>
                        <?php else: ?>
                            <div class="sm-flat-card__plan-missing">Brak rzutu</div>
                        <?php endif; ?>
                    </div>

                    <div class="sm-flat-card__meta">

                        <div class="sm-flat-card__meta-row">
                            <span class="label">Powierzchnia</span>
                            <span class="value">
                                <?= esc_html(number_format($meters, 2, '.', ' ')); ?> m²
                            </span>
                        </div>

                        <div class="sm-flat-card__meta-row">
                            <span class="label">Ilość pomieszczeń</span>
                            <span class="value">
                                <?= esc_html($rooms); ?>
                            </span>
                        </div>

                        <div class="sm-flat-card__meta-row">
                            <span class="label">Piętro</span>
                            <span class="sm-flat-floor-badge">
                                <?= ($floor_no !== null ? esc_html($floor_no) : '—'); ?>
                            </span>
                        </div>

                        <div class="sm-flat-card__meta-row">
                            <span class="label">Cena</span>
                            <span class="value">
                                <?= esc_html(number_format($price, 0, ',', ' ')); ?> zł/m<sup>2</sup>
                            </span>
                        </div>

                        <div class="sm-flat-card__meta-row sm-flat-card__footer">
                            <a class="sm-flat-card__btn" href="<?= esc_url($details_url); ?>">
                                Sprawdź szczegóły
                            </a>
                        </div>

                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }
}