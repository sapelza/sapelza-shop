<?php
/**
 * Plugin Name:       SAPELZA Shop-Logik
 * Plugin URI:        https://sapelzashop.com
 * GitHub Plugin URI: https://github.com/sapelza/sapelza-shop
 * Description:       Die Regeln des Betriebs, „Meine Artikel“ und der Wunschtermin. Bewusst kein Theme-Bestandteil: das hier muss einen Theme-Wechsel überleben.
 * Version:           1.26.0
 * Requires PHP:      8.0
 * Author:            SAPELZA
 * Text Domain:       sapelza-shop
 */

if (!defined('ABSPATH')) exit;

define('SZ_SHOP_PFAD', plugin_dir_path(__FILE__));

/* ===================================================================
   Die Sprache — so früh wie möglich
   ===================================================================

   WordPress lädt seine eigenen Texte (und baut Monats- und Tagesnamen)
   nach plugins_loaded, aber vor after_setup_theme. Was das Gebietsschema
   umschalten will, muss deshalb hier stehen, beim Laden der Datei —
   nicht in inc/sprache.php, das erst auf after_setup_theme dazukommt.

   Hier steht nur, was dafür nötig ist: welche Sprachen es gibt, welche
   gewählt ist, und der Filter aufs Gebietsschema. Alles Weitere —
   Cookie schreiben, Felder ausgeben, Pflege im Backend — in
   inc/sprache.php.
   =================================================================== */

const SZ_SPRACHE_COOKIE = 'sz_sprache';

/**
 * Die drei Sprachen des Hauses. Deutsch ist die Sprache der
 * Installation und braucht kein eigenes Gebietsschema.
 *
 * @return array<string, array{name:string, locale:string}>
 */
function sz_sprachen_verfuegbar(): array
{
    return [
        'de' => ['name' => 'Deutsch',  'locale' => ''],
        'it' => ['name' => 'Italiano', 'locale' => 'it_IT'],
        'en' => ['name' => 'English',  'locale' => 'en_GB'],
    ];
}

function sz_sprache_standard(): string
{
    return 'de';
}

/**
 * Die gewählte Sprache: erst die Adresse (?lang=it), dann das Cookie,
 * sonst Deutsch. Nur bekannte Werte gelten; alles andere ist Deutsch.
 *
 * Bewusst ohne Nutzerkonto: diese Funktion läuft, bevor WordPress den
 * angemeldeten Nutzer kennt. Das Konto kommt beim Anmelden ins Cookie.
 */
