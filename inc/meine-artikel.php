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
    ?>
    <div class="sz-etiketten-leiste">
        <label class="sz-etiketten-alle">
            <input type="checkbox" data-sz-alle>
            <?php echo esc_html__('Alle wählen', 'sapelza-shop'); ?>
        </label>
        <span class="sz-etiketten-zahl mono" data-sz-gewaehlt>0 gewählt</span>
        <label class="sz-etiketten-groesse">
            <span class="mono"><?php echo esc_html__('Etikett', 'sapelza-shop'); ?></span>
            <select data-sz-groesse>
                <option value="70x37"><?php echo esc_html__('70 × 37 mm · 24 je Bogen · Regalkante', 'sapelza-shop'); ?></option>
                <option value="48x25"><?php echo esc_html__('48 × 25 mm · 44 je Bogen · Kanister', 'sapelza-shop'); ?></option>
            </select>
        </label>
        <button type="button" class="sz-erfassung__knopf" data-sz-drucken disabled>
            <?php echo esc_html__('Etiketten drucken', 'sapelza-shop'); ?>
        </button>
    </div>

    <div class="sz-meine-artikel" data-sz-namen
         data-basis="<?php echo esc_url(function_exists('sz_erfassung_url') ? sz_erfassung_url() : home_url('/schnellerfassung/')); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('sz_namen')); ?>"
         data-ziel="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
    <?php
    foreach ($artikel as $eintrag) {
        $produkt = wc_get_product($eintrag['id']);
        if (!$produkt || !$produkt->is_purchasable()) continue;

        // Die Schleifenvorlage von WooCommerce liest $GLOBALS["product"].
        $GLOBALS["product"] = $produkt;

        $vorrat = $produkt->managing_stock() ? (int) $produkt->get_stock_quantity() : null;
        $eigen  = function_exists('sz_eigener_name') ? sz_eigener_name($produkt->get_id()) : '';
        $wer    = ($eigen !== '' && function_exists('sz_name_geaendert_von'))
                ? sz_name_geaendert_von($produkt->get_id()) : '';
        ?>
        <div class="sz-artikelzeile" data-sz-artikel="<?php echo esc_attr((string) $produkt->get_id()); ?>">

            <label class="sz-artikelwahl">
                <input type="checkbox" data-sz-wahl
                       value="<?php echo esc_attr((string) $produkt->get_sku()); ?>">
                <span class="screen-reader-text"><?php echo esc_html__('Für Etiketten wählen', 'sapelza-shop'); ?></span>
            </label>

            <div class="sz-artikelzeile__namen">
                <?php
                /*
                 * Der eigene Name IST die Schaltflaeche. Klick, Feld oeffnet
                 * sich an Ort und Stelle, Enter speichert, Escape verwirft.
                 * Kein Fenster, kein Speichern-Knopf, keine Bearbeiten-Ansicht.
                 */
                ?>
                <button type="button" class="sz-eigenname<?php echo $eigen === '' ? ' ist-leer' : ''; ?>"
                        data-sz-eigenname
                        title="<?php echo esc_attr__('Eigenen Namen vergeben oder aendern', 'sapelza-shop'); ?>">
                    <?php echo $eigen !== ''
                        ? esc_html($eigen)
                        : esc_html__('Eigenen Namen vergeben', 'sapelza-shop'); ?>
                </button>

                <a class="sz-katalogname" href="<?php echo esc_url($produkt->get_permalink()); ?>">
                    <?php echo esc_html($produkt->get_name()); ?>
                    <span class="sz-artikelnummer mono"><?php echo esc_html($produkt->get_sku()); ?></span>
                </a>

                <?php if ($wer !== '') : ?>
                    <span class="sz-eigenname__wer">
                        <?php
                        printf(
                            /* translators: %s ist der Name der Person, die zuletzt umbenannt hat. */
                            esc_html__('zuletzt geaendert von %s', 'sapelza-shop'),
                            esc_html($wer)
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <span class="sz-artikelmeta">
                <?php
                printf(
                    /* translators: %s ist ein Datum. */
                    esc_html__('zuletzt %s', 'sapelza-shop'),
                    esc_html(date_i18n('j. F Y', $eintrag['zuletzt']))
                );
                ?>
            </span>

            <span class="sz-artikelmeta">
                <?php echo $vorrat === null ? '' : esc_html($vorrat . ' Stueck auf Lager'); ?>
            </span>

            <span class="sz-artikelpreis"><?php echo wp_kses_post($produkt->get_price_html()); ?></span>

            <button type="button" class="sz-qr-knopf" data-sz-qr
                    aria-label="<?php echo esc_attr__('QR-Code zeigen', 'sapelza-shop'); ?>">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M3 3h7v7H3V3zm2 2v3h3V5H5zm9-2h7v7h-7V3zm2 2v3h3V5h-3zM3 14h7v7H3v-7zm2 2v3h3v-3H5zm9-2h3v3h-3v-3zm5 0h2v2h-2v-2zm-5 5h3v2h-3v-2zm5 0h2v2h-2v-2z"/>
                </svg>
            </button>

            <?php woocommerce_template_loop_add_to_cart(['quantity' => 1]); ?>
        </div>
        <?php
    }
    ?>
    </div>
    <?php
    return ob_get_clean();
});
