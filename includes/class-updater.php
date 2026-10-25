<?php
defined('ABSPATH') || exit;

/** Public GitHub release updates through WordPress' native plugin updater. */
final class AI0R_Updater {
    public const REPOSITORY = 'https://github.com/andersburn/ai0-content-refresh';
    public const API = 'https://api.github.com/repos/andersburn/ai0-content-refresh/releases/latest';
    private const CACHE = 'ai0r_github_release';

    public static function boot(): void {
        add_filter('update_plugins_github.com', [self::class, 'update'], 10, 4);
        add_filter('plugins_api', [self::class, 'information'], 10, 3);
        // Opening Dashboard > Updates allows a fresh check through the normal WP UI.
        add_action('load-update-core.php', static function () { delete_site_transient(self::CACHE); });
    }

    /** Accept only HTTPS download URLs for assets in this exact repository. */
    private static function asset_url(string $url, string $tag, string $filename): bool {
        return $url === self::REPOSITORY . '/releases/download/' . $tag . '/' . $filename;
    }

    public static function release(): ?array {
        $cached = get_site_transient(self::CACHE);
        if (is_array($cached)) { return $cached ?: null; }
        // Brief negative cache prevents repeated calls during errors or rate limits.
        set_site_transient(self::CACHE, [], 5 * MINUTE_IN_SECONDS);
        $args = ['timeout' => 10, 'redirection' => 0, 'limit_response_size' => 262144,
            'headers' => ['Accept' => 'application/vnd.github+json'],
            'user-agent' => 'Ai0-Content-Refresh/' . AI0R_VERSION];
        $response = wp_remote_get(self::API, $args);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return null; }
        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) ||
            !is_string($release['tag_name'] ?? null) || !preg_match('/^v(\d+\.\d+\.\d+)$/D', $release['tag_name'], $match)) { return null; }
        $tag = $release['tag_name'];
        $version = $match[1];
        $package = $manifest = '';
        foreach ((array) ($release['assets'] ?? []) as $asset) {
            if (!is_array($asset) || !is_string($asset['browser_download_url'] ?? null)) { continue; }
            $name = $asset['name'] ?? '';
            if ($name === 'ai0-content-refresh-' . $version . '.zip' && self::asset_url($asset['browser_download_url'], $tag, $name)) {
                $package = $asset['browser_download_url'];
            }
            if ($name === 'update.json' && self::asset_url($asset['browser_download_url'], $tag, $name)) { $manifest = $asset['browser_download_url']; }
        }
        if (!$package || !$manifest) { return null; }
        // GitHub redirects asset downloads to its storage host; WordPress validates redirects.
        $meta_response = wp_safe_remote_get($manifest, array_replace($args, ['redirection' => 3, 'limit_response_size' => 16384, 'headers' => ['Accept' => 'application/json']]));
        if (is_wp_error($meta_response) || wp_remote_retrieve_response_code($meta_response) !== 200) { return null; }
        $meta = json_decode(wp_remote_retrieve_body($meta_response), true);
        if (!is_array($meta) || ($meta['version'] ?? '') !== $version) { return null; }
        foreach (['requires', 'requires_php', 'tested'] as $field) {
            if (!is_string($meta[$field] ?? null) || !preg_match('/^\d+\.\d+(?:\.\d+)?$/D', $meta[$field])) { return null; }
        }
        $result = ['version' => $version, 'package' => $package, 'requires' => $meta['requires'],
            'requires_php' => $meta['requires_php'], 'tested' => $meta['tested'],
            'url' => self::REPOSITORY . '/releases/tag/' . $tag,
            'notes' => is_string($release['body'] ?? null) ? wp_kses_post($release['body']) : ''];
        set_site_transient(self::CACHE, $result, 6 * HOUR_IN_SECONDS);
        return $result;
    }

    public static function update($update, array $plugin_data, string $plugin_file, array $locales) {
        if ($plugin_file !== plugin_basename(AI0R_FILE) || ($plugin_data['UpdateURI'] ?? '') !== self::REPOSITORY) { return $update; }
        $release = self::release();
        if (!$release) { return $update; }
        return ['id' => self::REPOSITORY, 'slug' => 'ai0-content-refresh', 'version' => $release['version'],
            'url' => $release['url'], 'package' => $release['package'], 'tested' => $release['tested'],
            'requires' => $release['requires'], 'requires_php' => $release['requires_php']];
        // No autoupdate flag: the administrator's existing WordPress preference wins.
    }

    public static function information($result, string $action, $args) {
        if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'ai0-content-refresh') { return $result; }
        $release = self::release();
        if (!$release) { return $result; }
        return (object) ['name' => 'Ai0 Content Refresh', 'slug' => 'ai0-content-refresh',
            'version' => $release['version'], 'author' => 'Ai0', 'homepage' => self::REPOSITORY,
            'requires' => $release['requires'], 'requires_php' => $release['requires_php'], 'tested' => $release['tested'],
            'download_link' => $release['package'], 'external' => true,
            'sections' => ['description' => esc_html__('AI-assisted SEO improvements for existing WordPress pages and posts, with preserved media, traffic-based priorities and review history.', 'ai0-content-refresh'),
                'changelog' => '<pre style="white-space:pre-wrap">' . esc_html(wp_strip_all_tags($release['notes'])) . '</pre>']];
    }
}
