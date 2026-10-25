<?php
defined('ABSPATH') || exit;

final class AI0R_Google {
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public static function redirect_uri(): string {
        return admin_url('admin-post.php?action=ai0r_oauth_callback');
    }

    public static function start(): void {
        $s = AI0R_Store::settings();
        if (!$s['google_client_id'] || !AI0R_Store::decrypt($s['google_secret'])) {
            throw new RuntimeException('Gem Google Client ID og Client Secret under Indstillinger først.');
        }
        if (wp_parse_url(self::redirect_uri(), PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Google-forbindelsen kræver HTTPS på WordPress-administrationen.');
        }
        $state = bin2hex(random_bytes(32));
        set_transient('ai0r_oauth_' . hash('sha256', $state), ['user' => get_current_user_id(), 'client' => $s['google_client_id']], 10 * MINUTE_IN_SECONDS);
        $url = add_query_arg(['client_id' => $s['google_client_id'], 'redirect_uri' => self::redirect_uri(),
            'response_type' => 'code', 'scope' => self::SCOPE, 'state' => $state, 'access_type' => 'offline', 'prompt' => 'consent'], 'https://accounts.google.com/o/oauth2/v2/auth');
        wp_redirect($url); // Fixed Google origin; state binds the callback to the current WP administrator.
        exit;
    }

    public static function callback(): void {
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        $key = 'ai0r_oauth_' . hash('sha256', $state);
        $saved = get_transient($key);
        delete_transient($key); // Authorization codes and states are single-use.
        $s = AI0R_Store::settings();
        if (!$saved || $saved['user'] !== get_current_user_id() || $saved['client'] !== $s['google_client_id']) {
            throw new RuntimeException('Google-login er udløbet eller tilhører en anden bruger. Start forbindelsen igen.');
        }
        if (!empty($_GET['error']) || empty($_GET['code'])) { throw new RuntimeException('Google-forbindelsen blev ikke godkendt.'); }
        $tokens = self::token_request(['code' => sanitize_text_field(wp_unslash($_GET['code'])), 'grant_type' => 'authorization_code', 'redirect_uri' => self::redirect_uri()]);
        if (empty($tokens['refresh_token'])) { throw new RuntimeException('Google gav ikke adgang til baggrundskørsel. Forbind kontoen igen.'); }
        self::save_tokens($tokens);
        self::sites(true);
    }

    private static function token_request(array $body): array {
        $s = AI0R_Store::settings();
        $body += ['client_id' => $s['google_client_id'], 'client_secret' => AI0R_Store::decrypt($s['google_secret'])];
        $r = wp_remote_post('https://oauth2.googleapis.com/token', ['timeout' => 25, 'redirection' => 0, 'body' => $body]);
        if (is_wp_error($r)) { throw new RuntimeException('Google kunne ikke kontaktes. Prøv igen senere.'); }
        $json = json_decode(wp_remote_retrieve_body($r), true);
        if (wp_remote_retrieve_response_code($r) !== 200 || empty($json['access_token'])) {
            throw new RuntimeException('Google-adgang kunne ikke oprettes eller fornyes. Kontroller klientopsætningen og forbind kontoen igen.');
        }
        if (isset($json['scope']) && !in_array(self::SCOPE, explode(' ', $json['scope']), true)) {
            throw new RuntimeException('Google gav ikke den nødvendige læseadgang til Search Console.');
        }
        return $json;
    }

    private static function save_tokens(array $new): void {
        $old = (array) get_option('ai0r_google_tokens', []);
        $data = ['access' => AI0R_Store::encrypt($new['access_token']), 'refresh' => !empty($new['refresh_token']) ? AI0R_Store::encrypt($new['refresh_token']) : ($old['refresh'] ?? ''),
            'expires' => time() + max(60, (int) ($new['expires_in'] ?? 3600)), 'client' => AI0R_Store::settings()['google_client_id']];
        update_option('ai0r_google_tokens', $data, false);
    }

    public static function connected(): bool {
        $t = (array) get_option('ai0r_google_tokens', []);
        return !empty($t['refresh']) && ($t['client'] ?? '') === AI0R_Store::settings()['google_client_id'];
    }

    private static function access(): string {
        $t = (array) get_option('ai0r_google_tokens', []);
        if (!self::connected()) { throw new RuntimeException('Forbind Google Search Console først.'); }
        if (($t['expires'] ?? 0) < time() + 90) {
            $lock = AI0R_Store::lock('google_token');
            try {
                // Re-read after acquiring the lock; another request may have refreshed it.
                $t = (array) get_option('ai0r_google_tokens', []);
                if (($t['expires'] ?? 0) < time() + 90) {
                    $refresh = AI0R_Store::decrypt($t['refresh'] ?? '');
                    if (!$refresh) { throw new RuntimeException('Forbind Google igen; den gemte adgang kunne ikke læses.'); }
                    self::save_tokens(self::token_request(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]));
                }
                $t = (array) get_option('ai0r_google_tokens', []);
            } finally { AI0R_Store::unlock($lock, 'google_token'); }
        }
        $access = AI0R_Store::decrypt($t['access'] ?? '');
        if (!$access) { throw new RuntimeException('Google-adgangen kunne ikke læses. Forbind kontoen igen.'); }
        return $access;
    }

