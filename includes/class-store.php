<?php
defined('ABSPATH') || exit;

final class AI0R_Store {
    public const RECOMMENDED_MODEL = 'gpt-6.1-sol';

    public static function legacy_prompt(): string {
        return 'Skriv på dansk i sidens eksisterende tone. Gør teksten klarere, mere konkret og nyttig for læseren. Bevar fakta, forbehold, personlige erfaringer og søgeintention. Undgå fyld og unødvendige omskrivninger. Tilføj kun elementer, som giver en tydelig forbedring. Opfind aldrig priser, resultater, citater, cases, kilder eller nye faktuelle påstande. Vælg ingen ændringer, hvis indholdet allerede fungerer godt.';
    }

    public static function table(string $suffix): string {
        global $wpdb;
        return $wpdb->prefix . 'ai0r_' . $suffix;
    }

    public static function defaults(): array {
        return [
            'enabled' => 0, 'mode' => 'review', 'model' => self::RECOMMENDED_MODEL, 'api_key' => '',
            'monthly_limit' => 10, 'monthly_tokens' => 250000, 'max_output' => 12000,
            'start_day' => 1, 'hour' => 3, 'post_types' => ['post', 'page'],
            'tracking' => 1, 'traffic_source' => 'both', 'additions' => 1, 'media_library' => 1, 'excluded_ids' => '',
            'custom_rates' => [],
            'google_client_id' => '', 'google_secret' => '', 'property' => '',
            'prompt' => "Actively improve the existing page in its current language, tone and for its audience. Identify concrete weaknesses in the introduction, readability, repetition, grammar and answers to reader questions, and fix them in editable text. Clarify the value and differences between offerings. Substantial rewriting is welcome when it makes content clearer and more useful; preserve existing facts and caveats. Consider a short summary, comparison table, checklist or FAQ drawn only from the page's information when helpful and not already present. Preserve images, videos, links, banners and layout. Never invent prices, guarantees, results, quotations, experiences or new factual claims. List specific improvement opportunities. Suggest no changes only after a real review finds no useful improvement, and explain exactly why. Keep the content in its existing language unless the saved editorial prompt explicitly requests a different language.",
        ];
    }

    public static function settings(): array {
        return array_merge(self::defaults(), (array) get_option('ai0r_settings', []));
    }

    public static function uses_internal(): bool {
        $s = self::settings();
        return (bool) $s['tracking'] && in_array($s['traffic_source'], ['internal','both'], true);
    }

    public static function uses_google(): bool {
        return in_array(self::settings()['traffic_source'], ['google','both'], true);
    }

    public static function view_cutoff(?DateTimeImmutable $today = null): string {
        $today = $today ?: current_datetime();
        $target = $today->setDate((int) $today->format('Y') - 1, (int) $today->format('m'), 1);
        return $target->setDate((int) $target->format('Y'), (int) $target->format('m'), min((int) $today->format('d'), (int) $target->format('t')))->format('Y-m-d');
    }

