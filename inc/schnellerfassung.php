<?php
/**
 * Die Schnellerfassung.
 *
 * Artikelnummer oder EAN eintippen, Tab, nächste Zeile. Eine Bestellung
 * über vierzig Positionen entsteht so in einem Zug, ohne einen einzigen
 * Klick im Katalog.
 *
 * Liegt im Plugin und nicht im Theme, weil die Artikelsuche Preise
 * ausliefert: B2BKings Preisgruppen und Staffeln müssen greifen, und das
 * tun sie nur, wenn wir über WooCommerces eigene Wege gehen
 * ($product->get_price(), is_purchasable()) statt über die Datenbank.
 *
 * Eingebunden als Kurzcode [sz_schnellerfassung] — so braucht es keine
 * Seitenvorlage und der Baustein lässt sich überall setzen.
 */

if (!defined('ABSPATH')) exit;

/**
 * Einen Artikel über Artikelnummer oder EAN finden.
 *
 * Gesucht wird in dieser Reihenfolge: SKU (auch von Varianten), dann die
 * EAN-Felder. Die erste Übereinstimmung gewinnt.
 */
function sz_artikel_finden(string $eingabe): ?WC_Product
{
    $eingabe = trim($eingabe);
    if ($eingabe === '') return null;

    /* Artikelnummer — findet auch Varianten. */
    $id = wc_get_product_id_by_sku($eingabe);

    if (!$id) {
        /*
         * EAN. WooCommerce führt sie seit 9.x als _global_unique_id,
         * ältere Bestände oft als _ean. Beide werden abgefragt.
         */
        $treffer = get_posts([
            'post_type'      => ['product', 'product_variation'],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => [
                'relation' => 'OR',
                ['key' => '_global_unique_id', 'value' => $eingabe],
                ['key' => '_ean', 'value' => $eingabe],
            ],
        ]);
        $id = $treffer ? (int) $treffer[0] : 0;
    }

    if (!$id && function_exists('sz_namen_suchen')) {
        /*
         * Zuletzt der eigene Name. Ohne das waere er die halbe Miete
         * wert: wer "Spueli" tippt, muss den Artikel finden.
         */
        $eigene = sz_namen_suchen($eingabe);
        $id = $eigene ? (int) $eigene[0] : 0;
    }

    if (!$id) return null;

    $artikel = wc_get_product($id);
    return $artikel instanceof WC_Product ? $artikel : null;
}

/**
 * Was die Erfassung über einen Artikel wissen muss.
 *
 * Der Preis kommt aus $product->get_price() — dort hat B2BKing seine
 * Preisgruppe bereits angewandt. Wer hier in die Datenbank griffe, bekäme
 * den Listenpreis und würde falsche Summen anzeigen.
 */
function sz_artikel_daten(WC_Product $artikel): array
{
    $marke = '';
    if (function_exists('sz_marken_taxonomie')) {
        $tax = sz_marken_taxonomie();
        if ($tax !== '') {
            $begriffe = get_the_terms($artikel->get_id(), $tax);
            if ($begriffe && !is_wp_error($begriffe)) $marke = $begriffe[0]->name;
        }
    }

    return [
        'id'       => $artikel->get_id(),
        'name'     => function_exists('sz_anzeigename') ? sz_anzeigename($artikel) : $artikel->get_name(),
        'katalog'  => $artikel->get_name(),
        'marke'    => $marke,
        'nummer'   => $artikel->get_sku(),
        'preis'    => (float) wc_get_price_to_display($artikel),
        'preisText'=> wp_strip_all_tags($artikel->get_price_html()),
        'bestand'  => $artikel->managing_stock() ? (int) $artikel->get_stock_quantity() : null,
        'kaufbar'  => $artikel->is_purchasable() && $artikel->is_in_stock(),
    ];
}

/* ===================================================================
   Suche und Warenkorb über AJAX
   =================================================================== */

add_action('wp_ajax_sz_suchen', 'sz_erfassung_suchen');
add_action('wp_ajax_nopriv_sz_suchen', 'sz_erfassung_suchen');

