<?php
/**
 * „Meine Artikel" — der betriebseigene Katalog.
 *
 * Kein Vorschlagswesen: die Liste zeigt ausschließlich, was der Betrieb
 * schon einmal bezogen hat, und sie steht nur da, wo der Kunde sie selbst
 * aufruft. Keine E-Mails, keine Einblendungen im Warenkorb.
 *
 * Einsatz: eine Seite anlegen und [sz_meine_artikel] hineinschreiben.
 */

if (!defined('ABSPATH')) exit;

/**
 * Alle je bezogenen Produkte des angemeldeten Kunden, neueste zuerst.
 *
 * @return array<int, array{id:int, anzahl:int, zuletzt:int}>
 */
function sz_bezogene_artikel(int $grenze = 200): array
{
    if (!is_user_logged_in()) return [];

    $bestellungen = wc_get_orders([
        'customer_id' => get_current_user_id(),
        'limit'       => 60,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'status'      => ['wc-processing', 'wc-completed', 'wc-on-hold'],
    ]);

    $artikel = [];
    foreach ($bestellungen as $bestellung) {
        $wann = $bestellung->get_date_created() ? $bestellung->get_date_created()->getTimestamp() : 0;
        foreach ($bestellung->get_items() as $posten) {
            $id = $posten->get_variation_id() ?: $posten->get_product_id();
            if (!$id) continue;
            if (!isset($artikel[$id])) {
                $artikel[$id] = ['id' => $id, 'anzahl' => 0, 'zuletzt' => $wann];
            }
            $artikel[$id]['anzahl'] += (int) $posten->get_quantity();
            $artikel[$id]['zuletzt'] = max($artikel[$id]['zuletzt'], $wann);
        }
    }

    uasort($artikel, fn($a, $b) => $b['zuletzt'] <=> $a['zuletzt']);
    return array_slice($artikel, 0, $grenze, true);
}

add_shortcode('sz_meine_artikel', function () {
    if (!is_user_logged_in()) {
        return '<p class="sz-hinweis">Bitte melden Sie sich an, um Ihre bisher bezogenen Artikel zu sehen.</p>';
    }

    $artikel = sz_bezogene_artikel();
    if (!$artikel) {
        return '<p class="sz-hinweis">Hier stehen die Artikel, die Sie schon einmal bezogen haben. '
             . 'Sobald die erste Bestellung ausgeliefert ist, füllt sich die Liste von selbst.</p>';
    }

    ob_start();
    echo '<div class="sz-meine-artikel">';
    foreach ($artikel as $eintrag) {
        $produkt = wc_get_product($eintrag['id']);
        if (!$produkt || !$produkt->is_purchasable()) continue;

        // Die Schleifenvorlage von WooCommerce liest $GLOBALS["product"].
        $GLOBALS["product"] = $produkt;
        $vorrat = $produkt->managing_stock() ? (int) $produkt->get_stock_quantity() : null;
        ?>
        <div class="sz-artikelzeile">
            <a class="sz-artikelname" href="<?php echo esc_url($produkt->get_permalink()); ?>">
                <?php echo esc_html($produkt->get_name()); ?>
                <span class="sz-artikelnummer"><?php echo esc_html($produkt->get_sku()); ?></span>
            </a>
            <span class="sz-artikelmeta">
                zuletzt <?php echo esc_html(date_i18n('j. F Y', $eintrag['zuletzt'])); ?>
            </span>
            <span class="sz-artikelmeta">
                <?php echo $vorrat === null ? '' : esc_html($vorrat . ' Stück auf Lager'); ?>
            </span>
            <span class="sz-artikelpreis"><?php echo wp_kses_post($produkt->get_price_html()); ?></span>
            <?php woocommerce_template_loop_add_to_cart(['quantity' => 1]); ?>
        </div>
        <?php
    }
    echo '</div>';
    return ob_get_clean();
});
