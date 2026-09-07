<?php
/**
 * Die Sprache des Shops — Eigenbau statt Polylang.
 *
 * Ein Artikel, drei Sprachen. Italienisch und Englisch liegen als
 * Zusatzfelder am Artikel (_sz_name_it, _sz_kurz_it, _sz_name_en,
 * _sz_kurz_en) und an der Kategorie (_sz_name_it, _sz_name_en). Es gibt
 * keine Zwillinge, also nichts abzugleichen: Bestand, Preise und
 * B2BKing-Konditionen bleiben, wo sie sind.
 *
 * Die Sprache merkt sich der Shop pro Kunde — im Cookie, und bei
 * Angemeldeten zusätzlich im Konto, damit sie das nächste Gerät kennt.
 * Gewählt wird über ?lang=it; das Cookie hält die Wahl ein Jahr.
 *
 * Was hier NICHT steht: die Sprachwahl selbst und der Wechsel des
 * Gebietsschemas. Beides muss laufen, bevor WordPress seine eigenen
 * Texte lädt — und das tut es vor after_setup_theme, wo dieses Modul
 * geladen wird. Deshalb stehen sz_sprache(), sz_sprachen_verfuegbar()
 * und die locale-Filter in sapelza-shop.php.
 *
 * Kein eigener Pfad je Sprache (/it/…): dafür müsste jede Adresse, die
 * WordPress erzeugt, umgeschrieben werden — genau die Maschine, die
 * Polylang ausmacht. Der Preis ist, dass Google den Shop nur auf
 * Deutsch sieht. Für einen Shop, dessen Preise hinter der Anmeldung
 * liegen, ist das der kleinere.
 */

if (!defined('ABSPATH')) exit;

const SZ_SPRACHE_SEITE = 'sz-uebersetzungen';

/* ===================================================================
   Die Wahl merken
   =================================================================== */