function sz_erfassung_suchen(): void
{
    check_ajax_referer('sz_erfassung');

    $eingabe = isset($_POST['nummer']) ? sanitize_text_field(wp_unslash($_POST['nummer'])) : '';
    $artikel = sz_artikel_finden($eingabe);

    if (!$artikel) {
        wp_send_json_error([
            'meldung' => __('Nicht gefunden. Nicht im Sortiment? Fragen Sie uns — wir beschaffen es.', 'sapelza-shop'),
        ]);
    }

    if (!$artikel->is_purchasable()) {
        /*
         * Ausgelaufen? Dann nicht bloss "nicht verfuegbar" sagen. Ein
         * Etikett am Regal ueberlebt den Katalog; wer es scannt, soll
         * erfahren, was an seiner Stelle steht.
         */
        $nachfolger = function_exists('sz_nachfolger') ? sz_nachfolger($artikel) : null;

        if ($nachfolger) {
            wp_send_json_error([
                'meldung' => sprintf(
                    /* translators: 1: alter Artikelname, 2: Name des Nachfolgers. */
                    __('%1$s führen wir nicht mehr. An seiner Stelle steht jetzt %2$s.', 'sapelza-shop'),
                    $artikel->get_name(),
                    $nachfolger->get_name()
                ),
                'nachfolger' => $nachfolger->get_sku(),
            ]);
        }

        wp_send_json_error([
            'meldung' => __('Für diesen Artikel sehen Sie den Preis erst nach der Anmeldung.', 'sapelza-shop'),
        ]);
    }

    wp_send_json_success(sz_artikel_daten($artikel));
}

add_action('wp_ajax_sz_erfassung_warenkorb', 'sz_erfassung_warenkorb');
add_action('wp_ajax_nopriv_sz_erfassung_warenkorb', 'sz_erfassung_warenkorb');

function sz_erfassung_warenkorb(): void
{
    check_ajax_referer('sz_erfassung');

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error(['meldung' => __('Der Warenkorb steht gerade nicht bereit.', 'sapelza-shop')]);
    }

    $zeilen = isset($_POST['zeilen']) ? json_decode(wp_unslash($_POST['zeilen']), true) : [];
    if (!is_array($zeilen) || !$zeilen) {
        wp_send_json_error(['meldung' => __('Nichts erfasst.', 'sapelza-shop')]);
    }

    $zahl = 0;
    foreach ($zeilen as $z) {
        $id    = isset($z['id']) ? (int) $z['id'] : 0;
        $menge = isset($z['menge']) ? max(1, (int) $z['menge']) : 1;
        if (!$id) continue;

        $artikel = wc_get_product($id);
        if (!$artikel instanceof WC_Product || !$artikel->is_purchasable()) continue;

        /*
         * Varianten brauchen ihre Elternnummer, sonst legt WooCommerce
         * eine leere Position an.
         */
        if ($artikel->is_type('variation')) {
            WC()->cart->add_to_cart($artikel->get_parent_id(), $menge, $id);
        } else {
            WC()->cart->add_to_cart($id, $menge);
        }
        $zahl++;
    }

    if (!$zahl) {
        wp_send_json_error(['meldung' => __('Keine Position konnte übernommen werden.', 'sapelza-shop')]);
    }

    wp_send_json_success([
        'zahl' => $zahl,
        'ziel' => wc_get_cart_url(),
    ]);
}

/* ===================================================================
   Der Baustein
   =================================================================== */

