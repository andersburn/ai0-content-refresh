<?php
defined('ABSPATH') || exit;

final class AI0R_Content {
    private const CONTAINERS = ['core/group', 'core/columns', 'core/column', 'core/cover', 'core/media-text', 'core/list', 'core/quote'];
    private const LEAVES = ['core/paragraph', 'core/heading', 'core/list-item', 'core/freeform', 'core/table', null];
    private const VOID_TAGS = ['AREA','BASE','BR','COL','EMBED','HR','IMG','INPUT','LINK','META','PARAM','SOURCE','TRACK','WBR'];
    private const ATOMIC_TAGS = ['SCRIPT','STYLE','TEXTAREA','TITLE','IFRAME','XMP','NOEMBED','NOFRAMES'];
    private const BLOCKED_TAGS = ['A','PRE','CODE','SVG','MATH','VIDEO','AUDIO','PICTURE','FORM','BUTTON','SELECT','NOSCRIPT','TEMPLATE'];

    public static function schema(): array {
        $string = ['type' => 'string'];
        $item = ['type' => 'object', 'properties' => ['label' => $string, 'text' => $string, 'url' => $string], 'required' => ['label','text','url'], 'additionalProperties' => false];
        return ['type' => 'object', 'properties' => [
            'summary' => $string,
            'replacements' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['id' => $string, 'text' => $string], 'required' => ['id','text'], 'additionalProperties' => false]],
            'sections' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'after_block' => ['type' => 'integer'],
                'kind' => ['type' => 'string', 'enum' => ['summary','faq','checklist','table','callout','cta','related']],
                'heading' => $string, 'text' => $string, 'items' => ['type' => 'array', 'items' => $item],
            ], 'required' => ['after_block','kind','heading','text','items'], 'additionalProperties' => false]],
        ], 'required' => ['summary','replacements','sections'], 'additionalProperties' => false];
    }

    /** Change only real text tokens. Existing tags, attributes, links and embeds remain untouched. */
    private static function html(string $html, string $prefix, array &$segments, array $changes = []): string {
        $p = new WP_HTML_Tag_Processor($html);
        $stack = [];
        $ordinal = 0;
        while ($p->next_token()) {
            if ($p->get_token_type() === '#tag') {
                $tag = $p->get_tag();
                if ($p->is_tag_closer()) {
                    $at = array_search($tag, array_reverse($stack, true), true);
                    if ($at !== false) { $stack = array_slice($stack, 0, $at); }
                } elseif (!in_array($tag, array_merge(self::VOID_TAGS, self::ATOMIC_TAGS), true)) {
                    // Account for the usual HTML implied closing of paragraph/list-item elements.
                    if (in_array($tag, ['P','LI'], true) && in_array($tag, $stack, true)) {
                        $stack = array_slice($stack, 0, array_search($tag, $stack, true));
                    }
                    $stack[] = $tag;
                }
                continue;
            }
            if ($p->get_token_type() !== '#text') { continue; }
            $id = $prefix . ':' . $ordinal++;
            $text = $p->get_modifiable_text();
            if (array_intersect($stack, self::BLOCKED_TAGS) || !array_intersect($stack, ['P','H2','H3','H4','H5','H6','LI','TH','TD','BLOCKQUOTE']) ||
                mb_strlen(trim($text)) < 4 || preg_match('/\[[^\]]*\]/u', $text)) { continue; }
            $segments[$id] = ['id' => $id, 'text' => $text];
            if (array_key_exists($id, $changes)) {
                $new = $changes[$id];
                // Preserve whitespace at rich-text boundaries (e.g. before a protected link).
                preg_match('/^\s*/u', $text, $leading);
                preg_match('/\s*$/u', $text, $trailing);
                if (!$p->set_modifiable_text($leading[0] . trim($new) . $trailing[0])) {
                    throw new RuntimeException('En tekst kunne ikke ændres sikkert.');
                }
            }
        }
        return $p->get_updated_html();
    }

    private static function walk(array &$blocks, array &$segments, array $changes = [], string $prefix = ''): void {
        foreach ($blocks as $i => &$block) {
            $path = $prefix . $i;
            $name = $block['blockName'];
            if (in_array($name, self::CONTAINERS, true)) {
                self::walk($block['innerBlocks'], $segments, $changes, $path . '.');
            } elseif (!in_array($name, self::LEAVES, true)) {
                continue; // Unknown/dynamic/custom blocks are opaque, including their children.
            }
            // Registered shortcodes can have hidden semantics: protect the complete legacy fragment.
            foreach ($block['innerContent'] as $part => &$html) {
                if (!is_string($html)) { continue; }
                if (preg_match('/' . get_shortcode_regex() . '/s', $html)) { continue; }
                $html = self::html($html, $path . '/' . $part, $segments, $changes);
            }
            unset($html);
            $block['innerHTML'] = implode('', array_filter($block['innerContent'], 'is_string'));
        }
        unset($block);
    }

    public static function extract(string $content): array {
        if (!method_exists('WP_HTML_Tag_Processor', 'set_modifiable_text')) {
            throw new RuntimeException('Pluginet kræver WordPress 6.7 eller nyere.');
        }
        $blocks = parse_blocks($content);
        $segments = [];
        self::walk($blocks, $segments);
        $outline = [];
        foreach ($blocks as $i => $block) {
            $outline[] = ['index' => $i, 'type' => $block['blockName'] ?: 'legacy-html', 'text' => mb_substr(wp_strip_all_tags($block['innerHTML']), 0, 450)];
        }
        return ['segments' => $segments, 'outline' => $outline, 'blocks' => $blocks];
    }

    public static function build(string $original, array $response, array $allowed_urls, bool $allow_additions): string {
        $data = self::extract($original);
        $changes = [];
        if (!isset($response['replacements'], $response['sections'], $response['summary']) || !is_array($response['replacements']) || !is_array($response['sections']) || !is_string($response['summary'])) {
            throw new RuntimeException('Modellen returnerede et ugyldigt svar.');
        }
        foreach ($response['replacements'] as $r) {
            if (!is_array($r) || !isset($data['segments'][$r['id'] ?? '']) || !is_string($r['text'] ?? null) || isset($changes[$r['id']])) {
                throw new RuntimeException('Modellen forsøgte at ændre et ukendt eller gentaget tekstfelt.');
            }
            $old = $data['segments'][$r['id']]['text'];
            $new = $r['text'];
            if (trim($new) === '' || mb_strlen($new) > max(500, mb_strlen($old) * 3) || preg_match('/[<>\[\]]|https?:\/\//iu', $new)) {
                throw new RuntimeException('Et tekstforslag indeholder markup, links eller en uventet stor ændring.');
            }
            if (trim($new) !== trim($old)) { $changes[$r['id']] = $new; }
        }
        $blocks = $data['blocks'];
        $ignored = [];
        self::walk($blocks, $ignored, $changes);
        $existing = 0;
        $headings = [];
        foreach ($blocks as $b) {
            if ($b['blockName'] === 'ai0-refresh/section') {
                $existing++;
                $headings[] = mb_strtolower($b['attrs']['heading'] ?? '');
            }
        }
        if (count($response['sections']) > 4 || count($response['sections']) + $existing > 4 || (!$allow_additions && $response['sections'])) {
            throw new RuntimeException('For mange nye elementer, eller nye elementer er slået fra.');
        }
        $insert = [];
        foreach ($response['sections'] as $s) {
            if (!is_array($s) || !is_int($s['after_block'] ?? null) || !isset($blocks[$s['after_block']])) {
                throw new RuntimeException('Et nyt element har en ugyldig placering.');
            }
            $clean = self::validate_section($s, $allowed_urls);
            $key = mb_strtolower($clean['heading']);
            if (in_array($key, $headings, true)) { throw new RuntimeException('Et nyt element gentager en eksisterende overskrift.'); }
            $headings[] = $key;
            $insert[$s['after_block']][] = ['blockName' => 'ai0-refresh/section', 'attrs' => $clean, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
        }
        if (!$changes && !$insert) { return $original; }
        $final = [];
        foreach ($blocks as $i => $b) {
            $final[] = $b;
            foreach ($insert[$i] ?? [] as $extra) { $final[] = $extra; }
        }
        $result = serialize_blocks($final);
        // Every original block must survive, in the same order, with identical attributes.
        $roundtrip = parse_blocks($result);
        if (count($roundtrip) !== count($final)) { throw new RuntimeException('Blokkene kunne ikke valideres efter ændringen.'); }
        return $result;
    }

    private static function plain($text, int $limit = 4000): string {
        if (!is_string($text) || mb_strlen($text) > $limit || preg_match('/[<>\[\]]/u', $text)) {
            throw new RuntimeException('Et nyt element indeholder ugyldig tekst.');
        }
        return sanitize_textarea_field($text);
    }

    private static function validate_section(array $s, array $urls): array {
        if (!in_array($s['kind'] ?? '', ['summary','faq','checklist','table','callout','cta','related'], true) || !is_array($s['items'] ?? null) || count($s['items']) > 12) {
            throw new RuntimeException('Ugyldig elementtype eller for mange punkter.');
        }
        $clean = ['kind' => $s['kind'], 'heading' => self::plain($s['heading'] ?? null, 180), 'text' => self::plain($s['text'] ?? null), 'items' => []];
        if (!$clean['heading']) { throw new RuntimeException('Et nyt element mangler overskrift.'); }
        foreach ($s['items'] as $item) {
            $url = $item['url'] ?? '';
            if (!is_string($url) || ($url !== '' && !in_array($url, $urls, true))) {
                throw new RuntimeException('Modellen foreslog et link uden for de godkendte sider.');
            }
            $clean['items'][] = ['label' => self::plain($item['label'] ?? null, 240), 'text' => self::plain($item['text'] ?? null, 1400), 'url' => esc_url_raw($url)];
        }
        if (in_array($clean['kind'], ['faq','checklist','table','cta','related'], true) && !$clean['items']) {
            throw new RuntimeException('Elementet mangler indhold.');
        }
        if (in_array($clean['kind'], ['cta','related'], true)) {
            foreach ($clean['items'] as $item) { if (!$item['url'] || !$item['label']) { throw new RuntimeException('Et link mangler destination eller linktekst.'); } }
        }
        return $clean;
    }

    public static function render(array $a): string {
        $kind = $a['kind'] ?? 'callout';
        $out = '<section class="ai0r-section ai0r-section--' . esc_attr(sanitize_key($kind)) . '">';
        $out .= '<h2>' . esc_html($a['heading'] ?? '') . '</h2>';
        if (!empty($a['text'])) { $out .= '<p>' . nl2br(esc_html($a['text'])) . '</p>'; }
        $items = array_slice(is_array($a['items'] ?? null) ? $a['items'] : [], 0, 12);
        if ($kind === 'table') {
            $out .= '<div class="ai0r-table-scroll"><table><thead><tr><th scope="col">Emne</th><th scope="col">Forklaring</th></tr></thead><tbody>';
            foreach ($items as $i) { $out .= '<tr><th scope="row">' . esc_html($i['label'] ?? '') . '</th><td>' . esc_html($i['text'] ?? '') . '</td></tr>'; }
            $out .= '</tbody></table></div>';
        } elseif ($kind === 'faq') {
            foreach ($items as $i) { $out .= '<details><summary>' . esc_html($i['label'] ?? '') . '</summary><p>' . esc_html($i['text'] ?? '') . '</p></details>'; }
        } elseif ($items) {
            $out .= '<ul>';
            foreach ($items as $i) {
                $label = esc_html($i['label'] ?? '');
                $out .= '<li>' . (!empty($i['url']) ? '<a href="' . esc_url($i['url']) . '">' . $label . '</a>' : '<strong>' . $label . '</strong>');
                $out .= !empty($i['text']) ? ' ' . esc_html($i['text']) : '';
                $out .= '</li>';
            }
            $out .= '</ul>';
        }
        return $out . '</section>';
    }
}
