<?php
if (!defined('ABSPATH'))
    exit;

$flat_id = (int) get_query_var('sm_flat_id');
$flat = $flat_id ? SM_INV_Fixed_DB::flat_get($flat_id) : null;
if (!$flat) {
    status_header(404);
    nocache_headers();
    echo '404';
    exit;
}
$plan_id = isset($flat['media']) ? (int) $flat['media'] : 0;
$plan_img_html = '';

if ($plan_id > 0) {
    $plan_img_html = wp_get_attachment_image(
        $plan_id,
        'large',
        false,
        ['class' => 'flat-plan-img']
    );
}
$flatID = $flat['id'];
$flatCode = $flat['code'];
$flatMeters = $flat['meters'];
$flatRoomCount = $flat['rooms'];
$flatStatus = $flat['status'];
if ($flatStatus == 1) {
    $flatStatus = "Dostępne";
} else if ($flatStatus == 2) {
    $flatStatus = "Zarezerwowane";
} else {
    $flatStatus = "Sprzedane";
}
$flatPrice = $flat['price'];
$totalPrice = $flat['total_price'];

// Pobranie historii cen
$price_history = [];
if (class_exists('SM_INV_Fixed_DB') && method_exists('SM_INV_Fixed_DB', 'price_history')) {
    $price_history = SM_INV_Fixed_DB::price_history($flatID);
}

$last_price_change_label = '';
if (!empty($price_history)) {
    $last_change = $price_history[0]; // zakładamy ORDER BY change_date DESC
    $timestamp = strtotime($last_change['change_date']);
    if ($timestamp) {
        $last_price_change_label = sprintf(
            'Ostatnia zmiana ceny %s',
            date_i18n('j F Y', $timestamp)
        );
    }
}

// Pobranie historii cen
$price_history = [];
if (class_exists('SM_INV_Fixed_DB') && method_exists('SM_INV_Fixed_DB', 'price_history')) {
    $price_history = SM_INV_Fixed_DB::price_history($flatID);
}

$last_price_change_label = '';
if (!empty($price_history)) {
    $last_change = $price_history[0]; // zakładamy ORDER BY change_date DESC
    $timestamp = strtotime($last_change['change_date']);
    if ($timestamp) {
        $last_price_change_label = sprintf(
            date_i18n('j F Y', $timestamp)
        );
    }
}
$flatFloorNumber = $floor_number;
$invTitle = $investment_title;

$plan_url = '';
if (is_numeric($plan_id)) {
    $plan_url = wp_get_attachment_url((int) $plan_id);
}

get_header();
?>

