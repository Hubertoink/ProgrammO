<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class PostTypes
{
    public static function register(): void
    {
        add_action('after_setup_theme', [self::class, 'ensure_thumbnail_support']);
        add_action('init', [self::class, 'register_post_types']);
        add_action('init', [self::class, 'register_taxonomies']);
    }

    public static function ensure_thumbnail_support(): void
    {
        add_theme_support('post-thumbnails', ['programmo_offer']);
    }

    public static function register_post_types(): void
    {
        register_post_type('programmo_area', [
            'labels' => [
                'name' => __('Offene Bereiche', 'programmo'),
                'singular_name' => __('Offener Bereich', 'programmo'),
                'add_new'       => __('Neuer Bereich', 'programmo'),
                'add_new_item'  => __('Neuen Offenen Bereich anlegen', 'programmo'),
                'edit_item'     => __('Offenen Bereich bearbeiten', 'programmo'),
                'all_items'     => __('Offene Bereiche (Öffnungen)', 'programmo'),
            ],
            'description' => __('Zeitfenster, in denen das Jugendhaus geöffnet ist (hellblaue Kacheln im Plan).', 'programmo'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'programmo',
            'menu_icon' => 'dashicons-grid-view',
            'supports' => ['title', 'editor'],
            'show_in_rest' => true,
        ]);

        register_post_type('programmo_person', [
            'labels' => [
                'name' => __('Personen', 'programmo'),
                'singular_name' => __('Person', 'programmo'),
                'add_new'       => __('Neue Person', 'programmo'),
                'add_new_item'  => __('Neue Person anlegen', 'programmo'),
                'edit_item'     => __('Person bearbeiten', 'programmo'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'programmo',
            'menu_icon' => 'dashicons-groups',
            'supports' => ['title', 'editor'],
            'show_in_rest' => true,
        ]);

        register_post_type('programmo_offer', [
            'labels' => [
                'name' => __('Angebote', 'programmo'),
                'singular_name' => __('Angebot', 'programmo'),
                'add_new'       => __('Neues Angebot', 'programmo'),
                'add_new_item'  => __('Neues Angebot anlegen', 'programmo'),
                'edit_item'     => __('Angebot bearbeiten', 'programmo'),
                'all_items'     => __('Angebote', 'programmo'),
            ],
            'description' => __('Eigene ProgrammO-Angebote ohne eigene Detailseite. Beschreibung und Personen können direkt gepflegt werden.', 'programmo'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'programmo',
            'menu_icon' => 'dashicons-welcome-learn-more',
            'supports' => ['title', 'editor', 'thumbnail'],
            'show_in_rest' => true,
        ]);

        register_post_type('programmo_slot', [
            'labels' => [
                'name' => __('Wochenplan Slots', 'programmo'),
                'singular_name' => __('Wochenplan Slot', 'programmo'),
                'add_new'       => __('Neuer Slot', 'programmo'),
                'add_new_item'  => __('Neuen Slot anlegen', 'programmo'),
                'edit_item'     => __('Slot bearbeiten', 'programmo'),
                'all_items'     => __('Slots (Wochenplan-Einträge)', 'programmo'),
            ],
            'description' => __('Einzelne Einträge im Wochenplan: Wochentag + Zeitfenster + Offener Bereich + Angebot.', 'programmo'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'programmo',
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title'],
            'show_in_rest' => true,
        ]);
    }

    public static function register_taxonomies(): void
    {
        register_taxonomy('programmo_segment', ['programmo_area', 'programmo_slot'], [
            'labels' => [
                'name' => __('Segmente', 'programmo'),
                'singular_name' => __('Segment', 'programmo'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_rest' => true,
            'hierarchical' => true,
        ]);
    }
}
