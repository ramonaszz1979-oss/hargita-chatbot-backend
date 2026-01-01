<?php

if (!defined('ABSPATH')) {
    exit;
}

class SimpleChatbotProcessManager
{
    const FILE_NAME = 'processes.json';

    public function get_sections(): array
    {
        $path = $this->get_file_path();

        if ($path === '' || !file_exists($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        $decoded = json_decode($raw ?: '[]', true);

        return $this->sanitize_sections($decoded ?: []);
    }

    public function save_sections($sections): array
    {
        $clean = $this->sanitize_sections($sections);
        $path = $this->get_file_path();

        if ($path !== '') {
            if (!file_exists(dirname($path))) {
                wp_mkdir_p(dirname($path));
            }

            file_put_contents($path, wp_json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return $clean;
    }

    public function render_sections_as_text(array $sections): string
    {
        $lines = [];

        foreach ($this->sanitize_sections($sections) as $section) {
            $items = isset($section['items']) ? (array)$section['items'] : [];
            $prefix = !empty($section['is_start']) ? '[START] ' : '';
            $line = $prefix . $section['title'];

            if (!empty($section['form_url'])) {
                $formLabel = isset($section['form_label']) ? $section['form_label'] : '';
                $cleanLabel = $formLabel !== '' ? $formLabel : __('Űrlap', 'simple-chatbot');
                $line .= ' (' . $cleanLabel . ': ' . $section['form_url'] . ')';
            }
            $itemLabels = [];

            foreach ($items as $item) {
                if (is_array($item)) {
                    $label = isset($item['label']) ? $item['label'] : '';
                    if (!empty($item['is_end'])) {
                        $label .= ' (END)';
                    }
                } else {
                    $label = (string)$item;
                }

                if ($label !== '') {
                    $itemLabels[] = $label;
                }
            }

            if (!empty($itemLabels)) {
                $line .= ': ' . implode('; ', $itemLabels);
            }

            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    private function get_file_path(): string
    {
        $upload = wp_upload_dir();

        if (!isset($upload['basedir']) || $upload['basedir'] === '') {
            return '';
        }

        $dir = trailingslashit($upload['basedir']) . 'simple-chatbot';

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        return trailingslashit($dir) . self::FILE_NAME;
    }

    private function sanitize_sections($sections): array
    {
        if (!is_array($sections)) {
            return [];
        }

        $clean = [];
        $hasStart = false;

        foreach ($sections as $section) {
            $title = isset($section['title']) ? sanitize_text_field($section['title']) : '';
            $items = isset($section['items']) ? (array)$section['items'] : [];
            $id = isset($section['id']) ? sanitize_key($section['id']) : '';
            $isStart = !empty($section['is_start']);
            $formUrl = '';
            $formLabel = isset($section['form_label']) ? sanitize_text_field($section['form_label']) : '';

            if (!empty($section['form_url']) && is_string($section['form_url'])) {
                $formUrlRaw = trim($section['form_url']);

                if ($formUrlRaw !== '' && !preg_match('#^https?://#i', $formUrlRaw)) {
                    $formUrlRaw = 'https://' . ltrim($formUrlRaw, '/');
                }

                $formUrl = esc_url_raw($formUrlRaw);
            }

            if ($title === '') {
                continue;
            }

            if ($id === '') {
                $id = uniqid('section_', true);
            }

            $cleanItems = array_values(array_filter(array_map(function ($item) {
                if (is_array($item)) {
                    $label = isset($item['label']) ? sanitize_text_field($item['label']) : '';
                    $target = isset($item['target']) ? sanitize_key($item['target']) : '';
                    $isEnd = !empty($item['is_end']);

                    if ($label === '') {
                        return null;
                    }

                    return [
                        'label' => $label,
                        'target' => $target,
                        'is_end' => $isEnd,
                    ];
                }

                $label = sanitize_text_field($item);

                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'target' => '',
                    'is_end' => false,
                ];
            }, $items), function ($value) {
                return is_array($value) && $value['label'] !== '';
            }));

            if ($isStart && !$hasStart) {
                $hasStart = true;
            } else {
                $isStart = false;
            }

            $clean[] = [
                'id' => $id,
                'title' => $title,
                'items' => $cleanItems,
                'is_start' => $isStart,
                'form_url' => $formUrl,
                'form_label' => $formLabel,
            ];
        }

        if (!$hasStart && !empty($clean)) {
            $clean[0]['is_start'] = true;
        }

        return $clean;
    }
}
