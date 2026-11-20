<?php
/**
 * Plugin Name: WebMaker Click Stats
 * Plugin URI:  https://github.com/webmakercy/webmaker-click-stats
 * Description: Privacy-friendly contact statistics for a WebMaker site: counts phone, WhatsApp and email clicks, form opens/sends and WooCommerce cart/checkout/orders in the site's own database and shows them in WP admin (Click Stats + dashboard widget). No cookies, no IP stored.
 * Version: 1.0.0
 * Author: WebMaker Agency
 * Author URI: https://webmakercy.com
 * License: GPL-2.0-or-later
 * Text Domain: webmaker-click-stats
 *
 * Privacy: NO personal data. Per action only: time, type, a short label (e.g. the phone number clicked, the area of the page), page path,
 * language, mobile/desktop and a traffic-source label (Google / Facebook / ChatGPT ...). No IP, no cookie, nothing stored on the visitor's device.
 * A click on the phone/WhatsApp is the INTENT to call/write; it does not prove the call happened.
 *
 * Table: {prefix}wm_clicks (created on first load). If the site already has {prefix}cm_clicks (the old Clean Me version) that table is kept and used.
 * Remove = deactivate/delete the plugin; the table stays (DROP it by hand if wanted).
 */
if (!defined('ABSPATH')) {
    exit;
}

const WM_CLICKS_VER   = '1.0.0';
const WM_CLICKS_DB    = 1;
const WM_CLICKS_TYPES = ['call', 'whatsapp', 'email', 'form_open', 'form_send', 'cart_add', 'checkout', 'order'];

/* ---------- texts (Greek if the admin language is Greek, otherwise English) ---------- */
function wm_clicks_t($key) {
    static $el = null, $en = null;
    if ($en === null) {
        $en = [
            'call' => 'Phone clicks', 'whatsapp' => 'WhatsApp clicks', 'email' => 'Email clicks', 'form_open' => 'Form opened',
            'form_send' => 'Form sent', 'cart_add' => 'Shop: added to cart', 'checkout' => 'Shop: checkout', 'order' => 'Shop: orders',
            'menu' => 'Click Stats', 'title' => 'Clicks and enquiries', 'month' => 'This month',
            'intro' => 'Counts every visitor (with or without cookies). A click on the phone/WhatsApp shows intent, not that the call happened.',
            'per_month' => 'Per month', 'by_source' => 'This month, by source', 'source' => 'Source', 'phone' => 'Phone', 'form' => 'Form',
            'nothing' => 'Nothing yet.', 'where' => 'This month, where they were clicked', 'action' => 'Action', 'spot' => 'Place', 'device' => 'Device', 'clicks' => 'Clicks',
            'last50' => 'Last 50 actions', 'time' => 'Time', 'detail' => 'Detail', 'page' => 'Page', 'lang' => 'Language', 'all' => 'All statistics',
            'subs' => 'Submissions received (Elementor)',
        ];
        $el = [
            'call' => 'Κλικ τηλέφωνο', 'whatsapp' => 'Κλικ WhatsApp', 'email' => 'Κλικ email', 'form_open' => 'Άνοιξε φόρμα',
            'form_send' => 'Έστειλε φόρμα', 'cart_add' => 'Eshop: στο καλάθι', 'checkout' => 'Eshop: ταμείο', 'order' => 'Eshop: παραγγελία',
            'menu' => 'Στατιστικά κλικ', 'title' => 'Κλικ και αιτήσεις', 'month' => 'Αυτόν τον μήνα',
            'intro' => 'Μετράει κάθε επισκέπτη (με ή χωρίς cookies). Το κλικ στο τηλέφωνο/WhatsApp δείχνει πρόθεση, όχι ότι έγινε η κλήση.',
            'per_month' => 'Ανά μήνα', 'by_source' => 'Αυτόν τον μήνα, ανά πηγή', 'source' => 'Πηγή', 'phone' => 'Τηλέφωνο', 'form' => 'Φόρμα',
            'nothing' => 'Τίποτα ακόμα.', 'where' => 'Αυτόν τον μήνα, από πού πατήθηκαν', 'action' => 'Ενέργεια', 'spot' => 'Σημείο', 'device' => 'Συσκευή', 'clicks' => 'Κλικ',
            'last50' => 'Τελευταίες 50 ενέργειες', 'time' => 'Ώρα', 'detail' => 'Λεπτομέρεια', 'page' => 'Σελίδα', 'lang' => 'Γλώσσα', 'all' => 'Όλα τα στατιστικά',
            'subs' => 'Αιτήσεις που έφτασαν (Elementor)',
        ];
    }
    $greek = strpos((string) determine_locale(), 'el') === 0;
    $map = $greek ? $el : $en;
    return isset($map[$key]) ? $map[$key] : $key;
}

