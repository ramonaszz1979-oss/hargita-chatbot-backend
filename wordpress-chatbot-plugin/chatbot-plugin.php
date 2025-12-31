<?php
/**
 * Plugin Name: Egyszerű Chatbot Készítő
 * Description: Egy egyszerű, PHP-alapú chatbot widget, amelyet shortcode-dal lehet elhelyezni WordPress oldalon.
 * Version: 1.0.0
 * Author: AI Assistant
 */

if (!defined('ABSPATH')) {
    exit;
}

class SimpleChatbotPlugin
{
    const VERSION = '1.0.0';
    const OPTION_KEY = 'simple_chatbot_title';

    public function __construct()
    {
        add_shortcode('simple_chatbot', [$this, 'render_chatbot']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_settings_page']);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style(
            'simple-chatbot-style',
            plugins_url('assets/chatbot.css', __FILE__),
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'simple-chatbot-script',
            plugins_url('assets/chatbot.js', __FILE__),
            [],
            self::VERSION,
            true
        );

        wp_localize_script('simple-chatbot-script', 'SimpleChatbotData', [
            'apiBase' => esc_url_raw(rest_url('simple-chatbot/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'title' => get_option(self::OPTION_KEY, __('Chatbot', 'simple-chatbot')),
        ]);
    }

    public function register_routes()
    {
        register_rest_route('simple-chatbot/v1', '/message', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_message'],
            'permission_callback' => '__return_true',
            'args' => [
                'message' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public function handle_message(\WP_REST_Request $request)
    {
        $message = $request->get_param('message');
        $response = $this->generate_response($message);

        return rest_ensure_response([
            'reply' => $response,
        ]);
    }

    private function generate_response(string $message): string
    {
        $sanitized = trim($message);

        if ($sanitized === '') {
            return __('Kérlek, írj be egy üzenetet!', 'simple-chatbot');
        }

        $normalized = mb_strtolower($sanitized, 'UTF-8');

        $greetings = ['szia', 'helló', 'üdv', 'hello', 'hi', 'hey'];
        foreach ($greetings as $greeting) {
            if (strpos($normalized, $greeting) !== false) {
                return __('Szia! Egy egyszerű AI válaszoló vagyok. Mire vagy kíváncsi?', 'simple-chatbot');
            }
        }

        $faq = [
            'nyitvatart' => __('A nyitvatartásról nincs konkrét adat a rendszerben, de általában 9-17 óra között vagyunk elérhetők online.', 'simple-chatbot'),
            'arak' => __('Árakról itt nem található információ, de szívesen segítek általános kérdésekben vagy tájékoztatásban.', 'simple-chatbot'),
            'kapcsolat' => __('Kapcsolati adatokat nem tárolok, de érdemes az oldal Kapcsolat menüpontját megnézni.', 'simple-chatbot'),
            'segíts' => __('Szívesen segítek! Írd le röviden a kérdésed vagy a problémád, és adok egy rövid választ.', 'simple-chatbot'),
            'help' => __('Szívesen segítek! Írd le röviden a kérdésed vagy a problémád, és adok egy rövid választ.', 'simple-chatbot'),
            'info' => __('Általános tájékoztatást tudok adni. Mondd el, miben kellene információ!', 'simple-chatbot'),
            'köszön' => __('Szívesen, ha van még kérdésed, nyugodtan írd meg!', 'simple-chatbot'),
            'koszon' => __('Szívesen, ha van még kérdésed, nyugodtan írd meg!', 'simple-chatbot'),
            'időjárás' => __('Időjárási adatokat nem tudok lekérni, de nézd meg a kedvenc időjárás appodban!', 'simple-chatbot'),
            'idojaras' => __('Időjárási adatokat nem tudok lekérni, de nézd meg a kedvenc időjárás appodban!', 'simple-chatbot'),
        ];

        foreach ($faq as $keyword => $answer) {
            if (strpos($normalized, $keyword) !== false) {
                return $answer;
            }
        }

        if (substr($normalized, -1) === '?') {
            return sprintf(
                __('Jó kérdés: "%s". Röviden válaszolva: jelenlegi tudásom alapján általános tanácsot tudok adni, de pontos adatokért érdemes az oldal információit megnézni.', 'simple-chatbot'),
                esc_html($sanitized)
            );
        }

        return sprintf(
            __('Értem: "%s". Írj egy kérdést, és igyekszem hasznos választ adni!', 'simple-chatbot'),
            esc_html($sanitized)
        );
    }

    public function render_chatbot($atts)
    {
        $atts = shortcode_atts([
            'title' => get_option(self::OPTION_KEY, __('Chatbot', 'simple-chatbot')),
        ], $atts);

        ob_start();
        ?>
        <div class="simple-chatbot" data-title="<?php echo esc_attr($atts['title']); ?>">
            <div class="simple-chatbot__header"><?php echo esc_html($atts['title']); ?></div>
            <div class="simple-chatbot__messages" aria-live="polite"></div>
            <form class="simple-chatbot__form">
                <label class="screen-reader-text" for="simple-chatbot-input"><?php esc_html_e('Üzenet', 'simple-chatbot'); ?></label>
                <input type="text" id="simple-chatbot-input" name="message" placeholder="<?php esc_attr_e('Írj üzenetet...', 'simple-chatbot'); ?>" required />
                <button type="submit"><?php esc_html_e('Küldés', 'simple-chatbot'); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    public function register_settings()
    {
        register_setting('simple_chatbot_settings', self::OPTION_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => __('Chatbot', 'simple-chatbot'),
        ]);

        add_settings_section(
            'simple_chatbot_main_section',
            __('Chatbot beállítások', 'simple-chatbot'),
            '__return_false',
            'simple_chatbot_settings'
        );

        add_settings_field(
            self::OPTION_KEY,
            __('Chatbot címe', 'simple-chatbot'),
            [$this, 'render_title_field'],
            'simple_chatbot_settings',
            'simple_chatbot_main_section'
        );
    }

    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Egyszerű Chatbot Készítő', 'simple-chatbot'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('simple_chatbot_settings');
                do_settings_sections('simple_chatbot_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_title_field()
    {
        $value = get_option(self::OPTION_KEY, __('Chatbot', 'simple-chatbot'));
        ?>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <?php
    }

    public function register_settings_page()
    {
        add_options_page(
            __('Chatbot beállítások', 'simple-chatbot'),
            __('Chatbot', 'simple-chatbot'),
            'manage_options',
            'simple-chatbot-settings',
            [$this, 'render_settings_page']
        );
    }
}

new SimpleChatbotPlugin();
