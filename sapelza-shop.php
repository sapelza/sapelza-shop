<?php
/**
 * Plugin Name:       SAPELZA Shop
 * Plugin URI:        https://sapelzashop.com
 * GitHub Plugin URI: https://github.com/sapelza/sapelza-shop
 * Description:       Die Regeln des Betriebs, „Meine Artikel“ und der Wunschtermin. Bewusst kein Theme-Bestandteil: das hier muss einen Theme-Wechsel überleben.
 * Version:           1.1.0
 * Requires PHP:      8.0
 * Author:            SAPELZA
 * Text Domain:       sapelza-shop
 */

if (!defined('ABSPATH')) exit;

define('SZ_SHOP_PFAD', plugin_dir_path(__FILE__));

/*
 * Warum plugins_loaded und nicht sofort:
 *
 * Die Bausteine hängen durchweg an WooCommerce-Haken. Ist Woo nicht da,
 * greifen sie ins Leere — und der Betreiber sucht den Fehler an der
 * falschen Stelle. Deshalb wird einmal geprüft und im Zweifel deutlich
 * gemeldet, statt still nichts zu tun.
 *
 * Priorität 5, damit die Haken stehen, bevor WooCommerce bei der
 * Standardpriorität 10 seine eigenen Dinge aufbaut.
 */
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
               . esc_html__('SAPELZA Shop braucht WooCommerce. Das Plugin bleibt sonst wirkungslos.', 'sapelza-shop')
               . '</p></div>';
        });
        return;
    }

    foreach (['shop-regeln', 'meine-artikel', 'wunschtermin'] as $teil) {
        $pfad = SZ_SHOP_PFAD . 'inc/' . $teil . '.php';
        if (file_exists($pfad)) require_once $pfad;
    }
}, 5);
