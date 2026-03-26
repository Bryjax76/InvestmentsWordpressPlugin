<?php
if (!defined('ABSPATH'))
    exit;

/** @var array $inv */
$inv = $inv ?? [];

$title = (string) ($inv['title'] ?? '');
$content_title = (string) ($inv['content_title'] ?? '');
$excerpt = (string) ($inv['excerpt'] ?? '');
$content = (string) ($inv['content'] ?? '');
$image = (int) ($inv['image'] ?? 0);
$thumb2 = (int) ($inv['thumb2'] ?? 0);
$svg_id = (int) ($inv['media'] ?? 0);
$address = (string) ($inv['address'] ?? '');
$completion_date = (string) ($inv['completion_date'] ?? 'Q3 2027');
$available_flats = (string) ($inv['available_flats'] ?? '57');
$document = (string) ($inv['document'] ?? 'Prospekt');
$google_map = (string) ($inv['google_map'] ?? '');
$gallery_csv = (string) ($inv['gallery'] ?? '');

$svg_base = plugins_url('assets/svg/', SM_INV_FIXED_FILE);

$image_data = $image ? wp_get_attachment_image_src($image, 'full') : false;
$thumb2_data = $thumb2 ? wp_get_attachment_image_src($thumb2, 'full') : false;
$thumb2_data = $thumb2 ? wp_get_attachment_image_src($thumb2, 'full') : false;

$GLOBALS['sm_current_investment_name'] = $title;

// echo '<pre>';
// var_dump($inv);
// echo '</pre>';

/**
 * Pobierz SVG inline (kod SVG) z załącznika WP
 */
function sm_get_inline_svg(int $attachment_id): string
{
    if (!$attachment_id)
        return '';

    // Walidacja MIME
    $mime = get_post_mime_type($attachment_id);
    if ($mime && $mime !== 'image/svg+xml') {
        return '';
    }

    $svg_path = get_attached_file($attachment_id);
    if (!$svg_path || !file_exists($svg_path)) {
        return '';
    }

    $svg = file_get_contents($svg_path);
    if (!$svg)
        return '';

    // Usuń XML declaration i DOCTYPE (często psuje inline)
    $svg = preg_replace('/<\?xml.*?\?>\s*/i', '', $svg);
    $svg = preg_replace('/<!DOCTYPE.*?>\s*/is', '', $svg);

    // Usuń <script> (bezpiecznik)
    $svg = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $svg);

    // Usuń event handlery typu onclick="..."
    $svg = preg_replace('/on\w+\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/i', '', $svg);

    // Dodaj atrybuty dostępności (tylko jeśli ich nie ma)
    if (stripos($svg, '<svg') !== false && stripos($svg, 'role=') === false) {
        $svg = preg_replace('/<svg\b/i', '<svg role="img" aria-hidden="true"', $svg, 1);
    }

    return $svg;
}

$inline_svg = sm_get_inline_svg($svg_id);

/**
 * Walidacja URL embeda google maps (trzymasz tylko URL, a nie iframe)
 */
$google_map_src = trim($google_map);

$host = $google_map_src ? wp_parse_url($google_map_src, PHP_URL_HOST) : '';
$allowed_hosts = ['www.google.com', 'google.com'];

$is_valid_map =
    $google_map_src
    && filter_var($google_map_src, FILTER_VALIDATE_URL)
    && in_array($host, $allowed_hosts, true)
    && (strpos($google_map_src, 'https://www.google.com/maps/embed?') === 0);

$inv_id = (int) ($inv['id'] ?? 0);
$additional_products = ($inv_id && class_exists('SM_INV_Fixed_DB'))
    ? SM_INV_Fixed_DB::additional_products_by_investment($inv_id)
    : [];

// Gallery CSV -> list of attachment IDs
$gallery_ids = [];
if (!empty($gallery_csv)) {
    preg_match_all('/\d+/', $gallery_csv, $m);
    $gallery_ids = array_map('intval', $m[0] ?? []);
}

