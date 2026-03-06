<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API endpoints for the interactive Dashboard.
 *
 * Namespace: programmo/v1
 */
final class RestApi
{
    public const NAMESPACE = 'programmo/v1';

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        /* ---------- Slots ---------- */

        register_rest_route(self::NAMESPACE, '/slots', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_slots'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'create_slot'],
                'permission_callback' => [self::class, 'can_edit'],
                'args'                => self::slot_create_args(),
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/slots/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [self::class, 'update_slot'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [self::class, 'delete_slot'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
        ]);

        /* ---------- Events (read-only) ---------- */

        register_rest_route(self::NAMESPACE, '/events', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_events'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'create_event'],
                'permission_callback' => [self::class, 'can_edit'],
                'args'                => self::event_create_args(),
            ],
        ]);

        /* ---------- Events: update color ---------- */

        register_rest_route(self::NAMESPACE, '/events/(?P<id>\d+)', [
            'methods'             => 'PATCH',
            'callback'            => [self::class, 'update_event_color'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        /* ---------- Areas (read-only) ---------- */

        register_rest_route(self::NAMESPACE, '/areas', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_areas'],
            'permission_callback' => [self::class, 'can_edit'],
        ]);

        /* ---------- Day Colors ---------- */

        register_rest_route(self::NAMESPACE, '/day-colors', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_day_colors'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'save_day_colors'],
                'permission_callback' => [self::class, 'can_edit'],
            ],
        ]);
    }

    /* ================================================================== */
    /*  Permission check                                                   */
    /* ================================================================== */

    public static function can_edit(): bool
    {
        return current_user_can('edit_posts');
    }

    /* ================================================================== */
    /*  Slots                                                              */
    /* ================================================================== */

    /**
     * GET /programmo/v1/slots
     */
    public static function get_slots(\WP_REST_Request $request): \WP_REST_Response
    {
        $posts = get_posts([
            'post_type'      => 'programmo_slot',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'draft', 'private'],
        ]);

        $items = array_map([self::class, 'format_slot'], $posts);

        return new \WP_REST_Response($items, 200);
    }

    /**
     * POST /programmo/v1/slots
     */
    public static function create_slot(\WP_REST_Request $request): \WP_REST_Response
    {
        $weekday  = sanitize_text_field($request->get_param('weekday') ?? 'monday');
        $start    = sanitize_text_field($request->get_param('start') ?? '');
        $end      = sanitize_text_field($request->get_param('end') ?? '');
        $area_id  = absint($request->get_param('area_id') ?? 0);
        $title    = sanitize_text_field($request->get_param('title') ?? '');

        // Support both single event_id and event_ids array
        $event_ids = $request->get_param('event_ids');
        if (!is_array($event_ids)) {
            $single = absint($request->get_param('event_id') ?? 0);
            $event_ids = $single > 0 ? [$single] : [];
        }
        $event_ids = array_values(array_unique(array_filter(array_map('absint', $event_ids))));

        // Auto-generate title if empty
        if ($title === '') {
            $day_labels = [
                'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi',
                'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So',
            ];
            $day_short = $day_labels[$weekday] ?? $weekday;
            $title = sprintf('%s %s–%s', $day_short, $start, $end);
        }

        $post_id = wp_insert_post([
            'post_type'   => 'programmo_slot',
            'post_title'  => $title,
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response(['error' => $post_id->get_error_message()], 400);
        }

        $age_range = sanitize_text_field($request->get_param('age_range') ?? '');
        $jugend_term_ids = $request->get_param('jugend_term_ids');
        if (!is_array($jugend_term_ids)) {
            $jugend_term_ids = [];
        }
        $jugend_term_ids = array_values(array_unique(array_filter(array_map('absint', $jugend_term_ids))));

        update_post_meta($post_id, '_programmo_weekday', $weekday);
        update_post_meta($post_id, '_programmo_start_time', $start);
        update_post_meta($post_id, '_programmo_end_time', $end);
        update_post_meta($post_id, '_programmo_area_id', $area_id);
        update_post_meta($post_id, '_programmo_age_range', $age_range);
        update_post_meta($post_id, '_programmo_tax_jugendarbeit', $jugend_term_ids);
        update_post_meta($post_id, '_programmo_event_ids', $event_ids);
        // Keep legacy key in sync for backward compat
        update_post_meta($post_id, '_programmo_event_id', !empty($event_ids) ? $event_ids[0] : 0);

        $post = get_post($post_id);
        return new \WP_REST_Response(self::format_slot($post), 201);
    }

    /**
     * PATCH /programmo/v1/slots/<id>
     *
     * Supports:
     *  - event_ids: [1,2,3]       → set entire list
     *  - add_event_id: 5          → append one event
     *  - remove_event_id: 5       → remove one event
    *  - set_event_time: {event_id,start,end} → set offer time override
    *  - clear_event_time: 5      → remove offer time override
     *  - weekday, start, end, area_id → same as before
     */
    public static function update_slot(\WP_REST_Request $request): \WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'programmo_slot') {
            return new \WP_REST_Response(['error' => 'Slot not found'], 404);
        }

        // Handle simple fields
        $simple_fields = ['weekday', 'start', 'end', 'area_id', 'age_range'];
        $meta_map = [
            'weekday'   => '_programmo_weekday',
            'start'     => '_programmo_start_time',
            'end'       => '_programmo_end_time',
            'area_id'   => '_programmo_area_id',
            'age_range' => '_programmo_age_range',
        ];

        foreach ($simple_fields as $field) {
            $value = $request->get_param($field);
            if ($value !== null) {
                if ($field === 'area_id') {
                    $value = absint($value);
                } else {
                    $value = sanitize_text_field((string) $value);
                }
                update_post_meta($post_id, $meta_map[$field], $value);
            }
        }

        // Handle multi-event changes
        $current_ids = self::read_slot_event_ids($post_id);

        $set_ids = $request->get_param('event_ids');
        $add_id  = $request->get_param('add_event_id');
        $rm_id   = $request->get_param('remove_event_id');
        $set_event_time = $request->get_param('set_event_time');
        $clear_event_time = $request->get_param('clear_event_time');

        // Legacy single event_id → treat as set (for backward compat)
        $legacy_event_id = $request->get_param('event_id');

        if (is_array($set_ids)) {
            // Full replace
            $current_ids = array_values(array_unique(array_filter(array_map('absint', $set_ids))));
        } elseif ($legacy_event_id !== null) {
            // Legacy: single event_id = 0 means clear, >0 means set
            $legacy = absint($legacy_event_id);
            $current_ids = $legacy > 0 ? [$legacy] : [];
        }

        if ($add_id !== null) {
            $add_id = absint($add_id);
            if ($add_id > 0 && !in_array($add_id, $current_ids, true)) {
                $current_ids[] = $add_id;
            }
        }

        if ($rm_id !== null) {
            $rm_id = absint($rm_id);
            $current_ids = array_values(array_filter($current_ids, function ($id) use ($rm_id) {
                return $id !== $rm_id;
            }));
        }

        update_post_meta($post_id, '_programmo_event_ids', $current_ids);
        // Legacy sync
        update_post_meta($post_id, '_programmo_event_id', !empty($current_ids) ? $current_ids[0] : 0);

        $event_time_map = self::read_slot_event_times($post_id);

        if ($rm_id !== null && $rm_id > 0) {
            unset($event_time_map[(string) $rm_id]);
        }

        if (is_array($set_ids) || $legacy_event_id !== null) {
            $valid_ids = array_map('strval', $current_ids);
            foreach (array_keys($event_time_map) as $event_id_key) {
                if (!in_array((string) $event_id_key, $valid_ids, true)) {
                    unset($event_time_map[(string) $event_id_key]);
                }
            }
        }

        if (is_array($set_event_time)) {
            $time_event_id = absint($set_event_time['event_id'] ?? 0);
            $time_start = self::sanitize_time_value((string) ($set_event_time['start'] ?? ''));
            $time_end = self::sanitize_time_value((string) ($set_event_time['end'] ?? ''));

            if ($time_event_id > 0 && in_array($time_event_id, $current_ids, true) && $time_start !== '' && $time_end !== '') {
                $event_time_map[(string) $time_event_id] = [
                    'start' => $time_start,
                    'end'   => $time_end,
                ];
            }
        }

        if ($clear_event_time !== null) {
            $clear_event_id = absint($clear_event_time);
            if ($clear_event_id > 0) {
                unset($event_time_map[(string) $clear_event_id]);
            }
        }

        update_post_meta($post_id, '_programmo_slot_event_times', $event_time_map);

        // Update post title to reflect new data
        $weekday = (string) get_post_meta($post_id, '_programmo_weekday', true);
        $start   = (string) get_post_meta($post_id, '_programmo_start_time', true);
        $end     = (string) get_post_meta($post_id, '_programmo_end_time', true);
        $day_labels = [
            'monday' => 'Mo', 'tuesday' => 'Di', 'wednesday' => 'Mi',
            'thursday' => 'Do', 'friday' => 'Fr', 'saturday' => 'Sa', 'sunday' => 'So',
        ];
        $day_short = $day_labels[$weekday] ?? $weekday;
        wp_update_post([
            'ID'         => $post_id,
            'post_title' => sprintf('%s %s–%s', $day_short, $start, $end),
        ]);

        return new \WP_REST_Response(self::format_slot(get_post($post_id)), 200);
    }

    /**
     * DELETE /programmo/v1/slots/<id>
     */
    public static function delete_slot(\WP_REST_Request $request): \WP_REST_Response
    {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post || $post->post_type !== 'programmo_slot') {
            return new \WP_REST_Response(['error' => 'Slot not found'], 404);
        }

        wp_trash_post($post_id);

        return new \WP_REST_Response(['deleted' => true, 'id' => $post_id], 200);
    }

    /* ================================================================== */
    /*  Events                                                             */
    /* ================================================================== */

    /**
     * GET /programmo/v1/events
     */
    public static function get_events(\WP_REST_Request $request): \WP_REST_Response
    {
        $choices = EventsBridge::query_event_choices();

        $items = [];
        foreach ($choices as $id => $label) {
            $item = self::format_event_item((int) $id, (string) $label);
            if (!empty($item)) {
                $items[] = $item;
            }
        }

        return new \WP_REST_Response($items, 200);
    }

    /**
     * POST /programmo/v1/events
     */
    public static function create_event(\WP_REST_Request $request): \WP_REST_Response
    {
        $title = sanitize_text_field((string) ($request->get_param('title') ?? ''));
        if ($title === '') {
            return new \WP_REST_Response(['error' => __('Bitte einen Titel eingeben.', 'programmo')], 400);
        }

        $description = wp_kses_post((string) ($request->get_param('description') ?? ''));
        $programmo_people = $request->get_param('programmo_person_ids');
        $okja_people = $request->get_param('okja_person_ids');
        $event_color = sanitize_text_field((string) ($request->get_param('event_color') ?? ''));
        $image_id = absint($request->get_param('image_id'));

        $programmo_people = is_array($programmo_people)
            ? array_values(array_unique(array_filter(array_map('absint', $programmo_people))))
            : [];
        $okja_people = is_array($okja_people)
            ? array_values(array_unique(array_filter(array_map('absint', $okja_people))))
            : [];

        $post_id = wp_insert_post([
            'post_type'    => EventsBridge::PROGRAMMO_POST_TYPE_OFFER,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $description,
        ], true);

        if (is_wp_error($post_id)) {
            return new \WP_REST_Response(['error' => $post_id->get_error_message()], 400);
        }

        EventsBridge::save_local_offer_people($post_id, $programmo_people, $okja_people);

        if ($event_color !== '') {
            update_post_meta($post_id, '_programmo_event_color', $event_color);
        }

        if ($image_id > 0) {
            set_post_thumbnail($post_id, $image_id);
        }

        return new \WP_REST_Response(self::format_event_item((int) $post_id), 201);
    }

    /**
     * PATCH /programmo/v1/events/<id>
     *
     * Supports:
     * - event_color for all event sources
    * - title, description, programmo_person_ids, okja_person_ids, image_id for local ProgrammO offers
     */
    public static function update_event_color(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);
        if (!$post instanceof \WP_Post) {
            return new \WP_REST_Response(['message' => 'Event not found'], 404);
        }

        $updated_fields = false;
        $color = sanitize_text_field((string) ($request->get_param('event_color') ?? ''));
        if ($request->get_param('event_color') !== null) {
            update_post_meta($id, '_programmo_event_color', $color);
            $updated_fields = true;
        }

        if ($post->post_type === EventsBridge::PROGRAMMO_POST_TYPE_OFFER) {
            $title = $request->get_param('title');
            $description = $request->get_param('description');
            $programmo_people = $request->get_param('programmo_person_ids');
            $okja_people = $request->get_param('okja_person_ids');
            $image_id_param = $request->get_param('image_id');

            if ($title !== null || $description !== null) {
                $update_post = ['ID' => $id];

                if ($title !== null) {
                    $sanitized_title = sanitize_text_field((string) $title);
                    if ($sanitized_title === '') {
                        return new \WP_REST_Response(['message' => __('Bitte einen Titel eingeben.', 'programmo')], 400);
                    }
                    $update_post['post_title'] = $sanitized_title;
                }

                if ($description !== null) {
                    $update_post['post_content'] = wp_kses_post((string) $description);
                }

                $result = wp_update_post($update_post, true);
                if (is_wp_error($result)) {
                    return new \WP_REST_Response(['message' => $result->get_error_message()], 400);
                }
                $updated_fields = true;
            }

            if ($programmo_people !== null || $okja_people !== null) {
                $programmo_people = is_array($programmo_people)
                    ? array_values(array_unique(array_filter(array_map('absint', $programmo_people))))
                    : EventsBridge::get_local_offer_person_ids($id, 'programmo');
                $okja_people = is_array($okja_people)
                    ? array_values(array_unique(array_filter(array_map('absint', $okja_people))))
                    : EventsBridge::get_local_offer_person_ids($id, 'okja');

                EventsBridge::save_local_offer_people($id, $programmo_people, $okja_people);
                $updated_fields = true;
            }

            if ($image_id_param !== null) {
                $image_id = absint($image_id_param);
                if ($image_id > 0) {
                    set_post_thumbnail($id, $image_id);
                } else {
                    delete_post_thumbnail($id);
                }
                $updated_fields = true;
            }
        }

        if ($updated_fields) {
            return new \WP_REST_Response(self::format_event_item($id), 200);
        }

        return new \WP_REST_Response([
            'id'          => $id,
            'event_color' => $color,
        ], 200);
    }

    /* ================================================================== */
    /*  Areas                                                              */
    /* ================================================================== */

    /**
     * GET /programmo/v1/areas
     */
    public static function get_areas(\WP_REST_Request $request): \WP_REST_Response
    {
        $posts = get_posts([
            'post_type'      => 'programmo_area',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => ['publish', 'draft', 'private'],
        ]);

        $items = [];
        foreach ($posts as $p) {
            $color    = (string) get_post_meta($p->ID, '_programmo_area_color', true);
            $gradient = (string) get_post_meta($p->ID, '_programmo_area_gradient', true);
            $items[] = [
                'id'       => (int) $p->ID,
                'title'    => $p->post_title,
                'color'    => $color ?: '#bde9ff',
                'gradient' => $gradient,
            ];
        }

        return new \WP_REST_Response($items, 200);
    }

    /* ================================================================== */
    /*  Day Colors                                                         */
    /* ================================================================== */

    public const DAY_COLORS_OPTION = 'programmo_day_colors';

    private static function valid_day_keys(): array
    {
        return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    }

    /**
     * GET /programmo/v1/day-colors
     */
    public static function get_day_colors(\WP_REST_Request $request): \WP_REST_Response
    {
        $stored = (array) get_option(self::DAY_COLORS_OPTION, []);
        $result = [];
        foreach (self::valid_day_keys() as $key) {
            $result[$key] = isset($stored[$key]) ? (string) $stored[$key] : '';
        }
        return new \WP_REST_Response($result, 200);
    }

    /**
     * POST /programmo/v1/day-colors
     *
     * Body: { "monday": "linear-gradient(...)", "tuesday": "#2279d4", ... }
     * Only provided keys are updated; missing keys are left unchanged.
     */
    public static function save_day_colors(\WP_REST_Request $request): \WP_REST_Response
    {
        $valid_keys = self::valid_day_keys();
        $stored = (array) get_option(self::DAY_COLORS_OPTION, []);

        foreach ($valid_keys as $key) {
            $val = $request->get_param($key);
            if ($val !== null) {
                $stored[$key] = sanitize_text_field((string) $val);
            }
        }

        update_option(self::DAY_COLORS_OPTION, $stored);

        $result = [];
        foreach ($valid_keys as $key) {
            $result[$key] = isset($stored[$key]) ? (string) $stored[$key] : '';
        }
        return new \WP_REST_Response($result, 200);
    }

    /* ================================================================== */
    /*  Helpers                                                            */
    /* ================================================================== */

    private static function format_slot(\WP_Post $post): array
    {
        $area_id   = (int) get_post_meta($post->ID, '_programmo_area_id', true);
        $event_ids = self::read_slot_event_ids($post->ID);
        $slot_start = (string) get_post_meta($post->ID, '_programmo_start_time', true);
        $slot_end = (string) get_post_meta($post->ID, '_programmo_end_time', true);
        $event_time_map = self::read_slot_event_times($post->ID);

        $area_title  = '';
        $area_color  = '#bde9ff';
        $area_people = [];
        if ($area_id > 0) {
            $area = get_post($area_id);
            if ($area instanceof \WP_Post) {
                $area_title = $area->post_title;
            }
            $c = (string) get_post_meta($area_id, '_programmo_area_color', true);
            $g = (string) get_post_meta($area_id, '_programmo_area_gradient', true);
            if ($g !== '') {
                $area_color = $g;
            } elseif ($c !== '') {
                $area_color = $c;
            }
            $people_ids = (array) get_post_meta($area_id, '_programmo_area_people', true);
            foreach ($people_ids as $pid) {
                $person = get_post((int) $pid);
                if ($person instanceof \WP_Post) {
                    $area_people[] = $person->post_title;
                }
            }
        }

        // Slot-level Jugendarbeit taxonomy → additional people names
        $jugend_term_ids = (array) get_post_meta($post->ID, '_programmo_tax_jugendarbeit', true);
        foreach ($jugend_term_ids as $tid) {
            $term = get_term((int) $tid, EventsBridge::OKJA_TAX_JUGEND);
            if ($term instanceof \WP_Term && !in_array($term->name, $area_people, true)) {
                $area_people[] = $term->name;
            }
        }

        // Build events array
        $events_list = [];
        $has_warning = false;
        foreach ($event_ids as $eid) {
            $ev = get_post($eid);
            if (!$ev instanceof \WP_Post) {
                continue;
            }
            $ev_type = $ev->post_type;
            $primary = EventsBridge::get_primary_angebot_id($eid);
            $warn    = ($ev_type === EventsBridge::OKJA_POST_TYPE_EVENT && $primary <= 0);
            if ($warn) {
                $has_warning = true;
            }

            $event_key = (string) $eid;
            $offer_start = $slot_start;
            $offer_end = $slot_end;
            if (isset($event_time_map[$event_key]) && is_array($event_time_map[$event_key])) {
                $offer_start = self::sanitize_time_value((string) ($event_time_map[$event_key]['start'] ?? '')) ?: $slot_start;
                $offer_end = self::sanitize_time_value((string) ($event_time_map[$event_key]['end'] ?? '')) ?: $slot_end;
            }

            $events_list[] = [
                'id'                 => (int) $eid,
                'title'              => $ev->post_title,
                'type'               => $ev_type,
                'primary_angebot_id' => $primary,
                'has_warning'        => $warn,
                'image_url'          => EventsBridge::get_event_thumbnail_url($eid, 'thumbnail'),
                'event_color'        => (string) get_post_meta($eid, '_programmo_event_color', true),
                'offer_start'        => $offer_start,
                'offer_end'          => $offer_end,
                'offer_time'         => self::build_offer_time_label($offer_start, $offer_end),
            ];
        }

        return [
            'id'          => (int) $post->ID,
            'title'       => $post->post_title,
            'weekday'     => (string) get_post_meta($post->ID, '_programmo_weekday', true),
            'start'       => $slot_start,
            'end'         => $slot_end,
            'age_range'   => (string) get_post_meta($post->ID, '_programmo_age_range', true),
            'area_id'     => $area_id,
            'area_title'  => $area_title,
            'area_color'  => $area_color,
            'area_people' => $area_people,
            'event_ids'   => $event_ids,
            'event_time_overrides' => $event_time_map,
            'events'      => $events_list,
            'has_warning' => $has_warning,
            'edit_url'    => (string) get_edit_post_link($post->ID, 'raw'),
        ];
    }

    /**
     * Read event IDs for a slot, supporting both legacy (single) and new (array) format.
     */
    public static function read_slot_event_ids(int $slot_id): array
    {
        $ids = get_post_meta($slot_id, '_programmo_event_ids', true);
        if (is_array($ids) && !empty($ids)) {
            return array_values(array_filter(array_map('absint', $ids)));
        }

        // Fallback: legacy single _programmo_event_id
        $single = (int) get_post_meta($slot_id, '_programmo_event_id', true);
        if ($single > 0) {
            return [$single];
        }

        return [];
    }

    /**
     * Read per-offer time overrides for a slot.
     *
     * Result format:
     * [
     *   '123' => ['start' => '16:00', 'end' => '18:30'],
     * ]
     */
    public static function read_slot_event_times(int $slot_id): array
    {
        $raw = get_post_meta($slot_id, '_programmo_slot_event_times', true);
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $event_id => $time_data) {
            $event_id = absint($event_id);
            if ($event_id <= 0 || !is_array($time_data)) {
                continue;
            }
            $start = self::sanitize_time_value((string) ($time_data['start'] ?? ''));
            $end = self::sanitize_time_value((string) ($time_data['end'] ?? ''));
            if ($start === '' || $end === '') {
                continue;
            }
            $result[(string) $event_id] = [
                'start' => $start,
                'end'   => $end,
            ];
        }

        return $result;
    }

    private static function sanitize_time_value(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return $time;
        }
        return '';
    }

    private static function build_offer_time_label(string $start, string $end): string
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

    private static function slot_create_args(): array
    {
        return [
            'weekday' => [
                'type'              => 'string',
                'required'          => true,
                'enum'              => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'start' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'end' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'area_id' => [
                'type'    => 'integer',
                'default' => 0,
            ],
            'event_ids' => [
                'type'    => 'array',
                'default' => [],
                'items'   => ['type' => 'integer'],
            ],
            'event_id' => [
                'type'    => 'integer',
                'default' => 0,
            ],
            'title' => [
                'type'    => 'string',
                'default' => '',
            ],
            'jugend_term_ids' => [
                'type'    => 'array',
                'default' => [],
                'items'   => ['type' => 'integer'],
            ],
        ];
    }

    private static function event_create_args(): array
    {
        return [
            'title' => [
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'description' => [
                'type'    => 'string',
                'default' => '',
            ],
            'programmo_person_ids' => [
                'type'    => 'array',
                'default' => [],
                'items'   => ['type' => 'integer'],
            ],
            'okja_person_ids' => [
                'type'    => 'array',
                'default' => [],
                'items'   => ['type' => 'integer'],
            ],
            'image_id' => [
                'type'    => 'integer',
                'default' => 0,
            ],
            'event_color' => [
                'type'    => 'string',
                'default' => '',
            ],
        ];
    }

    private static function format_event_item(int $id, string $label = ''): array
    {
        $post = get_post($id);
        if (!$post instanceof \WP_Post) {
            return [];
        }

        $badges = EventsBridge::get_term_badges_for_post($id);
        $primary = EventsBridge::get_primary_angebot_id($id);
        $type = (string) $post->post_type;
        $computed_label = $label !== '' ? $label : (string) get_the_title($id);

        if ($label === '' && $type === EventsBridge::PROGRAMMO_POST_TYPE_OFFER) {
            $computed_label = sprintf(__('ProgrammO: %s', 'programmo'), (string) get_the_title($id));
        }

        return [
            'id'                  => $id,
            'title'               => (string) get_the_title($id),
            'label'               => $computed_label,
            'type'                => $type,
            'source'              => $type === EventsBridge::PROGRAMMO_POST_TYPE_OFFER ? 'programmo' : 'okja',
            'badges'              => $badges,
            'primary_angebot_id'  => $primary,
            'has_warning'         => ($type === EventsBridge::OKJA_POST_TYPE_EVENT && $primary <= 0),
            'image_url'           => EventsBridge::get_event_thumbnail_url($id, 'thumbnail'),
            'image_id'            => (int) get_post_thumbnail_id($id),
            'event_color'         => (string) get_post_meta($id, '_programmo_event_color', true),
            'description'         => wp_trim_words(wp_strip_all_tags((string) $post->post_content), 24),
            'description_raw'     => (string) $post->post_content,
            'programmo_person_ids' => $type === EventsBridge::PROGRAMMO_POST_TYPE_OFFER ? EventsBridge::get_local_offer_person_ids($id, 'programmo') : [],
            'okja_person_ids'      => $type === EventsBridge::PROGRAMMO_POST_TYPE_OFFER ? EventsBridge::get_local_offer_person_ids($id, 'okja') : [],
            'edit_url'            => (string) get_edit_post_link($id, 'raw'),
        ];
    }
}
