<?php

/**
 * Favoriten.
 *
 * Ein Stern an jeder Kachel und am Artikel. Was der Betrieb sich merkt,
 * steht danach im Konto und in Meine Artikel.
 *
 * Sie gehoeren dem BETRIEB, nicht der Person — genau wie die eigenen
 * Artikelnamen. Wenn die Kuechenchefin einen Artikel merkt, findet ihn
 * die Vertretung am naechsten Tag ebenso. Ein Favorit, den nur einer
 * sieht, ist in einem Betrieb mit mehreren Zugaengen wertlos.
 *
 * @package sapelza-shop
 */

defined('ABSPATH') || exit;

const SZ_FAVORITEN_SCHLUESSEL = '_sz_favoriten';

/**
 * Die gemerkten Artikel des Betriebs.
 *
 * @return int[] Produkt-IDs, jüngste zuerst.
 */
function sz_favoriten(): array
{
    $betrieb = function_exists('sz_betrieb_id') ? sz_betrieb_id() : get_current_user_id();
    if (!$betrieb) return [];

    $liste = get_user_meta($betrieb, SZ_FAVORITEN_SCHLUESSEL, true);
    if (!is_array($liste)) return [];

    return array_values(array_filter(array_map('intval', $liste)));
}

/** Merkt sich einen Artikel oder vergisst ihn wieder. */
function sz_favorit_setzen(int $produkt, bool $an): array
{
    $betrieb = function_exists('sz_betrieb_id') ? sz_betrieb_id() : get_current_user_id();
    if (!$betrieb || $produkt <= 0) return sz_favoriten();

    $liste = sz_favoriten();
    $liste = array_values(array_diff($liste, [$produkt]));

    /* Neu gemerktes kommt nach vorn: zuletzt gemerkt, zuerst gesehen. */
    if ($an) array_unshift($liste, $produkt);

    update_user_meta($betrieb, SZ_FAVORITEN_SCHLUESSEL, $liste);

    return $liste;
}

/** Ist dieser Artikel gemerkt? */
function sz_ist_favorit(int $produkt): bool
{
    return in_array($produkt, sz_favoriten(), true);
}

/* ===================================================================
   Das Herz
   =================================================================== */

/**
 * Der Knopf.
 *
 * Ein Umriss, wenn nicht gemerkt; gefuellt, wenn gemerkt.
 *
 * Mit Wort, nicht ohne. Die erste Fassung sass als runde Blase oben
 * rechts im Bild und trug nur das Symbol — auf dem Schirm las sich das
 * als "ein Kreis in jeder Kachel", nicht als Knopf. Ein Herz allein
 * sagt nicht, was es tut.
 *
 * @param int    $produkt Die Produkt-ID.
 * @param string $zusatz  Zusaetzliche Klasse.
 * @param bool   $wort    Beschriftung neben dem Herz.
 */
function sz_favorit_knopf(int $produkt, string $zusatz = '', bool $wort = false): string
{
    if (!is_user_logged_in()) return '';

    $gemerkt = sz_ist_favorit($produkt);
    $sagt    = $gemerkt ? __('Gemerkt', 'sapelza-shop') : __('Merken', 'sapelza-shop');

    return sprintf(
        '<button type="button" class="sz-stern%1$s%2$s" data-sz-favorit="%3$d"'
        . ' aria-pressed="%4$s" aria-label="%5$s" title="%5$s">'
        . '<svg viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M12 2.6l2.9 5.88 6.49.94-4.7 4.58 1.11 6.46L12 17.4l-5.8 3.06 1.1-6.46-4.69-4.58 6.49-.94z"></path>'
        . '</svg>%6$s</button>',
        $gemerkt ? ' ist-gemerkt' : '',
        $zusatz !== '' ? ' ' . esc_attr($zusatz) : '',
        $produkt,
        $gemerkt ? 'true' : 'false',
        esc_attr($gemerkt ? __('Aus den Favoriten nehmen', 'sapelza-shop') : __('Zu den Favoriten', 'sapelza-shop')),
        $wort ? '<span class="sz-stern__wort" data-sz-sternwort>' . esc_html($sagt) . '</span>' : ''
    );
}