function wm_clicks_table() {
    global $wpdb;
    $old = $wpdb->prefix . 'cm_clicks';   // the Clean Me version of this plugin used this name: keep its data
    static $t = null;
    if ($t === null) {
        $t = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old)) === $old) ? $old : $wpdb->prefix . 'wm_clicks';
    }
    return $t;
}

add_action('init', function () {
    if ((int) get_option('wm_clicks_db') >= WM_CLICKS_DB) {
        return;
    }
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta('CREATE TABLE ' . wm_clicks_table() . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  type varchar(20) NOT NULL,
  label varchar(80) NOT NULL DEFAULT '',
  page varchar(190) NOT NULL DEFAULT '',
  lang char(2) NOT NULL DEFAULT '',
  device varchar(8) NOT NULL DEFAULT '',
  source varchar(60) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY type_time (type,created_at)
) " . $wpdb->get_charset_collate() . ';');
    update_option('wm_clicks_db', WM_CLICKS_DB, false);
});

function wm_clicks_lang() {
    if (function_exists('pll_current_language')) {
        return (string) pll_current_language();
    }
    if (defined('ICL_LANGUAGE_CODE')) {
        return (string) ICL_LANGUAGE_CODE;
    }
    return '';
}

function wm_clicks_insert($type, $label = '', $page = '', $lang = '', $device = '', $source = '') {
    global $wpdb;
    if (!in_array($type, WM_CLICKS_TYPES, true)) {
        return false;
    }
    $clip = function ($s, $n) {
        return mb_substr(sanitize_text_field((string) $s), 0, $n);
    };
    return $wpdb->insert(wm_clicks_table(), [
        'created_at' => current_time('mysql'),
        'type'       => $type,
        'label'      => $clip($label, 80),
        'page'       => $clip($page, 190),
        'lang'       => $clip($lang, 2),
        'device'     => in_array($device, ['mobile', 'desktop'], true) ? $device : '',
        'source'     => $clip($source, 60),
    ]);
}

/* ---------- endpoint for the browser: POST /wp-json/wm/v1/hit (cm/v1/hit kept for pages cached by the old Clean Me version) ---------- */
add_action('rest_api_init', function () {
    $args = [
        'methods'             => 'POST',
        'permission_callback' => '__return_true',
        'callback'            => function (WP_REST_Request $r) {
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
            if ($ua === '' || preg_match('/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview/i', $ua)) {
                return new WP_REST_Response(null, 204);
            }
            // flood guard: max 40 hits a minute per visitor (the IP is only hashed in a 1-minute transient, never stored)
            $ip  = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
            $key = 'wm_hit_' . substr(md5($ip . wp_salt('nonce')), 0, 16);
            $n   = (int) get_transient($key);
            if ($n >= 40) {
                return new WP_REST_Response(null, 429);
            }
            set_transient($key, $n + 1, MINUTE_IN_SECONDS);
            $type = (string) $r->get_param('t');
            if ($type === 'book_open') { $type = 'form_open'; }   // names used by the Clean Me version
            if ($type === 'book_send') { $type = 'form_send'; }
            if (in_array($type, ['cart_add', 'order'], true)) {   // recorded by the server hooks below, never from the browser
                return new WP_REST_Response(null, 204);
            }
            wm_clicks_insert($type, $r->get_param('l'), $r->get_param('p'), $r->get_param('g'), $r->get_param('d'), $r->get_param('s'));
            return new WP_REST_Response(null, 204);
        },
    ];
    register_rest_route('wm/v1', '/hit', $args);
    register_rest_route('cm/v1', '/hit', $args);
});

/* ---------- WooCommerce actions, recorded by the server (also covers the Store API) ---------- */
add_action('woocommerce_add_to_cart', function ($cart_item_key, $product_id, $quantity) {
    if (is_admin() && !wp_doing_ajax()) {
        return;
    }
    $p = wc_get_product($product_id);
    wm_clicks_insert('cart_add', $p ? $p->get_name() : ('#' . $product_id), '', wm_clicks_lang());
}, 10, 3);
$wm_order_hit = function ($order) {
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }
    if (!$order || $order->get_meta('_wm_click_logged') || $order->get_meta('_cm_click_logged')) {
        return;
    }
    $order->update_meta_data('_wm_click_logged', 1);
    $order->save_meta_data();
    wm_clicks_insert('order', '#' . $order->get_id() . ' · ' . html_entity_decode(wp_strip_all_tags(get_woocommerce_currency_symbol() . $order->get_total())), '', wm_clicks_lang());
};
add_action('woocommerce_checkout_order_processed', function ($order_id) use ($wm_order_hit) { $wm_order_hit($order_id); }, 20, 1);
add_action('woocommerce_store_api_checkout_order_processed', $wm_order_hit, 20, 1);