/** Das Cookie schreiben — ein Jahr, nur über HTTPS, für JavaScript unsichtbar. */
function sz_sprache_cookie_setzen(string $wahl): void
{
    if (headers_sent()) return;

    setcookie(SZ_SPRACHE_COOKIE, $wahl, [
        'expires'  => time() + YEAR_IN_SECONDS,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[SZ_SPRACHE_COOKIE] = $wahl;
}

/*
 * ?lang=it in der Adresse: Cookie schreiben, bei Angemeldeten auch ins
 * Konto. Priorität 2, damit es vor allem anderen auf init geschieht.
 */
add_action('init', function () {
    if (!isset($_GET['lang'])) return; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    $wahl = sz_sprache();
    sz_sprache_cookie_setzen($wahl);

    if (is_user_logged_in()) {
        update_user_meta(get_current_user_id(), 'sz_sprache', $wahl);
    }
}, 2);

/*
 * Beim Anmelden holt sich das Gerät die Sprache aus dem Konto — wer am
 * Telefon Italienisch gewählt hat, bekommt es am Schreibtisch auch.
 */
add_action('wp_login', function ($login, $user) {
    if (!$user instanceof WP_User) return;

    $wahl = (string) get_user_meta($user->ID, 'sz_sprache', true);
    if ($wahl !== '' && isset(sz_sprachen_verfuegbar()[$wahl])) {
        sz_sprache_cookie_setzen($wahl);
    }
}, 10, 2);

/* ===================================================================
   Was das Theme braucht
   =================================================================== */

/**
 * Die Sprachen für den Schalter — in der Form, in der auch Polylang sie
 * liefert (slug, name, url, current_lang), damit das Theme nicht
 * unterscheiden muss, woher sie kommen.
 *
 * Die Adresse ist die aktuelle Seite mit ?lang=… — die Wahl bleibt also,
 * wo man gerade ist, statt auf die Startseite zu springen.
 */
function sz_sprachen_liste(): array
{
    $aktuell = sz_sprache();
    $aus = [];

    foreach (sz_sprachen_verfuegbar() as $code => $s) {
        $aus[$code] = [
            'slug'         => $code,
            'name'         => $s['name'],
            'url'          => add_query_arg('lang', $code),
            'current_lang' => $code === $aktuell,
        ];
    }

    return $aus;
}

/* ===================================================================
   Übersetzte Felder ausgeben
   =================================================================== */

/**
 * Gilt die Übersetzung gerade? Im Backend nicht — dort arbeitet man in
 * der Sprache des eigenen Profils, und die Artikelliste soll die
 * Katalognamen zeigen. Ajax-Aufrufe aus dem Shop zählen zum Shop.
 */
function sz_sprache_uebersetzt(): bool
{
    if (sz_sprache() === sz_sprache_standard()) return false;
    if (is_admin() && !wp_doing_ajax()) return false;
    return true;
}

/** Ein Feld eines Artikels in der gewählten Sprache, sonst der Rückfall. */
function sz_uebersetzt(int $id, string $feld, string $rueckfall): string
{
    if ($id <= 0 || !sz_sprache_uebersetzt()) return $rueckfall;

    $wert = get_post_meta($id, '_sz_' . $feld . '_' . sz_sprache(), true);
    return is_string($wert) && trim($wert) !== '' ? $wert : $rueckfall;
}

/*
 * Der Name: get_name() ist der eine Weg, den alle nehmen — Kachel,
 * Produktseite, Warenkorb, Schnellerfassung, Meine Artikel.
 * get_name('edit') umgeht den Filter; das nutzt die Kasse unten.
 */
add_filter('woocommerce_product_get_name', function ($name, $artikel) {
    return sz_uebersetzt((int) $artikel->get_id(), 'name', (string) $name);
}, 10, 2);

/* Titel über get_the_title(), etwa in Brotkrumen und im <title>. */
add_filter('the_title', function ($titel, $id = 0) {
    if (!$id || get_post_type($id) !== 'product') return $titel;
    return sz_uebersetzt((int) $id, 'name', (string) $titel);
}, 10, 2);

add_filter('woocommerce_product_get_short_description', function ($text, $artikel) {
    return sz_uebersetzt((int) $artikel->get_id(), 'kurz', (string) $text);
}, 10, 2);

/*
 * Kategorien: get_term() ist die Stelle, durch die jeder Begriff geht,
 * ob aus get_terms(), get_the_terms() oder der Brotkrume. WordPress
 * hält in seinen Zwischenspeichern die rohen Zeilen und ruft den Filter
 * beim Lesen — eine italienische Anfrage vergiftet also keine deutsche.
 *
 * Fehlt ein Zusatzfeld am Begriff, greift die Tabelle unten: die
 * Abteilungen des Hauses, damit nach dem Einspielen nichts Deutsches in
 * der italienischen Leiste steht.
 */
add_filter('get_term', function ($begriff, $taxonomie) {
    if (!$begriff instanceof WP_Term || $taxonomie !== 'product_cat') return $begriff;
    if (!sz_sprache_uebersetzt()) return $begriff;

    $sprache = sz_sprache();
    $wert = get_term_meta($begriff->term_id, '_sz_name_' . $sprache, true);
    if (!is_string($wert) || trim($wert) === '') {
        $wert = sz_kategorie_wort($begriff->name, $sprache);
    }
    if ($wert !== '') $begriff->name = $wert;

    return $begriff;
}, 10, 2);

/**
 * Die Abteilungen, die es beim Bau gab. Was der Betreiber am Begriff
 * einträgt, geht vor; die Tabelle ist nur das Netz darunter.
 */
function sz_kategorie_wort(string $deutsch, string $sprache): string
{
    static $tabelle = [
        'Handwerk'                       => ['it' => 'Artigianato',                    'en' => 'Trades'],
        'Gastronomie'                    => ['it' => 'Ristorazione',                   'en' => 'Hospitality'],
        'Abfall & Beutel'                => ['it' => 'Rifiuti e sacchetti',            'en' => 'Waste & bags'],
        'Desinfektion'                   => ['it' => 'Disinfezione',                   'en' => 'Disinfection'],
        'Gästekosmetik'                  => ['it' => 'Cortesia per gli ospiti',        'en' => 'Guest amenities'],
        'Handschuhe & Schutz'            => ['it' => 'Guanti e protezione',            'en' => 'Gloves & protection'],
        'Hygienepapier'                  => ['it' => 'Carta per l’igiene',             'en' => 'Hygiene paper'],
        'Reinigungsmittel'               => ['it' => 'Detergenti',                     'en' => 'Cleaning agents'],
        'Reinigungstextilien & Zubehör'  => ['it' => 'Panni e accessori per la pulizia', 'en' => 'Cleaning textiles & accessories'],
        'Schädlingsbekämpfung'           => ['it' => 'Disinfestazione',                'en' => 'Pest control'],
        'Spülmaschine & Geschirr'        => ['it' => 'Lavastoviglie e stoviglie',      'en' => 'Dishwashing & tableware'],
        'Wäschepflege'                   => ['it' => 'Cura della biancheria',          'en' => 'Laundry care'],
        'Besteck'                        => ['it' => 'Posate',                         'en' => 'Cutlery'],
        'Gläser'                         => ['it' => 'Bicchieri',                      'en' => 'Glassware'],
        'Geschirr'                       => ['it' => 'Stoviglie',                      'en' => 'Tableware'],
        'Lebensmittel'                   => ['it' => 'Alimentari',                     'en' => 'Food'],
        'Tischaccessoires'               => ['it' => 'Accessori per la tavola',        'en' => 'Table accessories'],
    ];

    return $tabelle[$deutsch][$sprache] ?? '';
}

/*
 * Bestellungen bleiben deutsch. Die Kasse schreibt den Namen des
 * Artikels in die Bestellposition — in der Sprache, die der Kunde gerade
 * hat. Das Lager arbeitet aber auf Deutsch, und ein Lieferschein mit
 * „Flocculant" neben „Flockungsmittel Pool" hilft niemandem.
 * get_name('edit') liefert den Katalognamen am Filter vorbei.
 */
add_action('woocommerce_checkout_create_order_line_item', function ($position, $schluessel, $werte) {
    if (!sz_sprache_uebersetzt()) return;
    $artikel = $werte['data'] ?? null;
    if (!$artikel instanceof WC_Product) return;

    $position->set_name($artikel->get_name('edit'));
}, 10, 3);

/* ===================================================================
   Der Shop weiß, welche Sprache läuft
   =================================================================== */

add_filter('body_class', function ($klassen) {
    $klassen[] = 'sz-sprache-' . sz_sprache();
    return $klassen;
});

/*
 * Das Skript, das ?lang=… an jeden Link hängt, solange eine andere als
 * die Standardsprache gewählt ist. Nötig wegen des Zwischenspeichers
 * auf dem Server: der kennt Cookies nicht und gäbe einem Gast, der über
 * einen nackten Link weitergeht, die deutsche Seite. Mit dem Parameter
 * in der Adresse ist jede Seite die richtige — und lässt sich obendrein
 * in ihrer Sprache weitergeben.
 *
 * In der Standardsprache läuft es nicht; dort sind die Adressen sauber.
 */
add_action('wp_enqueue_scripts', function () {
    if (sz_sprache() === sz_sprache_standard()) return;

    wp_enqueue_script(
        'sapelza-sprache',
        plugins_url('js/sprache.js', SZ_SHOP_PFAD . 'sapelza-shop.php'),
        [],
        '1.0.0',
        true
    );
    wp_localize_script('sapelza-sprache', 'szSprache', [
        'code'     => sz_sprache(),
        'standard' => sz_sprache_standard(),
    ]);
}, 20);

/* ===================================================================
   Pflege im Backend: Artikel
   =================================================================== */

add_action('add_meta_boxes', function () {
    add_meta_box(
        'sz-sprachen',
        __('Übersetzungen', 'sapelza-shop'),
        'sz_sprachen_kasten',
        'product',
        'normal',
        'default'
    );
});

/** Die Felder am Artikel: je Sprache Name und Kurzbeschreibung. */
function sz_sprachen_kasten(WP_Post $beitrag): void
{
    wp_nonce_field('sz_sprachen_' . $beitrag->ID, 'sz_sprachen_nonce');
    ?>
    <p class="description">
        <?php echo esc_html__('Leer heißt: der deutsche Text wird gezeigt. Der deutsche Name und die deutsche Kurzbeschreibung stehen oben wie gewohnt.', 'sapelza-shop'); ?>
    </p>
    <?php foreach (sz_sprachen_verfuegbar() as $code => $s) :
        if ($code === sz_sprache_standard()) continue;
        $name = (string) get_post_meta($beitrag->ID, '_sz_name_' . $code, true);
        $kurz = (string) get_post_meta($beitrag->ID, '_sz_kurz_' . $code, true);
        ?>
        <h4 style="margin: 1em 0 0.4em;"><?php echo esc_html($s['name']); ?></h4>
        <p>
            <label for="sz-name-<?php echo esc_attr($code); ?>" style="display:block; margin-bottom: 0.2em;">
                <?php echo esc_html__('Name', 'sapelza-shop'); ?>
            </label>
            <input type="text" class="large-text" id="sz-name-<?php echo esc_attr($code); ?>"
                   name="sz_name_<?php echo esc_attr($code); ?>" value="<?php echo esc_attr($name); ?>">
        </p>
        <p>
            <label for="sz-kurz-<?php echo esc_attr($code); ?>" style="display:block; margin-bottom: 0.2em;">
                <?php echo esc_html__('Kurzbeschreibung', 'sapelza-shop'); ?>
            </label>
            <textarea class="large-text" rows="3" id="sz-kurz-<?php echo esc_attr($code); ?>"
                      name="sz_kurz_<?php echo esc_attr($code); ?>"><?php echo esc_textarea($kurz); ?></textarea>
        </p>
    <?php endforeach;
}

add_action('save_post_product', function ($id) {
    if (!isset($_POST['sz_sprachen_nonce'])) return;
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sz_sprachen_nonce'])), 'sz_sprachen_' . $id)) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $id)) return;

    foreach (sz_sprachen_verfuegbar() as $code => $s) {
        if ($code === sz_sprache_standard()) continue;

        foreach (['name' => 'sanitize_text_field', 'kurz' => 'sanitize_textarea_field'] as $feld => $saeubern) {
            $schluessel = 'sz_' . $feld . '_' . $code;
            if (!isset($_POST[$schluessel])) continue;

            $wert = $saeubern(wp_unslash($_POST[$schluessel]));
            if ($wert === '') {
                delete_post_meta($id, '_sz_' . $feld . '_' . $code);
            } else {
                update_post_meta($id, '_sz_' . $feld . '_' . $code, $wert);
            }
        }
    }
});

/* ===================================================================
   Pflege im Backend: Kategorien
   =================================================================== */

add_action('product_cat_edit_form_fields', function (WP_Term $begriff) {
    wp_nonce_field('sz_sprachen_begriff_' . $begriff->term_id, 'sz_sprachen_begriff_nonce');

    foreach (sz_sprachen_verfuegbar() as $code => $s) {
        if ($code === sz_sprache_standard()) continue;
        $wert = (string) get_term_meta($begriff->term_id, '_sz_name_' . $code, true);
        $netz = sz_kategorie_wort($begriff->name, $code);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="sz-begriff-<?php echo esc_attr($code); ?>">
                    <?php echo esc_html(sprintf(
                        /* translators: %s ist der Name der Sprache. */
                        __('Name (%s)', 'sapelza-shop'),
                        $s['name']
                    )); ?>
                </label>
            </th>
            <td>
                <input type="text" id="sz-begriff-<?php echo esc_attr($code); ?>"
                       name="sz_name_<?php echo esc_attr($code); ?>" value="<?php echo esc_attr($wert); ?>"
                       placeholder="<?php echo esc_attr($netz); ?>">
                <?php if ($netz !== '') : ?>
                    <p class="description">
                        <?php echo esc_html(sprintf(
                            /* translators: %s ist die eingebaute Übersetzung. */
                            __('Leer heißt „%s" — die eingebaute Übersetzung.', 'sapelza-shop'),
                            $netz
                        )); ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
});

add_action('edited_product_cat', function ($id) {
    if (!isset($_POST['sz_sprachen_begriff_nonce'])) return;
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sz_sprachen_begriff_nonce'])), 'sz_sprachen_begriff_' . $id)) return;
    if (!current_user_can('manage_product_terms')) return;

    foreach (sz_sprachen_verfuegbar() as $code => $s) {
        if ($code === sz_sprache_standard()) continue;
        $schluessel = 'sz_name_' . $code;
        if (!isset($_POST[$schluessel])) continue;

        $wert = sanitize_text_field(wp_unslash($_POST[$schluessel]));
        if ($wert === '') {
            delete_term_meta($id, '_sz_name_' . $code);
        } else {
            update_term_meta($id, '_sz_name_' . $code, $wert);
        }
    }
});

/* ===================================================================
   Einspielen: die Übersetzungen aus der Datei
   ===================================================================

   daten/uebersetzungen.json liegt im Plugin, je SKU die Felder je
   Sprache. Beim Bau kamen die italienischen Texte aus dem Katalog des
   Lieferanten; weitere lassen sich in derselben Datei nachtragen.

   Der Knopf schreibt nur, was fehlt. Was am Artikel schon steht — von
   Hand eingetragen oder von einem früheren Lauf —, bleibt.
   =================================================================== */

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=product',
        __('Übersetzungen einspielen', 'sapelza-shop'),
        __('Übersetzungen einspielen', 'sapelza-shop'),
        'manage_woocommerce',
        SZ_SPRACHE_SEITE,
        'sz_sprachen_seite'
    );
});

