<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SM_INV_Fixed_PDF
{
    private static function load_dompdf(): void
    {
        $autoload = SM_INV_FIXED_PATH . 'lib/dompdf/autoload.inc.php';
        if (!file_exists($autoload)) {
            wp_die('Brak Dompdf: ' . esc_html($autoload));
        }

        // Dompdf ma własny autoloader (nie composer)
        require_once $autoload;
    }

    public static function render_flat_pdf(int $flat_id): void
    {
        if ($flat_id <= 0) {
            wp_die('Nieprawidłowe ID mieszkania.');
        }

        $flat = SM_INV_Fixed_DB::flat_get($flat_id);
        if (!$flat) {
            wp_die('Nie znaleziono mieszkania.');
        }

        $title = $flat['code'] ?? ('Mieszkanie #' . $flat_id);
        $meters = $flat['meters'] ?? '';
        $rooms = $flat['rooms'] ?? '';
        $price = $flat['price'] ?? '';
        $plan_id = (int) ($flat['media'] ?? 0);

        if ($plan_id <= 0) {
            wp_die('Brak rzutu mieszkania.');
        }

        $plan_path = get_attached_file($plan_id);
        if (!$plan_path || !file_exists($plan_path)) {
            wp_die('Nie znaleziono pliku SVG.');
        }

        $download_date = date_i18n('Y-m-d H:i:s');

        // wyczyść bufor
        if (ob_get_length()) {
            ob_end_clean();
        }

        // załaduj TCPDF
        require_once SM_INV_FIXED_PATH . 'lib/tcpdf/tcpdf.php';

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        $pdf->SetCreator('Siemaszko');
        $pdf->SetAuthor('Siemaszko');
        $pdf->SetTitle('Mieszkanie ' . $title);

        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);

        $pdf->AddPage();

        // Nagłówek
        $pdf->SetFont('dejavusans', '', 16);
        $pdf->Write(0, 'Mieszkanie ' . $title, '', 0, 'L', true);

        $pdf->Ln(5);

        // Dane
        $pdf->SetFont('dejavusans', '', 12);

        $pdf->Write(0, 'Powierzchnia: ' . $meters . ' m²', '', 0, 'L', true);
        $pdf->Write(0, 'Pokoje: ' . $rooms, '', 0, 'L', true);
        $pdf->Write(0, 'Cena: ' . number_format((float) $price, 2, ',', ' ') . ' zł', '', 0, 'L', true);

        $pdf->Ln(10);

        /*
         |--------------------------------------------------------------------------
         |  RYSOWANIE SVG (NAJWAŻNIEJSZE)
         |--------------------------------------------------------------------------
         */

        // TCPDF ma metodę ImageSVG
        $pdf->ImageSVG(
            $plan_path,
            '',     // x
            '',     // y (auto)
            170,    // width
            '',     // height auto
            '',     // link
            '',     // align
            '',
            0,
            false
        );

        $pdf->Ln(10);

        // Timestamp
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Write(0, 'Dokument wygenerowany: ' . $download_date, '', 0, 'L', true);

        $pdf->Output('mieszkanie-' . $flat_id . '.pdf', 'D');
        exit;
    }
}