/* ---------- browser script ---------- */
add_action('wp_footer', function () {
    if (is_admin()) {
        return;
    }
    $url  = esc_url_raw(rest_url('wm/v1/hit'));
    $lang = wm_clicks_lang();
    ?>
<script id="wm-clicks-js" data-no-optimize="1" data-no-defer="1" data-cfasync="false">
(function () {
  var URL = <?php echo wp_json_encode($url); ?>, LANG = <?php echo wp_json_encode($lang); ?>, GA = { call: 'click_call', whatsapp: 'click_whatsapp', email: 'click_email', form_open: 'form_open', checkout: 'begin_checkout_page' };
  function host(u) { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return ''; } }
  function src() {   // traffic label from this page's referrer / UTM only (nothing is stored on the device)
    var p = new URLSearchParams(location.search), r = document.referrer ? host(document.referrer) : '', s = (p.get('utm_source') || '').toLowerCase(), m = (p.get('utm_medium') || '').toLowerCase();
    if (r === location.hostname.replace(/^www\./, '')) r = '';
    r = r.toLowerCase();
    var fb = p.get('fbclid'), gc = p.get('gclid');
    if (gc || /(^|\.)google\./.test(r) && /cpc|ppc|paid/.test(m)) return 'Google Ads';
    if (fb || /facebook|instagram|fb\.com/.test(r + ' ' + s)) return /instagram/.test(r + ' ' + s) ? 'Instagram' : 'Facebook';
    if (s === 'google' && m === 'organic') return 'Google Business Profile';
    if (/(^|\.)google\./.test(r)) return 'Google (organic)';
    if (/chatgpt|openai/.test(r + ' ' + s)) return 'ChatGPT';
    if (/perplexity|claude\.ai|gemini/.test(r + ' ' + s)) return 'AI assistant';
    if (/tiktok/.test(r + ' ' + s)) return 'TikTok';
    if (/bing\.|yahoo\.|duckduckgo|ecosia|brave/.test(r)) return 'Search (' + r + ')';
    if (s) return (s + (m ? ' / ' + m : '')).slice(0, 60);
    if (r) return ('Link (' + r + ')').slice(0, 60);
    return 'Direct / unknown';
  }
  function device() {
    if (/Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) return 'mobile';
    var w = window.innerWidth || (screen && screen.width) || 1280;
    return (w < 768 || (window.matchMedia && matchMedia('(hover: none) and (pointer: coarse)').matches)) ? 'mobile' : 'desktop';
  }
  var last = {};
  function hit(type, label) {
    var k = type + '|' + label, t = Date.now();
    if (last[k] && t - last[k] < 3000) return;   // double taps
    last[k] = t;
    var b = new URLSearchParams({ t: type, l: label || '', p: location.pathname, g: LANG, d: device(), s: src() });
    try { if (!(navigator.sendBeacon && navigator.sendBeacon(URL, b))) fetch(URL, { method: 'POST', body: b, keepalive: true, credentials: 'omit' }); } catch (e) {}
    try { if (window.gtag && GA[type]) gtag('event', GA[type], { link_text: label || '', traffic_label: src() }); } catch (e) {}
  }
  function area(el) {
    if (el.closest('.elementor-location-header, header')) return 'header';
    if (el.closest('.elementor-location-footer, footer')) return 'footer';
    if (el.closest('.elementor-popup-modal')) return 'popup';
    var f = el; while (f && f !== document.body) { if (getComputedStyle(f).position === 'fixed') return 'floating'; f = f.parentElement; }
    return 'page';
  }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) return;
    var h = a.getAttribute('href') || '';
    if (/^tel:/i.test(h)) return hit('call', h.replace(/^tel:/i, '').replace(/\s+/g, '') + ' · ' + area(a));
    if (/wa\.me|api\.whatsapp\.com|web\.whatsapp\.com|^whatsapp:/i.test(h)) return hit('whatsapp', area(a));
    if (/^mailto:/i.test(h)) return hit('email', h.replace(/^mailto:/i, '').split('?')[0] + ' · ' + area(a));
  }, true);
  if (document.body && document.body.classList.contains('woocommerce-checkout') && !/order-received/.test(location.href)) {
    try { if (!sessionStorage.getItem('wm_co')) { sessionStorage.setItem('wm_co', '1'); hit('checkout', 'checkout page'); } } catch (e) { hit('checkout', 'checkout page'); }
  }
  window.addEventListener('load', function () {
    if (window.jQuery) {   // Elementor popups and forms
      jQuery(document).on('elementor/popup/show', function () { setTimeout(function () { hit('form_open', 'popup'); }, 150); });
      jQuery(document).on('submit_success', function (ev) {
        var f = ev && ev.target && ev.target.closest ? ev.target.closest('.elementor-popup-modal') : null;
        hit('form_send', f ? 'popup form' : 'form');
      });
    }
    document.addEventListener('wpcf7mailsent', function () { hit('form_send', 'Contact Form 7'); });   // Contact Form 7
    document.addEventListener('wpformsAjaxSubmitSuccess', function () { hit('form_send', 'WPForms'); });   // WPForms
  });
})();
</script>
    <?php
}, 99);

