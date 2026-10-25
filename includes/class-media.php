<?php
defined('ABSPATH') || exit;

/** Search and reuse existing WordPress images. No uploads, downloads or attachment edits. */
final class AI0R_Media {
    private const MIMES = ['image/jpeg','image/png','image/webp','image/gif','image/avif'];

    public static function terms(WP_Post $post): array {
        preg_match_all('/<h[2-4]\b[^>]*>(.*?)<\/h[2-4]>/is', $post->post_content, $headings);
        $text = mb_strtolower(remove_accents(wp_strip_all_tags($post->post_title . ' ' . implode(' ', array_slice($headings[1], 0, 8)))));
        $words = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $stop = explode(' ', 'dette denne dine deres vores dine alle eller ikke mere mest bedste saadan sådan hvordan hvorfor hvad hvor naar med uden samt til fra for som det den der har kan skal vil ved over under side siden indlaeg blogindlaeg guide integration');
        $filter = static fn($words) => array_values(array_unique(array_filter($words, static fn($word) => mb_strlen($word) >= 4 && mb_strlen($word) <= 50 && !in_array($word, $stop, true))));
        $terms = $filter($words);
        if (count($terms) < 2) {
            $intro = mb_strtolower(remove_accents(mb_substr(wp_strip_all_tags($post->post_content), 0, 1200)));
            $terms = array_values(array_unique(array_merge($terms, $filter(preg_split('/[^\p{L}\p{N}]+/u', $intro, -1, PREG_SPLIT_NO_EMPTY)))));
        }
        return array_slice($terms, 0, 4);
    }

    public static function used_ids(string $content, array &$seen = []): array {
        preg_match_all('/wp-image-(\d+)/', $content, $matches);
        $ids = array_map('intval', $matches[1]);
        $walk = static function (array $blocks) use (&$walk, &$ids, &$seen): void {
            foreach ($blocks as $block) {
                foreach ($block['attrs'] ?? [] as $key => $value) {
                    if (($key === 'id' || preg_match('/(?:image|media|attachment).*id$/i', $key)) && is_numeric($value)) { $ids[] = (int) $value; }
                    if ($key === 'ids' && is_array($value)) { foreach ($value as $id) { if (is_numeric($id)) { $ids[] = (int) $id; } } }
                }
                if ($block['blockName'] === 'core/block' && !empty($block['attrs']['ref'])) {
                    $ref = (int) $block['attrs']['ref'];
                    if (!isset($seen[$ref]) && count($seen) < 30) {
                        $seen[$ref] = true; $shared = get_post($ref);
                        if ($shared) { $ids = array_merge($ids, self::used_ids($shared->post_content, $seen)); }
                    }
                }
                $walk($block['innerBlocks'] ?? []);
            }
        };
        $walk(parse_blocks($content));
        return array_values(array_unique($ids));
    }

    private static function canonical(string $url): string {
        $path = (string) wp_parse_url(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH);
        return preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', rawurldecode($path));
    }

    private static function used_urls(string $content, array &$seen = []): array {
        $urls = [];
        $html = new WP_HTML_Tag_Processor($content);
        while ($html->next_tag('IMG')) { $urls[] = self::canonical((string) $html->get_attribute('src')); }
        $walk = static function (array $blocks) use (&$walk, &$urls, &$seen): void {
            foreach ($blocks as $block) {
                if ($block['blockName'] === 'core/block' && !empty($block['attrs']['ref'])) {
                    $ref = (int) $block['attrs']['ref'];
                    if (!isset($seen[$ref]) && count($seen) < 30) {
                        $seen[$ref] = true; $shared = get_post($ref);
                        if ($shared) { $urls = array_merge($urls, self::used_urls($shared->post_content, $seen)); }
                    }
                }
                $walk($block['innerBlocks'] ?? []);
            }
        };
        $walk(parse_blocks($content));
        return array_unique($urls);
    }

    private static function references(string $content): array {
        return ['ids' => self::used_ids($content), 'urls' => self::used_urls($content)];
    }

    private static function already_used(string $content, array $image, ?array $references = null): bool {
        $references = $references ?? self::references($content);
        return in_array($image['id'], $references['ids'], true) || in_array(self::canonical($image['url']), $references['urls'], true);
    }

    public static function image(int $id): ?array {
        $p = get_post($id);
        if (!$p || $p->post_type !== 'attachment' || $p->post_status !== 'inherit' || $p->post_password || !in_array($p->post_mime_type, self::MIMES, true)) { return null; }
        if ($p->post_parent) {
            $parent = get_post($p->post_parent);
            if (!$parent || $parent->post_status !== 'publish' || $parent->post_password) { return null; }
        }
        $source = wp_get_attachment_image_src($id, 'large');
        if (!$source || $source[1] < 300 || $source[2] < 180 || !apply_filters('ai0r_media_allowed', true, $id)) { return null; }
        $title = sanitize_text_field(wp_strip_all_tags($p->post_title));
        if (preg_match('/\b(?:logo|favicon|icon|ikon|avatar)\b/iu', $title)) { return null; }
        return ['id' => $id, 'title' => mb_substr($title, 0, 180), 'alt' => mb_substr(sanitize_text_field(wp_strip_all_tags((string) get_post_meta($id, '_wp_attachment_image_alt', true))), 0, 500),
            'caption' => mb_substr(sanitize_textarea_field(wp_strip_all_tags($p->post_excerpt)), 0, 600), 'filename' => mb_substr(wp_basename((string) get_post_meta($id, '_wp_attached_file', true)), 0, 180),
            'url' => esc_url_raw($source[0]), 'width' => (int) $source[1], 'height' => (int) $source[2]];
    }

