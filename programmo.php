<?php
/**
 * Plugin Name: ProgrammO
 * Description: Dynamischer Wochenplan für Jugendhäuser mit Offenen Bereichen, Personen-Zuordnung und Events_OKJA-Integration.
 * Version: 0.3
 * Author: Hubertoink
 * Text Domain: programmo
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PROGRAMMO_VERSION', '0.3');
define('PROGRAMMO_FILE', __FILE__);
define('PROGRAMMO_PATH', plugin_dir_path(__FILE__));
define('PROGRAMMO_URL', plugin_dir_url(__FILE__));

require_once PROGRAMMO_PATH . 'includes/class-programmo.php';

function programmo_bootstrap(): void
{
    ProgrammO\Core\Plugin::instance()->boot();
}
add_action('plugins_loaded', 'programmo_bootstrap');

function programmo_activate(): void
{
    ProgrammO\Core\Plugin::instance()->boot();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'programmo_activate');

function programmo_deactivate(): void
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'programmo_deactivate');
