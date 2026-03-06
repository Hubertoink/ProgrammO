<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Dashboard
{
    public const MENU_SLUG = 'programmo';
    public const SETTINGS_SLUG = 'programmo-settings';
    public const OPTION_KEY = 'programmo_settings';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'register_menu'], 9);
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
    }

    /* ------------------------------------------------------------------ */
    /*  Menu                                                               */
    /* ------------------------------------------------------------------ */

    public static function register_menu(): void
    {
        add_menu_page(
            __('ProgrammO', 'programmo'),
            __('ProgrammO', 'programmo'),
            'edit_posts',
            self::MENU_SLUG,
            [self::class, 'render_overview'],
            'dashicons-schedule',
            25
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Übersicht', 'programmo'),
            __('Übersicht', 'programmo'),
            'edit_posts',
            self::MENU_SLUG,
            [self::class, 'render_overview']
        );

        add_submenu_page(
            self::MENU_SLUG,
            __('Einstellungen', 'programmo'),
            __('Einstellungen', 'programmo'),
            'manage_options',
            self::SETTINGS_SLUG,
            [self::class, 'render_settings']
        );
    }

    /* ------------------------------------------------------------------ */
    /*  Admin assets (only on ProgrammO pages)                            */
    /* ------------------------------------------------------------------ */

    public static function enqueue_admin_assets(string $hook): void
    {
        if (strpos($hook, 'programmo') === false
            && !self::is_programmo_post_type_screen()) {
            return;
        }

        wp_enqueue_style(
            'programmo-admin',
            PROGRAMMO_URL . 'assets/css/admin.css',
            [],
            PROGRAMMO_VERSION
        );

        // Only load interactive dashboard JS on the overview page
        if ($hook === 'toplevel_page_programmo') {
            wp_enqueue_media();

            wp_enqueue_script(
                'programmo-dashboard',
                PROGRAMMO_URL . 'assets/js/programmo-dashboard.js',
                [],
                PROGRAMMO_VERSION,
                true
            );

            $weekday_map = [
                'monday'    => __('Montag', 'programmo'),
                'tuesday'   => __('Dienstag', 'programmo'),
                'wednesday' => __('Mittwoch', 'programmo'),
                'thursday'  => __('Donnerstag', 'programmo'),
                'friday'    => __('Freitag', 'programmo'),
                'saturday'  => __('Samstag', 'programmo'),
                'sunday'    => __('Sonntag', 'programmo'),
            ];

            $jugend_terms = EventsBridge::get_terms_for_taxonomy(EventsBridge::OKJA_TAX_JUGEND);
            $jugend_term_items = [];
            foreach ($jugend_terms as $term_id => $term_label) {
                $jugend_term_items[] = [
                    'id' => (int) $term_id,
                    'label' => (string) $term_label,
                ];
            }

            $people_by_source = EventsBridge::get_person_choices_by_source();

            wp_localize_script('programmo-dashboard', 'programmoDashboard', [
                'restBase'    => esc_url_raw(rest_url(RestApi::NAMESPACE)),
                'nonce'       => wp_create_nonce('wp_rest'),
                'weekdays'    => $weekday_map,
                'weekdayKeys' => array_keys($weekday_map),
                'jugendTerms' => $jugend_term_items,
                'programmoPeople' => $people_by_source['programmo'] ?? [],
                'okjaPeople'      => $people_by_source['okja'] ?? [],
            ]);
        }
    }

    private static function is_programmo_post_type_screen(): bool
    {
        if (!function_exists('get_current_screen')) {
            return false;
        }
        $screen = get_current_screen();
        if (!$screen) {
            return false;
        }
        return in_array($screen->post_type, ['programmo_area', 'programmo_person', 'programmo_offer', 'programmo_slot'], true);
    }

    /* ------------------------------------------------------------------ */
    /*  Overview page                                                      */
    /* ------------------------------------------------------------------ */

    public static function render_overview(): void
    {
        $area_count  = wp_count_posts('programmo_area');
        $person_count = wp_count_posts('programmo_person');
        $offer_count = wp_count_posts('programmo_offer');
        $slot_count  = wp_count_posts('programmo_slot');
        $okja_active = EventsBridge::is_available();

        ?>
        <div class="wrap programmo-dashboard">
            <h1><?php esc_html_e('ProgrammO — Wochenplan Dashboard', 'programmo'); ?></h1>

            <!-- Explainer box -->
            <div class="programmo-explainer">
                <h3><?php esc_html_e('So funktioniert ProgrammO', 'programmo'); ?></h3>
                <div class="programmo-explainer__grid">
                    <div class="programmo-explainer__item">
                        <span class="dashicons dashicons-grid-view" style="color:#2271b1"></span>
                        <div>
                            <strong><?php esc_html_e('Offener Bereich', 'programmo'); ?></strong><br>
                            <?php esc_html_e('= Öffnungszeit des Jugendhauses (hellblaue Kachel). Hat eine Farbe/Gradient und zugeordnetes Betreuungsteam.', 'programmo'); ?>
                        </div>
                    </div>
                    <div class="programmo-explainer__item">
                        <span class="dashicons dashicons-calendar-alt" style="color:#a12ed2"></span>
                        <div>
                            <strong><?php esc_html_e('Slot', 'programmo'); ?></strong><br>
                            <?php esc_html_e('= Eintrag im Wochenplan. Verknüpft Wochentag + Uhrzeit + Offenen Bereich + Angebot miteinander.', 'programmo'); ?>
                        </div>
                    </div>
                    <div class="programmo-explainer__item">
                        <span class="dashicons dashicons-welcome-learn-more" style="color:#ff4fa1"></span>
                        <div>
                            <strong><?php esc_html_e('Angebot', 'programmo'); ?></strong><br>
                            <?php esc_html_e('= Aktivität wie „Zirkus Trolori" oder „Medienfetz" (bunte Gradient-Kachel). Kann aus Events_OKJA kommen oder direkt in ProgrammO angelegt werden.', 'programmo'); ?>
                        </div>
                    </div>
                    <div class="programmo-explainer__item">
                        <span class="dashicons dashicons-groups" style="color:#00a32a"></span>
                        <div>
                            <strong><?php esc_html_e('Person', 'programmo'); ?></strong><br>
                            <?php esc_html_e('= Mitarbeiter:in / Betreuer:in, wird einem Offenen Bereich zugeordnet.', 'programmo'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status cards -->
            <div class="programmo-status-cards">
                <div class="programmo-status-card">
                    <span class="dashicons dashicons-grid-view"></span>
                    <div class="programmo-status-card__body">
                        <strong><?php echo esc_html((string) ($area_count->publish ?? 0)); ?></strong>
                        <?php esc_html_e('Offene Bereiche (Öffnungen)', 'programmo'); ?>
                    </div>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=programmo_area')); ?>" class="programmo-status-card__link"><?php esc_html_e('Verwalten', 'programmo'); ?></a>
                </div>
                <div class="programmo-status-card">
                    <span class="dashicons dashicons-groups"></span>
                    <div class="programmo-status-card__body">
                        <strong><?php echo esc_html((string) ($person_count->publish ?? 0)); ?></strong>
                        <?php esc_html_e('Personen (Team)', 'programmo'); ?>
                    </div>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=programmo_person')); ?>" class="programmo-status-card__link"><?php esc_html_e('Verwalten', 'programmo'); ?></a>
                </div>
                <div class="programmo-status-card">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <div class="programmo-status-card__body">
                        <strong><?php echo esc_html((string) ($slot_count->publish ?? 0)); ?></strong>
                        <?php esc_html_e('Slots (Wochenplan-Einträge)', 'programmo'); ?>
                    </div>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=programmo_slot')); ?>" class="programmo-status-card__link"><?php esc_html_e('Verwalten', 'programmo'); ?></a>
                </div>
                <div class="programmo-status-card">
                    <span class="dashicons dashicons-welcome-learn-more"></span>
                    <div class="programmo-status-card__body">
                        <strong><?php echo esc_html((string) ($offer_count->publish ?? 0)); ?></strong>
                        <?php esc_html_e('Eigene Angebote', 'programmo'); ?>
                    </div>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=programmo_offer')); ?>" class="programmo-status-card__link"><?php esc_html_e('Verwalten', 'programmo'); ?></a>
                </div>
                <div class="programmo-status-card <?php echo $okja_active ? 'programmo-status-card--ok' : 'programmo-status-card--warn'; ?>">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <div class="programmo-status-card__body">
                        <strong><?php echo $okja_active ? esc_html__('Aktiv', 'programmo') : esc_html__('Nicht erkannt', 'programmo'); ?></strong>
                        <?php esc_html_e('Events_OKJA (externe Angebote)', 'programmo'); ?>
                    </div>
                </div>
            </div>

            <!-- Quick actions -->
            <div class="programmo-quick-actions">
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=programmo_slot')); ?>" class="button button-primary button-hero">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Neuer Slot', 'programmo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=programmo_area')); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Neuer Bereich', 'programmo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=programmo_person')); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Neue Person', 'programmo'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=programmo_offer')); ?>" class="button button-secondary button-hero">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('Neues Angebot', 'programmo'); ?>
                </a>
            </div>

            <!-- Interactive Wochenplan -->
            <h2><?php esc_html_e('Wochenplan-Übersicht', 'programmo'); ?></h2>
            <p class="description" style="margin-bottom:12px;">
                <?php esc_html_e('Slots direkt hier anlegen und Angebote per Drag & Drop zuordnen.', 'programmo'); ?>
            </p>

            <div class="programmo-weekday-toggles">
                <label class="programmo-weekday-toggle">
                    <input type="checkbox" id="programmo-toggle-saturday" checked>
                    <span><?php esc_html_e('Samstag anzeigen', 'programmo'); ?></span>
                </label>
                <label class="programmo-weekday-toggle">
                    <input type="checkbox" id="programmo-toggle-sunday" checked>
                    <span><?php esc_html_e('Sonntag anzeigen', 'programmo'); ?></span>
                </label>
            </div>

            <div class="programmo-planner-layout">
                <!-- Angebote Pool (Sidebar) -->
                <div class="programmo-angebote-sidebar">
                    <h3><?php esc_html_e('Angebote', 'programmo'); ?></h3>
                    <p class="description"><?php esc_html_e('Externe OKJA-Angebote können weiter genutzt werden. Eigene ProgrammO-Angebote lassen sich direkt hier anlegen.', 'programmo'); ?></p>
                    <input type="text" id="programmo-angebote-search" class="programmo-angebote-search" placeholder="<?php esc_attr_e('Suchen…', 'programmo'); ?>">
                    <div id="programmo-angebote-pool" class="programmo-angebote-pool">
                        <p class="programmo-pool-empty"><?php esc_html_e('Lade Angebote…', 'programmo'); ?></p>
                    </div>
                </div>

                <!-- Interactive Week Matrix -->
                <div id="programmo-interactive-matrix" class="programmo-week-matrix programmo-week-matrix--interactive">
                    <p style="padding:20px; color:#8c8f94;"><?php esc_html_e('Lade Wochenplan…', 'programmo'); ?></p>
                </div>
            </div>

            <!-- Einbinden-Hint -->
            <div class="programmo-shortcode-hint">
                <h3><?php esc_html_e('Wochenplan einbinden', 'programmo'); ?></h3>

                <div class="programmo-embed-options">
                    <div class="programmo-embed-option">
                        <span class="dashicons dashicons-block-default" style="color:#2271b1;font-size:20px;width:20px;height:20px"></span>
                        <div>
                            <strong><?php esc_html_e('Gutenberg Block', 'programmo'); ?></strong>
                            <p class="description"><?php esc_html_e('Im Block-Editor nach „ProgrammO Wochenplan" suchen und einfügen. Der Block bietet eigene Optionen für Spalten, Wochentage, Kompakt-Modus etc.', 'programmo'); ?></p>
                        </div>
                    </div>
                    <div class="programmo-embed-option">
                        <span class="dashicons dashicons-shortcode" style="color:#8c8f94;font-size:20px;width:20px;height:20px"></span>
                        <div>
                            <strong><?php esc_html_e('Shortcode (Classic Editor)', 'programmo'); ?></strong>
                            <code>[programmo_weekplan]</code>
                            <p class="description"><?php esc_html_e('Alternativ für Seiten ohne Block-Editor.', 'programmo'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /*  Settings page                                                      */
    /* ------------------------------------------------------------------ */

    public static function register_settings(): void
    {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [
            'sanitize_callback' => [self::class, 'sanitize_settings'],
        ]);

        add_settings_section(
            'programmo_general',
            __('Allgemeine Einstellungen', 'programmo'),
            '__return_false',
            self::SETTINGS_SLUG
        );

        add_settings_field(
            'valid_from',
            __('Gültig ab', 'programmo'),
            [self::class, 'render_field_valid_from'],
            self::SETTINGS_SLUG,
            'programmo_general'
        );

        add_settings_field(
            'default_area_color',
            __('Standard-Kachelfarbe (Offener Bereich)', 'programmo'),
            [self::class, 'render_field_default_area_color'],
            self::SETTINGS_SLUG,
            'programmo_general'
        );

        add_settings_field(
            'offer_gradient',
            __('Standard-Gradient (Angebotskachel)', 'programmo'),
            [self::class, 'render_field_offer_gradient'],
            self::SETTINGS_SLUG,
            'programmo_general'
        );

        add_settings_field(
            'weekdays_visible',
            __('Angezeigte Wochentage', 'programmo'),
            [self::class, 'render_field_weekdays_visible'],
            self::SETTINGS_SLUG,
            'programmo_general'
        );

        add_settings_field(
            'mobile_collapse',
            __('Mobile: Tage auf-/zuklappbar', 'programmo'),
            [self::class, 'render_field_mobile_collapse'],
            self::SETTINGS_SLUG,
            'programmo_general'
        );

        add_settings_field(
            'active_theme',
            __('Aktives Theme', 'programmo'),
            [self::class, 'render_field_active_theme'],
            self::SETTINGS_SLUG,
            'programmo_general'
        );
    }

    public static function sanitize_settings(array $input): array
    {
        $clean = [];
        $clean['valid_from'] = isset($input['valid_from']) ? sanitize_text_field((string) $input['valid_from']) : '';
        $clean['default_area_color'] = isset($input['default_area_color']) ? sanitize_hex_color((string) $input['default_area_color']) : '#bde9ff';
        $clean['offer_gradient'] = isset($input['offer_gradient']) ? sanitize_text_field((string) $input['offer_gradient']) : '';

        $all_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $visible = isset($input['weekdays_visible']) && is_array($input['weekdays_visible'])
            ? array_values(array_intersect($input['weekdays_visible'], $all_days))
            : $all_days;
        $clean['weekdays_visible'] = $visible;
        $clean['mobile_collapse'] = !empty($input['mobile_collapse']);

        $allowed_themes = self::get_supported_themes();
        $theme_val = isset($input['active_theme']) ? sanitize_text_field((string) $input['active_theme']) : '';
        $clean['active_theme'] = array_key_exists($theme_val, $allowed_themes) ? $theme_val : '';

        return $clean;
    }

    public static function get_option(string $key, $default = '')
    {
        $opts = (array) get_option(self::OPTION_KEY, []);
        return $opts[$key] ?? $default;
    }

    /* --- Field renderers --- */

    public static function render_field_valid_from(): void
    {
        $val = self::get_option('valid_from', '');
        echo '<input type="month" name="' . esc_attr(self::OPTION_KEY) . '[valid_from]" value="' . esc_attr($val) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__('z. B. „2026-01" für „gültig ab Januar 2026". Wird im Frontend als Hinweis angezeigt.', 'programmo') . '</p>';
    }

    public static function render_field_default_area_color(): void
    {
        $val = self::get_option('default_area_color', '#bde9ff');
        echo '<input type="color" name="' . esc_attr(self::OPTION_KEY) . '[default_area_color]" value="' . esc_attr($val) . '">';
    }

    public static function render_field_offer_gradient(): void
    {
        $val = self::get_option('offer_gradient', 'linear-gradient(90deg, #a12ed2, #ff4fa1)');
        echo '<input type="text" name="' . esc_attr(self::OPTION_KEY) . '[offer_gradient]" value="' . esc_attr($val) . '" class="large-text" placeholder="linear-gradient(90deg, #a12ed2, #ff4fa1)">';
        echo '<p class="description">' . esc_html__('CSS-Gradient für Angebotskacheln. Wird als background verwendet.', 'programmo') . '</p>';
    }

    public static function render_field_weekdays_visible(): void
    {
        $visible = (array) self::get_option('weekdays_visible', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
        $all = [
            'monday'    => __('Montag', 'programmo'),
            'tuesday'   => __('Dienstag', 'programmo'),
            'wednesday' => __('Mittwoch', 'programmo'),
            'thursday'  => __('Donnerstag', 'programmo'),
            'friday'    => __('Freitag', 'programmo'),
            'saturday'  => __('Samstag', 'programmo'),
            'sunday'    => __('Sonntag', 'programmo'),
        ];
        foreach ($all as $key => $label) {
            $checked = in_array($key, $visible, true) ? 'checked' : '';
            echo '<label style="margin-right:16px;"><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[weekdays_visible][]" value="' . esc_attr($key) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
        }
    }

    public static function render_field_mobile_collapse(): void
    {
        $enabled = (bool) self::get_option('mobile_collapse', true);
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_KEY) . '[mobile_collapse]" value="1" ' . checked($enabled, true, false) . '> ' . esc_html__('In der Mobile-Ansicht können Tagesbereiche per Klick ein- und ausgeklappt werden.', 'programmo') . '</label>';
        echo '<p class="description">' . esc_html__('Wenn aktiviert, wird auf kleinen Bildschirmen pro Wochentag ein Auf-/Zuklappen angeboten.', 'programmo') . '</p>';
    }

    /**
     * Return map of supported theme slugs.
     */
    public static function get_supported_themes(): array
    {
        return [
            ''       => __('Standard (kein spezielles Theme)', 'programmo'),
            'neve'   => 'Neve',
            'astra'  => 'Astra',
            'flavor' => 'flavor starter',
        ];
    }

    public static function render_field_active_theme(): void
    {
        $current = (string) self::get_option('active_theme', '');
        $themes  = self::get_supported_themes();
        echo '<select name="' . esc_attr(self::OPTION_KEY) . '[active_theme]">';
        foreach ($themes as $slug => $label) {
            echo '<option value="' . esc_attr($slug) . '"' . selected($current, $slug, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('ProgrammO passt die Ausgabe an das gewählte Theme an (z. B. werden bei Neve überflüssige Cover ausgeblendet).', 'programmo') . '</p>';
    }

    public static function render_settings(): void
    {
        ?>
        <div class="wrap programmo-dashboard">
            <h1><?php esc_html_e('ProgrammO — Einstellungen', 'programmo'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields(self::OPTION_KEY);
                do_settings_sections(self::SETTINGS_SLUG);
                submit_button(__('Einstellungen speichern', 'programmo'));
                ?>
            </form>
        </div>
        <?php
    }
}
