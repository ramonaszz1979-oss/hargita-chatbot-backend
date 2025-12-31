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
    const OPTION_API_KEY = 'simple_chatbot_openai_api_key';

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

        $apiKey = trim(get_option(self::OPTION_API_KEY, ''));

        if ($apiKey === '') {
            return __('Nincs megadva OpenAI API-kulcs. Kérd meg az adminisztrátort, hogy a Beállítások → Chatbot oldalon adja meg az API-kulcsot.', 'simple-chatbot');
        }

        $requestBody = json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => __('Segítőkész asszisztens vagy, rövid, magyar nyelvű válaszokat adj.', 'simple-chatbot')],
                ['role' => 'user', 'content' => $sanitized],
            ],
            'max_tokens' => 256,
            'temperature' => 0.7,
        ]);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'body' => $requestBody,
        ]);

        if (is_wp_error($response)) {
            return __('Hiba történt a válasz lekérdezése közben. Próbáld újra később.', 'simple-chatbot');
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($body['choices'][0]['message']['content'])) {
            return __('Nem sikerült AI választ kapni. Kérlek, próbáld meg újra.', 'simple-chatbot');
        }

        return wp_kses_post(trim($body['choices'][0]['message']['content']));
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

        register_setting('simple_chatbot_settings', self::OPTION_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
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

        add_settings_field(
            self::OPTION_API_KEY,
            __('OpenAI API-kulcs', 'simple-chatbot'),
            [$this, 'render_api_key_field'],
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

    public function render_api_key_field()
    {
        $value = get_option(self::OPTION_API_KEY, '');
        ?>
        <input type="password" name="<?php echo esc_attr(self::OPTION_API_KEY); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text" autocomplete="off" />
        <p class="description"><?php esc_html_e('Add meg az OpenAI API-kulcsot (pl. sk-...). A kulcs nem jelenik meg nyilvánosan, de a WordPress adatbázisban tárolódik.', 'simple-chatbot'); ?></p>
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
