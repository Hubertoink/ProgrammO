<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class EventsBridge
{
    public const PROGRAMMO_POST_TYPE_OFFER = 'programmo_offer';
    public const PROGRAMMO_POST_TYPE_PERSON = 'programmo_person';
    public const OKJA_POST_TYPE_ANGEBOT = 'angebot';
    public const OKJA_POST_TYPE_EVENT = 'angebotsevent';
    public const OKJA_POST_TYPE_PERSON = 'person';

    public const OKJA_TAX_JUGEND = 'jugendarbeit';
    public const OKJA_TAX_PAED = 'paedagogik';
    public const OKJA_TAX_TAGE = 'tage';

    public static function register(): void
    {
        add_action('admin_notices', [self::class, 'maybe_render_admin_notice']);
    }

    public static function maybe_render_admin_notice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (self::is_available()) {
            return;
        }

        echo '<div class="notice notice-warning"><p>';
        echo esc_html__('ProgrammO: Events_OKJA wurde nicht erkannt. Eigene ProgrammO-Angebote funktionieren weiterhin, externe Angebots-Zuordnung bleibt optional und ohne Single-Link.', 'programmo');
        echo '</p></div>';
    }

    public static function is_available(): bool
    {
        return self::get_event_post_type() !== null;
    }

    public static function get_event_post_type(): ?string
    {
        $filtered = apply_filters('programmo/events/post_type', null);
        if (is_string($filtered) && post_type_exists($filtered)) {
            return $filtered;
        }

        if (post_type_exists(self::OKJA_POST_TYPE_ANGEBOT)) {
            return self::OKJA_POST_TYPE_ANGEBOT;
        }

        if (post_type_exists(self::OKJA_POST_TYPE_EVENT)) {
            return self::OKJA_POST_TYPE_EVENT;
        }

        return null;
    }

    public static function query_event_choices(array $filters = []): array
    {
        $local_choices = self::query_programmo_offer_choices();

        if (!post_type_exists(self::OKJA_POST_TYPE_ANGEBOT) && !post_type_exists(self::OKJA_POST_TYPE_EVENT)) {
            return $local_choices;
        }

        $angebot_choices = self::query_angebot_choices($filters);
        $event_choices = self::query_angebotsevent_choices($filters, array_keys($angebot_choices));

        return $local_choices + $angebot_choices + $event_choices;
    }

    private static function query_programmo_offer_choices(): array
    {
        if (!post_type_exists(self::PROGRAMMO_POST_TYPE_OFFER)) {
            return [];
        }

        $offers = get_posts([
            'post_type'      => self::PROGRAMMO_POST_TYPE_OFFER,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => ['publish', 'draft', 'future', 'private'],
        ]);

        $choices = [];
        foreach ($offers as $offer) {
            $choices[(int) $offer->ID] = sprintf(__('ProgrammO: %s', 'programmo'), $offer->post_title);
        }

        return $choices;
    }

    private static function query_angebot_choices(array $filters = []): array
    {
        if (!post_type_exists(self::OKJA_POST_TYPE_ANGEBOT)) {
            return [];
        }

        $query_args = [
            'post_type' => self::OKJA_POST_TYPE_ANGEBOT,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'future', 'private'],
        ];

        $tax_query = [];
        foreach (self::supported_taxonomies() as $taxonomy => $label) {
            if (!empty($filters[$taxonomy]) && taxonomy_exists($taxonomy)) {
                $term_ids = array_values(array_filter(array_map('absint', (array) $filters[$taxonomy])));
                if (!empty($term_ids)) {
                    $tax_query[] = [
                        'taxonomy' => $taxonomy,
                        'field' => 'term_id',
                        'terms' => $term_ids,
                    ];
                }
            }
        }
        if (!empty($tax_query)) {
            $query_args['tax_query'] = array_merge(['relation' => 'AND'], $tax_query);
        }

        $query_args = apply_filters('programmo/events/query_args', $query_args, self::OKJA_POST_TYPE_ANGEBOT);
        $events = get_posts($query_args);

        $choices = [];
        foreach ($events as $event) {
            $choices[(int) $event->ID] = sprintf(__('Angebot: %s', 'programmo'), $event->post_title);
        }

        return $choices;
    }

    private static function query_angebotsevent_choices(array $filters = [], array $angebot_ids = []): array
    {
        if (!post_type_exists(self::OKJA_POST_TYPE_EVENT)) {
            return [];
        }

        $query_args = [
            'post_type' => self::OKJA_POST_TYPE_EVENT,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'future', 'private'],
        ];

        $has_tax_filters = !empty($filters[self::OKJA_TAX_JUGEND]) || !empty($filters[self::OKJA_TAX_PAED]);
        if ($has_tax_filters) {
            if (!empty($angebot_ids)) {
                $query_args['meta_query'] = [
                    [
                        'key' => 'jhh_event_angebot_id',
                        'value' => array_values(array_map('absint', $angebot_ids)),
                        'compare' => 'IN',
                    ],
                ];
            } else {
                return [];
            }
        }

        $query_args = apply_filters('programmo/events/query_args', $query_args, self::OKJA_POST_TYPE_EVENT);
        $events = get_posts($query_args);

        $choices = [];
        foreach ($events as $event) {
            $choices[(int) $event->ID] = sprintf(__('A-Event: %s', 'programmo'), $event->post_title);
        }

        return $choices;
    }

    public static function get_event_link(int $event_id): string
    {
        if ($event_id <= 0 || get_post($event_id) === null) {
            return '';
        }

        $post = get_post($event_id);
        if ($post instanceof \WP_Post && $post->post_type === self::PROGRAMMO_POST_TYPE_OFFER) {
            return '';
        }

        $primary_offer_id = self::get_primary_angebot_id($event_id);
        if ($primary_offer_id > 0) {
            $url = get_permalink($primary_offer_id);
        } else {
            $url = get_permalink($event_id);
        }

        $url = is_string($url) ? $url : '';

        return (string) apply_filters('programmo/events/link', $url, $event_id);
    }

    public static function get_primary_angebot_id(int $selected_id): int
    {
        if ($selected_id <= 0) {
            return 0;
        }

        $post = get_post($selected_id);
        if (!$post instanceof \WP_Post) {
            return 0;
        }

        if ($post->post_type === self::PROGRAMMO_POST_TYPE_OFFER) {
            return 0;
        }

        if ($post->post_type === self::OKJA_POST_TYPE_ANGEBOT) {
            return (int) $post->ID;
        }

        if ($post->post_type === self::OKJA_POST_TYPE_EVENT) {
            return (int) get_post_meta($post->ID, 'jhh_event_angebot_id', true);
        }

        return 0;
    }

    public static function supported_taxonomies(): array
    {
        $tax = [
            self::OKJA_TAX_JUGEND => __('Jugendarbeit', 'programmo'),
            self::OKJA_TAX_PAED => __('Pädagogik', 'programmo'),
        ];

        return (array) apply_filters('programmo/events/supported_taxonomies', $tax);
    }

    public static function get_terms_for_taxonomy(string $taxonomy): array
    {
        if (!taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }

        $result = [];
        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $result[(int) $term->term_id] = $term->name;
            }
        }

        return $result;
    }

    /**
     * Predefined color palette for pädagogik taxonomy terms.
     * Falls back to cycling through these by term_id.
     */
    private const PAED_COLORS = [
        '#9333ea', // purple
        '#06b6d4', // cyan
        '#f97316', // orange
        '#22c55e', // green
        '#ec4899', // pink
        '#3b82f6', // blue
        '#ef4444', // red
        '#eab308', // yellow
    ];

    /**
     * Return structured badge data for a post (name, taxonomy, color).
     *
     * For 'jugendarbeit' terms we attempt to look up the matching person CPT
     * and use its `_person_color` meta.  For 'paedagogik' terms we cycle through
     * a deterministic colour palette.  'tage' terms use a muted gray.
     *
     * @return array<int, array{name: string, taxonomy: string, color: string, is_person: bool}>
     */
    public static function get_term_badges_for_post(int $post_id): array
    {
        $source_post_id = $post_id;
        $post = get_post($post_id);
        if ($post instanceof \WP_Post && $post->post_type === self::PROGRAMMO_POST_TYPE_OFFER) {
            return self::get_local_offer_badges($post->ID);
        }

        if ($post instanceof \WP_Post && $post->post_type === self::OKJA_POST_TYPE_EVENT) {
            $primary_offer_id = self::get_primary_angebot_id($post->ID);
            if ($primary_offer_id > 0) {
                $source_post_id = $primary_offer_id;
            }
        }

        $badges = [];
        $seen   = [];

        foreach (self::supported_taxonomies() as $taxonomy => $label) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = get_the_terms($source_post_id, $taxonomy);
            if (is_wp_error($terms) || !is_array($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                if (!($term instanceof \WP_Term) || isset($seen[$term->name])) {
                    continue;
                }
                $seen[$term->name] = true;

                $color     = '';
                $is_person = false;

                if ($taxonomy === self::OKJA_TAX_JUGEND) {
                    $is_person = true;
                    $color = self::resolve_person_badge_color($term->name);
                    if ($color === '') {
                        $color = '#ff6b6b'; // Events_OKJA default person colour
                    }
                } elseif ($taxonomy === self::OKJA_TAX_PAED) {
                    $idx   = abs($term->term_id) % count(self::PAED_COLORS);
                    $color = self::PAED_COLORS[$idx];
                } elseif ($taxonomy === self::OKJA_TAX_TAGE) {
                    $color = '#6b7280'; // muted gray for day badges
                }

                $badges[] = [
                    'name'      => $term->name,
                    'taxonomy'  => $taxonomy,
                    'color'     => $color,
                    'is_person' => $is_person,
                ];
            }
        }

        return $badges;
    }

    /**
     * Get the featured image / thumbnail URL for an event (angebot or angebotsevent).
     *
     * For angebotsevent, falls back to the linked primary angebot's thumbnail.
     *
     * @param int    $post_id Event post ID.
     * @param string $size    WordPress image size (default 'medium').
     * @return string Image URL or empty string.
     */
    public static function get_event_thumbnail_url(int $post_id, string $size = 'medium'): string
    {
        if ($post_id <= 0) {
            return '';
        }

        // Try featured image on the post itself first
        if (has_post_thumbnail($post_id)) {
            $url = get_the_post_thumbnail_url($post_id, $size);
            return is_string($url) ? $url : '';
        }

        // For angebotsevent: try the linked primary angebot's thumbnail
        $post = get_post($post_id);
        if ($post instanceof \WP_Post && $post->post_type === self::OKJA_POST_TYPE_EVENT) {
            $primary_id = self::get_primary_angebot_id($post_id);
            if ($primary_id > 0 && has_post_thumbnail($primary_id)) {
                $url = get_the_post_thumbnail_url($primary_id, $size);
                return is_string($url) ? $url : '';
            }
        }

        return '';
    }

    public static function get_person_choices_by_source(): array
    {
        return [
            'programmo' => self::query_person_choices(self::PROGRAMMO_POST_TYPE_PERSON),
            'okja'      => self::query_person_choices(self::OKJA_POST_TYPE_PERSON),
        ];
    }

    public static function get_local_offer_person_ids(int $post_id, string $source): array
    {
        $meta_key = self::get_local_offer_people_meta_key($source);
        if ($meta_key === '') {
            return [];
        }

        $ids = get_post_meta($post_id, $meta_key, true);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('absint', $ids))));
    }

    public static function save_local_offer_people(int $post_id, array $programmo_people, array $okja_people): void
    {
        update_post_meta($post_id, '_programmo_offer_programmo_people', array_values(array_unique(array_filter(array_map('absint', $programmo_people)))));
        update_post_meta($post_id, '_programmo_offer_okja_people', array_values(array_unique(array_filter(array_map('absint', $okja_people)))));
    }

    public static function resolve_person_badge_color(string $person_name, int $fallback_post_id = 0): string
    {
        $name = trim($person_name);
        if ($name === '') {
            return '';
        }

        $candidate_ids = [];
        if ($fallback_post_id > 0) {
            $candidate_ids[] = $fallback_post_id;
        }

        foreach ([self::PROGRAMMO_POST_TYPE_PERSON, self::OKJA_POST_TYPE_PERSON] as $post_type) {
            if (!post_type_exists($post_type)) {
                continue;
            }

            $matches = get_posts([
                'post_type'      => $post_type,
                'title'          => $name,
                'posts_per_page' => 1,
                'post_status'    => ['publish', 'draft', 'private'],
                'fields'         => 'ids',
            ]);

            if (!empty($matches)) {
                $candidate_ids[] = (int) $matches[0];
            }
        }

        $candidate_ids = array_values(array_unique(array_filter(array_map('absint', $candidate_ids))));

        foreach ($candidate_ids as $candidate_id) {
            $color = self::get_person_badge_color_for_post($candidate_id);
            if ($color !== '') {
                return $color;
            }
        }

        return '';
    }

    private static function query_person_choices(string $post_type): array
    {
        if (!post_type_exists($post_type)) {
            return [];
        }

        $people = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => ['publish', 'draft', 'future', 'private'],
        ]);

        $items = [];
        foreach ($people as $person) {
            $items[] = [
                'id'    => (int) $person->ID,
                'label' => (string) $person->post_title,
                'color' => self::get_person_badge_color_for_post((int) $person->ID),
            ];
        }

        return $items;
    }

    private static function get_local_offer_badges(int $post_id): array
    {
        $badges = [];
        $seen = [];

        $sources = [
            'programmo' => self::PROGRAMMO_POST_TYPE_PERSON,
            'okja'      => self::OKJA_POST_TYPE_PERSON,
        ];

        foreach ($sources as $source => $post_type) {
            if (!post_type_exists($post_type)) {
                continue;
            }

            foreach (self::get_local_offer_person_ids($post_id, $source) as $person_id) {
                $person = get_post($person_id);
                if (!$person instanceof \WP_Post || $person->post_type !== $post_type) {
                    continue;
                }

                $name = (string) $person->post_title;
                if ($name === '' || isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;

                $badges[] = [
                    'name'      => $name,
                    'taxonomy'  => 'person',
                    'color'     => self::get_person_badge_color_for_post((int) $person->ID),
                    'is_person' => true,
                ];
            }
        }

        return $badges;
    }

    private static function get_person_badge_color_for_post(int $post_id): string
    {
        if ($post_id <= 0) {
            return '';
        }

        $meta_candidates = [
            (string) get_post_meta($post_id, '_person_color', true),
            (string) get_post_meta($post_id, '_programmo_person_color', true),
        ];

        foreach ($meta_candidates as $raw_color) {
            $value = trim($raw_color);
            if ($value === '') {
                continue;
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
        }

        return '';
    }

    private static function get_local_offer_people_meta_key(string $source): string
    {
        if ($source === 'programmo') {
            return '_programmo_offer_programmo_people';
        }

        if ($source === 'okja') {
            return '_programmo_offer_okja_people';
        }

        return '';
    }
}