/** Die Datei lesen; leer, wenn sie fehlt oder kaputt ist. */
function sz_uebersetzungen_datei(): array
{
    $pfad = SZ_SHOP_PFAD . 'daten/uebersetzungen.json';
    if (!file_exists($pfad)) return [];

    $roh = json_decode((string) file_get_contents($pfad), true);
    return is_array($roh) ? $roh : [];
}

/**
 * Durchgehen und zählen — oder schreiben, wenn $schreiben.
 *
 * @return array{eintraege:int, gefunden:int, geschrieben:int, uebersprungen:int, unbekannt:string[]}
 */
function sz_uebersetzungen_einspielen(bool $schreiben): array
{
    $datei = sz_uebersetzungen_datei();
    $aus = ['eintraege' => count($datei), 'gefunden' => 0, 'geschrieben' => 0, 'uebersprungen' => 0, 'unbekannt' => []];

    foreach ($datei as $sku => $sprachen) {
        $id = wc_get_product_id_by_sku((string) $sku);
        if (!$id) { $aus['unbekannt'][] = (string) $sku; continue; }
        $aus['gefunden']++;

        if (!is_array($sprachen)) continue;
        foreach ($sprachen as $code => $felder) {
            if (!isset(sz_sprachen_verfuegbar()[$code]) || $code === sz_sprache_standard() || !is_array($felder)) continue;

            foreach (['name', 'kurz'] as $feld) {
                $wert = isset($felder[$feld]) ? trim((string) $felder[$feld]) : '';
                if ($wert === '') continue;

                $schluessel = '_sz_' . $feld . '_' . $code;
                $vorhanden = get_post_meta($id, $schluessel, true);
                if (is_string($vorhanden) && trim($vorhanden) !== '') { $aus['uebersprungen']++; continue; }

                if ($schreiben) update_post_meta($id, $schluessel, $wert);
                $aus['geschrieben']++;
            }
        }
    }

    return $aus;
}

