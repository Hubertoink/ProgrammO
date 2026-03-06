<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Shortcode
{
    public static function register(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_filter('style_loader_tag', [self::class, 'maybe_preload_frontend_style'], 10, 4);
        add_shortcode('programmo_weekplan', [self::class, 'render']);
    }

    /**
     * Load the frontend stylesheet in a non-blocking way on public pages.
     *
     * This keeps a <noscript> fallback for browsers with JavaScript disabled.
     */
    public static function maybe_preload_frontend_style(string $html, string $handle, string $href, string $media): string
    {
        if ($handle !== 'programmo-frontend' || is_admin()) {
            return $html;
        }

        $media_attr = $media !== '' ? " media=\"" . esc_attr($media) . "\"" : '';
        $href_attr = esc_url($href);

        return '<link rel="preload" as="style" id="programmo-frontend-css" href="' . $href_attr . '"' . $media_attr . ' onload="this.onload=null;this.rel=\'stylesheet\'" />'
            . "\n"
            . '<noscript><link rel="stylesheet" id="programmo-frontend-css-noscript" href="' . $href_attr . '"' . $media_attr . ' /></noscript>';
    }

    public static function register_assets(): void
    {
        wp_register_style('programmo-critical', PROGRAMMO_URL . 'assets/css/programmo-critical.css', [], PROGRAMMO_VERSION);
        wp_register_style('programmo-frontend', PROGRAMMO_URL . 'assets/css/programmo.css', [], PROGRAMMO_VERSION);
        wp_register_script('programmo-frontend', PROGRAMMO_URL . 'assets/js/programmo-frontend.js', [], PROGRAMMO_VERSION, true);
    }

    /**
     * Shortcode entry point — delegates to the shared render method.
     */
    public static function render(): string
    {
        wp_enqueue_style('programmo-critical');
        wp_enqueue_style('programmo-frontend');
        wp_enqueue_script('programmo-frontend');

        return self::render_plan([]);
    }

    /**
     * Shared rendering logic used by both the [programmo_weekplan] shortcode
     * and the programmo/weekplan Gutenberg block.
     *
     * Renders a Canva-style horizontal row layout: one row per weekday,
     * day label on the left, slots side-by-side to the right.
     */
    public static function render_plan(array $options = []): string
    {
        wp_enqueue_style('programmo-critical');
        wp_enqueue_style('programmo-frontend');
        wp_enqueue_script('programmo-frontend');

        $show_valid_from = (bool) ($options['show_valid_from'] ?? true);
        $show_team       = (bool) ($options['show_team'] ?? true);
        $show_badges     = (bool) ($options['show_badges'] ?? true);
        $show_details    = (bool) ($options['show_details'] ?? true);
        $columns         = (int) ($options['columns'] ?? 0);
        $compact_mode    = (bool) ($options['compact_mode'] ?? false);
        $mobile_collapse = (bool) ($options['mobile_collapse'] ?? Dashboard::get_option('mobile_collapse', true));
        $enable_pdf_export = (bool) ($options['enable_pdf_export'] ?? false);
        $show_grain      = (bool) ($options['show_grain'] ?? false);
        $show_tooltips   = (bool) ($options['show_tooltips'] ?? true);
        $title           = (string) ($options['title'] ?? '');
        $override_days   = (array) ($options['override_days'] ?? []);
        $back_url        = (string) ($options['back_url'] ?? '');

        // Resolve visible days: block override → global setting
        $visible_days = !empty($override_days)
            ? $override_days
            : (array) Dashboard::get_option('weekdays_visible', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);

        $default_area_color = (string) Dashboard::get_option('default_area_color', '#bde9ff');
        $offer_gradient = (string) Dashboard::get_option('offer_gradient', 'linear-gradient(90deg, #a12ed2, #ff4fa1)');
        $valid_from = (string) Dashboard::get_option('valid_from', '');
        $day_colors = (array) get_option(RestApi::DAY_COLORS_OPTION, []);

        $all_slots = get_posts([
            'post_type' => 'programmo_slot',
            'posts_per_page' => -1,
            'orderby' => 'meta_value',
            'meta_key' => '_programmo_start_time',
            'order' => 'ASC',
            'post_status' => ['publish', 'private'],
        ]);
        if (!$all_slots) {
            return '<div class="programmo-empty">' . esc_html__('Noch keine Slots vorhanden.', 'programmo') . '</div>';
        }

        // Group slots by weekday and collect render data
        $grouped = [];
        foreach ($all_slots as $slot) {
            $weekday = (string) get_post_meta($slot->ID, '_programmo_weekday', true);

            if (!empty($visible_days) && !in_array($weekday, $visible_days, true)) {
                continue;
            }

            $start     = (string) get_post_meta($slot->ID, '_programmo_start_time', true);
            $end       = (string) get_post_meta($slot->ID, '_programmo_end_time', true);
            $age_range = (string) get_post_meta($slot->ID, '_programmo_age_range', true);
            $age_range_color = (string) get_post_meta($slot->ID, '_programmo_age_range_color', true);
            $area_id   = (int) get_post_meta($slot->ID, '_programmo_area_id', true);

            $event_ids = RestApi::read_slot_event_ids($slot->ID);
            $event_time_overrides = RestApi::read_slot_event_times($slot->ID);

            $area_title  = '';
            $area_people = [];
            $area_people_colors = [];
            $area_color  = $default_area_color;

            if ($area_id > 0) {
                $area_post = get_post($area_id);
                if ($area_post instanceof \WP_Post) {
                    $area_title = $area_post->post_title;
                }
                $area_color_meta    = (string) get_post_meta($area_id, '_programmo_area_color', true);
                $area_gradient_meta = (string) get_post_meta($area_id, '_programmo_area_gradient', true);
                if ($area_gradient_meta !== '') {
                    $area_color = $area_gradient_meta;
                } elseif ($area_color_meta !== '') {
                    $area_color = $area_color_meta;
                }
                $people_ids = (array) get_post_meta($area_id, '_programmo_area_people', true);
                foreach ($people_ids as $person_id) {
                    $person_post = get_post((int) $person_id);
                    if ($person_post instanceof \WP_Post) {
                        $person_name = (string) $person_post->post_title;
                        $area_people[] = $person_name;
                        $person_color = self::resolve_person_badge_color($person_name, (int) $person_post->ID);
                        if ($person_color !== '') {
                            $area_people_colors[$person_name] = $person_color;
                        }
                    }
                }
            }

            // Slot-level Jugendarbeit taxonomy → additional people names
            $jugend_term_ids = (array) get_post_meta($slot->ID, '_programmo_tax_jugendarbeit', true);
            foreach ($jugend_term_ids as $tid) {
                $term = get_term((int) $tid, EventsBridge::OKJA_TAX_JUGEND);
                if ($term instanceof \WP_Term && !in_array($term->name, $area_people, true)) {
                    $area_people[] = $term->name;
                }
                if ($term instanceof \WP_Term) {
                    $term_color = self::resolve_person_badge_color($term->name, 0);
                    if ($term_color !== '') {
                        $area_people_colors[$term->name] = $term_color;
                    }
                }
            }

            $events_data = [];
            foreach ($event_ids as $event_id) {
                $event = get_post($event_id);
                if (!$event instanceof \WP_Post) {
                    continue;
                }
                $event_link = EventsBridge::get_event_link($event_id);
                if ($event_link !== '' && $back_url !== '') {
                    $event_link = add_query_arg('back', rawurlencode($back_url), $event_link);
                }
                $events_data[] = [
                    'id'                 => $event_id,
                    'title'              => $event->post_title,
                    'excerpt'            => wp_trim_words(wp_strip_all_tags((string) $event->post_content), 20),
                    'link'               => $event_link,
                    'badges'             => EventsBridge::get_term_badges_for_post($event_id),
                    'primary_angebot_id' => EventsBridge::get_primary_angebot_id($event_id),
                    'image_url'          => EventsBridge::get_event_thumbnail_url($event_id, 'large'),
                    'event_color'        => (string) get_post_meta($event_id, '_programmo_event_color', true),
                    'event_date'         => self::format_event_date($event_id),
                    'offer_start'        => (string) ($event_time_overrides[(string) $event_id]['start'] ?? $start),
                    'offer_end'          => (string) ($event_time_overrides[(string) $event_id]['end'] ?? $end),
                ];
            }

            $slot_data = [
                'slot_id'     => (int) $slot->ID,
                'weekday'     => $weekday,
                'start'       => $start,
                'end'         => $end,
                'age_range'       => $age_range,
                'age_range_color' => $age_range_color,
                'area_id'         => $area_id,
                'area_title'  => $area_title,
                'area_people' => $area_people,
                'area_people_colors' => $area_people_colors,
                'area_color'  => $area_color,
                'events'      => $events_data,
            ];
            $slot_data = apply_filters('programmo/slot/render_data', $slot_data, $slot);

            $grouped[$slot_data['weekday']][] = $slot_data;
        }

        if (empty($grouped)) {
            return '<div class="programmo-empty">' . esc_html__('Keine sichtbaren Slots.', 'programmo') . '</div>';
        }

        // Ensure consistent weekday order
        $ordered_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        ob_start();

        $wrapper_class = 'programmo-weekplan';
        if ($compact_mode) {
            $wrapper_class .= ' programmo-weekplan--compact';
        }
        if ($mobile_collapse) {
            $wrapper_class .= ' programmo-weekplan--mobile-collapse';
        }
        if ($show_grain) {
            $wrapper_class .= ' programmo-weekplan--grain';
        }
        if ($show_tooltips) {
            $wrapper_class .= ' programmo-weekplan--tooltips';
        }
        $active_theme = (string) Dashboard::get_option('active_theme', '');
        if ($active_theme !== '') {
            $wrapper_class .= ' programmo-theme-' . sanitize_html_class($active_theme);
        }
        echo '<div class="' . esc_attr($wrapper_class) . '">';

        // Title + valid-from header
        $has_header = ($title !== '') || ($show_valid_from && $valid_from !== '');
        if ($has_header) {
            echo '<div class="programmo-weekplan__header">';
            if ($title !== '') {
                echo '<h2 class="programmo-weekplan__title">' . esc_html($title) . '</h2>';
            }
            if ($show_valid_from && $valid_from !== '') {
                $ts = strtotime($valid_from . '-01');
                if ($ts) {
                    $formatted = wp_date('F Y', $ts);
                    echo '<span class="programmo-weekplan__valid">📌 ' . esc_html(sprintf(__('gültig ab %s', 'programmo'), (string) $formatted)) . '</span>';
                }
            }
            echo '</div>';
        }

        if ($enable_pdf_export) {
            $filename_source = $title !== '' ? $title : __('Wochenplan', 'programmo');
            $filename = sanitize_title($filename_source);
            if ($filename === '') {
                $filename = 'wochenplan';
            }
            echo '<button type="button" class="programmo-weekplan__pdf-export" data-programmo-export-pdf="1" data-programmo-export-file="' . esc_attr($filename) . '" aria-label="' . esc_attr__('Wochenplan als PDF exportieren', 'programmo') . '" title="' . esc_attr__('Als PDF exportieren', 'programmo') . '">';
            echo '<svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
            echo '</button>';
        }

        // Rows per weekday
        $row_index = 0;
        foreach ($ordered_days as $day_key) {
            if (!isset($grouped[$day_key])) {
                continue;
            }

            $day_slots = $grouped[$day_key];
            $day_label = strtoupper(self::weekday_label($day_key));
            $max_offers_in_day = 0;
            foreach ($day_slots as $slot_data) {
                $slot_events = is_array($slot_data['events'] ?? null) ? $slot_data['events'] : [];
                $max_offers_in_day = max($max_offers_in_day, count($slot_events));
            }
            $weekrow_classes = ['programmo-weekrow'];
            if ($max_offers_in_day >= 3) {
                $weekrow_classes[] = 'programmo-weekrow--offers-3plus';
            }
            if ($max_offers_in_day >= 5) {
                $weekrow_classes[] = 'programmo-weekrow--offers-5plus';
            }

            $row_index++;
            $slots_id = 'programmo-day-' . sanitize_html_class($day_key) . '-' . $row_index;
            echo '<div class="' . esc_attr(implode(' ', $weekrow_classes)) . '" data-day="' . esc_attr($day_key) . '">';

            // Day base tile (lowest layer)
            $day_bg = isset($day_colors[$day_key]) && $day_colors[$day_key] !== '' ? $day_colors[$day_key] : '';
            $day_style = $day_bg !== '' ? ' style="background:' . esc_attr($day_bg) . '"' : '';
            echo '<div class="programmo-weekrow__day"' . $day_style . '>';
            if ($mobile_collapse) {
                echo '<button type="button" class="programmo-weekrow__toggle" aria-expanded="false" aria-controls="' . esc_attr($slots_id) . '">';
                echo '<span>' . esc_html($day_label) . '</span>';
                echo '<span class="programmo-weekrow__chevron" aria-hidden="true">▾</span>';
                echo '</button>';
            } else {
                echo '<span>' . esc_html($day_label) . '</span>';
            }
            echo '</div>';

            // Slots container (higher layer, inset on day base)
            echo '<div class="programmo-weekrow__slots" id="' . esc_attr($slots_id) . '">';

            foreach ($day_slots as $sd) {
                $start           = (string) ($sd['start'] ?? '');
                $end             = (string) ($sd['end'] ?? '');
                $age_range       = (string) ($sd['age_range'] ?? '');
                $age_range_color = (string) ($sd['age_range_color'] ?? '');
                $area_title  = (string) ($sd['area_title'] ?? '');
                $area_people = is_array($sd['area_people'] ?? null) ? $sd['area_people'] : [];
                $area_people_colors = is_array($sd['area_people_colors'] ?? null) ? $sd['area_people_colors'] : [];
                $area_color  = (string) ($sd['area_color'] ?? $default_area_color);
                $events      = is_array($sd['events'] ?? null) ? $sd['events'] : [];
                $area_label  = $area_title ?: __('Offener Bereich', 'programmo');

                $offer_count = count($events);
                $slot_classes = ['programmo-slot'];
                if ($offer_count >= 3) {
                    $slot_classes[] = 'programmo-slot--offers-3plus';
                }
                if ($offer_count >= 5) {
                    $slot_classes[] = 'programmo-slot--offers-5plus';
                }
                echo '<div class="' . esc_attr(implode(' ', $slot_classes)) . '" style="background:' . esc_attr($area_color) . '">';

                echo '<div class="programmo-slot__area">';
                echo '<span>' . esc_html(strtoupper($area_label)) . '</span>';
                echo '</div>';

                // Header: people badges + time + age range
                echo '<div class="programmo-slot__header">';
                echo '<span class="programmo-slot__area-mobile">' . esc_html($area_label) . '</span>';
                if ($show_team && !empty($area_people)) {
                    echo '<div class="programmo-slot__people">';
                    foreach ($area_people as $person_name) {
                        $parts = explode(' ', $person_name, 2);
                        $person_color = self::normalize_badge_color((string) ($area_people_colors[$person_name] ?? ''));
                        $badge_style = $person_color !== '' ? ' style="background:' . esc_attr($person_color) . ';color:#ffffff"' : '';
                        echo '<span class="programmo-person-badge"' . $badge_style . '>';
                        echo '<span>' . esc_html($parts[0]) . '</span>';
                        if (isset($parts[1])) {
                            echo '<span>' . esc_html($parts[1]) . '</span>';
                        }
                        echo '</span>';
                    }
                    echo '</div>';
                }
                $time_str = $start . ' – ' . $end . ' Uhr';
                echo '<div class="programmo-slot__time">' . esc_html($time_str) . '</div>';
                if ($age_range !== '') {
                    $age_style = $age_range_color !== '' ? ' style="color:' . esc_attr($age_range_color) . '"' : '';
                    echo '<div class="programmo-slot__age"' . $age_style . '>' . esc_html('(' . $age_range . ')') . '</div>';
                }
                echo '</div>';

                // Offers (rectangular tiles)
                if (!empty($events)) {
                    echo '<div class="programmo-slot__offers">';
                    foreach ($events as $ev) {
                        $ev_title = (string) ($ev['title'] ?? '');
                        $ev_link  = (string) ($ev['link'] ?? '');
                        $ev_excerpt = (string) ($ev['excerpt'] ?? '');
                        $ev_image = (string) ($ev['image_url'] ?? '');
                        $ev_date  = (string) ($ev['event_date'] ?? '');
                        $ev_time_start = (string) ($ev['offer_start'] ?? $start);
                        $ev_time_end = (string) ($ev['offer_end'] ?? $end);
                        $ev_time_range = self::build_time_range($ev_time_start, $ev_time_end);
                        $ev_time_str = $ev_time_range !== '' ? ($ev_time_range . ' Uhr') : '';
                        $ev_badges = is_array($ev['badges'] ?? null) ? $ev['badges'] : [];
                        $ev_badges_json = wp_json_encode($ev_badges);

                        if ($ev_title === '') {
                            continue;
                        }

                        $tag  = $ev_link !== '' ? 'a' : 'button';
                        $href = $ev_link !== '' ? ' href="' . esc_url($ev_link) . '"' : ' type="button"';
                        $ev_bg = !empty($ev['event_color']) ? $ev['event_color'] : $offer_gradient;
                        echo '<' . $tag . $href
                            . ' class="programmo-slot__offer"'
                            . ' data-programmo-offer-trigger="1"'
                            . ' data-programmo-offer-title="' . esc_attr($ev_title) . '"'
                            . ' data-programmo-offer-excerpt="' . esc_attr($ev_excerpt) . '"'
                            . ' data-programmo-offer-link="' . esc_url($ev_link) . '"'
                            . ' data-programmo-offer-image="' . esc_url($ev_image) . '"'
                            . ' data-programmo-offer-badges="' . esc_attr($ev_badges_json) . '"'
                            . ' data-programmo-offer-time="' . esc_attr($ev_time_str) . '"'
                            . ' data-programmo-offer-date="' . esc_attr($ev_date) . '"'
                            . ' style="background:' . esc_attr($ev_bg) . '">';
                        echo esc_html($ev_title);
                        echo '</' . $tag . '>';
                    }
                    echo '</div>';
                }

                echo '</div>'; // .programmo-slot
            }

            echo '</div>'; // .programmo-weekrow__slots

            echo '</div>'; // .programmo-weekrow
        }

        echo '</div>'; // .programmo-weekplan
        return (string) ob_get_clean();
    }

    private static function weekday_label(string $weekday): string
    {
        $labels = [
            'monday' => __('Montag', 'programmo'),
            'tuesday' => __('Dienstag', 'programmo'),
            'wednesday' => __('Mittwoch', 'programmo'),
            'thursday' => __('Donnerstag', 'programmo'),
            'friday' => __('Freitag', 'programmo'),
            'saturday' => __('Samstag', 'programmo'),
            'sunday' => __('Sonntag', 'programmo'),
        ];

        return $labels[$weekday] ?? __('Unbekannt', 'programmo');
    }

    private static function build_time_range(string $start, string $end): string
    {
        if ($start === '' && $end === '') {
            return '';
        }
        if ($start === '') {
            return $end;
        }
        if ($end === '') {
            return $start;
        }
        return $start . ' – ' . $end;
    }

    private static function resolve_person_badge_color(string $person_name, int $fallback_post_id = 0): string
    {
        $name = trim($person_name);
        if ($name === '') {
            return '';
        }

        $candidate_ids = [];
        if ($fallback_post_id > 0) {
            $candidate_ids[] = $fallback_post_id;
        }

        $programmo_people = get_posts([
            'post_type'      => 'programmo_person',
            'title'          => $name,
            'posts_per_page' => 1,
            'post_status'    => ['publish', 'draft', 'private'],
            'fields'         => 'ids',
        ]);
        if (!empty($programmo_people)) {
            $candidate_ids[] = (int) $programmo_people[0];
        }

        $okja_people = get_posts([
            'post_type'      => 'person',
            'title'          => $name,
            'posts_per_page' => 1,
            'post_status'    => ['publish', 'draft', 'private'],
            'fields'         => 'ids',
        ]);
        if (!empty($okja_people)) {
            $candidate_ids[] = (int) $okja_people[0];
        }

        $candidate_ids = array_values(array_unique(array_filter(array_map('absint', $candidate_ids))));

        foreach ($candidate_ids as $pid) {
            $meta_candidates = [
                (string) get_post_meta($pid, '_person_color', true),
                (string) get_post_meta($pid, '_programmo_person_color', true),
            ];
            foreach ($meta_candidates as $raw_color) {
                $normalized = self::normalize_badge_color($raw_color);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return '';
    }

    private static function normalize_badge_color(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        $hex = sanitize_hex_color($value);
        if (is_string($hex) && $hex !== '') {
            return $hex;
        }

        if (preg_match('/^rgba?\([0-9.,\s%]+\)$/i', $value)) {
            return $value;
        }

        if (preg_match('/^hsla?\([0-9.,\s%]+\)$/i', $value)) {
            return $value;
        }

        return '';
    }

    /**
     * Format the event date for display in the modal.
     * Tries _event_date on the post itself; for angebotsevent falls back
     * via the primary angebot.
     */
    private static function format_event_date(int $event_id): string
    {
        $date_raw = (string) get_post_meta($event_id, '_event_date', true);

        if ($date_raw === '') {
            // For angebotsevent: try the linked primary angebot
            $primary = EventsBridge::get_primary_angebot_id($event_id);
            if ($primary > 0 && $primary !== $event_id) {
                $date_raw = (string) get_post_meta($primary, '_event_date', true);
            }
        }

        if ($date_raw === '') {
            return '';
        }

        $date_obj = \DateTime::createFromFormat('Y-m-d', $date_raw);
        if (!$date_obj) {
            return $date_raw;
        }

        $months = [
            'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
        ];
        $days = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

        return $days[(int) $date_obj->format('w')]
            . ', ' . $date_obj->format('j') . '. '
            . $months[(int) $date_obj->format('n') - 1]
            . ' ' . $date_obj->format('Y');
    }
}
