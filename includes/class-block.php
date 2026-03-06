<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gutenberg Block: programmo/weekplan
 *
 * Dynamic server-side rendered block that shares rendering logic
 * with the [programmo_weekplan] shortcode.
 */
final class Block
{
    public static function register(): void
    {
        add_action('init', [self::class, 'register_block']);
    }

    /**
     * Register the block type from block.json metadata.
     */
    public static function register_block(): void
    {
        // Register the frontend style so block.json "style" field works in both frontend and editor
        if (!wp_style_is('programmo-frontend', 'registered')) {
            wp_register_style(
                'programmo-frontend',
                PROGRAMMO_URL . 'assets/css/programmo.css',
                [],
                PROGRAMMO_VERSION
            );
        }

        // Register editor script manually so we can control deps
        wp_register_script(
            'programmo-block-editor',
            PROGRAMMO_URL . 'assets/js/programmo-block.js',
            [
                'wp-blocks',
                'wp-element',
                'wp-block-editor',
                'wp-components',
                'wp-server-side-render',
                'wp-i18n',
                'wp-data',
            ],
            PROGRAMMO_VERSION,
            true
        );

        // Pass translatable strings & config to the editor script
        wp_localize_script('programmo-block-editor', 'programmoBlockData', [
            'weekdays' => [
                'monday'    => __('Montag', 'programmo'),
                'tuesday'   => __('Dienstag', 'programmo'),
                'wednesday' => __('Mittwoch', 'programmo'),
                'thursday'  => __('Donnerstag', 'programmo'),
                'friday'    => __('Freitag', 'programmo'),
                'saturday'  => __('Samstag', 'programmo'),
                'sunday'    => __('Sonntag', 'programmo'),
            ],
            'globalDays' => (array) Dashboard::get_option('weekdays_visible', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            'globalMobileCollapse' => (bool) Dashboard::get_option('mobile_collapse', true),
        ]);

        // Register the block type via block.json
        register_block_type(
            PROGRAMMO_PATH . 'blocks/weekplan',
            [
                'render_callback' => [self::class, 'render_block'],
            ]
        );
    }

    /**
     * Server-side render callback for the block.
     *
     * Delegates to the shared Shortcode::render_plan() method, passing
     * block attributes as render options.
     *
     * @param array  $attributes Block attributes.
     * @param string $content    Inner content (unused for dynamic block).
     * @return string            HTML output.
     */
    public static function render_block(array $attributes, string $content): string
    {
        $options = [
            'title'          => sanitize_text_field($attributes['title'] ?? ''),
            'show_valid_from' => (bool) ($attributes['showValidFrom'] ?? true),
            'show_team'       => (bool) ($attributes['showTeam'] ?? true),
            'show_badges'     => (bool) ($attributes['showBadges'] ?? true),
            'show_details'    => (bool) ($attributes['showDetails'] ?? true),
            'columns'         => (int) ($attributes['columns'] ?? 0),
            'compact_mode'    => (bool) ($attributes['compactMode'] ?? false),
            'mobile_collapse' => (bool) ($attributes['mobileCollapse'] ?? Dashboard::get_option('mobile_collapse', true)),
            'enable_pdf_export' => (bool) ($attributes['enablePdfExport'] ?? false),
            'show_grain'        => (bool) ($attributes['showGrain'] ?? false),
            'show_tooltips'     => (bool) ($attributes['showTooltips'] ?? true),
            'override_days'   => (array) ($attributes['overrideDays'] ?? []),
            'back_url'        => sanitize_url($attributes['backUrl'] ?? ''),
        ];

        // Build wrapper classes from block supports
        $wrapper_attrs = '';
        if (function_exists('get_block_wrapper_attributes')) {
            $extra_class = $options['compact_mode'] ? 'programmo-compact' : '';
            $wrapper_attrs = get_block_wrapper_attributes(['class' => $extra_class]);
        }

        $html = Shortcode::render_plan($options);

        if ($wrapper_attrs !== '') {
            return '<div ' . $wrapper_attrs . '>' . $html . '</div>';
        }

        return '<div class="wp-block-programmo-weekplan">' . $html . '</div>';
    }
}