/*
 * In der Kachel unter dem Preis, mit Wort.
 *
 * Vorher schwebte er als Kreis ueber dem Bild. In einem Raster aus
 * vierundzwanzig Kacheln wurden daraus vierundzwanzig Kreise, die
 * niemand einordnen konnte.
 */
add_action('woocommerce_after_shop_loop_item_title', function () {
    global $product;
    if (!$product) return;

    echo wp_kses_post(sz_favorit_knopf($product->get_id(), 'sz-stern--zeile', true));
}, 20);

/* Am Artikel neben dem Warenkorbknopf. */
add_action('woocommerce_after_add_to_cart_button', function () {
    global $product;
    if (!$product) return;

    echo wp_kses_post(sz_favorit_knopf($product->get_id(), 'sz-stern--artikel', true));
}, 5);

/* ===================================================================
   Umschalten
   =================================================================== */

add_action('wp_ajax_sz_favorit', function () {
    check_ajax_referer('sz_favorit');

    $produkt = isset($_POST['produkt']) ? (int) $_POST['produkt'] : 0;
    $an      = isset($_POST['an']) && $_POST['an'] === '1';

    if ($produkt <= 0 || !wc_get_product($produkt)) {
        wp_send_json_error(['grund' => 'unbekannter Artikel']);
    }

    $liste = sz_favorit_setzen($produkt, $an);

    wp_send_json_success([
        'gemerkt' => in_array($produkt, $liste, true),
        'anzahl'  => count($liste),
    ]);
});

add_action('wp_enqueue_scripts', function () {
    if (!is_user_logged_in()) return;

    $pfad = SZ_SHOP_PFAD . 'sapelza-shop.php';
    $f    = SZ_NAMEN_FASSUNG;

    wp_enqueue_script('sapelza-favoriten', plugins_url('js/favoriten.js', $pfad), [], $f, true);
    wp_localize_script('sapelza-favoriten', 'szFavoriten', [
        'ziel'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('sz_favorit'),
    ]);
}, 30);

/* ===================================================================
   Nach Favoriten filtern
   ===================================================================

   Im Sortiment und in der Suche: ?favoriten=1 schraenkt die Abfrage auf
   die gemerkten Artikel ein.

   Kein Sortieren, sondern Filtern. Eine Sortierung nach Favoriten waere
   eine Sortierung nach einer Liste in den Benutzerdaten — die Datenbank
   kann danach nicht ordnen, ohne alle Artikel zu laden. Filtern kann sie,
   und es beantwortet dieselbe Frage: zeig mir, was wir uns gemerkt haben.
*/

/** Ist der Filter gerade an? */
function sz_favoritenfilter(): bool
{
    return isset($_GET['favoriten']) && $_GET['favoriten'] === '1';
}

/** Die Adresse mit oder ohne Filter. */
function sz_favoriten_adresse(bool $an): string
{
    $adresse = remove_query_arg('favoriten');

    return $an ? add_query_arg('favoriten', '1', $adresse) : $adresse;
}

add_action('pre_get_posts', function ($abfrage) {
    if (is_admin() || !$abfrage->is_main_query()) return;
    if (!sz_favoritenfilter()) return;
    if (!function_exists('is_woocommerce')) return;
    if (!$abfrage->is_post_type_archive('product') && !$abfrage->is_tax('product_cat')
        && !$abfrage->is_tax('product_tag') && !$abfrage->is_search()) return;

    $liste = sz_favoriten();

    /*
     * Ohne Favoriten muss die Abfrage leer bleiben. post__in mit einem
     * leeren Feld ignoriert WordPress — dann kaeme der ganze Katalog
     * zurueck, und der Filter zeigte das Gegenteil von dem, was er
     * verspricht. Darum eine ID, die es nicht gibt.
     */
    $abfrage->set('post__in', $liste ?: [0]);
});
