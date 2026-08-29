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

    $basis = function_exists('sz_erfassung_url') ? sz_erfassung_url() : home_url('/schnellerfassung/');

    /*
     * Erst sammeln, dann zeichnen. Nur so ist vor der Kopfzeile bekannt,
     * ob ueberhaupt ein Artikel einen Bestand fuehrt — und eine Spalte
     * voller Striche sieht aus, als fehlte etwas.
     */
    $posten       = [];
    $mit_bestand  = false;

    foreach ($artikel as $eintrag) {
        $produkt = wc_get_product($eintrag['id']);
        if (!$produkt || !$produkt->is_purchasable()) continue;

        if ($produkt->managing_stock()) $mit_bestand = true;
        $posten[] = ['produkt' => $produkt, 'zuletzt' => (int) $eintrag['zuletzt']];
    }

    if (!$posten) {
        return '<p class="sz-hinweis">Ihre bisherigen Artikel sind derzeit nicht bestellbar.</p>';
    }

    ob_start();
    ?>
    <div class="sz-artikelbereich"
         data-sz-namen
         data-nonce="<?php echo esc_attr(wp_create_nonce('sz_namen')); ?>"
         data-erfassung="<?php echo esc_attr(wp_create_nonce('sz_erfassung')); ?>"
         data-ziel="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-basis="<?php echo esc_url($basis); ?>">

        <label class="sz-artikelsuche">
            <span class="screen-reader-text"><?php echo esc_html__('In Ihren Artikeln suchen', 'sapelza-shop'); ?></span>
            <input type="search" data-sz-filter
                   placeholder="<?php echo esc_attr__('In Ihren Artikeln suchen …', 'sapelza-shop'); ?>">
        </label>

        <div class="sz-meine-artikel<?php echo $mit_bestand ? '' : ' ist-ohne-bestand'; ?>">
            <div class="sz-artikelkopf mono" aria-hidden="true">
                <span></span>
                <span><?php echo esc_html__('Ihre Bezeichnung', 'sapelza-shop'); ?></span>
                <span><?php echo esc_html__('Artikel', 'sapelza-shop'); ?></span>
                <span class="sz-spalte-bestand"><?php echo esc_html__('Bestand', 'sapelza-shop'); ?></span>
                <span><?php echo esc_html__('Preis', 'sapelza-shop'); ?></span>
                <span><?php echo esc_html__('Menge', 'sapelza-shop'); ?></span>
                <span></span>
            </div>

            <?php
            $lfd = 0;
            foreach ($posten as $eintrag) {
                $produkt = $eintrag['produkt'];

                $lfd++;
                $vorrat = $produkt->managing_stock() ? (int) $produkt->get_stock_quantity() : null;
                $eigen  = function_exists('sz_eigener_name') ? sz_eigener_name($produkt->get_id()) : '';
                $wer    = ($eigen !== '' && function_exists('sz_name_geaendert_von'))
                        ? sz_name_geaendert_von($produkt->get_id()) : '';

                $marke = '';
                if (function_exists('sz_marken_taxonomie')) {
                    $tax = sz_marken_taxonomie();
                    if ($tax !== '') {
                        $b = get_the_terms($produkt->get_id(), $tax);
                        if ($b && !is_wp_error($b)) $marke = $b[0]->name;
                    }
                }

                $tage = (int) floor((time() - $eintrag['zuletzt']) / DAY_IN_SECONDS);
                $such = mb_strtolower($eigen . ' ' . $produkt->get_name() . ' ' . $produkt->get_sku());
                ?>
                <div class="sz-artikelzeile"
                     data-sz-artikel="<?php echo esc_attr((string) $produkt->get_id()); ?>"
                     data-sz-lfd="<?php echo esc_attr((string) $lfd); ?>"
                     data-sz-suchtext="<?php echo esc_attr($such); ?>">

                    <label class="sz-artikelwahl">
                        <input type="checkbox" data-sz-wahl
                               value="<?php echo esc_attr((string) $produkt->get_sku()); ?>">
                        <span class="screen-reader-text"><?php echo esc_html__('Für Etiketten wählen', 'sapelza-shop'); ?></span>
                    </label>

                    <div class="sz-artikelzeile__namen">
                        <?php
                        /*
                         * Der eigene Name IST die Schaltflaeche. Klick, Feld
                         * oeffnet sich an Ort und Stelle, Enter speichert,
                         * Escape verwirft, leeres Feld loescht.
                         */
                        ?>
                        <button type="button" class="sz-eigenname<?php echo $eigen === '' ? ' ist-leer' : ''; ?>"
                                data-sz-eigenname>
                            <?php echo $eigen !== ''
                                ? esc_html($eigen)
                                : esc_html__('Eigenen Namen vergeben', 'sapelza-shop'); ?>
                        </button>
                        <?php if ($wer !== '') : ?>
                            <span class="sz-eigenname__wer">
                                <?php printf(esc_html__('zuletzt geändert von %s', 'sapelza-shop'), esc_html($wer)); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="sz-artikelzeile__katalog">
                        <?php if ($marke) : ?>
                            <span class="sz-artikelmarke mono"><?php echo esc_html($marke); ?></span>
                        <?php endif; ?>
                        <a class="sz-katalogname" href="<?php echo esc_url($produkt->get_permalink()); ?>">
                            <?php echo esc_html($produkt->get_name()); ?>
                        </a>
                        <span class="sz-artikelrhythmus">
                            <?php
                            printf(
                                /* translators: %d ist die Zahl der Tage seit der letzten Lieferung. */
                                esc_html(_n('letzte Lieferung vor %d Tag', 'letzte Lieferung vor %d Tagen', $tage, 'sapelza-shop')),
                                (int) $tage
                            );
                            ?>
                        </span>
                    </div>

                    <span class="sz-artikelbestand sz-spalte-bestand mono">
                        <?php if ($vorrat !== null) : ?>
                            <span class="sz-punkt sz-punkt--da" aria-hidden="true"></span><?php echo esc_html($vorrat . ' Stk.'); ?>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </span>

                    <span class="sz-artikelpreis mono"><?php echo wp_kses_post($produkt->get_price_html()); ?></span>

                    <span class="sz-menge">
                        <button type="button" data-sz-minus aria-label="<?php echo esc_attr__('weniger', 'sapelza-shop'); ?>">−</button>
                        <input type="number" min="1" value="1" data-sz-menge
                               aria-label="<?php echo esc_attr__('Menge', 'sapelza-shop'); ?>">
                        <button type="button" data-sz-plus aria-label="<?php echo esc_attr__('mehr', 'sapelza-shop'); ?>">+</button>
                    </span>

                    <button type="button" class="sz-inkorb" data-sz-inkorb
                            aria-label="<?php echo esc_attr__('In den Warenkorb', 'sapelza-shop'); ?>">+</button>
                </div>
                <?php
            }
            ?>
        </div>

        <p class="sz-artikelfuss">
            <?php
            printf(
                /* translators: %d ist die Zahl der gelisteten Artikel. */
                esc_html__('%d Artikel · auf den Namen klicken, um ihn zu ändern — leer heißt zurück zum Katalognamen', 'sapelza-shop'),
                (int) $lfd
            );
            ?>
        </p>

        <?php /* --- Kapitel: Etiketten ------------------------------------ */ ?>
        <section class="sz-kapitel sz-etikettenkapitel">
            <p class="kicker">
                <span class="kicker__punkt" aria-hidden="true"></span>
                <?php echo esc_html__('Am Regal', 'sapelza-shop'); ?>
            </p>

            <h2 class="sz-etiketten__titel"><?php echo esc_html__('Etiketten zum Aufkleben', 'sapelza-shop'); ?></h2>

            <p class="sz-etiketten__lead">
                <?php echo esc_html__('Wählen Sie oben aus, was ans Regal soll. Auf dem Etikett steht Ihre Bezeichnung — im Code selbst steht nur die Artikelnummer. Deshalb bleiben gedruckte Etiketten gültig, auch wenn Sie einen Artikel später anders nennen.', 'sapelza-shop'); ?>
            </p>

            <div class="sz-etiketten__wahl">
                <span class="sz-etiketten-zahl mono" data-sz-gewaehlt>0 ausgewählt</span>

                <div class="sz-etiketten__groessen" role="group"
                     aria-label="<?php echo esc_attr__('Etikettengröße', 'sapelza-shop'); ?>">
                    <button type="button" class="sz-groesse" data-sz-groesse="70x37" aria-pressed="true">
                        <?php echo esc_html__('70 × 37 mm · 24 Stück', 'sapelza-shop'); ?>
                    </button>
                    <button type="button" class="sz-groesse" data-sz-groesse="48x25" aria-pressed="false">
                        <?php echo esc_html__('48,5 × 25,4 mm · 44 Stück', 'sapelza-shop'); ?>
                    </button>
                </div>

                <button type="button" class="sz-erfassung__knopf" data-sz-drucken disabled>
                    <?php echo esc_html__('Bogen erzeugen', 'sapelza-shop'); ?>
                </button>
                <button type="button" class="sz-bogen__zu" data-sz-alle>
                    <?php echo esc_html__('Alle auswählen', 'sapelza-shop'); ?>
                </button>
            </div>

            <?php
            /*
             * Die Vorschau steht immer da, auch ohne Auswahl: als erster
             * Anstoss die fuenf zuletzt bestellten Artikel. Wer auswaehlt,
             * sieht seine Auswahl, hoechstens zehn — mehr sagt nichts mehr,
             * es macht die Seite nur lang.
             */
            ?>
            <p class="sz-etiketten__vorschautitel mono" data-sz-vorschautitel>
                <?php echo esc_html__('Zuletzt bestellt', 'sapelza-shop'); ?>
            </p>
            <div class="sz-etiketten__vorschau" data-sz-vorschau></div>
        </section>
    </div>
    <?php
    return ob_get_clean();
});