    private static function request(string $path, ?array $body = null): array {
        $r = wp_remote_request('https://www.googleapis.com/webmasters/v3/' . $path, [
            'method' => $body === null ? 'GET' : 'POST', 'timeout' => 30, 'redirection' => 0,
            'headers' => ['Authorization' => 'Bearer ' . self::access(), 'Content-Type' => 'application/json'],
            'body' => $body === null ? null : wp_json_encode($body),
        ]);
        if (is_wp_error($r)) { throw new RuntimeException('Search Console kunne ikke kontaktes.'); }
        $status = wp_remote_retrieve_response_code($r);
        if ($status < 200 || $status >= 300) { throw new RuntimeException('Search Console afviste forespørgslen (HTTP ' . $status . '). Kontroller adgang og valgt website.'); }
        $json = json_decode(wp_remote_retrieve_body($r), true);
        if (!is_array($json)) { throw new RuntimeException('Search Console returnerede et ugyldigt svar.'); }
        return $json;
    }

    public static function sites(bool $refresh = false): array {
        $cached = get_transient('ai0r_google_sites');
        if (!$refresh && is_array($cached)) { return $cached; }
        $json = self::request('sites');
        $sites = array_values(array_filter($json['siteEntry'] ?? [], static function ($s) {
            return !empty($s['siteUrl']) && ($s['permissionLevel'] ?? '') !== 'siteUnverifiedUser';
        }));
        set_transient('ai0r_google_sites', $sites, HOUR_IN_SECONDS);
        return $sites;
    }

    public static function disconnect(): void {
        $tokens = (array) get_option('ai0r_google_tokens', []);
        $refresh = AI0R_Store::decrypt($tokens['refresh'] ?? '');
        if ($refresh) {
            wp_remote_post('https://oauth2.googleapis.com/revoke', ['timeout' => 10, 'redirection' => 0, 'body' => ['token' => $refresh]]);
        }
        delete_option('ai0r_google_tokens');
        delete_option('ai0r_google_stats');
        delete_transient('ai0r_google_sites');
    }

    private static function period(string $property, string $start, string $end): array {
        $result = [];
        for ($offset = 0; $offset < 100000; $offset += 25000) {
            $json = self::request('sites/' . rawurlencode($property) . '/searchAnalytics/query', [
                'startDate' => $start, 'endDate' => $end, 'dimensions' => ['page'], 'type' => 'web',
                'dataState' => 'final', 'rowLimit' => 25000, 'startRow' => $offset,
            ]);
            $rows = $json['rows'] ?? [];
            foreach ($rows as $row) {
                $url = $row['keys'][0] ?? '';
                if ($url) { $result[$url] = ['clicks' => (int) ($row['clicks'] ?? 0), 'impressions' => (int) ($row['impressions'] ?? 0), 'ctr' => (float) ($row['ctr'] ?? 0), 'position' => (float) ($row['position'] ?? 0)]; }
            }
            if (count($rows) < 25000) { break; }
        }
        return $result;
    }

    public static function sync(): void {
        $s = AI0R_Store::settings();
        if (!$s['property'] || !self::connected()) { return; }
        $lock = AI0R_Store::lock('google_sync');
        try {
            $end = (new DateTimeImmutable('today', new DateTimeZone('America/Los_Angeles')))->modify('-3 days');
            $start = $end->modify('-27 days');
            $previous_end = $start->modify('-1 day');
            $previous_start = $previous_end->modify('-27 days');
            $current = self::period($s['property'], $start->format('Y-m-d'), $end->format('Y-m-d'));
            $previous = self::period($s['property'], $previous_start->format('Y-m-d'), $previous_end->format('Y-m-d'));
            update_option('ai0r_google_stats', ['property' => $s['property'], 'synced' => time(), 'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d'), 'current' => $current, 'previous' => $previous], false);
            delete_option('ai0r_google_error');
        } catch (Throwable $e) {
            update_option('ai0r_google_error', $e->getMessage(), false);
            throw $e;
        } finally { AI0R_Store::unlock($lock, 'google_sync'); }
    }
}
