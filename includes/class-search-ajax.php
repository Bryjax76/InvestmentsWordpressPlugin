<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SM_INV_Fixed_Search_Ajax
{
    public static function init(): void
    {
        // endpointy są spięte w głównym pliku
    }

    public static function handle(): void
    {
        // 1️⃣ Nonce
        $nonce = $_POST['sm_flats_search_nonce_field'] ?? '';
        if (!wp_verify_nonce($nonce, 'sm_flats_search_nonce')) {
            wp_send_json_error(['message' => 'Nieprawidłowy nonce.'], 403);
        }

        // 2️⃣ Numer strony
        $page = isset($_POST['pg']) ? max(1, (int) $_POST['pg']) : 1;

        // 3️⃣ Filtry
        $filters = [
            'investment' => isset($_POST['investment']) ? absint($_POST['investment']) : 0,
            'floor' => isset($_POST['floor']) ? (int) $_POST['floor'] : 0,
            'rooms' => isset($_POST['rooms']) ? absint($_POST['rooms']) : 0,
            'price_from' => isset($_POST['price_from']) ? (float) $_POST['price_from'] : 0,
            'price_to' => isset($_POST['price_to']) ? (float) $_POST['price_to'] : 0,
            'meters_from' => isset($_POST['meters_from']) ? (float) $_POST['meters_from'] : 0,
            'meters_to' => isset($_POST['meters_to']) ? (float) $_POST['meters_to'] : 0,
            'sort' => isset($_POST['sort']) ? sanitize_text_field($_POST['sort']) : '',
            'page' => $page,
            'per_page' => 9,
        ];

        // 4️⃣ DB
        $result = SM_INV_Fixed_DB::flats_search($filters);

        $rows = $result['rows'] ?? [];
        $total = $result['total'] ?? 0;
        $pages = $result['pages'] ?? 1;
        $current_page = $result['current_page'] ?? 1;

        // 5️⃣ Render HTML
        $html = self::render_results_html($rows);
        $pagination = self::render_pagination($pages, $current_page);

        wp_send_json_success([
            'html' => $html,
            'pagination' => $pagination,
            'total' => $total,
        ]);
    }

    // ============================
    // GRID WYNIKÓW
    // ============================
    public static function render_results_html(array $rows): string
    {
        if (empty($rows)) {
            return '<div class="sm-search-empty">Brak wyników.</div>';
        }

        ob_start();
        ?>
        <div class="sm-search-grid">
            <?php foreach ($rows as $flat): ?>
                <?php
                $flat_id = (int) ($flat['id'] ?? 0);
                $code = (string) ($flat['code'] ?? '');
                $meters = (float) ($flat['meters'] ?? 0);
                $rooms = (int) ($flat['rooms'] ?? 0);
                $price = (float) ($flat['price'] ?? 0);

                // z JOIN (dodaj w SQL: b.floors_no AS floor_no)
                $floor_no = isset($flat['floor_no']) ? (int) $flat['floor_no'] : null;

                // z JOIN (dodaj w SQL: inv.title AS investment_title)
                $inv_title = (string) ($flat['investment_title'] ?? '');

                // SVG rzut (attachment ID w "media")
                $plan_id = (int) ($flat['media'] ?? 0);

                // Link do szczegółów (na razie placeholder, później podmienimy na docelowy permalink)
                $slug = sanitize_title($code);
                $details_url = home_url("/mieszkanie/{$slug}/{$flat_id}/");
                ?>
                <article class="sm-flat-card">
                    <header class="sm-flat-card__header">
                        <div class="sm-flat-card__code">
                            <?= esc_html('Mieszkanie nr ' . $code); ?>
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
                            // UWAGA: jeśli to SVG jako attachment, wp_get_attachment_image powinno zwrócić <img>.
                            echo wp_get_attachment_image($plan_id, 'large', false, [
                                'class' => 'sm-flat-card__plan-img',
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
                            <span class="value"><?= esc_html(number_format($meters, 2, '.', ' ')); ?> m²</span>
                        </div>

                        <div class="sm-flat-card__meta-row">
                            <span class="label">Ilość pomieszczeń</span>
                            <span class="value"><?= esc_html($rooms); ?></span>
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

    // ============================
    // PAGINACJA
    // ============================
    private static function render_pagination(int $pages, int $current_page): string
    {
        if ($pages <= 1) {
            return '';
        }

        $range = 1; // ile stron obok aktualnej

        ob_start();
        ?>
        <div class="sm-search-pagination">

            <?php if ($current_page > 1): ?>
                <button class="sm-page-btn sm-page-prev" data-page="<?= $current_page - 1; ?>">‹</button>
            <?php endif; ?>

            <?php
            for ($i = 1; $i <= $pages; $i++) {

                if (
                    $i == 1 ||
                    $i == $pages ||
                    ($i >= $current_page - $range && $i <= $current_page + $range)
                ) {

                    ?>
                    <button class="sm-page-btn <?= $i === $current_page ? 'is-active' : ''; ?>" data-page="<?= $i; ?>">
                        <?= $i; ?>
                    </button>
                    <?php
                } elseif (
                    $i == 2 && $current_page > 3 ||
                    $i == $pages - 1 && $current_page < $pages - 2
                ) {
                    ?>
                    <span class="sm-page-dots">...</span>
                    <?php
                }
            }
            ?>

            <?php if ($current_page < $pages): ?>
                <button class="sm-page-btn sm-page-next" data-page="<?= $current_page + 1; ?>">›</button>
            <?php endif; ?>

        </div>
        <?php

        return (string) ob_get_clean();
    }
}