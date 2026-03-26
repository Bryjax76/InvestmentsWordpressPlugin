<?php
final class SM_INV_Fixed_Search
{

    public static function init()
    {
        add_shortcode('sm_flats_search', [self::class, 'render']);

        // 🔥 FIX NA PAGE W URL
        add_filter('redirect_canonical', function ($redirect_url) {

            if (is_page('znajdz-mieszkanie') && isset($_GET['page'])) {
                return false;
            }

            return $redirect_url;
        });
    }

    public static function render()
    {

        // Enqueue JS
        wp_enqueue_script(
            'sm-search',
            SM_INV_FIXED_URL . 'assets/search.js',
            ['jquery'],
            time(),
            true
        );

        wp_localize_script('sm-search', 'sm_search', [
            'ajax_url' => admin_url('admin-ajax.php')
        ]);

        $rows = [];
        ob_start();
        ?>

        <section class="sec-block sec1 fixed-width">

            <div class="heading-block">
                <h2>
                    <?php the_title(); ?>
                </h2>
            </div>

            <div class="sm-search-filters">
                <form class="sm-search-form" method="post">

                    <?php wp_nonce_field('sm_flats_search_nonce', 'sm_flats_search_nonce_field'); ?>
                    <input type="hidden" name="action" value="sm_flats_search">

                    <!-- INWESTYCJA -->
                    <div class="sm-filter-item">
                        <span class="sm-filter-label">INWESTYCJA</span>
                        <div class="sm-filter-control">
                            <span class="sm-filter-icon">
                                <?php echo file_get_contents(SM_INV_FIXED_PATH . 'assets/svg/apartment.svg'); ?>
                            </span>

                            <select name="investment">
                                <option value="">Wybierz</option>
                                <?php foreach (SM_INV_Fixed_DB::investments_for_select() as $inv): ?>
                                    <option value="<?= esc_attr($inv['id']); ?>">
                                        <?= esc_html($inv['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- PIĘTRO -->
                    <div class="sm-filter-item">
                        <span class="sm-filter-label">PIĘTRO</span>
                        <div class="sm-filter-control">
                            <span class="sm-filter-icon">
                                <?php echo file_get_contents(SM_INV_FIXED_PATH . 'assets/svg/door-closed.svg'); ?>
                            </span>

                            <select name="floor">
                                <option value="">Wszystkie</option>
                                <option value="0">0</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                                <option value="6">6</option>
                                <option value="7">7</option>
                                <option value="8">8</option>
                                <option value="9">9</option>
                            </select>
                        </div>
                    </div>

                    <!-- ILOŚĆ POKOI -->
                    <div class="sm-filter-item">
                        <span class="sm-filter-label">ILOŚĆ POKOI</span>
                        <div class="sm-filter-control">
                            <span class="sm-filter-icon">
                                <?php echo file_get_contents(SM_INV_FIXED_PATH . 'assets/svg/circle-sort.svg'); ?>
                            </span>

                            <select name="rooms">
                                <option value="">Wszystkie</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4+</option>
                            </select>
                        </div>
                    </div>

                    <!-- POWIERZCHNIA -->
                    <div class="sm-filter-item">
                        <span class="sm-filter-label">POWIERZCHNIA</span>
                        <div class="sm-filter-control range">
                            <span class="sm-filter-icon">
                                <?php echo file_get_contents(SM_INV_FIXED_PATH . 'assets/svg/arrows-maximize.svg'); ?>
                            </span>

                            <input type="number" name="meters_from" placeholder="Od">
                            <span class="range-separator">–</span>
                            <input type="number" name="meters_to" placeholder="Do">
                            <span class="range-unit">m²</span>
                        </div>
                    </div>

                    <!-- CENA -->
                    <div class="sm-filter-item">
                        <span class="sm-filter-label">CENA</span>
                        <div class="sm-filter-control range">
                            <span class="sm-filter-icon">
                                <?php echo file_get_contents(SM_INV_FIXED_PATH . 'assets/svg/circle-dollar.svg'); ?>
                            </span>

                            <input type="number" name="price_from" placeholder="Od">
                            <span class="range-separator">–</span>
                            <input type="number" name="price_to" placeholder="Do">
                            <span class="range-unit">zł/m²</span>
                        </div>
                    </div>

                    <!-- SORTOWANIE -->
                    <div class="sm-filter-item">
                        <span class="sm-filter-label">SORTOWANIE</span>
                        <div class="sm-filter-control">
                            <span class="sm-filter-icon">
                                <?php echo file_get_contents(SM_INV_FIXED_PATH . 'assets/svg/bars-filter.svg'); ?>
                            </span>

                            <select name="sort">
                                <option value="">Sortuj według</option>
                                <option value="price_asc">Cena ↑</option>
                                <option value="price_desc">Cena ↓</option>
                                <option value="meters_asc">Metraż ↑</option>
                                <option value="meters_desc">Metraż ↓</option>
                            </select>
                        </div>
                    </div>

                    <!-- RESET -->
                    <div class="sm-filter-reset">
                        <button type="reset" class="sm-clear-filters">
                            ✕ Wyczyść filtry
                        </button>
                    </div>

                </form>
            </div>

            <div class="sm-search-results">
                <?php echo SM_INV_Fixed_Search_Ajax::render_results_html($rows); ?>
            </div>

        </section>

        <?php
        return ob_get_clean();
    }
}