    public static function search(WP_Post $post, bool $enabled): array {
        $terms = $enabled ? self::terms($post) : [];
        if (!$terms) { return ['terms' => [], 'images' => []]; }
        $args = ['post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => self::MIMES, 'numberposts' => 20, 'orderby' => 'ID', 'order' => 'DESC', 'no_found_rows' => true, 'update_post_term_cache' => false];
        $candidates = []; $meta = ['relation' => 'OR'];
        foreach ($terms as $term) {
            foreach (get_posts($args + ['s' => $term, 'search_columns' => ['post_title','post_excerpt']]) as $p) { $candidates[$p->ID] = $p; }
            foreach (['_wp_attachment_image_alt','_wp_attached_file'] as $key) { $meta[] = ['key' => $key, 'value' => $term, 'compare' => 'LIKE']; }
        }
        foreach (get_posts($args + ['meta_query' => $meta]) as $p) { $candidates[$p->ID] = $p; }
        $references = self::references($post->post_content);
        $found = []; $featured = (int) get_post_thumbnail_id($post->ID);
        foreach ($candidates as $id => $p) {
            if ($id === $featured || !($image = self::image((int) $id)) || self::already_used($post->post_content, $image, $references)) { continue; }
            $haystack = mb_strtolower(remove_accents(implode(' ', [$image['title'], $image['alt'], $image['caption'], $image['filename']])));
            $score = 0; foreach ($terms as $term) { if (mb_strpos($haystack, $term) !== false) { $score++; } }
            if ($score) { $image['score'] = $score; $found[] = $image; }
        }
        usort($found, static fn($a, $b) => ($b['score'] <=> $a['score']) ?: ($b['id'] <=> $a['id']));
        return ['terms' => $terms, 'images' => array_slice($found, 0, 12)];
    }

    public static function insertions(string $original, array $requests, array $allowed, bool $enabled, array $blocks, array &$chosen): array {
        if (count($requests) > 2 || (!$enabled && $requests)) { throw new RuntimeException('Højst to nye billeder er tilladt, og billedadgang skal være slået til.'); }
        $references = $requests ? self::references($original) : null;
        $ids = array_column($allowed, 'id'); $insert = []; $seen = []; $seen_urls = []; $chosen = [];
        foreach ($requests as $r) {
            $id = $r['attachment_id'] ?? null; $at = $r['after_block'] ?? null;
            if (!is_int($id) || !in_array($id, $ids, true) || isset($seen[$id]) || !is_int($at) || !isset($blocks[$at]) || !is_string($r['reason'] ?? null) || trim($r['reason']) === '' || mb_strlen($r['reason']) > 800) { throw new RuntimeException('Et billedvalg har en ugyldig kilde, begrundelse eller placering.'); }
            $image = self::image($id);
            if (!$image || self::already_used($original, $image, $references)) { throw new RuntimeException('Billedet er ikke længere tilgængeligt eller findes allerede på siden.'); }
            $url_key = self::canonical($image['url']);
            if (isset($seen_urls[$url_key])) { throw new RuntimeException('Det samme billede må ikke indsættes to gange.'); }
            $seen[$id] = true; $seen_urls[$url_key] = true;
            $html = '<figure class="wp-block-image size-large"><img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt']) . '" class="wp-image-' . $id . '"/>';
            if ($image['caption'] !== '') { $html .= '<figcaption class="wp-element-caption">' . esc_html($image['caption']) . '</figcaption>'; }
            $html .= '</figure>';
            $insert[$at][] = ['blockName' => 'core/image', 'attrs' => ['id' => $id, 'sizeSlug' => 'large', 'linkDestination' => 'none'], 'innerBlocks' => [], 'innerHTML' => $html, 'innerContent' => [$html]];
            $chosen[] = ['id' => $id, 'title' => $image['title'], 'alt' => $image['alt'], 'caption' => $image['caption'], 'url' => $image['url'], 'reason' => sanitize_textarea_field($r['reason'])];
        }
        return $insert;
    }

    public static function check_publication(WP_Post $post, array $details): void {
        $images = $details['images'] ?? [];
        if (!$images) { return; }
        if (empty(AI0R_Store::settings()['media_library'])) { throw new RuntimeException('Billedadgang er slået fra. Forslaget med nye billeder er bevaret.'); }
        $references = self::references($post->post_content);
        foreach ($images as $snapshot) {
            $image = self::image((int) $snapshot['id']);
            if (!$image || $image['url'] !== $snapshot['url'] || (int) get_post_thumbnail_id($post->ID) === $image['id'] || self::already_used($post->post_content, $image, $references)) {
                throw new RuntimeException('Et foreslået billede er ændret, fjernet, gjort privat eller findes allerede på siden. Lav et nyt forslag.');
            }
        }
    }
}
