<?php
/**
 * Der Shop als App auf dem Startbildschirm.
 *
 * Wer im Lager steht und nachbestellt, will nicht erst den Browser
 * öffnen, ein Lesezeichen suchen und sich durch die Startseite
 * arbeiten. Ein Symbol antippen, und die Liste ist da.
 *
 * Das geht ohne App Store: ein Manifest sagt dem Telefon, wie die Seite
 * heißen soll, welches Symbol sie trägt und womit sie startet. Danach
 * legt man sie über "Zum Home-Bildschirm" ab und sie öffnet ohne
 * Browserleisten.
 *
 * Die Startseite ist einstellbar
 * ------------------------------
 *
 * Ein Manifest kennt nur eine feste Startadresse. Damit trotzdem jeder
 * seine haben kann, zeigt sie nicht auf eine Seite, sondern auf /app/ —
 * und die schickt weiter, je nachdem, was der Angemeldete gewählt hat.
 * Im Lager also die Schnellerfassung, im Büro die eigene Artikelliste.
 *
 * Was das nicht ist
 * -----------------
 *
 * Kein Offline-Betrieb. Dafür bräuchte es einen Service Worker, und der
 * liefert im schlechten Fall veraltete Preise aus oder bricht die
 * Kasse. Für einen Shop ist das ein Risiko, das der Bequemlichkeit
 * nicht wert ist. Ohne ihn funktioniert alles außer Offline.
 *
 * @package sapelza-shop
 */

if (!defined('ABSPATH')) exit;

/** Wird die Fassung hier erhöht, schreibt WordPress seine Adressregeln neu. */
const SZ_APP_REGELN = '1.0.0';

/**
 * Womit die App starten kann.
 *
 * Nur Seiten, die es wirklich gibt: die Schnellerfassung wird gesucht,
 * nicht geraten, und fehlt sie, steht sie auch nicht zur Wahl. Eine
 * Einstellung, die auf einen 404 zeigt, ist schlimmer als eine, die
 * fehlt.
 *
 * @return array<string, array{titel: string, url: string}>
 */
function sz_app_ziele(): array
{
    $ziele = [];

    $artikel = get_page_by_path('meine-artikel');
    if ($artikel) {
        $ziele['meine-artikel'] = [
            'titel' => __('Meine Artikel', 'sapelza-shop'),
            'url'   => (string) get_permalink($artikel),
        ];
    }

    if (function_exists('sz_erfassung_url')) {
        $erfassung = sz_erfassung_url();
        if ($erfassung !== '') {
            $ziele['schnellerfassung'] = [
                'titel' => __('Schnellerfassung', 'sapelza-shop'),
                'url'   => $erfassung,
            ];
        }
    }

    if (function_exists('wc_get_page_permalink')) {
        $laden = wc_get_page_permalink('shop');
        if ($laden) {
            $ziele['produkte'] = [
                'titel' => __('Produkte', 'sapelza-shop'),
                'url'   => (string) $laden,
            ];
        }

        $konto = wc_get_page_permalink('myaccount');
        if ($konto) {
            $ziele['konto'] = [
                'titel' => __('Mein Konto', 'sapelza-shop'),
                'url'   => (string) $konto,
            ];
        }
    }

    $ziele['startseite'] = [
        'titel' => __('Startseite', 'sapelza-shop'),
        'url'   => home_url('/'),
    ];

    return $ziele;
}

/**
 * Was der Angemeldete gewählt hat — sonst die Voreinstellung.
 *
 * Voreinstellung ist "Meine Artikel": die eigene Liste ist das, wofür
 * dieser Shop gebaut ist. Wer lieber scannt, stellt es um.
 */
function sz_app_wahl(): string
{
    $wahl = '';

    if (is_user_logged_in()) {
        $wahl = (string) get_user_meta(get_current_user_id(), '_sz_app_start', true);
    }

    if ($wahl === '') $wahl = (string) get_option('sz_app_start', 'meine-artikel');

    $ziele = sz_app_ziele();

    /* Zeigt die Wahl ins Leere — weil die Seite umbenannt oder gelöscht
       wurde —, fällt sie auf das erste vorhandene Ziel zurück. */
    if (!isset($ziele[$wahl])) $wahl = (string) array_key_first($ziele);

    return $wahl;
}

function sz_app_start_url(): string
{
    $ziele = sz_app_ziele();
    $wahl  = sz_app_wahl();

    return isset($ziele[$wahl]) ? $ziele[$wahl]['url'] : home_url('/');
}

/**
 * Das Symbol — das Theme darf es überschreiben.
 *
 * Wer ein eigenes will, legt es als bilder/app-512.png ins Theme; von
 * dort hat es Vorrang. So braucht es dafür keine Einstellung und keinen
 * Eingriff ins Plugin.
 */
function sz_app_symbol(int $groesse): string
{
    $datei = '/bilder/app-' . $groesse . '.png';

    if (file_exists(get_stylesheet_directory() . $datei)) {
        return get_stylesheet_directory_uri() . $datei;
    }

    return plugins_url('bilder/app-' . $groesse . '.png', SZ_SHOP_PFAD . 'sapelza-shop.php');
}