/* ---------- admin report ---------- */
function wm_clicks_counts($from, $to) {
    global $wpdb;
    $rows = $wpdb->get_results($wpdb->prepare('SELECT type, COUNT(*) n FROM ' . wm_clicks_table() . ' WHERE created_at >= %s AND created_at < %s GROUP BY type', $from, $to), OBJECT_K);
    $out = [];
    foreach (WM_CLICKS_TYPES as $t) {
        $out[$t] = isset($rows[$t]) ? (int) $rows[$t]->n : 0;
    }
    // the Clean Me version stored these two under other names
    $out['form_open'] += isset($rows['book_open']) ? (int) $rows['book_open']->n : 0;
    $out['form_send'] += isset($rows['book_send']) ? (int) $rows['book_send']->n : 0;
    return $out;
}
add_action('admin_menu', function () {
    add_menu_page(wm_clicks_t('menu'), wm_clicks_t('menu'), 'edit_pages', 'wm-clicks', 'wm_clicks_page', 'dashicons-chart-bar', 3);
});
function wm_clicks_page() {
    global $wpdb;
    $T = wm_clicks_table();
    $send = "'form_send','book_send'";
    echo '<div class="wrap"><h1>' . esc_html(get_bloginfo('name') . ' — ' . wm_clicks_t('title')) . '</h1><p>' . esc_html(wm_clicks_t('intro')) . '</p>';
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[] = date('Y-m-01', strtotime(date('Y-m-01', current_time('timestamp')) . " -$i month"));
    }
    echo '<h2>' . esc_html(wm_clicks_t('per_month')) . '</h2><table class="widefat striped" style="max-width:1000px"><thead><tr><th></th>';
    foreach ($months as $m) {
        echo '<th>' . esc_html(date_i18n('M Y', strtotime($m))) . '</th>';
    }
    echo '</tr></thead><tbody>';
    $data = [];
    foreach ($months as $m) {
        $data[$m] = wm_clicks_counts($m, date('Y-m-01', strtotime($m . ' +1 month')));
    }
    foreach (WM_CLICKS_TYPES as $t) {
        if (in_array($t, ['cart_add', 'checkout', 'order'], true) && !class_exists('WooCommerce')) {
            continue;
        }
        echo '<tr><td><b>' . esc_html(wm_clicks_t($t)) . '</b></td>';
        foreach ($months as $m) {
            echo '<td>' . (int) $data[$m][$t] . '</td>';
        }
        echo '</tr>';
    }
    $subs = $wpdb->prefix . 'e_submissions';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $subs)) === $subs) {
        echo '<tr><td><b>' . esc_html(wm_clicks_t('subs')) . '</b></td>';
        foreach ($months as $m) {
            $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $subs WHERE created_at >= %s AND created_at < %s", $m, date('Y-m-01', strtotime($m . ' +1 month'))));
            echo '<td>' . $n . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    $m0 = date('Y-m-01', current_time('timestamp'));
    $by = $wpdb->get_results($wpdb->prepare("SELECT source, type, COUNT(*) n FROM $T WHERE created_at >= %s AND type IN ('call','whatsapp','email',$send) GROUP BY source, type ORDER BY source", $m0));
    $src = [];
    foreach ($by as $r) {
        $tk = ($r->type === 'book_send') ? 'form_send' : $r->type;
        $src[$r->source ?: '—'][$tk] = (isset($src[$r->source ?: '—'][$tk]) ? $src[$r->source ?: '—'][$tk] : 0) + (int) $r->n;
    }
    echo '<h2>' . esc_html(wm_clicks_t('by_source')) . '</h2><table class="widefat striped" style="max-width:800px"><thead><tr><th>' . esc_html(wm_clicks_t('source')) . '</th><th>' . esc_html(wm_clicks_t('phone')) . '</th><th>WhatsApp</th><th>Email</th><th>' . esc_html(wm_clicks_t('form')) . '</th></tr></thead><tbody>';
    if (!$src) {
        echo '<tr><td colspan="5">' . esc_html(wm_clicks_t('nothing')) . '</td></tr>';
    }
    foreach ($src as $s => $v) {
        echo '<tr><td>' . esc_html($s) . '</td>';
        foreach (['call', 'whatsapp', 'email', 'form_send'] as $t) {
            echo '<td>' . (isset($v[$t]) ? $v[$t] : 0) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    $where = $wpdb->get_results($wpdb->prepare("SELECT type, label, device, COUNT(*) n FROM $T WHERE created_at >= %s AND type IN ('call','whatsapp','email') GROUP BY type, label, device ORDER BY n DESC LIMIT 30", $m0));
    echo '<h2>' . esc_html(wm_clicks_t('where')) . '</h2><table class="widefat striped" style="max-width:800px"><thead><tr><th>' . esc_html(wm_clicks_t('action')) . '</th><th>' . esc_html(wm_clicks_t('spot')) . '</th><th>' . esc_html(wm_clicks_t('device')) . '</th><th>' . esc_html(wm_clicks_t('clicks')) . '</th></tr></thead><tbody>';
    foreach ($where as $r) {
        echo '<tr><td>' . esc_html(wm_clicks_t($r->type)) . '</td><td>' . esc_html($r->label) . '</td><td>' . esc_html($r->device) . '</td><td>' . (int) $r->n . '</td></tr>';
    }
    echo '</tbody></table>';
    $last = $wpdb->get_results("SELECT * FROM $T ORDER BY id DESC LIMIT 50");
    echo '<h2>' . esc_html(wm_clicks_t('last50')) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html(wm_clicks_t('time')) . '</th><th>' . esc_html(wm_clicks_t('action')) . '</th><th>' . esc_html(wm_clicks_t('detail')) . '</th><th>' . esc_html(wm_clicks_t('page')) . '</th><th>' . esc_html(wm_clicks_t('lang')) . '</th><th>' . esc_html(wm_clicks_t('device')) . '</th><th>' . esc_html(wm_clicks_t('source')) . '</th></tr></thead><tbody>';
    foreach ($last as $r) {
        $when = (new DateTime($r->created_at, wp_timezone()))->setTimezone(new DateTimeZone('Europe/Nicosia'))->format('d/m/Y H:i');
        $tk   = ($r->type === 'book_open') ? 'form_open' : (($r->type === 'book_send') ? 'form_send' : $r->type);
        echo '<tr><td>' . esc_html($when) . '</td><td>' . esc_html(wm_clicks_t($tk)) . '</td><td>' . esc_html($r->label) . '</td><td>' . esc_html($r->page) . '</td><td>' . esc_html($r->lang) . '</td><td>' . esc_html($r->device) . '</td><td>' . esc_html($r->source) . '</td></tr>';
    }
    echo '</tbody></table></div>';
}
add_action('wp_dashboard_setup', function () {
    if (!current_user_can('edit_pages')) {
        return;
    }
    wp_add_dashboard_widget('wm_clicks_widget', get_bloginfo('name') . ' — ' . wm_clicks_t('month'), function () {
        $c = wm_clicks_counts(date('Y-m-01', current_time('timestamp')), date('Y-m-01', strtotime(date('Y-m-01', current_time('timestamp')) . ' +1 month')));
        echo '<p style="font-size:14px;line-height:1.9">📞 ' . esc_html(wm_clicks_t('phone')) . ': <b>' . $c['call'] . '</b> &nbsp; 💬 WhatsApp: <b>' . $c['whatsapp'] . '</b> &nbsp; ✉️ Email: <b>' . $c['email'] . '</b><br>📝 ' . esc_html(wm_clicks_t('form')) . ': <b>' . $c['form_open'] . '</b> / <b>' . $c['form_send'] . '</b>';
        if (class_exists('WooCommerce')) {
            echo '<br>🛒 ' . $c['cart_add'] . ' / ' . $c['checkout'] . ' / ' . $c['order'];
        }
        echo '</p><p><a href="' . esc_url(admin_url('admin.php?page=wm-clicks')) . '">' . esc_html(wm_clicks_t('all')) . ' →</a></p>';
    });
});
