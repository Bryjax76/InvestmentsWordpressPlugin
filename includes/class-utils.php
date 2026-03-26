<?php
if (!defined('ABSPATH')) {
    exit;
}

final class SM_INV_Fixed_Utils
{

    public static function current_user_can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    public static function admin_die_forbidden(): void
    {
        wp_die(esc_html__('Brak uprawnień.', 'sm-inv-fixed'), 403);
    }

    public static function sanitize_int($value): int
    {
        return absint($value);
    }

    public static function sanitize_float($value): float
    {
        // Accept "1 234,56" or "1234.56"
        if (is_string($value)) {
            $value = str_replace([' ', "\u00A0"], '', $value);
            $value = str_replace(',', '.', $value);
        }
        return (float)$value;
    }

    public static function esc_textarea_nullable($value): string
    {
        return esc_textarea((string)$value);
    }

    public static function admin_url_page(string $page, array $args = []): string
    {
        $args = array_merge(['page' => $page], $args);
        return admin_url('admin.php?' . http_build_query($args));
    }

    public static function checkbox_to_int($value): int
    {
        return !empty($value) ? 1 : 0;
    }

    public static function sanitize_key_or_default($value, string $default): string
    {
        $k = sanitize_key($value);
        return $k ? $k : $default;
    }

    public static function sanitize_order_dir($value): string
    {
        $v = strtolower((string)$value);
        return in_array($v, ['asc', 'desc'], true) ? $v : 'asc';
    }

    public static function sanitize_orderby($value, array $allowed, string $default): string
    {
        $v = sanitize_key($value);
        return in_array($v, $allowed, true) ? $v : $default;
    }

    public static function admin_notice_missing_tables(array $missing): void
    {
        if (empty($missing)) return;
        $msg = sprintf(
            /* translators: %s: table list */
            __('Brak tabel w bazie danych: %s. Uruchom aktywację wtyczki ponownie lub utwórz tabele ręcznie.', 'sm-inv-fixed'),
            implode(', ', array_map('esc_html', $missing))
        );
        printf('<div class="notice notice-error"><p>%s</p></div>', $msg);
    }

    function sm_inv_fixed_inline_svg_from_attachment(int $attachment_id): string
    {
        $path = get_attached_file($attachment_id);
        if (!$path || !file_exists($path)) return '';
        $svg = file_get_contents($path);
        if (!$svg) return '';
        return $svg;
    }
}
