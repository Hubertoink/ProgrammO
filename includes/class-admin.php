<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'register_meta_boxes']);
        add_action('save_post_programmo_area', [self::class, 'save_area_meta']);
        add_action('save_post_programmo_offer', [self::class, 'save_offer_meta']);
        add_action('save_post_programmo_slot', [self::class, 'save_slot_meta']);
        add_action('admin_notices', [self::class, 'maybe_render_slot_validation_notice']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_color_picker']);
    }

    public static function enqueue_color_picker(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        if (!function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'programmo_area') {
            return;
        }
        wp_enqueue_style('programmo-admin', PROGRAMMO_URL . 'assets/css/admin.css', [], PROGRAMMO_VERSION);
        wp_enqueue_script(
            'programmo-area-colorpicker',
            PROGRAMMO_URL . 'assets/js/programmo-area-colorpicker.js',
            [],
            PROGRAMMO_VERSION,
            true
        );
    }

    public static function register_meta_boxes(): void
    {
        add_meta_box(
            'programmo_area_meta',
            __('Offener Bereich — Darstellung & Team', 'programmo'),
            [self::class, 'render_area_meta_box'],
            'programmo_area',
            'normal',
            'high'
        );

        add_meta_box(
            'programmo_slot_meta',
            __('Wochenplan-Slot — Zeitfenster, Bereich & Angebot', 'programmo'),
            [self::class, 'render_slot_meta_box'],
            'programmo_slot',
            'normal',
            'high'
        );

        add_meta_box(
            'programmo_offer_meta',
            __('Angebot — Beschreibung & Personen', 'programmo'),
            [self::class, 'render_offer_meta_box'],
            'programmo_offer',
            'normal',
            'high'
        );
    }

    public static function render_area_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('programmo_area_meta', 'programmo_area_meta_nonce');

        $color = (string) get_post_meta($post->ID, '_programmo_area_color', true);
        $gradient = (string) get_post_meta($post->ID, '_programmo_area_gradient', true);
        $assigned_people = (array) get_post_meta($post->ID, '_programmo_area_people', true);

        $people = get_posts([
            'post_type' => 'programmo_person',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <p class="description" style="margin-bottom:12px;">
            <?php esc_html_e('Ein Offener Bereich ist das Zeitfenster, in dem das Jugendhaus geöffnet ist (hellblaue Kachel im Wochenplan). Hier legst du Farbe und zuständiges Team fest.', 'programmo'); ?>
        </p>

        <div id="programmo-area-colorpicker"
             data-color="<?php echo esc_attr($color ?: '#90d7f3'); ?>"
             data-gradient="<?php echo esc_attr($gradient); ?>">
        </div>
        <input type="hidden" name="programmo_area_color" id="programmo_area_color_hidden" value="<?php echo esc_attr($color ?: '#90d7f3'); ?>">
        <input type="hidden" name="programmo_area_gradient" id="programmo_area_gradient_hidden" value="<?php echo esc_attr($gradient); ?>">

        <p style="margin-top:16px;">
            <strong><?php esc_html_e('Zugeordnete Personen (Betreuungsteam)', 'programmo'); ?></strong>
        </p>
        <select name="programmo_area_people[]" multiple style="width:100%;min-height:140px;">
            <?php foreach ($people as $person) : ?>
                <option value="<?php echo esc_attr((string) $person->ID); ?>" <?php selected(in_array((string) $person->ID, array_map('strval', $assigned_people), true)); ?>>
                    <?php echo esc_html($person->post_title); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Mehrfachauswahl möglich (Strg/Cmd).', 'programmo'); ?></p>
        <?php
    }

    public static function render_slot_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('programmo_slot_meta', 'programmo_slot_meta_nonce');

        $weekday = (string) get_post_meta($post->ID, '_programmo_weekday', true);
        $start = (string) get_post_meta($post->ID, '_programmo_start_time', true);
        $end = (string) get_post_meta($post->ID, '_programmo_end_time', true);
        $area_id = (int) get_post_meta($post->ID, '_programmo_area_id', true);
        $age_range = (string) get_post_meta($post->ID, '_programmo_age_range', true);
        $age_range_color = (string) get_post_meta($post->ID, '_programmo_age_range_color', true);

        $jugend_terms_selected = (array) get_post_meta($post->ID, '_programmo_tax_jugendarbeit', true);
        $paed_terms_selected = (array) get_post_meta($post->ID, '_programmo_tax_paedagogik', true);

        $areas = get_posts([
            'post_type' => 'programmo_area',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $jugend_terms = EventsBridge::get_terms_for_taxonomy(EventsBridge::OKJA_TAX_JUGEND);
        $paed_terms = EventsBridge::get_terms_for_taxonomy(EventsBridge::OKJA_TAX_PAED);
        ?>
        <p>
            <label for="programmo_weekday"><strong><?php esc_html_e('Wochentag', 'programmo'); ?></strong></label><br>
            <select name="programmo_weekday" id="programmo_weekday">
                <?php foreach (self::weekday_options() as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($weekday, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="programmo_start_time"><strong><?php esc_html_e('Startzeit', 'programmo'); ?></strong></label><br>
            <input type="time" id="programmo_start_time" name="programmo_start_time" value="<?php echo esc_attr($start); ?>">
            <label for="programmo_end_time" style="margin-left:10px;"><strong><?php esc_html_e('Endzeit', 'programmo'); ?></strong></label>
            <input type="time" id="programmo_end_time" name="programmo_end_time" value="<?php echo esc_attr($end); ?>">
        </p>
        <p>
            <label for="programmo_age_range"><strong><?php esc_html_e('Altersspanne', 'programmo'); ?></strong></label><br>
            <input type="text" id="programmo_age_range" name="programmo_age_range" value="<?php echo esc_attr($age_range); ?>" placeholder="z.B. ab 6 Jahre" style="min-width:200px;">
            <span class="description"><?php esc_html_e('Wird im Wochenplan neben der Uhrzeit angezeigt.', 'programmo'); ?></span>
        </p>
        <p>
            <label for="programmo_age_range_color"><strong><?php esc_html_e('Schriftfarbe Altersspanne', 'programmo'); ?></strong></label><br>
            <input type="color" id="programmo_age_range_color" name="programmo_age_range_color" value="<?php echo esc_attr($age_range_color ?: '#0a1520'); ?>" style="width:50px;height:34px;vertical-align:middle;cursor:pointer;">
            <span class="description"><?php esc_html_e('Farbe des Altersspanne-Texts im Wochenplan.', 'programmo'); ?></span>
        </p>
        <p>
            <label for="programmo_area_id"><strong><?php esc_html_e('Offener Bereich', 'programmo'); ?></strong></label><br>
            <select id="programmo_area_id" name="programmo_area_id" style="min-width:280px;">
                <option value="0"><?php esc_html_e('— Ohne Bereich —', 'programmo'); ?></option>
                <?php foreach ($areas as $area) : ?>
                    <option value="<?php echo esc_attr((string) $area->ID); ?>" <?php selected($area_id, (int) $area->ID); ?>>
                        <?php echo esc_html($area->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <strong><?php esc_html_e('Taxonomie-Filter (OKJA Angebote)', 'programmo'); ?></strong>
        </p>
        <p>
            <label for="programmo_tax_jugendarbeit"><strong><?php esc_html_e('Jugendarbeit', 'programmo'); ?></strong></label><br>
            <select id="programmo_tax_jugendarbeit" name="programmo_tax_jugendarbeit[]" multiple style="width:100%;min-height:100px;">
                <?php foreach ($jugend_terms as $term_id => $term_label) : ?>
                    <option value="<?php echo esc_attr((string) $term_id); ?>" <?php selected(in_array((string) $term_id, array_map('strval', $jugend_terms_selected), true)); ?>>
                        <?php echo esc_html($term_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="programmo_tax_paedagogik"><strong><?php esc_html_e('Pädagogik', 'programmo'); ?></strong></label><br>
            <select id="programmo_tax_paedagogik" name="programmo_tax_paedagogik[]" multiple style="width:100%;min-height:100px;">
                <?php foreach ($paed_terms as $term_id => $term_label) : ?>
                    <option value="<?php echo esc_attr((string) $term_id); ?>" <?php selected(in_array((string) $term_id, array_map('strval', $paed_terms_selected), true)); ?>>
                        <?php echo esc_html($term_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <!-- Angebote werden jetzt im Dashboard per Drag & Drop zugeordnet -->
        <p class="description"><?php esc_html_e('Angebote werden im interaktiven Dashboard per Drag & Drop zugeordnet.', 'programmo'); ?></p>
        <?php
    }

    public static function render_offer_meta_box(\WP_Post $post): void
    {
        wp_nonce_field('programmo_offer_meta', 'programmo_offer_meta_nonce');

        $people_by_source = EventsBridge::get_person_choices_by_source();
        $programmo_selected = EventsBridge::get_local_offer_person_ids($post->ID, 'programmo');
        $okja_selected = EventsBridge::get_local_offer_person_ids($post->ID, 'okja');
        ?>
        <p class="description" style="margin-bottom:12px;">
            <?php esc_html_e('Dieses Angebot bleibt innerhalb von ProgrammO und verlinkt nicht auf eine eigene Detailseite. Die ausführliche Beschreibung kommt aus dem Editor oberhalb.', 'programmo'); ?>
        </p>

        <p>
            <strong><?php esc_html_e('ProgrammO-Personen', 'programmo'); ?></strong>
        </p>
        <select name="programmo_offer_programmo_people[]" multiple style="width:100%;min-height:120px;">
            <?php foreach (($people_by_source['programmo'] ?? []) as $person) : ?>
                <option value="<?php echo esc_attr((string) ($person['id'] ?? 0)); ?>" <?php selected(in_array((int) ($person['id'] ?? 0), $programmo_selected, true)); ?>>
                    <?php echo esc_html((string) ($person['label'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Eigene ProgrammO-Personen, die im Angebots-Modal als Personen-Badges erscheinen.', 'programmo'); ?></p>

        <p>
            <strong><?php esc_html_e('OKJA Personenpool', 'programmo'); ?></strong>
        </p>
        <select name="programmo_offer_okja_people[]" multiple style="width:100%;min-height:120px;">
            <?php foreach (($people_by_source['okja'] ?? []) as $person) : ?>
                <option value="<?php echo esc_attr((string) ($person['id'] ?? 0)); ?>" <?php selected(in_array((int) ($person['id'] ?? 0), $okja_selected, true)); ?>>
                    <?php echo esc_html((string) ($person['label'] ?? '')); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php esc_html_e('Optional können zusätzlich Personen aus dem OKJA-Personenpool genutzt werden.', 'programmo'); ?></p>
        <?php
    }

    public static function save_area_meta(int $post_id): void
    {
        if (!self::can_save($post_id, 'programmo_area_meta_nonce', 'programmo_area_meta')) {
            return;
        }

        $color = isset($_POST['programmo_area_color']) ? sanitize_text_field((string) wp_unslash($_POST['programmo_area_color'])) : '';
        $gradient = isset($_POST['programmo_area_gradient']) ? sanitize_text_field((string) wp_unslash($_POST['programmo_area_gradient'])) : '';
        update_post_meta($post_id, '_programmo_area_color', $color);
        update_post_meta($post_id, '_programmo_area_gradient', $gradient);

        $people = isset($_POST['programmo_area_people']) ? (array) $_POST['programmo_area_people'] : [];
        $people = array_values(array_filter(array_map('absint', $people)));
        update_post_meta($post_id, '_programmo_area_people', $people);
    }

    public static function save_offer_meta(int $post_id): void
    {
        if (!self::can_save($post_id, 'programmo_offer_meta_nonce', 'programmo_offer_meta')) {
            return;
        }

        $programmo_people = isset($_POST['programmo_offer_programmo_people']) ? (array) $_POST['programmo_offer_programmo_people'] : [];
        $okja_people = isset($_POST['programmo_offer_okja_people']) ? (array) $_POST['programmo_offer_okja_people'] : [];

        EventsBridge::save_local_offer_people(
            $post_id,
            array_values(array_filter(array_map('absint', $programmo_people))),
            array_values(array_filter(array_map('absint', $okja_people)))
        );
    }

    public static function save_slot_meta(int $post_id): void
    {
        if (!self::can_save($post_id, 'programmo_slot_meta_nonce', 'programmo_slot_meta')) {
            return;
        }

        $weekday = isset($_POST['programmo_weekday']) ? sanitize_text_field((string) wp_unslash($_POST['programmo_weekday'])) : '';
        $start = isset($_POST['programmo_start_time']) ? sanitize_text_field((string) wp_unslash($_POST['programmo_start_time'])) : '';
        $end = isset($_POST['programmo_end_time']) ? sanitize_text_field((string) wp_unslash($_POST['programmo_end_time'])) : '';
        $area_id = isset($_POST['programmo_area_id']) ? absint($_POST['programmo_area_id']) : 0;
        $age_range = isset($_POST['programmo_age_range']) ? sanitize_text_field((string) wp_unslash($_POST['programmo_age_range'])) : '';
        $age_range_color = isset($_POST['programmo_age_range_color']) ? sanitize_hex_color((string) wp_unslash($_POST['programmo_age_range_color'])) : '';
        $tax_jugend = isset($_POST['programmo_tax_jugendarbeit']) ? (array) $_POST['programmo_tax_jugendarbeit'] : [];
        $tax_paed = isset($_POST['programmo_tax_paedagogik']) ? (array) $_POST['programmo_tax_paedagogik'] : [];

        // Events are now managed via Dashboard drag & drop — do NOT overwrite here
        // $event_ids = isset($_POST['programmo_event_ids']) ? … : [];

        update_post_meta($post_id, '_programmo_weekday', $weekday);
        update_post_meta($post_id, '_programmo_start_time', $start);
        update_post_meta($post_id, '_programmo_end_time', $end);
        update_post_meta($post_id, '_programmo_area_id', $area_id);
        update_post_meta($post_id, '_programmo_age_range', $age_range);
        update_post_meta($post_id, '_programmo_age_range_color', (string) $age_range_color);
        // _programmo_event_ids is managed via REST API / Dashboard only
        update_post_meta($post_id, '_programmo_tax_jugendarbeit', array_values(array_filter(array_map('absint', $tax_jugend))));
        update_post_meta($post_id, '_programmo_tax_paedagogik', array_values(array_filter(array_map('absint', $tax_paed))));
    }

    private static function can_save(int $post_id, string $nonce_key, string $action): bool
    {
        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return false;
        }

        if (!isset($_POST[$nonce_key])) {
            return false;
        }

        $nonce = sanitize_text_field((string) wp_unslash($_POST[$nonce_key]));
        return wp_verify_nonce($nonce, $action) === 1;
    }

    public static function maybe_render_slot_validation_notice(): void
    {
        if (!is_admin()) {
            return;
        }

        if (!function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'programmo_slot') {
            return;
        }

        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($post_id <= 0) {
            return;
        }

        $event_ids = RestApi::read_slot_event_ids($post_id);
        $warnings = [];
        foreach ($event_ids as $eid) {
            if (self::is_angebotsevent_without_primary($eid)) {
                $warnings[] = get_the_title($eid);
            }
        }

        if (empty($warnings)) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html(sprintf(
            __('ProgrammO: Folgende A-Events haben keine Verknüpfung zu einem Angebot (jhh_event_angebot_id fehlt): %s', 'programmo'),
            implode(', ', $warnings)
        ));
        echo '</p></div>';
    }

    private static function is_angebotsevent_without_primary(int $event_id): bool
    {
        $post = get_post($event_id);
        if (!$post instanceof \WP_Post) {
            return false;
        }

        if ($post->post_type !== EventsBridge::OKJA_POST_TYPE_EVENT) {
            return false;
        }

        return EventsBridge::get_primary_angebot_id($event_id) <= 0;
    }

    private static function weekday_options(): array
    {
        return [
            'monday' => __('Montag', 'programmo'),
            'tuesday' => __('Dienstag', 'programmo'),
            'wednesday' => __('Mittwoch', 'programmo'),
            'thursday' => __('Donnerstag', 'programmo'),
            'friday' => __('Freitag', 'programmo'),
            'saturday' => __('Samstag', 'programmo'),
            'sunday' => __('Sonntag', 'programmo'),
        ];
    }
}