    public static function purge_views(): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM ' . self::table('views') . ' WHERE day < %s', self::view_cutoff()));
        // Also remove expired, short-lived abuse counters; never touch content-generation history.
        $names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d", $wpdb->esc_like('_transient_timeout_ai0r_rate_') . '%', time()));
        foreach ($names as $name) { delete_transient(substr($name, strlen('_transient_timeout_'))); }
    }

    public static function excluded(int $id): bool {
        return in_array($id, wp_parse_id_list(self::settings()['excluded_ids']), true) || (bool) get_post_meta($id, '_ai0r_excluded', true);
    }

    public static function set_excluded(int $id, bool $excluded): void {
        $s = self::settings();
        $ids = array_values(array_diff(wp_parse_id_list($s['excluded_ids']), [$id]));
        if ($excluded) { $ids[] = $id; }
        $s['excluded_ids'] = implode(',', $ids);
        update_option('ai0r_settings', $s, false);
        delete_post_meta($id, '_ai0r_excluded');
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $history = self::table('history');
        $views = self::table('views');
        dbDelta("CREATE TABLE $history (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint unsigned NOT NULL,
            created_at datetime NOT NULL,
            applied_at datetime NULL,
            actor_id bigint unsigned NOT NULL DEFAULT 0,
            status varchar(24) NOT NULL,
            model varchar(160) NOT NULL DEFAULT '',
            prompt longtext NOT NULL,
            summary text NOT NULL,
            details longtext NULL,
            before_content longtext NOT NULL,
            after_content longtext NOT NULL,
            before_hash varchar(64) NOT NULL,
            after_hash varchar(64) NOT NULL DEFAULT '',
            input_tokens bigint unsigned NOT NULL DEFAULT 0,
            output_tokens bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY post_status (post_id,status),
            KEY created_at (created_at)
        ) $charset;");
        dbDelta("CREATE TABLE $views (
            post_id bigint unsigned NOT NULL,
            day date NOT NULL,
            views bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (post_id,day),
            KEY day (day)
        ) $charset;");
        if (!$wpdb->get_var("SHOW COLUMNS FROM $history LIKE 'details'")) {
            update_option('ai0r_install_error', __('The plugin history table could not be upgraded. Contact your hosting provider; existing history has been retained.', 'ai0-content-refresh'), false);
            return;
        }
        delete_option('ai0r_install_error');
        if (!get_option('ai0r_settings')) {
            add_option('ai0r_settings', self::defaults(), '', false);
        } elseif (version_compare((string) get_option('ai0r_version', '0'), '1.1.0', '<')) {
            $s = self::settings();
            // Upgrade only the original shipped prompt; preserve editorial prompts and model choices.
            if ($s['prompt'] === self::legacy_prompt()) { $s['prompt'] = self::defaults()['prompt']; }
            $legacy_ids = get_posts(['post_type' => ['post','page'], 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_ai0r_excluded', 'meta_value' => '1']);
            $s['excluded_ids'] = implode(',', wp_parse_id_list(array_merge(wp_parse_id_list($s['excluded_ids']), $legacy_ids)));
            update_option('ai0r_settings', $s, false);
            foreach ($legacy_ids as $id) { delete_post_meta($id, '_ai0r_excluded'); }
        }
        update_option('ai0r_version', AI0R_VERSION, false);
        if (!wp_next_scheduled('ai0r_tick')) {
            wp_schedule_event(time() + 300, 'hourly', 'ai0r_tick');
        }
        if (!wp_next_scheduled('ai0r_google_sync')) {
            wp_schedule_event(time() + 600, 'daily', 'ai0r_google_sync');
        }
        if (!wp_next_scheduled('ai0r_cleanup')) {
            wp_schedule_event(time() + 60, 'daily', 'ai0r_cleanup');
        }
        if (!wp_next_scheduled('ai0r_exchange_sync')) {
            wp_schedule_event(time() + 60, 'daily', 'ai0r_exchange_sync');
        }
        self::purge_views();
    }

    // Secrets are never returned to the browser. WP salts bind encrypted values to this site.
    public static function encrypt(string $plain): string {
        if ($plain === '') { return ''; }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException(__('The server is missing OpenSSL for secure key storage.', 'ai0-content-refresh'));
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) { throw new RuntimeException(__('The key could not be encrypted.', 'ai0-content-refresh')); }
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $cipher): string {
        if ($cipher === '' || !function_exists('openssl_decrypt')) { return ''; }
        $raw = base64_decode($cipher, true);
        if ($raw === false || strlen($raw) < 29) { return ''; }
        return (string) openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', wp_salt('auth'), true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    }

    public static function lock(string $name = 'worker'): string {
        global $wpdb;
        $key = 'ai0r_lock_' . $name;
        $value = wp_generate_uuid4() . '|' . (time() + 240);
        $old = get_option($key);
        if ($old && (int) substr(strrchr((string) $old, '|'), 1) < time()) {
            $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name=%s AND option_value=%s", $key, $old));
            wp_cache_delete($key, 'options');
        }
        if (!add_option($key, $value, '', false)) {
            throw new RuntimeException(__('Another run is in progress. Try again shortly.', 'ai0-content-refresh'));
        }
        return $value;
    }

    public static function unlock(string $value, string $name = 'worker'): void {
        global $wpdb;
        $key = 'ai0r_lock_' . $name;
        $wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name=%s AND option_value=%s", $key, $value));
        wp_cache_delete($key, 'options');
    }

    public static function month_start(): string {
        return current_datetime()->modify('first day of this month')->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public static function usage(): array {
        global $wpdb;
        $t = self::table('history');
        return (array) $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) attempts, COALESCE(SUM(input_tokens+output_tokens),0) tokens FROM $t WHERE created_at >= %s", self::month_start()), ARRAY_A);
    }

    public static function history(int $id): ?object {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table('history') . ' WHERE id=%d', $id));
    }

    public static function record(array $data): int {
        global $wpdb;
        $data += ['post_id' => 0, 'created_at' => gmdate('Y-m-d H:i:s'), 'actor_id' => get_current_user_id(),
            'status' => 'running', 'model' => '', 'prompt' => '', 'summary' => '', 'details' => '', 'before_content' => '',
            'after_content' => '', 'before_hash' => '', 'after_hash' => '', 'input_tokens' => 0, 'output_tokens' => 0];
        if (!$wpdb->insert(self::table('history'), $data)) { throw new RuntimeException(__('History could not be saved. No API request was started.', 'ai0-content-refresh')); }
        return (int) $wpdb->insert_id;
    }

    public static function update(int $id, array $data): void {
        global $wpdb;
        if ($wpdb->update(self::table('history'), $data, ['id' => $id]) === false) {
            throw new RuntimeException(__('History could not be updated.', 'ai0-content-refresh'));
        }
    }

    public static function views(): array {
        global $wpdb;
        $today = current_datetime();
        $cut = $today->modify('-89 days')->format('Y-m-d');
        $start = self::view_cutoff($today);
        $t = self::table('views');
        $rows = $wpdb->get_results($wpdb->prepare("SELECT post_id, SUM(CASE WHEN day >= %s THEN views ELSE 0 END) v90, SUM(views) vyear FROM $t WHERE day >= %s AND day <= %s GROUP BY post_id", $cut, $start, $today->format('Y-m-d')), ARRAY_A);
        return array_column($rows, null, 'post_id');
    }
}