function sz_sprache(): string
{
    $roh = '';
    if (isset($_GET['lang'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $roh = (string) wp_unslash($_GET['lang']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    } elseif (isset($_COOKIE[SZ_SPRACHE_COOKIE])) {
        $roh = (string) wp_unslash($_COOKIE[SZ_SPRACHE_COOKIE]);
    }

    return isset(sz_sprachen_verfuegbar()[$roh]) ? $roh : sz_sprache_standard();
}

/**
 * Das Gebietsschema zur gewählten Sprache — leer für die Standardsprache.
 *
 * Das Backend folgt dem eigenen Profil, nicht dem Shop-Cookie: wer im
 * Shop Italienisch gewählt hat, soll die Artikelliste trotzdem deutsch
 * sehen. Ajax-Aufrufe aus dem Shop gehören aber zum Shop.
 */
function sz_sprache_locale(): string
{
    if (is_admin() && !wp_doing_ajax()) return '';

    $wahl = sz_sprache();
    if ($wahl === sz_sprache_standard()) return '';

    return sz_sprachen_verfuegbar()[$wahl]['locale'] ?? '';
}

/* Beide Filter: get_locale() hört auf den einen, determine_locale() auf
   den anderen — und beide werden benutzt, je nach Alter des Aufrufers. */
foreach (['locale', 'determine_locale'] as $sz_haken) {
    add_filter($sz_haken, function ($locale) {
        $eigen = sz_sprache_locale();
        return $eigen !== '' ? $eigen : $locale;
    });
}
unset($sz_haken);

/*
 * Die Sprachdatei des Plugins: languages/sapelza-shop-<locale>.mo, dort,
 * wo WordPress sie für ein Plugin sucht. Bis 1.24.0 wurde keine geladen —
 * 153 übersetzbare Texte, aber nie eine Übersetzung.
 *
 * Theme und Plugin teilen sich die Domain; WordPress legt beide Dateien
 * übereinander. Auf init reicht es: die Texte werden beim Ausgeben
 * übersetzt, nicht beim Einhängen der Bausteine.
 */
add_action('init', function () {
    load_plugin_textdomain('sapelza-shop', false, dirname(plugin_basename(__FILE__)) . '/languages');
}, 1);

/*
 * Warum after_setup_theme und nicht plugins_loaded:
 *
 * WordPress laedt Plugins VOR Themes. Auf plugins_loaded hat die
 * functions.php des Themes noch nicht gelaufen — eine Pruefung mit
 * function_exists() liefe dort ins Leere, das Plugin wuerde laden und
 * danach kaeme der Fatal aus dem Theme. Auf after_setup_theme ist die
 * functions.php durch, und die Pruefung unten greift wirklich.
 *
 * Frueh genug ist es allemal: init hat noch nicht gefeuert.
 *
 *
 * Die Bausteine hängen durchweg an WooCommerce-Haken. Ist Woo nicht da,
 * greifen sie ins Leere — und der Betreiber sucht den Fehler an der
 * falschen Stelle. Deshalb wird einmal geprüft und im Zweifel deutlich
 * gemeldet, statt still nichts zu tun.
 *
 * Priorität 5, damit die Haken stehen, bevor WooCommerce bei der
 * Standardpriorität 10 seine eigenen Dinge aufbaut.
 */
add_action('after_setup_theme', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
               . esc_html__('SAPELZA Shop braucht WooCommerce. Das Plugin bleibt sonst wirkungslos.', 'sapelza-shop')
               . '</p></div>';
        });
        return;
    }

    /*
     * Erst pruefen, ob das Theme dieselben Bausteine noch mitbringt.
     *
     * Bis Fassung 1.4.0 lagen shop-regeln, meine-artikel und wunschtermin
     * im Child-Theme. Wer dieses Plugin neben einem alten Theme aktiviert,
     * deklariert jede Funktion zweimal — PHP bricht dann hart ab und die
     * Seite ist weg. Genau das ist am 27.08.2026 auf der Live-Seite
     * passiert.
     *
     * Ein Plugin darf eine Seite nicht lahmlegen. Also lieber untaetig
     * bleiben und deutlich sagen, was zu tun ist.
     */
    $doppelt = array_filter(
        ['sz_bereich', 'sz_liefertage', 'sz_termin_gewaehlt', 'sz_bezogene_artikel'],
        'function_exists'
    );

    if ($doppelt) {
        add_action('admin_notices', function () use ($doppelt) {
            echo '<div class="notice notice-error"><p><strong>'
               . esc_html__('SAPELZA Shop-Logik wurde nicht geladen.', 'sapelza-shop')
               . '</strong><br>'
               . esc_html(
                   sprintf(
                       /* translators: %s ist eine Liste von PHP-Funktionsnamen. */
                       __('Das aktive Theme bringt dieselben Bausteine noch selbst mit (%s). Bitte zuerst das Child-Theme auf Fassung 1.5.0 oder neuer aktualisieren, dann laedt sich dieses Plugin von allein.', 'sapelza-shop'),
                       implode(', ', $doppelt)
                   )
               )
               . '</p></div>';
        });
        return;
    }

    foreach (['shop-regeln', 'meine-artikel', 'wunschtermin', 'artikelnamen', 'schnellerfassung', 'favoriten', 'konto', 'marken', 'app', 'rechtstexte', 'rechtsseiten', 'sprache'] as $teil) {
        $pfad = SZ_SHOP_PFAD . 'inc/' . $teil . '.php';
        if (file_exists($pfad)) require_once $pfad;
    }
}, 5);
