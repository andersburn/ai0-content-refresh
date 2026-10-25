<?php
defined('ABSPATH') || exit;

/** Daily reference-rate conversion, independent of OpenAI model pricing. */
final class AI0R_Exchange {
    public const ENDPOINT = 'https://api.frankfurter.dev/v2/providers/ecb/rate/usd/dkk';
    public const SOURCE = 'Frankfurter / ECB';

    public static function valid($data): bool {
        if (!is_array($data) || ($data['base'] ?? '') !== 'USD' || ($data['quote'] ?? '') !== 'DKK' || !is_numeric($data['rate'] ?? null)) { return false; }
        $rate = (float) $data['rate'];
        if (!is_finite($rate) || $rate <= 0 || $rate > 1000 || !is_string($data['date'] ?? null)) { return false; }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date'], new DateTimeZone('UTC'));
        return $date && $date->format('Y-m-d') === $data['date'] && $data['date'] >= '2000-01-01' && $data['date'] <= gmdate('Y-m-d', time() + DAY_IN_SECONDS);
    }

    public static function current(bool $fetch = true): ?array {
        $cached = get_option('ai0r_exchange', null);
        if (!self::valid($cached)) { $cached = null; }
        $fresh = $cached && (int) ($cached['checked'] ?? 0) > time() - DAY_IN_SECONDS && $cached['date'] >= gmdate('Y-m-d', time() - 7 * DAY_IN_SECONDS);
        if ($fetch && !$fresh && !get_transient('ai0r_exchange_retry')) {
            // Avoid a network request per visible row, and throttle failures to once per hour.
            set_transient('ai0r_exchange_retry', 1, HOUR_IN_SECONDS);
            $response = wp_remote_get(self::ENDPOINT, ['timeout' => 8, 'redirection' => 0, 'user-agent' => 'Ai0 Content Refresh currency conversion', 'headers' => ['Accept' => 'application/json']]);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                // Never replace the last valid observation with malformed, very old or regressing data.
                if (self::valid($data) && $data['date'] >= gmdate('Y-m-d', time() - 7 * DAY_IN_SECONDS) && (!$cached || $data['date'] >= $cached['date'])) {
                    $cached = ['base' => 'USD', 'quote' => 'DKK', 'rate' => (float) $data['rate'], 'date' => $data['date'], 'checked' => time(), 'source' => self::SOURCE];
                    update_option('ai0r_exchange', $cached, false);
                    $fresh = true;
                }
            }
        }
        if (!$cached) { return null; }
        $cached['stale'] = !$fresh;
        return $cached;
    }

    public static function description(?array $exchange): string {
        if (!$exchange) { return __('The exchange rate could not be retrieved yet. Prices are shown in USD until an online rate is available.', 'ai0-content-refresh'); }
        return ($exchange['stale'] ? (__('Last known rate — update pending.', 'ai0-content-refresh') . ' ') : '')
            . '1 USD ≈ ' . number_format_i18n($exchange['rate'], 4) . (' ' . __('DKK · rate date', 'ai0-content-refresh') . ' ') . $exchange['date'] . ' · ' . ($exchange['source'] ?? self::SOURCE);
    }
}
