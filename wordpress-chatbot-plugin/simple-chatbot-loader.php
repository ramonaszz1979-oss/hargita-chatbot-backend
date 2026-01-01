<?php
/**
 * Plugin Name: Simple Chatbot (mu-loader)
 * Description: MU-plugin loader that boots the Simple Chatbot plugin when its files are placed outside wp-content/plugins (e.g., directly under public_html).
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$chatbot_base_paths = [
    ABSPATH . 'simple-chatbot',
    ABSPATH . 'wordpress-chatbot-plugin',
];

$chatbot_main = null;
foreach ($chatbot_base_paths as $base_path) {
    $main_candidate = trailingslashit($base_path) . 'chatbot-plugin.php';
    if (file_exists($main_candidate)) {
        $chatbot_main = $main_candidate;
        break;
    }
}

if ($chatbot_main) {
    require_once $chatbot_main;
} else {
    if (!function_exists('simple_chatbot_loader_notice')) {
        function simple_chatbot_loader_notice() {
            echo '<div class="notice notice-error"><p>Simple Chatbot MU-loader: nem található a chatbot-plugin.php. Másold a teljes mappát a public_html/simple-chatbot vagy public_html/wordpress-chatbot-plugin útvonalra.</p></div>';
        }
    }
    add_action('admin_notices', 'simple_chatbot_loader_notice');
}
