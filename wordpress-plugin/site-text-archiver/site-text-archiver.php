<?php
/**
 * Plugin Name: Site Text Archiver
 * Description: Linkelj be egy vagy több weboldalt, és a bővítmény letölti az összes aloldalt szöveges fájlokba a feltöltések mappájában.
 * Version: 1.0.0
 * Author: OpenAI
 */

if (!defined('ABSPATH')) {
    exit;
}

class Site_Text_Archiver {
    private $option_name = 'sta_target_urls';
    private $archive_meta_option = 'sta_archive_meta';
    private $archive_dir = 'site-text-archives';
    private $progress_option = 'sta_crawl_progress';
    private $stop_option = 'sta_crawl_stop';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('site_text_archiver_form', [$this, 'render_frontend_shortcode']);

        add_action('wp_ajax_sta_start_crawl', [$this, 'ajax_start_crawl']);
        add_action('wp_ajax_sta_poll_crawl', [$this, 'ajax_poll_crawl']);
        add_action('wp_ajax_sta_stop_crawl', [$this, 'ajax_stop_crawl']);
    }

    public function register_settings() {
        register_setting('sta_options', $this->option_name, [$this, 'sanitize_urls']);
    }

    public function sanitize_urls($input) {
        if (is_array($input)) {
            $input = implode("\n", $input);
        }

        $lines  = preg_split('/\r?\n/', (string) $input);
        $urls   = [];

        foreach ($lines as $line) {
            $url = trim($line);
            if (empty($url)) {
                continue;
            }

            $normalized = $this->normalize_url($url);
            if ($normalized) {
                $urls[] = $normalized;
            }
        }

        return array_values(array_unique($urls));
    }

    public function register_menu() {
        add_submenu_page(
            'tools.php',
            __('Site Text Archiver', 'site-text-archiver'),
            __('Site Text Archiver', 'site-text-archiver'),
            'manage_options',
            'sta-archiver',
            [$this, 'render_page']
        );
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        [$messages, $saved_urls] = $this->handle_form_submission(true);
        $messages                = array_merge($messages, $this->handle_archive_deletion(true));
        $storage_hint            = $this->get_storage_hint();
        $archives                = $this->get_archived_sites();
        $archive_map             = $this->archives_by_slug($archives);
        $form_id                 = 'sta-admin-form-' . wp_generate_uuid4();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Site Text Archiver', 'site-text-archiver'); ?></h1>
            <p><?php esc_html_e('Add hozzá a feldolgozandó weboldalakat, majd indítsd a letöltést.', 'site-text-archiver'); ?></p>
            <p>
                <a class="button" href="https://hargita.smartonlineedu.com/site-arhiver/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Tartalomarchiváló oldal megnyitása', 'site-text-archiver'); ?></a>
            </p>
            <?php $this->render_messages($messages); ?>

            <form method="post" action="" class="sta-crawl-form" id="<?php echo esc_attr($form_id); ?>">
                <?php wp_nonce_field('sta_crawl_action', 'sta_crawl_nonce'); ?>
                <input type="hidden" name="sta_action" value="run" />
                <?php $this->render_url_fields($saved_urls, false, $archive_map); ?>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Letöltés indítása', 'site-text-archiver'); ?></button>
                    <button type="submit" class="button button-secondary" name="sta_action" value="save"><?php esc_html_e('Csak mentés', 'site-text-archiver'); ?></button>
                </p>
                <p><em><?php echo esc_html($storage_hint); ?></em></p>
            </form>
            <?php $this->render_progress_ui($form_id); ?>
            <?php $this->render_archive_list($archives, true); ?>
        </div>
        <?php
    }

    public function render_frontend_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Kérjük, jelentkezz be az archiválás indításához.', 'site-text-archiver') . '</p>';
        }

        if (!current_user_can('manage_options')) {
            return '<p>' . esc_html__('Nincs jogosultságod a Site Text Archiver használatához.', 'site-text-archiver') . '</p>';
        }

        [$messages, $saved_urls] = $this->handle_form_submission(false);
        $messages                = array_merge($messages, $this->handle_archive_deletion(false));
        $storage_hint            = $this->get_storage_hint();
        $archives                = $this->get_archived_sites();
        $archive_map             = $this->archives_by_slug($archives);
        $form_id                 = 'sta-frontend-form-' . wp_generate_uuid4();

        ob_start();
        ?>
        <div class="sta-frontend">
            <h2><?php echo esc_html__('Site Text Archiver', 'site-text-archiver'); ?></h2>
            <p><?php esc_html_e('Add hozzá a feldolgozandó weboldalakat, majd indítsd a letöltést.', 'site-text-archiver'); ?></p>
            <p>
                <a class="button" href="https://hargita.smartonlineedu.com/site-arhiver/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Tartalomarchiváló oldal megnyitása', 'site-text-archiver'); ?></a>
            </p>
            <?php $this->render_messages($messages); ?>

            <form method="post" action="" class="sta-crawl-form" id="<?php echo esc_attr($form_id); ?>">
                <?php wp_nonce_field('sta_crawl_action', 'sta_crawl_nonce'); ?>
                <input type="hidden" name="sta_action" value="run" />
                <?php $this->render_url_fields($saved_urls, true, $archive_map); ?>
                <p>
                    <button type="submit" class="button button-primary"><?php esc_html_e('Letöltés indítása', 'site-text-archiver'); ?></button>
                    <button type="submit" class="button button-secondary" name="sta_action" value="save"><?php esc_html_e('Csak mentés', 'site-text-archiver'); ?></button>
                </p>
                <p><em><?php echo esc_html($storage_hint); ?></em></p>
            </form>
            <?php $this->render_progress_ui($form_id); ?>
            <?php $this->render_archive_list($archives, false); ?>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_storage_hint() {
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']) . $this->archive_dir . '/<domain>/';

        return sprintf(
            __('A letöltött .txt fájlokat itt találod: %s', 'site-text-archiver'),
            $base_dir
        );
    }

    private function handle_form_submission($require_capability = true) {
        $messages   = [];
        $saved_urls = get_option($this->option_name, []);

        if (empty($_POST['sta_action']) || $_POST['sta_action'] === 'delete_archive') {
            return [$messages, $saved_urls];
        }

        if ($require_capability && !current_user_can('manage_options')) {
            $messages[] = ['type' => 'error', 'text' => __('Nincs jogosultságod a Site Text Archiver használatához.', 'site-text-archiver')];
            return [$messages, $saved_urls];
        }

        check_admin_referer('sta_crawl_action', 'sta_crawl_nonce');

        $submitted_urls = isset($_POST[$this->option_name]) ? wp_unslash($_POST[$this->option_name]) : '';
        $urls           = $this->sanitize_urls($submitted_urls);

        update_option($this->option_name, $urls);

        if ($_POST['sta_action'] === 'run') {
            if (empty($urls)) {
                $messages[] = ['type' => 'error', 'text' => __('Nincs érvényes URL megadva.', 'site-text-archiver')];
            } else {
                $overwrite = $this->sanitize_overwrite_request();
                $log      = $this->process_urls($urls, $overwrite);
                $messages = array_merge($messages, $log);
            }
        } else {
            $messages[] = ['type' => 'updated', 'text' => __('Beállítások mentve.', 'site-text-archiver')];
        }

        return [$messages, $urls];
    }

    private function render_url_fields($saved_urls, $is_frontend = false, $archives = []) {
        $field_id   = uniqid('sta-fields-');
        $urls       = !empty($saved_urls) ? $saved_urls : [''];
        $field_name = esc_attr($this->option_name) . '[]';
        $classes    = $is_frontend ? 'sta-url-fields sta-url-fields--frontend' : 'sta-url-fields';
        ?>
        <div class="<?php echo esc_attr($classes); ?>" id="<?php echo esc_attr($field_id); ?>" data-existing="<?php echo esc_attr(wp_json_encode(array_keys($archives))); ?>">
            <div class="sta-url-rows">
                <?php foreach ($urls as $url) :
                    $slug        = $this->slug_from_url($url);
                    $has_archive = $slug && isset($archives[$slug]);
                ?>
                    <div class="sta-url-row">
                        <input type="url" name="<?php echo $field_name; ?>" value="<?php echo esc_attr($url); ?>" placeholder="https://pelda.hu" style="width: 75%; max-width: 520px;" />
                        <button type="button" class="button sta-remove-url" aria-label="<?php esc_attr_e('Weboldal törlése', 'site-text-archiver'); ?>">&times;</button>
                        <?php if ($has_archive) : ?>
                            <div class="sta-archive-reload-hint">
                                <small><?php printf(
                                    /* translators: %s archive path */
                                    esc_html__('Korábban letöltve. Régi archívum törlése új futtatás előtt? (%s)', 'site-text-archiver'),
                                    esc_html($archives[$slug]['path'])
                                ); ?></small><br />
                                <label>
                                    <input type="checkbox" name="sta_overwrite[<?php echo esc_attr($slug); ?>]" value="1" />
                                    <?php esc_html_e('Igen, töröld a régi fájlokat, majd töltsd le újra.', 'site-text-archiver'); ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p>
                <button type="button" class="button sta-add-url">+ <?php esc_html_e('Új weboldal hozzáadása', 'site-text-archiver'); ?></button>
            </p>
        </div>
        <style>
            #<?php echo esc_attr($field_id); ?> .sta-url-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; }
            #<?php echo esc_attr($field_id); ?> .sta-url-row input { flex: 1; }
        </style>
        <script>
            (function() {
                const root = document.getElementById('<?php echo esc_js($field_id); ?>');
                if (!root) { return; }

                const rows = root.querySelector('.sta-url-rows');
                const addButton = root.querySelector('.sta-add-url');
                const existingSlugs = (() => {
                    try {
                        return JSON.parse(root.dataset.existing || '[]');
                    } catch (e) {
                        return [];
                    }
                })();

                const createRow = (value = '') => {
                    const row = document.createElement('div');
                    row.className = 'sta-url-row';

                    const input = document.createElement('input');
                    input.type = 'url';
                    input.name = '<?php echo esc_js($this->option_name); ?>[]';
                    input.placeholder = 'https://pelda.hu';
                    input.value = value;
                    input.style.width = '75%';
                    input.style.maxWidth = '520px';

                    const remove = document.createElement('button');
                    remove.type = 'button';
                    remove.className = 'button sta-remove-url';
                    remove.setAttribute('aria-label', '<?php echo esc_js(__('Weboldal törlése', 'site-text-archiver')); ?>');
                    remove.textContent = '×';

                    row.appendChild(input);
                    row.appendChild(remove);
                    bindRemove(remove, row);

                    return row;
                };

                const bindRemove = (button, row) => {
                    button.addEventListener('click', () => {
                        row.remove();
                        if (!rows.children.length) {
                            rows.appendChild(createRow(''));
                        }
                    });
                };

                Array.prototype.forEach.call(rows.querySelectorAll('.sta-remove-url'), (btn) => {
                    const row = btn.closest('.sta-url-row');
                    if (row) {
                        bindRemove(btn, row);
                    }
                });

                addButton.addEventListener('click', () => {
                    rows.appendChild(createRow(''));
                });

                const slugify = (url) => {
                    let host = '';
                    try {
                        host = new URL(url).host;
                    } catch (e) {
                        host = (url || '').replace(/^https?:\/\//i, '').split('/')[0];
                    }

                    return host
                        .toLowerCase()
                        .replace(/[^a-z0-9\-]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                };

                const form = root.closest('form');
                if (form) {
                    form.addEventListener('submit', (event) => {
                        if (event.submitter && event.submitter.value === 'save') {
                            return;
                        }

                        const seen = new Set();

                        Array.prototype.forEach.call(rows.querySelectorAll('input[type="url"]'), (input) => {
                            const slug = slugify(input.value);
                            if (!slug || seen.has(slug) || existingSlugs.indexOf(slug) === -1) {
                                return;
                            }

                            seen.add(slug);

                            const checkbox = form.querySelector('input[name="sta_overwrite[' + slug + ']"]');
                            if (checkbox && checkbox.checked) {
                                return;
                            }

                            const wantsDelete = window.confirm('<?php echo esc_js(__('Ez a weboldal korábban le lett töltve. Töröljük a régi fájlokat az új futtatás előtt?', 'site-text-archiver')); ?>');
                            if (wantsDelete) {
                                if (checkbox) {
                                    checkbox.checked = true;
                                } else {
                                    const hidden = document.createElement('input');
                                    hidden.type = 'hidden';
                                    hidden.name = 'sta_overwrite[' + slug + ']';
                                    hidden.value = '1';
                                    form.appendChild(hidden);
                                }
                            }
                        });
                    });
                }
            })();
        </script>
        <?php
    }

    private function process_urls(array $urls, array $overwrite_request, $run_id = '') {
        $messages = [];

        foreach ($urls as $url) {
            $log = $this->crawl_site($url, $overwrite_request, $run_id);
            $messages[] = ['type' => $log['success'] ? 'updated' : 'error', 'text' => $log['message']];

            if ($run_id && $this->should_stop($run_id)) {
                break;
            }
        }

        return $messages;
    }

    private function crawl_site($url, array $overwrite_request, $run_id = '') {
        $host = wp_parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            return ['success' => false, 'message' => sprintf(__('Hibás URL: %s', 'site-text-archiver'), $url)];
        }

        $queue   = [$url];
        $visited = [];
        $saved   = 0;

        $slug = sanitize_title($host);

        $upload_dir     = wp_upload_dir();
        $base_dir       = trailingslashit($upload_dir['basedir']) . $this->archive_dir . '/' . $slug;
        $had_archives   = is_dir($base_dir);
        $should_replace = !empty($overwrite_request[$slug]);

        wp_mkdir_p($base_dir);

        if ($had_archives && $should_replace) {
            $this->delete_directory($base_dir);
            wp_mkdir_p($base_dir);
        }

        while (!empty($queue)) {
            if ($run_id && $this->should_stop($run_id)) {
                $this->update_progress($run_id, [
                    'status'       => 'stopped',
                    'current_host' => $host,
                    'message'      => __('A letöltés megszakítva.', 'site-text-archiver'),
                ]);

                return [
                    'success' => false,
                    'message' => sprintf(__('Megállítva: %s', 'site-text-archiver'), $host),
                ];
            }

            $current = array_shift($queue);
            $normalized = $this->normalize_url($current);

            if (empty($normalized) || isset($visited[$normalized])) {
                continue;
            }

            $visited[$normalized] = true;
            $response = wp_remote_get($normalized, [
                'timeout' => 15,
                'redirection' => 3,
                'user-agent' => 'Site-Text-Archiver/1.0'
            ]);

            if (is_wp_error($response)) {
                continue;
            }

            $status = wp_remote_retrieve_response_code($response);
            if ($status !== 200) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                continue;
            }

            $this->save_page_as_text($base_dir, $normalized, $body);
            $saved++;

            if ($run_id) {
                $this->update_progress($run_id, [
                    'status'       => 'running',
                    'current_host' => $host,
                    'saved'        => $saved,
                    'last_url'     => $normalized,
                ]);
            }

            foreach ($this->extract_links($body, $host, $normalized) as $link) {
                if (!isset($visited[$link]) && !in_array($link, $queue, true)) {
                    $queue[] = $link;
                }
            }
        }

        $message = sprintf(
            __('%1$s: %2$d oldal mentve a(z) %3$s mappába.', 'site-text-archiver'),
            $host,
            $saved,
            $base_dir
        );

        if ($had_archives && !$should_replace) {
            $message .= ' ' . __('A korábbi archívum érintetlen maradt; ha törölni szeretnéd új letöltés előtt, jelöld be a törlés jelölőnégyzetét.', 'site-text-archiver');
        }

        $this->record_archive_meta($slug, $url);

        return ['success' => true, 'message' => $message];
    }

    private function extract_links($html, $host, $base_url) {
        $links = [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();

        if (!$loaded) {
            return $links;
        }

        $anchor_tags = $dom->getElementsByTagName('a');
        foreach ($anchor_tags as $anchor) {
            $href = $anchor->getAttribute('href');
            if (empty($href)) {
                continue;
            }

            $resolved = $this->resolve_url($href, $base_url);
            if (!$resolved) {
                continue;
            }

            $resolved_host = wp_parse_url($resolved, PHP_URL_HOST);
            if ($resolved_host !== $host) {
                continue;
            }

            $normalized = $this->normalize_url($resolved);
            if ($normalized) {
                $links[] = $normalized;
            }
        }

        return array_values(array_unique($links));
    }

    private function resolve_url($href, $base_url) {
        // Ignore fragments and mailto/tel schemes.
        if (strpos($href, '#') === 0 || preg_match('/^(mailto|tel):/i', $href)) {
            return '';
        }

        // Protocol-relative URLs
        if (strpos($href, '//') === 0) {
            $scheme = wp_parse_url($base_url, PHP_URL_SCHEME) ?: 'https';
            $href   = $scheme . ':' . $href;
        }

        $parsed_base = wp_parse_url($base_url);
        if (!$parsed_base || empty($parsed_base['scheme']) || empty($parsed_base['host'])) {
            return '';
        }

        // Absolute URL
        if (!empty(wp_parse_url($href, PHP_URL_SCHEME))) {
            return $href;
        }

        // Relative path
        $base_path = isset($parsed_base['path']) ? $parsed_base['path'] : '/';
        $combined  = rtrim($parsed_base['scheme'] . '://' . $parsed_base['host'], '/');

        if (!empty($parsed_base['port'])) {
            $combined .= ':' . $parsed_base['port'];
        }

        if (strpos($href, '/') === 0) {
            $combined .= $href;
        } else {
            $dir      = trailingslashit(dirname($base_path));
            $combined .= $dir . $href;
        }

        return $combined;
    }

    private function normalize_url($url) {
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return '';
        }

        $normalized = $scheme . '://' . strtolower($parts['host']);

        if (!empty($parts['port'])) {
            $normalized .= ':' . $parts['port'];
        }

        $path = isset($parts['path']) ? $parts['path'] : '/';
        $normalized .= untrailingslashit($path === '' ? '/' : $path);

        if (!empty($parts['query'])) {
            $normalized .= '?' . $parts['query'];
        }

        return $normalized;
    }

    private function save_page_as_text($base_dir, $url, $html) {
        $text = wp_strip_all_tags($html);

        $parts = wp_parse_url($url);
        $path  = isset($parts['path']) ? trim($parts['path'], '/') : '';
        if ($path === '') {
            $path = 'index';
        }

        $identifier = $path;
        if (!empty($parts['query'])) {
            $identifier .= '-' . md5($parts['query']);
        }

        $filename = preg_replace('/[^a-zA-Z0-9\-]+/', '-', $identifier);
        $filename = trim($filename, '-') . '-' . substr(md5($url), 0, 8) . '.txt';

        $filepath = trailingslashit($base_dir) . $filename;
        wp_mkdir_p(dirname($filepath));

        file_put_contents($filepath, $text);
    }

    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    private function get_archived_sites() {
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']) . $this->archive_dir;

        if (!is_dir($base_dir)) {
            return [];
        }

        $entries = scandir($base_dir);
        if ($entries === false) {
            return [];
        }

        $archives = [];

        $meta = $this->get_archive_meta();

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = trailingslashit($base_dir) . $entry;
            if (!is_dir($path)) {
                continue;
            }

            $archives[] = [
                'domain'    => $entry,
                'path'      => $path . '/',
                'file_count' => $this->count_files($path),
                'url'       => isset($meta[$entry]['url']) ? $meta[$entry]['url'] : ('https://' . $entry),
            ];
        }

        return $archives;
    }

    private function archives_by_slug($archives) {
        $map = [];

        foreach ($archives as $archive) {
            if (empty($archive['domain'])) {
                continue;
            }

            $map[$archive['domain']] = $archive;
        }

        return $map;
    }

    private function count_files($dir) {
        $items = scandir($dir);
        if ($items === false) {
            return 0;
        }

        $count = 0;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $count += $this->count_files($path);
            } else {
                $count++;
            }
        }

        return $count;
    }

    private function render_archive_list($archives, $is_admin) {
        if (empty($archives)) {
            echo '<p><em>' . esc_html__('Még nincs letöltött weboldal.', 'site-text-archiver') . '</em></p>';
            return;
        }

        ?>
        <h3><?php esc_html_e('Letöltött weboldalak', 'site-text-archiver'); ?></h3>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Weboldal', 'site-text-archiver'); ?></th>
                    <th><?php esc_html_e('Fájlok száma', 'site-text-archiver'); ?></th>
                    <th><?php esc_html_e('Elérési út', 'site-text-archiver'); ?></th>
                    <th><?php esc_html_e('Művelet', 'site-text-archiver'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($archives as $archive) : ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($archive['domain']); ?></strong><br />
                            <a href="<?php echo esc_url($archive['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($archive['url']); ?></a>
                        </td>
                        <td><?php echo esc_html(number_format_i18n($archive['file_count'])); ?></td>
                        <td><code style="user-select: all;"><?php echo esc_html($archive['path']); ?></code></td>
                        <td>
                            <form method="post" action="" style="margin: 0;">
                                <?php wp_nonce_field('sta_delete_archive', 'sta_delete_nonce'); ?>
                                <input type="hidden" name="sta_action" value="delete_archive" />
                                <input type="hidden" name="sta_archive_domain" value="<?php echo esc_attr($archive['domain']); ?>" />
                                <button type="submit" class="button button-secondary" <?php echo $is_admin ? '' : 'style="margin:0;"'; ?>><?php esc_html_e('Törlés', 'site-text-archiver'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function handle_archive_deletion($require_capability = true) {
        $messages = [];

        if (empty($_POST['sta_action']) || $_POST['sta_action'] !== 'delete_archive') {
            return $messages;
        }

        if ($require_capability && !current_user_can('manage_options')) {
            $messages[] = ['type' => 'error', 'text' => __('Nincs jogosultságod az archívum törléséhez.', 'site-text-archiver')];
            return $messages;
        }

        if (empty($_POST['sta_delete_nonce']) || !wp_verify_nonce(wp_unslash($_POST['sta_delete_nonce']), 'sta_delete_archive')) {
            $messages[] = ['type' => 'error', 'text' => __('Érvénytelen biztonsági ellenőrzés.', 'site-text-archiver')];
            return $messages;
        }

        $domain = isset($_POST['sta_archive_domain']) ? sanitize_title(wp_unslash($_POST['sta_archive_domain'])) : '';

        if (empty($domain)) {
            $messages[] = ['type' => 'error', 'text' => __('Hiányzó weboldal azonosító.', 'site-text-archiver')];
            return $messages;
        }

        $deleted = $this->delete_archive_by_slug($domain, false);

        if ($deleted) {
            $messages[] = ['type' => 'updated', 'text' => sprintf(__('Törölve: %s archívuma.', 'site-text-archiver'), $domain)];
        } else {
            $messages[] = ['type' => 'error', 'text' => __('A törlés nem sikerült vagy a mappa nem található.', 'site-text-archiver')];
        }

        return $messages;
    }

    private function delete_archive_by_slug($slug, $remove_meta_only = false) {
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']) . $this->archive_dir;
        $target_dir = trailingslashit($base_dir) . $slug;
        $meta       = $this->get_archive_meta();
        $has_meta   = isset($meta[$slug]);
        $has_dir    = is_dir($target_dir);

        if (!$has_dir && !$has_meta) {
            return false;
        }

        if (!$remove_meta_only && $has_dir) {
            $this->delete_directory($target_dir);
        }

        $this->remove_archive_meta($slug);

        return true;
    }

    private function slug_from_url($url) {
        $host = wp_parse_url($url, PHP_URL_HOST);
        return $host ? sanitize_title($host) : '';
    }

    private function sanitize_overwrite_request() {
        $choices = isset($_POST['sta_overwrite']) && is_array($_POST['sta_overwrite']) ? $_POST['sta_overwrite'] : [];

        $clean = [];

        foreach ($choices as $slug => $value) {
            $slug = sanitize_title($slug);
            if (!$slug) {
                continue;
            }

            $clean[$slug] = (int) !empty($value);
        }

        return $clean;
    }

    private function get_archive_meta() {
        $meta = get_option($this->archive_meta_option, []);
        return is_array($meta) ? $meta : [];
    }

    private function record_archive_meta($slug, $url) {
        $meta = $this->get_archive_meta();

        $meta[$slug] = [
            'url'       => $url,
            'timestamp' => time(),
        ];

        update_option($this->archive_meta_option, $meta);
    }

    private function remove_archive_meta($slug) {
        $meta = $this->get_archive_meta();
        if (isset($meta[$slug])) {
            unset($meta[$slug]);
            update_option($this->archive_meta_option, $meta);
        }
    }

    private function render_messages($messages) {
        if (empty($messages)) {
            return;
        }

        foreach ($messages as $message) {
            $class = $message['type'] === 'error' ? 'notice notice-error' : 'notice notice-success';
            echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message['text']) . '</p></div>';
        }
    }

    private function render_progress_ui($form_id) {
        $ajax_nonce = wp_create_nonce('sta_ajax_nonce');
        $progress_id = 'sta-progress-' . wp_generate_uuid4();
        ?>
        <div class="sta-progress" id="<?php echo esc_attr($progress_id); ?>" data-form="<?php echo esc_attr($form_id); ?>" style="display:none; margin-top: 16px;">
            <p class="sta-progress__status"><?php esc_html_e('Letöltés indítása...', 'site-text-archiver'); ?></p>
            <p class="sta-progress__count"></p>
            <p class="sta-progress__current"></p>
            <button type="button" class="button button-secondary sta-stop-run" disabled><?php esc_html_e('Letöltés megállítása', 'site-text-archiver'); ?></button>
        </div>
        <script>
            (function() {
                const ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                const nonce   = '<?php echo esc_js($ajax_nonce); ?>';

                const initForm = (formId) => {
                    const form = document.getElementById(formId);
                    if (!form) { return; }

                    const progress = document.querySelector('.sta-progress[data-form="' + formId + '"]');
                    if (!progress) { return; }

                    const statusEl  = progress.querySelector('.sta-progress__status');
                    const countEl   = progress.querySelector('.sta-progress__count');
                    const currentEl = progress.querySelector('.sta-progress__current');
                    const stopBtn   = progress.querySelector('.sta-stop-run');
                    let pollTimer = null;
                    let activeRun = '';

                    const setStatus = (text) => { statusEl.textContent = text || ''; };
                    const setCount  = (text) => { countEl.textContent = text || ''; };
                    const setCurrent = (text) => { currentEl.textContent = text || ''; };

                    const stopPolling = () => {
                        if (pollTimer) { clearInterval(pollTimer); }
                        pollTimer = null;
                    };

                    const resetUI = () => {
                        stopPolling();
                        activeRun = '';
                        stopBtn.disabled = true;
                    };

                    const updateUI = (data) => {
                        setStatus(data.message || '');
                        if (data.saved) {
                            setCount('<?php echo esc_js(__('Mentett fájlok: ', 'site-text-archiver')); ?>' + data.saved);
                        } else {
                            setCount('');
                        }
                        if (data.last_url) {
                            setCurrent('<?php echo esc_js(__('Aktuális oldal: ', 'site-text-archiver')); ?>' + data.last_url);
                        } else if (data.current_host) {
                            setCurrent('<?php echo esc_js(__('Aktuális domain: ', 'site-text-archiver')); ?>' + data.current_host);
                        } else {
                            setCurrent('');
                        }

                        if (data.status === 'running') {
                            stopBtn.disabled = false;
                        } else {
                            stopBtn.disabled = true;
                        }

                        if (['completed', 'stopped', 'error'].indexOf(data.status) !== -1) {
                            stopPolling();
                        }
                    };

                    const poll = () => {
                        if (!activeRun) { return; }
                        const body = new FormData();
                        body.append('action', 'sta_poll_crawl');
                        body.append('run_id', activeRun);
                        body.append('nonce', nonce);

                        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                            .then((res) => res.json())
                            .then((json) => {
                                if (!json || !json.success) { return; }
                                updateUI(json.data || {});
                            })
                            .catch(() => {});
                    };

                    const startPolling = () => {
                        stopPolling();
                        poll();
                        pollTimer = window.setInterval(poll, 2000);
                    };

                    const startCrawl = () => {
                        const fd = new FormData(form);
                        const submitter = form._sta_last_submitter;
                        if (submitter && submitter.value === 'save') { return true; }

                        fd.append('action', 'sta_start_crawl');
                        fd.append('nonce', nonce);

                        progress.style.display = 'block';
                        setStatus('<?php echo esc_js(__('Letöltés indítása...', 'site-text-archiver')); ?>');
                        setCount('');
                        setCurrent('');
                        stopBtn.disabled = true;

                        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
                            .then((res) => res.json())
                            .then((json) => {
                                if (!json || !json.success || !json.data) {
                                    setStatus('<?php echo esc_js(__('A letöltés nem indult el. Ellenőrizd az URL-eket.', 'site-text-archiver')); ?>');
                                    return;
                                }

                                activeRun = json.data.run_id;
                                startPolling();
                            })
                            .catch(() => {
                                setStatus('<?php echo esc_js(__('Hiba történt a letöltés indításakor.', 'site-text-archiver')); ?>');
                            });

                        return false;
                    };

                    form.addEventListener('submit', (event) => {
                        form._sta_last_submitter = event.submitter;
                        if (event.submitter && event.submitter.value === 'save') {
                            return;
                        }
                        event.preventDefault();
                        startCrawl();
                    });

                    stopBtn.addEventListener('click', () => {
                        if (!activeRun) { return; }
                        const body = new FormData();
                        body.append('action', 'sta_stop_crawl');
                        body.append('run_id', activeRun);
                        body.append('nonce', nonce);

                        stopBtn.disabled = true;
                        setStatus('<?php echo esc_js(__('Megállítás kérése...', 'site-text-archiver')); ?>');

                        fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
                            .then((res) => res.json())
                            .then((json) => {
                                if (json && json.success) {
                                    setStatus('<?php echo esc_js(__('A letöltés hamarosan megáll.', 'site-text-archiver')); ?>');
                                }
                            })
                            .catch(() => {});
                    });
                };

                document.addEventListener('DOMContentLoaded', () => {
                    initForm('<?php echo esc_js($form_id); ?>');
                });
            })();
        </script>
        <?php
    }

    public function ajax_start_crawl() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Nincs jogosultságod a letöltés indításához.', 'site-text-archiver')]);
        }

        if (empty($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'sta_ajax_nonce')) {
            wp_send_json_error(['message' => __('Érvénytelen biztonsági ellenőrzés.', 'site-text-archiver')]);
        }

        $submitted_urls = isset($_POST[$this->option_name]) ? wp_unslash($_POST[$this->option_name]) : '';
        $urls           = $this->sanitize_urls($submitted_urls);
        $overwrite      = $this->sanitize_overwrite_request();

        update_option($this->option_name, $urls);

        if (empty($urls)) {
            wp_send_json_error(['message' => __('Nincs érvényes URL megadva.', 'site-text-archiver')]);
        }

        $run_id = wp_generate_uuid4();
        $this->init_progress($run_id);
        $this->clear_stop_flag($run_id);

        $this->send_async_json(['run_id' => $run_id]);

        $messages = $this->process_urls($urls, $overwrite, $run_id);

        $summary = implode(' ', array_map(function ($item) {
            return $item['text'];
        }, $messages));

        if ($this->should_stop($run_id)) {
            $this->update_progress($run_id, [
                'status'  => 'stopped',
                'message' => $summary ?: __('A letöltés megállítva.', 'site-text-archiver'),
            ]);
        } else {
            $this->update_progress($run_id, [
                'status'  => 'completed',
                'message' => $summary,
            ]);
        }

        $this->clear_stop_flag($run_id);
        exit;
    }

    public function ajax_poll_crawl() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        if (empty($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'sta_ajax_nonce')) {
            wp_send_json_error();
        }

        $run_id = isset($_POST['run_id']) ? sanitize_text_field(wp_unslash($_POST['run_id'])) : '';
        $state  = $this->get_progress();

        if (empty($run_id) || empty($state) || $state['run_id'] !== $run_id) {
            wp_send_json_error();
        }

        wp_send_json_success($state);
    }

    public function ajax_stop_crawl() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        if (empty($_POST['nonce']) || !wp_verify_nonce(wp_unslash($_POST['nonce']), 'sta_ajax_nonce')) {
            wp_send_json_error();
        }

        $run_id = isset($_POST['run_id']) ? sanitize_text_field(wp_unslash($_POST['run_id'])) : '';
        $state  = $this->get_progress();

        if (empty($run_id) || empty($state) || $state['run_id'] !== $run_id) {
            wp_send_json_error();
        }

        update_option($this->stop_option, $run_id);
        wp_send_json_success(['message' => __('Megállítás kérése sikeres.', 'site-text-archiver')]);
    }

    private function init_progress($run_id) {
        $state = [
            'run_id'       => $run_id,
            'status'       => 'running',
            'current_host' => '',
            'last_url'     => '',
            'saved'        => 0,
            'message'      => __('Letöltés indítása...', 'site-text-archiver'),
        ];

        update_option($this->progress_option, $state);
    }

    private function update_progress($run_id, array $data) {
        $state = $this->get_progress();
        if (empty($state) || $state['run_id'] !== $run_id) {
            return;
        }

        $state = array_merge($state, $data);
        update_option($this->progress_option, $state);
    }

    private function get_progress() {
        $state = get_option($this->progress_option, []);
        return is_array($state) ? $state : [];
    }

    private function should_stop($run_id) {
        $flag = get_option($this->stop_option);
        return !empty($flag) && $flag === $run_id;
    }

    private function clear_stop_flag($run_id) {
        $flag = get_option($this->stop_option);
        if ($flag === $run_id) {
            delete_option($this->stop_option);
        }
    }

    private function send_async_json(array $payload) {
        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        echo wp_json_encode(['success' => true, 'data' => $payload]);
        echo str_repeat(' ', 1024);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            flush();
        }
    }
}

new Site_Text_Archiver();