add_shortcode('sz_schnellerfassung', function () {
    ob_start();
    ?>
    <?php
    /*
     * Die Adresse der Strichcode-Bibliothek haengt am Abschnitt. Geladen
     * wird sie erst, wenn jemand auf Scannen tippt und der Browser den
     * eingebauten BarcodeDetector nicht mitbringt — also praktisch nur
     * auf iPhones. 386 kB will man nicht jedem mitschicken.
     */
    ?>
    <section class="sz-erfassung" data-sz-erfassung
             data-sz-zxing="<?php echo esc_url(plugins_url('js/zxing-browser.min.js', SZ_SHOP_PFAD . 'sapelza-shop.php')); ?>"
             data-nonce="<?php echo esc_attr(wp_create_nonce('sz_erfassung')); ?>"
             data-ziel="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">

        <div class="sz-erfassung__wege" role="tablist">
            <button type="button" class="sz-erfassung__weg" data-sz-weg="tippen"
                    role="tab" aria-selected="true"><?php echo esc_html__('Tippen', 'sapelza-shop'); ?></button>
            <button type="button" class="sz-erfassung__weg" data-sz-weg="scannen"
                    role="tab" aria-selected="false"><?php echo esc_html__('Scannen', 'sapelza-shop'); ?></button>
        </div>

        <?php /* --- Tippen --------------------------------------------- */ ?>
        <div class="sz-erfassung__feld" data-sz-bereich="tippen">
            <table class="sz-erfassung__tabelle">
                <thead>
                    <tr>
                        <th class="mono"><?php echo esc_html__('Art.-Nr. / EAN', 'sapelza-shop'); ?></th>
                        <th class="mono"><?php echo esc_html__('Artikel', 'sapelza-shop'); ?></th>
                        <th class="mono"><?php echo esc_html__('Bestand', 'sapelza-shop'); ?></th>
                        <th class="mono"><?php echo esc_html__('Menge', 'sapelza-shop'); ?></th>
                        <th class="mono"><?php echo esc_html__('Summe', 'sapelza-shop'); ?></th>
                        <th><span class="screen-reader-text"><?php echo esc_html__('Entfernen', 'sapelza-shop'); ?></span></th>
                    </tr>
                </thead>
                <tbody data-sz-zeilen></tbody>
            </table>

            <div class="sz-erfassung__fuss">
                <p class="sz-erfassung__hilfe">
                    <?php echo esc_html__('Eingeben und mit Tab oder Enter bestätigen — die nächste Zeile öffnet sich von selbst.', 'sapelza-shop'); ?>
                </p>
                <div class="sz-erfassung__summe">
                    <span class="mono"><?php echo esc_html__('Summe', 'sapelza-shop'); ?></span>
                    <strong class="mono" data-sz-summe><?php echo wp_kses_post(wc_price(0)); ?></strong>
                    <button type="button" class="sz-erfassung__knopf" data-sz-uebernehmen disabled>
                        <?php echo esc_html__('In den Warenkorb', 'sapelza-shop'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php /* --- Scannen -------------------------------------------- */ ?>
        <div class="sz-erfassung__feld" data-sz-bereich="scannen" hidden>
            <div class="sz-erfassung__scan">
                <div class="sz-scan__buehne">
                    <video data-sz-video playsinline muted></video>
                    <span class="sz-scan__rahmen" aria-hidden="true"></span>
                    <p class="sz-scan__hinweis" data-sz-scanhinweis><?php echo esc_html__('Barcode ins Feld halten', 'sapelza-shop'); ?></p>
                </div>
                <div class="sz-scan__text">
                    <h3 class="display"><?php echo esc_html__('Scannen statt suchen', 'sapelza-shop'); ?></h3>
                    <p><?php echo esc_html__('Barcode mit der Kamera Ihres Mobilgeräts erfassen, ebenso über die QR-Etiketten an Ihrem Lagerplatz. Sie bestellen dort, wo Ihnen der Mangel auffällt — im Lager, nicht Stunden später am Schreibtisch.', 'sapelza-shop'); ?></p>
                    <p class="sz-scan__kasten" data-sz-scanstatus></p>
                    <button type="button" class="sz-erfassung__knopf" data-sz-scanstart>
                        <?php echo esc_html__('Kamera öffnen', 'sapelza-shop'); ?>
                    </button>
                </div>
            </div>
        </div>
    </section>
    <?php
    return (string) ob_get_clean();
});

/**
 * Das Skript der Erfassung.
 *
 * Nur dort geladen, wo der Baustein auch steht.
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_singular()) return;
    $inhalt = get_post_field('post_content', get_queried_object_id());
    if (!is_string($inhalt) || !has_shortcode($inhalt, 'sz_schnellerfassung')) return;

    wp_enqueue_script(
        'sapelza-erfassung',
        plugins_url('js/erfassung.js', SZ_SHOP_PFAD . 'sapelza-shop.php'),
        [],
        '1.6.0',
        true
    );
}, 30);

/**
 * Wo die Schnellerfassung liegt.
 *
 * Gesucht wird die Seite, die den Baustein tatsächlich trägt — nicht ein
 * fest angenommener Pfad. Wer die Seite umbenennt oder verschiebt, soll
 * nicht plötzlich tote Verweise im Kopf und auf der Startseite haben.
 *
 * Das Ergebnis wird zwischengespeichert; die Seite wechselt selten.
 */
function sz_erfassung_url(): string
{
    $merker = get_transient('sz_erfassung_seite');
    if ($merker !== false) {
        return $merker ? get_permalink((int) $merker) : '';
    }

    $seiten = get_posts([
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        's'              => 'sz_schnellerfassung',
    ]);

    $id = 0;
    foreach ($seiten as $kandidat) {
        $inhalt = get_post_field('post_content', $kandidat);
        if (is_string($inhalt) && has_shortcode($inhalt, 'sz_schnellerfassung')) {
            $id = (int) $kandidat;
            break;
        }
    }

    set_transient('sz_erfassung_seite', $id, DAY_IN_SECONDS);
    return $id ? get_permalink($id) : '';
}

/* Beim Speichern einer Seite den Merker verwerfen — sonst zeigt der
   Verweis noch einen Tag lang auf die alte Seite. */
add_action('save_post_page', fn() => delete_transient('sz_erfassung_seite'));
