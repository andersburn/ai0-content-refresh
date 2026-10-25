<?php
defined('ABSPATH') || exit;

final class AI0R_Admin {
    public static function boot(): void {
        add_action('admin_menu', static function () { add_menu_page('Ai0 Content Refresh', 'Content Refresh', 'manage_options', 'ai0-refresh', [self::class, 'page'], 'dashicons-update-alt', 59); });
        add_action('admin_enqueue_scripts', static function ($hook) {
            if ($hook === 'toplevel_page_ai0-refresh') {
                wp_enqueue_style('ai0r-admin', plugins_url('assets/admin.css', AI0R_FILE), [], AI0R_VERSION);
                wp_enqueue_script('ai0r-admin', plugins_url('assets/admin.js', AI0R_FILE), [], AI0R_VERSION, true);
            }
        });
        add_action('admin_post_ai0r_action', [self::class, 'action']);
        add_action('admin_post_ai0r_oauth_callback', static function () {
            if (!current_user_can('manage_options')) { wp_die('Adgang nægtet.', '', ['response' => 403]); }
            try { AI0R_Google::callback(); self::notice('Google er forbundet. Vælg website nedenfor.'); }
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

    public static function action(): void {
        if (!current_user_can('manage_options') || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('Adgang nægtet.', '', ['response' => 403]); }
        check_admin_referer('ai0r_admin');
        $do = sanitize_key($_POST['do'] ?? '');
        $tab = sanitize_key($_POST['tab'] ?? 'queue');
        $detail = 0;
        try {
            switch ($do) {
                case 'settings': self::save_settings(); self::notice('Indstillingerne er gemt.'); break;
                case 'models':
                    $models = AI0R_Plugin::models();
                    self::notice('API-nøglen virker. Der blev hentet ' . count($models) . ' modelnavne. Vælg en tekstmodel, der understøtter Responses og Structured Outputs.'); break;
                case 'connect': AI0R_Google::start(); break;
                case 'disconnect': AI0R_Google::disconnect(); self::notice('Google-forbindelsen er fjernet lokalt. Du kan også tilbagekalde adgangen i din Google-konto.'); break;
                case 'property':
                    $property = sanitize_text_field(wp_unslash($_POST['property'] ?? ''));
                    if (!in_array($property, array_column(AI0R_Google::sites(true), 'siteUrl'), true)) { throw new RuntimeException('Du har ikke adgang til det valgte website.'); }
                    $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
                    $matches = str_starts_with($property, 'sc-domain:') ? ($host === substr($property, 10) || str_ends_with($host, '.' . substr($property, 10))) : strtolower((string) wp_parse_url($property, PHP_URL_HOST)) === $host;
                    if (!$matches) { throw new RuntimeException('Vælg et Search Console-website, der dækker dette WordPress-domæne.'); }
                    $s = AI0R_Store::settings(); $s['property'] = $property;
                    update_option('ai0r_settings', $s, false);
                    delete_option('ai0r_google_stats');
                    AI0R_Google::sync(); self::notice('Website er valgt, og søgedata er hentet.'); break;
                case 'sync': AI0R_Google::sync(); self::notice('Søgedata er opdateret.'); break;
                case 'generate':
                    $post_id = absint($_POST['post_id'] ?? 0);
                    if (!current_user_can('edit_post', $post_id)) { throw new RuntimeException('Du må ikke redigere dette indhold.'); }
                    $detail = AI0R_Plugin::generate($post_id, false); $tab = 'history';
                    $h = AI0R_Store::history($detail);
                    self::notice($h && $h->status === 'no_change' ? 'Modellen foreslog ingen ændringer. Siden er uændret; se begrundelsen i historikken.' : 'Kørslen er gemt i historikken. Et forslag ændrer ikke den udgivne side.'); break;
                case 'next':
                    foreach (AI0R_Plugin::queue() as $row) {
                        if (!$row['blocked']) { $detail = AI0R_Plugin::generate($row['post']->ID, false); break; }
                    }
                    if (!$detail) { throw new RuntimeException('Der er ingen sider, som må opdateres lige nu.'); }
                    $tab = 'history'; self::notice('Næste side er gennemgået.'); break;
                case 'apply':
                case 'restore':
                    $detail = absint($_POST['id'] ?? 0);
                    AI0R_Plugin::apply($detail, $do === 'restore');
                    self::notice($do === 'restore' ? 'Indholdet er gendannet. Seks måneders grænsen gælder fortsat.' : 'Forslaget er publiceret.'); break;
                case 'reject':
                    $detail = absint($_POST['id'] ?? 0); $h = AI0R_Store::history($detail);
                    if (!$h || $h->status !== 'pending' || !current_user_can('edit_post', (int) $h->post_id)) { throw new RuntimeException('Forslaget kan ikke afvises.'); }
                    AI0R_Store::update($detail, ['status' => 'rejected']);
                    update_post_meta($h->post_id, '_ai0r_retry_after', time() + 30 * DAY_IN_SECONDS);
                    self::notice('Forslaget er afvist.'); break;
                case 'priority':
                    $post_id = absint($_POST['post_id'] ?? 0);
                    if (!current_user_can('edit_post', $post_id)) { throw new RuntimeException('Adgang nægtet.'); }
                    update_post_meta($post_id, '_ai0r_priority', max(0, min(2, (int) ($_POST['priority'] ?? 0))));
                    AI0R_Store::set_excluded($post_id, !empty($_POST['excluded']));
                    self::notice('Prioriteringen er gemt.'); break;
                default: throw new RuntimeException('Ukendt handling.');
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
            if (strlen($new_key) > 512 || preg_match('/\s/', $new_key)) { throw new RuntimeException('API-nøglen har et ugyldigt format.'); }
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
        if ($s['model'] === '') { throw new RuntimeException('Vælg en model, eller udfyld feltet Anden model.'); }
        if (strlen($s['model']) > 160 || preg_match('/[^a-zA-Z0-9._:\-]/', $s['model'])) { throw new RuntimeException('Modelnavnet har et ugyldigt format.'); }
        $s['traffic_source'] = in_array($_POST['traffic_source'] ?? '', ['google','internal','both'], true) ? $_POST['traffic_source'] : $s['traffic_source'];
        unset($s['usd_dkk']); // Old manual setting is no longer used; historical snapshots remain intact.
        if (!empty($_POST['custom_pricing'])) {
            $rates = [];
            foreach (['input','output','cached','write'] as $type) {
                $value = str_replace(',', '.', (string) ($_POST['rate_' . $type] ?? ''));
                if (!is_numeric($value) || (float) $value < 0 || (float) $value > 100000) { throw new RuntimeException('Udfyld alle fire takster med et tal fra 0 til 100.000 USD pr. million tokens.'); }
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
        if (!$s['post_types']) { throw new RuntimeException('Vælg mindst én indholdstype.'); }
        $s['excluded_ids'] = implode(',', wp_parse_id_list(wp_unslash($_POST['excluded_ids'] ?? '')));
        $s['tracking'] = !empty($_POST['tracking']) ? 1 : 0;
        $s['additions'] = !empty($_POST['additions']) ? 1 : 0;
        $s['prompt'] = sanitize_textarea_field(wp_unslash($_POST['prompt'] ?? ''));
        if (mb_strlen($s['prompt']) > 12000) { throw new RuntimeException('Prompten må højst fylde 12.000 tegn.'); }
        update_option('ai0r_settings', $s, false);
    }

    public static function metabox(WP_Post $p): void {
        wp_nonce_field('ai0r_priority_' . $p->ID, 'ai0r_meta_nonce');
        echo '<p>Prioritet til næste indholdsgennemgang:</p><select name="ai0r_priority">';
        foreach ([0 => 'Normal', 1 => 'Vigtig', 2 => 'Meget vigtig'] as $value => $label) {
            echo '<option value="' . esc_attr((string) $value) . '" ' . selected((int) get_post_meta($p->ID, '_ai0r_priority', true), $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select><p><label><input type="checkbox" name="ai0r_excluded" value="1" ' . checked(AI0R_Store::excluded($p->ID), true, false) . '> Udeluk denne side</label></p>';
        if ($p->post_status === 'publish') { echo '<p>Tidligst næste opdatering: <strong>' . esc_html(wp_date('d.m.Y', AI0R_Plugin::next_allowed($p)->getTimestamp())) . '</strong></p>'; }
    }

    public static function save_meta(int $id, WP_Post $p): void {
        if (!isset($_POST['ai0r_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ai0r_meta_nonce'])), 'ai0r_priority_' . $id) || !current_user_can('edit_post', $id) || wp_is_post_revision($id) || wp_is_post_autosave($id) || !in_array($p->post_type, ['post','page'], true)) { return; }
        update_post_meta($id, '_ai0r_priority', max(0, min(2, (int) ($_POST['ai0r_priority'] ?? 0))));
        AI0R_Store::set_excluded($id, !empty($_POST['ai0r_excluded']));
    }

    public static function page(): void {
        if (!current_user_can('manage_options')) { return; }
        $tab = sanitize_key($_GET['tab'] ?? 'queue');
        echo '<div class="wrap ai0r-admin"><h1>Ai0 Content Refresh</h1><p class="ai0r-lead">Giv dit eksisterende indhold en ny gennemgang. Bevar medier og layout, og følg alle ændringer.</p>';
        $notice = get_transient('ai0r_notice_' . get_current_user_id());
        delete_transient('ai0r_notice_' . get_current_user_id());
        if ($notice) { echo '<div class="notice ' . ($notice['error'] ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($notice['text']) . '</p></div>'; }
        if (get_option('ai0r_install_error')) { echo '<div class="notice notice-error"><p>' . esc_html(get_option('ai0r_install_error')) . '</p></div>'; }
        echo '<nav class="nav-tab-wrapper">';
        foreach (['queue' => 'Indhold & prioritering', 'history' => 'Genereringshistorik', 'settings' => 'Indstillinger', 'google' => 'Google Search Console'] as $key => $label) {
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
        foreach (['Indhold i oversigten' => count($rows), 'Klar til gennemgang' => $eligible, 'Genereringer denne måned' => (int) $usage['attempts'] . ' / ' . $s['monthly_limit'], 'Automatik' => $s['enabled'] ? ($s['mode'] === 'auto' ? 'Publicerer automatisk' : 'Gemmer forslag') : 'Slået fra'] as $label => $value) {
            echo '<div class="ai0r-card"><span>' . esc_html($label) . '</span><strong>' . esc_html((string) $value) . '</strong></div>';
        }
        echo '</div><p class="description">' . esc_html(AI0R_Exchange::description(AI0R_Exchange::current())) . '</p><p>Prioriteringen bruger dine markeringer, Yoast-hjørnestensindhold, den valgte trafikkilde og indholdets alder. Seks måneders pause regnes fra seneste udgivelse, manuel indholdsændring eller pluginopdatering.</p>';
        foreach (['ai0r_worker_error', 'ai0r_google_error'] as $option) { if (get_option($option)) { echo '<p class="ai0r-warning">' . esc_html(get_option($option)) . '</p>'; } }
        self::button('next', 'Generér forslag til næste side', [], 'queue', true);
        echo '<p class="description">Manuelle genereringer gemmer altid et forslag. De tæller med i månedens loft. Automatikken arbejder højst på én side i timen fra den valgte månedsdag.</p>';
        $search = sanitize_text_field(wp_unslash($_GET['s'] ?? ''));
        echo '<form method="get" class="ai0r-search"><input type="hidden" name="page" value="ai0-refresh"><label for="ai0r-search">Find indhold </label><input id="ai0r-search" type="search" name="s" value="' . esc_attr($search) . '"><button class="button">Søg</button></form>';
        if ($search) { $rows = array_values(array_filter($rows, static fn($r) => mb_stripos($r['post']->post_title, $search) !== false)); }
        $page = max(1, absint($_GET['paged'] ?? 1));
        echo '<div class="ai0r-scroll"><table class="widefat striped"><thead><tr><th>Indhold</th><th>Visninger<br>90 dage / 1 år</th><th>Google<br>28 dage</th><th>Prioritering</th><th>Estimeret pris<br>pr. gennemgang</th><th>Status / handling</th></tr></thead><tbody>';
        foreach (array_slice($rows, ($page - 1) * 25, 25) as $r) {
            $p = $r['post']; $g = $r['google'];
            echo '<tr><td><strong><a href="' . esc_url(get_edit_post_link($p->ID)) . '">' . esc_html($p->post_title ?: '(Uden titel)') . '</a></strong><br>' . esc_html($p->post_type === 'page' ? 'Side' : 'Blogindlæg') . ' · ID ' . (int) $p->ID . '<br><a target="_blank" rel="noopener" href="' . esc_url($r['url']) . '">Se siden ↗</a></td>';
            echo '<td>' . (AI0R_Store::uses_internal() ? (int) $r['views']['v90'] . ' / ' . (int) $r['views']['vyear'] : 'Ikke valgt') . '</td><td>' . (AI0R_Store::uses_google() ? (int) $g['clicks'] . ' klik<br>' . (int) $g['impressions'] . ' visninger<br>' . esc_html(number_format_i18n($g['ctr'] * 100, 1)) . '% CTR<br>Position ' . esc_html(number_format_i18n($g['position'], 1)) : 'Ikke valgt') . '</td>';
            echo '<td><strong>Score ' . esc_html((string) $r['score']) . '</strong><p>' . esc_html($r['why']) . '</p>';
            self::form('priority', ['post_id' => $p->ID]);
            echo '<select aria-label="Prioritet" name="priority">';
            foreach ([0 => 'Normal',1 => 'Vigtig',2 => 'Meget vigtig'] as $v => $label) { echo '<option value="' . (int) $v . '" ' . selected((int) get_post_meta($p->ID, '_ai0r_priority', true), $v, false) . '>' . esc_html($label) . '</option>'; }
            echo '</select><label class="ai0r-check"><input name="excluded" type="checkbox" value="1" ' . checked(AI0R_Store::excluded($p->ID), true, false) . '> Udeluk</label><button class="button button-small">Gem</button></form></td><td>';
            try {
                $cost = AI0R_Cost::forecast(AI0R_Plugin::request_plan($p, $s)['body'], $p->post_content, $s);
                echo isset($cost['usd']) ? esc_html(AI0R_Cost::amount($cost['usd'], $cost['usd_dkk'])) . '<br><small>Forsigtigt scenario: ' . esc_html(AI0R_Cost::amount($cost['scenario_usd'], $cost['usd_dkk'])) . '</small>' : esc_html(AI0R_Cost::label($cost));
            } catch (Throwable $e) { echo 'Kan ikke beregnes for dette indhold'; }
            echo '</td><td>';
            if ($r['blocked']) { echo esc_html($r['blocked']); }
            else { echo '<p class="ai0r-ready">Klar til gennemgang</p>'; self::button('generate', 'Generér forslag', ['post_id' => $p->ID]); }
            echo '</td></tr>';
        }
        if (!$rows) { echo '<tr><td colspan="6">Ingen udgivne sider eller indlæg fundet.</td></tr>'; }
        echo '</tbody></table></div>';
        self::pagination(count($rows), 25, $page, 'queue', ['s' => $search]);
        $stats = (array) get_option('ai0r_google_stats', []);
        echo '<p class="description">Valgt trafikkilde: ' . esc_html(['both' => 'Search Console + besøgstæller', 'google' => 'Kun Search Console', 'internal' => 'Kun besøgstæller'][$s['traffic_source']] ?? $s['traffic_source']) . '. ';
        if (AI0R_Store::uses_google()) {
            echo !empty($stats['synced']) ? 'Google-data: ' . esc_html($stats['start'] . ' – ' . $stats['end']) . '. Senest hentet ' . esc_html(wp_date('d.m.Y H:i', $stats['synced'])) . '. Data ældre end syv dage indgår ikke i prioriteringen.' : 'Der er endnu ingen Google-data. Forbind Search Console under fanen ovenfor.';
        }
        echo ' Prisoverslag bruger valgt model, prompt og svargrænse. Tokenmængden er anslået; faktisk forbrug, herunder reasoning, kan afvige. Det forsigtige scenario er ikke et garanteret maksimum. Sidevisninger tælles fra installationen, er ikke unikke besøgende og kan påvirkes af blokering og bots.</p>';
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
            $items[$p->ID] = ($p->post_title ?: '(Uden titel)') . ' · ' . ($p->post_type === 'page' ? 'Side' : 'Blogindlæg') . ' · ID ' . $p->ID;
        }
        $selected_ids = array_unique($selected_ids);
        foreach ($selected_ids as $id) {
            if (!isset($items[$id])) { $items[$id] = 'Tidligere udelukket indhold · ID ' . $id; }
        }
        $out = '<details class="ai0r-picker" data-exclusions><summary>Vælg sider og indlæg <span data-selection-count>(' . count($selected_ids) . ' valgt)</span></summary><div class="ai0r-picker-panel">';
        $out .= '<label for="ai0r-exclusion-search">Søg i sider og blogindlæg</label><input id="ai0r-exclusion-search" type="search" placeholder="Søg på titel eller ID …" autocomplete="off" data-exclusion-search>';
        $out .= '<div class="ai0r-picker-options" role="group" aria-label="Sider og indlæg der skal udelukkes">';
        foreach ($items as $id => $label) {
            $out .= '<label class="ai0r-picker-option" data-exclusion-option><input type="checkbox" name="excluded_ids[]" value="' . (int) $id . '" ' . checked(in_array($id, $selected_ids, true), true, false) . '> <span>' . esc_html($label) . '</span></label>';
        }
        return $out . '</div><p data-no-matches hidden>Ingen sider matcher din søgning.</p><button type="button" class="button button-small" data-clear-exclusions>Ryd alle valg</button></div></details><div class="description ai0r-selected-exclusions" data-selected-exclusions aria-live="polite"></div>';
    }

    private static function settings(): void {
        $s = AI0R_Store::settings(); $u = AI0R_Store::usage();
        echo '<div class="ai0r-panel"><h2>API og redaktionelle valg</h2>';
        self::form('settings', [], 'settings');
        echo '<table class="form-table">';
        self::row('OpenAI API-nøgle', '<input aria-label="OpenAI API-nøgle" class="regular-text" type="password" autocomplete="new-password" name="api_key" value="" placeholder="' . esc_attr($s['api_key'] ? 'Nøgle gemt — lad feltet stå tomt for at beholde den' : 'Indtast din API-nøgle') . '"><label class="ai0r-check"><input type="checkbox" name="remove_api_key" value="1"> Fjern gemt API-nøgle</label>', 'Nøglen gemmes krypteret på denne WordPress-installation og sendes kun til OpenAI.');
        $models = '<div class="ai0r-recommendation"><strong>Anbefalet: GPT-6.1 Sol</strong><p>Mit forslag til denne opgave: god tekstkvalitet og en balance mellem kvalitet og pris. Start med <code>' . esc_html(AI0R_Store::RECOMMENDED_MODEL) . '</code>, og gennemgå de første forslag.</p><button type="button" class="button" data-recommended-model="' . esc_attr(AI0R_Store::RECOMMENDED_MODEL) . '">Brug den anbefalede model</button> <a href="https://developers.openai.com/api/docs/models" target="_blank" rel="noopener">OpenAI Docs ↗</a></div>';
        $available = array_values(array_unique(array_merge(array_keys(AI0R_Cost::catalog()), (array) get_option('ai0r_models', []))));
        sort($available, SORT_NATURAL | SORT_FLAG_CASE);
        $other = !in_array($s['model'], $available, true);
        $models .= '<select aria-label="Model" name="model_choice" data-model-choice>';
        foreach ($available as $m) { $models .= '<option value="' . esc_attr($m) . '" ' . selected($s['model'], $m, false) . '>' . esc_html($m . ($m === AI0R_Store::RECOMMENDED_MODEL ? ' (anbefalet)' : '')) . '</option>'; }
        $models .= '<option value="__other__" ' . selected($other, true, false) . '>Anden model …</option></select><div data-custom-model-wrap' . ($other ? '' : ' hidden') . '><label for="ai0r-custom-model">Model-ID (andet)</label><br><input id="ai0r-custom-model" class="regular-text" name="custom_model" value="' . esc_attr($other ? $s['model'] : '') . '"></div>';
        self::row('Model', $models, 'Listen kan hentes fra din API-konto med knappen nederst. Den indeholder alle kontoens modelnavne; vælg en tekstmodel med Responses og Structured Outputs. Vælg Anden model for selv at skrive et ID.');
        $pricing = '<label><input type="checkbox" name="custom_pricing" value="1" data-custom-pricing ' . checked(isset($s['custom_rates'][$s['model']]), true, false) . '> Brug egne takster til den valgte model</label><div class="ai0r-rates" data-price-fields>';
        foreach (['input' => 'Input', 'output' => 'Output', 'cached' => 'Cache-læsning', 'write' => 'Cache-skrivning'] as $type => $label) {
            $pricing .= '<label>' . esc_html($label) . ' <input aria-label="' . esc_attr($label . ' USD pr. million tokens') . '" name="rate_' . $type . '" type="number" min="0" max="100000" step="any" value="' . esc_attr((string) ($s['custom_rates'][$s['model']][$type] ?? '')) . '"></label> ';
        }
        $pricing .= '</div><p data-rate-description aria-live="polite"></p><script type="application/json" id="ai0r-prices">' . wp_json_encode(['catalog' => AI0R_Cost::catalog(), 'custom' => $s['custom_rates']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
        self::row('Modelpriser · USD pr. 1 mio. tokens', $pricing, 'Indbyggede standardtakster er dateret 25.10.2026. Andre modeller kræver egne takster for at vise et prisestimat. Egne takster bruges uden automatiske tillæg for lang kontekst.');
        self::row('Valutaomregning', '<strong>Automatisk USD → DKK</strong><p>' . esc_html(AI0R_Exchange::description(AI0R_Exchange::current())) . '</p><a href="https://frankfurter.dev/" target="_blank" rel="noopener">Valutakilde ↗</a>', 'Seneste referencekurs hentes automatisk og gemmes lokalt i op til et døgn. Ingen ekstra API-nøgle nødvendig. Weekender og helligdage bruger seneste offentliggjorte kurs. Estimater er uden moms, gebyrer og aftalerabatter.');
        self::row('Prompt', '<textarea aria-label="Prompt" name="prompt" rows="9" class="large-text">' . esc_textarea($s['prompt']) . '</textarea>', 'Prompten kan tilpasses. Pluginets faste bevaringsregler og seksmånedersgrænse kan ikke slås fra med prompten.');
        self::row('Nye indholdselementer', '<label><input type="checkbox" name="additions" value="1" ' . checked((bool) $s['additions'], true, false) . '> Tillad resumé, FAQ, tjekliste, tabel, faktaboks og relevante links</label>', 'Højst fire nye elementer pr. side i alt. Eksisterende medier og specialblokke bliver bevaret.');
        $types = '';
        foreach (['page' => 'Sider','post' => 'Blogindlæg'] as $k => $label) { $types .= '<label class="ai0r-check"><input name="post_types[]" type="checkbox" value="' . esc_attr($k) . '" ' . checked(in_array($k, $s['post_types'], true), true, false) . '> ' . esc_html($label) . '</label>'; }
        self::row('Indholdstyper', $types);
        self::row('Udeluk sider og indlæg', self::exclusions(), "Åbn listen, søg og vælg flere sider.\nValgene er de samme som under Indhold & prioritering og i editoren.\nGem indstillingerne for at anvende dem.\nPrivatlivssiden, adgangskodebeskyttet indhold og Yoast-noindex er automatisk udelukket.");
        $sources = '<select aria-label="Trafikkilde til prioritering" name="traffic_source">';
        foreach (['both' => 'Search Console + indbygget besøgstæller', 'google' => 'Kun Google Search Console', 'internal' => 'Kun indbygget besøgstæller'] as $value => $label) { $sources .= '<option value="' . esc_attr($value) . '" ' . selected($s['traffic_source'], $value, false) . '>' . esc_html($label) . '</option>'; }
        self::row('Trafikkilde til prioritering', $sources . '</select>', 'Valget bestemmer, hvilke trafiktal der påvirker rækkefølgen. Kun Search Console stopper intern optælling. Kun indbygget besøgstæller stopper automatisk Google-synkronisering. Dine manuelle prioriteringer bruges altid.');
        self::row('Sidevisninger', '<label><input type="checkbox" name="tracking" value="1" ' . checked((bool) $s['tracking'], true, false) . '> Aktivér den indbyggede besøgstæller</label>', 'Alle indloggede brugere udelades, også på cachede sider. Data ældre end ét kalenderår slettes ved opdatering og derefter dagligt via WP-Cron, også når automatikken er slået fra. Gemmer kun dato, indholds-ID og antal visninger. Sætter ingen sporingscookies og gemmer ingen besøgsprofiler. Kortvarig misbrugsbegrænsning bruger en envejsværdi af IP-adressen i op til to minutter. Respekterer DNT/GPC. Ryd sidens cache efter aktivering eller ændring.');
        echo '</table><h2>Automatik og forbrugsgrænser</h2><table class="form-table">';
        self::row('Planlagt kørsel', '<label><input name="enabled" type="checkbox" value="1" ' . checked((bool) $s['enabled'], true, false) . '> Aktivér planlagt gennemgang</label>');
        self::row('Når et forslag er klar', '<select aria-label="Publicering" name="mode"><option value="review" ' . selected($s['mode'], 'review', false) . '>Gem forslag til gennemgang</option><option value="auto" ' . selected($s['mode'], 'auto', false) . '>Publicér automatisk efter teknisk kontrol</option></select>', 'Teknisk kontrol sikrer format og bevaring. Den er ikke en garanti for faktuel korrekthed eller bedre Google-placeringer.');
        foreach (['monthly_limit' => ['Maks. genereringer pr. måned',1,500], 'monthly_tokens' => ['Maks. tokens pr. måned',10000,10000000], 'max_output' => ['Maks. svartokens pr. kørsel',1000,32000], 'start_day' => ['Startdag i måneden',1,28], 'hour' => ['Starttidspunkt (time)',0,23]] as $k => $f) {
            self::row($f[0], '<input aria-label="' . esc_attr($f[0]) . '" type="number" name="' . esc_attr($k) . '" min="' . $f[1] . '" max="' . $f[2] . '" value="' . (int) $s[$k] . '">');
        }
        self::row('Månedens forbrug', esc_html(number_format_i18n((int) $u['tokens'])) . ' tokens / reservationer', 'Også manuelle forsøg, afviste forslag og kørsler uden ændringer tæller med. Et tokenloft er ikke et præcist kronebudget. Ved ukendt API-forbrug beholdes en forsigtig reservation.');
        self::row('Tidszone', esc_html(wp_timezone_string()), 'WP-Cron afhænger af trafik. Brug webhotellets cron til wp-cron.php for mere pålidelig kørsel.');
        echo '</table><h2>Google – engangsopsætning</h2><p>Når klienten er konfigureret, foregår tilslutning med Google-login under fanen Google Search Console.</p><table class="form-table">';
        self::row('Google Client ID', '<input aria-label="Google Client ID" class="large-text" name="google_client_id" value="' . esc_attr($s['google_client_id']) . '">');
        self::row('Google Client Secret', '<input aria-label="Google Client Secret" class="regular-text" type="password" autocomplete="new-password" name="google_secret" value="" placeholder="' . esc_attr($s['google_secret'] ? 'Gemt — tomt felt beholder værdien' : 'Indtast Client Secret') . '">', 'En ændring af klienten kræver nyt Google-login.');
        self::row('Autoriseret redirect-URI', '<code>' . esc_html(AI0R_Google::redirect_uri()) . '</code>', 'Kopiér denne præcise adresse til OAuth-klienten i Google Cloud.');
        echo '</table><p><button class="button button-primary">Gem indstillinger</button></p></form>';
        self::button('models', 'Test nøgle og hent modeller', [], 'settings');
        echo '<p class="description">Gem ændrede nøgler før testen. Testen henter modelnavne; den genererer ikke indhold.</p></div>';
    }

    private static function google(): void {
        $s = AI0R_Store::settings();
        echo '<div class="ai0r-panel"><h2>Google Search Console</h2><p>Brug søgeklik, eksponeringer, klikrate og placering til at prioritere eksisterende sider. Pluginet anmoder kun om læseadgang.</p>';
        if (!AI0R_Store::uses_google()) { echo '<p class="ai0r-warning">Search Console er ikke valgt som trafikkilde. Automatisk synkronisering er slået fra, og Google-data indgår ikke i prioriteringen. Du kan stadig hente data manuelt her.</p>'; }
        if (!AI0R_Google::connected()) {
            self::button('connect', 'Forbind med Google', [], 'google', true);
        } else {
            echo '<p class="ai0r-ready">Google er forbundet</p>';
            self::form('property', [], 'google');
            echo '<label for="ai0r-property">Website </label><select id="ai0r-property" name="property"><option value="">Vælg website</option>';
            foreach (AI0R_Google::sites() as $site) { echo '<option value="' . esc_attr($site['siteUrl']) . '" ' . selected($s['property'], $site['siteUrl'], false) . '>' . esc_html($site['siteUrl']) . '</option>'; }
            echo '</select> <button class="button button-primary">Vælg og hent data</button></form><p></p>';
            self::button('sync', 'Opdatér søgedata nu', [], 'google');
            self::button('disconnect', 'Afbryd Google-forbindelsen', [], 'google');
        }
        $stats = get_option('ai0r_google_stats');
        if ($stats) { echo '<p>Senest synkroniseret: ' . esc_html(wp_date('d.m.Y H:i', $stats['synced'])) . '. Periode: ' . esc_html($stats['start'] . ' – ' . $stats['end']) . '.</p>'; }
        if (get_option('ai0r_google_error')) { echo '<p class="ai0r-warning">' . esc_html(get_option('ai0r_google_error')) . '</p>'; }
        echo '<p>Data hentes dagligt og sammenligner to perioder på 28 dage. De seneste tre dage udelades for at undgå foreløbige tal. Manglende rækker betyder ikke nødvendigvis nul trafik; Search Console returnerer ikke nødvendigvis alle data. Søgeord og besøgsprofiler sendes ikke til OpenAI.</p>';
        echo '<details><summary>Første opsætning af Google-login</summary><ol><li>Opret eller vælg dit projekt i <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud</a>.</li><li>Aktivér Google Search Console API.</li><li>Konfigurér OAuth-samtykke med læseadgangen <code>https://www.googleapis.com/auth/webmasters.readonly</code>.</li><li>Opret en OAuth-klient af typen Web application.</li><li>Tilføj denne redirect-URI: <code>' . esc_html(AI0R_Google::redirect_uri()) . '</code>.</li><li>Gem Client ID og Client Secret under pluginets Indstillinger. Vend tilbage hertil, og forbind Google.</li></ol><p>Google-kontoen skal have adgang til dit website i Search Console. En ekstern OAuth-app i testtilstand kan få kortlivede refresh-tokens og derfor kræve hyppige nye login. Ved varig brug skal appens publiceringsstatus og eventuelle Google-verifikationskrav være afklaret.</p></details></div>';
    }

    private static function statuses(): array {
        return ['running' => 'Kører', 'pending' => 'Afventer gennemgang', 'applied' => 'Publiceret', 'rolled_back' => 'Gendannet', 'rejected' => 'Afvist', 'stale' => 'Forældet forslag', 'no_change' => 'Ingen ændringer foreslået', 'error' => 'Fejl'];
    }

    private static function post_links(int $post_id): string {
        $p = get_post($post_id);
        if (!$p) { return ''; }
        $links = [];
        if ($p->post_status === 'publish') {
            $links[] = '<a href="' . esc_url(get_permalink($p)) . '" target="_blank" rel="noopener">Se siden ↗</a>';
        }
        if (current_user_can('edit_post', $post_id)) {
            $edit = get_edit_post_link($post_id, 'raw');
            if ($edit) { $links[] = '<a href="' . esc_url($edit) . '" target="_blank" rel="noopener">Redigér i WordPress ↗</a>'; }
        }
        return $links ? '<p class="ai0r-post-links">' . implode(' · ', $links) . '</p>' : '';
    }

    private static function history(): void {
        global $wpdb;
        $id = absint($_GET['id'] ?? 0);
        if ($id) {
            $h = AI0R_Store::history($id);
            if (!$h) { echo '<p>Historikken findes ikke.</p>'; return; }
            echo '<div class="ai0r-panel"><h2>' . esc_html(get_the_title($h->post_id) ?: 'Indhold slettet') . '</h2><p>' . esc_html(self::statuses()[$h->status] ?? $h->status) . ' · ' . esc_html($h->model) . ' · ' . esc_html(get_date_from_gmt($h->created_at, 'd.m.Y H:i')) . '</p><p>' . nl2br(esc_html($h->summary)) . '</p><p>' . (int) $h->input_tokens . ' inputtokens + ' . (int) $h->output_tokens . ' outputtokens (eller reserverede tokens ved ukendt forbrug).</p>';
            echo self::post_links((int) $h->post_id);
            $details = json_decode($h->details ?? '', true);
            echo '<p><strong>Estimeret pris: ' . esc_html(AI0R_Cost::label($details['cost'] ?? null)) . '</strong></p><p class="description">Priser og beregningskurs gemmes sammen med kørslen. Uden moms, gebyrer og aftalerabatter. Ved fejl kan API-forbruget være ukendt.</p>';
            if (!empty($details['cost']['rates']['source'])) { echo '<p class="description">' . esc_html($details['cost']['rates']['source']) . '</p>'; }
            if (array_key_exists('exchange', $details['cost'] ?? [])) { echo '<p class="description">' . esc_html(AI0R_Exchange::description($details['cost']['exchange'])) . '</p>'; }
            elseif (!empty($details['cost']['usd_dkk'])) { echo '<p class="description">Tidligere manuel beregningskurs: ' . esc_html((string) $details['cost']['usd_dkk']) . ' DKK/USD</p>'; }
            if (is_array($details) && isset($details['text_changes'], $details['new_sections'])) {
                echo '<p class="ai0r-change-count"><strong>' . (int) $details['text_changes'] . ' tekstændringer · ' . (int) $details['new_sections'] . ' nye elementer</strong></p>';
                if (!empty($details['findings'])) {
                    echo '<h3>Modellens vurdering</h3><ul>';
                    foreach ($details['findings'] as $finding) { echo '<li>' . esc_html($finding) . '</li>'; }
                    echo '</ul>';
                }
            }
            if ($h->status === 'no_change') {
                echo '<p class="ai0r-warning"><strong>Ingen ændringer blev foreslået eller publiceret.</strong> Før og efter er identiske.' . (!empty($details['no_change_reason']) ? '<br>Modellens begrundelse: ' . esc_html($details['no_change_reason']) : '') . '</p>';
                $post = get_post($h->post_id);
                if ($post && AI0R_Plugin::reason($post) === '') {
                    self::button('generate', 'Prøv en ny gennemgang', ['post_id' => $h->post_id], 'history');
                    echo '<p class="description">Du kan ændre model eller prompt og prøve igen. Kørslen tæller med i månedens forbrug; seksmånedersgrænsen gælder stadig.</p>';
                }
            }
            if ($h->status === 'pending') { self::button('apply', 'Publicér dette forslag', ['id' => $id], 'history', true); self::button('reject', 'Afvis forslag', ['id' => $id], 'history'); }
            if ($h->status === 'applied') { self::button('restore', 'Gendan indhold før denne opdatering', ['id' => $id], 'history'); }
            echo '<details><summary>Prompt brugt til denne kørsel</summary><pre>' . esc_html($h->prompt) . '</pre></details>';
            if ($h->after_content && $h->after_content !== $h->before_content) {
                echo '<h3>Ændringer i indholdet</h3>';
                require_once ABSPATH . 'wp-admin/includes/revision.php';
                $diff = wp_text_diff($h->before_content, $h->after_content, ['title_left' => 'Før', 'title_right' => 'Forslag', 'show_split_view' => true]);
                echo $diff ?: '<p>Ingen ændringer i indholdet.</p>'; // wp_text_diff escapes source lines.
                echo '<details><summary>Forhåndsvisning af indhold (uden temaets header/footer)</summary>';
                $preview = preg_replace_callback('/<!-- wp:ai0-refresh\/section (\{.*?\}) \/-->/s', static function ($m) { return AI0R_Content::render((array) json_decode($m[1], true)); }, $h->after_content);
                $html = '<!doctype html><html lang="da"><meta charset="utf-8"><style>body{font:17px/1.65 system-ui;padding:24px;max-width:900px;margin:auto}img,video{max-width:100%;height:auto}.ai0r-section{border:1px solid #dbe4ec;padding:20px;margin:24px 0;border-radius:12px}table{border-collapse:collapse;width:100%}td,th{padding:10px;border:1px solid #ddd}iframe{max-width:100%}</style><body>' . wp_kses_post($preview) . '</body></html>';
                echo '<iframe title="Forhåndsvisning af indhold" class="ai0r-preview" sandbox="" referrerpolicy="no-referrer" srcdoc="' . esc_attr($html) . '"></iframe></details>';
                echo '<details><summary>Komplet indhold før og efter</summary><h3>Før</h3><pre>' . esc_html($h->before_content) . '</pre><h3>Efter</h3><pre>' . esc_html($h->after_content) . '</pre></details>';
            }
            echo '</div>'; return;
        }
        $t = AI0R_Store::table('history');
        $page = max(1, absint($_GET['paged'] ?? 1));
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id,post_id,created_at,status,model,summary,input_tokens,output_tokens,details FROM $t ORDER BY id DESC LIMIT 20 OFFSET %d", ($page - 1) * 20));
        echo '<div class="ai0r-panel"><h2>Genereringshistorik</h2><p>Alle forsøg gemmes, også fejl og gennemgange uden ændringer. Forslag ændrer først den udgivne side, når de publiceres.</p><table class="widefat striped"><thead><tr><th>Dato</th><th>Indhold</th><th>Status</th><th>Model / tokens</th><th>Estimeret pris</th></tr></thead><tbody>';
        foreach ($rows as $h) {
            echo '<tr><td>' . esc_html(get_date_from_gmt($h->created_at, 'd.m.Y H:i')) . '</td><td><a href="' . esc_url(add_query_arg(['page' => 'ai0-refresh', 'tab' => 'history', 'id' => $h->id], admin_url('admin.php'))) . '">' . esc_html(get_the_title($h->post_id) ?: '(Indhold slettet)') . '</a>' . self::post_links((int) $h->post_id) . '<p>' . esc_html(mb_substr($h->summary, 0, 180)) . '</p></td><td>' . esc_html(self::statuses()[$h->status] ?? $h->status) . '</td><td>' . esc_html($h->model) . '<br>' . ((int) $h->input_tokens + (int) $h->output_tokens) . '</td><td>' . esc_html(AI0R_Cost::label(json_decode($h->details ?? '', true)['cost'] ?? null)) . '</td></tr>';
        }
        if (!$rows) { echo '<tr><td colspan="5">Ingen kørsler endnu.</td></tr>'; }
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