/* ===================================================================
   Die zwei Adressen: /app/ und /app.webmanifest
   =================================================================== */

add_action('init', static function (): void {
    add_rewrite_rule('^app/?$', 'index.php?sz_app=1', 'top');
    add_rewrite_rule('^app\.webmanifest$', 'index.php?sz_app=manifest', 'top');

    /*
     * Neue Adressregeln greifen erst, wenn WordPress sie neu schreibt.
     * Sonst zeigt /app/ einen 404, und niemand käme darauf, dass die
     * Ursache in den Permalinks liegt. Einmal je Fassung, dann nie
     * wieder.
     */
    if (get_option('sz_app_regeln') !== SZ_APP_REGELN) {
        flush_rewrite_rules(false);
        update_option('sz_app_regeln', SZ_APP_REGELN);
    }
});

add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'sz_app';
    return $vars;
});

add_action('template_redirect', static function (): void {
    $was = get_query_var('sz_app');

    /* Fallback über die Abfrage, falls die Adressregeln einmal nicht
       greifen — dann geht wenigstens /?sz_app=1 noch. */
    if ($was === '' && isset($_GET['sz_app'])) {
        $was = sanitize_key(wp_unslash($_GET['sz_app']));
    }

    if ($was === '' || $was === false) return;

    if ($was === 'manifest') {
        sz_app_manifest_ausgeben();
        exit;
    }

    /*
     * Wer nicht angemeldet ist, sieht ohne Preise nichts Brauchbares.
     * Also zuerst die Anmeldung — und danach dorthin, wo er hinwollte.
     * Das erledigt der Filter weiter unten.
     *
     * Die Marke heisst sz_von und ist bewusst KEIN angemeldetes
     * Abfragewort. Mit sz_app=1 griff dieser Riegel auf der Kontoseite
     * noch einmal, schickte wieder dorthin, und der Browser brach nach
     * zehn Runden mit "zu viele Weiterleitungen" ab — ausgerechnet im
     * haeufigsten Fall: Symbol antippen, noch nicht angemeldet.
     */
    if (!is_user_logged_in() && function_exists('wc_get_page_permalink')) {
        $konto = wc_get_page_permalink('myaccount');
        if ($konto) {
            wp_safe_redirect(add_query_arg('sz_von', 'app', $konto));
            exit;
        }
    }

    $ziel = sz_app_start_url();

    /*
     * Und ein Riegel gegen Schleifen ueberhaupt: zeigt das Ziel auf die
     * Adresse, auf der wir gerade stehen, wird nicht weitergeleitet,
     * sondern die Seite gezeigt. Lieber eine Seite, die nicht ganz die
     * gewuenschte ist, als eine Fehlermeldung des Browsers.
     */
    $zielweg = (string) wp_parse_url($ziel, PHP_URL_PATH);
    $hierweg = (string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    if ($zielweg !== '' && untrailingslashit($zielweg) === untrailingslashit($hierweg)) return;

    wp_safe_redirect($ziel);
    exit;
});

/**
 * Nach der Anmeldung aus der App: weiter zum gewählten Start.
 */
add_filter('woocommerce_login_redirect', static function ($ziel, $benutzer) {
    $von = isset($_GET['sz_von']) ? sanitize_key(wp_unslash($_GET['sz_von'])) : '';
    if ($von !== 'app') return $ziel;

    return sz_app_start_url();
}, 20, 2);

function sz_app_manifest_ausgeben(): void
{
    $manifest = [
        'id'               => home_url('/app/'),
        'name'             => get_bloginfo('name') ?: 'Sapelza',
        'short_name'       => 'Sapelza',
        'description'      => __('Bestellen für Handwerk und Gastronomie im Hochpustertal.', 'sapelza-shop'),
        'lang'             => 'de',
        'dir'              => 'ltr',
        'start_url'        => home_url('/app/'),
        'scope'            => home_url('/'),
        'display'          => 'standalone',
        'background_color' => '#f5f2ec',
        'theme_color'      => '#b52f36',
        'icons'            => [
            [
                'src'     => sz_app_symbol(192),
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ],
            [
                'src'     => sz_app_symbol(512),
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ],
        ],
    ];

    nocache_headers();
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/* ===================================================================
   Was im Kopf jeder Seite stehen muss
   =================================================================== */

add_action('wp_head', static function (): void {
    $manifest = home_url('/app.webmanifest');
    ?>
    <link rel="manifest" href="<?php echo esc_url($manifest); ?>">
    <meta name="theme-color" content="#b52f36">
    <?php
    /*
     * Safari liest das Manifest inzwischen, verlangt für das Symbol auf
     * dem Startbildschirm aber weiterhin apple-touch-icon. Ohne das
     * nimmt es einen Bildschirmausschnitt der Seite — und der sieht
     * jedes Mal anders aus.
     */
    ?>
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url(sz_app_symbol(180)); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Sapelza">
    <?php
}, 5);

/* ===================================================================
   Die Wahl im Konto
   =================================================================== */

/**
 * Speichern — vor jeder Ausgabe, damit die Seite die neue Wahl zeigt.
 */
add_action('template_redirect', static function (): void {
    if (!isset($_POST['sz_app_speichern'])) return;
    if (!is_user_logged_in()) return;

    check_admin_referer('sz_app_start');

    $wahl  = sanitize_key(wp_unslash($_POST['sz_app_start'] ?? ''));
    $ziele = sz_app_ziele();

    if (isset($ziele[$wahl])) {
        update_user_meta(get_current_user_id(), '_sz_app_start', $wahl);
    }

    wp_safe_redirect(add_query_arg('sz-app-gespeichert', '1', wp_get_referer() ?: home_url('/')));
    exit;
}, 5);

add_action('woocommerce_account_dashboard', 'sz_app_karte', 40);

function sz_app_karte(): void
{
    $ziele = sz_app_ziele();
    if (!$ziele) return;

    $jetzt = sz_app_wahl();
    ?>
    <section class="sz-appkarte">
        <p class="sz-kapitelmarke">
            <span class="sz-kapitelmarke__nr mono">05</span>
            <span class="hairline"></span>
            <span class="sz-kapitelmarke__kicker mono"><?php echo esc_html__('Auf dem Telefon', 'sapelza-shop'); ?></span>
        </p>

        <h2 class="sz-appkarte__titel"><?php echo esc_html__('Den Shop als App ablegen', 'sapelza-shop'); ?></h2>

        <p class="sz-appkarte__lead">
            <?php echo esc_html__('Sie können den Shop auf den Startbildschirm legen — ein Symbol antippen, und die Liste ist da, ohne Browserleisten. Kein App Store, keine Installation.', 'sapelza-shop'); ?>
        </p>

        <?php if (isset($_GET['sz-app-gespeichert'])) : ?>
            <p class="sz-appkarte__gemerkt"><?php echo esc_html__('Gespeichert. Beim nächsten Öffnen der App gilt die neue Wahl.', 'sapelza-shop'); ?></p>
        <?php endif; ?>

        <form method="post" class="sz-appkarte__form">
            <?php wp_nonce_field('sz_app_start'); ?>

            <label class="sz-appkarte__frage" for="sz-app-start">
                <?php echo esc_html__('Womit soll die App starten?', 'sapelza-shop'); ?>
            </label>

            <div class="sz-appkarte__zeile">
                <select id="sz-app-start" name="sz_app_start">
                    <?php foreach ($ziele as $sz_k => $sz_z) : ?>
                        <option value="<?php echo esc_attr($sz_k); ?>" <?php selected($sz_k, $jetzt); ?>>
                            <?php echo esc_html($sz_z['titel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" name="sz_app_speichern" value="1" class="sz-appkarte__knopf">
                    <?php echo esc_html__('Merken', 'sapelza-shop'); ?>
                </button>
            </div>

            <p class="sz-appkarte__fein">
                <?php echo esc_html__('Die Wahl gilt für Sie, nicht für den ganzen Betrieb — wer im Lager scannt, kann mit der Schnellerfassung starten, während im Büro die Artikelliste aufgeht.', 'sapelza-shop'); ?>
            </p>
        </form>

        <div class="sz-appkarte__wege">
            <div class="sz-appkarte__weg">
                <h3 class="sz-appkarte__wegtitel"><?php echo esc_html__('iPhone und iPad', 'sapelza-shop'); ?></h3>
                <ol class="sz-appkarte__schritte">
                    <li><?php echo esc_html__('Diese Seite in Safari öffnen.', 'sapelza-shop'); ?></li>
                    <li><?php echo esc_html__('Unten auf das Teilen-Zeichen tippen — das Quadrat mit dem Pfeil nach oben.', 'sapelza-shop'); ?></li>
                    <li><?php echo esc_html__('„Zum Home-Bildschirm" wählen und bestätigen.', 'sapelza-shop'); ?></li>
                </ol>
            </div>

            <div class="sz-appkarte__weg">
                <h3 class="sz-appkarte__wegtitel"><?php echo esc_html__('Android', 'sapelza-shop'); ?></h3>
                <ol class="sz-appkarte__schritte">
                    <li><?php echo esc_html__('Diese Seite in Chrome öffnen.', 'sapelza-shop'); ?></li>
                    <li><?php echo esc_html__('Oben rechts auf die drei Punkte tippen.', 'sapelza-shop'); ?></li>
                    <li><?php echo esc_html__('„App installieren" oder „Zum Startbildschirm hinzufügen" wählen.', 'sapelza-shop'); ?></li>
                </ol>
            </div>
        </div>

        <p class="sz-appkarte__fein">
            <?php echo esc_html__('Einmal noch anmelden: Das Fenster vom Startbildschirm hat auf dem iPhone einen eigenen Speicher, getrennt von Safari. Danach bleibt es angemeldet.', 'sapelza-shop'); ?>
        </p>
    </section>
    <?php
}
