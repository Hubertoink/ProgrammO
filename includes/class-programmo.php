<?php

namespace ProgrammO\Core;

if (!defined('ABSPATH')) {
    exit;
}

require_once PROGRAMMO_PATH . 'includes/class-post-types.php';
require_once PROGRAMMO_PATH . 'includes/class-admin.php';
require_once PROGRAMMO_PATH . 'includes/class-events-bridge.php';
require_once PROGRAMMO_PATH . 'includes/class-shortcode.php';
require_once PROGRAMMO_PATH . 'includes/class-dashboard.php';
require_once PROGRAMMO_PATH . 'includes/class-block.php';
require_once PROGRAMMO_PATH . 'includes/class-rest-api.php';

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        PostTypes::register();
        Dashboard::register();
        Admin::register();
        EventsBridge::register();
        Shortcode::register();
        Block::register();
        RestApi::register();
    }

    private function __construct()
    {
    }
}