<main class="sm-flat-single">
    <section class="sec-block sec1 fixed-width" style="padding-top: 150px;">
        <div class="breadcrumb-wrapper">
            <a href="<?php echo esc_url(home_url('/')); ?>">Strona główna</a>
            <span>/</span>
            <span><?php echo esc_html($invTitle); ?></span>
            <span>/</span>
            <span class="current">Mieszkanie <?php echo esc_html($flatCode); ?></span>
        </div>
        <div class="container">
            <p style="margin-top:20px;"><a href="<?php echo esc_url(wp_get_referer() ?: home_url('/')); ?>">&larr;
                    Wróć</a></p>
            <div class="sm-flat-meta">
                <div class="column-wrapper">

                    <!-- LEWA KOLUMNA -->
                    <div class="column col-left">
                        <h2 class="investment-title"><?= esc_html($invTitle); ?></h2>

                        <div class="meta-list">
                            <div class="meta-row">
                                <span class="label">NUMER MIESZKANIA:</span>
                                <span class="value"><?= esc_html($flatCode); ?></span>
                            </div>

                            <div class="meta-row">
                                <span class="label">POWIERZCHNIA:</span>
                                <span class="value"><?= esc_html($flatMeters); ?> m²</span>
                            </div>

                            <div class="meta-row">
                                <span class="label">ILOŚĆ POMIESZCZEŃ:</span>
                                <span class="value"><?= esc_html($flatRoomCount); ?></span>
                            </div>

                            <div class="meta-row">
                                <span class="label">STATUS:</span>
                                <span class="value status status--<?= strtolower($flatStatus); ?>">
                                    <?= esc_html($flatStatus); ?>
                                </span>
                            </div>

                            <div class="meta-row">
                                <span class="label">PIĘTRO:</span>
                                <span class="floor-badge"><?= esc_html($flatFloorNumber); ?></span>
                            </div>
                        </div>

                        <div class="price-box">
                            <span class="price-label">CENA</span>
                            <div class="price-value">
                                <span id="totalPrice"><?= number_format($totalPrice, 2, ',', ' '); ?> zł</span>
                                <span id="pricePerMeter"><?= number_format($flatPrice, 2, ',', ' '); ?> zł/m²</span>
                            </div>

                            <?php if ($last_price_change_label): ?>
                                <div class="price-last-change">
                                    <p>Ostatnia zmiana ceny <span
                                            class="fw-bold"><?= esc_html($last_price_change_label); ?></span></p>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($price_history)): ?>
                                <div class="price-history-link">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 10 10">
                                        <path id="clock-rotate-left"
                                            d="M5,0A5,5,0,1,1,2.143,9.1a.625.625,0,0,1,.715-1.025A3.75,3.75,0,1,0,5,1.25a3.712,3.712,0,0,0-2.652,1.1l.6.6a.469.469,0,0,1-.33.8H.469A.468.468,0,0,1,0,3.281V1.132A.469.469,0,0,1,.8.8l.664.664A4.977,4.977,0,0,1,4.982,0ZM5,2.5a.468.468,0,0,1,.469.469V4.807L6.721,6.074a.457.457,0,0,1-.646.646L4.668,5.314A.4.4,0,0,1,4.531,5V2.969A.468.468,0,0,1,5,2.5Z" />
                                    </svg>

                                    <a href="#" class="js-price-history-open">Szczegóły i historia cen</a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        $pdf_url = add_query_arg([
                            'action' => 'sm_inv_flat_pdf',
                            'flat_id' => $flat_id,
                            // '_wpnonce' => wp_create_nonce('sm_inv_flat_pdf_' . $flat_id),
                        ], admin_url('admin-post.php'));
                        ?>

                        <p style="margin-top:15px;">
                            <a class="btn-download" href="<?php echo esc_url($pdf_url); ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16">
                                    <path id="download"
                                        d="M15,11H10.828L9.414,12.414a2,2,0,0,1-2.828,0L5.172,11H1a1,1,0,0,0-1,1v3a1,1,0,0,0,1,1H15a1,1,0,0,0,1-1V12A1,1,0,0,0,15,11Zm-1.5,3.25a.75.75,0,1,1,.75-.75A.752.752,0,0,1,13.5,14.25ZM7.294,11.706a1,1,0,0,0,1.413,0l4-4a1,1,0,0,0-1.414-1.414L9,8.588V1A1,1,0,0,0,7,1V8.588L4.706,6.294A1,1,0,0,0,3.292,7.708Z"
                                        fill="#f5f5ff" />
                                </svg>
                                Pobierz kartę mieszkania
                            </a>
                        </p>
                    </div>

                    <!-- ŚRODEK – RZUT -->
                    <div class="column col-mid">
                        <div class="plan-wrapper">
                            <?php if ($plan_img_html): ?>
                                <?= $plan_img_html; ?>
                            <?php else: ?>
                                <div class="plan-missing">
                                    Brak rzutu mieszkania
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- PRAWA KOLUMNA – FORMULARZ -->
                    <div class="column col-right">
                        <div class="contact-box">
                            <h3>ZAPYTAJ O <span>MIESZKANIE</span></h3>

                            <form class="flat-contact-form">

                                <input type="text" placeholder="Imię i nazwisko">
                                <input type="email" placeholder="Adres e-mail">
                                <input type="tel" placeholder="Numer telefonu">

                                <textarea
                                    rows="4">Dzień dobry! Interesuje mnie mieszkanie nr <?= esc_html($flatCode); ?> na inwestycji <?= esc_html($invTitle); ?>. Proszę o kontakt.</textarea>

                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox">
                                        Zaznacz wszystkie
                                    </label>

                                    <label>
                                        <input type="checkbox">Wyrażam zgodę na przetwarzanie moich danych osobowych w
                                        celu przygotowania i przedstawienia oferty przez Siemaszko Sp. z o.o.
                                    </label>

                                    <label>
                                        <input type="checkbox">
                                        Wyrażam zgodę na przesyłanie za pomocą środków komunikacji elektronicznej, w
                                        szczególności poczty elektronicznej i numer telefonu komórkowego skierowanej do
                                        mnie informacji handlowej.
                                    </label>
                                </div>

                                <button type="submit" class="btn-submit">
                                    Wyślij wiadomość
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="sec-block sales-office-section sec2 fixed-width animate__animated" data-animation="fadeIn">
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
    <?php
    // echo "<pre style='padding-top: 150px'>";
    // var_dump($flat);
    // echo "</pre>";
    ?>
    <?php if (!empty($price_history)): ?>
        <div id="price-history-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;">
            <div style="background:#fff;max-width:500px;margin:80px auto;padding:20px;position:relative;">
                <button type="button" class="js-price-history-close"
                    style="position:absolute;top:10px;right:10px;">×</button>
                <h3>Historia cen</h3>
                <ul style="margin:15px 0;padding-left:18px;">
                    <?php foreach ($price_history as $row): ?>
                        <?php
                        $date = date_i18n('j F Y', strtotime($row['change_date']));
                        $old = isset($row['old_price']) ? number_format((float) $row['old_price'], 2, ',', ' ') : '';
                        $new = isset($row['new_price']) ? number_format((float) $row['new_price'], 2, ',', ' ') : '';
                        ?>
                        <li>
                            <strong><?= esc_html($date); ?></strong> –
                            <?= esc_html($old); ?> zł
                            <?php if ($new !== ''): ?>
                                → <?= esc_html($new); ?> zł
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var openBtn = document.querySelector('.js-price-history-open');
                var modal = document.getElementById('price-history-modal');
                var closeBtn = document.querySelector('.js-price-history-close');

                if (openBtn && modal) {
                    openBtn.addEventListener('click', function (e) {
                        e.preventDefault();
                        modal.style.display = 'block';
                    });
                }

                if (closeBtn && modal) {
                    closeBtn.addEventListener('click', function () {
                        modal.style.display = 'none';
                    });
                }

                if (modal) {
                    modal.addEventListener('click', function (e) {
                        if (e.target === modal) {
                            modal.style.display = 'none';
                        }
                    });
                }
            });
        </script>
    <?php endif; ?>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.querySelector('.checkbox-group label:first-child input');
    const checkboxes = document.querySelectorAll('.checkbox-group label:not(:first-child) input');

    // Klik "zaznacz wszystkie"
    selectAll.addEventListener('change', function () {
        checkboxes.forEach(cb => {
            cb.checked = selectAll.checked;
        });
    });

    // Klik pojedynczego checkboxa
    checkboxes.forEach(cb => {
        cb.addEventListener('change', function () {
            const allChecked = Array.from(checkboxes).every(c => c.checked);
            const noneChecked = Array.from(checkboxes).every(c => !c.checked);

            // jeśli wszystkie zaznaczone → zaznacz "select all"
            if (allChecked) {
                selectAll.checked = true;
                selectAll.indeterminate = false;
            } 
            // jeśli żaden → odznacz
            else if (noneChecked) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } 
            // jeśli część → stan pośredni
            else {
                selectAll.checked = false;
                selectAll.indeterminate = true;
            }
        });
    });
});
</script>

<?php get_footer(); ?>