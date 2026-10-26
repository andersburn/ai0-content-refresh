<?php
defined('ABSPATH') || exit;

final class AI0R_Admin {
    public static function boot(): void {
        add_action('admin_menu', static function () { add_menu_page('Ai0 Content Refresh', 'Content Refresh', 'manage_options', 'ai0-refresh', [self::class, 'page'], 'dashicons-update-alt', 59); });
        add_action('admin_enqueue_scripts', static function ($hook) {
            if ($hook === 'toplevel_page_ai0-refresh') {
                wp_enqueue_style('ai0r-admin', plugins_url('assets/admin.css', AI0R_FILE), [], AI0R_VERSION);
                wp_enqueue_script('ai0r-admin', plugins_url('assets/admin.js', AI0R_FILE), ['wp-i18n'], AI0R_VERSION, true);
                wp_set_script_translations('ai0r-admin', 'ai0-content-refresh', AI0R_DIR . 'languages');
            }
        });
        add_action('admin_post_ai0r_action', [self::class, 'action']);
        add_action('admin_post_ai0r_oauth_callback', static function () {
            if (!current_user_can('manage_options')) { wp_die(__('Access denied.', 'ai0-content-refresh'), '', ['response' => 403]); }
            try { AI0R_Google::callback(); self::notice(__('Google is connected. Select a property below.', 'ai0-content-refresh')); }
            catch (Throwable $e) { self::notice($e->getMessage(), true); }
            wp_safe_redirect(admin_url('admin.php?page=ai0-refresh&tab=google'));
            exit;
        });
        add_action('add_meta_boxes', static function () {
            foreach (['post','page'] as $type) { add_meta_box('ai0r-priority', 'Content Refresh', [self::class, 'metabox'], $type, 'side'); }
        });
        add_action('save_post', [self::class, 'save_meta'], 20, 2);
    }

    private static function notice(string $message, bool $error = false): void {
        set_transient('ai0r_notice_' . get_current_user_id(), ['text' => $message, 'error' => $error], MINUTE_IN_SECONDS);
    }

    private static function form(string $do, array $extra = [], string $tab = 'queue'): void {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="ai0r-inline">';
        foreach (array_merge(['action' => 'ai0r_action', 'do' => $do, 'tab' => $tab], $extra) as $k => $v) {
            echo '<input type="hidden" name="' . esc_attr($k) . '" value="' . esc_attr((string) $v) . '">';
        }
        wp_nonce_field('ai0r_admin');
    }

    private static function button(string $do, string $label, array $extra = [], string $tab = 'queue', bool $primary = false): void {
        self::form($do, $extra, $tab);
        echo '<button class="button ' . ($primary ? 'button-primary' : '') . '">' . esc_html($label) . '</button></form>';
    }

    private static function generation_button(string $do, array $extra = [], string $tab = 'queue', bool $primary = false, bool $retry = false): void {
        $mode = AI0R_Store::settings()['mode'];
        if ($do === 'next') {
            $label = $mode === 'auto' ? __('Generate and publish the next page', 'ai0-content-refresh') : __('Generate a proposal for the next page', 'ai0-content-refresh');
        } elseif ($mode === 'auto') {
            $label = $retry ? __('Generate and publish a new version', 'ai0-content-refresh') : __('Generate and publish', 'ai0-content-refresh');
        } else {
            $label = $retry ? __('Try another review', 'ai0-content-refresh') : __('Generate proposal', 'ai0-content-refresh');
        }
        self::button($do, $label, $extra + ['generation_mode' => $mode], $tab, $primary);
    }

    /** Bind a manual run to the publishing choice shown on its submit button. */
    private static function generate_from_admin(int $post_id): int {
        if (!current_user_can('edit_post', $post_id)) { throw new RuntimeException(__('You are not allowed to edit this content.', 'ai0-content-refresh')); }
        $mode = sanitize_key(wp_unslash($_POST['generation_mode'] ?? 'review'));
        if (!in_array($mode, ['review', 'auto'], true) || $mode !== AI0R_Store::settings()['mode']) {
            throw new RuntimeException(__('The publishing mode has changed. Reload this page before generating content.', 'ai0-content-refresh'));
        }
        return AI0R_Plugin::generate($post_id, $mode === 'auto');
    }

    private static function generation_notice(int $id): void {
        $h = AI0R_Store::history($id);
        if ($h && $h->status === 'applied') {
            self::notice(__('The changes have been published automatically. View the result and restore options in the history.', 'ai0-content-refresh'));
        } elseif ($h && $h->status === 'no_change') {
            self::notice(__('The model suggested no changes. The page is unchanged; see the explanation in the history.', 'ai0-content-refresh'));
        } else {
            self::notice(__('The proposal is waiting for your approval. Open the history and click Publish this proposal to apply it.', 'ai0-content-refresh'));
        }
    }

