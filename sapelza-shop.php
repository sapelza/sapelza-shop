<?php
/**
 * Plugin Name:       SAPELZA Shop-Logik
 * Plugin URI:        https://sapelzashop.com
 * GitHub Plugin URI: https://github.com/sapelza/sapelza-shop
 * Description:       Die Regeln des Betriebs, „Meine Artikel“ und der Wunschtermin. Bewusst kein Theme-Bestandteil: das hier muss einen Theme-Wechsel überleben.
 * Version:           1.9.0
 * Requires PHP:      8.0
 * Author:            SAPELZA
 * Text Domain:       sapelza-shop
 */

if (!defined('ABSPATH')) exit;

define('SZ_SHOP_PFAD', plugin_dir_path(__FILE__));

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

    foreach (['shop-regeln', 'meine-artikel', 'wunschtermin', 'artikelnamen', 'schnellerfassung'] as $teil) {
        $pfad = SZ_SHOP_PFAD . 'inc/' . $teil . '.php';
        if (file_exists($pfad)) require_once $pfad;
    }
}, 5);
