<?php
if (!defined('ABSPATH')) {
    exit;
}

class SM_INV_Fixed_Dashboard_Page
{
    public static function render(): void
    {
        if (!SM_INV_Fixed_Utils::current_user_can_manage()) {
            SM_INV_Fixed_Utils::admin_die_forbidden();
        }

        $summary = SM_INV_Fixed_Dashboard_Service::get_summary();
        $rows = SM_INV_Fixed_Dashboard_Service::get_investments_rows();
        $validations = SM_INV_Fixed_Dashboard_Service::get_validations();

        echo '<div class="wrap sm-inv-fixed sm-inv-dashboard">';
        echo '<h1>Dashboard inwestycji</h1>';

        echo '<div class="sm-inv-dashboard-cards" style="display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:16px;margin-top:20px;">';
        self::card('Aktywne inwestycje', (string) $summary['active_investments']);
        self::card('Wszystkie mieszkania', (string) $summary['all_flats']);
        self::card('Dostępne', (string) $summary['available_flats']);
        self::card('Sprzedane / niedostępne', (string) $summary['sold_flats']);
        echo '</div>';

        echo '<div style="margin-top:16px;max-width:260px;">';
        self::card('Poziom sprzedaży', $summary['sales_percent'] . '%');
        echo '</div>';


        echo '<div style="margin-top:30px;background:#fff;padding:20px;border:1px solid #e5e7eb;border-radius:12px;">';
        echo '<h2 style="margin-top:0;">Aktywne inwestycje</h2>';

        if (empty($rows)) {
            echo '<p>Brak aktywnych inwestycji.</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>Inwestycja</th>';
            echo '<th>Wszystkie</th>';
            echo '<th>Dostępne</th>';
            echo '<th>Zarezerwowane</th>';
            echo '<th>Sprzedane</th>';
            echo '<th>Sprzedaż %</th>';
            echo '<th style="width:120px;"></th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($rows as $row) {
                echo '<tr>';
                echo '<td>' . esc_html($row['title'] ?? '') . '</td>';
                echo '<td>' . esc_html((string) ($row['all_flats'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) ($row['available_flats'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) ($row['reserved_flats'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) ($row['sold_flats'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) ($row['sales_percent'] ?? 0)) . '%</td>';
                echo '<td>';
                self::render_actions_radial($row['actions'] ?? [], (int) ($row['id'] ?? 0));
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        }

        echo '</div>';
        self::render_validations($validations);

        self::render_inline_styles();
        self::render_inline_script();

        echo '</div>';
    }

    private static function card(string $label, string $value): void
    {
        echo '<div style="background:#fff;padding:20px;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.05);">';
        echo '<div style="font-size:13px;color:#6b7280;margin-bottom:8px;">' . esc_html($label) . '</div>';
        echo '<div style="font-size:28px;font-weight:700;line-height:1.2;">' . esc_html($value) . '</div>';
        echo '</div>';
    }

    private static function render_validations(array $validations): void
    {
        echo '<div style="margin-top:30px;background:#fff;padding:20px;border:1px solid #e5e7eb;border-radius:12px;">';
        echo '<h2 style="margin-top:0;">Wymaga uwagi</h2>';

        if (empty($validations)) {
            echo '<p style="margin:0;color:#4b5563;">Brak problemów w aktywnych inwestycjach.</p>';
            echo '</div>';
            return;
        }

        echo '<div style="display:flex;flex-direction:column;gap:10px;">';

        foreach ($validations as $validation) {
            $type = (string) ($validation['type'] ?? 'warning');
            $title = (string) ($validation['title'] ?? 'Uwaga');
            $message = (string) ($validation['message'] ?? '');
            $url = (string) ($validation['url'] ?? '');

            $border = $type === 'error' ? '#dc2626' : '#f59e0b';
            $bg = $type === 'error' ? '#fef2f2' : '#fffbeb';

            echo '<div style="border:1px solid ' . esc_attr($border) . ';background:' . esc_attr($bg) . ';border-radius:10px;padding:14px 16px;">';
            echo '<div style="font-weight:700;margin-bottom:4px;">' . esc_html($title) . '</div>';
            echo '<div style="color:#374151;">' . esc_html($message) . '</div>';

            if ($url !== '') {
                echo '<div style="margin-top:10px;">';
                echo '<a class="button button-small" href="' . esc_url($url) . '">Przejdź</a>';
                echo '</div>';
            }

            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    private static function render_actions_radial(array $actions, int $investment_id): void
    {
        echo '<div class="sm-inv-radial" data-investment-id="' . esc_attr((string) $investment_id) . '">';
        echo '<button type="button" class="button button-secondary sm-inv-radial-toggle">Zarządzaj</button>';

        echo '<div class="sm-inv-radial-menu">';

        echo '<a class="sm-inv-radial-item sm-inv-radial-item-top" href="' . esc_url($actions['investment'] ?? '#') . '" data-label="Inwestycja" title="Inwestycja">';
        echo self::icon_investment();
        echo '</a>';

        echo '<a class="sm-inv-radial-item sm-inv-radial-item-right" href="' . esc_url($actions['objects'] ?? '#') . '" data-label="Budynki" title="Budynki">';
        echo self::icon_buildings();
        echo '</a>';

        echo '<a class="sm-inv-radial-item sm-inv-radial-item-bottom" href="' . esc_url($actions['floors'] ?? '#') . '" data-label="Piętra" title="Piętra">';
        echo self::icon_floors();
        echo '</a>';

        echo '<a class="sm-inv-radial-item sm-inv-radial-item-left" href="' . esc_url($actions['flats'] ?? '#') . '" data-label="Mieszkania" title="Mieszkania">';
        echo self::icon_flats();
        echo '</a>';

        echo '</div>';
        echo '</div>';
    }

    private static function icon_investment(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M3 21V7h4V3h10v8h4v10h-8v-4h-2v4zm2-2h2v-2H5zm0-4h2v-2H5zm0-4h2V9H5zm4 4h2v-2H9zm0-4h2V9H9zm0-4h2V5H9zm4 8h2v-2h-2zm0-4h2V9h-2zm0-4h2V5h-2zm4 12h2v-2h-2zm0-4h2v-2h-2z"/></svg>';
    }

    private static function icon_buildings(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M3 21V3h18v18zm2-2h4v-4H5zm0-6h4V9H5zm0-6h4V5H5zm6 12h4v-4h-4zm0-6h4V9h-4zm0-6h4V5h-4z"/></svg>';
    }

    private static function icon_floors(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M3 3h18v4H3zm0 7h18v4H3zm0 7h18v4H3z"/></svg>';
    }

    private static function icon_flats(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 3l9 8h-3v9h-5v-6H11v6H6v-9H3z"/></svg>';
    }

    private static function render_inline_styles(): void
    {
        echo '<style>
            .sm-inv-radial {
                position: relative;
                display: inline-block;
                width: 96px;
                height: 40px;
            }

            .sm-inv-radial-toggle {
                position: relative;
                z-index: 3;
                width: 96px;
            }

            .sm-inv-radial-menu {
                position: absolute;
                left: 50%;
                top: 50%;
                width: 0;
                height: 0;
                z-index: 20;
            }

            .sm-inv-radial-item {
                position: absolute;
                width: 40px;
                height: 40px;
                margin-left: -20px;
                margin-top: -20px;
                border-radius: 999px;
                background: #2b2d83;
                color: #fff !important;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 4px 10px rgba(0,0,0,.15);
                opacity: 0;
                pointer-events: none;
                transform: scale(.75);
                transition: all .18s ease;
            }

            .sm-inv-radial-item svg {
                display: block;
            }

            .sm-inv-radial.is-open .sm-inv-radial-item {
                opacity: 1;
                pointer-events: auto;
            }

            .sm-inv-radial.is-open .sm-inv-radial-item-top {
                transform: translateY(-58px) scale(1);
            }

            .sm-inv-radial.is-open .sm-inv-radial-item-right {
                transform: translateX(58px) scale(1);
            }

            .sm-inv-radial.is-open .sm-inv-radial-item-bottom {
                transform: translateY(58px) scale(1);
            }

            .sm-inv-radial.is-open .sm-inv-radial-item-left {
                transform: translateX(-58px) scale(1);
            }

            .sm-inv-radial-item:hover {
                background: #af423e;
                color: #fff !important;
            }

            .sm-inv-radial-item::after {
                content: attr(data-label);
                position: absolute;
                bottom: -32px;
                left: 50%;
                transform: translateX(-50%);
                background: #1d2327;
                color: #fff;
                font-size: 11px;
                line-height: 1;
                padding: 6px 8px;
                border-radius: 6px;
                white-space: nowrap;
                opacity: 0;
                pointer-events: none;
                transition: opacity .2s ease;
            }

            .sm-inv-radial-item:hover::after {
                opacity: 1;
            }
        </style>';
    }

    private static function render_inline_script(): void
    {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function () {
                document.querySelectorAll(".sm-inv-radial-toggle").forEach(function (button) {
                    button.addEventListener("click", function (e) {
                        e.preventDefault();
                        e.stopPropagation();

                        var wrap = button.closest(".sm-inv-radial");

                        document.querySelectorAll(".sm-inv-radial.is-open").forEach(function (item) {
                            if (item !== wrap) {
                                item.classList.remove("is-open");
                            }
                        });

                        wrap.classList.toggle("is-open");
                    });
                });

                document.addEventListener("click", function () {
                    document.querySelectorAll(".sm-inv-radial.is-open").forEach(function (item) {
                        item.classList.remove("is-open");
                    });
                });

                document.querySelectorAll(".sm-inv-radial-menu").forEach(function (menu) {
                    menu.addEventListener("click", function (e) {
                        e.stopPropagation();
                    });
                });
            });
        </script>';
    }
}