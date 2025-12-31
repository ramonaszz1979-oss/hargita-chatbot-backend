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
    const OPTION_KB_FILES = 'simple_chatbot_kb_files';
    const OPTION_BEHAVIOR = 'simple_chatbot_behavior';

    public function __construct()
    {
        add_shortcode('simple_chatbot', [$this, 'render_chatbot']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_post_simple_chatbot_upload', [$this, 'handle_file_upload']);
        add_action('admin_post_simple_chatbot_delete_file', [$this, 'handle_file_delete']);
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

        $behavior = trim(get_option(self::OPTION_BEHAVIOR, __('Segítőkész asszisztens vagy, rövid, magyar nyelvű válaszokat adj.', 'simple-chatbot')));

        $kbText = $this->get_knowledge_context();

        $requestBody = json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => $behavior],
                ['role' => 'system', 'content' => $kbText],
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

        register_setting('simple_chatbot_settings', self::OPTION_BEHAVIOR, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => __('Segítőkész asszisztens vagy, rövid, magyar nyelvű válaszokat adj.', 'simple-chatbot'),
        ]);

        register_setting('simple_chatbot_settings', self::OPTION_KB_FILES, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_kb_files'],
            'default' => [],
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

        add_settings_field(
            self::OPTION_BEHAVIOR,
            __('Chatbot viselkedés (rendszerutasítás)', 'simple-chatbot'),
            [$this, 'render_behavior_field'],
            'simple_chatbot_settings',
            'simple_chatbot_main_section'
        );
    }

    public function render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Egyszerű Chatbot Készítő', 'simple-chatbot'); ?></h1>
            <?php $this->render_notices(); ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('simple_chatbot_settings');
                do_settings_sections('simple_chatbot_settings');
                submit_button();
                ?>
            </form>

            <h2><?php esc_html_e('Tudásanyag feltöltése', 'simple-chatbot'); ?></h2>
            <p><?php esc_html_e('Tölts fel saját szöveges fájlt (TXT, MD vagy PDF), hogy az AI válaszoknál felhasználhassa.', 'simple-chatbot'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('simple_chatbot_upload'); ?>
                <input type="hidden" name="action" value="simple_chatbot_upload" />
                <input type="file" name="simple_chatbot_file" accept=".txt,.md,.pdf,text/plain,text/markdown,application/pdf" />
                <?php submit_button(__('Feltöltés', 'simple-chatbot'), 'secondary', 'submit', false); ?>
            </form>

            <?php $this->render_uploaded_files(); ?>
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

    public function render_behavior_field()
    {
        $value = get_option(self::OPTION_BEHAVIOR, __('Segítőkész asszisztens vagy, rövid, magyar nyelvű válaszokat adj.', 'simple-chatbot'));
        ?>
        <textarea name="<?php echo esc_attr(self::OPTION_BEHAVIOR); ?>" rows="4" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Add meg a rendszerutasítást, amely meghatározza a chatbot viselkedését (pl. hangnem, válaszstílus).', 'simple-chatbot'); ?></p>
        <?php
    }

    public function handle_file_upload()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nincs jogosultságod a feltöltéshez.', 'simple-chatbot'));
        }

        check_admin_referer('simple_chatbot_upload');

        if (empty($_FILES['simple_chatbot_file']['name'])) {
            $this->redirect_with_notice(__('Nem választottál fájlt.', 'simple-chatbot'), 'error');
        }

        $file = $_FILES['simple_chatbot_file'];

        $overrides = [
            'test_form' => false,
            'mimes' => [
                'txt' => 'text/plain',
                'md' => 'text/markdown',
                'pdf' => 'application/pdf',
            ],
        ];

        $movefile = wp_handle_upload($file, $overrides);

        if (isset($movefile['error'])) {
            $this->redirect_with_notice($movefile['error'], 'error');
        }

        $filetype = wp_check_filetype_and_ext($movefile['file'], $movefile['url']);

        if (empty($filetype['ext'])) {
            @unlink($movefile['file']);
            $this->redirect_with_notice(__('A fájl típusa nem támogatott.', 'simple-chatbot'), 'error');
        }

        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_text_field(pathinfo($movefile['file'], PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachId = wp_insert_attachment($attachment, $movefile['file']);

        if (is_wp_error($attachId)) {
            @unlink($movefile['file']);
            $this->redirect_with_notice(__('Nem sikerült a fájl mentése.', 'simple-chatbot'), 'error');
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachId, wp_generate_attachment_metadata($attachId, $movefile['file']));

        $existing = get_option(self::OPTION_KB_FILES, []);
        $existing[] = $attachId;
        update_option(self::OPTION_KB_FILES, $existing);

        $this->redirect_with_notice(__('Fájl sikeresen feltöltve.', 'simple-chatbot'));
    }

    public function handle_file_delete()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nincs jogosultságod a törléshez.', 'simple-chatbot'));
        }

        $fileId = isset($_GET['file_id']) ? intval($_GET['file_id']) : 0;

        check_admin_referer('simple_chatbot_delete_file_' . $fileId);

        if (!$fileId) {
            $this->redirect_with_notice(__('Hiányzó fájl azonosító.', 'simple-chatbot'), 'error');
        }

        $files = get_option(self::OPTION_KB_FILES, []);
        $files = array_filter($files, function ($id) use ($fileId) {
            return intval($id) !== $fileId;
        });

        update_option(self::OPTION_KB_FILES, $files);
        wp_delete_attachment($fileId, true);

        $this->redirect_with_notice(__('A fájl törölve.', 'simple-chatbot'));
    }

    public function redirect_with_notice(string $message, string $type = 'updated')
    {
        $url = add_query_arg(
            [
                'page' => 'simple-chatbot-settings',
                'simple_chatbot_notice' => rawurlencode($message),
                'simple_chatbot_notice_type' => $type,
            ],
            admin_url('options-general.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    public static function sanitize_kb_files($value)
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $value), function ($id) {
            return $id > 0;
        }));
    }

    private function get_knowledge_context(): string
    {
        $fileIds = get_option(self::OPTION_KB_FILES, []);

        if (empty($fileIds)) {
            return __('Nincs feltöltött tudásanyag.', 'simple-chatbot');
        }

        $content = '';

        foreach ($fileIds as $fileId) {
            $mime = get_post_mime_type($fileId);

            $contentFromFile = '';

            $filePath = get_attached_file($fileId);

            if ($filePath && file_exists($filePath)) {
                $contentFromFile = $this->get_file_text($filePath, $mime);
            } else {
                $url = wp_get_attachment_url($fileId);
                if ($url) {
                    $response = wp_remote_get($url);
                    if (!is_wp_error($response)) {
                        $body = wp_remote_retrieve_body($response);
                        if (!empty($body)) {
                            $tmpFile = wp_tempnam($url);
                            if ($tmpFile) {
                                file_put_contents($tmpFile, $body);
                                $contentFromFile = $this->get_file_text($tmpFile, $mime);
                                @unlink($tmpFile);
                            }
                        }
                    }
                }
            }

            if ($contentFromFile !== '') {
                $content .= "\n\n" . $contentFromFile;
            }
        }

        $trimmed = trim($content);

        if ($trimmed === '') {
            return __('Nincs felhasználható tudásanyag.', 'simple-chatbot');
        }

        return __('Felhasználói tudásanyag:', 'simple-chatbot') . "\n" . mb_substr($trimmed, 0, 2000);
    }

    private function get_file_text(string $filePath, string $mime): string
    {
        if (in_array($mime, ['text/plain', 'text/markdown'], true)) {
            $content = @file_get_contents($filePath);
            return is_string($content) ? $content : '';
        }

        if ($mime === 'application/pdf') {
            return $this->extract_pdf_text($filePath);
        }

        return '';
    }

    private function extract_pdf_text(string $filePath): string
    {
        $raw = @file_get_contents($filePath);

        if ($raw === false || $raw === '') {
            return '';
        }

        $text = $this->extract_pdf_text_from_streams($raw);

        if ($text === '') {
            $text = $this->extract_text_fragments($raw);
        }

        return trim(preg_replace('/\s+/', ' ', $text));
    }

    private function extract_pdf_text_from_streams(string $raw): string
    {
        $output = '';

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = $this->try_inflate_stream($stream);
                $output .= ' ' . $this->extract_text_fragments($decoded ?? $stream);
            }
        }

        return trim($output);
    }

    private function try_inflate_stream(string $stream): ?string
    {
        $inflated = @gzuncompress($stream);

        if ($inflated !== false) {
            return $inflated;
        }

        $inflated = @gzinflate($stream);

        if ($inflated !== false) {
            return $inflated;
        }

        return null;
    }

    private function extract_text_fragments(string $content): string
    {
        $text = '';

        if (preg_match_all('/\(([^\)\\]*(?:\\.[^\)\\]*)*)\)/s', $content, $matches)) {
            foreach ($matches[1] as $fragment) {
                $text .= ' ' . $this->decode_pdf_text_fragment($fragment);
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f]+)>/', $content, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                $decoded = @pack('H*', $hex);
                if ($decoded !== false) {
                    $text .= ' ' . $this->decode_pdf_text_fragment($decoded);
                }
            }
        }

        return trim($text);
    }

    private function decode_pdf_text_fragment(string $text): string
    {
        $decoded = str_replace(
            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
            ['(', ')', '\\', "\n", "\r", "\t"],
            $text
        );

        return trim($decoded);
    }

    public function render_notices()
    {
        if (!isset($_GET['simple_chatbot_notice'])) {
            return;
        }

        $notice = sanitize_text_field(wp_unslash($_GET['simple_chatbot_notice']));
        $type = isset($_GET['simple_chatbot_notice_type']) ? sanitize_text_field(wp_unslash($_GET['simple_chatbot_notice_type'])) : 'updated';

        $class = $type === 'error' ? 'notice notice-error' : 'notice notice-success';

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($notice));
    }

    public function render_uploaded_files()
    {
        $fileIds = get_option(self::OPTION_KB_FILES, []);

        if (empty($fileIds)) {
            echo '<p>' . esc_html__('Még nincs feltöltött tudásanyag.', 'simple-chatbot') . '</p>';
            return;
        }

        echo '<h3>' . esc_html__('Feltöltött fájlok', 'simple-chatbot') . '</h3>';
        echo '<ul>';

        foreach ($fileIds as $fileId) {
            $url = wp_get_attachment_url($fileId);
            $title = get_the_title($fileId);

            if (!$url) {
                continue;
            }

            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => 'simple_chatbot_delete_file',
                        'file_id' => $fileId,
                    ],
                    admin_url('admin-post.php')
                ),
                'simple_chatbot_delete_file_' . $fileId
            );

            echo '<li>';
            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($title) . '</a> ';
            echo '<a href="' . esc_url($deleteUrl) . '" class="button-link delete">' . esc_html__('Törlés', 'simple-chatbot') . '</a>';
            echo '</li>';
        }

        echo '</ul>';
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