get_header();
?>
<main class="sm-invest-single">

    <section class="sec-block sec1 banner-section" style="padding-bottom: 0; position: relative;">
        <?php if (!empty($image_data) && !empty($image_data[0])): ?>
            <div class="image-banner-background">
                <img src="<?php echo esc_url($image_data[0]); ?>" alt="<?php echo esc_attr($title); ?>">
            </div>
        <?php endif; ?>

        <div class="banner-content">
            <?php if (!empty($content_title)): ?>
                <span><?php echo esc_html($content_title); ?></span>
            <?php endif; ?>
            <h1><?php echo esc_html($title); ?></h1>
        </div>
    </section>

    <section class="sec-block sec2 fixed-width animate__animated" data-animation="fadeIn" style="padding-top: 0;"
        data-hotlink="#standard_inwestycji" data-hotlink-name="Standard inwestycji">
        <div class="breadcrumb-wrapper">
            <a href="<?php echo esc_url(home_url('/')); ?>">Strona główna</a>
            <span>/</span>
            <span><?php echo esc_html($title); ?></span>
        </div>

        <div class="blocks-wrapper">
            <div class="block">
                <span>Lokalizacja</span>
                <h4><?php echo esc_html($address); ?></h4>
                <button type="button" onclick="document.getElementById('map').scrollIntoView({behavior:'smooth'});">
                    <span>Zobacz na mapie</span>
                </button>
            </div>

            <div class="block">
                <span>Planowany termin zakończenia</span>
                <h4><?php echo esc_html($completion_date); ?></h4>
            </div>

            <div class="block">
                <span>Dostępne mieszkania</span>
                <h4><?php echo esc_html($available_flats); ?></h4>
                <button type="button">
                    <span>Zapytaj o mieszkanie</span>
                </button>
            </div>

            <div class="block">
                <span>Dokumenty</span>
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" width="19.5" height="26" viewBox="0 0 19.5 26">
                        <path id="file"
                            d="M18.55,6.363,13.142.955A3.252,3.252,0,0,0,10.842,0H3.25A3.25,3.25,0,0,0,0,3.25v19.5A3.25,3.25,0,0,0,3.25,26h13a3.25,3.25,0,0,0,3.25-3.25V8.658A3.239,3.239,0,0,0,18.55,6.363ZM17.4,7.51a1.5,1.5,0,0,1,.371.614H12.188a.815.815,0,0,1-.812-.812V1.731a1.6,1.6,0,0,1,.615.371Zm.477,15.239a1.627,1.627,0,0,1-1.625,1.625h-13A1.627,1.627,0,0,1,1.625,22.75V3.25A1.627,1.627,0,0,1,3.25,1.625h6.5V7.312A2.438,2.438,0,0,0,12.188,9.75h5.688Z"
                            transform="translate(0 0)" />
                    </svg>

                    <h4><?php echo esc_html($document); ?></h4>
                </div>
            </div>
        </div>
    </section>

    <?php if (do_shortcode('[sm_inv_standards]') != ""): ?>
        <section class="sec-block fixed-width animate__animated" data-animation="fadeIn">
            <div class="section-content">
                <div class="heading-block">
                    <h2 class="sm-inv-title">Standard inwestycji</h2>
                </div>
                <?= do_shortcode('[sm_inv_standards]'); ?>
            </div>
        </section>
    <?php endif; ?>


    <section class="sec-block sec3 fixed-width animate__animated" data-animation="fadeIn"
        style="min-height: 80vh; padding-top: 0;" data-hotlink="#wybierz_mieszkanie"
        data-hotlink-name="Wybierz mieszkanie">
        <div class="section-content" id="sm-inv-root">
            <div class="heading-block">
                <h2 class="sm-inv-title">Wybierz budynek</h2>

                <div class="legend-map" style="opacity: 0; height: 1px;">
                    <div class="left-col floor-nav" id="floor-nav">
                        <div class="subheading">
                            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
                                <path id="circle-sort"
                                    d="M17.052,14.625h-8.1a1.225,1.225,0,0,0-.871,2.088l4.067,4.063a1.253,1.253,0,0,0,.856.349,1.266,1.266,0,0,0,.869-.357l4.054-4.056a1.224,1.224,0,0,0-.871-2.087Zm.3,1.513L13.3,20.188a.435.435,0,0,1-.59.006L8.648,16.138a.4.4,0,0,1-.081-.447.393.393,0,0,1,.381-.254h8.11a.4.4,0,0,1,.381.254A.412.412,0,0,1,17.352,16.138ZM13.863,5.225a1.265,1.265,0,0,0-1.727.006L8.074,9.288a1.225,1.225,0,0,0,.873,2.087h8.11a1.225,1.225,0,0,0,.87-2.087Zm3.57,5.083a.393.393,0,0,1-.381.254h-8.1a.412.412,0,0,1-.3-.7l4.054-4.05A.42.42,0,0,1,13,5.688a.439.439,0,0,1,.3.119L17.35,9.863A.393.393,0,0,1,17.433,10.309ZM13,0A13,13,0,1,0,26,13,13,13,0,0,0,13,0Zm0,25.188A12.188,12.188,0,1,1,25.188,13,12.2,12.2,0,0,1,13,25.188Z"
                                    fill="#2f2d7b" />
                            </svg>
                            <span>WYBIERZ PIĘTRO</span>
                        </div>
                        <div class="floor-nav-wrapper" id="floor-nav-wrapper">
                            <!-- RENDER FLOORS HERE -->
                        </div>
                    </div>
                    <div class="right-col status-nav">
                        <div class="subheading">
                            <span>STATUS MIESZKANIA</span>
                        </div>
                        <div class="status-wrapper">
                            <div class="status">
                                <div class="color available"></div>
                                <span>Dostępne</span>
                            </div>
                            <div class="status">
                                <div class="color reserved"></div>
                                <span>Zarezerwowane</span>
                            </div>
                            <div class="status">
                                <div class="color sold"></div>
                                <span>Sprzedane</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content svg-content-wrapper">
                    <div class="sm-inv-nav" id="sm-inv-nav" data-inv-id="<?php echo (int) $inv_id; ?>"
                        data-step="investment">
                        <!-- <div class="sm-inv-breadcrumb" id="sm-inv-breadcrumb"></div> -->
                        <button class="sm-inv-back" id="sm-inv-back" type="button" style="display:none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"
                                viewBox="0 0 24 24"><!-- Icon from Tabler Icons by Paweł Kuna - https://github.com/tabler/tabler-icons/blob/master/LICENSE -->
                                <path fill="#2f2d7b"
                                    d="M12 2a10 10 0 0 1 .324 19.995L12 22l-.324-.005A10 10 0 0 1 12 2m.707 5.293a1 1 0 0 0-1.414 0l-4 4a1 1 0 0 0-.083.094l-.064.092l-.052.098l-.044.11l-.03.112l-.017.126L7 12l.004.09l.007.058l.025.118l.035.105l.054.113l.043.07l.071.095l.054.058l4 4l.094.083a1 1 0 0 0 1.32-1.497L10.415 13H16l.117-.007A1 1 0 0 0 16 11h-5.586l2.293-2.293l.083-.094a1 1 0 0 0-.083-1.32" />
                            </svg>
                        </button>

                        <?php if (!empty($inline_svg)): ?>
                            <div class="svg_container" id="sm-inv-svg">
                                <?php echo $inline_svg; ?>
                            </div>
                        <?php else: ?>
                            <p>Plan budynków niedostępny</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="sec-block sec4 animate__animated fixed-width" data-animation="fadeIn" id="lokalizacja"
        data-hotlink="#lokalizacja" data-hotlink-name="Lokalizacja">
        <div class="section-content">
            <div class="heading-block fixed-width">
                <h2>Lokalizacja</h2>
                <div class="content">
                    <p class="text-center"><?= $inv['address']; ?></p>
                </div>
            </div>
            <?php
            $pois = SM_INV_Fixed_POI_Service::get_poi_for_investment($inv['id']);
            $lat = (float) $inv['latitude'];
            $lng = (float) $inv['longitude'];
            ?>

            <div id="map">

                <div class="filters-panel">
                    <div class="text-wrapper">
                        <p>FILTRY</p>
                    </div>
                    <div class="filter-group">
                        <label class="filter-item filter-all">
                            <input type="checkbox" id="wszystkie" checked>
                            <span>Wszystkie</span>
                        </label>

                        <label class="filter-item">
                            <input type="checkbox" id="komunikacja" checked>
                            <div class="filter-icon-wrapper">
                                <img class="filter-icon" src="<?= esc_url($svg_base . 'bus-simple.svg'); ?>" alt="">
                            </div>
                            <span>Komunikacja</span>
                        </label>

                        <label class="filter-item">
                            <input type="checkbox" id="restauracje" checked>
                            <div class="filter-icon-wrapper">
                                <img class="filter-icon" src="<?= esc_url($svg_base . 'fork-knife.svg'); ?>" alt="">
                            </div>
                            <span>Restauracje</span>
                        </label>

                        <label class="filter-item">
                            <input type="checkbox" id="zdrowie" checked>
                            <div class="filter-icon-wrapper">
                                <img class="filter-icon" src="<?= esc_url($svg_base . 'heart-pulse.svg'); ?>" alt="">
                            </div>
                            <span>Zdrowie</span>
                        </label>

                        <label class="filter-item">
                            <input type="checkbox" id="oswiata" checked>
                            <div class="filter-icon-wrapper">
                                <img class="filter-icon" src="<?= esc_url($svg_base . 'school.svg'); ?>" alt="">
                            </div>
                            <span>Oświata</span>
                        </label>

                        <label class="filter-item">
                            <input type="checkbox" id="sklepy" checked>
                            <div class="filter-icon-wrapper">
                                <img class="filter-icon" src="<?= esc_url($svg_base . 'shop.svg'); ?>" alt="">
                            </div>
                            <span>Sklepy</span>
                        </label>

                        <label class="filter-item">
                            <input type="checkbox" id="sport" checked>
                            <div class="filter-icon-wrapper">
                                <img class="filter-icon" src="<?= esc_url($svg_base . 'sport.svg'); ?>" alt="">
                            </div>
                            <span>Sport</span>
                        </label>
                    </div>


                    <!-- <div class="info-stats" id="stats">
                        🟢 Ładowanie danych...
                    </div> -->
                </div>
            </div>

            <script>
                window.SM_POI_DATA = {
                    center: [<?= $lat ?>, <?= $lng ?>],
                    pois: <?= wp_json_encode($pois) ?>
                };
            </script>
        </div>
    </section>

    <section class="sec-block sec5 fixed-width animate__animated" data-animation="fadeIn" id="opis_inwestycji"
        data-hotlink="#opis_inwestycji" data-hotlink-name="Opis inwestycji">
        <div class="section-content">
            <h2 style="display: none">Opis inwestycji</h2>
            <div class="left-col">
                <?php echo wpautop(wp_kses_post($excerpt)); ?>
            </div>
            <div class="right-col">
                <?php
                $side_img = (!empty($thumb2_data) && !empty($thumb2_data[0]))
                    ? $thumb2_data[0]
                    : (!empty($image_data) && !empty($image_data[0]) ? $image_data[0] : '');
                ?>

                <?php if (!empty($side_img)): ?>
                    <img src="<?php echo esc_url($side_img); ?>" alt="<?php echo esc_attr($title); ?>">
                <?php endif; ?>

            </div>
        </div>
    </section>

    <?php if (!empty($additional_products)): ?>
        <section class="sec-block sec6 fixed-width additional-products-section animate__animated" data-animation="fadeIn"
            data-hotlink="#dodatkowe_produkty" data-hotlink-name="Dodatkowe produkty">
            <div class="section-content">
                <div class="heading-block">
                    <h2>Dodatkowe produkty</h2>
                </div>

                <div class="additional-products">
                    <?php foreach ($additional_products as $p):
                        $icon_id = (int) ($p['icon'] ?? 0);
                        $icon_url = $icon_id ? wp_get_attachment_url($icon_id) : '';
                        $name = (string) ($p['name'] ?? '');
                        $price = (string) ($p['price'] ?? '');
                        ?>
                        <div class="additional-product">
                            <div class="additional-product-inner">
                                <div class="top-row">
                                    <div class="additional-product-icon">
                                        <img src="<?php echo esc_url($icon_url); ?>" alt="">
                                    </div>
                                    <div class="additional-product-name">
                                        <span><?php echo esc_html($name); ?></span>
                                    </div>
                                </div>
                                <div class="bottom-row">
                                    <div class="additional-product-price">
                                        OD <span><?php echo esc_html($price); ?> zł Brutto</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>


    <?php if (!empty($gallery_ids)): ?>
        <section class="sec-block sec7 fixed-width animate__animated" data-animation="fadeIn" data-hotlink="#galeria"
            data-hotlink-name="Galeria">
            <div class="section-content">
                <div class="heading-block">
                    <h2>Galeria</h2>
                </div>

                <div class="gallery-wrapper">
                    <div class="swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($gallery_ids as $img_id): ?>
                                <?php
                                $img_id = (int) $img_id;
                                if (!$img_id)
                                    continue;

                                $alt = get_post_meta($img_id, '_wp_attachment_image_alt', true);
                                if ($alt === '') {
                                    $alt = get_the_title($img_id);
                                }

                                $src = wp_get_attachment_image_url($img_id, 'large');
                                if (!$src)
                                    continue;
                                ?>
                                <div class="swiper-slide">
                                    <img src="<?php echo esc_url($src); ?>" alt="<?php echo esc_attr($alt); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="swiper-pagination">
                            <!-- @TODO: Do przerobienia swiper paginacji. -->
                        </div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>


    <section class="sec-block sales-office-section sec7 fixed-width animate__animated" data-animation="fadeIn"
        data-hotlink="#biuro_sprzedazy" data-hotlink-name="Biuro sprzedaży">
        <div class="section-content">
            <div class="heading-block">
                <h2>Biuro sprzedaży</h2>
            </div>
            <div class="col-wrapper">
                <div class="col">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1577.5044043894695!2d14.531835519955893!3d53.414228114854986!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x47aa08dfc296c4b7%3A0xc35be30ac0a1171a!2sal.%20Powsta%C5%84c%C3%B3w%20Wielkopolskich%2081%20A%2C%2070-110%20Szczecin!5e0!3m2!1spl!2spl!4v1768912667419!5m2!1spl!2spl"
                        width="725" height="433" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
                <div class="col">
                    <div class="top-row">
                        <h4>Biuro sprzedaży</h4>
                    </div>
                    <div class="bottom-row">
                        <div class="item">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="19.551" height="26"
                                    viewBox="0 0 19.551 26">
                                    <path id="location-dot"
                                        d="M5.7,9.775a4.073,4.073,0,1,1,4.073,4.073A4.072,4.072,0,0,1,5.7,9.775ZM9.775,6.517a3.258,3.258,0,1,0,3.258,3.258A3.261,3.261,0,0,0,9.775,6.517Zm9.775,3.258c0,4.45-5.957,12.372-8.569,15.64a1.538,1.538,0,0,1-2.413,0C5.911,22.147,0,14.225,0,9.775a9.775,9.775,0,0,1,19.551,0ZM9.775.815A8.961,8.961,0,0,0,.815,9.775a9.684,9.684,0,0,0,.907,3.544A32.386,32.386,0,0,0,4,17.611a83.4,83.4,0,0,0,5.207,7.3.723.723,0,0,0,1.14,0,83.6,83.6,0,0,0,5.208-7.3,32.688,32.688,0,0,0,2.276-4.292,9.732,9.732,0,0,0,.906-3.544A8.961,8.961,0,0,0,9.775.815Z"
                                        fill="#2f2d7b" />
                                </svg>
                            </div>
                            <div class="content">
                                <div class="name">
                                    <span>Al. Powstańców Wlkp. 81A</span>
                                </div>
                                <div class="small-text">
                                    <span>70-110 Szczecin</span>
                                </div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="25.997" height="26"
                                    viewBox="0 0 25.997 26">
                                    <path id="phone-flip"
                                        d="M24.787,1.224,19.669.043a1.6,1.6,0,0,0-1.817.92L15.491,6.471a1.58,1.58,0,0,0,.456,1.852l2.732,2.236a17.756,17.756,0,0,1-8.1,8.1L8.342,15.93a1.589,1.589,0,0,0-1.851-.456L.982,17.836a1.6,1.6,0,0,0-.92,1.819l1.18,5.118A1.577,1.577,0,0,0,2.789,26,23.253,23.253,0,0,0,26.017,2.774,1.584,1.584,0,0,0,24.787,1.224Zm-22,23.963a.772.772,0,0,1-.756-.6L.851,19.469a.785.785,0,0,1,.454-.893l5.509-2.362a.776.776,0,0,1,.9.224l2.64,3.226a18.542,18.542,0,0,0,9.332-9.332L16.462,7.69a.768.768,0,0,1-.222-.9L18.6,1.281a.78.78,0,0,1,.887-.449l5.118,1.18a.779.779,0,0,1,.6.758A22.471,22.471,0,0,1,2.787,25.186Z"
                                        transform="translate(-0.021 -0.002)" fill="#2f2d7b" />
                                </svg>
                            </div>
                            <div class="content">
                                <div class="small-text">
                                    <span>91 422 16 52</span>
                                    <span>501 303 116</span>
                                    <span>501 693 180</span>
                                </div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="28.667" height="21.5"
                                    viewBox="0 0 28.667 21.5">
                                    <path id="envelope"
                                        d="M25.083,64H3.583A3.583,3.583,0,0,0,0,67.583V81.917A3.583,3.583,0,0,0,3.583,85.5h21.5a3.583,3.583,0,0,0,3.583-3.583V67.583A3.585,3.585,0,0,0,25.083,64Zm2.688,17.917A2.69,2.69,0,0,1,25.083,84.6H3.583A2.69,2.69,0,0,1,.9,81.917V70.22L12.458,79.47a3.006,3.006,0,0,0,3.751,0L27.771,70.22Zm0-12.844-12.122,9.7a2.158,2.158,0,0,1-2.632,0L.9,69.073V67.583A2.69,2.69,0,0,1,3.583,64.9h21.5a2.69,2.69,0,0,1,2.688,2.688Z"
                                        transform="translate(0 -64)" fill="#2f2d7b" />
                                </svg>
                            </div>
                            <div class="content">
                                <div class="small-text">
                                    <span>biuro@siemaszko.pl</span>
                                </div>
                            </div>
                        </div>
                        <div class="item">
                            <div class="icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 26 26">
                                    <path id="clock"
                                        d="M12.594,5.281a.406.406,0,0,1,.813,0v7.5L18.1,15.91a.407.407,0,1,1-.447.68l-4.875-3.25a.452.452,0,0,1-.229-.34ZM13,0A13,13,0,1,1,0,13,13,13,0,0,1,13,0ZM.813,13A12.188,12.188,0,1,0,13,.813,12.191,12.191,0,0,0,.813,13Z"
                                        fill="#2f2d7b" />
                                </svg>
                            </div>
                            <div class="content">
                                <div class="name">
                                    <span>Poniedziałek - Piątek</span>
                                </div>
                                <div class="small-text">
                                    <span>07:30 - 16:00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="sec-block sec8 fixed-width">
        <div class="section-content">
            <div class="heading-block">
                <h2>INNE INWESTYCJE</h2>
            </div>
            <?php echo do_shortcode('[sm_other_investments]') ?>
        </div>
    </section>

</main>
<?php get_footer(); ?>