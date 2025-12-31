<?php

if (!defined('ABSPATH')) {
    exit;
}

class SimpleChatbotCrawler
{
    private $maxPages;

    public function __construct(int $maxPages = 8)
    {
        $this->maxPages = $maxPages;
    }

    public function collect_site_content(string $rootUrl): string
    {
        $startUrl = wp_http_validate_url(esc_url_raw($rootUrl));

        if (!$startUrl) {
            return '';
        }

        $parsed = wp_parse_url($startUrl);

        if (!$parsed || empty($parsed['host'])) {
            return '';
        }

        $host = $parsed['host'];
        $queue = [$startUrl];
        $visited = [];
        $contentPieces = [];

        while (!empty($queue) && count($visited) < $this->maxPages) {
            $current = array_shift($queue);

            if (isset($visited[$current])) {
                continue;
            }

            $visited[$current] = true;

            $response = wp_remote_get($current, ['timeout' => 10]);

            if (is_wp_error($response)) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);

            if (!is_string($body) || trim($body) === '') {
                continue;
            }

            $contentPieces[] = wp_strip_all_tags($body);

            if (count($visited) >= $this->maxPages) {
                break;
            }

            $links = $this->extract_internal_links($body, $current, $host);

            foreach ($links as $link) {
                if (!isset($visited[$link]) && !in_array($link, $queue, true) && count($queue) + count($visited) < $this->maxPages) {
                    $queue[] = $link;
                }
            }
        }

        return trim(implode("\n\n", $contentPieces));
    }

    public function get_cached_or_collect(string $rootUrl, string $targetDir): string
    {
        if ($targetDir === '') {
            return $this->collect_site_content($rootUrl);
        }

        if (!file_exists($targetDir) && !wp_mkdir_p($targetDir)) {
            return $this->collect_site_content($rootUrl);
        }

        $cachePath = $this->build_cache_path($rootUrl, $targetDir);

        if (file_exists($cachePath) && filesize($cachePath) > 0) {
            $cached = @file_get_contents($cachePath);

            if (is_string($cached) && trim($cached) !== '') {
                return $cached;
            }
        }

        $content = $this->collect_site_content($rootUrl);

        if ($content !== '') {
            @file_put_contents($cachePath, $content);
        }

        return $content;
    }

    private function extract_internal_links(string $html, string $base, string $host): array
    {
        $links = [];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();

        if (!$dom->loadHTML($html)) {
            libxml_clear_errors();
            return $links;
        }

        libxml_clear_errors();
        $anchors = $dom->getElementsByTagName('a');

        foreach ($anchors as $anchor) {
            /** @var DOMElement $anchor */
            $href = $anchor->getAttribute('href');
            $normalized = $this->normalize_url($href, $base);

            if (!$normalized) {
                continue;
            }

            $parsed = wp_parse_url($normalized);

            if ($parsed && isset($parsed['host']) && $parsed['host'] === $host) {
                $links[] = $normalized;
            }
        }

        return array_values(array_unique($links));
    }

    private function normalize_url(string $href, string $base): ?string
    {
        $trimmed = trim($href);

        if ($trimmed === '' || strpos($trimmed, 'javascript:') === 0 || strpos($trimmed, 'mailto:') === 0 || strpos($trimmed, '#') === 0) {
            return null;
        }

        if (strpos($trimmed, '//') === 0) {
            $trimmed = 'https:' . $trimmed;
        }

        $parsed = wp_parse_url($trimmed);

        if ($parsed && !isset($parsed['scheme'])) {
            $baseParsed = wp_parse_url($base);

            if (!$baseParsed || empty($baseParsed['scheme']) || empty($baseParsed['host'])) {
                return null;
            }

            $basePath = isset($baseParsed['path']) ? $baseParsed['path'] : '/';
            $baseDir = rtrim(dirname($basePath), '/');

            if ($baseDir === '') {
                $baseDir = '/';
            }

            $path = isset($trimmed[0]) && $trimmed[0] === '/'
                ? $trimmed
                : rtrim($baseDir, '/') . '/' . ltrim($trimmed, '/');

            $port = isset($baseParsed['port']) ? ':' . $baseParsed['port'] : '';
            $trimmed = $baseParsed['scheme'] . '://' . $baseParsed['host'] . $port . $path;
        }

        return wp_http_validate_url($trimmed);
    }

    private function build_cache_path(string $rootUrl, string $targetDir): string
    {
        $hash = md5($rootUrl);
        return trailingslashit($targetDir) . 'kb_url_' . $hash . '.txt';
    }
}