    public static function action(): void {
        if (!current_user_can('manage_options') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die(__('Access denied.', 'ai0-content-refresh'), '', ['response' => 403]); }
        check_admin_referer('ai0r_admin');
        $do = sanitize_key($_POST['do'] ?? '');
        $tab = sanitize_key($_POST['tab'] ?? 'queue');
        $detail = 0;
        try {
            switch ($do) {
                case 'settings': self::save_settings(); self::notice(__('Settings saved.', 'ai0-content-refresh')); break;
                case 'models':
                    $models = AI0R_Plugin::models();
                    self::notice((__('The API key works. Retrieved', 'ai0-content-refresh') . ' ') . count($models) . (' ' . __('model names. Choose a text model that supports Responses and Structured Outputs.', 'ai0-content-refresh'))); break;
                case 'connect': AI0R_Google::start(); break;
                case 'disconnect': AI0R_Google::disconnect(); self::notice(__('The Google connection has been removed locally. You can also revoke access in your Google Account.', 'ai0-content-refresh')); break;
                case 'property':
                    $property = sanitize_text_field(wp_unslash($_POST['property'] ?? ''));
                    if (!in_array($property, array_column(AI0R_Google::sites(true), 'siteUrl'), true)) { throw new RuntimeException(__('You do not have access to the selected property.', 'ai0-content-refresh')); }
                    $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
                    $matches = str_starts_with($property, 'sc-domain:') ? ($host === substr($property, 10) || str_ends_with($host, '.' . substr($property, 10))) : strtolower((string) wp_parse_url($property, PHP_URL_HOST)) === $host;
                    if (!$matches) { throw new RuntimeException(__('Choose a Search Console property that covers this WordPress domain.', 'ai0-content-refresh')); }
                    $s = AI0R_Store::settings(); $s['property'] = $property;
                    update_option('ai0r_settings', $s, false);
                    delete_option('ai0r_google_stats');
                    AI0R_Google::sync(); self::notice(__('Property selected and search data retrieved.', 'ai0-content-refresh')); break;
                case 'sync': AI0R_Google::sync(); self::notice(__('Search data updated.', 'ai0-content-refresh')); break;
                case 'generate':
                    $post_id = absint($_POST['post_id'] ?? 0);
                    $detail = self::generate_from_admin($post_id); $tab = 'history';
                    self::generation_notice($detail); break;
                case 'next':
                    foreach (AI0R_Plugin::queue() as $row) {
                        if (!$row['blocked']) { $detail = self::generate_from_admin($row['post']->ID); break; }
                    }
                    if (!$detail) { throw new RuntimeException(__('There are no pages eligible for an update right now.', 'ai0-content-refresh')); }
                    $tab = 'history'; self::generation_notice($detail); break;
                case 'apply':
                case 'restore':
                    $detail = absint($_POST['id'] ?? 0);
                    AI0R_Plugin::apply($detail, $do === 'restore');
                    self::notice($do === 'restore' ? __('Content restored. The six-month cooldown still applies.', 'ai0-content-refresh') : __('The proposal has been published.', 'ai0-content-refresh')); break;
                case 'reject':
                    $detail = absint($_POST['id'] ?? 0); $h = AI0R_Store::history($detail);
                    if (!$h || $h->status !== 'pending' || !current_user_can('edit_post', (int) $h->post_id)) { throw new RuntimeException(__('This proposal cannot be rejected.', 'ai0-content-refresh')); }
                    AI0R_Store::update($detail, ['status' => 'rejected']);
                    update_post_meta($h->post_id, '_ai0r_retry_after', time() + 30 * DAY_IN_SECONDS);
                    self::notice(__('The proposal has been rejected.', 'ai0-content-refresh')); break;
                case 'priority':
                    $post_id = absint($_POST['post_id'] ?? 0);
                    if (!current_user_can('edit_post', $post_id)) { throw new RuntimeException(__('Access denied.', 'ai0-content-refresh')); }
                    update_post_meta($post_id, '_ai0r_priority', max(0, min(2, (int) ($_POST['priority'] ?? 0))));
                    AI0R_Store::set_excluded($post_id, !empty($_POST['excluded']));
                    self::notice(__('Priority saved.', 'ai0-content-refresh')); break;
                default: throw new RuntimeException(__('Unknown action.', 'ai0-content-refresh'));
            }
        } catch (Throwable $e) { self::notice($e->getMessage(), true); }
        $args = ['page' => 'ai0-refresh', 'tab' => in_array($tab, ['queue','history','settings','google'], true) ? $tab : 'queue'];
        if ($detail) { $args['id'] = $detail; }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private static function number(string $key, int $min, int $max, int $default): int {
        return max($min, min($max, isset($_POST[$key]) ? (int) $_POST[$key] : $default));
    }

    private static function save_settings(): void {
        $s = AI0R_Store::settings();
        $old_client = $s['google_client_id'];
        $new_secret = trim((string) wp_unslash($_POST['google_secret'] ?? ''));
        $new_key = trim((string) wp_unslash($_POST['api_key'] ?? ''));
        if ($new_key !== '') {
            if (strlen($new_key) > 512 || preg_match('/\s/', $new_key)) { throw new RuntimeException(__('The API key has an invalid format.', 'ai0-content-refresh')); }
            $s['api_key'] = AI0R_Store::encrypt($new_key);
            delete_option('ai0r_models');
        } elseif (!empty($_POST['remove_api_key'])) { $s['api_key'] = ''; delete_option('ai0r_models'); }
        $s['google_client_id'] = sanitize_text_field(wp_unslash($_POST['google_client_id'] ?? ''));
        if ($new_secret !== '') { $s['google_secret'] = AI0R_Store::encrypt($new_secret); }
        if ($old_client !== $s['google_client_id'] || $new_secret !== '') {
            AI0R_Google::disconnect();
            $s['property'] = '';
        }
        $s['enabled'] = !empty($_POST['enabled']) ? 1 : 0;
        $s['mode'] = ($_POST['mode'] ?? '') === 'auto' ? 'auto' : 'review';
        $choice = (string) ($_POST['model_choice'] ?? $_POST['model'] ?? '');
        $s['model'] = sanitize_text_field(wp_unslash($choice === '__other__' ? ($_POST['custom_model'] ?? '') : $choice));
        if ($s['model'] === '') { throw new RuntimeException(__('Choose a model or complete the Other model field.', 'ai0-content-refresh')); }
        if (strlen($s['model']) > 160 || preg_match('/[^a-zA-Z0-9._:\-]/', $s['model'])) { throw new RuntimeException(__('The model name has an invalid format.', 'ai0-content-refresh')); }
        $s['traffic_source'] = in_array($_POST['traffic_source'] ?? '', ['google','internal','both'], true) ? $_POST['traffic_source'] : $s['traffic_source'];
        unset($s['usd_dkk']); // Old manual setting is no longer used; historical snapshots remain intact.
        if (!empty($_POST['custom_pricing'])) {
            $rates = [];
            foreach (['input','output','cached','write'] as $type) {
                $value = str_replace(',', '.', (string) ($_POST['rate_' . $type] ?? ''));
                if (!is_numeric($value) || (float) $value < 0 || (float) $value > 100000) { throw new RuntimeException(__('Enter all four rates as numbers from 0 to 100,000 USD per million tokens.', 'ai0-content-refresh')); }
                $rates[$type] = (float) $value;
            }
            $s['custom_rates'][$s['model']] = $rates;
        } elseif (isset($_POST['model_choice'])) { unset($s['custom_rates'][$s['model']]); }
        $s['monthly_limit'] = self::number('monthly_limit', 1, 500, 10);
        $s['monthly_tokens'] = self::number('monthly_tokens', 10000, 10000000, 250000);
        $s['max_output'] = self::number('max_output', 1000, 32000, 12000);
        $s['start_day'] = self::number('start_day', 1, 28, 1);
        $s['hour'] = self::number('hour', 0, 23, 3);
        $s['post_types'] = array_values(array_intersect(['post','page'], (array) ($_POST['post_types'] ?? [])));
        if (!$s['post_types']) { throw new RuntimeException(__('Select at least one content type.', 'ai0-content-refresh')); }
        $s['excluded_ids'] = implode(',', wp_parse_id_list(wp_unslash($_POST['excluded_ids'] ?? '')));
        $s['tracking'] = !empty($_POST['tracking']) ? 1 : 0;
        $s['additions'] = !empty($_POST['additions']) ? 1 : 0;
        $s['media_library'] = !empty($_POST['media_library']) ? 1 : 0;
        $s['prompt'] = sanitize_textarea_field(wp_unslash($_POST['prompt'] ?? ''));
        if (mb_strlen($s['prompt']) > 12000) { throw new RuntimeException(__('The prompt must not exceed 12,000 characters.', 'ai0-content-refresh')); }
        update_option('ai0r_settings', $s, false);
    }

    public static function metabox(WP_Post $p): void {
        wp_nonce_field('ai0r_priority_' . $p->ID, 'ai0r_meta_nonce');
        echo ('<p>' . esc_html__('Priority for the next content review:', 'ai0-content-refresh') . '</p><select name="ai0r_priority">');
        foreach ([0 => __('Normal', 'ai0-content-refresh'), 1 => __('Important', 'ai0-content-refresh'), 2 => __('Very important', 'ai0-content-refresh')] as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '" ' . selected((int) get_post_meta($p->ID, '_ai0r_priority', true), $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p><label><input type="checkbox" name="ai0r_excluded" value="1" ' . checked(AI0R_Store::excluded($p->ID), true, false) . ('> ' . esc_html__('Exclude this page', 'ai0-content-refresh') . '</label></p>');
        if ($p->post_status === 'publish') { echo ('<p>' . esc_html__('Earliest next update:', 'ai0-content-refresh') . ' <strong>') . esc_html(wp_date('d.m.Y', AI0R_Plugin::next_allowed($p)->getTimestamp())) . '</strong></p>'; }
    }

    public static function save_meta(int $id, WP_Post $p): void {
        if (!isset($_POST['ai0r_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ai0r_meta_nonce'])), 'ai0r_priority_' . $id) || !current_user_can('edit_post', $id) || wp_is_post_revision($id) || wp_is_post_autosave($id) || !in_array($p->post_type, ['post','page'], true)) { return; }
        update_post_meta($id, '_ai0r_priority', max(0, min(2, (int) ($_POST['ai0r_priority'] ?? 0))));
        AI0R_Store::set_excluded($id, !empty($_POST['ai0r_excluded']));
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) { return; }
        $tab = sanitize_key($_GET['tab'] ?? 'queue');
        echo ('<div class="wrap ai0r-admin"><h1>Ai0 Content Refresh</h1><p class="ai0r-lead">' . esc_html__('Review your existing content. Preserve media and layout, and track every change.', 'ai0-content-refresh') . '</p>');
        $notice = get_transient('ai0r_notice_' . get_current_user_id());
        delete_transient('ai0r_notice_' . get_current_user_id());
        if ($notice) { echo '<div class="notice ' . ($notice['error'] ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($notice['text']) . '</p></div>'; }
        if (get_option('ai0r_install_error')) { echo '<div class="notice notice-error"><p>' . esc_html(get_option('ai0r_install_error')) . '</p></div>'; }
        echo '<nav class="nav-tab-wrapper">';
        foreach (['queue' => __('Content & priorities', 'ai0-content-refresh'), 'history' => __('Generation history', 'ai0-content-refresh'), 'settings' => __('Settings', 'ai0-content-refresh'), 'google' => __('Google Search Console', 'ai0-content-refresh')] as $key => $label) {
            echo '<a class="nav-tab ' . ($key === $tab ? 'nav-tab-active' : '') . '" href="' . esc_url(add_query_arg(['page' => 'ai0-refresh', 'tab' => $key], admin_url('admin.php'))) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';
        try {
            if ($tab === 'settings') { self::settings(); }
            elseif ($tab === 'google') { self::google(); }
            elseif ($tab === 'history') { self::history(); }
            else { self::queue(); }
        } catch (Throwable $e) { echo '<div class="notice notice-error"><p>' . esc_html($e->getMessage()) . '</p></div>'; }
        echo '</div>';
    }

    private static function queue(): void {
        $s = AI0R_Store::settings();
        $usage = AI0R_Store::usage();
        $rows = AI0R_Plugin::queue();
        $eligible = count(array_filter($rows, static fn($r) => !$r['blocked']));
        echo '<div class="ai0r-cards">';
        foreach ([__('Content in the overview', 'ai0-content-refresh') => count($rows), __('Ready for review', 'ai0-content-refresh') => $eligible, __('Generations this month', 'ai0-content-refresh') => (int) $usage['attempts'] . ' / ' . $s['monthly_limit'], __('Scheduled review', 'ai0-content-refresh') => $s['enabled'] ? ($s['mode'] === 'auto' ? __('Publishes automatically', 'ai0-content-refresh') : __('Saves proposals', 'ai0-content-refresh')) : __('Disabled', 'ai0-content-refresh')] as $label => $value) {
            echo '<div class="ai0r-card"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div><p class="description">' . esc_html(AI0R_Exchange::description(AI0R_Exchange::current())) . ('</p><p>' . esc_html__('Priorities use your selections, Yoast cornerstone content, the selected traffic source and content age. The six-month cooldown starts at the latest publication, manual content change or content update by this plugin.', 'ai0-content-refresh') . '</p>');
        foreach (['ai0r_worker_error', 'ai0r_google_error'] as $option) { if (get_option($option)) { echo '<p class="ai0r-warning">' . esc_html(get_option($option)) . '</p>'; } }
        echo '<p class="' . ($s['mode'] === 'auto' ? 'ai0r-warning' : 'description') . '"><strong>' . esc_html__('Publishing mode:', 'ai0-content-refresh') . '</strong> ' . esc_html($s['mode'] === 'auto'
            ? __('Automatic publishing is on. New manual and scheduled runs update the live page immediately after technical validation.', 'ai0-content-refresh')
            : __('Approval is required. New manual and scheduled runs save proposals; click Publish this proposal in the history to apply them.', 'ai0-content-refresh')) . '</p>';
        self::generation_button('next', [], 'queue', true);
        echo '<p class="description">' . esc_html__('All runs count towards the monthly limit. Scheduled reviews process at most one page per hour from the selected day of the month. Earlier pending proposals still require approval.', 'ai0-content-refresh') . '</p>';
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        echo ('<form method="get" class="ai0r-search"><input type="hidden" name="page" value="ai0-refresh"><label for="ai0r-search">' . esc_html__('Find content', 'ai0-content-refresh') . ' </label><input id="ai0r-search" type="search" name="s" value="') . esc_attr($search) . ('"><button class="button">' . esc_html__('Search', 'ai0-content-refresh') . '</button></form>');
        if ($search) { $rows = array_values(array_filter($rows, static fn($r) => mb_stripos($r['post']->post_title, $search) !== false)); }
        $page = max(1, absint($_GET['paged'] ?? 1));
        echo ('<div class="ai0r-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__('Content', 'ai0-content-refresh') . '</th><th>' . esc_html__('Views', 'ai0-content-refresh') . '<br>' . esc_html__('90 days / 1 year', 'ai0-content-refresh') . '</th><th>' . esc_html__('Google', 'ai0-content-refresh') . '<br>' . esc_html__('28 days', 'ai0-content-refresh') . '</th><th>' . esc_html__('Priority', 'ai0-content-refresh') . '</th><th>' . esc_html__('Estimated cost', 'ai0-content-refresh') . '<br>' . esc_html__('per review', 'ai0-content-refresh') . '</th><th>' . esc_html__('Status / action', 'ai0-content-refresh') . '</th></tr></thead><tbody>');
        foreach (array_slice($rows, ($page - 1) * 25, 25) as $r) {
            $p = $r['post']; $g = $r['google'];
            echo '<tr><td><strong><a href="' . esc_url(get_edit_post_link($p->ID)) . '">' . esc_html($p->post_title ?: __('(Untitled)', 'ai0-content-refresh')) . '</a></strong><br>' . esc_html($p->post_type === 'page' ? __('Page', 'ai0-content-refresh') : __('Blog post', 'ai0-content-refresh')) . ' · ID ' . (int) $p->ID . '<br><a target="_blank" rel="noopener" href="' . esc_url($r['url']) . ('">' . esc_html__('View page ↗', 'ai0-content-refresh') . '</a></td>');
            echo '<td>' . (AI0R_Store::uses_internal() ? (int) $r['views']['v90'] . ' / ' . (int) $r['views']['vyear'] : __('Not selected', 'ai0-content-refresh')) . '</td><td>' . (AI0R_Store::uses_google() ? (int) $g['clicks'] . (' ' . esc_html__('clicks', 'ai0-content-refresh') . '<br>') . (int) $g['impressions'] . (' ' . esc_html__('impressions', 'ai0-content-refresh') . '<br>') . esc_html(number_format_i18n($g['ctr'] * 100, 1)) . ('% CTR<br>' . esc_html__('Position', 'ai0-content-refresh') . ' ') . esc_html(number_format_i18n($g['position'], 1)) : __('Not selected', 'ai0-content-refresh')) . '</td>';
            echo ('<td><strong>' . esc_html__('Score', 'ai0-content-refresh') . ' ') . esc_html((string) $r['score']) . '</strong><p>' . esc_html($r['why']) . '</p>';
            self::form('priority', ['post_id' => $p->ID]);
            echo ('<select aria-label="' . esc_attr__('Priority', 'ai0-content-refresh') . '" name="priority">');
            foreach ([0 => __('Normal', 'ai0-content-refresh'),1 => __('Important', 'ai0-content-refresh'),2 => __('Very important', 'ai0-content-refresh')] as $v => $label) { echo '<option value="' . (int) $v . '" ' . selected((int) get_post_meta($p->ID, '_ai0r_priority', true), $v, false) . '>' . esc_html($label) . '</option>'; }
            echo '</select><label class="ai0r-check"><input name="excluded" type="checkbox" value="1" ' . checked(AI0R_Store::excluded($p->ID), true, false) . ('> ' . esc_html__('Exclude', 'ai0-content-refresh') . '</label><button class="button button-small">' . esc_html__('Save', 'ai0-content-refresh') . '</button></form></td><td>');
            try {
                $cost = AI0R_Cost::forecast(AI0R_Plugin::request_plan($p, $s)['body'], $p->post_content, $s);
                echo isset($cost['usd']) ? esc_html(AI0R_Cost::amount($cost['usd'], $cost['usd_dkk'])) . ('<br><small>' . esc_html__('Conservative scenario:', 'ai0-content-refresh') . ' ') . esc_html(AI0R_Cost::amount($cost['scenario_usd'], $cost['usd_dkk'])) . '</small>' : esc_html(AI0R_Cost::label($cost));
            } catch (Throwable $e) { echo __('Cannot estimate this content', 'ai0-content-refresh'); }
            echo '</td><td>';
            if ($r['blocked']) { echo esc_html($r['blocked']); }
            else { echo ('<p class="ai0r-ready">' . esc_html__('Ready for review', 'ai0-content-refresh') . '</p>'); self::generation_button('generate', ['post_id' => $p->ID]); }
            echo '</td></tr>';
        }
        if (!$rows) { echo ('<tr><td colspan="6">' . esc_html__('No published pages or posts found.', 'ai0-content-refresh') . '</td></tr>'); }
        echo '</tbody></table></div>';
        self::pagination(count($rows), 25, $page, 'queue', ['s' => $search]);
        $stats = (array) get_option('ai0r_google_stats', []);
        echo ('<p class="description">' . esc_html__('Selected traffic source:', 'ai0-content-refresh') . ' ') . esc_html(['both' => __('Search Console + view counter', 'ai0-content-refresh'), 'google' => __('Search Console only', 'ai0-content-refresh'), 'internal' => __('View counter only', 'ai0-content-refresh')][$s['traffic_source']] ?? $s['traffic_source']) . '. ';
        if (AI0R_Store::uses_google()) {
            echo !empty($stats['synced']) ? 'Google-data: ' . esc_html($stats['start'] . ' – ' . $stats['end']) . (__('. Last retrieved', 'ai0-content-refresh') . ' ') . esc_html(wp_date('d.m.Y H:i', $stats['synced'])) . __('. Data older than seven days is excluded from priorities.', 'ai0-content-refresh') : __('No Google data yet. Connect Search Console using the tab above.', 'ai0-content-refresh');
        }
        echo (' ' . esc_html__('Cost forecasts use the selected model, prompt and response limit. Token counts are estimates; actual usage, including reasoning, may differ. The conservative scenario is not a guaranteed maximum. Views are counted from installation, are not unique visitors and may be affected by blockers and bots.', 'ai0-content-refresh') . '</p>');
    }

    private static function row(string $label, string $html, string $help = ''): void {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $html . ($help ? '<p class="description">' . nl2br(esc_html($help)) . '</p>' : '') . '</td></tr>';
    }

    private static function exclusions(): string {
        $selected_ids = wp_parse_id_list(AI0R_Store::settings()['excluded_ids']);
        $posts = get_posts(['post_type' => ['post','page'], 'post_status' => ['publish','draft','pending','future','private'], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $items = [];
        foreach ($posts as $p) {
            if (!current_user_can('edit_post', $p->ID)) { continue; }
            if (get_post_meta($p->ID, '_ai0r_excluded', true)) { $selected_ids[] = $p->ID; }
            $items[$p->ID] = ($p->post_title ?: __('(Untitled)', 'ai0-content-refresh')) . ' · ' . ($p->post_type === 'page' ? __('Page', 'ai0-content-refresh') : __('Blog post', 'ai0-content-refresh')) . ' · ID ' . $p->ID;
        }
        $selected_ids = array_unique($selected_ids);
        foreach ($selected_ids as $id) {
            if (!isset($items[$id])) { $items[$id] = (__('Previously excluded content · ID', 'ai0-content-refresh') . ' ') . $id; }
        }
        $out = ('<details class="ai0r-picker" data-exclusions><summary>' . esc_html__('Select pages and posts', 'ai0-content-refresh') . ' <span data-selection-count>(') . count($selected_ids) . (' ' . esc_html__('selected)', 'ai0-content-refresh') . '</span></summary><div class="ai0r-picker-panel">');
        $out .= ('<label for="ai0r-exclusion-search">' . esc_html__('Search pages and blog posts', 'ai0-content-refresh') . '</label><input id="ai0r-exclusion-search" type="search" placeholder="' . esc_attr__('Search by title or ID …', 'ai0-content-refresh') . '" autocomplete="off" data-exclusion-search>');
        $out .= ('<div class="ai0r-picker-options" role="group" aria-label="' . esc_attr__('Pages and posts to exclude', 'ai0-content-refresh') . '">');
        foreach ($items as $id => $label) {
            $out .= '<label class="ai0r-picker-option" data-exclusion-option><input type="checkbox" name="excluded_ids[]" value="' . (int) $id . '" ' . checked(in_array($id, $selected_ids, true), true, false) . '> <span>' . esc_html($label) . '</span></label>';
        }
        return $out . ('</div><p data-no-matches hidden>' . esc_html__('No pages match your search.', 'ai0-content-refresh') . '</p><button type="button" class="button button-small" data-clear-exclusions>' . esc_html__('Clear all selections', 'ai0-content-refresh') . '</button></div></details><div class="description ai0r-selected-exclusions" data-selected-exclusions aria-live="polite"></div>');
    }

    private static function settings(): void {
        $s = AI0R_Store::settings(); $u = AI0R_Store::usage();
        echo ('<div class="ai0r-panel"><h2>' . esc_html__('API and editorial preferences', 'ai0-content-refresh') . '</h2>');
        self::form('settings', [], 'settings');
        echo '<table class="form-table">';
        self::row(__('OpenAI API key', 'ai0-content-refresh'), ('<input aria-label="' . esc_attr__('OpenAI API key', 'ai0-content-refresh') . '" class="regular-text" type="password" autocomplete="new-password" name="api_key" value="" placeholder="') . esc_attr($s['api_key'] ? __('Key saved — leave blank to keep it', 'ai0-content-refresh') : __('Enter your API key', 'ai0-content-refresh')) . ('"><label class="ai0r-check"><input type="checkbox" name="remove_api_key" value="1"> ' . esc_html__('Remove saved API key', 'ai0-content-refresh') . '</label>'), __('The key is stored encrypted on this WordPress installation and is sent only to OpenAI.', 'ai0-content-refresh'));
        $models = ('<div class="ai0r-recommendation"><strong>' . esc_html__('Recommended: GPT-6.1 Sol', 'ai0-content-refresh') . '</strong><p>' . esc_html__('Suggested for this task: good writing quality with a balance of quality and cost. Start with', 'ai0-content-refresh') . ' <code>') . esc_html(AI0R_Store::RECOMMENDED_MODEL) . ('</code>' . esc_html__(', and review the first proposals.', 'ai0-content-refresh') . '</p><button type="button" class="button" data-recommended-model="') . esc_attr(AI0R_Store::RECOMMENDED_MODEL) . ('">' . esc_html__('Use the recommended model', 'ai0-content-refresh') . '</button> <a href="https://developers.openai.com/api/docs/models" target="_blank" rel="noopener">' . esc_html__('OpenAI Docs ↗', 'ai0-content-refresh') . '</a></div>');
        $available = array_values(array_unique(array_merge(array_keys(AI0R_Cost::catalog()), (array) get_option('ai0r_models', []))));
        sort($available, SORT_NATURAL | SORT_FLAG_CASE);
        $other = !in_array($s['model'], $available, true);
        $models .= ('<select aria-label="' . esc_attr__('Model', 'ai0-content-refresh') . '" name="model_choice" data-model-choice>');
        foreach ($available as $m) { $models .= '<option value="' . esc_attr($m) . '" ' . selected($s['model'], $m, false) . '>' . esc_html($m . ($m === AI0R_Store::RECOMMENDED_MODEL ? ' ' . __('(recommended)', 'ai0-content-refresh') : '')) . '</option>'; }
        $models .= '<option value="__other__" ' . selected($other, true, false) . ('>' . esc_html__('Other model …', 'ai0-content-refresh') . '</option></select><div data-custom-model-wrap') . ($other ? '' : ' hidden') . ('><label for="ai0r-custom-model">' . esc_html__('Model ID (other)', 'ai0-content-refresh') . '</label><br><input id="ai0r-custom-model" class="regular-text" name="custom_model" value="') . esc_attr($other ? $s['model'] : '') . '"></div>';
        self::row(__('Model', 'ai0-content-refresh'), $models, __('Retrieve the list from your API account using the button below. It includes all model names on the account; choose a text model with Responses and Structured Outputs. Choose Other model to enter an ID yourself.', 'ai0-content-refresh'));
        $pricing = '<label><input type="checkbox" name="custom_pricing" value="1" data-custom-pricing ' . checked(isset($s['custom_rates'][$s['model']]), true, false) . ('> ' . esc_html__('Use custom rates for the selected model', 'ai0-content-refresh') . '</label><div class="ai0r-rates" data-price-fields>');
        foreach (['input' => __('Input', 'ai0-content-refresh'), 'output' => __('Output', 'ai0-content-refresh'), 'cached' => __('Cached input', 'ai0-content-refresh'), 'write' => __('Cache writes', 'ai0-content-refresh')] as $type => $label) {
            $pricing .= '<label>' . esc_html($label) . ' <input aria-label="' . esc_attr($label . (' ' . __('USD per million tokens', 'ai0-content-refresh'))) . '" name="rate_' . $type . '" type="number" min="0" max="100000" step="any" value="' . esc_attr((string) ($s['custom_rates'][$s['model']][$type] ?? '')) . '"></label> ';
        }
        $pricing .= '</div><p data-rate-description aria-live="polite"></p><script type="application/json" id="ai0r-prices">' . wp_json_encode(['catalog' => AI0R_Cost::catalog(), 'custom' => $s['custom_rates']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
        self::row(__('Model rates · USD per 1M tokens', 'ai0-content-refresh'), $pricing, __('Built-in standard rates are dated 25 October 2026. Other models need custom rates to show a cost estimate. Custom rates are used without automatic long-context surcharges.', 'ai0-content-refresh'));
        self::row(__('Currency conversion', 'ai0-content-refresh'), ('<strong>' . esc_html__('Automatic USD → DKK', 'ai0-content-refresh') . '</strong><p>') . esc_html(AI0R_Exchange::description(AI0R_Exchange::current())) . ('</p><a href="https://frankfurter.dev/" target="_blank" rel="noopener">' . esc_html__('Exchange rate source ↗', 'ai0-content-refresh') . '</a>'), __('The latest reference rate is retrieved automatically and cached locally for up to one day. No extra API key is needed. Weekends and holidays use the last published rate. Estimates exclude VAT, fees and negotiated discounts.', 'ai0-content-refresh'));
        self::row(__('Prompt', 'ai0-content-refresh'), ('<textarea aria-label="' . esc_attr__('Prompt', 'ai0-content-refresh') . '" name="prompt" rows="9" class="large-text">') . esc_textarea($s['prompt']) . '</textarea>', __('Customize the prompt. The plugin\'s preservation rules and six-month cooldown cannot be disabled by the prompt.', 'ai0-content-refresh'));
        self::row(__('New content elements', 'ai0-content-refresh'), '<label><input type="checkbox" name="additions" value="1" ' . checked((bool) $s['additions'], true, false) . ('> ' . esc_html__('Allow summaries, FAQs, checklists, tables, callouts and relevant links', 'ai0-content-refresh') . '</label>'), __('At most four new elements per page in total. Existing media and custom blocks are preserved.', 'ai0-content-refresh'));
        self::row(__('Media library images', 'ai0-content-refresh'), '<label><input type="checkbox" name="media_library" value="1" ' . checked((bool) $s['media_library'], true, false) . ('> ' . esc_html__('Allow searching and reusing existing images', 'ai0-content-refresh') . '</label>'), __('Uses WordPress to search titles, filenames, alt text and captions based on the page topics. The model receives metadata for up to 12 relevant images and can insert at most two. Image files are not sent to OpenAI. Existing images and metadata are preserved. Images attached to private or unpublished pages are excluded.', 'ai0-content-refresh'));
        $types = '';
        foreach (['page' => __('Pages', 'ai0-content-refresh'),'post' => __('Blog posts', 'ai0-content-refresh')] as $k => $label) { $types .= '<label class="ai0r-check"><input name="post_types[]" type="checkbox" value="' . esc_attr($k) . '" ' . checked(in_array($k, $s['post_types'], true), true, false) . '> ' . esc_html($label) . '</label>'; }
        self::row(__('Content types', 'ai0-content-refresh'), $types);
        self::row(__('Exclude pages and posts', 'ai0-content-refresh'), self::exclusions(), __('Open the list, search and select multiple pages.
Selections are shared with Content & priorities and the editor.
Save settings to apply them.
The privacy policy page, password-protected content and Yoast noindex content are automatically excluded.', 'ai0-content-refresh'));
        $sources = ('<select aria-label="' . esc_attr__('Traffic source for priorities', 'ai0-content-refresh') . '" name="traffic_source">');
        foreach (['both' => __('Search Console + built-in view counter', 'ai0-content-refresh'), 'google' => __('Google Search Console only', 'ai0-content-refresh'), 'internal' => __('Built-in view counter only', 'ai0-content-refresh')] as $value => $label) { $sources .= '<option value="' . esc_attr($value) . '" ' . selected($s['traffic_source'], $value, false) . '>' . esc_html($label) . '</option>'; }
        self::row(__('Traffic source for priorities', 'ai0-content-refresh'), $sources . '</select>', __('This determines which traffic data affects the order. Search Console only stops internal view counting. Built-in view counter only stops automatic Google syncing. Your manual priorities are always used.', 'ai0-content-refresh'));
        self::row(__('Page views', 'ai0-content-refresh'), '<label><input type="checkbox" name="tracking" value="1" ' . checked((bool) $s['tracking'], true, false) . ('> ' . esc_html__('Enable the built-in view counter', 'ai0-content-refresh') . '</label>'), __('All logged-in users are excluded, including on cached pages. Data older than one calendar year is deleted on upgrade and then daily through WP-Cron, even when automation is disabled. Only the date, content ID and view count are stored. No tracking cookies or visitor profiles are created. A short-lived abuse limit uses a one-way value derived from the IP address for up to two minutes. Respects DNT/GPC. Clear your page cache after activation or changes.', 'ai0-content-refresh'));
        echo ('</table><h2>' . esc_html__('Automation and usage limits', 'ai0-content-refresh') . '</h2><table class="form-table">');
        self::row(__('Scheduled review', 'ai0-content-refresh'), '<label><input name="enabled" type="checkbox" value="1" ' . checked((bool) $s['enabled'], true, false) . ('> ' . esc_html__('Enable scheduled reviews', 'ai0-content-refresh') . '</label>'));
        $publishing = '<fieldset class="ai0r-publishing"><legend class="screen-reader-text">' . esc_html__('Publishing', 'ai0-content-refresh') . '</legend>';
        $publishing .= '<label class="ai0r-mode-choice"><input type="radio" name="mode" value="review" ' . checked($s['mode'], 'review', false) . '><strong>' . esc_html__('Save proposals for review', 'ai0-content-refresh') . '</strong><span class="description">' . esc_html__('New manual and scheduled runs wait for your approval. Open the history and click Publish this proposal; there is no later automatic approval.', 'ai0-content-refresh') . '</span></label>';
        $publishing .= '<label class="ai0r-mode-choice"><input type="radio" name="mode" value="auto" ' . checked($s['mode'], 'auto', false) . '><strong>' . esc_html__('Publish automatically after technical validation', 'ai0-content-refresh') . '</strong><span class="description">' . esc_html__('New manual and scheduled runs publish changes to the live page immediately after validation. No approval click is needed. Enable scheduled reviews above for unattended runs.', 'ai0-content-refresh') . '</span></label></fieldset>';
        self::row(__('When a proposal is ready', 'ai0-content-refresh'), $publishing, __('Save settings to apply this choice to future runs. Earlier pending proposals still require approval. Technical validation checks format and preservation; it does not guarantee factual accuracy or better Google rankings.', 'ai0-content-refresh'));
        foreach (['monthly_limit' => [__('Max. generations per month', 'ai0-content-refresh'),1,500], 'monthly_tokens' => [__('Max. tokens per month', 'ai0-content-refresh'),10000,10000000], 'max_output' => [__('Max. response tokens per run', 'ai0-content-refresh'),1000,32000], 'start_day' => [__('Start day of the month', 'ai0-content-refresh'),1,28], 'hour' => [__('Start time (hour)', 'ai0-content-refresh'),0,23]] as $k => $f) {
            self::row($f[0], '<input aria-label="' . esc_attr($f[0]) . '" type="number" name="' . esc_attr($k) . '" min="' . $f[1] . '" max="' . $f[2] . '" value="' . (int) $s[$k] . '">');
        }
        self::row(__('Usage this month', 'ai0-content-refresh'), esc_html(number_format_i18n((int) $u['tokens'])) . (' ' . __('tokens / reservations', 'ai0-content-refresh')), __('Manual attempts, rejected proposals and runs without changes also count. A token limit is not an exact money budget. A conservative reservation is retained when API usage is unknown.', 'ai0-content-refresh'));
        self::row(__('Time zone', 'ai0-content-refresh'), esc_html(wp_timezone_string()), __('WP-Cron depends on traffic. Use your hosting provider\'s cron to run wp-cron.php for a more reliable schedule.', 'ai0-content-refresh'));
        echo ('</table><h2>' . esc_html__('Google — one-time setup', 'ai0-content-refresh') . '</h2><p>' . esc_html__('Once the client is configured, connect with Google login on the Google Search Console tab.', 'ai0-content-refresh') . '</p><table class="form-table">');
        self::row(__('Google Client ID', 'ai0-content-refresh'), ('<input aria-label="' . esc_attr__('Google Client ID', 'ai0-content-refresh') . '" class="large-text" name="google_client_id" value="') . esc_attr($s['google_client_id']) . '">');
        self::row(__('Google Client Secret', 'ai0-content-refresh'), ('<input aria-label="' . esc_attr__('Google Client Secret', 'ai0-content-refresh') . '" class="regular-text" type="password" autocomplete="new-password" name="google_secret" value="" placeholder="') . esc_attr($s['google_secret'] ? __('Saved — leave blank to keep the value', 'ai0-content-refresh') : __('Enter Client Secret', 'ai0-content-refresh')) . '">', __('Changing the client requires a new Google login.', 'ai0-content-refresh'));
        self::row(__('Authorized redirect URI', 'ai0-content-refresh'), '<code>' . esc_html(AI0R_Google::redirect_uri()) . '</code>', __('Copy this exact address to the OAuth client in Google Cloud.', 'ai0-content-refresh'));
        echo ('</table><p><button class="button button-primary">' . esc_html__('Save settings', 'ai0-content-refresh') . '</button></p></form>');
        self::button('models', __('Test key and retrieve models', 'ai0-content-refresh'), [], 'settings');
        echo ('<p class="description">' . esc_html__('Save changed keys before testing. The test retrieves model names; it does not generate content.', 'ai0-content-refresh') . '</p></div>');
    }

    private static function google(): void {
        $s = AI0R_Store::settings();
        echo ('<div class="ai0r-panel"><h2>' . esc_html__('Google Search Console', 'ai0-content-refresh') . '</h2><p>' . esc_html__('Use search clicks, impressions, click-through rate and position to prioritize existing pages. The plugin requests read-only access.', 'ai0-content-refresh') . '</p>');
        if (!AI0R_Store::uses_google()) { echo ('<p class="ai0r-warning">' . esc_html__('Search Console is not selected as a traffic source. Automatic syncing is disabled and Google data is excluded from priorities. You can still retrieve data manually here.', 'ai0-content-refresh') . '</p>'); }
        if (!AI0R_Google::connected()) {
            self::button('connect', __('Connect with Google', 'ai0-content-refresh'), [], 'google', true);
        } else {
            echo ('<p class="ai0r-ready">' . esc_html__('Google is connected', 'ai0-content-refresh') . '</p>');
            self::form('property', [], 'google');
            echo ('<label for="ai0r-property">' . esc_html__('Property', 'ai0-content-refresh') . ' </label><select id="ai0r-property" name="property"><option value="">' . esc_html__('Select a property', 'ai0-content-refresh') . '</option>');
            foreach (AI0R_Google::sites() as $site) { echo '<option value="' . esc_attr($site['siteUrl']) . '" ' . selected($s['property'], $site['siteUrl'], false) . '>' . esc_html($site['siteUrl']) . '</option>'; }
            echo ('</select> <button class="button button-primary">' . esc_html__('Select and retrieve data', 'ai0-content-refresh') . '</button></form><p></p>');
            self::button('sync', __('Refresh search data now', 'ai0-content-refresh'), [], 'google');
            self::button('disconnect', __('Disconnect Google', 'ai0-content-refresh'), [], 'google');
        }
        $stats = get_option('ai0r_google_stats');
        if ($stats) { echo ('<p>' . esc_html__('Last synced:', 'ai0-content-refresh') . ' ') . esc_html(wp_date('d.m.Y H:i', $stats['synced'])) . (__('. Period:', 'ai0-content-refresh') . ' ') . esc_html($stats['start'] . ' – ' . $stats['end']) . '.</p>'; }
        if (get_option('ai0r_google_error')) { echo '<p class="ai0r-warning">' . esc_html(get_option('ai0r_google_error')) . '</p>'; }
        echo ('<p>' . esc_html__('Data is retrieved daily and compares two 28-day periods. The latest three days are excluded to avoid preliminary figures. Missing rows do not necessarily mean zero traffic; Search Console may omit data. Search queries and visitor profiles are not sent to OpenAI.', 'ai0-content-refresh') . '</p>');
        echo ('<details><summary>' . esc_html__('Initial Google login setup', 'ai0-content-refresh') . '</summary><ol><li>' . esc_html__('Create or select your project in', 'ai0-content-refresh') . ' <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">' . esc_html__('Google Cloud', 'ai0-content-refresh') . '</a>.</li><li>' . esc_html__('Enable the Google Search Console API.', 'ai0-content-refresh') . '</li><li>' . esc_html__('Configure OAuth consent with read-only access', 'ai0-content-refresh') . ' <code>https://www.googleapis.com/auth/webmasters.readonly</code>.</li><li>' . esc_html__('Create a Web application OAuth client.', 'ai0-content-refresh') . '</li><li>' . esc_html__('Add this redirect URI:', 'ai0-content-refresh') . ' <code>') . esc_html(AI0R_Google::redirect_uri()) . ('</code>.</li><li>' . esc_html__('Save the Client ID and Client Secret in the plugin Settings. Return here and connect Google.', 'ai0-content-refresh') . '</li></ol><p>' . esc_html__('The Google Account must have access to your Search Console property. An external OAuth app in testing mode may receive short-lived refresh tokens and require frequent logins. For ongoing use, check the app\'s publishing status and any Google verification requirements.', 'ai0-content-refresh') . '</p></details></div>');
    }

    private static function statuses(): array {
        return ['running' => __('Running', 'ai0-content-refresh'), 'pending' => __('Pending review', 'ai0-content-refresh'), 'applied' => __('Published', 'ai0-content-refresh'), 'rolled_back' => __('Restored', 'ai0-content-refresh'), 'rejected' => __('Rejected', 'ai0-content-refresh'), 'stale' => __('Outdated proposal', 'ai0-content-refresh'), 'no_change' => __('No changes suggested', 'ai0-content-refresh'), 'error' => __('Error', 'ai0-content-refresh')];
    }

    private static function post_links(int $post_id): string {
        $p = get_post($post_id);
        if (!$p) { return ''; }
        $links = [];
        if ($p->post_status === 'publish') {
            $links[] = '<a href="' . esc_url(get_permalink($p)) . ('" target="_blank" rel="noopener">' . esc_html__('View page ↗', 'ai0-content-refresh') . '</a>');
        }
        if (current_user_can('edit_post', $post_id)) {
            $edit = get_edit_post_link($post_id, 'raw');
            if ($edit) { $links[] = '<a href="' . esc_url($edit) . ('" target="_blank" rel="noopener">' . esc_html__('Edit in WordPress ↗', 'ai0-content-refresh') . '</a>'); }
        }
        return $links ? '<p class="ai0r-post-links">' . implode(' · ', $links) . '</p>' : '';
    }

    private static function history(): void {
        global $wpdb;
        $id = absint($_GET['id'] ?? 0);
        if ($id) {
            $h = AI0R_Store::history($id);
            if (!$h) { echo ('<p>' . esc_html__('History entry not found.', 'ai0-content-refresh') . '</p>'); return; }
            echo '<div class="ai0r-panel"><h2>' . esc_html(get_the_title($h->post_id) ?: __('Content deleted', 'ai0-content-refresh')) . '</h2><p>' . esc_html(self::statuses()[$h->status] ?? $h->status) . ' · ' . esc_html($h->model) . ' · ' . esc_html(get_date_from_gmt($h->created_at, 'd.m.Y H:i')) . '</p><p>' . nl2br(esc_html($h->summary)) . '</p><p>' . (int) $h->input_tokens . (' ' . __('input tokens +', 'ai0-content-refresh') . ' ') . (int) $h->output_tokens . (' ' . esc_html__('output tokens (or reserved tokens when usage is unknown).', 'ai0-content-refresh') . '</p>');
            echo self::post_links((int) $h->post_id);
            $details = json_decode($h->details ?? '', true);
            if ($h->status === 'pending') {
                echo '<p class="ai0r-warning">' . esc_html__('This proposal is waiting for your approval. It will not be published automatically. Click Publish this proposal to apply it. Changing the publishing mode affects future runs only.', 'ai0-content-refresh') . '</p>';
                if (!empty($details['publication_paused'])) {
                    echo '<p class="description">' . esc_html__('Automatic publishing was switched off while this run was in progress. The proposal has been retained for review.', 'ai0-content-refresh') . '</p>';
                }
                if (!empty($details['publication_error'])) {
                    echo '<p class="ai0r-warning"><strong>' . esc_html__('Automatic publishing did not complete:', 'ai0-content-refresh') . '</strong> ' . esc_html($details['publication_error']) . '</p>';
                }
            }
            echo ('<p><strong>' . esc_html__('Estimated cost:', 'ai0-content-refresh') . ' ') . esc_html(AI0R_Cost::label($details['cost'] ?? null)) . ('</strong></p><p class="description">' . esc_html__('Rates and the exchange rate are saved with each run. Excludes VAT, fees and negotiated discounts. API usage may be unknown on failure.', 'ai0-content-refresh') . '</p>');
            if (!empty($details['cost']['rates']['source'])) { echo '<p class="description">' . esc_html($details['cost']['rates']['source']) . '</p>'; }
            if (array_key_exists('exchange', $details['cost'] ?? [])) { echo '<p class="description">' . esc_html(AI0R_Exchange::description($details['cost']['exchange'])) . '</p>'; }
            elseif (!empty($details['cost']['usd_dkk'])) { echo ('<p class="description">' . esc_html__('Previous manual exchange rate:', 'ai0-content-refresh') . ' ') . esc_html((string) $details['cost']['usd_dkk']) . ' DKK/USD</p>'; }
            if (is_array($details) && isset($details['text_changes'], $details['new_sections'])) {
                echo '<p class="ai0r-change-count"><strong>' . (int) $details['text_changes'] . (' ' . __('text changes ·', 'ai0-content-refresh') . ' ') . (int) $details['new_sections'] . (' ' . __('new elements ·', 'ai0-content-refresh') . ' ') . (int) ($details['new_images'] ?? 0) . (($details['new_images'] ?? 0) === 1 ? (' ' . __('new image', 'ai0-content-refresh')) : (' ' . __('new images', 'ai0-content-refresh'))) . '</strong></p>';
                if (!empty($details['findings'])) {
                    echo ('<h3>' . esc_html__('Model assessment', 'ai0-content-refresh') . '</h3><ul>');
                    foreach ($details['findings'] as $finding) { echo '<li>' . esc_html($finding) . '</li>'; }
                    echo '</ul>';
                }
            }
            if (!empty($details['media_search']['enabled'])) {
                echo ('<p class="description">' . esc_html__('Media library: searched for', 'ai0-content-refresh') . ' ') . esc_html(implode(', ', $details['media_search']['terms'])) . ' · ' . (int) $details['media_search']['candidates'] . (' ' . esc_html__('eligible candidates.', 'ai0-content-refresh') . '</p>');
            }
            if (!empty($details['images'])) {
                echo ('<h3>' . esc_html__('Images in the proposal', 'ai0-content-refresh') . '</h3><ul class="ai0r-media-choices">');
                foreach ($details['images'] as $image) {
                    echo '<li>' . (get_post((int) $image['id']) ? wp_get_attachment_image((int) $image['id'], 'thumbnail') : '') . '<div><strong>' . esc_html($image['title']) . ' · ID ' . (int) $image['id'] . '</strong><p>' . esc_html($image['reason']) . ('</p><p class="description">' . esc_html__('Alt text:', 'ai0-content-refresh') . ' ') . esc_html($image['alt'] ?: __('(empty in the media library)', 'ai0-content-refresh')) . '</p></div></li>';
                }
                echo '</ul>';
            }
            if ($h->status === 'no_change') {
                echo ('<p class="ai0r-warning"><strong>' . esc_html__('No changes were proposed or published.', 'ai0-content-refresh') . '</strong> ' . esc_html__('Before and after are identical.', 'ai0-content-refresh')) . (!empty($details['no_change_reason']) ? ('<br>' . esc_html__('Model explanation:', 'ai0-content-refresh') . ' ') . esc_html($details['no_change_reason']) : '') . '</p>';
                $post = get_post($h->post_id);
                if ($post && AI0R_Plugin::reason($post) === '') {
                    self::generation_button('generate', ['post_id' => $h->post_id], 'history', false, true);
                    echo ('<p class="description">' . esc_html__('Change the model or prompt and try again. The run counts towards monthly usage; the six-month cooldown still applies.', 'ai0-content-refresh') . '</p>');
                }
            }
            if ($h->status === 'pending') { self::button('apply', __('Publish this proposal', 'ai0-content-refresh'), ['id' => $id], 'history', true); self::button('reject', __('Reject proposal', 'ai0-content-refresh'), ['id' => $id], 'history'); }
            if ($h->status === 'applied') { self::button('restore', __('Restore content from before this update', 'ai0-content-refresh'), ['id' => $id], 'history'); }
            echo ('<details><summary>' . esc_html__('Prompt used for this run', 'ai0-content-refresh') . '</summary><pre>') . esc_html($h->prompt) . '</pre></details>';
            if ($h->after_content && $h->after_content !== $h->before_content) {
                echo ('<h3>' . esc_html__('Content changes', 'ai0-content-refresh') . '</h3>');
                require_once ABSPATH . 'wp-admin/includes/revision.php';
                $diff = wp_text_diff($h->before_content, $h->after_content, ['title_left' => __('Before', 'ai0-content-refresh'), 'title_right' => __('Proposal', 'ai0-content-refresh'), 'show_split_view' => true]);
                echo $diff ?: ('<p>' . esc_html__('No content changes.', 'ai0-content-refresh') . '</p>'); // wp_text_diff escapes source lines.
                echo ('<details><summary>' . esc_html__('Content preview (without the theme header/footer)', 'ai0-content-refresh') . '</summary>');
                $preview = preg_replace_callback('/<!-- wp:ai0-refresh\/section (\{.*?\}) \/-->/s', static function ($m) { return AI0R_Content::render((array) json_decode($m[1], true)); }, $h->after_content);
                $html = '<!doctype html><html lang="' . esc_attr(get_bloginfo('language')) . '"><meta charset="utf-8"><style>body{font:17px/1.65 system-ui;padding:24px;max-width:900px;margin:auto}img,video{max-width:100%;height:auto}.ai0r-section{border:1px solid #dbe4ec;padding:20px;margin:24px 0;border-radius:12px}table{border-collapse:collapse;width:100%}td,th{padding:10px;border:1px solid #ddd}iframe{max-width:100%}</style><body>' . wp_kses_post($preview) . '</body></html>';
                echo ('<iframe title="' . esc_attr__('Content preview', 'ai0-content-refresh') . '" class="ai0r-preview" sandbox="" referrerpolicy="no-referrer" srcdoc="') . esc_attr($html) . '"></iframe></details>';
                echo ('<details><summary>' . esc_html__('Complete content before and after', 'ai0-content-refresh') . '</summary><h3>' . esc_html__('Before', 'ai0-content-refresh') . '</h3><pre>') . esc_html($h->before_content) . ('</pre><h3>' . esc_html__('After', 'ai0-content-refresh') . '</h3><pre>') . esc_html($h->after_content) . '</pre></details>';
            }
            echo '</div>'; return;
        }
        $t = AI0R_Store::table('history');
        $page = max(1, absint($_GET['paged'] ?? 1));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,post_id,created_at,status,model,summary,input_tokens,output_tokens,details FROM $t ORDER BY id DESC LIMIT 20 OFFSET %d", ($page - 1) * 20));
        echo ('<div class="ai0r-panel"><h2>' . esc_html__('Generation history', 'ai0-content-refresh') . '</h2><p>' . esc_html__('All attempts are saved, including errors and reviews without changes. Pending review means you must open the proposal and click Publish this proposal. These entries are not approved automatically later.', 'ai0-content-refresh') . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__('Date', 'ai0-content-refresh') . '</th><th>' . esc_html__('Content', 'ai0-content-refresh') . '</th><th>' . esc_html__('Status', 'ai0-content-refresh') . '</th><th>' . esc_html__('Model / tokens', 'ai0-content-refresh') . '</th><th>' . esc_html__('Estimated cost', 'ai0-content-refresh') . '</th></tr></thead><tbody>');
        foreach ($rows as $h) {
            echo '<tr><td>' . esc_html(get_date_from_gmt($h->created_at, 'd.m.Y H:i')) . '</td><td><a href="' . esc_url(add_query_arg(['page' => 'ai0-refresh', 'tab' => 'history', 'id' => $h->id], admin_url('admin.php'))) . '">' . esc_html(get_the_title($h->post_id) ?: __('(Content deleted)', 'ai0-content-refresh')) . '</a>' . self::post_links((int) $h->post_id) . '<p>' . esc_html(mb_substr($h->summary, 0, 180)) . '</p></td><td>' . esc_html(self::statuses()[$h->status] ?? $h->status) . '</td><td>' . esc_html($h->model) . '<br>' . ((int) $h->input_tokens + (int) $h->output_tokens) . '</td><td>' . esc_html(AI0R_Cost::label(json_decode($h->details ?? '', true)['cost'] ?? null)) . '</td></tr>';
        }
        if (!$rows) { echo ('<tr><td colspan="5">' . esc_html__('No runs yet.', 'ai0-content-refresh') . '</td></tr>'); }
        echo '</tbody></table>';
        self::pagination((int) $wpdb->get_var("SELECT COUNT(*) FROM $t"), 20, $page, 'history');
        echo '</div>';
    }

    private static function pagination(int $count, int $size, int $page, string $tab, array $extra = []): void {
        if ($count <= $size) { return; }
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo wp_kses_post(paginate_links(['base' => add_query_arg(array_merge(['page' => 'ai0-refresh', 'tab' => $tab, 'paged' => '%#%'], $extra), admin_url('admin.php')), 'format' => '', 'current' => $page, 'total' => (int) ceil($count / $size)]));
        echo '</div></div>';
    }
}
