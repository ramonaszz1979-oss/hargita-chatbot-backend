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

require_once plugin_dir_path(__FILE__) . 'includes/class-simple-chatbot-crawler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-simple-chatbot-process-manager.php';

class SimpleChatbotPlugin
{
    const VERSION = '1.0.0';
    const OPTION_KEY = 'simple_chatbot_title';
    const OPTION_API_KEY = 'simple_chatbot_openai_api_key';
    const OPTION_KB_FILES = 'simple_chatbot_kb_files';
    const OPTION_KB_URLS = 'simple_chatbot_kb_urls';
    const OPTION_BEHAVIOR = 'simple_chatbot_behavior';
    const OPTION_WELCOME = 'simple_chatbot_welcome_message';

    /** @var SimpleChatbotCrawler */
    private $crawler;

    /** @var SimpleChatbotProcessManager */
    private $processManager;

    public function __construct()
    {
        add_shortcode('simple_chatbot', [$this, 'render_chatbot']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_routes']);
        add_action('init', [$this, 'ensure_cache_directory']);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'maybe_render_embed_page']);

        $this->crawler = new SimpleChatbotCrawler();
        $this->processManager = new SimpleChatbotProcessManager();
    }

    private function get_url_cache_dir(): string
    {
        $upload = wp_upload_dir();

        if (!isset($upload['basedir']) || $upload['basedir'] === '') {
            return '';
        }

        $dir = trailingslashit($upload['basedir']) . 'simple-chatbot/url-cache';

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        return $dir;
    }

    public function ensure_cache_directory()
    {
        // Force the URL cache directory to be created early in the request lifecycle.
        $this->get_url_cache_dir();
    }

    public function register_query_vars($vars)
    {
        $vars[] = 'simple_chatbot_embed';
        return $vars;
    }

    public function maybe_render_embed_page()
    {
        $should_embed = get_query_var('simple_chatbot_embed');

        if (empty($should_embed) && !isset($_GET['simple_chatbot_embed'])) {
            return;
        }

        nocache_headers();
        status_header(200);

        $chatbot_markup = $this->render_chatbot([]);
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <?php wp_head(); ?>
            <style>
                body {
                    margin: 0;
                    background: transparent;
                }

                .simple-chatbot-embed-shell {
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: flex-end;
                    padding: 16px;
                    box-sizing: border-box;
                }

                .simple-chatbot {
                    width: 100%;
                    max-width: 420px;
                }
            </style>
        </head>
        <body class="simple-chatbot-embed-body">
            <?php if (function_exists('wp_body_open')) { wp_body_open(); } ?>
            <div class="simple-chatbot-embed-shell">
                <?php echo $chatbot_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }

    public function enqueue_assets()
    {
        $embedUrl = add_query_arg('simple_chatbot_embed', '1', home_url('/'));
        $embedCode = sprintf(
            '<iframe src="%s" style="border:0;width:100%%;max-width:420px;height:640px;" loading="lazy"></iframe>',
            esc_url($embedUrl)
        );

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
            'welcomeMessage' => $this->get_welcome_message(),
            'canManage' => current_user_can('manage_options'),
            'embedUrl' => esc_url_raw($embedUrl),
            'embedCode' => $embedCode,
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

        register_rest_route('simple-chatbot/v1', '/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'can_manage'],
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_settings'],
                'permission_callback' => [$this, 'can_manage'],
                'args' => [
                    'title' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'api_key' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'behavior' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                    'welcome_message' => [
                        'required' => false,
                        'sanitize_callback' => 'sanitize_textarea_field',
                    ],
                ],
            ],
        ]);

        register_rest_route('simple-chatbot/v1', '/knowledge/upload', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_file_upload_rest'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route('simple-chatbot/v1', '/knowledge/file/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [$this, 'handle_file_delete_rest'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return intval($param) > 0;
                    },
                ],
            ],
        ]);

        register_rest_route('simple-chatbot/v1', '/knowledge/url', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'handle_add_url_rest'],
                'permission_callback' => [$this, 'can_manage'],
                'args' => [
                    'url' => [
                        'required' => true,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'handle_delete_url_rest'],
                'permission_callback' => [$this, 'can_manage'],
                'args' => [
                    'url' => [
                        'required' => true,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ],
        ]);

        register_rest_route('simple-chatbot/v1', '/processes', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'get_processes'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'save_processes'],
                'permission_callback' => [$this, 'can_manage'],
                'args' => [
                    'sections' => [
                        'required' => true,
                    ],
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
        $behaviorSpeedHint = __('Válaszolj tömören, legfeljebb 3 rövid mondatban a gyorsabb működés érdekében.', 'simple-chatbot');

        $kbText = $this->get_knowledge_context();
        $processText = $this->get_process_context();

        $messages = [
            ['role' => 'system', 'content' => $behavior . "\n" . $behaviorSpeedHint],
        ];

        if ($processText !== '') {
            $messages[] = ['role' => 'system', 'content' => $processText];
        }

        if ($kbText !== '') {
            $messages[] = ['role' => 'system', 'content' => $kbText];
        }

        $messages[] = ['role' => 'user', 'content' => $sanitized];

        $requestBody = json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'max_tokens' => 400,
            'temperature' => 0.7,
        ]);

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 12,
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

        $welcome = $this->get_welcome_message();

        ob_start();
        ?>
        <div class="simple-chatbot" data-title="<?php echo esc_attr($atts['title']); ?>" data-welcome="<?php echo esc_attr($welcome); ?>">
            <div class="simple-chatbot__actions">
                <button class="simple-chatbot__button js-simple-chatbot-settings" <?php disabled(!current_user_can('manage_options')); ?>><?php esc_html_e('Beállítások', 'simple-chatbot'); ?></button>
                <button class="simple-chatbot__button simple-chatbot__button--ghost js-simple-chatbot-preview"><?php esc_html_e('Preview', 'simple-chatbot'); ?></button>
            </div>
            <div class="simple-chatbot__panel simple-chatbot__panel--preview">
                <div class="simple-chatbot__header"><?php echo esc_html($atts['title']); ?></div>
                <div class="simple-chatbot__process-nav js-simple-chatbot-process-nav" hidden>
                    <div class="simple-chatbot__process-chips js-simple-chatbot-section-chips" role="tablist" aria-label="<?php esc_attr_e('Folyamat szekciók', 'simple-chatbot'); ?>">
                    </div>
                    <div class="simple-chatbot__process-choices js-simple-chatbot-choices" aria-live="polite"></div>
                </div>
                <div class="simple-chatbot__messages" aria-live="polite"></div>
                <form class="simple-chatbot__form">
                    <label class="screen-reader-text" for="simple-chatbot-input"><?php esc_html_e('Üzenet', 'simple-chatbot'); ?></label>
                    <input type="text" id="simple-chatbot-input" name="message" placeholder="<?php esc_attr_e('Írj üzenetet...', 'simple-chatbot'); ?>" required />
                    <button type="submit"><?php esc_html_e('Küldés', 'simple-chatbot'); ?></button>
                </form>
            </div>
            <div class="simple-chatbot__modal" hidden>
                <div class="simple-chatbot__modal-card">
                    <div class="simple-chatbot__modal-header">
                        <h2><?php esc_html_e('Chatbot beállítások', 'simple-chatbot'); ?></h2>
                        <button class="simple-chatbot__close" type="button" aria-label="<?php esc_attr_e('Bezárás', 'simple-chatbot'); ?>">×</button>
                    </div>
                    <div class="simple-chatbot__modal-body">
                        <div class="simple-chatbot__tabs" role="tablist">
                            <button class="simple-chatbot__tab is-active" type="button" data-tab-target="general" role="tab" aria-selected="true"><?php esc_html_e('Általános', 'simple-chatbot'); ?></button>
                            <button class="simple-chatbot__tab" type="button" data-tab-target="files" role="tab" aria-selected="false"><?php esc_html_e('Fájlok', 'simple-chatbot'); ?></button>
                            <button class="simple-chatbot__tab" type="button" data-tab-target="urls" role="tab" aria-selected="false"><?php esc_html_e('Weboldalak', 'simple-chatbot'); ?></button>
                            <button class="simple-chatbot__tab" type="button" data-tab-target="process" role="tab" aria-selected="false"><?php esc_html_e('Folyamat szerkesztő', 'simple-chatbot'); ?></button>
                        </div>

                        <div class="simple-chatbot__tab-panels">
                            <div class="simple-chatbot__tab-panel is-active" data-tab-panel="general" role="tabpanel">
                                <?php include plugin_dir_path(__FILE__) . 'templates/general-settings-tab.php'; ?>
                            </div>

                            <div class="simple-chatbot__tab-panel" data-tab-panel="files" role="tabpanel" aria-hidden="true">
                                <?php include plugin_dir_path(__FILE__) . 'templates/files-settings-tab.php'; ?>
                            </div>

                            <div class="simple-chatbot__tab-panel" data-tab-panel="urls" role="tabpanel" aria-hidden="true">
                                <?php include plugin_dir_path(__FILE__) . 'templates/urls-settings-tab.php'; ?>
                            </div>

                            <div class="simple-chatbot__tab-panel" data-tab-panel="process" role="tabpanel" aria-hidden="true">
                                <div class="simple-chatbot__section">
                                    <h3><?php esc_html_e('Folyamat szekciók', 'simple-chatbot'); ?></h3>
                                    <p class="simple-chatbot__muted"><?php esc_html_e('Adj meg több szekciót (pl. "Szia, én Hargita megye..."), és a válasz lehetőségeket a plusz gombbal add hozzá (pl. Látnivalók, Szállás, Egyéb).', 'simple-chatbot'); ?></p>
                                    <form class="simple-chatbot__process-form">
                                        <label>
                                            <span><?php esc_html_e('Szekció címe', 'simple-chatbot'); ?></span>
                                            <input type="text" name="process_title" />
                                        </label>
                                        <label>
                                            <span><?php esc_html_e('Űrlap link (nem kötelező)', 'simple-chatbot'); ?></span>
                                            <input type="url" name="process_form_url" placeholder="https://docs.google.com/forms/..." />
                                        </label>
                                        <label>
                                            <span><?php esc_html_e('Űrlap gomb felirata', 'simple-chatbot'); ?></span>
                                            <input type="text" name="process_form_label" placeholder="<?php esc_attr_e('Pl. Jelentkezés', 'simple-chatbot'); ?>" />
                                        </label>
                                        <label class="simple-chatbot__checkbox">
                                            <input type="checkbox" name="process_is_start" />
                                            <span><?php esc_html_e('Legyen ez a start szekció', 'simple-chatbot'); ?></span>
                                        </label>
                                        <label>
                                            <span><?php esc_html_e('Válasz lehetőségek', 'simple-chatbot'); ?></span>
                                            <div class="simple-chatbot__process-new-items js-simple-chatbot-new-items">
                                                <button type="button" class="simple-chatbot__icon-button js-add-process-item" aria-label="<?php esc_attr_e('Új válasz lehetőség hozzáadása', 'simple-chatbot'); ?>">+</button>
                                            </div>
                                        </label>
                                        <div class="simple-chatbot__form-actions">
                                            <button type="submit" class="simple-chatbot__button simple-chatbot__button--primary"><?php esc_html_e('Szekció hozzáadása', 'simple-chatbot'); ?></button>
                                        </div>
                                    </form>
                                    <ul class="simple-chatbot__process-list js-simple-chatbot-processes"></ul>
                                </div>
                            </div>
                        </div>
                        <div class="simple-chatbot__notice js-simple-chatbot-notice" hidden></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function get_settings(): \WP_REST_Response
    {
        return rest_ensure_response([
            'title' => get_option(self::OPTION_KEY, __('Chatbot', 'simple-chatbot')),
            'api_key' => get_option(self::OPTION_API_KEY, ''),
            'behavior' => get_option(self::OPTION_BEHAVIOR, __('Segítőkész asszisztens vagy, rövid, magyar nyelvű válaszokat adj.', 'simple-chatbot')),
            'welcome_message' => $this->get_welcome_message(),
            'files' => $this->prepare_files(),
            'processes' => $this->processManager->get_sections(),
        ]);
    }

    public function save_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $title = $request->get_param('title');
        $apiKey = $request->get_param('api_key');
        $behavior = $request->get_param('behavior');
        $welcomeMessage = $request->get_param('welcome_message');

        $currentTitle = get_option(self::OPTION_KEY, __('Chatbot', 'simple-chatbot'));
        $currentApiKey = get_option(self::OPTION_API_KEY, '');
        $currentBehavior = get_option(
            self::OPTION_BEHAVIOR,
            __('Segítőkész asszisztens vagy, rövid, magyar nyelvű válaszokat adj.', 'simple-chatbot')
        );
        $currentWelcome = $this->get_welcome_message();

        $titleToSave = is_null($title) ? $currentTitle : sanitize_text_field($title);
        $apiKeyToSave = is_null($apiKey) ? $currentApiKey : sanitize_text_field($apiKey);
        $behaviorToSave = is_null($behavior) ? $currentBehavior : sanitize_textarea_field($behavior);
        $welcomeToSave = is_null($welcomeMessage) ? $currentWelcome : sanitize_textarea_field($welcomeMessage);

        update_option(self::OPTION_KEY, $titleToSave);
        update_option(self::OPTION_API_KEY, $apiKeyToSave);
        update_option(self::OPTION_BEHAVIOR, $behaviorToSave);
        update_option(self::OPTION_WELCOME, $welcomeToSave);

        return rest_ensure_response([
            'success' => true,
            'title' => $titleToSave,
            'api_key' => $apiKeyToSave,
            'behavior' => $behaviorToSave,
            'welcome_message' => $welcomeToSave,
            'files' => $this->prepare_files(),
            'processes' => $this->processManager->get_sections(),
        ]);
    }

    public function get_processes(): \WP_REST_Response
    {
        return rest_ensure_response([
            'sections' => $this->processManager->get_sections(),
        ]);
    }

    public function save_processes(\WP_REST_Request $request): \WP_REST_Response
    {
        $payload = $request->get_json_params();
        $sections = isset($payload['sections']) ? $payload['sections'] : $request->get_param('sections');

        if ($sections === null) {
            return new \WP_REST_Response(['message' => __('Hiányzó szekció adatok.', 'simple-chatbot')], 400);
        }

        $saved = $this->processManager->save_sections($sections);

        return rest_ensure_response([
            'message' => __('Folyamat szekciók elmentve.', 'simple-chatbot'),
            'sections' => $saved,
        ]);
    }

    public function handle_file_upload_rest(\WP_REST_Request $request): \WP_REST_Response
    {
        if (empty($_FILES['file']['name'])) {
            return new \WP_REST_Response(['message' => __('Nem választottál fájlt.', 'simple-chatbot')], 400);
        }

        $file = $_FILES['file'];

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
            return new \WP_REST_Response(['message' => $movefile['error']], 400);
        }

        $filetype = wp_check_filetype_and_ext($movefile['file'], $movefile['url']);

        if (empty($filetype['ext'])) {
            @unlink($movefile['file']);
            return new \WP_REST_Response(['message' => __('A fájl típusa nem támogatott.', 'simple-chatbot')], 400);
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
            return new \WP_REST_Response(['message' => __('Nem sikerült a fájl mentése.', 'simple-chatbot')], 500);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata($attachId, wp_generate_attachment_metadata($attachId, $movefile['file']));

        $cacheDir = $this->get_url_cache_dir();

        if ($cacheDir !== '') {
            $this->cache_file_text($attachId, $movefile['file'], $filetype['type'], $cacheDir);
        }

        $existing = get_option(self::OPTION_KB_FILES, []);
        $existing[] = $attachId;
        update_option(self::OPTION_KB_FILES, $existing);

        return rest_ensure_response([
            'message' => __('Fájl sikeresen feltöltve.', 'simple-chatbot'),
            'files' => $this->prepare_files(),
        ]);
    }

    public function handle_file_delete_rest(\WP_REST_Request $request): \WP_REST_Response
    {
        $fileId = intval($request->get_param('id'));

        $files = get_option(self::OPTION_KB_FILES, []);
        $files = array_filter($files, function ($id) use ($fileId) {
            return intval($id) !== $fileId;
        });

        update_option(self::OPTION_KB_FILES, $files);
        wp_delete_attachment($fileId, true);

        $cacheDir = $this->get_url_cache_dir();
        $cachePath = $this->build_file_cache_path($fileId, $cacheDir);

        if ($cachePath && file_exists($cachePath)) {
            @unlink($cachePath);
        }

        return rest_ensure_response([
            'message' => __('A fájl törölve.', 'simple-chatbot'),
            'files' => $this->prepare_files(),
        ]);
    }

    public function handle_add_url_rest(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = $request->get_param('url');
        $validated = wp_http_validate_url($url);

        if (!$validated) {
            return new \WP_REST_Response(['message' => __('Érvénytelen URL.', 'simple-chatbot')], 400);
        }

        $existing = get_option(self::OPTION_KB_URLS, []);
        $existing[] = $validated;
        update_option(self::OPTION_KB_URLS, self::sanitize_kb_urls($existing));

        $cacheDir = $this->get_url_cache_dir();
        $cachedContent = $this->crawler->get_cached_or_collect($validated, $cacheDir);

        $message = __('URL sikeresen hozzáadva.', 'simple-chatbot');

        if ($cachedContent === '') {
            $message .= ' ' . __('Nem sikerült tartalmat letölteni, ellenőrizd az oldalt.', 'simple-chatbot');
        }

        return rest_ensure_response([
            'message' => $message,
            'urls' => get_option(self::OPTION_KB_URLS, []),
        ]);
    }

    public function handle_delete_url_rest(\WP_REST_Request $request): \WP_REST_Response
    {
        $url = $request->get_param('url');

        $urls = get_option(self::OPTION_KB_URLS, []);
        $urls = array_filter($urls, function ($item) use ($url) {
            return $item !== $url;
        });

        update_option(self::OPTION_KB_URLS, array_values($urls));

        return rest_ensure_response([
            'message' => __('Az URL törölve.', 'simple-chatbot'),
            'urls' => get_option(self::OPTION_KB_URLS, []),
        ]);
    }

    public function can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    private function prepare_files(): array
    {
        $fileIds = get_option(self::OPTION_KB_FILES, []);
        $files = [];

        foreach ($fileIds as $fileId) {
            $url = wp_get_attachment_url($fileId);
            if (!$url) {
                continue;
            }

            $files[] = [
                'id' => $fileId,
                'title' => get_the_title($fileId),
                'url' => $url,
                'mime' => get_post_mime_type($fileId),
            ];
        }

        return $files;
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

        register_setting('simple_chatbot_settings', self::OPTION_KB_URLS, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize_kb_urls'],
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

        add_settings_field(
            self::OPTION_WELCOME,
            __('Beköszönő üzenet', 'simple-chatbot'),
            [$this, 'render_welcome_field'],
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

            <h2><?php esc_html_e('Tudásanyag weboldalak', 'simple-chatbot'); ?></h2>
            <p><?php esc_html_e('Adj meg webcímeket (URL), amelyek teljes oldalstruktúráját – a főoldalt és az aloldalakat – beemeljük a chatbot kontextusába.', 'simple-chatbot'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('simple_chatbot_add_url'); ?>
                <input type="hidden" name="action" value="simple_chatbot_add_url" />
                <input type="url" name="simple_chatbot_url" class="regular-text" placeholder="https://pelda.hu" />
                <?php submit_button(__('URL hozzáadása', 'simple-chatbot'), 'secondary', 'submit', false); ?>
            </form>

            <?php $this->render_urls(); ?>
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

    public function render_welcome_field()
    {
        $value = $this->get_welcome_message();
        ?>
        <textarea name="<?php echo esc_attr(self::OPTION_WELCOME); ?>" rows="2" class="large-text code"><?php echo esc_textarea($value); ?></textarea>
        <p class="description"><?php esc_html_e('Ez a beköszönő üzenet jelenik meg a chat ablak elején.', 'simple-chatbot'); ?></p>
        <?php
    }

    private function get_welcome_message(): string
    {
        $title = get_option(self::OPTION_KEY, __('Chatbot', 'simple-chatbot'));
        $default = sprintf(__('%s bekapcsolva. Írj egy kérdést!', 'simple-chatbot'), $title);

        $stored = get_option(self::OPTION_WELCOME, '');

        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        return $default;
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

    public function handle_add_url()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nincs jogosultságod az URL hozzáadásához.', 'simple-chatbot'));
        }

        check_admin_referer('simple_chatbot_add_url');

        $url = isset($_POST['simple_chatbot_url']) ? wp_unslash($_POST['simple_chatbot_url']) : '';
        $validated = wp_http_validate_url(esc_url_raw($url));

        if (!$validated) {
            $this->redirect_with_notice(__('Érvénytelen URL.', 'simple-chatbot'), 'error');
        }

        $existing = get_option(self::OPTION_KB_URLS, []);
        $existing[] = $validated;

        update_option(self::OPTION_KB_URLS, self::sanitize_kb_urls($existing));

        $this->redirect_with_notice(__('URL sikeresen hozzáadva.', 'simple-chatbot'));
    }

    public function handle_delete_url()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Nincs jogosultságod az URL törléséhez.', 'simple-chatbot'));
        }

        $url = isset($_GET['simple_chatbot_url']) ? wp_unslash($_GET['simple_chatbot_url']) : '';

        check_admin_referer('simple_chatbot_delete_url_' . md5($url));

        if ($url === '') {
            $this->redirect_with_notice(__('Hiányzó URL.', 'simple-chatbot'), 'error');
        }

        $urls = get_option(self::OPTION_KB_URLS, []);
        $urls = array_filter($urls, function ($item) use ($url) {
            return $item !== $url;
        });

        update_option(self::OPTION_KB_URLS, array_values($urls));

        $this->redirect_with_notice(__('Az URL törölve.', 'simple-chatbot'));
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

    public static function sanitize_kb_urls($value)
    {
        if (!is_array($value)) {
            return [];
        }

        $clean = [];

        foreach ($value as $url) {
            $validated = wp_http_validate_url(esc_url_raw($url));
            if ($validated) {
                $clean[] = $validated;
            }
        }

        return array_values(array_unique($clean));
    }

    private function get_knowledge_context(): string
    {
        $fileIds = get_option(self::OPTION_KB_FILES, []);
        $cacheDir = $this->get_url_cache_dir();

        $parts = [];

        $archiveText = $this->collect_site_archive_texts();
        if ($archiveText !== '') {
            $parts[] = $archiveText;
        }

        foreach ($fileIds as $fileId) {
            $contentFromFile = $this->get_cached_file_text($fileId, $cacheDir);

            if ($contentFromFile !== '') {
                $parts[] = $contentFromFile;
            }
        }

        if (empty($parts)) {
            $fallbackUrl = 'https://hargita.smartonlineedu.com/site-arhiver/';
            $fallbackContent = $this->crawler->get_cached_or_collect($fallbackUrl, $cacheDir);

            if ($fallbackContent !== '') {
                $parts[] = $fallbackContent;
            }
        }

        if (empty($parts)) {
            return __('Nincs felhasználható tudásanyag.', 'simple-chatbot');
        }

        $trimmed = trim(implode("\n\n", $parts));

        if ($trimmed === '') {
            return __('Nincs felhasználható tudásanyag.', 'simple-chatbot');
        }

        return __('Felhasználói tudásanyag:', 'simple-chatbot') . "\n" . mb_substr($trimmed, 0, 1500);
    }

    private function collect_site_archive_texts(): string
    {
        $uploads = wp_upload_dir();

        if (!isset($uploads['basedir']) || $uploads['basedir'] === '') {
            return '';
        }

        $archiveDir = trailingslashit($uploads['basedir']) . 'site-text-archives';

        if (!is_dir($archiveDir)) {
            return '';
        }

        $buffer = '';

        try {
            foreach (new \FilesystemIterator($archiveDir, \FilesystemIterator::SKIP_DOTS) as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $filetype = wp_check_filetype($path);
                $mime = isset($filetype['type']) ? $filetype['type'] : '';

                if ($mime === '' && function_exists('mime_content_type')) {
                    $mime = (string) mime_content_type($path);
                }

                $content = $this->get_file_text($path, $mime);

                if ($content !== '') {
                    $buffer .= "\n\n" . $content;
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return trim($buffer);
    }

    private function get_process_context(): string
    {
        $sections = $this->processManager->get_sections();

        if (empty($sections)) {
            return '';
        }

        $text = $this->processManager->render_sections_as_text($sections);

        if ($text === '') {
            return '';
        }

        return __('Folyamat szekciók, amelyeket tarts be:', 'simple-chatbot') . "\n" . $text;
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

        $text = $this->compact_staggered_pdf_text($text);

        $text = preg_replace('/\s+/', ' ', str_replace(["\r"], "\n", $text));

        return trim(preg_replace(['/(\n\s*){3,}/', '/\s{2,}/'], ["\n\n", ' '], $text));
    }

    private function extract_pdf_text_from_streams(string $raw): string
    {
        $output = '';

        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $streams)) {
            foreach ($streams[1] as $stream) {
                $decoded = $this->try_inflate_stream($stream);
                $output .= "\n" . $this->extract_text_fragments($decoded ?? $stream);
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
        $normalized = $this->mark_pdf_newlines($content);
        $text = '';

        if (preg_match_all('/\(([^\)\\]*(?:\\.[^\)\\]*)*)\)/s', $normalized, $matches)) {
            foreach ($matches[1] as $fragment) {
                $text .= ' ' . $this->decode_pdf_text_fragment($fragment);
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f]+)>/', $normalized, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                $decoded = @pack('H*', $hex);

                if ($decoded !== false) {
                    $text .= ' ' . $this->decode_pdf_text_fragment($decoded);
                }
            }
        }

        return trim(preg_replace('/\s+\n/', "\n", str_replace($this->pdf_newline_marker(), "\n", $text)));
    }

    private function decode_pdf_text_fragment(string $text): string
    {
        $decoded = str_replace(
            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
            ['(', ')', '\\', "\n", "\r", "\t"],
            $text
        );

        if (strpos($decoded, "\xFE\xFF") === 0 || strpos($decoded, "\xFF\xFE") === 0) {
            $utf16 = substr($decoded, 2);
            $converted = @mb_convert_encoding($utf16, 'UTF-8', 'UTF-16');

            if (is_string($converted) && $converted !== '') {
                $decoded = $converted;
            }
        }

        return trim(preg_replace('/\s+/', ' ', $decoded));
    }

    private function mark_pdf_newlines(string $content): string
    {
        $marker = $this->pdf_newline_marker();

        $patterns = [
            '/\)\s*(Tj|TJ)/',
            '/<([0-9A-Fa-f]+)>\s*(Tj|TJ)/',
            '/\)\s*[\'\"]/',
            '/\sT\*/',
            '/\)\s*Td/'
        ];

        $replacements = ["){$marker}", "<$1>{$marker}", "){$marker}", $marker, "){$marker}"];

        return preg_replace($patterns, $replacements, $content);
    }

    private function compact_staggered_pdf_text(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $text);

        if (!is_string($clean)) {
            $clean = $text;
        }

        $tokens = preg_split('/\s+/', $clean);

        if (!is_array($tokens)) {
            return $clean;
        }

        $rebuilt = [];
        $buffer = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            if (preg_match('/^[\p{L}\p{N}]$/u', $token)) {
                $buffer[] = $token;
                continue;
            }

            if (!empty($buffer)) {
                $rebuilt[] = count($buffer) >= 4 ? implode('', $buffer) : implode(' ', $buffer);
                $buffer = [];
            }

            $rebuilt[] = $token;
        }

        if (!empty($buffer)) {
            $rebuilt[] = count($buffer) >= 4 ? implode('', $buffer) : implode(' ', $buffer);
        }

        $collapsed = preg_replace('/\s{2,}/', ' ', implode(' ', $rebuilt));

        if (!is_string($collapsed)) {
            $collapsed = implode(' ', $rebuilt);
        }

        $collapsed = preg_replace('/�+/u', ' ', $collapsed);

        return is_string($collapsed) ? $collapsed : $clean;
    }

    private function pdf_newline_marker(): string
    {
        return '[[SCB_NL]]';
    }

    private function get_cached_file_text(int $fileId, string $cacheDir): string
    {
        if ($cacheDir === '') {
            return $this->read_file_text($fileId);
        }

        $cachePath = $this->build_file_cache_path($fileId, $cacheDir);

        if ($cachePath && file_exists($cachePath) && filesize($cachePath) > 0) {
            $cached = @file_get_contents($cachePath);

            if (is_string($cached) && trim($cached) !== '') {
                return $cached;
            }
        }

        $text = $this->read_file_text($fileId);

        if ($cachePath && $text !== '') {
            @file_put_contents($cachePath, $text);
        }

        return $text;
    }

    private function cache_file_text(int $fileId, string $filePath, string $mime, string $cacheDir): void
    {
        $cachePath = $this->build_file_cache_path($fileId, $cacheDir);

        if (!$cachePath) {
            return;
        }

        $text = $this->get_file_text($filePath, $mime);

        if ($text === '') {
            return;
        }

        @file_put_contents($cachePath, $text);
    }

    private function read_file_text(int $fileId): string
    {
        $mime = get_post_mime_type($fileId);

        $filePath = get_attached_file($fileId);

        if ($filePath && file_exists($filePath)) {
            return $this->get_file_text($filePath, $mime);
        }

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

                        return $contentFromFile;
                    }
                }
            }
        }

        return '';
    }

    private function build_file_cache_path(int $fileId, string $cacheDir): string
    {
        if ($cacheDir === '') {
            return '';
        }

        return trailingslashit($cacheDir) . 'kb_file_' . $fileId . '.txt';
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

    public function render_urls()
    {
        $urls = get_option(self::OPTION_KB_URLS, []);

        if (empty($urls)) {
            echo '<p>' . esc_html__('Még nincs megadott webcím.', 'simple-chatbot') . '</p>';
            return;
        }

        echo '<h3>' . esc_html__('Hozzáadott weboldalak', 'simple-chatbot') . '</h3>';
        echo '<ul>';

        foreach ($urls as $url) {
            $deleteUrl = wp_nonce_url(
                add_query_arg(
                    [
                        'action' => 'simple_chatbot_delete_url',
                        'simple_chatbot_url' => rawurlencode($url),
                    ],
                    admin_url('admin-post.php')
                ),
                'simple_chatbot_delete_url_' . md5($url)
            );

            echo '<li>';
            echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a> ';
            echo '<a href="' . esc_url($deleteUrl) . '" class="button-link delete">' . esc_html__('Törlés', 'simple-chatbot') . "</a>";
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