/** Die Seite unter Produkte → Übersetzungen einspielen. */
function sz_sprachen_seite(): void
{
    if (!current_user_can('manage_woocommerce')) return;

    $ergebnis = null;
    if (isset($_POST['sz_uebersetzungen_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sz_uebersetzungen_nonce'])), 'sz_uebersetzungen')) {
        $ergebnis = sz_uebersetzungen_einspielen(true);
    }

    $vorschau = sz_uebersetzungen_einspielen(false);
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Übersetzungen einspielen', 'sapelza-shop'); ?></h1>

        <?php if ($ergebnis) : ?>
            <div class="notice notice-success"><p>
                <?php echo esc_html(sprintf(
                    /* translators: 1: geschriebene Felder, 2: Artikel. */
                    __('%1$d Felder an %2$d Artikeln eingetragen.', 'sapelza-shop'),
                    $ergebnis['geschrieben'],
                    $ergebnis['gefunden']
                )); ?>
            </p></div>
        <?php endif; ?>

        <p>
            <?php echo esc_html__('Die Datei daten/uebersetzungen.json im Plugin enthält je Artikelnummer die italienischen und englischen Texte. Dieser Knopf trägt ein, was am Artikel noch fehlt — Felder, die schon etwas enthalten, bleiben unberührt. Er lässt sich also gefahrlos wiederholen, etwa nach einem Plugin-Update mit neuen Texten.', 'sapelza-shop'); ?>
        </p>

        <table class="widefat striped" style="max-width: 40em;">
            <tbody>
                <tr><td><?php echo esc_html__('Einträge in der Datei', 'sapelza-shop'); ?></td><td><strong><?php echo esc_html((string) $vorschau['eintraege']); ?></strong></td></tr>
                <tr><td><?php echo esc_html__('davon als Artikel gefunden', 'sapelza-shop'); ?></td><td><strong><?php echo esc_html((string) $vorschau['gefunden']); ?></strong></td></tr>
                <tr><td><?php echo esc_html__('Felder, die noch fehlen', 'sapelza-shop'); ?></td><td><strong><?php echo esc_html((string) $vorschau['geschrieben']); ?></strong></td></tr>
                <tr><td><?php echo esc_html__('Felder, die schon belegt sind', 'sapelza-shop'); ?></td><td><?php echo esc_html((string) $vorschau['uebersprungen']); ?></td></tr>
            </tbody>
        </table>

        <?php if ($vorschau['unbekannt']) : ?>
            <p class="description" style="margin-top: 1em;">
                <?php echo esc_html(sprintf(
                    /* translators: 1: Anzahl, 2: Beispiele. */
                    __('%1$d Artikelnummern aus der Datei gibt es im Shop nicht (zum Beispiel %2$s).', 'sapelza-shop'),
                    count($vorschau['unbekannt']),
                    implode(', ', array_slice($vorschau['unbekannt'], 0, 5))
                )); ?>
            </p>
        <?php endif; ?>

        <form method="post" style="margin-top: 1.5em;">
            <?php wp_nonce_field('sz_uebersetzungen', 'sz_uebersetzungen_nonce'); ?>
            <?php submit_button(__('Fehlende Übersetzungen eintragen', 'sapelza-shop'), 'primary', 'submit', false, $vorschau['geschrieben'] ? [] : ['disabled' => 'disabled']); ?>
        </form>

        <h2 style="margin-top: 2em;"><?php echo esc_html__('Damit WooCommerce mitspricht', 'sapelza-shop'); ?></h2>
        <p>
            <?php echo esc_html__('Warenkorb, Kasse und Konto übersetzt WooCommerce selbst — sobald die Sprachpakete installiert sind. Das geschieht unter Einstellungen → Allgemein → Sprache der Website: einmal „Italiano" wählen und speichern, einmal „English (UK)" wählen und speichern, dann zurück auf Deutsch. WordPress lädt bei jedem Wechsel die Pakete für alle Plugins nach und behält sie.', 'sapelza-shop'); ?>
        </p>
    </div>
    <?php
}
