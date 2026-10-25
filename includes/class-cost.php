<?php
defined('ABSPATH') || exit;

/** Price snapshots are estimates, never billing records. USD per million tokens. */
final class AI0R_Cost {
    public static function catalog(): array {
        return [
            'gpt-6.1-sol' => ['input' => 2.0, 'output' => 10.0, 'cached' => 0.10, 'write' => 2.50],
            'gpt-6-astra' => ['input' => 10.0, 'output' => 50.0, 'cached' => 1.0, 'write' => 12.50],
            'gpt-6-luna' => ['input' => 0.10, 'output' => 0.50, 'cached' => 0.01, 'write' => 0.125],
        ];
    }

    public static function rates(string $model, array $s): ?array {
        if (isset($s['custom_rates'][$model])) {
            $r = $s['custom_rates'][$model];
            return $r + ['source' => 'Egne takster', 'long_context' => false];
        }
        $r = self::catalog()[$model] ?? null;
        return $r ? $r + ['source' => 'OpenAI standardtakster, 25.10.2026', 'long_context' => true] : null;
    }

    public static function calculate(int $input, int $output, int $cached, int $write, array $rates): float {
        $input = max(0, $input); $output = max(0, $output);
        $cached = min($input, max(0, $cached));
        $write = min($input - $cached, max(0, $write));
        $long = !empty($rates['long_context']) && $input > 272000;
        return (($input - $cached - $write) * $rates['input'] * ($long ? 2 : 1)
            + $cached * $rates['cached'] * ($long ? 2 : 1)
            + $write * $rates['write'] * ($long ? 2 : 1)
            + $output * $rates['output'] * ($long ? 1.5 : 1)) / 1000000;
    }

    public static function forecast(array $body, string $content, array $s): array {
        $rates = self::rates($body['model'], $s);
        $result = ['state' => $rates ? 'forecast' : 'unavailable', 'model' => $body['model'], 'rates' => $rates, 'usd_dkk' => (float) $s['usd_dkk']];
        if (!$rates) { return $result; }
        // Heuristic, not a tokenizer. Include instructions and schema, not only article text.
        $input = (int) ceil(mb_strlen(wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) / 3);
        $output = min((int) $body['max_output_tokens'], max(1000, (int) ceil(mb_strlen(wp_strip_all_tags($content)) / 3 * 0.8) + 600));
        $result['usd'] = self::calculate($input, $output, 0, 0, $rates);
        $result['scenario_usd'] = self::calculate(strlen(wp_json_encode($body)) + 2048, (int) $body['max_output_tokens'], 0, 0, $rates);
        $result['estimated_input'] = $input; $result['estimated_output'] = $output;
        return $result;
    }

    public static function from_usage(array $cost, array $usage): array {
        if (!isset($usage['input_tokens'], $usage['output_tokens'])) { return $cost; }
        $cost['usage_reported'] = true;
        if (!$cost['rates']) { return $cost; }
        $detail = (array) ($usage['input_tokens_details'] ?? []);
        $cost['usd'] = self::calculate((int) $usage['input_tokens'], (int) $usage['output_tokens'], (int) ($detail['cached_tokens'] ?? 0), (int) ($detail['cache_write_tokens'] ?? $detail['cache_creation_tokens'] ?? 0), $cost['rates']);
        $cost['state'] = 'usage';
        return $cost;
    }

    public static function amount(float $usd, float $exchange): string {
        return '$' . number_format($usd, 4, ',', '.') . ' ≈ ' . number_format($usd * $exchange, 2, ',', '.') . ' kr.';
    }

    public static function label(?array $cost): string {
        if (!$cost) { return 'Pris ikke registreret'; }
        if (!isset($cost['usd'])) { return 'Ukendt pris – angiv modellens takster'; }
        return self::amount($cost['usd'], $cost['usd_dkk']) . ($cost['state'] === 'usage' ? ' (rapporteret tokenforbrug)' : ' (overslag; forbrug ukendt)');
    }
}
