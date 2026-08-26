<?php
/**
 * Die Regeln des Betriebs, so wie sie festgelegt wurden.
 *
 * Nichts hier ist Gestaltung — es sind Entscheidungen: es gibt keine
 * Gutscheine, es gibt nur die Zustellung im Hochpustertal, und bezahlt
 * wird auf Rechnung oder per Vorkasse.
 */

if (!defined('ABSPATH')) exit;

/**
 * Keine Gutscheine.
 *
 * Das CSS blendet die Felder aus, hier verschwinden sie wirklich: das
 * Formular wird nicht mehr gerendert, die Store-API nimmt keinen Code
 * mehr an, und niemand kann einen über die Adresszeile einlösen.
 */
add_filter('woocommerce_coupons_enabled', '__return_false');

/**
 * Zahlarten: auf Rechnung und Vorkasse per Überweisung.
 *
 * Die Bezeichner stammen aus der Store-API dieser Installation:
 * b2bking-invoice-gateway von B2BKing, bacs ist WooCommerces
 * Überweisung. Nur im Frontend — im Backend bleiben alle verwaltbar.
 */
add_filter('woocommerce_available_payment_gateways', function ($gateways) {
    if (is_admin()) return $gateways;
    return array_intersect_key($gateways, array_flip(['b2bking-invoice-gateway', 'bacs']));
});

/**
 * Zustellung statt Versand.
 *
 * Es gibt genau eine Zone. Eine Auswahlliste mit einem Eintrag ist keine
 * Auswahl, sondern eine Angabe — deshalb bekommt sie einen Text, der das
 * sagt, statt einer leeren Entscheidung.
 */
add_filter('woocommerce_cart_shipping_method_full_label', function ($label, $method) {
    if (strpos($method->get_id(), 'local_pickup') === 0) return $label;
    return $label . ' <span class="sz-zustellhinweis">im Hochpustertal</span>';
}, 10, 2);

/**
 * Abholung im Geschäft gibt es nicht.
 *
 * Falls in einer Zone doch eine Abholmethode aktiv ist, fällt sie hier
 * heraus, statt dem Kunden eine Möglichkeit anzubieten, die keine ist.
 */
add_filter('woocommerce_package_rates', function ($rates) {
    foreach ($rates as $id => $rate) {
        if (strpos($rate->get_method_id(), 'local_pickup') === 0) unset($rates[$id]);
    }
    return $rates;
}, 10);

/**
 * Echte Stückzahl statt „vorrätig".
 *
 * Ein Betrieb, der vierzig Positionen bestellt, muss wissen, ob zwölf
 * oder zweihundert da sind. WooCommerce kann das, es muss nur an.
 */
add_filter('woocommerce_get_availability_text', function ($text, $product) {
    if (!$product->managing_stock()) return $text;
    $anzahl = $product->get_stock_quantity();
    if ($anzahl === null) return $text;
    if ($anzahl <= 0) return 'nicht auf Lager';
    return sprintf('%d %s auf Lager', $anzahl, $anzahl === 1 ? 'Stück' : 'Stück');
}, 10, 2);

/**
 * Die gültigen Bereiche — die Oberkategorien des Katalogs.
 *
 * Früher standen hier fest 'handwerk' und 'gastro'. Beide entsprachen
 * keiner Kategorie, weshalb sz_bereich() immer leer zurückkam. Jetzt gilt,
 * was der Katalog tatsächlich führt: kommt ein Handwerk-Sortiment als
 * Oberkategorie dazu, ist es ohne Codeänderung wählbar.
 *
 * Bewusst eine eigene kleine Abfrage statt eines Aufrufs ins Theme: das
 * Plugin darf nicht vom Theme abhängen.
 *
 * @return string[] Slugs, größter Bereich zuerst.
 */
function sz_bereich_slugs(): array
{
    if (!taxonomy_exists('product_cat')) return [];

    $begriffe = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => 0,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
        'fields'     => 'slugs',
    ]);

    return is_wp_error($begriffe) ? [] : $begriffe;
}

/**
 * Der Bereich des Betriebs.
 *
 * Der Handwerker braucht keinen Teller, der Hotelier nicht wöchentlich
 * einen Bohrer. Die Wahl wird am Benutzer gemerkt; die Sortimentsseite
 * liest sie aus.
 *
 * Ohne getroffene Wahl gilt der größte Bereich — nicht leer, denn eine
 * Sortimentsseite ohne ausgewählten Bereich hätte nichts zu zeigen.
 */
function sz_bereich(): string
{
    $erlaubt = sz_bereich_slugs();
    if (!$erlaubt) return '';

    if (isset($_GET['bereich'])) {
        $wahl = sanitize_key(wp_unslash($_GET['bereich']));
        if (in_array($wahl, $erlaubt, true)) {
            if (is_user_logged_in()) update_user_meta(get_current_user_id(), 'sz_bereich', $wahl);
            return $wahl;
        }
    }

    if (is_user_logged_in()) {
        $wahl = get_user_meta(get_current_user_id(), 'sz_bereich', true);
        if (in_array($wahl, $erlaubt, true)) return $wahl;
    }

    return $erlaubt[0];
}
